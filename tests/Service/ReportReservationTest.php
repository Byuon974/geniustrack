<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\SessionReservation;
use App\Entity\User;
use App\Enum\MachineEtat;
use App\Enum\ProjetType;
use App\Enum\ReservationStatut;
use App\Enum\ReservationType;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie le report d'une réservation (BF_3.12) : l'ancien créneau passe
 * « reporté », un nouveau est créé, et le quota de réalisations n'est pas faussé.
 */
class ReportReservationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReservationService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->service = $c->get(ReservationService::class);
        (new \Doctrine\ORM\Tools\SchemaTool($this->em))
            ->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function fixtures(): array
    {
        $u = (new User())->setEmail('e@cci.re')->setNom('X')->setPrenom('Y')
            ->setRoles(['ROLE_ETUDIANT'])->setPassword('x');
        $m = (new Machine())->setNom('Imprimante')->setType('impression_3d')
            ->setEtat(MachineEtat::Active);
        $p = (new Projet())->setTitre('P')->setType(ProjetType::Personnel)->setEtudiant($u);
        $this->em->persist($u);
        $this->em->persist($m);
        $this->em->persist($p);
        $this->em->flush();

        return [$p, $m];
    }

    public function testReportChangeStatutEtCreeNouveauCreneau(): void
    {
        [$projet, $machine] = $this->fixtures();
        $dans10jours = new \DateTimeImmutable('+10 days 10:00');
        $original = $this->service->creerSession($projet, ReservationType::Realisation, $dans10jours, 1, 60, [$machine]);

        $nouvelleDate = new \DateTimeImmutable('+15 days 10:00');
        [$nouveau, $tardif] = $this->service->reporter($original, $nouvelleDate);

        self::assertSame(ReservationStatut::Reportee, $original->getStatut());
        self::assertSame(ReservationStatut::Planifiee, $nouveau->getStatut());
        self::assertFalse($tardif, 'Report à 10 jours ne doit pas être tardif');
        self::assertEquals($nouvelleDate, $nouveau->getDateDebut());
    }

    public function testReportNeFaussePasLeQuota(): void
    {
        [$projet, $machine] = $this->fixtures();
        // 4 réalisations = quota max. On en reporte une : doit rester possible.
        $reservations = [];
        for ($i = 1; $i <= SessionReservation::MAX_SESSIONS_REALISATION; ++$i) {
            $reservations[] = $this->service->creerSession(
                $projet, ReservationType::Realisation,
                new \DateTimeImmutable("+$i days 10:00"), 1, 60, [$machine]
            );
        }

        // Reporter la 1re ne doit PAS être bloqué par le quota (elle libère sa place).
        $nouvelle = new \DateTimeImmutable('+20 days 10:00');
        [$nouveau] = $this->service->reporter($reservations[0], $nouvelle);

        self::assertSame(ReservationStatut::Planifiee, $nouveau->getStatut());
    }

    public function testReportTardifEstSignale(): void
    {
        [$projet, $machine] = $this->fixtures();
        $dans2jours = new \DateTimeImmutable('+2 days 10:00');
        $original = $this->service->creerSession($projet, ReservationType::Realisation, $dans2jours, 1, 60, [$machine]);

        [, $tardif] = $this->service->reporter($original, new \DateTimeImmutable('+20 days 10:00'));

        self::assertTrue($tardif, 'Report à 2 jours doit être signalé tardif (BF_6.2)');
    }
}
