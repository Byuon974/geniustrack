<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Machine;
use App\Repository\MachineRepository;
use App\Entity\SessionReservation;
use App\Entity\User;
use App\Repository\ReservationRepository;
use App\Repository\SessionReservationRepository;

/**
 * Calcule l'état « libre / occupé » des créneaux d'une machine sur une journée,
 * façon free/busy : on expose la disponibilité sans révéler le détail des
 * réservations d'autrui (pattern Google Workspace / GroupCal). L'étudiant voit
 * seulement « libre », « occupé », « complet », ou « à vous » pour ses propres
 * créneaux.
 *
 * Les bornes horaires de la journée ne sont pas spécifiées par le cahier des
 * charges : on retient des heures ouvrées par défaut, centralisées ici et donc
 * faciles à ajuster si le FabLab fixe d'autres horaires.
 */
final class DisponibiliteService
{
    /** Première heure de créneau proposée (incluse). */
    public const HEURE_OUVERTURE = 8;
    /** Minute d'ouverture (sur l'heure d'ouverture). */
    public const MINUTE_OUVERTURE = 0;
    /**
     * Fermeture du FabLab à 16h30, exprimée en minutes depuis minuit (16*60+30).
     * Borne unique partagée : aucun créneau ne peut se prolonger au-delà.
     */
    public const FERMETURE_MINUTES = 16 * 60 + 30;
    /** Pas de génération des heures de début, en minutes (RETEX : 30 min). */
    public const PAS_MINUTES = 30;

    /** Pause déjeuner : aucun créneau ne peut chevaucher cette plage (12h-13h). */
    public const PAUSE_DEBUT_MINUTES = 12 * 60;
    public const PAUSE_FIN_MINUTES = 13 * 60;

    /**
     * Durées de session proposées, en minutes (30 min à 4 h par paliers de 30).
     * Liste fermée : le wizard n'accepte que ces valeurs, ce qui interdit toute
     * durée arbitraire saisie à la main (conforme RETEX FabLab : 30 min, 1, 2,
     * 3, 4 h, étendu aux demi-heures intermédiaires).
     *
     * @return list<int>
     */
    public static function dureesProposees(): array
    {
        return [30, 60, 90, 120, 150, 180, 210, 240];
    }

    public function __construct(
        private readonly ReservationRepository $reservations,
        private readonly SessionReservationRepository $sessions,
        private readonly MachineRepository $machinesRepo,
    ) {
    }

    /**
     * Renvoie la liste des créneaux de début possibles dans la journée, au pas
     * de 30 minutes, jusqu'à la fermeture. Pour chaque heure de début, l'état
     * est évalué pour une durée donnée : un créneau est « occupé »/« complet »
     * si la plage [début, début+durée] chevauche des réservations existantes.
     *
     * @return array<int, array{heure: string, debut: \DateTimeImmutable, etat: string, restant: int}>
     */
    public function creneauxDuJour(
        Machine $machine,
        \DateTimeImmutable $jour,
        User $utilisateur,
        int $dureeMinutes = self::PAS_MINUTES,
    ): array {
        $creneaux = [];
        $depart = self::HEURE_OUVERTURE * 60 + self::MINUTE_OUVERTURE;

        // Le dernier début admissible est celui dont la fin tient avant la
        // fermeture : début + durée <= fermeture.
        for ($m = $depart; $m + $dureeMinutes <= self::FERMETURE_MINUTES; $m += self::PAS_MINUTES) {
            // Un créneau qui chevauche la pause déjeuner (12h-13h) est exclu :
            // il commence avant la fin de la pause et finit après son début.
            $finCreneau = $m + $dureeMinutes;
            if ($m < self::PAUSE_FIN_MINUTES && $finCreneau > self::PAUSE_DEBUT_MINUTES) {
                continue;
            }

            $debut = $jour->setTime(intdiv($m, 60), $m % 60);
            $fin = $debut->modify(sprintf('+%d minutes', $dureeMinutes));

            $occupe = $this->sessions->sommePersonnesSurCreneau($debut, $fin);
            $restant = max(0, SessionReservation::CAPACITE_MAX_FABLAB - $occupe);
            $mien = $this->sessions->etudiantOccupeCreneau($utilisateur, $debut, $fin);

            $etat = match (true) {
                $mien => 'mien',
                0 === $restant => 'complet',
                $occupe > 0 => 'occupe',
                default => 'libre',
            };

            $creneaux[] = [
                'heure' => $debut->format('H\hi'),
                'debut' => $debut,
                'etat' => $etat,
                'restant' => $restant,
            ];
        }

        return $creneaux;
    }

