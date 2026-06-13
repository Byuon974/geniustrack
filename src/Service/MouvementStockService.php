<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Consommable;
use App\Entity\MouvementStock;
use App\Enum\MotifMouvement;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Trace les mouvements de stock pour alimenter la supervision des fluctuations.
 *
 * Principe (modèle ledger immuable, comme les sanctions) : chaque variation de
 * stock crée une LIGNE de mouvement permanente. Les entrées historiques ne sont
 * jamais modifiées ni supprimées ; une éventuelle correction prendra la forme
 * d'un nouveau mouvement (écriture inverse), ce qui garde l'historique intègre.
 *
 * Le traçage est SILENCIEUX et AUTOMATIQUE : l'administrateur continue d'ajuster
 * les quantités dans le module Stock comme avant, sans geste supplémentaire. À
 * chaque changement de quantité, ce service écrit le mouvement correspondant
 * (le delta) en arrière-plan. La saisie du stock reste l'unique porte d'entrée :
 * on ne duplique pas l'inventaire ailleurs.
 */
class MouvementStockService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Trace la variation entre l'ancienne et la nouvelle quantité d'un
     * consommable, si elle existe. Le motif est déduit du sens de la variation
     * (entrée pour un réassort, sortie pour une consommation), l'inventaire
     * restant le motif générique d'un ajustement manuel.
     *
     * À appeler après que la nouvelle quantité a été appliquée au consommable.
     * N'écrit rien si la quantité n'a pas changé.
     */
    public function tracerVariation(
        Consommable $consommable,
        int $quantiteAvant,
        ?MotifMouvement $motif = null,
        ?string $note = null,
    ): ?MouvementStock {
        $quantiteApres = $consommable->getQuantite();
        $variation = $quantiteApres - $quantiteAvant;

        // Pas de changement : pas de mouvement (on ne pollue pas l'historique).
        if (0 === $variation) {
            return null;
        }

        // À défaut de motif explicite, un ajustement manuel relève de
        // l'inventaire (réassort si la quantité monte, sinon inventaire).
        $motif ??= $variation > 0 ? MotifMouvement::Reassort : MotifMouvement::Inventaire;

        $mouvement = (new MouvementStock())
            ->setConsommable($consommable)
            ->setVariation($variation)
            ->setMotif($motif)
            ->setQuantiteApres($quantiteApres)
            ->setNote($note);

        $this->em->persist($mouvement);
        // Le flush est laissé à l'appelant, pour rester dans la même
        // transaction que la mise à jour du consommable (cohérence atomique).

        return $mouvement;
    }
}
