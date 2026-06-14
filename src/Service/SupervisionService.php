<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\SessionReservationRepository;

/**
 * Supervision analytique du laboratoire.
 *
 * Calcule les indicateurs de tendance : taux d'utilisation des machines sur une
 * période, à partir des minutes réservées rapportées à la capacité d'ouverture.
 *
 * Capacité d'ouverture : dérivée des bornes horaires déjà définies dans
 * DisponibiliteService (source unique, principe DRY). Une journée ouvrée offre
 * « fermeture - ouverture » minutes par machine ; on ne compte que les jours
 * ouvrés de la période (les week-ends sont exclus, le labo étant fermé).
 *
 * Le taux suit le RETEX : minutes réservées / minutes disponibles. Au-delà de
 * 100 %, on plafonne (chevauchements de capacité simultanée possibles, mais le
 * taux d'occupation d'un créneau ne dépasse pas une journée pleine par machine).
 */
final class SupervisionService
{
    /** Seuils de lecture (RETEX FabLab) : saturation et sous-utilisation. */
    public const SEUIL_SATURATION = 80;
    public const SEUIL_ELEVE = 50;

    public function __construct(
        private readonly SessionReservationRepository $reservations,
    ) {
    }

    /**
     * Taux d'utilisation par machine sur la période, en pourcentage entier.
     *
     * @return array<int, array{nom: string, minutes: int, capacite: int, taux: int, niveau: string}>
     */
    public function tauxUtilisationMachines(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $capacite = $this->capaciteOuvertureMinutes($debut, $fin);
        $lignes = $this->reservations->minutesReserveesParMachine($debut, $fin);

        $resultat = [];
        foreach ($lignes as $ligne) {
            $taux = $capacite > 0
                ? min(100, (int) round($ligne['minutes'] / $capacite * 100))
                : 0;

            $resultat[] = [
                'nom' => $ligne['nom'],
                'minutes' => $ligne['minutes'],
                'capacite' => $capacite,
                'taux' => $taux,
                'niveau' => $this->niveau($taux),
            ];
        }

        // Tri décroissant : les machines les plus sollicitées en tête.
        usort($resultat, static fn (array $a, array $b) => $b['taux'] <=> $a['taux']);

        return $resultat;
    }

    /**
     * Minutes d'ouverture disponibles par machine sur la période : amplitude
     * journalière (dérivée des bornes de DisponibiliteService) multipliée par le
     * nombre de jours ouvrés entre le début et la fin.
     */
    private function capaciteOuvertureMinutes(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        $amplitudeJour = DisponibiliteService::FERMETURE_MINUTES
            - (DisponibiliteService::HEURE_OUVERTURE * 60 + DisponibiliteService::MINUTE_OUVERTURE);

        return $amplitudeJour * $this->joursOuvres($debut, $fin);
    }

    /**
     * Nombre de jours ouvrés (lundi à vendredi) dans l'intervalle [début, fin[.
     */
    private function joursOuvres(\DateTimeImmutable $debut, \DateTimeImmutable $fin): int
    {
        $jours = 0;
        $courant = $debut->setTime(0, 0);
        $borne = $fin->setTime(0, 0);

        while ($courant < $borne) {
            $jourSemaine = (int) $courant->format('N'); // 1 = lundi, 7 = dimanche
            if ($jourSemaine <= 5) {
                ++$jours;
            }
            $courant = $courant->modify('+1 day');
        }

        return $jours;
    }

    private function niveau(int $taux): string
    {
        return match (true) {
            $taux >= self::SEUIL_SATURATION => 'saturation',
            $taux >= self::SEUIL_ELEVE => 'eleve',
            default => 'disponible',
        };
    }
}
