<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Projet;
use App\Enum\ProjetType;
use App\Repository\ProjetRepository;
use App\Service\Exception\ReservationImpossibleException;
use App\Service\ProjetWorkflowService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Validation des demandes (BF_3.4 formateur, BF_3.5 BDE).
 * La règle « qui valide quoi » (pédago→formateur, perso→BDE) est portée par
 * ProjetWorkflowService::valider() ; ce controller filtre la file selon le rôle.
 */
#[Route('/demandes')]
class DemandeController extends AbstractController
{
    #[Route('', name: 'demande_index', methods: ['GET'])]
    #[IsGranted(new \Symfony\Component\ExpressionLanguage\Expression('is_granted("ROLE_FORMATEUR") or is_granted("ROLE_BDE")'))]
    public function index(ProjetRepository $repository): Response
    {
        // Chaque rôle ne voit que les demandes qu'il peut traiter.
        $enAttente = $repository->enAttenteDeValidation();
        $visibles = array_filter($enAttente, fn (Projet $p) => $this->peutValider($p));

        return $this->render('demande/index.html.twig', ['projets' => $visibles]);
    }

    #[Route('/{id}', name: 'demande_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    #[IsGranted(new \Symfony\Component\ExpressionLanguage\Expression('is_granted("ROLE_FORMATEUR") or is_granted("ROLE_BDE")'))]
    public function show(Projet $projet, ProjetRepository $repository): Response
    {
        // Lecture seule : le valideur habilité (ou l'admin) peut examiner la
        // demande et ses plans en détail. Le Voter VIEW porte cette règle.
        $this->denyAccessUnlessGranted(\App\Security\Voter\ProjetVoter::VIEW, $projet);

        return $this->render('demande/show.html.twig', [
            'projet' => $projet,
            'peutValider' => $this->peutValider($projet),
            'premiereUtilisation' => $repository->estPremierProjet($projet),
        ]);
    }

    #[Route('/{id}/valider', name: 'demande_valider', methods: ['POST'])]
    #[IsGranted(new \Symfony\Component\ExpressionLanguage\Expression('is_granted("ROLE_FORMATEUR") or is_granted("ROLE_BDE")'))]
    public function valider(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
    ): Response {
        if ($this->isCsrfTokenValid('valider'.$projet->getId(), $request->request->getString('_token'))) {
            try {
                // Le service vérifie que le rôle correspond au type de projet.
                $workflow->valider($projet, $this->getUser());
                $this->addFlash('success', 'Projet validé. L\'étudiant en est informé.');
            } catch (ReservationImpossibleException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('demande_index');
    }

    #[Route('/{id}/refuser', name: 'demande_refuser', methods: ['POST'])]
    #[IsGranted(new \Symfony\Component\ExpressionLanguage\Expression('is_granted("ROLE_FORMATEUR") or is_granted("ROLE_BDE")'))]
    public function refuser(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
    ): Response {
        if ($this->isCsrfTokenValid('refuser'.$projet->getId(), $request->request->getString('_token'))) {
            $motif = trim($request->request->getString('motif'));
            if ('' === $motif) {
                $this->addFlash('error', 'Un motif de refus est requis.');

                return $this->redirectToRoute('demande_index');
            }

            try {
                $workflow->refuser($projet, $this->getUser(), $motif);
                $this->addFlash('success', 'Projet refusé. L\'étudiant en est informé avec le motif.');
            } catch (ReservationImpossibleException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('demande_index');
    }

    /** Le rôle de l'utilisateur correspond-il au type de projet ? */
    private function peutValider(Projet $projet): bool
    {
        return match ($projet->getType()) {
            ProjetType::Pedagogique => $this->isGranted('ROLE_FORMATEUR'),
            ProjetType::Personnel => $this->isGranted('ROLE_BDE'),
        };
    }
}
