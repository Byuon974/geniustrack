<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\JournalActivite;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralise l'écriture du journal d'activité (BF_8.1).
 *
 * Le journal trace des ACTIONS MÉTIER significatives (« Projet validé »,
 * « Machine en maintenance »), pas des écritures techniques en base. C'est
 * pourquoi il est alimenté par des appels explicites aux moments métier
 * (workflow, services) et NON par un listener Doctrine global, qui ne verrait
 * que des UPDATE/INSERT sans en connaître le sens.
 *
 * Même logique que NotificationService : un seul endroit sait écrire au journal.
 */
class JournalService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Enregistre une entrée. Le flush est groupé avec l'opération métier en
     * cours quand c'est possible ; ici on flush pour garantir la trace même si
     * l'appelant ne flush pas derrière.
     */
    public function tracer(User $acteur, string $action, ?string $cible = null): void
    {
        $this->em->persist(new JournalActivite($acteur, $action, $cible));
        $this->em->flush();
    }
}
