<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\MachineRepository;
use App\Repository\ProjetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Vitrine publique du FabLab (BF_1.1).
 * Trois sections : présentation (hero statique), machines (toujours présentes),
 * galerie de projets réalisés (affichée seulement si elle n'est pas vide).
 *
 * Le hero est statique et la galerie est une grille de cartes filtrable :
 * structure retenue pour l'accessibilité (troubles moteurs, dyslexie) et la
 * lisibilité. Justification détaillée dans docs/LOT-VITRINE-README.md.
 */
class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        MachineRepository $machineRepository,
        ProjetRepository $projetRepository,
        \App\Repository\ContenuVitrineRepository $contenuRepository,
    ): Response {
        // BF_2.2 : la galerie n'affiche que les projets terminés ET partagés.
        $projetsGalerie = $projetRepository->pourGalerie();

        return $this->render('home/index.html.twig', [
            'machines' => $machineRepository->findBy([], ['nom' => 'ASC']),
            'projets' => $projetsGalerie,
            // Catégories de machines présentes, pour les filtres de la galerie.
            'categories' => $this->categoriesProjets($projetsGalerie),
            // BF_1.2 : contenus éditables (repli sur défauts dans le template).
            'contenus' => $contenuRepository->parCle(),
        ]);
    }

    /**
     * Extrait les types de machines réellement représentés dans la galerie,
     * pour ne proposer que des filtres utiles (pas de catégorie vide).
     *
     * @param array $projets
     */
    private function categoriesProjets(array $projets): array
    {
        $cats = [];
        foreach ($projets as $projet) {
            foreach ($projet->getMachines() as $machine) {
                $cats[$machine->getType()] = true;
            }
        }

        return array_keys($cats);
    }
}
