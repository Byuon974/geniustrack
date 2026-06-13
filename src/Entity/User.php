<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Ce compte existe déjà.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // BNF_3.1 : connexion restreinte au domaine .cci (contrainte renforcée côté validation/form).
    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Regex(pattern: '/@cci\.re$/', message: 'Seules les adresses @cci.re sont autorisées.')]
    private string $email;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $nom;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $prenom;

    // BF_6.2 : un compte sanctionné est désactivé. Défaut SQL à true pour que
    // toute ligne créée hors setter (insertion directe) soit active.
    #[ORM\Column(options: ['default' => true])]
    private bool $actif = true;

    // BF_6.2 : sanctions de l'étudiant (modèle ledger). Le compteur de
    // sanctions actives se dérive de cette collection, plus de champ figé.
    /** @var Collection<int, Sanction> */
    #[ORM\OneToMany(targetEntity: Sanction::class, mappedBy: 'etudiant')]
    private Collection $sanctions;

    public function __construct()
    {
        $this->sanctions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    /** Identifiant unique de session (Symfony Security). */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
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

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): self
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNomComplet(): string
    {
        return $this->prenom.' '.$this->nom;
    }

    public function estActif(): bool
    {
        return $this->actif;
    }

    /**
     * Alias de estActif() reconnu par Symfony PropertyAccess (préfixe « is »).
     * Permet au champ « actif » du formulaire de lire la valeur initiale.
     * Le métier continue d'utiliser estActif().
     */
    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): self
    {
        $this->actif = $actif;

        return $this;
    }

    /**
     * Nombre de sanctions ACTIVES (non levées). Dérivé de la collection :
     * plus de compteur stocké. L'API reste la même pour les vues existantes.
     */
    public function getNbSanctions(): int
    {
        return $this->sanctions
            ->filter(static fn (Sanction $s): bool => $s->estActive())
            ->count();
    }

    /** @return Collection<int, Sanction> */
    public function getSanctions(): Collection
    {
        return $this->sanctions;
    }

    /**
     * Rattache une sanction à l'étudiant (côté inverse), pour que la collection
     * en mémoire et donc getNbSanctions() reflètent l'état sans rechargement.
     */
    public function ajouterSanction(Sanction $sanction): void
    {
        if (!$this->sanctions->contains($sanction)) {
            $this->sanctions->add($sanction);
        }
    }

    public function eraseCredentials(): void
    {
        // Aucune donnée sensible temporaire à effacer.
    }
}
