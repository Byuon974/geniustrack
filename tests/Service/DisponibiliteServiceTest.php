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
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Prouve les règles de génération des créneaux : liste fermée des durées,
 * exclusion de la pause déjeuner (12h-13h), respect de la fermeture (16h30),
 * et décompte des machines libres par créneau pour une durée donnée.
 *
 * SQLite en mémoire, schéma créé à la volée.
 */
class DisponibiliteServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private DisponibiliteService $disponibilite;
    private ReservationService $reservation;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->disponibilite = $container->get(DisponibiliteService::class);
        $this->reservation = $container->get(ReservationService::class);

        $tool = new \Doctrine\ORM\Tools\SchemaTool($this->em);
        $tool->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function creerEtudiant(): User
    {
        $u = (new User())
            ->setEmail('eleve@cci.re')
            ->setNom('Test')->setPrenom('Élève')
            ->setRoles(['ROLE_ETUDIANT'])
            ->setPassword('x');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
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

    private function creerProjetValide(User $etudiant): Projet
    {
        $p = (new Projet())
            ->setTitre('Projet test')
            ->setType(ProjetType::Personnel)
            ->setStatut(ProjetStatut::Valide)
            ->setEtudiant($etudiant);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    /** La liste des durées proposées est fermée : 30 min à 4 h par pas de 30. */
    public function testDureesProposeesEstUneListeFermee(): void
    {
        self::assertSame(
            [30, 60, 90, 120, 150, 180, 210, 240],
            DisponibiliteService::dureesProposees()
        );
    }

    /**
     * Aucun créneau ne chevauche la pause déjeuner (12h-13h). Pour une durée de
     * 60 min, le créneau 11h30 (finirait à 12h30) et le créneau 12h00 sont
     * exclus ; 11h00 (finit à 12h00 pile) et 13h00 sont admis.
     */
    public function testPauseDejeunerExclueDesCreneaux(): void
    {
        $etudiant = $this->creerEtudiant();
        $machine = $this->creerMachine('Imprimante');
        $jour = new \DateTimeImmutable('next monday');

        $creneaux = $this->disponibilite->creneauxDuJour($machine, $jour, $etudiant, 60);
        $heures = array_map(static fn (array $c) => $c['debut']->format('H:i'), $creneaux);

        self::assertContains('11:00', $heures, 'créneau finissant à 12h00 pile doit être admis');
        self::assertContains('13:00', $heures, 'créneau après la pause doit être admis');
        self::assertNotContains('11:30', $heures, 'chevauche la pause (finit 12h30)');
        self::assertNotContains('12:00', $heures, 'en pleine pause');
        self::assertNotContains('12:30', $heures, 'en pleine pause');
    }

    /**
     * Le dernier créneau d'une durée donnée ne dépasse pas la fermeture (16h30).
     * Pour 120 min, le dernier début admissible est 14h30 (finit 16h30 pile).
     */
    public function testFermetureRespectee(): void
    {
        $etudiant = $this->creerEtudiant();
        $machine = $this->creerMachine('Imprimante');
        $jour = new \DateTimeImmutable('next monday');

        $creneaux = $this->disponibilite->creneauxDuJour($machine, $jour, $etudiant, 120);
        $heures = array_map(static fn (array $c) => $c['debut']->format('H:i'), $creneaux);

        self::assertContains('14:30', $heures, 'dernier créneau de 2h finit à 16h30 pile');
        self::assertNotContains('15:00', $heures, 'finirait à 17h00, après fermeture');
    }

    /**
     * Décompte des machines libres par créneau : avec deux machines actives, un
     * créneau sans réservation en montre deux ; après réservation de l'une sur
     * un créneau, ce créneau n'en montre plus qu'une.
     */
    public function testMachinesLibresParCreneau(): void
    {
        $etudiant = $this->creerEtudiant();
        $projet = $this->creerProjetValide($etudiant);
        $m1 = $this->creerMachine('Machine 1');
        $this->creerMachine('Machine 2');

        $jour = new \DateTimeImmutable('next monday');
        $debut10h = $jour->setTime(10, 0);

        // Avant toute réservation : 2 machines libres à 10h00.
        $avant = $this->disponibilite->creneauxAvecMachinesLibres($jour, 60, $etudiant);
        $creneau10hAvant = $this->creneauA($avant, '10:00');
        self::assertSame(2, $creneau10hAvant['machinesLibres']);

        // On occupe m1 à 10h00 pour 60 min.
        $this->reservation->creerSession(
            $projet, ReservationType::Realisation, $debut10h, 1, 60, [$m1]
        );

        // Après : plus qu'une machine libre à 10h00.
        $apres = $this->disponibilite->creneauxAvecMachinesLibres($jour, 60, $etudiant);
        $creneau10hApres = $this->creneauA($apres, '10:00');
        self::assertSame(1, $creneau10hApres['machinesLibres']);
        self::assertSame('occupe', $creneau10hApres['etat']);
    }

    /**
     * @param array<int, array{heure: string, debut: \DateTimeImmutable, etat: string, machinesLibres: int}> $creneaux
     *
     * @return array{heure: string, debut: \DateTimeImmutable, etat: string, machinesLibres: int}
     */
    private function creneauA(array $creneaux, string $hhmm): array
    {
        foreach ($creneaux as $c) {
            if ($c['debut']->format('H:i') === $hhmm) {
                return $c;
            }
        }
        self::fail(sprintf('Aucun créneau à %s', $hhmm));
    }
}
