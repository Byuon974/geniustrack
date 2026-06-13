<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Projet;
use App\Entity\User;
use App\Service\Exception\ReservationImpossibleException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Pilote la machine à états du projet via le composant Workflow.
 * Encapsule la règle de validation différenciée : un projet pédagogique se
 * valide par un formateur, un projet personnel par le BDE (BF_3.4 / BF_3.5).
 */
class ProjetWorkflowService
{
    public function __construct(
        // #[Target('projet')] cible explicitement le workflow nommé "projet"
        // déclaré dans workflow.yaml (évite toute ambiguïté de câblage).
        #[Target('projet')]
        private readonly WorkflowInterface $projetStateMachine,
        private readonly EntityManagerInterface $em,
        private readonly Security $security,
    ) {
    }

    public function soumettre(Projet $projet): void
    {
        $this->appliquer($projet, 'soumettre');
    }

    /**
     * Valide un projet : vérifie que l'agent a le bon rôle selon le type.
     *
     * @throws ReservationImpossibleException si le valideur n'est pas habilité
     */
    public function valider(Projet $projet, User $valideur): void
    {
        // On vérifie l'habilitation via le composant Security pour respecter la
        // hiérarchie des rôles (un admin hérite de formateur et BDE) : sinon un
        // admin verrait la demande mais ne pourrait pas la valider.
        $roleRequis = $projet->getType()->roleValideur();
        if (!$this->security->isGranted($roleRequis)) {
            $libelleRole = 'ROLE_FORMATEUR' === $roleRequis ? 'un formateur' : 'le BDE';
            throw new ReservationImpossibleException(sprintf(
                'Ce %s doit être validé par %s.',
                strtolower($projet->getType()->libelle()),
                $libelleRole
            ));
        }

        $projet->setValideur($valideur);
        $this->appliquer($projet, 'valider');
    }

    public function refuser(Projet $projet, User $valideur, string $motif): void
    {
        $projet->setValideur($valideur);
        $projet->setMotifRefus($motif);
        $this->appliquer($projet, 'refuser');
    }

    public function resoumettre(Projet $projet): void
    {
        $projet->setMotifRefus(null);
        $this->appliquer($projet, 'resoumettre');
    }

    public function demarrer(Projet $projet): void
    {
        $this->appliquer($projet, 'demarrer');
    }

    public function terminer(Projet $projet): void
    {
        $this->appliquer($projet, 'terminer');
    }

    /**
     * Applique une transition si elle est légale, sinon lève une exception.
     * C'est ici que le composant Workflow garantit la cohérence de la machine
     * à états : impossible de sauter une étape.
     */
    private function appliquer(Projet $projet, string $transition): void
    {
        if (!$this->projetStateMachine->can($projet, $transition)) {
            throw new ReservationImpossibleException(sprintf(
                'Transition « %s » impossible depuis le statut « %s ».',
                $transition,
                $projet->getStatut()->libelle()
            ));
        }

        $this->projetStateMachine->apply($projet, $transition);
        $this->em->flush();
    }
}
