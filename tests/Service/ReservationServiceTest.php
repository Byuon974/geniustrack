<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\User;
use App\Enum\MachineEtat;
use App\Enum\ProjetType;
use App\Enum\ReservationType;
use App\Service\Exception\ReservationImpossibleException;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie les règles métier critiques du Lot 3.
 * Tourne sur SQLite en mémoire (.env.test) — rapide, sans service externe.
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

        // Schéma créé à la volée pour la base de test.
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

        $reservation = $this->service->creerSession(
            $projet, $machine, ReservationType::Realisation, $debut, 3
        );

        self::assertNotNull($reservation->getId());
        self::assertSame(3, $reservation->getNbPersonnesPrevues());
    }

    public function testMachineEnMaintenanceRefusee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine(MachineEtat::Maintenance);

        $this->expectException(ReservationImpossibleException::class);
        $this->service->creerSession(
            $projet, $machine, ReservationType::Realisation,
            new \DateTimeImmutable('+2 days 10:00'), 1
        );
    }

    public function testCapaciteQuinzePersonnesPlafonnee(): void
    {
        $projet = $this->creerProjetValide();
        // Deux machines distinctes sur le MÊME créneau (sinon collision machine).
        $m1 = $this->creerMachine();
        $m2 = $this->creerMachine();
        $debut = new \DateTimeImmutable('+2 days 10:00');

        // 10 personnes sur m1.
        $this->service->creerSession($projet, $m1, ReservationType::Realisation, $debut, 10);

        // 6 de plus sur m2 → 16 > 15 → doit échouer.
        $this->expectException(ReservationImpossibleException::class);
        $this->service->creerSession($projet, $m2, ReservationType::Realisation, $debut, 6);
    }
}
