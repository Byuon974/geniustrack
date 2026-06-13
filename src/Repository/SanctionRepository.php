<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Sanction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Sanction>
 */
class SanctionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sanction::class);
    }

    /**
     * Nombre de sanctions ACTIVES (non levées) d'un étudiant. C'est la valeur
     * qui pilote la désactivation : on la dérive des lignes, plus de compteur.
     */
    public function compterActives(User $etudiant): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.etudiant = :etudiant')
            ->andWhere('s.leveeLe IS NULL')
            ->setParameter('etudiant', $etudiant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * La sanction active la plus récente d'un étudiant, ou null. Sert à « lever
     * une sanction » : on lève la dernière infligée encore active.
     */
    public function derniereActive(User $etudiant): ?Sanction
    {
        return $this->createQueryBuilder('s')
            ->where('s.etudiant = :etudiant')
            ->andWhere('s.leveeLe IS NULL')
            ->setParameter('etudiant', $etudiant)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Historique complet des sanctions d'un étudiant (actives et levées),
     * du plus récent au plus ancien. Pour la fiche utilisateur.
     *
     * @return Sanction[]
     */
    public function historique(User $etudiant): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.etudiant = :etudiant')
            ->setParameter('etudiant', $etudiant)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