    /**
     * Pour chaque créneau de début possible du jour (durée donnée), renvoie le
     * nombre de machines actives encore libres. Sert à la page de réservation
     * multi-machines : on choisit d'abord un créneau, l'état reflète combien de
     * machines y sont disponibles.
     *
     * @return array<int, array{heure: string, debut: \DateTimeImmutable, etat: string, machinesLibres: int}>
     */
    public function creneauxAvecMachinesLibres(
        \DateTimeImmutable $jour,
        int $dureeMinutes,
        User $utilisateur,
    ): array {
        $actives = $this->machinesRepo->actives();
        $total = \count($actives);
        $creneaux = [];
        $depart = self::HEURE_OUVERTURE * 60 + self::MINUTE_OUVERTURE;

        // Une requête pour toute la journée, puis comptage en mémoire (au lieu
        // d'une requête par créneau et par machine).
        $debutJour = $jour->setTime(0, 0);
        $occupations = $this->reservations->occupationsActivesSurPeriode(
            $debutJour, $debutJour->modify('+1 day')
        );

        for ($m = $depart; $m + $dureeMinutes <= self::FERMETURE_MINUTES; $m += self::PAS_MINUTES) {
            $finCreneau = $m + $dureeMinutes;
            if ($m < self::PAUSE_FIN_MINUTES && $finCreneau > self::PAUSE_DEBUT_MINUTES) {
                continue;
            }

            $debut = $jour->setTime(intdiv($m, 60), $m % 60);
            $fin = $debut->modify(sprintf('+%d minutes', $dureeMinutes));

            $machinesOccupees = [];
            foreach ($occupations as $o) {
                if ($o['debut'] < $fin && $o['fin'] > $debut) {
                    $machinesOccupees[$o['machine']] = true;
                }
            }
            $libres = $total - \count($machinesOccupees);

            $etat = match (true) {
                0 === $libres => 'complet',
                $libres < $total => 'occupe',
                default => 'libre',
            };

            $creneaux[] = [
                'heure' => $debut->format('H\hi'),
                'debut' => $debut,
                'etat' => $etat,
                'machinesLibres' => $libres,
            ];
        }

        return $creneaux;
    }

