<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Projet;
use App\Enum\ProjetStatut;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Branche les notifications sur la machine à états du projet (BF_3.6).
 * À chaque entrée dans un statut « notifiable », l'étudiant reçoit un email.
 * Le métier (quand notifier) vit ici, déclenché par le workflow : pas dispersé
 * dans les controllers.
 */
#[AsEventListener(event: 'workflow.projet.entered')]
class ProjetNotificationListener
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    public function __invoke(EnteredEvent $event): void
    {
        $projet = $event->getSubject();
        if (!$projet instanceof Projet) {
            return;
        }

        // On notifie l'étudiant pour les statuts qui le concernent directement.
        $statutsNotifiables = [
            ProjetStatut::EnAttente,
            ProjetStatut::Valide,
            ProjetStatut::Refuse,
        ];

        if (\in_array($projet->getStatut(), $statutsNotifiables, true)) {
            $this->notifications->statutProjet($projet);
        }
    }
}
