<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\SanctionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Réactive un compte désactivé, identifié par son e-mail. Utile quand un compte
 * (souvent un admin) a été désactivé à la main depuis le menu et qu'on s'est
 * verrouillé dehors : cette commande le réactive sans toucher au reste de la
 * base, donc sans hard reset.
 *
 * Un compte peut être désactivé pour deux raisons : désactivation manuelle, ou
 * cumul de sanctions (seuil atteint). Par défaut on ne fait que réactiver. Avec
 * --lever-sanctions, on lève aussi les sanctions actives, sinon un compte
 * désactivé par sanctions resterait à risque d'être redésactivé.
 *
 * Usage :
 *   php bin/console app:activer-compte EMAIL [--lever-sanctions]
 */
#[AsCommand(
    name: 'app:activer-compte',
    description: 'Réactive un compte désactivé, identifié par son e-mail.',
)]
class ActiverCompteCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $utilisateurs,
        private readonly SanctionRepository $sanctions,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'E-mail du compte à réactiver')
            ->addOption('lever-sanctions', null, InputOption::VALUE_NONE, 'Lève aussi les sanctions actives du compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $compte = $this->utilisateurs->findOneBy(['email' => $email]);
        if (null === $compte) {
            $io->error(sprintf('Aucun compte avec l\'e-mail « %s ».', $email));

            return Command::FAILURE;
        }

        // Levée des sanctions actives si demandée (cas désactivation par cumul).
        if ($input->getOption('lever-sanctions')) {
            $actives = $this->sanctions->compterActives($compte);
            if ($actives > 0) {
                foreach ($this->sanctions->historique($compte) as $sanction) {
                    if ($sanction->estActive()) {
                        $sanction->lever();
                    }
                }
                $io->note(sprintf('%d sanction(s) active(s) levée(s).', $actives));
            }
        }

        if ($compte->estActif()) {
            $io->success(sprintf('Le compte « %s » est déjà actif.', $email));

            return Command::SUCCESS;
        }

        $compte->setActif(true);
        $this->em->flush();

        $io->success(sprintf('Compte « %s » réactivé.', $email));

        return Command::SUCCESS;
    }
}
