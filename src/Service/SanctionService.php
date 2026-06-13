<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Sanction;
use App\Entity\User;
use App\Repository\SanctionRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gère les sanctions des étudiants (BF_6.2).
 *
 * Règle métier : une annulation/report tardif (< 3 jours, détecté par
 * ReservationService::annuler) ajoute une sanction. Au SEUIL de sanctions
 * ACTIVES cumulées, le compte est automatiquement désactivé : l'étudiant ne
 * peut plus se connecter (le UserChecker bloque déjà les comptes inactifs).
 *
 * Modèle « ledger » (esprit Laravel) : chaque sanction est une LIGNE immuable,
 * et le compteur de sanctions actives se DÉRIVE de la table (plus de champ
 * nbSanctions figé sur User). On conserve ainsi qui a sanctionné, quand et
 * pourquoi. La règle vit ici, dans un service dédié.
 */
class SanctionService
{
    /** Nombre de sanctions actives déclenchant la désactivation (BF_6.2). */
    public const SEUIL_DESACTIVATION = 5;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SanctionRepository $sanctions,
        private readonly JournalService $journal,
    ) {
    }

    /**
     * Inflige une sanction à l'étudiant (une nouvelle ligne). Désactive le
     * compte si le seuil de sanctions actives est atteint. Renvoie true si la
     * sanction a entraîné la désactivation.
     *
     * @param User|null $auteur Admin à l'origine du geste, ou null si automatique.
     */
    public function sanctionner(User $etudiant, string $motif, ?User $auteur = null): bool
    {
        // Garde-fou RBAC : le système de sanctions vise les étudiants. Un membre
        // du staff (admin, formateur, BDE) n'est jamais sanctionné, ce qui évite
        // notamment qu'un admin se sanctionne lui-même ou se désactive.
        if ($this->estStaff($etudiant)) {
            return false;
        }

        $sanction = new Sanction($etudiant, $motif, $auteur);
        $this->em->persist($sanction);
        $etudiant->ajouterSanction($sanction);
        $this->em->flush();

        $actives = $this->sanctions->compterActives($etudiant);

        $desactive = false;
        if ($actives >= self::SEUIL_DESACTIVATION && $etudiant->estActif()) {
            $etudiant->setActif(false);
            $this->em->flush();
            $desactive = true;
        }

        // Trace l'événement métier (BF_8.1) via le service dédié.
        $this->journal->tracer(
            $etudiant,
            $desactive ? 'Sanction + désactivation' : 'Sanction',
            $motif,
        );

        return $desactive;
    }

    /** Un membre du staff n'est pas soumis au régime de sanctions étudiant. */
    private function estStaff(User $user): bool
    {
        foreach (['ROLE_ADMIN', 'ROLE_FORMATEUR', 'ROLE_BDE'] as $role) {
            if (\in_array($role, $user->getRoles(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lève la dernière sanction active (geste admin) : la ligne est horodatée
     * comme levée, pas supprimée.
     *
     * Lever une sanction ne réactive PAS le compte, même si l'étudiant repasse
     * sous le seuil. La réactivation est un geste admin distinct et délibéré
     * (édition du compte, ou `make activer EMAIL=...`) : rendre un accès est une
     * décision consciente, pas un effet de bord d'une levée. Symétrique de la
     * prudence appliquée à la désactivation (DEC-044).
     */
    public function leverSanction(User $etudiant): void
    {
        $derniere = $this->sanctions->derniereActive($etudiant);
        if (null === $derniere) {
            return;
        }

        $derniere->lever();
        $this->em->flush();
    }

    /**
     * Nombre de sanctions actives d'un étudiant (dérivé). Exposé pour les vues
     * et l'ancien point d'accès User::getNbSanctions().
     */
    public function nombreActives(User $etudiant): int
    {
        return $this->sanctions->compterActives($etudiant);
    }
}
