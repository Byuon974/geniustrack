<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Projet;
use App\Enum\ProjetStatut;
use App\Repository\ProjetRepository;
use App\Service\JournalService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\EnteredEvent;

/**
 * Journalise les transitions significatives du projet (BF_8.1).
 *
 * Branché sur le workflow (comme les notifications), pour tracer des ACTIONS
 * MÉTIER lisibles : « Projet validé par Mme X » : et non des écritures techniques.
 * L'acteur tracé est le valideur quand il existe (validation/refus), sinon
 * l'étudiant porteur du projet (soumission, démarrage).
 */
#[AsEventListener(event: 'workflow.projet.entered')]
class ProjetJournalListener
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly ProjetRepository $projets,
    ) {
    }

    public function __invoke(EnteredEvent $event): void
    {
        $projet = $event->getSubject();
        if (!$projet instanceof Projet) {
            return;
        }

        $action = match ($projet->getStatut()) {
            ProjetStatut::EnAttente => 'Projet soumis',
            ProjetStatut::Valide => 'Projet validé',
            ProjetStatut::Refuse => 'Projet refusé',
            ProjetStatut::EnCours => 'Projet démarré',
            ProjetStatut::Termine => 'Projet terminé',
            default => null,
        };

        if (null === $action) {
            return;
        }

        // Acteur : le valideur pour validation/refus, sinon l'étudiant.
        $acteur = match ($projet->getStatut()) {
            ProjetStatut::Valide, ProjetStatut::Refuse => $projet->getValideur() ?? $projet->getEtudiant(),
            default => $projet->getEtudiant(),
        };

        $this->journal->tracer($acteur, $action, $projet->getTitre());

        // Première utilisation de GeniusLab par l'étudiant : tracée à la soumission
        // du tout premier projet, pour rester consultable dans l'archive et
        // signaler un accompagnement humain de prise en main (sans rendez-vous
        // modélisé). Décision métier : on archive les mouvements, pas l'assemblée.
        if (ProjetStatut::EnAttente === $projet->getStatut() && $this->projets->estPremierProjet($projet)) {
            $this->journal->tracer($projet->getEtudiant(), 'Première utilisation de GeniusLab', $projet->getTitre());
        }
    }
}
