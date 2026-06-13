<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Panier de reservation : l'etat de la page de reservation, stocke en session
 * HTTP. Volontairement simple et serialisable (scalaires et tableaux).
 *
 * Modele : une reservation est une liste de creneaux ; chaque creneau associe un
 * horaire (debut ISO + duree) a UNE OU PLUSIEURS machines utilisees en parallele
 * pendant ce creneau, plus un nombre de personnes. Il n'y a plus d'etapes ni de
 * distinction preparation/realisation : la prise en main de premiere utilisation
 * releve de l'humain (signalee au valideur), pas du logiciel.
 *
 * Anti-vandalisme : aucune saisie libre. Le debut provient d'un creneau propose,
 * la duree d'une liste fermee, les machines d'une selection de machines actives.
 *
 * @phpstan-type Creneau array{debut: string, duree: int, personnes: int, machines: list<int>}
 */
final class PanierReservation
{
    public const MAX_CRENEAUX = 8;

    /** @var list<Creneau> */
    public array $creneaux = [];

    /**
     * @param array<string, mixed> $donnees
     */
    public static function depuisSession(array $donnees): self
    {
        $panier = new self();
        $creneaux = $donnees['creneaux'] ?? null;
        $panier->creneaux = \is_array($creneaux) ? array_values($creneaux) : [];

        return $panier;
    }

    /**
     * @return array<string, mixed>
     */
    public function versSession(): array
    {
        return ['creneaux' => $this->creneaux];
    }

    public function estVide(): bool
    {
        return [] === $this->creneaux;
    }

    /** Nombre total de machines reservees, tous creneaux confondus. */
    public function nombreMachines(): int
    {
        $total = 0;
        foreach ($this->creneaux as $creneau) {
            $total += \count($creneau['machines'] ?? []);
        }

        return $total;
    }
}
