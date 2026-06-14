<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\Reservation;
use App\Entity\SessionReservation;
use App\Enum\MachineEtat;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Enum\ReservationType;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prouve le comportement du parcours de réservation sur le modèle « session » :
 * durée de session variable portée par la session, et plusieurs machines sur un
 * même créneau réunies dans UNE session (une occupation par machine).
 *
 * Tournent sur SQLite en mémoire (.env.test), schéma créé à la volée.
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
     * La durée choisie pour la session (90 min) est portée par la session, et
     * la date de fin en découle.
     */
    public function testDureeDeSessionEstPropagee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Imprimante A');
        $debut = new \DateTimeImmutable('+2 days 10:00');

        $session = $this->service->creerSession(
            $projet, ReservationType::Realisation, $debut, 2, 90, [$machine]
        );

        self::assertSame(90, $session->getDureeMinutes());
        self::assertEquals($debut->modify('+90 minutes'), $session->getDateFin());
    }

    /**
     * Plusieurs machines sur un même créneau : UNE session, autant d'occupations
     * que de machines, même début, même durée, effectif compté une fois. C'est
     * le cœur du parcours multi-machines.
     */
    public function testReservationMultiMachinesSurUnMemeCreneau(): void
    {
        $projet = $this->creerProjetValide();
        $m1 = $this->creerMachine('Découpe laser');
        $m2 = $this->creerMachine('Fraiseuse');
        $m3 = $this->creerMachine('Imprimante 3D');
        $debut = new \DateTimeImmutable('+3 days 14:00');

        $session = $this->service->creerSession(
            $projet, ReservationType::Realisation, $debut, 1, 60, [$m1, $m2, $m3]
        );

        // Une session, trois occupations distinctes, mêmes créneau et durée.
        self::assertCount(3, $session->getOccupations());
        self::assertEquals($debut, $session->getDateDebut());
        self::assertSame(60, $session->getDureeMinutes());

        // Les trois machines sont bien celles cochées.
        $ids = array_map(static fn (Machine $m) => $m->getId(), $session->getMachines());
        sort($ids);
        $attendus = [$m1->getId(), $m2->getId(), $m3->getId()];
        sort($attendus);
        self::assertSame($attendus, $ids);

        // Trois occupations persistées au total.
        $compte = $this->em->getRepository(Reservation::class)->count([]);
        self::assertSame(3, $compte);
    }

    /**
     * Une même machine ne peut pas être réservée deux fois sur un créneau qui
     * se chevauche : la seconde session est refusée.
     */
    public function testMemeMachineDeuxFoisSurCreneauChevauchantRefusee(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Découpe laser');
        $debut = new \DateTimeImmutable('+3 days 14:00');

        $this->service->creerSession($projet, ReservationType::Realisation, $debut, 1, 120, [$machine]);

        $this->expectException(\App\Service\Exception\ReservationImpossibleException::class);
        // Chevauche le créneau précédent (commence 60 min après, dure 120).
        $this->service->creerSession(
            $projet, ReservationType::Realisation,
            $debut->modify('+60 minutes'), 1, 120, [$machine]
        );
    }
}
