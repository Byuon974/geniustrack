<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Projet;
use App\Entity\User;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Projet>
 */
class ProjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Projet::class);
    }

    /**
     * Projets éligibles à la galerie publique : terminés ET partagés (BF_2.2).
     *
     * @return Projet[]
     */
    public function pourGalerie(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :termine')
            ->andWhere('p.partageAutorise = true')
            ->setParameter('termine', ProjetStatut::Termine->value)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * File d'attente de validation pour un rôle donné (formateur ou BDE).
     *
     * @return Projet[]
     */
    public function enAttenteDeValidation(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :attente')
            ->setParameter('attente', ProjetStatut::EnAttente->value)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes en attente d'un type donné (pédagogique ou personnel).
     *
     * La validation se fait par type : un formateur valide les projets
     * pédagogiques, le BDE les projets personnels (voir ProjetType::roleValideur()).
     * Cette méthode sert le tableau de bord différencié par rôle : chaque
     * valideur ne voit que les demandes qui le concernent.
     *
     * @return Projet[]
     */
    public function enAttenteParType(ProjetType $type): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :attente')
            ->andWhere('p.type = :type')
            ->setParameter('attente', ProjetStatut::EnAttente->value)
            ->setParameter('type', $type->value)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Projets déposés par un étudiant donné, du plus récent au plus ancien.
     * Utilisé par la fiche utilisateur pour donner une vision de son activité.
     *
     * @return Projet[]
     */
    public function parEtudiant(User $etudiant): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.etudiant = :etudiant')
            ->setParameter('etudiant', $etudiant)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les projets, du plus récent au plus ancien. L'étudiant est chargé à
     * la demande par Doctrine (relation ManyToOne, peu coûteuse ici vu le volume
     * d'un FabLab de campus).
     *
     * @return Projet[]
     */
    public function tousAvecEtudiant(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Projets terminés, éligibles à la galerie publique, pour la page de
     * curation : qu'ils soient déjà mis en avant ou non, afin que l'admin
     * puisse les activer ou les retirer. Tri du plus récent au plus ancien.
     *
     * @return Projet[]
     */
    public function terminesPourCuration(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.statut = :termine')
            ->setParameter('termine', ProjetStatut::Termine->value)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vrai si ce projet est le tout premier de l'étudiant sur GeniusLab,
     * c'est-à-dire qu'il n'en possède aucun autre. Sert à signaler une première
     * utilisation au formateur sur la fiche de demande (accompagnement humain de
     * prise en main), sans modéliser de rendez-vous distinct côté logiciel.
     *
     * Compte les projets de l'étudiant autres que celui-ci : zéro = première fois.
     */
    public function estPremierProjet(Projet $projet): bool
    {
        $autres = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.etudiant = :etudiant')
            ->andWhere('p.id != :projet')
            ->setParameter('etudiant', $projet->getEtudiant())
            ->setParameter('projet', $projet->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return 0 === $autres;
    }
}
