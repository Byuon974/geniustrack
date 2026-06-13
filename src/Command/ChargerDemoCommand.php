<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Notification;
use App\Entity\MouvementStock;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Entity\Sanction;
use App\Entity\User;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Repository\MachineRepository;
use App\Repository\ConsommableRepository;
use App\Repository\ProjetRepository;
use App\Repository\UserRepository;
use App\Enum\MotifMouvement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée des données de démonstration pour tester les fonctionnalités : comptes
 * (rôles RBAC), puis projets dans des statuts variés (pour exercer le workflow
 * de validation, la galerie, les notifications et les sanctions), réservations
 * et quelques notifications/sanctions d'exemple.
 *
 * Idempotente : ne recrée pas une donnée déjà présente (vérif par email/titre).
 * À n'utiliser qu'en développement. Mot de passe commun : « demo1234! ».
 */
#[AsCommand(name: 'app:charger-demo', description: 'Crée comptes, projets, demandes et réservations de test.')]
class ChargerDemoCommand extends Command
{
    private const MOT_DE_PASSE = 'demo1234!';

    /** [email, prénom, nom, rôles]. */
    private const COMPTES = [
        ['jean.dupont@cci.re', 'Jean', 'Dupont', ['ROLE_ETUDIANT']],
        ['marie.martin@cci.re', 'Marie', 'Martin', ['ROLE_ETUDIANT']],
        ['luc.payet@cci.re', 'Luc', 'Payet', ['ROLE_ETUDIANT']],
        ['paul.formateur@cci.re', 'Paul', 'Lebon', ['ROLE_FORMATEUR']],
        ['sophie.bde@cci.re', 'Sophie', 'Hoarau', ['ROLE_BDE']],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly UserRepository $users,
        private readonly ProjetRepository $projets,
        private readonly MachineRepository $machines,
        private readonly ConsommableRepository $consommables,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $comptes = $this->chargerComptes();
        $io->success(sprintf('%d compte(s) de démonstration créé(s).', $comptes));

        $projets = $this->chargerProjets($io);
        $io->success(sprintf('%d projet(s) de démonstration créé(s).', $projets));

        $io->table(
            ['Rôle', 'E-mail', 'Mot de passe'],
            [
                ['Étudiant', 'jean.dupont@cci.re', self::MOT_DE_PASSE],
                ['Étudiant', 'marie.martin@cci.re', self::MOT_DE_PASSE],
                ['Étudiant', 'luc.payet@cci.re', self::MOT_DE_PASSE],
                ['Formateur', 'paul.formateur@cci.re', self::MOT_DE_PASSE],
                ['BDE', 'sophie.bde@cci.re', self::MOT_DE_PASSE],
            ],
        );
        $io->note('Connectez-vous en étudiant pour voir vos projets et notifications, en formateur/BDE pour valider les demandes en attente.');

        return Command::SUCCESS;
    }

    private function chargerComptes(): int
    {
        $crees = 0;
        foreach (self::COMPTES as [$email, $prenom, $nom, $roles]) {
            if ($this->users->findOneBy(['email' => $email])) {
                continue;
            }
            $u = (new User())
                ->setEmail($email)
                ->setPrenom($prenom)
                ->setNom($nom)
                ->setRoles($roles);
            $u->setPassword($this->hasher->hashPassword($u, self::MOT_DE_PASSE));
            $this->em->persist($u);
            ++$crees;
        }
        $this->em->flush();

        return $crees;
    }

