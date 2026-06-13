<?php

declare(strict_types=1);

namespace App\Twig;

use App\Catalogue\MachineTypes;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Filtres d'affichage du catalogue (libellés lisibles). Sépare la valeur
 * stockée du libellé montré, sans dupliquer la correspondance dans les
 * templates.
 *
 * Usage : {{ machine.type|libelle_type_machine }}
 */
class CatalogueExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('libelle_type_machine', MachineTypes::libelle(...)),
        ];
    }
}
