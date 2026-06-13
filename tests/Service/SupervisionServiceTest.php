<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\User;
use App\Enum\MachineEtat;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Enum\ReservationType;
use App\Service\DisponibiliteService;
use App\Service\ReservationService;
use App\Service\SupervisionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prouve le calcul du taux d'utilisation par machine de la supervision, sur un
 * jeu de données contrôlé et une fenêtre d'un seul jour ouvré (capacité connue
 * et déterministe).
 *
 * Capacité d'ouverture journalière = fermeture (16h30 = 990 min) moins
 * ouverture (8h00 = 480 min) = 510 min par jour ouvré.
 *
 * SQLite en mémoire, schéma créé à la volée.
 */
class SupervisionServiceTest extends KernelTestCase
{
    private const AMPLITUDE_JOUR = 510; // 990 - 480

    private EntityManagerInterface $em;
    private SupervisionService $supervision;
    private ReservationService $reservation;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->supervision = $container->get(SupervisionService::class);
        $this->reservation = $container->get(ReservationService::class);

        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());

        // Cohérence du test avec les bornes réelles du service.
        self::assertSame(
            self::AMPLITUDE_JOUR,
            DisponibiliteService::FERMETURE_MINUTES
                - (DisponibiliteService::HEURE_OUVERTURE * 60 + DisponibiliteService::MINUTE_OUVERTURE)
        );
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
            ->setTitre('Projet test')
            ->setType(ProjetType::Personnel)
            ->setStatut(ProjetStatut::Valide)
            ->setEtudiant($etudiant);
        $this->em->persist($projet);
        $this->em->flush();

        return $projet;
    }

    private function creerMachine(string $nom): Machine
    {
        $m = (new Machine())
            ->setNom($nom)
            ->setType('impression_3d')
            ->setDureeCreneauMinutes(60)
            ->setEtat(MachineEtat::Active);
        $this->em->persist($m);
        $this->em->flush();

        return $m;
    }

    /**
     * Une seule machine, une réservation de 120 min un mardi : sur une fenêtre
     * d'un jour ouvré (mardi → mercredi), capacité = 510 min, donc taux =
     * round(120 / 510 * 100) = 24 %.
     */
    public function testTauxUtilisationSurUnJourOuvre(): void
    {
        $projet = $this->creerProjetValide();
        $machine = $this->creerMachine('Découpe laser');

        // Un mardi quelconque, dans le futur, créneau 10h00 pour 120 min.
        $mardi = new \DateTimeImmutable('next tuesday');
        $debut = $mardi->setTime(10, 0);
        $this->reservation->creerSession(
            $projet, $machine, ReservationType::Realisation, $debut, 1, dureeMinutes: 120
        );

        // Fenêtre [mardi 00:00, mercredi 00:00[ = un seul jour ouvré.
        $finFenetre = $mardi->setTime(0, 0)->modify('+1 day');
        $lignes = $this->supervision->tauxUtilisationMachines($mardi->setTime(0, 0), $finFenetre);

        self::assertCount(1, $lignes);
        self::assertSame('Découpe laser', $lignes[0]['nom']);
        self::assertSame(120, $lignes[0]['minutes']);
        self::assertSame(self::AMPLITUDE_JOUR, $lignes[0]['capacite']);
        self::assertSame(24, $lignes[0]['taux']); // round(120/510*100)
    }

    /**
     * Tri décroissant par taux : la machine la plus sollicitée est en tête.
     */
    public function testTriDecroissantParTaux(): void
    {
        $projet = $this->creerProjetValide();
        $faible = $this->creerMachine('Peu utilisée');
        $forte = $this->creerMachine('Très utilisée');

        $mardi = new \DateTimeImmutable('next tuesday');
        // Faible : 60 min. Forte : 240 min (deux créneaux de 120).
        $this->reservation->creerSession($projet, $faible, ReservationType::Realisation, $mardi->setTime(9, 0), 1, dureeMinutes: 60);
        $this->reservation->creerSession($projet, $forte, ReservationType::Realisation, $mardi->setTime(9, 0), 1, dureeMinutes: 120);
        $this->reservation->creerSession($projet, $forte, ReservationType::Realisation, $mardi->setTime(13, 0), 1, dureeMinutes: 120);

        $finFenetre = $mardi->setTime(0, 0)->modify('+1 day');
        $lignes = $this->supervision->tauxUtilisationMachines($mardi->setTime(0, 0), $finFenetre);

        self::assertCount(2, $lignes);
        self::assertSame('Très utilisée', $lignes[0]['nom']);
        self::assertGreaterThan($lignes[1]['taux'], $lignes[0]['taux']);
    }
}