    private function chargerProjets(SymfonyStyle $io): int
    {
        $jean = $this->users->findOneBy(['email' => 'jean.dupont@cci.re']);
        $marie = $this->users->findOneBy(['email' => 'marie.martin@cci.re']);
        $luc = $this->users->findOneBy(['email' => 'luc.payet@cci.re']);
        $formateur = $this->users->findOneBy(['email' => 'paul.formateur@cci.re']);
        $bde = $this->users->findOneBy(['email' => 'sophie.bde@cci.re']);

        if (null === $jean || null === $formateur || null === $bde) {
            $io->warning('Comptes de démo absents : projets non chargés.');

            return 0;
        }

        $machinesActives = $this->machines->actives();
        $machine = $machinesActives[0] ?? null;

        // [titre, description, type, statut, étudiant, valideur, partage, motifRefus]
        $defs = [
            ['Boîtier capteur température', 'Boîtier imprimé en 3D pour un capteur DHT22.', ProjetType::Pedagogique, ProjetStatut::EnAttente, $jean, null, false, null],
            ['Support smartphone réglable', 'Support de bureau articulé, projet personnel.', ProjetType::Personnel, ProjetStatut::EnAttente, $marie, null, false, null],
            ['Maquette pont en treillis', 'Découpe laser de pièces pour une maquette de pont.', ProjetType::Pedagogique, ProjetStatut::Valide, $jean, $formateur, false, null],
            ['Porte-clés promo 2026', 'Gravure de porte-clés pour la promotion.', ProjetType::Personnel, ProjetStatut::Refuse, $luc, $bde, false, 'Fichier non conforme : épaisseur de trait trop fine pour la graveuse.'],
            ['Engrenage planétaire', 'Prototype d\'engrenage pour un projet de robotique.', ProjetType::Pedagogique, ProjetStatut::EnCours, $marie, $formateur, false, null],
            ['Lampe origami', 'Abat-jour en PETG translucide, terminé et partagé.', ProjetType::Personnel, ProjetStatut::Termine, $jean, $bde, true, null],
            ['Boîte à bijoux gravée', 'Petite boîte en bois gravée, terminée et partagée.', ProjetType::Pedagogique, ProjetStatut::Termine, $luc, $formateur, true, null],
            // Supports de jeux d'essai (titres préfixés « [Test] » pour être repérables).
            // Cas nominal : projet validé, prêt à réserver.
            ['[Test] Réservation nominale', 'Projet validé servant au jeu d\'essai du cas nominal de réservation.', ProjetType::Pedagogique, ProjetStatut::Valide, $jean, $formateur, false, null],
            // Cas d'erreur : projet en brouillon, la réservation doit être refusée.
            ['[Test] Projet brouillon', 'Projet au statut brouillon servant au jeu d\'essai du refus de réservation.', ProjetType::Pedagogique, ProjetStatut::Brouillon, $marie, null, false, null],
            // Cas limite : projet validé utilisé pour tenter une réservation sur un créneau saturé.
            ['[Test] Créneau saturé', 'Projet validé servant au jeu d\'essai du cas limite (capacité atteinte).', ProjetType::Pedagogique, ProjetStatut::Valide, $luc, $formateur, false, null],
        ];

        $crees = 0;
        foreach ($defs as [$titre, $desc, $type, $statut, $etudiant, $valideur, $partage, $motif]) {
            if ($this->projets->findOneBy(['titre' => $titre])) {
                continue;
            }
            $projet = (new Projet())
                ->setTitre($titre)
                ->setDescription($desc)
                ->setType($type)
                ->setStatut($statut)
                ->setEtudiant($etudiant)
                ->setValideur($valideur)
                ->setPartageAutorise($partage)
                ->setMotifRefus($motif);
            if (null !== $machine) {
                $projet->addMachine($machine);
            }
            $this->em->persist($projet);
            ++$crees;
        }
        $this->em->flush();

        $this->chargerReservations($io);
        $this->chargerCreneauSature($io);
        $this->chargerMouvementsStock($io);

        // Notifications de démo pour l'étudiant Jean (statuts de ses projets).
        if (0 === (int) $this->em->getRepository(Notification::class)->count(['destinataire' => $jean])) {
            $this->em->persist(new Notification($jean, 'projet', 'Votre projet « Maquette pont en treillis » est maintenant : Validé.', null));
            $this->em->persist(new Notification($jean, 'projet', 'Votre projet « Lampe origami » est maintenant : Terminé.', null));
        }

        // Une sanction active de démo sur Luc, pour exercer la fiche utilisateur.
        if (null !== $luc && 0 === (int) $this->em->getRepository(Sanction::class)->count(['etudiant' => $luc])) {
            $this->em->persist(new Sanction($luc, 'Annulation tardive (démo)', null));
        }

        $this->em->flush();

        return $crees;
    }

