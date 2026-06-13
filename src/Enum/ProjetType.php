<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Type de projet : détermine QUI valide (règle métier structurante).
 *  - Pédagogique → Formateur (BF_3.4)
 *  - Personnel   → BDE       (BF_3.5)
 */
enum ProjetType: string
{
    case Pedagogique = 'pedagogique';
    case Personnel = 'personnel';

    /** Rôle Symfony habilité à valider ce type de projet. */
    public function roleValideur(): string
    {
        return match ($this) {
            self::Pedagogique => 'ROLE_FORMATEUR',
            self::Personnel => 'ROLE_BDE',
        };
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Pedagogique => 'Projet pédagogique',
            self::Personnel => 'Projet personnel',
        };
    }
}
