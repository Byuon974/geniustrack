<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Consommable;
use PHPUnit\Framework\TestCase;

/**
 * Teste la logique de prédiction de rupture (BF_4.3) — pur calcul, sans base.
 */
class ConsommablePredictionTest extends TestCase
{
    public function testPasDePredictionSansConsommation(): void
    {
        $c = (new Consommable())->setQuantite(10)->setConsommationMensuelleEstimee(0);

        self::assertNull($c->joursAvantRupture());
        self::assertSame('inconnu', $c->niveauUrgence());
    }

    public function testJoursAvantRuptureCalcules(): void
    {
        // 30 unités, 30/mois → 1/jour → 30 jours.
        $c = (new Consommable())->setQuantite(30)->setConsommationMensuelleEstimee(30);

        self::assertSame(30, $c->joursAvantRupture());
    }

    public function testUrgenceRougeQuandRuptureAvantReappro(): void
    {
        // 5 unités, 30/mois (1/j) → 5 jours, délai fournisseur 14 j → rouge.
        $c = (new Consommable())
            ->setQuantite(5)
            ->setConsommationMensuelleEstimee(30)
            ->setDelaiFournisseurJours(14);

        self::assertSame('rouge', $c->niveauUrgence());
    }

    public function testUrgenceVerteQuandLargeMarge(): void
    {
        // 120 unités, 30/mois (1/j) → 120 jours → vert.
        $c = (new Consommable())
            ->setQuantite(120)
            ->setConsommationMensuelleEstimee(30)
            ->setDelaiFournisseurJours(14);

        self::assertSame('vert', $c->niveauUrgence());
    }
}
