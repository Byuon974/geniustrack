<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Entity\SessionReservation;
use App\Entity\User;
use App\Enum\MachineEtat;
use App\Enum\ProjetType;
use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Service\CalendrierIcalService;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie la génération du flux iCal (BF_3.1) : structure RFC 5545 et contenu.
 */
class CalendrierIcalServiceTest extends TestCase
{
    private function session(): SessionReservation
    {
        $user = (new User())->setEmail('e@cci.re')->setNom('X')->setPrenom('Y')->setPassword('x');
        $machine = (new Machine())->setNom('Imprimante 3D')->setType('impression_3d')
            ->setDureeCreneauMinutes(60)->setEtat(MachineEtat::Active);
        $projet = (new Projet())->setTitre('Mon projet')->setType(ProjetType::Personnel)->setEtudiant($user);

        $session = (new SessionReservation())
            ->setType(ReservationType::Realisation)
            ->definirCreneau(new \DateTimeImmutable('2026-07-01 10:00', new \DateTimeZone('Indian/Reunion')), 60)
            ->setNbPersonnes(2)
            ->setStatut(ReservationStatut::Planifiee);
        $projet->addSession($session);
        $session->addOccupation((new Reservation())->setMachine($machine));

        return $session;
    }

    public function testFluxBienForme(): void
    {
        $flux = (new CalendrierIcalService())->genererFlux([$this->session()]);

        // Enveloppe VCALENDAR.
        self::assertStringContainsString('BEGIN:VCALENDAR', $flux);
        self::assertStringContainsString('VERSION:2.0', $flux);
        self::assertStringContainsString('END:VCALENDAR', $flux);
        // Un événement.
        self::assertStringContainsString('BEGIN:VEVENT', $flux);
        self::assertStringContainsString('SUMMARY:Imprimante 3D', $flux);
        self::assertStringContainsString('Mon projet', $flux);
        // Séparateurs CRLF (RFC 5545).
        self::assertStringContainsString("\r\n", $flux);
    }

    public function testFluxVideResteValide(): void
    {
        $flux = (new CalendrierIcalService())->genererFlux([]);
        self::assertStringContainsString('BEGIN:VCALENDAR', $flux);
        self::assertStringContainsString('END:VCALENDAR', $flux);
        self::assertStringNotContainsString('BEGIN:VEVENT', $flux);
    }
}
