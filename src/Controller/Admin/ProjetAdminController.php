<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Projet;
use App\Repository\ProjetRepository;
use App\Service\JournalService;
use App\Service\ProjetWorkflowService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de TOUS les projets, réservée à l'administrateur.
 *
 * Donne à l'admin une vue d'ensemble qui lui manquait (il ne voyait jusque-là
 * que ses propres projets, comme un étudiant), et trois leviers : décider de la
 * mise en avant d'un projet en galerie publique (curation, DEC-105), faire
 * avancer un projet via une transition légale de la machine à états, et retirer
 * un projet. Aucune règle métier ici : les transitions passent par le workflow,
 * qui refuse tout saut illégal.
 */
#[Route('/admin/projets')]
#[IsGranted('ROLE_ADMIN')]
class ProjetAdminController extends AbstractController
{
    #[Route('', name: 'admin_projet_index', methods: ['GET'])]
    public function index(ProjetRepository $repository, ProjetWorkflowService $workflow): Response
    {
        $projets = $repository->tousAvecEtudiant();
        $transitions = [];
        foreach ($projets as $projet) {
            $transitions[$projet->getId()] = $workflow->transitionsPossibles($projet);
        }

        return $this->render('admin/projet/index.html.twig', [
            'projets' => $projets,
            'transitions' => $transitions,
        ]);
    }

    /**
     * Applique une transition de statut choisie par l'admin, parmi celles que la
     * machine à états autorise depuis le statut courant. Le workflow refuse tout
     * saut illégal, donc aucune vérification de cohérence à dupliquer ici.
     */
    #[Route('/{id}/transition', name: 'admin_projet_transition', methods: ['POST'])]
    public function transition(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
        JournalService $journal,
    ): Response {
        $transition = $request->request->getString('transition');
        if ($this->isCsrfTokenValid('transition'.$projet->getId(), $request->request->getString('_token'))) {
            try {
                $workflow->appliquerDepuisAdmin($projet, $transition, $this->getUser());
                $journal->tracer($this->getUser(), 'Statut projet modifié ('.$transition.')', $projet->getTitre());
                $this->addFlash('success', 'Statut mis à jour.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_projet_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_projet_supprimer', methods: ['POST'])]
    public function supprimer(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('supprimer'.$projet->getId(), $request->request->getString('_token'))) {
            $titre = $projet->getTitre();
            $journal->tracer($this->getUser(), 'Projet supprimé (admin)', $titre);
            $em->remove($projet);
            $em->flush();
            $this->addFlash('success', 'Projet supprimé.');
        }

        return $this->redirectToRoute('admin_projet_index');
    }
}
