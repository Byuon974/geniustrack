<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Machine;
use App\Enum\MachineEtat;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Machine>
 */
class MachineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Machine::class);
    }

    /** @return Machine[] machines réservables (actives) */
    public function actives(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.etat = :active')
            ->setParameter('active', MachineEtat::Active->value)
            ->orderBy('m.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
