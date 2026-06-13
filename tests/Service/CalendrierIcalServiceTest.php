<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
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
    private function reservation(): Reservation
    {
        $user = (new User())->setEmail('e@cci.re')->setNom('X')->setPrenom('Y')->setPassword('x');
        $machine = (new Machine())->setNom('Imprimante 3D')->setType('impression_3d')
            ->setDureeCreneauMinutes(60)->setEtat(MachineEtat::Active);
        $projet = (new Projet())->setTitre('Mon projet')->setType(ProjetType::Personnel)->setEtudiant($user);

        return (new Reservation())
            ->setProjet($projet)
            ->setMachine($machine)
            ->setType(ReservationType::Realisation)
            ->setDateDebut(new \DateTimeImmutable('2026-07-01 10:00', new \DateTimeZone('Indian/Reunion')))
            ->setNbPersonnesPrevues(2)
            ->setStatut(ReservationStatut::Planifiee);
    }

    public function testFluxBienForme(): void
    {
        $flux = (new CalendrierIcalService())->genererFlux([$this->reservation()]);

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
