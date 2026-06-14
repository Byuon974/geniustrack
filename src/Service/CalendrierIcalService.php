<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\SessionReservation;

/**
 * Génère un flux iCal (.ics) du planning FabLab (BF_3.1).
 *
 * Format iCalendar (RFC 5545) produit à la main : un VCALENDAR contenant un
 * VEVENT par session de réservation. Zéro dépendance : le format est un texte
 * simple et stable, cohérent avec la ligne du projet (pas de bundle pour ce
 * besoin).
 *
 * « Synchroniser » = exposer ce flux à une URL ; Google/Outlook/Apple Calendar
 * s'y abonnent et le re-récupèrent périodiquement. Le calendrier externe reste
 * à jour sans intégration bidirectionnelle (celle-ci, lourde, a été écartée).
 */
class CalendrierIcalService
{
    private const PRODID = '-//GeniusLab//Planning FabLab//FR';

    /**
     * Construit le flux iCal complet pour un ensemble de sessions.
     *
     * @param SessionReservation[] $sessions
     */
    public function genererFlux(array $sessions): string
    {
        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:'.self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:Planning GeniusLab',
            'X-WR-TIMEZONE:Indian/Reunion', // La Réunion (UTC+4)
        ];

        foreach ($sessions as $session) {
            $lignes = array_merge($lignes, $this->evenement($session));
        }

        $lignes[] = 'END:VCALENDAR';

        // RFC 5545 : lignes séparées par CRLF.
        return implode("\r\n", $lignes)."\r\n";
    }

    /**
     * Un VEVENT pour une session de réservation. Une session pouvant porter
     * plusieurs machines, le résumé les liste toutes.
     *
     * @return string[]
     */
    private function evenement(SessionReservation $session): array
    {
        $projet = $session->getProjet();
        $noms = array_map(static fn ($m) => $m->getNom(), $session->getMachines());
        $machinesLibelle = [] === $noms ? 'Machines' : implode(', ', $noms);

        $resume = sprintf('%s : %s', $machinesLibelle, $session->getType()->libelle());
        $description = sprintf(
            'Projet : %s (%d personne(s))',
            $projet->getTitre(),
            $session->getNbPersonnes(),
        );

        return [
            'BEGIN:VEVENT',
            'UID:session-'.$session->getId().'@geniuslab.cci.re',
            'DTSTAMP:'.$this->formatUtc(new \DateTimeImmutable()),
            'DTSTART:'.$this->formatUtc($session->getDateDebut()),
            'DTEND:'.$this->formatUtc($session->getDateFin()),
            'SUMMARY:'.$this->echapper($resume),
            'DESCRIPTION:'.$this->echapper($description),
            'LOCATION:'.$this->echapper('GeniusLab : Campus CCI Nord'),
            'STATUS:CONFIRMED',
            'END:VEVENT',
        ];
    }

    /** Date au format iCal UTC (RFC 5545 : YYYYMMDDTHHMMSSZ). */
    private function formatUtc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    /** Échappe les caractères spéciaux iCal (virgule, point-virgule, etc.). */
    private function echapper(string $texte): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\\;', '\\,', '\\n'],
            $texte,
        );
    }
}
