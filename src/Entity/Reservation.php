<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
// Index de performance (volumétrie : milliers de réservations). Les filtres par
// période et par machine sont omniprésents (supervision, chevauchement, export).
#[ORM\Index(name: 'idx_resa_date_debut', columns: ['date_debut'])]
#[ORM\Index(name: 'idx_resa_machine_date', columns: ['machine_id', 'date_debut'])]
#[ORM\Index(name: 'idx_resa_statut', columns: ['statut'])]
class Reservation
{
    public const CAPACITE_MAX_FABLAB = 15; // BF_3.9

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reservations')]
    #[ORM\JoinColumn(nullable: false)]
    private Projet $projet;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Machine $machine;

    #[ORM\Column(length: 20, enumType: ReservationType::class)]
    private ReservationType $type;

    #[ORM\Column]
    #[Assert\NotNull]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column]
    private \DateTimeImmutable $dateFin;

    /**
     * Durée en minutes, stockée (et non plus seulement dérivée des bornes).
     * Redondance maîtrisée : maintenue cohérente avec [dateDebut, dateFin] par
     * les seules portes d'écriture (setDateDebut, definirCreneau). Elle permet
     * une agrégation SQL directe (SUM) du temps réservé, indispensable au calcul
     * du taux d'utilisation à l'échelle (milliers de réservations).
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $dureeMinutes = 0;

    #[ORM\Column(length: 20, enumType: ReservationStatut::class)]
    private ReservationStatut $statut = ReservationStatut::Planifiee;

    // BF_3.9 : compte dans la limite de 15 personnes simultanées.
    // PositiveOrZero (et non Positive) : sur un créneau multi-machines, l'effectif
    // n'est porté que par la première réservation du créneau ; les machines
    // supplémentaires du même créneau portent 0 (même groupe, déjà compté dans
    // la capacité). Zéro est donc une valeur métier légitime ici, pas une saisie
    // vide. La borne haute (capacité) reste inchangée.
    #[ORM\Column]
    #[Assert\PositiveOrZero]
    #[Assert\LessThanOrEqual(self::CAPACITE_MAX_FABLAB)]
    private int $nbPersonnesPrevues = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProjet(): Projet
    {
        return $this->projet;
    }

    public function setProjet(Projet $projet): self
    {
        $this->projet = $projet;

        return $this;
    }

    public function getMachine(): Machine
    {
        return $this->machine;
    }

    public function setMachine(Machine $machine): self
    {
        $this->machine = $machine;

        return $this;
    }

    public function getType(): ReservationType
    {
        return $this->type;
    }

    public function setType(ReservationType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getDateDebut(): \DateTimeImmutable
    {
        return $this->dateDebut;
    }

    /**
     * Positionne le début et calcule la fin selon la durée propre à la machine.
     * Conservé pour les usages où la durée reste celle, fixe, de la machine
     * (chargement de données, anciens appels). Pour une durée choisie par
     * l'utilisateur, voir definirCreneau().
     */
    public function setDateDebut(\DateTimeImmutable $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        $duree = $this->machine->getDureeCreneauMinutes();
        $this->dateFin = $dateDebut->modify(sprintf('+%d minutes', $duree));
        $this->dureeMinutes = $duree;

        return $this;
    }

    /**
     * Positionne le début et la fin à partir d'une durée explicite, en minutes.
     * Sert le créneau à durée variable choisi dans le wizard (RETEX : la durée
     * est un réglage propre à la réservation, distinct de l'heure de début).
     */
    public function definirCreneau(\DateTimeImmutable $dateDebut, int $dureeMinutes): self
    {
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateDebut->modify(sprintf('+%d minutes', $dureeMinutes));
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    /**
     * Durée de la réservation en minutes (champ stocké, maintenu cohérent avec
     * les bornes par les portes d'écriture). Lecture directe, sans calcul.
     */
    public function getDureeMinutes(): int
    {
        return $this->dureeMinutes;
    }

    public function getStatut(): ReservationStatut
    {
        return $this->statut;
    }

    public function setStatut(ReservationStatut $statut): self
    {
        $this->statut = $statut;

        return $this;
    }

    public function getNbPersonnesPrevues(): int
    {
        return $this->nbPersonnesPrevues;
    }

    public function setNbPersonnesPrevues(int $nb): self
    {
        $this->nbPersonnesPrevues = $nb;

        return $this;
    }
}
