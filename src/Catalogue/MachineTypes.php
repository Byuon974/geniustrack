<?php

declare(strict_types=1);

namespace App\Catalogue;

/**
 * Source unique des types de machine et de leurs libellés lisibles.
 *
 * Le type de machine est stocké en chaîne sur l'entité (pas en enum), et les
 * libellés étaient jusqu'ici dupliqués dans le formulaire. On les centralise ici
 * pour que le formulaire ET l'affichage (tableau, fiches) parlent la même langue,
 * et pour qu'un type sans correspondance reste affiché proprement plutôt qu'en
 * valeur brute de base de données.
 *
 * Cette même nomenclature sert aussi de catégorie de rangement du stock : au
 * FabLab, les consommables sont classés par machine à laquelle ils servent
 * (impression_3d, resine, graveuse_laser, plotteur, iot…). Le filtre Twig
 * associé s'applique donc aux deux usages.
 */
final class MachineTypes
{
    /**
     * Correspondance valeur stockée → libellé grammaticalement correct.
     *
     * @var array<string, string>
     */
    public const LIBELLES = [
        'impression_3d' => 'Impression 3D',
        'resine' => 'Résine',
        'flocage' => 'Flocage',
        'graveuse_laser' => 'Graveuse laser',
        'scanner_3d' => 'Scanner 3D',
        'mini_ordinateur' => 'Mini-ordinateur (Raspberry/Arduino)',
        'plotteur' => 'Plotteur de découpe',
        'iot' => 'IoT',
    ];

    /**
     * Libellé lisible d'une valeur de type. Si la valeur n'a pas de
     * correspondance connue, on la rend présentable (underscores en espaces,
     * première lettre en majuscule) plutôt que de l'afficher brute.
     */
    public static function libelle(string $valeur): string
    {
        if (isset(self::LIBELLES[$valeur])) {
            return self::LIBELLES[$valeur];
        }

        return ucfirst(str_replace('_', ' ', $valeur));
    }

    /**
     * Choix prêts pour un ChoiceType de formulaire (libellé => valeur).
     *
     * @return array<string, string>
     */
    public static function choix(): array
    {
        return array_flip(self::LIBELLES);
    }
}
