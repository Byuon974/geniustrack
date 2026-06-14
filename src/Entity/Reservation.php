<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Occupation d'UNE machine au sein d'une session de réservation.
 *
 * Cette entité ne porte que le lien vers sa session (App\Entity\SessionReservation)
 * et la machine occupée. Tout le reste (projet, type, créneau, effectif, statut)
 * appartient à la session, source unique de vérité : une occupation lit ces
 * informations via getSession(). Ce découpage supprime l'ancien artefact des
 * lignes à effectif 0 (DEC-094) et rend l'annulation et le report cohérents au
 * niveau du groupe (DEC-099 puis refonte du modèle).
 *
 * RETEX : Fab-Manager distingue de même la réservation de ses créneaux datés,
 * sans dupliquer les dates sur plusieurs niveaux.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
// Le chevauchement machine filtre par machine puis par dates de la session
// (jointure). On indexe le couple (machine, session) pour servir ce filtre.
#[ORM\Index(name: 'idx_resa_machine_session', columns: ['machine_id', 'session_id'])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'occupations')]
    #[ORM\JoinColumn(nullable: false)]
    private SessionReservation $session;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Machine $machine;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): SessionReservation
    {
        return $this->session;
    }

    public function setSession(SessionReservation $session): self
    {
        $this->session = $session;

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
}
