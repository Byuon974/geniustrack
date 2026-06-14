<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\PlanProjet;
use App\Entity\Projet;
use App\Repository\ProjetRepository;
use App\Service\GalerieVitrineService;
use App\Service\JournalService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion de la galerie publique « Projets réalisés ».
 *
 * Regroupe toute la curation de ce qui paraît sur l'accueil : mise en avant d'un
 * projet (bascule), et choix de l'image de sa carte (réutilisée d'un fichier
 * joint, ou téléversée). La page Vitrine y renvoie. La vue admin Projets, elle,
 * se recentre sur le cycle de vie (statut, transitions).
 */
#[Route('/admin/galerie')]
#[IsGranted('ROLE_ADMIN')]
class GalerieAdminController extends AbstractController
{
    #[Route('', name: 'admin_galerie_index', methods: ['GET'])]
    public function index(ProjetRepository $repository, GalerieVitrineService $galerie): Response
    {
        // Tous les projets terminés (éligibles à la galerie), mis en avant ou non,
        // pour que l'admin puisse les activer depuis cette page.
        $projets = $repository->terminesPourCuration();
        $plansImages = [];
        foreach ($projets as $projet) {
            $plansImages[$projet->getId()] = $galerie->plansImages($projet);
        }

        return $this->render('admin/galerie/index.html.twig', [
            'projets' => $projets,
            'plansImages' => $plansImages,
        ]);
    }

    /** Bascule la mise en avant d'un projet en galerie (déplacé depuis la vue Projets). */
    #[Route('/{id}/basculer', name: 'admin_galerie_basculer', methods: ['POST'])]
    public function basculer(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('galerie'.$projet->getId(), $request->request->getString('_token'))) {
            $nouvelEtat = !$projet->isPartageAutorise();
            $projet->setPartageAutorise($nouvelEtat);
            $em->flush();
            $journal->tracer(
                $this->getUser(),
                $nouvelEtat ? 'Projet mis en avant en galerie' : 'Projet retiré de la galerie',
                $projet->getTitre()
            );
            $this->addFlash('success', $nouvelEtat
                ? 'Projet mis en avant : il apparaîtra en galerie s\'il est terminé.'
                : 'Projet retiré de la galerie.');
        }

        return $this->redirectToRoute('admin_galerie_index');
    }

    /** Définit l'image de carte depuis un fichier-image déjà joint au projet. */
    #[Route('/{id}/image-plan/{planId}', name: 'admin_galerie_image_plan', methods: ['POST'])]
    public function imageDepuisPlan(
        Request $request,
        Projet $projet,
        int $planId,
        EntityManagerInterface $em,
        GalerieVitrineService $galerie,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('image_plan'.$projet->getId(), $request->request->getString('_token'))) {
            $plan = $em->getRepository(PlanProjet::class)->find($planId);
            if (!$plan instanceof PlanProjet) {
                $this->addFlash('error', 'Fichier introuvable.');

                return $this->redirectToRoute('admin_galerie_index');
            }
            try {
                $galerie->definirDepuisPlan($projet, $plan);
                $em->flush();
                $journal->tracer($this->getUser(), 'Image de galerie définie', $projet->getTitre());
                $this->addFlash('success', 'Image de la carte mise à jour.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_galerie_index');
    }

    /** Définit l'image de carte depuis une image téléversée dédiée. */
    #[Route('/{id}/image-upload', name: 'admin_galerie_image_upload', methods: ['POST'])]
    public function imageDepuisUpload(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        GalerieVitrineService $galerie,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('image_upload'.$projet->getId(), $request->request->getString('_token'))) {
            $fichier = $request->files->get('image');
            if (null === $fichier) {
                $this->addFlash('error', 'Aucune image reçue.');

                return $this->redirectToRoute('admin_galerie_index');
            }
            // Validation simple : type image et taille raisonnable (5 Mo).
            $mime = $fichier->getMimeType();
            if (null === $mime || !str_starts_with($mime, 'image/')) {
                $this->addFlash('error', 'Le fichier doit être une image.');

                return $this->redirectToRoute('admin_galerie_index');
            }
            if ($fichier->getSize() > 5 * 1024 * 1024) {
                $this->addFlash('error', 'L\'image dépasse 5 Mo.');

                return $this->redirectToRoute('admin_galerie_index');
            }
            try {
                $galerie->definirDepuisUpload($projet, $fichier);
                $em->flush();
                $journal->tracer($this->getUser(), 'Image de galerie téléversée', $projet->getTitre());
                $this->addFlash('success', 'Image de la carte mise à jour.');
            } catch (\Throwable $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('admin_galerie_index');
    }

    /** Retire l'image de carte (retour au placeholder). */
    #[Route('/{id}/image-retirer', name: 'admin_galerie_image_retirer', methods: ['POST'])]
    public function retirerImage(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        GalerieVitrineService $galerie,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('image_retirer'.$projet->getId(), $request->request->getString('_token'))) {
            $galerie->retirerImage($projet);
            $em->flush();
            $journal->tracer($this->getUser(), 'Image de galerie retirée', $projet->getTitre());
            $this->addFlash('success', 'Image retirée.');
        }

        return $this->redirectToRoute('admin_galerie_index');
    }
}
