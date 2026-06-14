<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Projet;
use App\Entity\User;
use App\Enum\ProjetStatut;
use App\Enum\ProjetType;
use App\Service\Exception\ReservationImpossibleException;
use App\Service\ProjetWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie l'articulation B→C : cycle de vie d'un projet à travers le workflow
 * et la règle de validation différenciée formateur/BDE.
 */
class CycleProjetTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ProjetWorkflowService $workflow;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->workflow = $c->get(ProjetWorkflowService::class);
        (new \Doctrine\ORM\Tools\SchemaTool($this->em))
            ->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function user(string $email, array $roles): User
    {
        $u = (new User())->setEmail($email)->setNom('X')->setPrenom('Y')
            ->setRoles($roles)->setPassword('x');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    private function projet(User $etudiant, ProjetType $type): Projet
    {
        $p = (new Projet())->setTitre('Test')->setType($type)->setEtudiant($etudiant);
        $this->em->persist($p);
        $this->em->flush();

        return $p;
    }

    public function testCyclePedagogiqueValideParFormateur(): void
    {
        $etudiant = $this->user('e@cci.re', ['ROLE_ETUDIANT']);
        $formateur = $this->user('f@cci.re', ['ROLE_FORMATEUR']);
        $projet = $this->projet($etudiant, ProjetType::Pedagogique);

        $this->workflow->soumettre($projet);
        self::assertSame(ProjetStatut::EnAttente, $projet->getStatut());

        $this->workflow->valider($projet, $formateur);
        self::assertSame(ProjetStatut::Valide, $projet->getStatut());
        self::assertSame($formateur, $projet->getValideur());
    }

    public function testBdeNePeutPasValiderUnProjetPedagogique(): void
    {
        $etudiant = $this->user('e@cci.re', ['ROLE_ETUDIANT']);
        $bde = $this->user('bde@cci.re', ['ROLE_BDE']);
        $projet = $this->projet($etudiant, ProjetType::Pedagogique);
        $this->workflow->soumettre($projet);

        // Un BDE ne valide pas un projet pédagogique (réservé au formateur).
        $this->expectException(ReservationImpossibleException::class);
        $this->workflow->valider($projet, $bde);
    }

    public function testRefusPuisResoumission(): void
    {
        $etudiant = $this->user('e@cci.re', ['ROLE_ETUDIANT']);
        $bde = $this->user('bde@cci.re', ['ROLE_BDE']);
        $projet = $this->projet($etudiant, ProjetType::Personnel);
        $this->workflow->soumettre($projet);

        $this->workflow->refuser($projet, $bde, 'Trop volumineux');
        self::assertSame(ProjetStatut::Refuse, $projet->getStatut());
        self::assertSame('Trop volumineux', $projet->getMotifRefus());

        $this->workflow->resoumettre($projet);
        self::assertSame(ProjetStatut::Brouillon, $projet->getStatut());
        self::assertNull($projet->getMotifRefus());
    }

    public function testRetractationRamenenEnBrouillon(): void
    {
        $etudiant = $this->user('e@cci.re', ['ROLE_ETUDIANT']);
        $projet = $this->projet($etudiant, ProjetType::Pedagogique);
        $this->workflow->soumettre($projet);
        self::assertSame(ProjetStatut::EnAttente, $projet->getStatut());

        $this->workflow->retracter($projet);
        self::assertSame(ProjetStatut::Brouillon, $projet->getStatut());

        // Le brouillon rétracté peut être resoumis.
        $this->workflow->soumettre($projet);
        self::assertSame(ProjetStatut::EnAttente, $projet->getStatut());
    }
}