    /**
     * Crée une série dense de réservations FUTURES et planifiées, pour que le
     * calendrier (qui n'affiche que les réservations à venir et planifiées) soit
     * bien rempli : deux types (préparation/réalisation), plusieurs machines,
     * étalées sur les trois prochaines semaines, à des horaires ouvrés.
     */
    private function chargerReservations(SymfonyStyle $io): void
    {
        // On rattache les réservations à des projets actifs (en cours ou validés),
        // seuls états où une réservation a du sens. Les projets de support aux jeux
        // d'essai (préfixe « [Test] ») sont exclus : ils servent à des scénarios
        // précis et ne doivent pas être encombrés de réservations de démo.
        $projets = array_values(array_filter(
            $this->projets->findAll(),
            static fn (Projet $p): bool => \in_array($p->getStatut(), [ProjetStatut::EnCours, ProjetStatut::Valide], true)
                && !str_starts_with($p->getTitre(), '[Test]'),
        ));
        $machines = $this->machines->actives();

        if ([] === $projets || [] === $machines) {
            $io->warning('Projets actifs ou machines absents : réservations non chargées.');

            return;
        }

        // Déjà peuplé ? On ne duplique pas.
        if ($this->em->getRepository(Reservation::class)->count([]) > 0) {
            $io->note('Réservations déjà présentes : chargement ignoré.');

            return;
        }

        // Créneaux : [jours dans le futur, heure, type, nb personnes].
        $creneaux = [
            // [jours, heure, minute, type, nb personnes, durée minutes].
            // Durées variées et heures au pas de 30 min : la supervision (taux
            // d'utilisation) et le créneau souple sont ainsi représentés en démo.
            [1, 9, 0, ReservationType::Preparation, 1, 30],
            [1, 14, 0, ReservationType::Realisation, 2, 120],
            [2, 10, 30, ReservationType::Realisation, 3, 90],
            [3, 9, 0, ReservationType::Preparation, 1, 30],
            [4, 15, 0, ReservationType::Realisation, 2, 60],
            [5, 11, 0, ReservationType::Realisation, 4, 150],
            [8, 9, 30, ReservationType::Preparation, 2, 30],
            [9, 14, 0, ReservationType::Realisation, 2, 120],
            [10, 10, 0, ReservationType::Realisation, 1, 240],
            [12, 13, 30, ReservationType::Preparation, 1, 30],
            [15, 9, 0, ReservationType::Realisation, 3, 90],
            [16, 14, 30, ReservationType::Realisation, 2, 60],
        ];

        $cree = 0;
        foreach ($creneaux as $i => [$jours, $heure, $minute, $type, $nb, $duree]) {
            $projet = $projets[$i % \count($projets)];
            $machine = $machines[$i % \count($machines)];

            $debut = (new \DateTimeImmutable(sprintf('+%d days', $jours)))->setTime($heure, $minute);

            // definirCreneau pose début, fin et durée stockée de façon cohérente
            // (créneau à durée variable, comme le wizard de réservation).
            $resa = (new Reservation())
                ->setProjet($projet)
                ->setMachine($machine)
                ->setType($type)
                ->setStatut(ReservationStatut::Planifiee)
                ->definirCreneau($debut, $duree)
                ->setNbPersonnesPrevues($nb);
            $this->em->persist($resa);
            ++$cree;
        }

        // Historique : réservations passées réparties sur les mois écoulés de
        // l'année en cours. Sans cet historique, la supervision (taux machines,
        // réservations par mois) n'a presque rien à montrer, car les réservations
        // de démo ci-dessus sont toutes dans le futur proche. On répartit sur
        // chaque machine et chaque mois écoulé pour des indicateurs réalistes.
        $cree += $this->chargerHistoriqueReservations($projets, $machines);

        $this->em->flush();
        $io->success(sprintf('%d réservation(s) chargée(s) (futures + historique).', $cree));
    }

