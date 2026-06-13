<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un compte administrateur. Indispensable pour amorcer le système :
 * sur une base vierge, aucun compte n'existe et personne ne peut se connecter.
 *
 * Deux usages :
 *  - Interactif  : php bin/console app:create-admin   (pose les questions)
 *  - Automatique : php bin/console app:create-admin EMAIL MDP [PRENOM] [NOM]
 *
 * Le mode automatique permet l'amorçage par le script d'assemblage. Si le compte
 * existe déjà, la commande réussit sans rien changer (idempotente).
 */
#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur GeniusLab.',
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Adresse e-mail (@cci.re)')
            ->addArgument('motdepasse', InputArgument::OPTIONAL, 'Mot de passe')
            ->addArgument('prenom', InputArgument::OPTIONAL, 'Prénom', 'Admin')
            ->addArgument('nom', InputArgument::OPTIONAL, 'Nom', 'GeniusLab');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Email et mot de passe : pris en arguments, sinon demandés.
        $email = $input->getArgument('email') ?: $io->ask('Adresse e-mail (@cci.re)');
        if (!str_ends_with((string) $email, '@cci.re')) {
            $io->error('L\'adresse doit se terminer par @cci.re.');

            return Command::FAILURE;
        }

        // Idempotent : si le compte existe déjà, on ne fait rien et on réussit
        // (le script d'assemblage peut être relancé sans erreur).
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email])) {
            $io->success(sprintf('Le compte « %s » existe déjà : rien à faire.', $email));

            return Command::SUCCESS;
        }

        $password = $input->getArgument('motdepasse') ?: $io->askHidden('Mot de passe');
        if (empty($password)) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $prenom = $input->getArgument('prenom');
        $nom = $input->getArgument('nom');

        $admin = (new User())
            ->setEmail($email)
            ->setPrenom($prenom)
            ->setNom($nom)
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, $password));

        $this->em->persist($admin);
        $this->em->flush();

        $io->success(sprintf('Administrateur « %s » créé.', $email));

        return Command::SUCCESS;
    }
}
