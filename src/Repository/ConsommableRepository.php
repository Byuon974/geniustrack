<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Consommable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Consommable>
 */
class ConsommableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Consommable::class);
    }

    /** BF_4.4 : articles sous le seuil minimal (pour alertes et dashboard). */
    public function sousSeuil(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.quantite <= c.seuilMinimal')
            ->orderBy('c.categorie', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Catégories distinctes déjà saisies en base. Sert à enrichir la liste
     * figée du formulaire : une catégorie créée à la volée réapparaît ainsi
     * pour les autres articles, sans table de référence dédiée.
     *
     * @return string[]
     */
    public function categoriesUtilisees(): array
    {
        $lignes = $this->createQueryBuilder('c')
            ->select('DISTINCT c.categorie')
            ->orderBy('c.categorie', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $ligne): string => $ligne['categorie'], $lignes);
    }

    /**
     * Unités distinctes déjà saisies en base. Permet à la liste fermée du
     * formulaire d'accepter une valeur existante hors socle (ex. ancienne
     * saisie libre) sans rejeter l'article à l'édition.
     *
     * @return string[]
     */
    public function unitesUtilisees(): array
    {
        $lignes = $this->createQueryBuilder('c')
            ->select('DISTINCT c.unite')
            ->orderBy('c.unite', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $ligne): string => $ligne['unite'], $lignes);
    }
}