    /**
     * Crée un historique de réservations terminées, réparti sur les mois écoulés
     * de l'année en cours et sur l'ensemble des machines actives. Donne de la
     * matière à la supervision : taux d'utilisation non nuls et courbe mensuelle
     * lisible. Volumes et durées variés pour rester crédible.
     *
     * @param list<Projet>  $projets
     * @param list<Machine> $machines
     */
    private function chargerHistoriqueReservations(array $projets, array $machines): int
    {
        $maintenant = new \DateTimeImmutable('today');
        $moisCourant = (int) $maintenant->format('n');
        $annee = (int) $maintenant->format('Y');

        // Durées plausibles (minutes) et heures d'ouverture (8h-16h30, pas 30).
        $durees = [60, 90, 120, 150, 180, 240];
        $heures = [[8, 30], [9, 0], [10, 30], [13, 0], [14, 0], [15, 30]];

        $cree = 0;
        // Pour chaque mois déjà écoulé (de janvier au mois courant inclus).
        for ($mois = 1; $mois <= $moisCourant; ++$mois) {
            // Nombre de réservations du mois : croissant et varié, plafonné.
            $nbDuMois = 3 + ($mois % 4);

            for ($k = 0; $k < $nbDuMois; ++$k) {
                $jour = 2 + (($k * 5 + $mois * 3) % 24);   // jour 2..25, réparti
                [$h, $min] = $heures[($k + $mois) % \count($heures)];

                // Si on est sur le mois courant, ne pas dépasser aujourd'hui.
                $date = (new \DateTimeImmutable(sprintf('%d-%02d-%02d', $annee, $mois, $jour)))->setTime($h, $min);
                if ($date >= $maintenant) {
                    continue;
                }

                $machine = $machines[($k + $mois) % \count($machines)];
                $projet = $projets[($k + $mois) % \count($projets)];
                $nb = 1 + (($k + $mois) % 5); // 1..5 personnes

                // Une prépa sur trois (30 min imposées), sinon réalisation à durée variable.
                $estPrepa = (0 === $k % 3);
                $type = $estPrepa ? ReservationType::Preparation : ReservationType::Realisation;
                $duree = $estPrepa ? 30 : $durees[($k + $mois) % \count($durees)];

                $resa = (new Reservation())
                    ->setProjet($projet)
                    ->setMachine($machine)
                    ->setType($type)
                    ->setStatut(ReservationStatut::Effectuee)
                    ->definirCreneau($date, $duree)
                    ->setNbPersonnesPrevues($nb);
                $this->em->persist($resa);
                ++$cree;
            }
        }

        return $cree;
    }

    /**
     * Prépare le jeu d'essai du cas limite : un créneau saturé à la capacité
     * maximale du FabLab (15 personnes). On pose, sur une machine dédiée et un
     * créneau bien identifiable, des réservations cumulant exactement 15
     * personnes. Tenter d'en ajouter une de plus sur ce créneau doit être refusé.
     *
     * Le créneau est volontairement placé loin des réservations de démo (dans
     * trois semaines, à 8h) pour éviter toute interférence de capacité.
     */
    private function chargerCreneauSature(SymfonyStyle $io): void
    {
        $projet = $this->projets->findOneBy(['titre' => '[Test] Créneau saturé']);
        $machines = $this->machines->actives();
        $machine = $machines[0] ?? null;

        if (null === $projet || null === $machine) {
            $io->warning('Support du créneau saturé absent : jeu d\'essai du cas limite non chargé.');

            return;
        }

        // Créneau cible : dans 21 jours à 8h00, durée 1h. Repère explicite pour la recette.
        $debut = (new \DateTimeImmutable('+21 days'))->setTime(8, 0);

        // Deux réservations cumulant 15 personnes (8 + 7) : la capacité du créneau
        // est atteinte sans qu'une seule réservation dépasse la limite par ligne.
        $repartition = [8, 7];

        // Idempotence : si une réservation existe déjà sur ce créneau pour ce projet,
        // on considère le cas limite déjà chargé.
        $existante = $this->em->getRepository(Reservation::class)->findOneBy([
            'projet' => $projet,
            'machine' => $machine,
            'dateDebut' => $debut,
        ]);
        if (null !== $existante) {
            return;
        }

        foreach ($repartition as $nb) {
            $resa = (new Reservation())
                ->setProjet($projet)
                ->setMachine($machine)
                ->setType(ReservationType::Realisation)
                ->setStatut(ReservationStatut::Planifiee)
                ->definirCreneau($debut, 60)
                ->setNbPersonnesPrevues($nb);
            $this->em->persist($resa);
        }

        $this->em->flush();
        $io->success(sprintf(
            'Créneau saturé chargé (15 personnes le %s sur « %s ») pour le jeu d\'essai du cas limite.',
            $debut->format('d/m à H\hi'),
            $machine->getNom(),
        ));
    }

