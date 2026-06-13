<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose aux templates le nombre de notifications non lues de l'utilisateur
 * connecté, pour le badge de la barre. Évite de requêter dans chaque controller :
 * la fonction n'est évaluée que là où le template l'appelle (le badge), et une
 * seule fois par requête (résultat mémoïsé).
 */
class NotificationExtension extends AbstractExtension
{
    private ?int $cache = null;

    public function __construct(
        private readonly Security $security,
        private readonly NotificationRepository $notifications,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('notifications_non_lues', $this->compterNonLues(...)),
        ];
    }

    public function compterNonLues(): int
    {
        if (null !== $this->cache) {
            return $this->cache;
        }

        $user = $this->security->getUser();
        $this->cache = $user instanceof User
            ? $this->notifications->compterNonLues($user)
            : 0;

        return $this->cache;
    }
}
