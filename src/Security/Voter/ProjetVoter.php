<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Projet;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Autorise un étudiant à agir sur SES projets uniquement.
 * Référencé par les controllers (denyAccessUnlessGranted('PROJET_EDIT', $projet)).
 *
 * @extends Voter<string, Projet>
 */
class ProjetVoter extends Voter
{
    public const EDIT = 'PROJET_EDIT';
    public const VIEW = 'PROJET_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::VIEW], true) && $subject instanceof Projet;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Projet $projet */
        $projet = $subject;

        $estProprietaire = $projet->getEtudiant() === $user;
        $estAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);

        return match ($attribute) {
            // Édition : le propriétaire ou un admin uniquement.
            self::EDIT => $estProprietaire || $estAdmin,
            // Consultation : propriétaire, admin, ou le valideur habilité pour ce
            // type de projet (formateur pour pédagogique, BDE pour personnel).
            // Permet à un valideur d'examiner la demande et ses plans en lecture
            // seule, sans pouvoir la modifier.
            self::VIEW => $estProprietaire || $estAdmin
                || \in_array($projet->getType()->roleValideur(), $user->getRoles(), true),
            default => false,
        };
    }
}
