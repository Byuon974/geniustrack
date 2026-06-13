<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Consommable;
use App\Entity\Machine;
use App\Enum\MachineEtat;
use App\Repository\ConsommableRepository;
use App\Repository\MachineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Charge les données réelles du FabLab (inventaire matériel V1, déc. 2025) :
 * machines et consommables par groupe. Idempotente : ne recrée pas un élément
 * déjà présent (comparaison sur le nom), donc relançable sans danger.
 *
 * Source : « Liste matériel FabLab_V1 » (consultation Alexis BLARD, 16/12/2025).
 */
#[AsCommand(name: 'app:charger-donnees', description: 'Charge machines et consommables réels du FabLab.')]
class ChargerDonneesCommand extends Command
{
    /**
     * Machines du FabLab : [nom, type, description].
     * Le type sert au filtrage (galerie, formulaires par machine).
     */
    private const MACHINES = [
        ['Imprimante 3D Bambu Lab', 'impression_3d', 'Imprimante 3D à dépôt de filament (PLA, PETG, TPU).'],
        ['Imprimante 3D résine', 'resine', 'Imprimante 3D à résine pour pièces de précision.'],
        ['Graveuse laser', 'graveuse_laser', 'Découpe et gravure de plaques acrylique et bois.'],
        ['Plotteur de découpe', 'plotteur', 'Découpe de vinyle (rouleaux classiques et adhésifs).'],
        ['Presse à flocage', 'flocage', 'Flocage textile : t-shirts, casquettes, mugs.'],
        ['Station IoT / électronique', 'iot', 'Prototypage IoT : Raspberry, Arduino, capteurs, breadboard.'],
    ];

