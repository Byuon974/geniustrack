<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Nombre de notifications non lues d'un utilisateur (pour le badge).
     */
    public function compterNonLues(User $destinataire): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.destinataire = :dest')
            ->andWhere('n.luLe IS NULL')
            ->setParameter('dest', $destinataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Les notifications les plus récentes d'un utilisateur (lues et non lues),
     * pour le centre de notifications.
     *
     * @return Notification[]
     */
    public function recentes(User $destinataire, int $limite = 30): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinataire = :dest')
            ->setParameter('dest', $destinataire)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque toutes les non-lues d'un utilisateur comme lues, en une requête
     * (plus efficace qu'une boucle en mémoire). Renvoie le nombre marqué.
     */
    public function marquerToutesLues(User $destinataire): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.luLe', ':maintenant')
            ->where('n.destinataire = :dest')
            ->andWhere('n.luLe IS NULL')
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->setParameter('dest', $destinataire)
            ->getQuery()
            ->execute();
    }

    /**
     * Purge les notifications lues plus anciennes que le nombre de jours donné
     * (hygiène, comme la rotation des sauvegardes). Renvoie le nombre supprimé.
     */
    public function purgerLuesAnciennes(int $joursRetention = 60): int
    {
        $limite = new \DateTimeImmutable(sprintf('-%d days', $joursRetention));

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.luLe IS NOT NULL')
            ->andWhere('n.luLe < :limite')
            ->setParameter('limite', $limite)
            ->getQuery()
            ->execute();
    }
}
