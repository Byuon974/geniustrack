<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Statut d'un projet : machine à états pilotée par le composant Workflow Symfony.
 * Transitions légales déclarées dans config/packages/workflow.yaml.
 *
 *   brouillon → en_attente → (validé | refusé)
 *   validé → en_cours → terminé
 *   refusé → brouillon (resoumission, BF_3.4 / BF_3.5)
 */
enum ProjetStatut: string
{
    case Brouillon = 'brouillon';
    case EnAttente = 'en_attente';
    case Valide = 'valide';
    case Refuse = 'refuse';
    case EnCours = 'en_cours';
    case Termine = 'termine';

    /** Libellé FR pour l'affichage (les valeurs métier ne se généralisent pas). */
    public function libelle(): string
    {
        return match ($this) {
            self::Brouillon => 'Brouillon',
            self::EnAttente => 'En attente de validation',
            self::Valide => 'Validé',
            self::Refuse => 'Refusé',
            self::EnCours => 'En cours',
            self::Termine => 'Terminé',
        };
    }

    /** Couleur de badge (design tokens : BNF_7.1). */
    public function couleur(): string
    {
        return match ($this) {
            self::Brouillon => 'gray',
            self::EnAttente => 'amber',
            self::Valide => 'green',
            self::Refuse => 'red',
            self::EnCours => 'blue',
            self::Termine => 'slate',
        };
    }
}
