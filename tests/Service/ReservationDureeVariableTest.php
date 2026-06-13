<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Enum\MachineEtat;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Enum\ReservationType;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prouve le comportement du parcours de réservation refondu (DEC-090) :
 * durée de session variable propagée de bout en bout, et réservation de
 * plusieurs machines sur un même créneau (une réservation par machine).
 *
 * Ces tests transforment en preuve exécutable les réserves « à confirmer à
 * l'écran » du parcours multi-machines. Tournent sur SQLite en mémoire
 * (.env.test), schéma créé à la volée comme les autres tests de service.
 */
class ReservationDureeVariableTest extends KernelTestCase
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
        $etudiant = (new \App\Entity\User())
            ->setEmail('eleve@cci.re')
            ->setNom('Test')->setPrenom('Élève')
            ->setRoles(['ROLE_ETUDIANT'])
            ->setPassword('x');
        $this->em->persist($etudiant);

        $projet = (new Projet())
            ->setTitre('Boîtier imprimé')
            ->setType(ProjetType::Personnel)
            ->setStatut(ProjetStatut::Valide)
            ->setEtudiant($etudiant);
        $this->em->persist($projet);
        $this->em->flush();

        return $projet;
    }

    private function creerMachine(string $nom): Machine
    {
        // Durée propre à la machine volontairement différente des durées de
        // session testées, pour prouver que c'est bien la durée de SESSION qui
        // est retenue, pas celle de la machine.
        $machine = (new Machine())
            ->setNom($nom)
            ->setType('impression_3d')
            ->setDureeCreneauMinutes(30)
            ->setEtat(MachineEtat::Active);
        $this->em->persist($machine);
        $this->em->flush();

        return $machine;
    }

    /**
     * La durée choisie pour la session (90 min) est portée par la réservation,
     * et non la durée par défaut de la machine (30 min). La date de fin reflète
     * la durée de session.
     */
    public function testDureeDeSessionEstPropagee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Imprimante A');
        $debut = new \DateTimeImmutable('+2 days 10:00');

        $reservation = $this->service->creerSession(
            $projet, $machine, ReservationType::Realisation, $debut, 2, dureeMinutes: 90
        );

        self::assertSame(90, $reservation->getDureeMinutes());
        self::assertEquals($debut->modify('+90 minutes'), $reservation->getDateFin());
    }

    /**
     * Sans durée de session explicite, on retombe sur la durée propre à la
     * machine (compatibilité ascendante voulue par le service).
     */
    public function testDureeParDefautRetombeSurLaMachine(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Imprimante B'); // 30 min
        $debut = new \DateTimeImmutable('+2 days 10:00');

        $reservation = $this->service->creerSession(
            $projet, $machine, ReservationType::Realisation, $debut, 1
        );

        self::assertSame(30, $reservation->getDureeMinutes());
    }

    /**
     * Plusieurs machines cochées sur un même créneau : autant de réservations
     * distinctes, même début, même durée. C'est le cœur du parcours
     * multi-machines (le service est appelé une fois par machine cochée).
     */
    public function testReservationMultiMachinesSurUnMemeCreneau(): void
    {
        $projet = $this->creerProjetValide();
        $m1 = $this->creerMachine('Découpe laser');
        $m2 = $this->creerMachine('Fraiseuse');
        $m3 = $this->creerMachine('Imprimante 3D');
        $debut = new \DateTimeImmutable('+3 days 14:00');

        $r1 = $this->service->creerSession($projet, $m1, ReservationType::Realisation, $debut, 1, dureeMinutes: 60);
        $r2 = $this->service->creerSession($projet, $m2, ReservationType::Realisation, $debut, 1, dureeMinutes: 60);
        $r3 = $this->service->creerSession($projet, $m3, ReservationType::Realisation, $debut, 1, dureeMinutes: 60);

        // Trois réservations bien distinctes.
        self::assertNotSame($r1->getId(), $r2->getId());
        self::assertNotSame($r2->getId(), $r3->getId());

        // Toutes au même créneau, même durée, machines différentes.
        foreach ([$r1, $r2, $r3] as $r) {
            self::assertEquals($debut, $r->getDateDebut());
            self::assertSame(60, $r->getDureeMinutes());
        }
        self::assertSame($m1->getId(), $r1->getMachine()->getId());
        self::assertSame($m2->getId(), $r2->getMachine()->getId());
        self::assertSame($m3->getId(), $r3->getMachine()->getId());

        // Au total, trois réservations persistées sur ce créneau.
        $compte = $this->em->getRepository(Reservation::class)->count([]);
        self::assertSame(3, $compte);
    }

    /**
     * Une même machine ne peut pas être réservée deux fois sur un créneau qui
     * se chevauche : la seconde tentative est refusée. Garde-fou du
     * multi-machines (on ne coche pas deux fois la même machine).
     */
    public function testMemeMachineDeuxFoisSurCreneauChevauchantRefusee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Découpe laser');
        $debut = new \DateTimeImmutable('+3 days 14:00');

        $this->service->creerSession($projet, $machine, ReservationType::Realisation, $debut, 1, dureeMinutes: 120);

        $this->expectException(\App\Service\Exception\ReservationImpossibleException::class);
        // Chevauche le créneau précédent (commence 60 min après, dure 120).
        $this->service->creerSession(
            $projet, $machine, ReservationType::Realisation,
            $debut->modify('+60 minutes'), 1, dureeMinutes: 120
        );
    }
}
