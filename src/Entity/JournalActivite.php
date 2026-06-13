<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\JournalActiviteRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Trace d'une action significative (BF_8.1).
 * Alimenté par un listener Doctrine ou des appels explicites depuis les services.
 */
#[ORM\Entity(repositoryClass: JournalActiviteRepository::class)]
class JournalActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $acteur;

    #[ORM\Column(length: 100)]
    private string $action;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cible = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $acteur, string $action, ?string $cible = null)
    {
        $this->acteur = $acteur;
        $this->action = $action;
        $this->cible = $cible;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActeur(): User
    {
        return $this->acteur;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getCible(): ?string
    {
        return $this->cible;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
