<?php

declare(strict_types=1);

namespace App\Enum;

/** État d'une machine : conditionne la réservabilité (BF_3.8). */
enum MachineEtat: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case HorsService = 'hors_service';

    /** Une machine n'est réservable que si elle est active. */
    public function estReservable(): bool
    {
        return $this === self::Active;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Maintenance => 'En maintenance',
            self::HorsService => 'Hors service',
        };
    }
}
