<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContenuVitrineRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bloc de contenu éditable de la vitrine (mini-CMS, BF_1.2).
 *
 * Modèle volontairement minimal : une clé (identifiant stable du bloc, ex.
 * « hero_titre », « hero_texte ») et sa valeur. Pas de page builder ni de
 * versionning : un FabLab de campus a une poignée de textes à ajuster, pas un
 * site éditorial. On reste sur du clé/valeur, suffisant et sans dette.
 */
#[ORM\Entity(repositoryClass: ContenuVitrineRepository::class)]
class ContenuVitrine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Identifiant stable du bloc (ex. « hero_titre »). Unique. */
    #[ORM\Column(length: 100, unique: true)]
    private string $cle;

    /** Libellé lisible affiché à l'admin dans le formulaire d'édition. */
    #[ORM\Column(length: 150)]
    private string $libelle;

    /**
     * Type de bloc : « texte » (édité en zone de texte) ou « image »
     * (uploadée par l'admin). RETEX CMS : on type le bloc par sa nature de
     * donnée plutôt que de mélanger texte et image dans un même champ.
     */
    #[ORM\Column(length: 20, options: ['default' => 'texte'])]
    private string $type = 'texte';

    #[ORM\Column(type: 'text')]
    private string $valeur = '';

    /** Nom de fichier de l'image uploadée (pour les blocs de type « image »). */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCle(): string
    {
        return $this->cle;
    }

    public function setCle(string $cle): self
    {
        $this->cle = $cle;

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): self
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function getValeur(): string
    {
        return $this->valeur;
    }

    public function setValeur(string $valeur): self
    {
        $this->valeur = $valeur;

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

    public function estImage(): bool
    {
        return 'image' === $this->type;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }
}
