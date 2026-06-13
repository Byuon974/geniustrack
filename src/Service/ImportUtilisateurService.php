<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Import en masse d'utilisateurs depuis un fichier CSV ou XLSX (BF_6.x).
 *
 * Pensé pour amorcer des promotions entières (centaines d'étudiants) sans
 * saisie manuelle. Le service fait DEUX choses séparées, jamais mélangées :
 *  - analyser() : lit le fichier et valide chaque ligne SANS rien écrire ;
 *    l'admin voit un aperçu avec les erreurs avant de confirmer.
 *  - importer() : n'écrit en base que les lignes valides, une fois confirmé.
 *
 * Cette séparation (RETEX import CMS/CRUD) évite l'écriture aveugle : on ne
 * crée jamais de comptes à l'aveugle, on montre d'abord ce qui passera.
 *
 * Colonnes attendues (en-tête, ordre libre) : prenom, nom, email, role.
 * Le rôle accepte des libellés lisibles (etudiant, formateur, bde, admin).
 */
class ImportUtilisateurService
{
    /** Libellés acceptés dans la colonne « role » vers le rôle technique. */
    private const ROLES = [
        'etudiant' => 'ROLE_ETUDIANT',
        'étudiant' => 'ROLE_ETUDIANT',
        'formateur' => 'ROLE_FORMATEUR',
        'bde' => 'ROLE_BDE',
        'admin' => 'ROLE_ADMIN',
    ];

    private const COLONNES_REQUISES = ['prenom', 'nom', 'email', 'role'];

    public function __construct(
        private readonly UserRepository $utilisateurs,
        private readonly ValidatorInterface $validator,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly \Doctrine\ORM\EntityManagerInterface $em,
    ) {
    }

    /**
     * Analyse un fichier et renvoie un aperçu ligne par ligne, sans écrire.
     *
     * @return array{lignes: list<array{numero: int, donnees: array<string,string>, erreurs: list<string>, valide: bool}>, entetesManquants: list<string>, valides: int, invalides: int}
     */
    public function analyser(string $chemin): array
    {
        $feuille = IOFactory::load($chemin)->getActiveSheet();
        $rangs = $feuille->toArray(null, true, false, false);

        if ([] === $rangs) {
            return ['lignes' => [], 'entetesManquants' => self::COLONNES_REQUISES, 'valides' => 0, 'invalides' => 0];
        }

        // Première ligne = en-têtes. On normalise (minuscules, sans accents parasites).
        $entetes = array_map(
            static fn ($v) => strtolower(trim((string) $v)),
            array_shift($rangs)
        );
        $indexParColonne = array_flip($entetes);

        $entetesManquants = [];
        foreach (self::COLONNES_REQUISES as $colonne) {
            if (!isset($indexParColonne[$colonne])) {
                $entetesManquants[] = $colonne;
            }
        }
        if ([] !== $entetesManquants) {
            return ['lignes' => [], 'entetesManquants' => $entetesManquants, 'valides' => 0, 'invalides' => 0];
        }

        // Emails déjà présents en base : on les signale comme doublons.
        $emailsExistants = $this->emailsExistants();
        $emailsVus = [];

        $lignes = [];
        $valides = 0;
        $invalides = 0;
        $numero = 1; // 1 = en-tête ; les données commencent à 2.

        foreach ($rangs as $rang) {
            ++$numero;
            // Ligne entièrement vide : on l'ignore silencieusement.
            if ('' === trim(implode('', array_map(static fn ($c) => (string) $c, $rang)))) {
                continue;
            }

            $donnees = [
                'prenom' => trim((string) ($rang[$indexParColonne['prenom']] ?? '')),
                'nom' => trim((string) ($rang[$indexParColonne['nom']] ?? '')),
                'email' => trim((string) ($rang[$indexParColonne['email']] ?? '')),
                'role' => strtolower(trim((string) ($rang[$indexParColonne['role']] ?? ''))),
            ];

            $erreurs = $this->validerLigne($donnees, $emailsExistants, $emailsVus);
            $valide = [] === $erreurs;
            if ($valide) {
                ++$valides;
                $emailsVus[strtolower($donnees['email'])] = true;
            } else {
                ++$invalides;
            }

            $lignes[] = ['numero' => $numero, 'donnees' => $donnees, 'erreurs' => $erreurs, 'valide' => $valide];
        }

        return ['lignes' => $lignes, 'entetesManquants' => [], 'valides' => $valides, 'invalides' => $invalides];
    }

    /**
     * Importe uniquement les lignes valides du fichier. Renvoie le nombre de
     * comptes créés. Les lignes invalides sont ignorées (déjà signalées à
     * l'aperçu).
     */
    public function importer(string $chemin): int
    {
        $analyse = $this->analyser($chemin);
        $crees = 0;

        foreach ($analyse['lignes'] as $ligne) {
            if (!$ligne['valide']) {
                continue;
            }
            $d = $ligne['donnees'];

            $user = (new User())
                ->setPrenom($d['prenom'])
                ->setNom($d['nom'])
                ->setEmail($d['email'])
                ->setRoles([self::ROLES[$d['role']]]);

            // Mot de passe aléatoire : l'utilisateur le réinitialisera à la
            // première connexion (parcours « mot de passe oublié »).
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(16))));

            $this->em->persist($user);
            ++$crees;
        }

        $this->em->flush();

        return $crees;
    }

    /**
     * Valide une ligne. Retourne la liste des erreurs (vide = ligne valide).
     *
     * @param array<string,string> $donnees
     * @param array<string,true>   $emailsExistants
     * @param array<string,true>   $emailsVus
     *
     * @return list<string>
     */
    private function validerLigne(array $donnees, array $emailsExistants, array $emailsVus): array
    {
        $erreurs = [];

        if ('' === $donnees['prenom']) {
            $erreurs[] = 'Prénom manquant.';
        }
        if ('' === $donnees['nom']) {
            $erreurs[] = 'Nom manquant.';
        }

        $email = $donnees['email'];
        if ('' === $email) {
            $erreurs[] = 'E-mail manquant.';
        } elseif (!str_ends_with($email, '@cci.re')) {
            $erreurs[] = 'L\'e-mail doit se terminer par @cci.re.';
        } elseif (isset($emailsExistants[strtolower($email)])) {
            $erreurs[] = 'Un compte existe déjà avec cet e-mail.';
        } elseif (isset($emailsVus[strtolower($email)])) {
            $erreurs[] = 'E-mail en double dans le fichier.';
        }

        if ('' === $donnees['role']) {
            $erreurs[] = 'Rôle manquant.';
        } elseif (!isset(self::ROLES[$donnees['role']])) {
            $erreurs[] = sprintf('Rôle inconnu « %s » (attendus : etudiant, formateur, bde, admin).', $donnees['role']);
        }

        return $erreurs;
    }

    /**
     * Emails déjà en base, en minuscules, pour détecter les doublons.
     *
     * @return array<string,true>
     */
    private function emailsExistants(): array
    {
        $map = [];
        foreach ($this->utilisateurs->findAll() as $u) {
            $map[strtolower($u->getEmail())] = true;
        }

        return $map;
    }
}
