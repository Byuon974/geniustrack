<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Motif d'un mouvement de stock.
 *
 * Le motif donne du sens à la variation : sans lui, on constate que le stock
 * baisse, sans savoir pourquoi. Catégoriser permet l'analyse des fluctuations
 * (par exemple distinguer une consommation normale d'une perte récurrente).
 */
enum MotifMouvement: string
{
    case Reassort = 'reassort';
    case ConsommationProjet = 'consommation_projet';
    case Perte = 'perte';
    case Inventaire = 'inventaire';

    public function libelle(): string
    {
        return match ($this) {
            self::Reassort => 'Réassort fournisseur',
            self::ConsommationProjet => 'Consommation projet',
            self::Perte => 'Perte ou casse',
            self::Inventaire => 'Correction d\'inventaire',
        };
    }

    /**
     * Sens par défaut du motif : une entrée augmente le stock, une sortie le
     * diminue. Le réassort entre, la consommation et la perte sortent ;
     * l'inventaire peut aller dans les deux sens, on le traite comme neutre
     * (le signe est alors porté par la quantité saisie).
     */
    public function estEntree(): bool
    {
        return self::Reassort === $this;
    }
}
