<?php

declare(strict_types=1);

namespace App\Service\Exception;

/**
 * Levée quand une réservation ne peut être créée pour une raison métier
 * (capacité dépassée, machine indisponible, quota de sessions atteint).
 * Le message est destiné à être affiché à l'utilisateur.
 */
class ReservationImpossibleException extends \RuntimeException
{
}
