<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Machine;
use App\Enum\MachineEtat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Vérifie que la Vitrine répond et masque la galerie quand elle est vide.
 */
class HomeControllerTest extends WebTestCase
{
    public function testVitrineRepondEtAfficheMachines(): void
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        // Une machine, aucun projet → la galerie doit être absente.
        $em->persist((new Machine())
            ->setNom('Imprimante 3D')->setType('impression_3d')
            ->setDureeCreneauMinutes(120)->setEtat(MachineEtat::Active));
        $em->flush();

        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Nos machines');
        self::assertSelectorExists('.machine-card');
        // Galerie projets absente (aucun projet terminé+partagé).
        self::assertSelectorNotExists('.project-grid');
    }
}
