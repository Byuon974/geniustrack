<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MachineEtat;
use App\Repository\MachineRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MachineRepository::class)]
class Machine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private string $nom;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null;

    // ex. impression_3d, resine, decoupe_laser, broderie...
    #[ORM\Column(length: 50)]
    private string $type;

    // BF_3.10 : temps d'utilisation adapté à chaque machine.
    #[ORM\Column]
    #[Assert\Positive]
    private int $dureeCreneauMinutes = 60;

    // BF_3.8 / BF_5.2 : état piloté par l'admin.
    #[ORM\Column(length: 20, enumType: MachineEtat::class)]
    private MachineEtat $etat = MachineEtat::Active;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDureeCreneauMinutes(): int
    {
        return $this->dureeCreneauMinutes;
    }

    public function setDureeCreneauMinutes(int $dureeCreneauMinutes): self
    {
        $this->dureeCreneauMinutes = $dureeCreneauMinutes;

        return $this;
    }

    public function getEtat(): MachineEtat
    {
        return $this->etat;
    }

    public function setEtat(MachineEtat $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    /** BF_3.8 : raccourci métier pour le wizard de réservation. */
    public function estReservable(): bool
    {
        return $this->etat->estReservable();
    }
}
