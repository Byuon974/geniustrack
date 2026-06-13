<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Service\SanctionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Vérifie la règle BF_6.2 : cumul de sanctions et désactivation au seuil.
 */
class SanctionServiceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private SanctionService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $this->em = $c->get(EntityManagerInterface::class);
        $this->service = $c->get(SanctionService::class);
        (new \Doctrine\ORM\Tools\SchemaTool($this->em))
            ->createSchema($this->em->getMetadataFactory()->getAllMetadata());
    }

    private function etudiant(): User
    {
        $u = (new User())->setEmail('e@cci.re')->setNom('X')->setPrenom('Y')
            ->setRoles(['ROLE_ETUDIANT'])->setPassword('x');
        $this->em->persist($u);
        $this->em->flush();

        return $u;
    }

    public function testSanctionsCumulentSansDesactiverAvantLeSeuil(): void
    {
        $u = $this->etudiant();

        for ($i = 1; $i < SanctionService::SEUIL_DESACTIVATION; ++$i) {
            $desactive = $this->service->sanctionner($u, 'test');
            self::assertFalse($desactive, "Désactivation prématurée à $i sanction(s)");
            self::assertTrue($u->estActif());
        }
        self::assertSame(SanctionService::SEUIL_DESACTIVATION - 1, $u->getNbSanctions());
    }

    public function testDesactivationAuSeuil(): void
    {
        $u = $this->etudiant();

        $desactive = false;
        for ($i = 0; $i < SanctionService::SEUIL_DESACTIVATION; ++$i) {
            $desactive = $this->service->sanctionner($u, 'test');
        }

        self::assertTrue($desactive);
        self::assertFalse($u->estActif());
        self::assertSame(SanctionService::SEUIL_DESACTIVATION, $u->getNbSanctions());
    }

    public function testLeverSanctionReactiveSousLeSeuil(): void
    {
        $u = $this->etudiant();
        for ($i = 0; $i < SanctionService::SEUIL_DESACTIVATION; ++$i) {
            $this->service->sanctionner($u, 'test');
        }
        self::assertFalse($u->estActif());

        $this->service->leverSanction($u);
        self::assertTrue($u->estActif(), 'Le compte doit être réactivé sous le seuil');
    }
}
