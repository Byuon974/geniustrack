<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContenuVitrine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContenuVitrine>
 */
class ContenuVitrineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContenuVitrine::class);
    }

    /**
     * Retourne tous les blocs indexés par clé, pour un accès direct en Twig :
     * contenus['hero_titre'].valeur : avec repli sur défaut si absent.
     *
     * @return array<string, ContenuVitrine>
     */
    public function parCle(): array
    {
        $map = [];
        foreach ($this->findAll() as $bloc) {
            $map[$bloc->getCle()] = $bloc;
        }

        return $map;
    }
}
