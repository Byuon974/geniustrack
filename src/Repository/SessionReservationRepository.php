<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SessionReservation;
use App\Entity\User;
use App\Enum\ReservationStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes portant sur les sessions de réservation. La session est l'objet qui
 * porte le créneau (dates), l'effectif et le statut : capacité, quota,
 * disponibilité, supervision, export et calendrier raisonnent donc à ce niveau.
 *
 * Le chevauchement d'une MACHINE donnée (une occupation a-t-elle lieu sur ce
 * créneau ?) vit dans ReservationRepository, car il filtre par machine ; il
 * joint la session pour les dates et le statut.
 *
 * @extends ServiceEntityRepository<SessionReservation>
 */
class SessionReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SessionReservation::class);
    }

    /**
     * Somme des personnes déjà prévues sur les sessions qui CHEVAUCHENT
     * [debut, fin). Intervalles semi-ouverts : deux créneaux qui se touchent
     * (fin = debut) ne se chevauchent PAS. Sert au contrôle de la capacité 15
     * (BF_3.9). L'effectif étant porté UNE fois par la session, la somme est
     * directe : plus d'artefact de lignes à 0.
     *
     * Verrou pessimiste (DEC-098) : PostgreSQL interdit « FOR UPDATE » sur un
     * agrégat. On verrouille donc d'abord les LIGNES de session concernées par
     * une requête sans agrégat (SELECT ... FOR UPDATE), ce qui bloque les
     * écritures concurrentes jusqu'à la fin de la transaction, PUIS on calcule
     * la somme sans verrou. Le verrou de lignes suffit à empêcher le
     * dépassement ; l'agrégat reste cohérent dans la transaction.
     */
    public function sommePersonnesSurCreneau(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
        bool $verrouiller = false,
    ): int {
        if ($verrouiller) {
            $this->createQueryBuilder('sl')
                ->select('sl.id')
                ->where('sl.statut IN (:actifs)')
                ->andWhere('sl.dateDebut < :fin')
                ->andWhere('sl.dateFin > :debut')
                ->setParameter('actifs', [
                    ReservationStatut::Planifiee->value,
                    ReservationStatut::Effectuee->value,
                ])
                ->setParameter('debut', $debut)
                ->setParameter('fin', $fin)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getResult();
        }

        return (int) $this->createQueryBuilder('s')
            ->select('COALESCE(SUM(s.nbPersonnes), 0)')
            ->where('s.statut IN (:actifs)')
            // chevauchement d'intervalles semi-ouverts : s.debut < fin ET s.fin > debut
            ->andWhere('s.dateDebut < :fin')
            ->andWhere('s.dateFin > :debut')
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Nombre de sessions par mois sur une période (non annulées), pour la courbe
     * d'activité de la supervision. Agrégation SQL native groupée par mois (au
     * plus douze lignes), plutôt que de ramener toutes les sessions.
     *
     * @return array<int, array{mois: int, total: int}>
     */
    public function reservationsParMois(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $sql = <<<'SQL'
            SELECT EXTRACT(MONTH FROM date_debut)::int AS mois, COUNT(*) AS total
            FROM session_reservation
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
     * Total des minutes réservées par machine sur une période (sessions non
     * annulées). Chaque occupation hérite de la durée de sa session ; on somme
     * donc la durée de la session par machine occupée. Sert au taux
     * d'utilisation de la supervision analytique.
     *
     * @return array<int, array{nom: string, minutes: int}>
     */
    public function minutesReserveesParMachine(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $lignes = $this->createQueryBuilder('s')
            ->select('m.nom AS nom, COALESCE(SUM(s.dureeMinutes), 0) AS minutes')
            ->join('s.occupations', 'o')
            ->join('o.machine', 'm')
            ->where('s.dateDebut >= :debut')
            ->andWhere('s.dateDebut < :fin')
            ->andWhere('s.statut != :annulee')
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

    /**
     * Nombre d'occupations par machine depuis une date (sessions non annulées),
     * pour le classement d'utilisation des machines.
     *
     * @return array<int, array{nom: string, total: int}>
     */
    public function utilisationParMachine(\DateTimeImmutable $depuis): array
    {
        return $this->createQueryBuilder('s')
            ->select('m.nom AS nom, COUNT(o.id) AS total')
            ->join('s.occupations', 'o')
            ->join('o.machine', 'm')
            ->where('s.dateDebut >= :depuis')
            ->andWhere('s.statut != :annulee')
            ->setParameter('depuis', $depuis)
            ->setParameter('annulee', ReservationStatut::Annulee->value)
            ->groupBy('m.nom')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions dont le début tombe dans une plage [debut, fin[, pour l'export
     * annuel. Les relations (projet, occupations, machines) se chargent en lazy
     * loading à l'usage.
     *
     * @return SessionReservation[]
     */
    public function entrePeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.dateDebut >= :debut')
            ->andWhere('s.dateDebut < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les sessions dont le début tombe dans une plage [debut, fin[.
     */
    public function compterEntre(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.dateDebut >= :debut')
            ->andWhere('s.dateDebut < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * BF_3.22 : sessions à venir et planifiées, pour le calendrier admin.
     *
     * @return SessionReservation[]
     */
    public function aVenir(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.dateDebut >= :maintenant')
            ->andWhere('s.statut = :planifiee')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions à venir dont le projet appartient à l'étudiant donné (BF_6.3,
     * moindre privilège : il ne voit que ses propres créneaux).
     *
     * @return SessionReservation[]
     */
    public function aVenirParEtudiant(User $etudiant): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.projet', 'p')
            ->where('s.dateDebut >= :maintenant')
            ->andWhere('s.statut = :planifiee')
            ->andWhere('p.etudiant = :etudiant')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->setParameter('etudiant', $etudiant)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Sessions à venir dont le projet a été validé par le membre du staff donné
     * (périmètre de validation, moindre privilège).
     *
     * @return SessionReservation[]
     */
    public function aVenirParValideur(User $valideur): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.projet', 'p')
            ->where('s.dateDebut >= :maintenant')
            ->andWhere('s.statut = :planifiee')
            ->andWhere('p.valideur = :valideur')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('planifiee', ReservationStatut::Planifiee->value)
            ->setParameter('valideur', $valideur)
            ->orderBy('s.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrai si l'étudiant donné a une session (de l'un de ses projets) qui
     * chevauche le créneau [debut, fin). Sert l'affichage free/busy : distinguer
     * « occupé » (anonyme) de « votre réservation », sans rien révéler d'autrui.
     */
    public function etudiantOccupeCreneau(
        User $etudiant,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): bool {
        $n = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->join('s.projet', 'p')
            ->where('s.statut IN (:actifs)')
            ->andWhere('s.dateDebut < :fin')
            ->andWhere('s.dateFin > :debut')
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
