<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Parcours d'authentification (BNF_3.1) et blocage des comptes sanctionnés (BF_6.2).
 */
class AuthenticationTest extends WebTestCase
{
    private function preparer(): array
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $tool = new \Doctrine\ORM\Tools\SchemaTool($em);
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        return [$client, $em, static::getContainer()->get(UserPasswordHasherInterface::class)];
    }

    private function creerUser(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, bool $actif = true): User
    {
        $user = (new User())
            ->setEmail('etudiant@cci.re')
            ->setNom('Test')->setPrenom('Étudiant')
            ->setRoles(['ROLE_ETUDIANT'])
            ->setActif($actif);
        $user->setPassword($hasher->hashPassword($user, 'secret123'));
        $em->persist($user);
        $em->flush();

        return $user;
    }

    public function testLoginReussit(): void
    {
        [$client, $em, $hasher] = $this->preparer();
        $this->creerUser($em, $hasher);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            '_username' => 'etudiant@cci.re',
            '_password' => 'secret123',
        ]);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testMauvaisMotDePasseEchoue(): void
    {
        [$client, $em, $hasher] = $this->preparer();
        $this->creerUser($em, $hasher);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            '_username' => 'etudiant@cci.re',
            '_password' => 'mauvais',
        ]);

        // Reste sur la page de connexion avec une erreur.
        self::assertResponseRedirects('/connexion');
    }

    public function testCompteDesactiveBloque(): void
    {
        [$client, $em, $hasher] = $this->preparer();
        $this->creerUser($em, $hasher, actif: false);

        $client->request('GET', '/connexion');
        $client->submitForm('Se connecter', [
            '_username' => 'etudiant@cci.re',
            '_password' => 'secret123',  // bon mot de passe, mais compte désactivé
        ]);

        // Connexion refusée malgré les bons identifiants (BF_6.2).
        self::assertResponseRedirects('/connexion');
    }

    public function testZoneAdminProtegee(): void
    {
        [$client, $em, $hasher] = $this->preparer();
        $this->creerUser($em, $hasher); // étudiant, pas admin

        $client->loginUser($em->getRepository(User::class)->findOneBy(['email' => 'etudiant@cci.re']));
        $client->request('GET', '/admin/machines');

        self::assertResponseStatusCodeSame(403);
    }
}
