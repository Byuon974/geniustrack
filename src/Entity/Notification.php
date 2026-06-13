<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une notification in-app destinée à un utilisateur (Lot 4).
 *
 * Schéma inspiré de Laravel : destinataire, type, données utiles (message +
 * lien), et un « luLe » NULLABLE qui sert d'indicateur lu/non-lu tout en
 * gardant la date de lecture. Persistée EN PLUS de l'email : le mail informe
 * tout de suite, la notification in-app garde une trace consultable et un
 * compteur de non-lues.
 *
 * La notification est immuable à la création ; seul « luLe » évolue (marquage
 * de lecture).
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Index(name: 'idx_notif_destinataire_lu', columns: ['destinataire_id', 'lu_le'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $destinataire;

    /** Catégorie courte, pour l'icône et le filtrage (ex. « projet », « stock »). */
    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(length: 255)]
    private string $message;

    /** Lien interne vers la ressource concernée (ex. la fiche projet), ou null. */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lien;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** Date de lecture (null = non lue). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $luLe = null;

    public function __construct(User $destinataire, string $type, string $message, ?string $lien = null)
    {
        $this->destinataire = $destinataire;
        $this->type = $type;
        $this->message = $message;
        $this->lien = $lien;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDestinataire(): User
    {
        return $this->destinataire;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getLien(): ?string
    {
        return $this->lien;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLuLe(): ?\DateTimeImmutable
    {
        return $this->luLe;
    }

    public function estLue(): bool
    {
        return null !== $this->luLe;
    }

    /** Marque la notification comme lue. Idempotent. */
    public function marquerLue(): void
    {
        $this->luLe ??= new \DateTimeImmutable();
    }
}