    /**
     * Densité de disponibilité par jour pour un mois donné, destinée au
     * calendrier de réservation : chaque cellule du calendrier porte une
     * pastille (libre / chargé / complet) calculée d'après les créneaux du
     * jour pour la durée envisagée. Le détail chiffré n'est pas exposé ici —
     * seul l'état synthétique l'est, conformément au principe free/busy.
     *
     * Les jours passés et ceux sans aucun créneau ouvrable (week-ends si
     * fermés, jours entièrement complets) sont marqués « indispo » afin que le
     * calendrier les désactive d'un coup d'œil.
     *
     * @return array<string, array{etat: string, creneauxLibres: int}> indexé par 'Y-m-d'
     */
    public function densitesDuMois(
        int $annee,
        int $mois,
        int $dureeMinutes,
        User $utilisateur,
    ): array {
        if (!\in_array($dureeMinutes, self::dureesProposees(), true)) {
            $dureeMinutes = self::PAS_MINUTES;
        }

        $premier = (new \DateTimeImmutable())->setDate($annee, $mois, 1)->setTime(0, 0);
        $nbJours = (int) $premier->format('t');
        $aujourdhui = (new \DateTimeImmutable('today'));
        $finMois = $premier->modify('+1 month');

        // UNE seule requête pour tout le mois : toutes les occupations actives
        // qui le chevauchent, avec leur machine et leurs bornes. On évite ainsi
        // la requête-par-créneau-et-par-machine qui rendait le calendrier lent
        // (plusieurs centaines d'allers-retours SQL pour un mois).
        $occupations = $this->reservations->occupationsActivesSurPeriode($premier, $finMois);
        $nbMachinesActives = \count($this->machinesRepo->actives());

        $densites = [];
        for ($j = 1; $j <= $nbJours; ++$j) {
            $jour = $premier->setDate($annee, $mois, $j);
            $cle = $jour->format('Y-m-d');

            // Les jours passés sont indisponibles sans calcul.
            if ($jour < $aujourdhui) {
                $densites[$cle] = ['etat' => 'indispo', 'creneauxLibres' => 0];
                continue;
            }

            [$total, $libres] = $this->densiteDuJourEnMemoire(
                $jour, $dureeMinutes, $nbMachinesActives, $occupations
            );

            // Synthèse : aucun créneau ouvrable -> indispo ; tous pris -> complet ;
            // moins de la moitié des créneaux encore ouverts -> chargé ; sinon libre.
            $etat = match (true) {
                0 === $total => 'indispo',
                0 === $libres => 'complet',
                $libres * 2 < $total => 'charge',
                default => 'libre',
            };

            $densites[$cle] = ['etat' => $etat, 'creneauxLibres' => $libres];
        }

        return $densites;
    }

    /**
     * Compte, pour un jour donné, le nombre de créneaux ouvrables et le nombre
     * de créneaux où au moins une machine reste libre, à partir des occupations
     * du mois déjà chargées en mémoire (aucune requête ici).
     *
     * @param list<array{machine: int, debut: \DateTimeImmutable, fin: \DateTimeImmutable}> $occupations
     * @return array{0: int, 1: int} [total créneaux, créneaux avec ≥1 machine libre]
     */
    private function densiteDuJourEnMemoire(
        \DateTimeImmutable $jour,
        int $dureeMinutes,
        int $nbMachinesActives,
        array $occupations,
    ): array {
        $depart = self::HEURE_OUVERTURE * 60 + self::MINUTE_OUVERTURE;
        $total = 0;
        $creneauxLibres = 0;

        for ($m = $depart; $m + $dureeMinutes <= self::FERMETURE_MINUTES; $m += self::PAS_MINUTES) {
            $finCreneau = $m + $dureeMinutes;
            if ($m < self::PAUSE_FIN_MINUTES && $finCreneau > self::PAUSE_DEBUT_MINUTES) {
                continue;
            }

            $debut = $jour->setTime(intdiv($m, 60), $m % 60);
            $fin = $debut->modify(sprintf('+%d minutes', $dureeMinutes));
            ++$total;

            // Machines distinctes occupées sur ce créneau (chevauchement
            // d'intervalles semi-ouverts), comptées en mémoire.
            $machinesOccupees = [];
            foreach ($occupations as $o) {
                if ($o['debut'] < $fin && $o['fin'] > $debut) {
                    $machinesOccupees[$o['machine']] = true;
                }
            }

            if (\count($machinesOccupees) < $nbMachinesActives) {
                ++$creneauxLibres;
            }
        }

        return [$total, $creneauxLibres];
    }

    /**
     * Liste les machines actives et leur disponibilité sur un créneau précis
     * (début + durée). Chaque entrée indique si la machine est libre, pour
     * proposer des cases à cocher : libres cochables, occupées grisées.
     *
     * @return array<int, array{machine: Machine, libre: bool}>
     */
    public function machinesLibresSurCreneau(\DateTimeImmutable $debut, int $dureeMinutes): array
    {
        $fin = $debut->modify(sprintf('+%d minutes', $dureeMinutes));
        $resultat = [];
        foreach ($this->machinesRepo->actives() as $machine) {
            $resultat[] = [
                'machine' => $machine,
                'libre' => !$this->reservations->machineOccupeeSurCreneau($machine, $debut, $fin),
            ];
        }

        return $resultat;
    }
}
