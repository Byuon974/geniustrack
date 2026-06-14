<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Entity\Reservation;
use App\Enum\ReservationStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Requêtes portant sur les occupations machine. Une occupation rattache une
 * machine à une session ; le créneau, le statut et l'effectif vivent sur la
 * session (voir SessionReservationRepository pour les requêtes par période,
 * capacité, quota et calendrier).
 *
 * Ne restent ici que les requêtes intrinsèquement liées à la machine : combien
 * d'occupations porte une machine (suppression), et le chevauchement d'une
 * machine donnée sur un créneau (jointure vers la session pour dates et statut).
 *
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Nombre total d'occupations rattachées à une machine, tous statuts de
     * session confondus (y compris annulées et reportées : elles font partie de
     * l'historique et portent la clé étrangère). Sert à décider si une machine
     * peut être supprimée ou doit seulement être passée hors service.
     */
    public function compterPourMachine(Machine $machine): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.machine = :machine')
            ->setParameter('machine', $machine)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vrai si la MACHINE donnée est déjà occupée par une session active dont le
     * créneau chevauche [debut, fin). Intervalles semi-ouverts : deux créneaux
     * qui se touchent (fin = debut) ne se chevauchent pas. La date et le statut
     * étant portés par la session, on joint s = session de l'occupation.
     *
     * Le verrou pessimiste de sérialisation est posé par le service sur la
     * MACHINE elle-même avant cet appel ; ici, simple lecture.
     */
    public function machineOccupeeSurCreneau(
        Machine $machine,
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): bool {
        $count = (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->join('o.session', 's')
            ->where('o.machine = :machine')
            ->andWhere('s.statut IN (:actifs)')
            ->andWhere('s.dateDebut < :fin')
            ->andWhere('s.dateFin > :debut')
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
}
