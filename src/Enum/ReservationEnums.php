<?php

declare(strict_types=1);

namespace App\Enum;

/** Nature d'une session de réservation. */
enum ReservationType: string
{
    case Preparation = 'preparation';   // RDV de prépa obligatoire (bloquant benchmark)
    case Realisation = 'realisation';   // session de fabrication (1 à 4 par projet)

    public function libelle(): string
    {
        return match ($this) {
            self::Preparation => 'RDV de préparation',
            self::Realisation => 'Réalisation',
        };
    }
}

/** Statut d'une réservation. */
enum ReservationStatut: string
{
    case Planifiee = 'planifiee';
    case Effectuee = 'effectuee';
    case Annulee = 'annulee';
    case Reportee = 'reportee';

    public function libelle(): string
    {
        return match ($this) {
            self::Planifiee => 'Planifiée',
            self::Effectuee => 'Effectuée',
            self::Annulee => 'Annulée',
            self::Reportee => 'Reportée',
        };
    }
}
