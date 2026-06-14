<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Repository\ProjetRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjetRepository::class)]
class Projet
{
    public const MAX_SESSIONS_REALISATION = 4; // benchmark : 1 à 4 créneaux

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 40,
        minMessage: 'Le titre doit faire au moins {{ limit }} caractères.',
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.',
    )]
    private string $titre;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(
        max: 250,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $description = null;

    // Détermine le valideur (formateur vs BDE).
    #[ORM\Column(length: 20, enumType: ProjetType::class)]
    private ProjetType $type;

    // Statut piloté par le composant Workflow (config/packages/workflow.yaml).
    #[ORM\Column(length: 20, enumType: ProjetStatut::class)]
    private ProjetStatut $statut = ProjetStatut::Brouillon;

    #[ORM\ManyToOne(inversedBy: 'projets')]
    #[ORM\JoinColumn(nullable: false)]
    private User $etudiant;

    #[ORM\ManyToOne]
    private ?User $valideur = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motifRefus = null;

    // BF_2.2 : alimente la galerie publique uniquement si terminé ET partagé.
    #[ORM\Column]
    private bool $partageAutorise = false;

    /** @var Collection<int, Machine> */
    #[ORM\ManyToMany(targetEntity: Machine::class)]
    private Collection $machines;

    /** @var Collection<int, SessionReservation> */
    #[ORM\OneToMany(targetEntity: SessionReservation::class, mappedBy: 'projet', cascade: ['persist', 'remove'])]
    private Collection $sessions;

    /** Plans importés à la soumission (BF_3.7). */
    #[ORM\OneToMany(targetEntity: PlanProjet::class, mappedBy: 'projet', cascade: ['persist', 'remove'])]
    private Collection $plans;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->machines = new ArrayCollection();
        $this->sessions = new ArrayCollection();
        $this->plans = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): self
    {
        $this->titre = $titre;

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

    public function getType(): ProjetType
    {
        return $this->type;
    }

    public function setType(ProjetType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getStatut(): ProjetStatut
    {
        return $this->statut;
    }

    public function setStatut(ProjetStatut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getEtudiant(): User
    {
        return $this->etudiant;
    }

    public function setEtudiant(User $etudiant): self
    {
        $this->etudiant = $etudiant;

        return $this;
    }

    public function getValideur(): ?User
    {
        return $this->valideur;
    }

    public function setValideur(?User $valideur): self
    {
        $this->valideur = $valideur;

        return $this;
    }

    public function getMotifRefus(): ?string
    {
        return $this->motifRefus;
    }

    public function setMotifRefus(?string $motifRefus): self
    {
        $this->motifRefus = $motifRefus;

        return $this;
    }

    public function isPartageAutorise(): bool
    {
        return $this->partageAutorise;
    }

    public function setPartageAutorise(bool $partageAutorise): self
    {
        $this->partageAutorise = $partageAutorise;

        return $this;
    }

    /** BF_2.2 : éligible à la galerie publique. */
    public function estVisibleEnGalerie(): bool
    {
        return $this->statut === ProjetStatut::Termine && $this->partageAutorise;
    }

    /** @return Collection<int, Machine> */
    public function getMachines(): Collection
    {
        return $this->machines;
    }

    public function addMachine(Machine $machine): self
    {
        if (!$this->machines->contains($machine)) {
            $this->machines->add($machine);
        }

        return $this;
    }

    public function removeMachine(Machine $machine): self
    {
        $this->machines->removeElement($machine);

        return $this;
    }

    /** @return Collection<int, SessionReservation> */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    public function addSession(SessionReservation $session): self
    {
        if (!$this->sessions->contains($session)) {
            $this->sessions->add($session);
            $session->setProjet($this);
        }

        return $this;
    }

    public function removeSession(SessionReservation $session): self
    {
        $this->sessions->removeElement($session);

        return $this;
    }

    /** @return Collection<int, PlanProjet> */
    public function getPlans(): Collection
    {
        return $this->plans;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
