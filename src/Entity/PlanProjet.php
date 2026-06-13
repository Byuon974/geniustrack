<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PlanProjetRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un fichier de plan importé à la soumission d'un projet (BF_3.7).
 *
 * Un projet peut avoir plusieurs plans (un par machine/pièce). On stocke le nom
 * de fichier (pas le binaire), comme pour les photos machines. Le nom d'origine
 * est conservé pour l'afficher à l'utilisateur.
 */
#[ORM\Entity(repositoryClass: PlanProjetRepository::class)]
class PlanProjet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'plans')]
    #[ORM\JoinColumn(nullable: false)]
    private Projet $projet;

    /** Nom de fichier stocké sur disque (slug + uniqid). */
    #[ORM\Column(length: 255)]
    private string $fichier;

    /** Nom d'origine, pour affichage. */
    #[ORM\Column(length: 255)]
    private string $nomOriginal;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(Projet $projet, string $fichier, string $nomOriginal)
    {
        $this->projet = $projet;
        $this->fichier = $fichier;
        $this->nomOriginal = $nomOriginal;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjet(): Projet
    {
        return $this->projet;
    }

    public function getFichier(): string
    {
        return $this->fichier;
    }

    public function getNomOriginal(): string
    {
        return $this->nomOriginal;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
