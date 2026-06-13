<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\MouvementStock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MouvementStock>
 */
class MouvementStockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MouvementStock::class);
    }

    /**
     * Mouvements d'une plage de dates, consommable chargé, pour l'export et la
     * supervision des fluctuations.
     *
     * @return MouvementStock[]
     */
    public function entrePeriode(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('c')
            ->join('m.consommable', 'c')
            ->where('m.effectueLe >= :debut')
            ->andWhere('m.effectueLe < :fin')
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->orderBy('m.effectueLe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Les derniers mouvements, pour l'affichage de supervision.
     *
     * @return MouvementStock[]
     */
    public function recents(int $limite = 20): array
    {
        return $this->createQueryBuilder('m')
            ->addSelect('c')
            ->join('m.consommable', 'c')
            ->orderBy('m.effectueLe', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Variation nette du stock global (tous consommables) par mois sur une
     * période. Sert à tracer l'évolution du niveau de stock dans le temps
     * (RETEX dashboard d'inventaire : suivre le niveau global plutôt qu'un
     * article isolé). Renvoie une variation cumulable mois par mois.
     *
     * @return array<int, int> variation nette indexée par numéro de mois (1..12)
     */
    public function variationParMois(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $sql = <<<'SQL'
            SELECT EXTRACT(MONTH FROM effectue_le)::int AS mois, COALESCE(SUM(variation), 0) AS net
            FROM mouvement_stock
            WHERE effectue_le >= :debut AND effectue_le < :fin
            GROUP BY mois
            ORDER BY mois
            SQL;

        $lignes = $this->getEntityManager()->getConnection()->executeQuery($sql, [
            'debut' => $debut->format('Y-m-d H:i:s'),
            'fin' => $fin->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();

        // Tableau garanti : une entrée par mois (1..12), 0 si aucun mouvement.
        // Contrat explicite pour le template (clés de mois stables, pas de trous).
        $parMois = array_fill(1, 12, 0);
        foreach ($lignes as $l) {
            $mois = (int) $l['mois'];
            if ($mois >= 1 && $mois <= 12) {
                $parMois[$mois] = (int) $l['net'];
            }
        }

        return $parMois;
    }
}
