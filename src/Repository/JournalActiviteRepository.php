<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\JournalActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<JournalActivite>
 */
class JournalActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, JournalActivite::class);
    }

    /** Dernières entrées, pour la page Journal (BF_8.1). */
    public function recentes(int $limite = 100): array
    {
        return $this->createQueryBuilder('j')
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Entrées concernant une cible donnée (ici le nom complet d'un compte),
     * en ordre anti-chronologique. Sert à afficher l'historique récent sur la
     * fiche utilisateur.
     *
     * Limite connue : la cible est une chaîne (le nom au moment de l'action).
     * Si l'utilisateur est renommé, ses anciennes entrées portent l'ancien
     * nom et n'apparaîtront plus ici. Acceptable pour un aperçu ; une refonte
     * en référence vers l'entité serait nécessaire pour une traçabilité stricte.
     *
     * @return JournalActivite[]
     */
    public function parCible(string $cible, int $limite = 8): array
    {
        return $this->createQueryBuilder('j')
            ->where('j.cible = :cible')
            ->setParameter('cible', $cible)
            ->orderBy('j.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }
}
