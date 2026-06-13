<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Vérifie l'état du compte au moment de la connexion.
 * BF_6.2 : un étudiant sanctionné (compte désactivé) ne doit plus pouvoir
 * se connecter. La règle vit ici, pas dans le controller, pour s'appliquer
 * quel que soit le point d'entrée d'authentification.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->estActif()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été désactivé. Contactez l\'administration du GeniusLab.'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Aucune vérification post-authentification nécessaire ici.
    }
}
