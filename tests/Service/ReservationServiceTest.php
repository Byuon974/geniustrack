<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\SessionReservation;
use App\Entity\User;
use App\Enum\MachineEtat;
use App\Enum\ProjetType;
use App\Enum\ReservationType;
use App\Service\Exception\ReservationImpossibleException;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie les règles métier critiques du Lot 3, sur le modèle « session ».
 * Tourne sur SQLite en mémoire (.env.test) : rapide, sans service externe.
 */
class ReservationServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ReservationService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->service = $container->get(ReservationService::class);

        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function creerProjetValide(): Projet
    {
        $etudiant = (new User())
            ->setEmail('eleve@cci.re')
            ->setNom('Test')->setPrenom('Élève')
            ->setRoles(['ROLE_ETUDIANT'])
            ->setPassword('x');
        $this->em->persist($etudiant);

        $projet = (new Projet())
            ->setTitre('Boîtier imprimé')
            ->setType(ProjetType::Personnel)
            ->setEtudiant($etudiant);
        $this->em->persist($projet);
        $this->em->flush();

        return $projet;
    }

    private function creerMachine(MachineEtat $etat = MachineEtat::Active): Machine
    {
        $machine = (new Machine())
            ->setNom('Imprimante 3D')
            ->setType('impression_3d')
            ->setDureeCreneauMinutes(120)
            ->setEtat($etat);
        $this->em->persist($machine);
        $this->em->flush();

        return $machine;
    }

    public function testReservationSimpleReussit(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine();
        $debut = new \DateTimeImmutable('+2 days 10:00');

        $session = $this->service->creerSession(
            $projet, ReservationType::Realisation, $debut, 3, 60, [$machine]
        );

        self::assertNotNull($session->getId());
        self::assertSame(3, $session->getNbPersonnes());
        self::assertCount(1, $session->getOccupations());
    }

    public function testMachineEnMaintenanceRefusee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine(MachineEtat::Maintenance);

        $this->expectException(ReservationImpossibleException::class);
        $this->service->creerSession(
            $projet, ReservationType::Realisation,
            new \DateTimeImmutable('+2 days 10:00'), 1, 60, [$machine]
        );
    }

    public function testCapaciteQuinzePersonnesPlafonnee(): void
    {
        $projet = $this->creerProjetValide();
        // Deux machines distinctes sur le MÊME créneau (sinon collision machine).
        $m1 = $this->creerMachine();
        $m2 = $this->creerMachine();
        $debut = new \DateTimeImmutable('+2 days 10:00');

        // 10 personnes (une session, machine m1).
        $this->service->creerSession($projet, ReservationType::Realisation, $debut, 10, 60, [$m1]);

        // 6 de plus sur le même créneau (machine m2) → 16 > 15 → doit échouer.
        $this->expectException(ReservationImpossibleException::class);
        $this->service->creerSession($projet, ReservationType::Realisation, $debut, 6, 60, [$m2]);
    }

    public function testSessionMultiMachinesCompteEffectifUneSeuleFois(): void
    {
        $projet = $this->creerProjetValide();
        $m1 = $this->creerMachine();
        $m2 = $this->creerMachine();
        $m3 = $this->creerMachine();
        $debut = new \DateTimeImmutable('+2 days 10:00');

        // Un groupe de 7 personnes sur 3 machines en parallèle = UNE session,
        // trois occupations. L'effectif est porté une seule fois par la session.
        $session = $this->service->creerSession(
            $projet, ReservationType::Realisation, $debut, 7, 60, [$m1, $m2, $m3]
        );

        self::assertCount(3, $session->getOccupations());
        // La capacité consommée sur le créneau est 7 (le groupe), pas 21.
        self::assertSame(7, $session->getNbPersonnes());
    }

    public function testLotPlusieursCreneauxAtomique(): void
    {
        $projet = $this->creerProjetValide();
        $m1 = $this->creerMachine();
        $m2 = $this->creerMachine();

        // Deux sessions distinctes (deux créneaux) dans un même panier.
        $creees = $this->service->creerSessionsLot([
            ['projet' => $projet, 'type' => ReservationType::Realisation, 'debut' => new \DateTimeImmutable('+2 days 10:00'), 'nbPersonnes' => 5, 'duree' => 60, 'machines' => [$m1]],
            ['projet' => $projet, 'type' => ReservationType::Realisation, 'debut' => new \DateTimeImmutable('+3 days 14:00'), 'nbPersonnes' => 2, 'duree' => 60, 'machines' => [$m2]],
        ]);

        self::assertCount(2, $creees);
    }

    public function testLotEchoueEntierementSiUneMachineIndisponible(): void
    {
        $projet = $this->creerProjetValide();
        $m1 = $this->creerMachine();
        $hs = $this->creerMachine(MachineEtat::Maintenance);
        $debut = new \DateTimeImmutable('+2 days 10:00');

        try {
            $this->service->creerSessionsLot([
                ['projet' => $projet, 'type' => ReservationType::Realisation, 'debut' => $debut, 'nbPersonnes' => 3, 'duree' => 60, 'machines' => [$m1]],
                ['projet' => $projet, 'type' => ReservationType::Realisation, 'debut' => new \DateTimeImmutable('+3 days 09:00'), 'nbPersonnes' => 2, 'duree' => 60, 'machines' => [$hs]],
            ]);
            self::fail('Le lot aurait dû échouer sur la machine en maintenance.');
        } catch (ReservationImpossibleException) {
            // Atomicité : aucune session ni occupation ne doit subsister.
            $this->em->clear();
            $sessions = $this->em->getRepository(SessionReservation::class)->findAll();
            self::assertCount(0, $sessions, 'Le lot partiel ne doit rien laisser en base.');
        }
    }

    public function testQuotaRealisationPlafonneMaisPrepaIllimitee(): void
    {
        $projet = $this->creerProjetValide();
        $debut = new \DateTimeImmutable('+2 days 08:00');
        $max = SessionReservation::MAX_SESSIONS_REALISATION;

        // On atteint le quota de réalisations (une machine neuve par créneau,
        // des heures distinctes pour éviter toute collision machine).
        for ($i = 0; $i < $max; ++$i) {
            $this->service->creerSession(
                $projet, ReservationType::Realisation,
                $debut->modify(sprintf('+%d hours', $i)), 1, 30, [$this->creerMachine()]
            );
        }

        // Une réalisation de plus dépasse le quota : refus.
        try {
            $this->service->creerSession(
                $projet, ReservationType::Realisation,
                $debut->modify('+10 hours'), 1, 30, [$this->creerMachine()]
            );
            self::fail('Le quota de réalisations aurait dû être atteint.');
        } catch (ReservationImpossibleException) {
            // attendu
        }

        // Mais une PRÉPARATION reste possible : elle n'est pas plafonnée.
        $prepa = $this->service->creerSession(
            $projet, ReservationType::Preparation,
            $debut->modify('+20 hours'), 1, 30, [$this->creerMachine()]
        );
        self::assertNotNull($prepa->getId());
    }
}
