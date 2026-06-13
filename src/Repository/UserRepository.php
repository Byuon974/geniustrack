<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 *
 * @implements PasswordUpgraderInterface<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    /**
     * Rôles acceptés comme filtre de recherche. Liste fermée : tout autre valeur
     * est rejetée en amont de la requête.
     *
     * @var list<string>
     */
    private const ROLES_FILTRABLES = ['ROLE_ETUDIANT', 'ROLE_FORMATEUR', 'ROLE_BDE', 'ROLE_ADMIN'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Liste paginée côté serveur des utilisateurs (BF_6.x, montée en charge).
     *
     * Pensée pour des centaines d'étudiants : on ne charge jamais toute la
     * table, la base fait la recherche, le filtre, le tri et la découpe par
     * page. Retourne les utilisateurs de la page ET le total, pour que la vue
     * affiche le nombre de pages sans seconde requête manuelle.
     *
     * @return array{resultats: User[], total: int}
     */
    public function paginer(
        int $page = 1,
        int $parPage = 20,
        string $recherche = '',
        string $role = '',
        string $tri = 'nom',
        string $sens = 'asc',
    ): array {
        $qb = $this->createQueryBuilder('u');

        if ('' !== $recherche) {
            $qb->andWhere('LOWER(u.nom) LIKE :rech OR LOWER(u.prenom) LIKE :rech OR LOWER(u.email) LIKE :rech')
               ->setParameter('rech', '%'.mb_strtolower($recherche).'%');
        }

        if ('' !== $role) {
            // Liste blanche : on n'accepte que des rôles connus. Une valeur hors
            // liste (rôle inexistant ou tentative d'injection de joker LIKE) ne
            // correspond à personne, on renvoie un résultat vide plutôt que de la
            // passer à la requête. Défense en profondeur : la valeur est déjà liée
            // en paramètre, ceci ferme en plus tout usage détourné.
            if (!\in_array($role, self::ROLES_FILTRABLES, true)) {
                return ['resultats' => [], 'total' => 0];
            }
            // roles est stocké en JSON ; sous PostgreSQL, LIKE ne s'applique pas
            // au type json et CAST n'est pas une fonction DQL native. On résout
            // le filtre en amont par une requête native portable (cast en texte
            // côté SQL) qui retourne les identifiants concernés, puis on borne
            // la requête principale à ces identifiants.
            $ids = $this->idsParRole($role);
            if ([] === $ids) {
                return ['resultats' => [], 'total' => 0];
            }
            $qb->andWhere('u.id IN (:ids)')->setParameter('ids', $ids);
        }

        // Tri sur une liste blanche de colonnes (jamais la saisie brute en SQL).
        // Le tri par sanctions ne porte plus sur un champ (supprimé au profit du
        // modèle ledger) : on compte les sanctions actives via une jointure,
        // uniquement quand ce tri est demandé.
        $direction = 'desc' === strtolower($sens) ? 'DESC' : 'ASC';
        if ('sanctions' === $tri) {
            $qb->leftJoin('u.sanctions', 's_tri', 'WITH', 's_tri.leveeLe IS NULL')
               ->addSelect('COUNT(s_tri.id) AS HIDDEN nb_sanctions_actives')
               ->groupBy('u.id')
               ->orderBy('nb_sanctions_actives', $direction);
        } else {
            $colonnes = ['nom' => 'u.nom', 'email' => 'u.email'];
            $qb->orderBy($colonnes[$tri] ?? 'u.nom', $direction);
        }

        $page = max(1, $page);
        $qb->setFirstResult(($page - 1) * $parPage)->setMaxResults($parPage);

        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($qb->getQuery());

        return [
            'resultats' => iterator_to_array($paginator),
            'total' => count($paginator),
        ];
    }

    /**
     * Identifiants des utilisateurs portant un rôle donné. Requête native :
     * on caste la colonne JSON en texte (portable : CAST(... AS TEXT) est
     * compris par PostgreSQL comme par SQLite) et on cherche le rôle entre
     * guillemets. Isolé ici pour que le filtre du paginateur reste en DQL pur.
     *
     * @return list<int>
     */
    /**
     * Nombre de comptes administrateurs actuellement actifs. Sert le garde-fou
     * anti-verrouillage : on ne désactive jamais le dernier admin actif, sinon
     * plus personne ne peut administrer la plateforme.
     */
    public function compterAdminsActifs(): int
    {
        $ids = $this->idsParRole('ROLE_ADMIN');
        if ([] === $ids) {
            return 0;
        }

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.id IN (:ids)')
            ->andWhere('u.actif = true')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function idsParRole(string $role): array
    {
        $sql = 'SELECT id FROM "user" WHERE CAST(roles AS TEXT) LIKE :motif';
        $resultats = $this->getEntityManager()->getConnection()
            ->executeQuery($sql, ['motif' => '%"'.$role.'"%'])
            ->fetchFirstColumn();

        return array_map('intval', $resultats);
    }
}
