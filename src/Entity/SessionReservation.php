<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Repository\SessionReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Une session de réservation : un groupe d'étudiants occupe un créneau (date +
 * durée) pour y utiliser une ou plusieurs machines en parallèle, dans le cadre
 * d'un projet. C'est l'enveloppe métier de la réservation.
 *
 * Le modèle distingue l'enveloppe (cette classe : qui, quand, combien de
 * personnes, quel régime, où on en est) de l'occupation d'une machine
 * (App\Entity\Reservation : quelle machine, dans quelle session). Une session
 * porte UNE date, UN effectif et UN statut, valables pour toutes ses machines.
 * Ce choix supprime l'ancien artefact où la première machine d'un créneau
 * portait l'effectif réel et les suivantes 0 (voir DEC-094, corrigé ici).
 *
 * Sources et raisonnement (RETEX) : Fab-Manager sépare la réservation de ses
 * créneaux datés (slots) sans jamais dupliquer les dates ; les règles
 * d'annulation hôtelières veulent qu'une réservation prise sous une
 * confirmation unique s'annule en bloc (tout-ou-rien), jamais par fraction.
 * D'où : dates et statut portés par la session, type homogène par session,
 * annulation et report au niveau de la session entière.
 */
#[ORM\Entity(repositoryClass: SessionReservationRepository::class)]
#[ORM\Table(name: 'session_reservation')]
// Index de performance : le filtre par période (capacité, supervision, export,
// calendrier) et par statut est omniprésent. La date vit désormais ici.
#[ORM\Index(name: 'idx_session_date_debut', columns: ['date_debut'])]
#[ORM\Index(name: 'idx_session_statut', columns: ['statut'])]
class SessionReservation
{
    /** Capacité maximale simultanée du FabLab, par créneau (BF_3.9). */
    public const CAPACITE_MAX_FABLAB = 15;

    /** Plafond de sessions de réalisation par projet (benchmark : 1 à 4). */
    public const MAX_SESSIONS_REALISATION = 4;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sessions')]
    #[ORM\JoinColumn(nullable: false)]
    private Projet $projet;

    /**
     * Régime de la session, homogène : préparation (accompagnement) ou
     * réalisation (fabrication). Choisi une fois pour toute la session. Mélanger
     * les deux dans une même enveloppe créerait deux régimes sous une même
     * confirmation (l'un compté au quota, l'autre non), incohérence écartée.
     */
    #[ORM\Column(length: 20, enumType: ReservationType::class)]
    private ReservationType $type = ReservationType::Realisation;

    /** Début du créneau. Source de vérité de la date pour toutes les machines. */
    #[ORM\Column]
    #[Assert\NotNull]
    private \DateTimeImmutable $dateDebut;

    /** Fin du créneau, dérivée de [dateDebut + dureeMinutes] par definirCreneau(). */
    #[ORM\Column]
    private \DateTimeImmutable $dateFin;

    /**
     * Durée en minutes, stockée (et maintenue cohérente avec les bornes par la
     * seule porte d'écriture definirCreneau). Permet l'agrégation SQL directe du
     * temps réservé pour le taux d'utilisation (supervision à l'échelle).
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $dureeMinutes = 0;

    /**
     * Effectif du groupe sur le créneau (BF_3.9). Compté UNE fois pour la
     * session, quel que soit le nombre de machines. Strictement positif : une
     * session sans personne n'a pas de sens (l'ancien 0 était un artefact, voir
     * DEC-094).
     */
    #[ORM\Column]
    #[Assert\Positive]
    #[Assert\LessThanOrEqual(self::CAPACITE_MAX_FABLAB)]
    private int $nbPersonnes = 1;

    #[ORM\Column(length: 20, enumType: ReservationStatut::class)]
    private ReservationStatut $statut = ReservationStatut::Planifiee;

    /**
     * Occupations machine de cette session. Cascade complète : créer ou
     * supprimer une session crée ou supprime ses occupations ; orphanRemoval
     * retire de la base toute occupation détachée de sa session.
     *
     * @var Collection<int, Reservation>
     */
    #[ORM\OneToMany(targetEntity: Reservation::class, mappedBy: 'session', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $occupations;

    public function __construct()
    {
        $this->occupations = new ArrayCollection();
    }

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

    public function getDateFin(): \DateTimeImmutable
    {
        return $this->dateFin;
    }

    /**
     * Positionne le début et la fin à partir d'une durée explicite en minutes
     * (créneau à durée variable choisi au moment de réserver). Seule porte
     * d'écriture du créneau : garantit la cohérence [dateDebut, dateFin,
     * dureeMinutes].
     */
    public function definirCreneau(\DateTimeImmutable $dateDebut, int $dureeMinutes): self
    {
        $this->dateDebut = $dateDebut;
        $this->dateFin = $dateDebut->modify(sprintf('+%d minutes', $dureeMinutes));
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getDureeMinutes(): int
    {
        return $this->dureeMinutes;
    }

    public function getNbPersonnes(): int
    {
        return $this->nbPersonnes;
    }

    public function setNbPersonnes(int $nbPersonnes): self
    {
        $this->nbPersonnes = $nbPersonnes;

        return $this;
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

    /** @return Collection<int, Reservation> */
    public function getOccupations(): Collection
    {
        return $this->occupations;
    }

    public function addOccupation(Reservation $occupation): self
    {
        if (!$this->occupations->contains($occupation)) {
            $this->occupations->add($occupation);
            $occupation->setSession($this);
        }

        return $this;
    }

    public function removeOccupation(Reservation $occupation): self
    {
        $this->occupations->removeElement($occupation);

        return $this;
    }

    /**
     * Machines occupées par cette session, dérivées des occupations. Confort de
     * lecture pour l'affichage (la page projet, le récapitulatif).
     *
     * @return list<Machine>
     */
    public function getMachines(): array
    {
        return $this->occupations->map(
            static fn (Reservation $o): Machine => $o->getMachine()
        )->getValues();
    }
}