    /**
     * Charge quelques mouvements de stock datés sur les dernières semaines, pour
     * que la supervision des consommables (fluctuations) soit parlante en démo.
     * Les mouvements sont créés directement avec leur date (contexte de seed),
     * et la quantité après chaque mouvement est tenue cohérente le long de la
     * série. Le niveau final du consommable est aligné sur le dernier mouvement.
     */
    private function chargerMouvementsStock(SymfonyStyle $io): void
    {
        // Séries de mouvements étalées sur les mois écoulés de l'année, pour que
        // la courbe « niveau de stock dans le temps » (supervision) évolue sur
        // toute la période, et pas seulement sur le dernier mois. Chaque entrée :
        // [mois (1..12), jour, variation, motif].
        $maintenant = new \DateTimeImmutable('today');
        $moisCourant = (int) $maintenant->format('n');
        $annee = (int) $maintenant->format('Y');

        $series = [
            'Bobine PLA gris satiné' => [
                [1, 12, 10, MotifMouvement::Reassort],
                [2, 8, -3, MotifMouvement::ConsommationProjet],
                [3, 15, -2, MotifMouvement::ConsommationProjet],
                [4, 6, 8, MotifMouvement::Reassort],
                [5, 20, -4, MotifMouvement::ConsommationProjet],
                [6, 10, 6, MotifMouvement::Reassort],
                [7, 4, -2, MotifMouvement::ConsommationProjet],
                [8, 18, -3, MotifMouvement::ConsommationProjet],
                [9, 9, 5, MotifMouvement::Reassort],
                [10, 14, -2, MotifMouvement::ConsommationProjet],
                [11, 7, -1, MotifMouvement::ConsommationProjet],
                [12, 3, 4, MotifMouvement::Reassort],
            ],
            'Bobine PETG vert' => [
                [1, 20, 6, MotifMouvement::Reassort],
                [2, 14, -1, MotifMouvement::ConsommationProjet],
                [3, 9, -2, MotifMouvement::Perte],
                [4, 22, 4, MotifMouvement::Reassort],
                [5, 11, -1, MotifMouvement::ConsommationProjet],
                [6, 16, -2, MotifMouvement::ConsommationProjet],
                [7, 8, 5, MotifMouvement::Reassort],
                [8, 25, -1, MotifMouvement::ConsommationProjet],
                [9, 13, -1, MotifMouvement::ConsommationProjet],
                [10, 6, 3, MotifMouvement::Reassort],
                [11, 19, -2, MotifMouvement::ConsommationProjet],
                [12, 2, 2, MotifMouvement::Inventaire],
            ],
            'Bobine TPU noir' => [
                [2, 10, 5, MotifMouvement::Reassort],
                [4, 12, -1, MotifMouvement::ConsommationProjet],
                [6, 5, 3, MotifMouvement::Reassort],
                [8, 17, -2, MotifMouvement::ConsommationProjet],
                [10, 8, 2, MotifMouvement::Inventaire],
                [12, 1, -1, MotifMouvement::ConsommationProjet],
            ],
        ];

        $cree = 0;
        foreach ($series as $nom => $mouvements) {
            $consommable = $this->consommables->findOneBy(['nom' => $nom]);
            if (null === $consommable) {
                continue;
            }

            // On ne garde que les mouvements déjà survenus (mois <= mois courant).
            $passes = array_values(array_filter(
                $mouvements,
                static fn (array $m): bool => $m[0] <= $moisCourant,
            ));

            // Reconstitution cohérente du niveau : on part du niveau courant moins
            // la somme des variations passées, puis on rejoue chronologiquement.
            $sommeVariations = array_sum(array_map(static fn (array $m): int => $m[2], $passes));
            $quantite = max(0, $consommable->getQuantite() - $sommeVariations);

            foreach ($passes as [$mois, $jour, $variation, $motif]) {
                $quantite = max(0, $quantite + $variation);
                $date = (new \DateTimeImmutable(sprintf('%d-%02d-%02d', $annee, $mois, $jour)))->setTime(10, 0);

                $this->em->persist((new MouvementStock())
                    ->setConsommable($consommable)
                    ->setVariation($variation)
                    ->setMotif($motif)
                    ->setQuantiteApres($quantite)
                    ->setEffectueLe($date));
                ++$cree;
            }

            // Le niveau courant du consommable reflète le dernier mouvement passé.
            $consommable->setQuantite($quantite);
        }

        $this->em->flush();
        $io->success(sprintf('%d mouvement(s) de stock chargé(s) (étalés sur l\'année).', $cree));
    }
}
