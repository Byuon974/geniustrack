<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ConsommableRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Consommable / article de stock (BF_4.x).
 * Catégories : filament PLA/PETG/TPU, résine, pièces d'usure, supports flocage...
 */
#[ORM\Entity(repositoryClass: ConsommableRepository::class)]
class Consommable
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    private string $nom;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 80)]
    private string $categorie;

    // Bornes hautes : une borne métier réaliste évite les saisies absurdes et la
    // corruption en aval (RETEX OWASP « range check », WCAG SC 3.3.4). Un stock de
    // FabLab étudiant reste dans les milliers d'unités ; 100 000 cadre large.
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000)]
    private int $quantite = 0;

    // BF_4.4 : seuil déclenchant l'alerte de stock bas.
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000)]
    private int $seuilMinimal = 0;

    #[ORM\Column(length: 20)]
    private string $unite = 'unité';

    // Pour la prédiction de rupture (BF_4.3) : délai fournisseur en jours.
    // Au-delà d'un an, un réapprovisionnement n'a plus de sens opérationnel.
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(365)]
    private int $delaiFournisseurJours = 14;

    // Consommation moyenne estimée, saisie par l'admin (approche pragmatique
    // petit FabLab : pas d'historique de mouvements, cf. AUDIT/recherches).
    // En unités par mois (granularité naturelle pour bobines/résine).
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(100000)]
    private float $consommationMensuelleEstimee = 0;

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

    public function getCategorie(): string
    {
        return $this->categorie;
    }

    public function setCategorie(string $categorie): self
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): self
    {
        $this->quantite = $quantite;

        return $this;
    }

    public function getSeuilMinimal(): int
    {
        return $this->seuilMinimal;
    }

    public function setSeuilMinimal(int $seuilMinimal): self
    {
        $this->seuilMinimal = $seuilMinimal;

        return $this;
    }

    public function getUnite(): string
    {
        return $this->unite;
    }

    public function setUnite(string $unite): self
    {
        $this->unite = $unite;

        return $this;
    }

    public function getDelaiFournisseurJours(): int
    {
        return $this->delaiFournisseurJours;
    }

    public function setDelaiFournisseurJours(int $delai): self
    {
        $this->delaiFournisseurJours = $delai;

        return $this;
    }

    public function getConsommationMensuelleEstimee(): float
    {
        return $this->consommationMensuelleEstimee;
    }

    public function setConsommationMensuelleEstimee(float $conso): self
    {
        $this->consommationMensuelleEstimee = $conso;

        return $this;
    }

    /** BF_4.4 : l'article est sous le seuil d'alerte. */
    public function estSousSeuil(): bool
    {
        return $this->quantite <= $this->seuilMinimal;
    }

    /**
     * BF_4.3 : prédiction de rupture (algo simple benchmark).
     * Jours restants avant rupture = stock ÷ consommation journalière.
     * Null si aucune consommation estimée (pas de prédiction possible).
     */
    public function joursAvantRupture(): ?int
    {
        if ($this->consommationMensuelleEstimee <= 0) {
            return null;
        }

        $consoParJour = $this->consommationMensuelleEstimee / 30;

        return (int) floor($this->quantite / $consoParJour);
    }

    /**
     * Date estimée de rupture, ou null si non calculable.
     */
    public function dateRuptureEstimee(): ?\DateTimeImmutable
    {
        $jours = $this->joursAvantRupture();

        return null === $jours ? null : (new \DateTimeImmutable())->modify("+{$jours} days");
    }

    /**
     * Niveau d'urgence pour la pastille du widget (vert/orange/rouge).
     * Tient compte du délai fournisseur : si la rupture arrive avant qu'une
     * commande puisse être livrée, c'est rouge.
     */
    public function niveauUrgence(): string
    {
        $jours = $this->joursAvantRupture();

        if (null === $jours) {
            return 'inconnu';
        }
        if ($jours <= $this->delaiFournisseurJours) {
            return 'rouge';   // rupture avant réappro possible
        }
        if ($jours <= 30) {
            return 'orange';
        }

        return 'vert';
    }
}
