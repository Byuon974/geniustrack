<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Reservation;
use App\Form\DemandeProjetType;
use App\Repository\ProjetRepository;
use App\Security\Voter\ProjetVoter;
use App\Service\ProjetWorkflowService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Parcours projet côté étudiant (BF_3.2, 3.4 demande, 3.11, 3.21).
 * Controller mince : délègue au ProjetWorkflowService (transitions) et au
 * ReservationService (créneaux). Aucune règle métier ici.
 */
#[Route('/projets')]
#[IsGranted('ROLE_ETUDIANT')]
class ProjetController extends AbstractController
{
    #[Route('', name: 'projet_index', methods: ['GET'])]
    public function index(ProjetRepository $repository): Response
    {
        // BF_3.21 : l'étudiant consulte le statut de SES projets.
        $projets = $repository->findBy(
            ['etudiant' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('projet/index.html.twig', ['projets' => $projets]);
    }

    #[Route('/nouveau', name: 'projet_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        ProjetWorkflowService $workflow,
        \App\Service\PlanUploadService $plans,
    ): Response {
        $projet = new Projet();
        $projet->setEtudiant($this->getUser());

        $form = $this->createForm(DemandeProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($projet);

            // BF_3.7 : stocke les plans importés et les rattache au projet.
            $fichiers = $form->get('plansFiles')->getData();
            foreach ($fichiers as $fichier) {
                if (null === $fichier) {
                    continue;
                }
                $nom = $plans->stocker($fichier);
                $em->persist(new \App\Entity\PlanProjet(
                    $projet, $nom, $fichier->getClientOriginalName()
                ));
            }

            $em->flush();

            // Soumission = transition workflow (brouillon → en_attente),
            // qui déclenche la notification au valideur via le listener.
            $workflow->soumettre($projet);

            $this->addFlash('success', 'Votre demande a été soumise.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        return $this->render('projet/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'projet_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Projet $projet): Response
    {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        return $this->render('projet/show.html.twig', ['projet' => $projet]);
    }

    #[Route('/{id}/resoumettre', name: 'projet_resoumettre', methods: ['POST'])]
    public function resoumettre(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
    ): Response {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('resoumettre'.$projet->getId(), $request->request->getString('_token'))) {
            $workflow->resoumettre($projet);
            $this->addFlash('success', 'Projet remis en brouillon, vous pouvez le corriger.');
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    #[Route('/reservations/{id}/annuler', name: 'reservation_annuler', methods: ['POST'])]
    public function annulerReservation(
        Request $request,
        Reservation $reservation,
        ReservationService $reservationService,
        \App\Service\SanctionService $sanctionService,
    ): Response {
        $projet = $reservation->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('annuler'.$reservation->getId(), $request->request->getString('_token'))) {
            try {
                // BF_3.11 : annulation. Le service signale si une sanction est due,
                // et refuse d'annuler une réservation qui n'est plus planifiée.
                $sanctionDue = $reservationService->annuler($reservation);
                if ($sanctionDue) {
                    // BF_6.2 : applique réellement la sanction (cumul, désactivation à 5).
                    $desactive = $sanctionService->sanctionner(
                        $projet->getEtudiant(),
                        sprintf('Annulation tardive (réservation #%d)', $reservation->getId()),
                    );
                    if ($desactive) {
                        $this->addFlash('error', 'Annulation tardive : seuil de sanctions atteint, votre compte est désactivé.');
                    } else {
                        $this->addFlash('error', 'Annulation tardive (moins de 3 jours) : une sanction a été enregistrée.');
                    }
                } else {
                    $this->addFlash('success', 'Réservation annulée.');
                }
            } catch (\App\Service\Exception\ReservationImpossibleException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    #[Route('/reservations/{id}/reporter', name: 'reservation_reporter', methods: ['POST'])]
    public function reporterReservation(
        Request $request,
        Reservation $reservation,
        ReservationService $reservationService,
        \App\Service\SanctionService $sanctionService,
    ): Response {
        $projet = $reservation->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('reporter'.$reservation->getId(), $request->request->getString('_token'))) {
            $nouvelleDateStr = $request->request->getString('nouvelle_date');
            if ('' === $nouvelleDateStr) {
                $this->addFlash('error', 'Veuillez indiquer une nouvelle date.');

                return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
            }

            try {
                // BF_3.12 : report = ancien créneau « reporté » + nouveau créneau.
                [, $reportTardif] = $reservationService->reporter(
                    $reservation,
                    new \DateTimeImmutable($nouvelleDateStr),
                );

                if ($reportTardif) {
                    // BF_6.2 : un report tardif est sanctionné comme une annulation tardive.
                    $desactive = $sanctionService->sanctionner(
                        $projet->getEtudiant(),
                        sprintf('Report tardif (réservation #%d)', $reservation->getId()),
                    );
                    $this->addFlash('error', $desactive
                        ? 'Report tardif : seuil de sanctions atteint, votre compte est désactivé.'
                        : 'Report effectué, mais tardif (moins de 3 jours) : une sanction a été enregistrée.');
                } else {
                    $this->addFlash('success', 'Réservation reportée.');
                }
            } catch (\App\Service\Exception\ReservationImpossibleException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    #[Route('/plans/{id}/telecharger', name: 'plan_telecharger', methods: ['GET'])]
    public function telechargerPlan(
        \App\Entity\PlanProjet $plan,
        \App\Service\PlanUploadService $plans,
    ): \Symfony\Component\HttpFoundation\BinaryFileResponse {
        // Les plans sont hors public/ : on contrôle l'accès avant de servir.
        // VIEW autorise le propriétaire, l'admin et le valideur habilité (lecture).
        $this->denyAccessUnlessGranted(ProjetVoter::VIEW, $plan->getProjet());

        $chemin = $plans->cheminComplet($plan->getFichier());
        if (!is_file($chemin)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        return $this->file($chemin, $plan->getNomOriginal());
    }
}
