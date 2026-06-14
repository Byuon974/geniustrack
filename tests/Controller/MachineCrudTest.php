<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Machine;
use App\Entity\User;
use App\Enum\MachineEtat;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test fonctionnel du CRUD machine (parcours admin).
 * Tourne sur SQLite en mémoire — aucun service externe.
 */
class MachineCrudTest extends WebTestCase
{
    private function creerAdmin(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): User
    {
        $admin = (new User())
            ->setEmail('admin@cci.re')
            ->setNom('Admin')->setPrenom('Test')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($hasher->hashPassword($admin, 'password'));
        $em->persist($admin);
        $em->flush();

        return $admin;
    }

    public function testAdminPeutCreerUneMachine(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);

        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $admin = $this->creerAdmin($em, $container->get(UserPasswordHasherInterface::class));
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/admin/machines/nouveau');
        self::assertResponseIsSuccessful();

        $client->submitForm('Créer', [
            'machine[nom]' => 'Imprimante 3D Prusa',
            'machine[type]' => 'impression_3d',
            'machine[etat]' => MachineEtat::Active->value,
        ]);

        self::assertResponseRedirects('/admin/machines');

        $machine = $em->getRepository(Machine::class)->findOneBy(['nom' => 'Imprimante 3D Prusa']);
        self::assertNotNull($machine);
    }

    public function testEtudiantNePeutPasAcceder(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $etudiant = (new User())
            ->setEmail('eleve@cci.re')->setNom('E')->setPrenom('E')
            ->setRoles(['ROLE_ETUDIANT'])->setPassword('x');
        $em->persist($etudiant);
        $em->flush();

        $client->loginUser($etudiant);
        $client->request('GET', '/admin/machines');

        self::assertResponseStatusCodeSame(403);
    }
}
