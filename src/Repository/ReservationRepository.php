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

    /**
     * Occupations actives (sessions planifiées ou effectuées) dont le créneau
     * chevauche la période [debut, fin). Chargées en UNE requête, avec la
     * machine et la session jointes, pour calculer la disponibilité d'un mois
     * entier en mémoire plutôt qu'avec une requête par créneau et par machine
     * (le calendrier passait de plusieurs centaines de requêtes à une seule).
     *
     * @return list<array{machine: int, debut: \DateTimeImmutable, fin: \DateTimeImmutable}>
     */
    public function occupationsActivesSurPeriode(
        \DateTimeImmutable $debut,
        \DateTimeImmutable $fin,
    ): array {
        /** @var list<array{machine_id: int, debut: \DateTimeImmutable, fin: \DateTimeImmutable}> $lignes */
        $lignes = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.machine) AS machine_id', 's.dateDebut AS debut', 's.dateFin AS fin')
            ->join('o.session', 's')
            ->where('s.statut IN (:actifs)')
            ->andWhere('s.dateDebut < :fin')
            ->andWhere('s.dateFin > :debut')
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->getArrayResult();

        return array_map(
            static fn (array $l): array => [
                'machine' => (int) $l['machine_id'],
                'debut' => $l['debut'],
                'fin' => $l['fin'],
            ],
            $lignes
        );
    }
}
