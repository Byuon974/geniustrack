<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MotifMouvement;
use App\Repository\MouvementStockRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Mouvement de stock : une variation datée de la quantité d'un consommable.
 *
 * L'historique des mouvements permet de superviser les fluctuations de stock
 * dans le temps (consommation, réassort, pertes), là où le consommable seul
 * ne porte que son niveau courant. Chaque mouvement est saisi explicitement
 * par l'administrateur (geste métier, non automatique).
 *
 * Convention de signe : la variation est positive pour une entrée (réassort)
 * et négative pour une sortie (consommation, perte). Le niveau du consommable
 * est mis à jour en conséquence au moment de l'enregistrement.
 */
#[ORM\Entity(repositoryClass: MouvementStockRepository::class)]
// Index de performance : filtres par période et par consommable (supervision,
// export, historique). Volumétrie croissante au fil des ajustements de stock.
#[ORM\Index(name: 'idx_mvt_effectue_le', columns: ['effectue_le'])]
#[ORM\Index(name: 'idx_mvt_consommable', columns: ['consommable_id', 'effectue_le'])]
class MouvementStock
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Consommable $consommable;

    // Variation signée : positive pour une entrée, négative pour une sortie.
    #[ORM\Column]
    private int $variation = 0;

    #[ORM\Column(length: 30, enumType: MotifMouvement::class)]
    private MotifMouvement $motif;

    #[ORM\Column]
    private \DateTimeImmutable $effectueLe;

    // Quantité du consommable après le mouvement, figée pour la traçabilité.
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    private int $quantiteApres = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $note = null;

    public function __construct()
    {
        $this->effectueLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConsommable(): Consommable
    {
        return $this->consommable;
    }

    public function setConsommable(Consommable $consommable): self
    {
        $this->consommable = $consommable;

        return $this;
    }

    public function getVariation(): int
    {
        return $this->variation;
    }

    public function setVariation(int $variation): self
    {
        $this->variation = $variation;

        return $this;
    }

    public function getMotif(): MotifMouvement
    {
        return $this->motif;
    }

    public function setMotif(MotifMouvement $motif): self
    {
        $this->motif = $motif;

        return $this;
    }

    public function getEffectueLe(): \DateTimeImmutable
    {
        return $this->effectueLe;
    }

    public function setEffectueLe(\DateTimeImmutable $effectueLe): self
    {
        $this->effectueLe = $effectueLe;

        return $this;
    }

    public function getQuantiteApres(): int
    {
        return $this->quantiteApres;
    }

    public function setQuantiteApres(int $quantiteApres): self
    {
        $this->quantiteApres = $quantiteApres;

        return $this;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;

        return $this;
    }
}
