<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SanctionRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une sanction infligée à un étudiant (BF_6.2).
 *
 * Pattern « ledger » (inspiré du schéma de notifications/journal de Laravel) :
 * chaque sanction est une LIGNE immuable, jamais modifiée. On ne stocke plus un
 * simple compteur sur l'étudiant ; le nombre de sanctions actives se DÉRIVE des
 * lignes (celles qui ne sont pas levées). On conserve ainsi l'historique complet
 * (qui, quand, pourquoi) au lieu d'un entier sans mémoire.
 *
 * Une sanction « levée » n'est pas supprimée : on horodate sa levée (leveeLe),
 * traçant le geste admin. Le compteur actif = sanctions dont leveeLe est null.
 */
#[ORM\Entity(repositoryClass: SanctionRepository::class)]
class Sanction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $etudiant;

    #[ORM\Column(length: 255)]
    private string $motif;

    /** Auteur du geste : l'admin qui a sanctionné, ou null si automatique. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $auteur;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Horodatage de la levée (null = sanction active). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $leveeLe = null;

    public function __construct(User $etudiant, string $motif, ?User $auteur = null)
    {
        $this->etudiant = $etudiant;
        $this->motif = $motif;
        $this->auteur = $auteur;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtudiant(): User
    {
        return $this->etudiant;
    }

    public function getMotif(): string
    {
        return $this->motif;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLeveeLe(): ?\DateTimeImmutable
    {
        return $this->leveeLe;
    }

    public function estActive(): bool
    {
        return null === $this->leveeLe;
    }

    /**
     * Lève la sanction (geste admin). Idempotent : une sanction déjà levée
     * n'est pas re-horodatée.
     */
    public function lever(): void
    {
        $this->leveeLe ??= new \DateTimeImmutable();
    }
}
