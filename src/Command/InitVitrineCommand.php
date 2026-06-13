<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\ContenuVitrine;
use App\Repository\ContenuVitrineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Amorce les blocs de contenu éditables de la vitrine (BF_1.2).
 * Idempotent : ne recrée pas un bloc déjà présent.
 */
#[AsCommand(name: 'app:init-vitrine', description: 'Amorce les blocs de contenu de la vitrine (et resynchronise leurs libellés).')]
class InitVitrineCommand extends Command
{
    /** Blocs par défaut : clé => [libellé, valeur initiale, type]. */
    private const BLOCS = [
        'hero_titre' => ['Titre principal', 'Concevez, prototypez, fabriquez au GeniusLab', 'texte'],
        'hero_texte' => ['Texte d\'accroche', 'Impression 3D, découpe laser, gravure, flocage : le FabLab du campus pour vos projets pédagogiques comme pour vos prototypes personnels.', 'texte'],
        'hero_image' => ['Image d\'accroche', '', 'image'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ContenuVitrineRepository $repository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $existants = $this->repository->parCle();
        $crees = 0;
        $majLibelles = 0;

        foreach (self::BLOCS as $cle => [$libelle, $valeur, $type]) {
            if (isset($existants[$cle])) {
                // Le bloc existe : on ne touche jamais à sa valeur (contenu édité
                // par l'admin), mais on resynchronise son libellé, qui est une
                // étiquette de présentation figée et non éditable. Cela corrige
                // les libellés des installations déjà initialisées.
                $bloc = $existants[$cle];
                if ($bloc->getLibelle() !== $libelle) {
                    $bloc->setLibelle($libelle);
                    ++$majLibelles;
                }
                continue;
            }
            $this->em->persist((new ContenuVitrine())
                ->setCle($cle)->setLibelle($libelle)->setValeur($valeur)->setType($type));
            ++$crees;
        }
        $this->em->flush();

        $io->success(sprintf('%d bloc(s) créé(s), %d libellé(s) mis à jour.', $crees, $majLibelles));

        return Command::SUCCESS;
    }
}
