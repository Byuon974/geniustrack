<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\Reservation;
use App\Enum\ReservationStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Somme des personnes déjà prévues sur un créneau qui CHEVAUCHE [debut, fin).
     * Intervalles semi-ouverts : deux créneaux qui se touchent (fin = debut) ne
     * se chevauchent PAS. Sert au contrôle de la capacité 15 (BF_3.9).
     *
     * Le verrou pessimiste (LOCK FOR UPDATE) est appliqué par le service appelant
     * dans une transaction ; ici on construit la requête de lecture.
     */
    public function sommePersonnesSurCreneau(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        bool $verrouiller = false,
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.nbPersonnesPrevues), 0)')
            ->where('r.statut IN (:actifs)')
            // chevauchement d'intervalles semi-ouverts : r.debut < fin ET r.fin > debut
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.dateFin > :debut')
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin);

        $query = $qb->getQuery();

        if ($verrouiller) {
            // Verrou pessimiste : bloque les écritures concurrentes sur les lignes lues
            // jusqu'à la fin de la transaction → empêche le dépassement de capacité.
            $query->setLockMode(LockMode::PESSIMISTIC_WRITE);
        }

        return (int) $query->getSingleScalarResult();
    }

    /**
     * Vérifie qu'une machine n'est pas déjà réservée sur un créneau qui chevauche.
     * Une machine = une seule session à la fois.
     */
    public function machineOccupeeSurCreneau(
        Machine $machine,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): bool {
        $count = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.machine = :machine')
            ->andWhere('r.statut IN (:actifs)')
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.dateFin > :debut')
            ->setParameter('machine', $machine)
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Nombre total de réservations rattachées à une machine, tous statuts
     * confondus (y compris annulées et reportées : elles font partie de
     * l'historique et portent la clé étrangère). Sert à décider si une machine
     * peut être supprimée ou doit seulement être passée hors service.
     */
    public function compterPourMachine(Machine $machine): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.machine = :machine')
            ->setParameter('machine', $machine)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * BF_7.1 : nombre de réservations par machine sur une période.
     * Retourne [ ['nom' => ..., 'total' => ...], ... ].
     */
    /**
     * Nombre de réservations par mois sur une période (non annulées), pour la
     * courbe d'activité de la supervision. Agrégation en PHP (portabilité DQL).
     *
     * @return array<int, array{mois: int, total: int}> indexé de 1 à 12 présents
     */
    public function reservationsParMois(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        // Agrégation SQL native : COUNT groupé par mois côté base. Ne ramène que
        // les mois présents (au plus douze lignes), au lieu de toutes les
        // réservations de la période. EXTRACT(MONTH ...) est standard SQL.
        $sql = <<<'SQL'
            SELECT EXTRACT(MONTH FROM date_debut)::int AS mois, COUNT(*) AS total
            FROM reservation
            WHERE date_debut >= :debut AND date_debut < :fin AND statut != :annulee
            GROUP BY mois
            ORDER BY mois
            SQL;

        $lignes = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'debut' => $debut->format('Y-m-d H:i:s'),
            'fin' => $fin->format('Y-m-d H:i:s'),
            'annulee' => ReservationStatut::Annulee->value,
        ])->fetchAllAssociative();

        return array_map(
            static fn (array $l) => ['mois' => (int) $l['mois'], 'total' => (int) $l['total']],
            $lignes
        );
    }

    /**
     * Total des minutes réservées par machine sur une période (réservations non
     * annulées). La durée de chaque réservation se déduit de ses bornes. Sert au
     * calcul du taux d'utilisation (minutes réservées rapportées à la capacité
     * d'ouverture), pour la supervision analytique du labo.
     *
     * @return array<int, array{nom: string, minutes: int}>
     */
    public function minutesReserveesParMachine(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        // Agrégation SQL : SUM de la durée stockée, groupé par machine. Ne ramène
        // qu'une ligne par machine, et non toutes les réservations de la période
        // (tient à l'échelle de milliers de réservations).
        $lignes = $this->createQueryBuilder('r')
            ->select('m.nom AS nom, COALESCE(SUM(r.dureeMinutes), 0) AS minutes')
            ->join('r.machine', 'm')
            ->where('r.dateDebut >= :debut')
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.statut != :annulee')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setParameter('annulee', ReservationStatut::Annulee->value)
            ->groupBy('m.nom')
            ->getQuery()
            ->getResult();

        return array_map(
            static fn (array $l) => ['nom' => $l['nom'], 'minutes' => (int) $l['minutes']],
            $lignes
        );
    }

    public function utilisationParMachine(\DateTimeImmutable $depuis): array
    {
        return $this->createQueryBuilder('r')
            ->select('m.nom AS nom, COUNT(r.id) AS total')
            ->join('r.machine', 'm')
            ->where('r.dateDebut >= :depuis')
            ->andWhere('r.statut != :annulee')
            ->setParameter('depuis', $depuis)
            ->setParameter('annulee', ReservationStatut::Annulee->value)
            ->groupBy('m.nom')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations dont le début tombe dans une plage de dates, relations
     * chargées (projet, machine, étudiant) pour éviter le N+1 à l'export.
     * Sert l'export annuel des données pour l'analyse temporelle hors application.
     *
     * @return Reservation[]
     */
    public function entrePeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        // Pas de addSelect des relations : il provoquait une erreur d'hydratation
        // (« Undefined array key »). Les relations (projet, machine, étudiant) se
        // chargent en lazy loading à l'usage, ce qui est fiable. La jointure sur
        // le projet reste utile si un filtre/tri sur le projet est ajouté ensuite.
        return $this->createQueryBuilder('r')
            ->where('r.dateDebut >= :debut')
            ->andWhere('r.dateDebut < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les réservations dont le début tombe dans une plage [debut, fin[.
     * COUNT simple sans jointure : sert aux indicateurs (ex. créneaux du jour),
     * sans charger les entités liées.
     */
    public function compterEntre(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.dateDebut >= :debut')
            ->andWhere('r.dateDebut < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * BF_3.22 : réservations à venir, pour le calendrier admin.
     *
     * @return Reservation[]
     */
    public function aVenir(): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.dateDebut >= :maintenant')
            ->andWhere('r.statut = :planifiee')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations à venir dont le projet appartient à l'étudiant donné.
     * Sert la vue calendrier de l'étudiant (BF_6.3 : vue selon le rôle ;
     * moindre privilège : il ne voit que ses propres créneaux).
     *
     * @return Reservation[]
     */
    public function aVenirParEtudiant(\App\Entity\User $etudiant): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.projet', 'p')
            ->where('r.dateDebut >= :maintenant')
            ->andWhere('r.statut = :planifiee')
            ->andWhere('p.etudiant = :etudiant')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->setParameter('etudiant', $etudiant)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations à venir dont le projet a été validé par le membre du staff
     * donné (formateur ou BDE). Périmètre de validation : moindre privilège,
     * il suit les projets qu'il a pris en charge, pas tout le FabLab.
     *
     * @return Reservation[]
     */
    public function aVenirParValideur(\App\Entity\User $valideur): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.projet', 'p')
            ->where('r.dateDebut >= :maintenant')
            ->andWhere('r.statut = :planifiee')
            ->andWhere('p.valideur = :valideur')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->setParameter('valideur', $valideur)
            ->orderBy('r.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrai si l'étudiant donné a une réservation (de l'un de ses projets) qui
     * chevauche le créneau [debut, fin). Sert l'affichage free/busy : on
     * distingue « occupé » (anonyme) de « votre réservation », sans rien révéler
     * des créneaux d'autrui.
     */
    public function etudiantOccupeCreneau(
        \App\Entity\User $etudiant,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): bool {
        $n = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->join('r.projet', 'p')
            ->where('r.statut IN (:actifs)')
            ->andWhere('r.dateDebut < :fin')
            ->andWhere('r.dateFin > :debut')
            ->andWhere('p.etudiant = :etudiant')
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->setParameter('etudiant', $etudiant)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $n > 0;
    }
}