    /**
     * Consommables : [nom, catégorie, quantité, seuil, unité].
     * Quantités initiales raisonnables ; à ajuster dans l'app après inventaire.
     * Délai fournisseur : 14 jours (défaut, « minimum 2 semaines » selon le doc).
     */
    private const CONSOMMABLES = [
        // Impression 3D
        ['Bobine PLA gris satiné', 'impression_3d', 5, 2, 'bobine'],
        ['Bobine PLA arc-en-ciel', 'impression_3d', 3, 1, 'bobine'],
        ['Bobine PLA rouge brillant', 'impression_3d', 4, 2, 'bobine'],
        ['Bobine PLA bleu', 'impression_3d', 4, 2, 'bobine'],
        ['Bobine PLA blanc', 'impression_3d', 6, 2, 'bobine'],
        ['Bobine PLA noir et mauve (bicolore)', 'impression_3d', 2, 1, 'bobine'],
        ['Bobine PETG vert', 'impression_3d', 3, 1, 'bobine'],
        ['Bobine PETG orange', 'impression_3d', 3, 1, 'bobine'],
        ['Bobine PETG rouge', 'impression_3d', 3, 1, 'bobine'],
        ['Bobine TPU noir', 'impression_3d', 2, 1, 'bobine'],
        ['Bobine TPU blanc', 'impression_3d', 2, 1, 'bobine'],
        // Résine
        ['Bidon résine noir', 'resine', 3, 1, 'bidon'],
        ['Bidon résine blanc', 'resine', 3, 1, 'bidon'],
        ['Bidon résine bleu clair', 'resine', 2, 1, 'bidon'],
        // Graveuse laser
        ['Plaque acrylique petite noir', 'graveuse_laser', 10, 3, 'plaque'],
        ['Plaque acrylique petite transparent', 'graveuse_laser', 10, 3, 'plaque'],
        ['Plaque acrylique grande noir', 'graveuse_laser', 6, 2, 'plaque'],
        ['Plaque bois 3 mm', 'graveuse_laser', 8, 2, 'plaque'],
        ['Plaque bois 5 mm', 'graveuse_laser', 8, 2, 'plaque'],
        ['Plaque bois 10 mm', 'graveuse_laser', 5, 2, 'plaque'],
        // Plotteur de découpe
        ['Cartouche cyan', 'plotteur', 4, 1, 'cartouche'],
        ['Cartouche magenta', 'plotteur', 4, 1, 'cartouche'],
        ['Cartouche jaune', 'plotteur', 4, 1, 'cartouche'],
        ['Cartouche noir', 'plotteur', 4, 1, 'cartouche'],
        ['Rouleau vinyle classique 50x20', 'plotteur', 5, 2, 'rouleau'],
        ['Rouleau vinyle super collant 50x20', 'plotteur', 3, 1, 'rouleau'],
        // Flocage
        ['T-shirt coton blanc M', 'flocage', 20, 5, 'unité'],
        ['T-shirt coton blanc L', 'flocage', 20, 5, 'unité'],
        ['T-shirt polyester blanc M', 'flocage', 15, 5, 'unité'],
        ['Casquette coton blanc', 'flocage', 10, 3, 'unité'],
        ['Mug standard', 'flocage', 12, 4, 'unité'],
        ['Encre coton cyan', 'flocage', 3, 1, 'flacon'],
        ['Encre coton magenta', 'flocage', 3, 1, 'flacon'],
        ['Encre coton jaune', 'flocage', 3, 1, 'flacon'],
        ['Encre coton noir', 'flocage', 3, 1, 'flacon'],
        ['Papier A4 transfert', 'flocage', 100, 20, 'feuille'],
        // IoT / petit électronique
        ['Raspberry Pi', 'iot', 6, 2, 'unité'],
        ['Carte Arduino', 'iot', 8, 2, 'unité'],
        ['Carte SD', 'iot', 10, 3, 'unité'],
        ['Breadboard', 'iot', 12, 3, 'unité'],
        ['LED bleu', 'iot', 50, 10, 'unité'],
        ['LED rouge', 'iot', 50, 10, 'unité'],
        ['LED vert', 'iot', 50, 10, 'unité'],
        ['Résistance 10k', 'iot', 100, 20, 'unité'],
        ['Résistance 220', 'iot', 100, 20, 'unité'],
        ['Résistance 1k', 'iot', 100, 20, 'unité'],
        ['Capteur infrarouge', 'iot', 15, 4, 'unité'],
        ['Capteur ultrason', 'iot', 15, 4, 'unité'],
        ['Bouton poussoir', 'iot', 40, 10, 'unité'],
        ['Joystick', 'iot', 8, 2, 'unité'],
        ['Potentiomètre', 'iot', 20, 5, 'unité'],
        ['Buzzer', 'iot', 15, 4, 'unité'],
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MachineRepository $machines,
        private readonly ConsommableRepository $consommables,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Machines (idempotent : on saute celles déjà présentes par nom).
        $mAjoutees = 0;
        foreach (self::MACHINES as [$nom, $type, $description]) {
            if ($this->machines->findOneBy(['nom' => $nom])) {
                continue;
            }
            $this->em->persist((new Machine())
                ->setNom($nom)
                ->setType($type)
                ->setDescription($description)
                ->setEtat(MachineEtat::Active));
            ++$mAjoutees;
        }

        // Consommables.
        $cAjoutes = 0;
        foreach (self::CONSOMMABLES as [$nom, $cat, $qte, $seuil, $unite]) {
            if ($this->consommables->findOneBy(['nom' => $nom])) {
                continue;
            }
            $this->em->persist((new Consommable())
                ->setNom($nom)
                ->setCategorie($cat)
                ->setQuantite($qte)
                ->setSeuilMinimal($seuil)
                ->setUnite($unite));
            ++$cAjoutes;
        }

        $this->em->flush();

        $io->success(sprintf(
            '%d machine(s) et %d consommable(s) chargés (données FabLab V1).',
            $mAjoutees,
            $cAjoutes,
        ));

        return Command::SUCCESS;
    }
}
