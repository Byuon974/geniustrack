<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\SessionReservation;
use App\Form\DemandeProjetType;
use App\Repository\ProjetRepository;
use App\Security\Voter\ProjetVoter;
use App\Service\JournalService;
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

    /**
     * Soumet un brouillon existant (le fait passer en attente de validation).
     * Utile après une rétractation : le projet, repassé en brouillon et corrigé,
     * peut être resoumis sans repasser par le formulaire de création.
     */
    #[Route('/{id}/soumettre', name: 'projet_soumettre', methods: ['POST'])]
    public function soumettreBrouillon(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
        JournalService $journal,
    ): Response {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('soumettre'.$projet->getId(), $request->request->getString('_token'))) {
            $workflow->soumettre($projet);
            $journal->tracer($this->getUser(), 'Demande soumise', $projet->getTitre());
            $this->addFlash('success', 'Demande soumise. Elle est de nouveau en attente de validation.');
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Rétractation « corriger » : l'étudiant retire sa demande en attente, qui
     * repasse en brouillon (modifiable et resoumettable).
     */
    #[Route('/{id}/retracter', name: 'projet_retracter', methods: ['POST'])]
    public function retracter(
        Request $request,
        Projet $projet,
        ProjetWorkflowService $workflow,
        JournalService $journal,
    ): Response {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('retracter'.$projet->getId(), $request->request->getString('_token'))) {
            $workflow->retracter($projet);
            $journal->tracer($this->getUser(), 'Demande rétractée', $projet->getTitre());
            $this->addFlash('success', 'Demande rétractée. Elle est repassée en brouillon, vous pouvez la corriger puis la resoumettre.');
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Rétractation « supprimer » : l'étudiant abandonne définitivement une
     * demande encore en attente ou en brouillon. Retrait de l'entité (ce n'est
     * pas une transition d'état). Interdit dès que la demande a été tranchée.
     */
    #[Route('/{id}/supprimer', name: 'projet_supprimer', methods: ['POST'])]
    public function supprimer(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        JournalService $journal,
    ): Response {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        $statutSupprimable = in_array($projet->getStatut()->value, ['brouillon', 'en_attente'], true);
        if (!$statutSupprimable) {
            $this->addFlash('error', 'Cette demande ne peut plus être supprimée : elle a déjà été traitée.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        if ($this->isCsrfTokenValid('supprimer'.$projet->getId(), $request->request->getString('_token'))) {
            $titre = $projet->getTitre();
            $journal->tracer($this->getUser(), 'Demande supprimée', $titre);
            $em->remove($projet);
            $em->flush();
            $this->addFlash('success', 'Demande supprimée.');

            return $this->redirectToRoute('projet_index');
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    #[Route('/reservations/{id}/annuler', name: 'reservation_annuler', methods: ['POST'])]
    public function annulerReservation(
        Request $request,
        SessionReservation $session,
        ReservationService $reservationService,
        \App\Service\SanctionService $sanctionService,
    ): Response {
        $projet = $session->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('annuler'.$session->getId(), $request->request->getString('_token'))) {
            try {
                // BF_3.11 : annulation de la session entière (toutes ses machines).
                // Le service signale si une sanction est due, et refuse d'annuler
                // une session qui n'est plus planifiée.
                $sanctionDue = $reservationService->annuler($session);
                if ($sanctionDue) {
                    // BF_6.2 : applique réellement la sanction (cumul, désactivation à 5).
                    $desactive = $sanctionService->sanctionner(
                        $projet->getEtudiant(),
                        sprintf('Annulation tardive (réservation #%d)', $session->getId()),
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

    #[Route('/reservations/{id}/reporter', name: 'reservation_reporter_page', methods: ['GET'])]
    public function pageReportReservation(SessionReservation $session): Response
    {
        $projet = $session->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        // Page dédiée de report : calendrier + créneaux (anti-vandalisme, aucune
        // saisie de date libre). La durée du créneau d'origine est verrouillée.
        return $this->render('reservation/reporter.html.twig', [
            'session' => $session,
            'projet' => $projet,
            'dureeMinutes' => $session->getDureeMinutes(),
            'jourInitial' => $session->getDateDebut()->format('Y-m-d'),
        ]);
    }

    #[Route('/reservations/{id}/reporter', name: 'reservation_reporter', methods: ['POST'])]
    public function reporterReservation(
        Request $request,
        SessionReservation $session,
        ReservationService $reservationService,
        \App\Service\SanctionService $sanctionService,
    ): Response {
        $projet = $session->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if ($this->isCsrfTokenValid('reporter'.$session->getId(), $request->request->getString('_token'))) {
            $nouvelleDateStr = $request->request->getString('nouvelle_date');
            if ('' === $nouvelleDateStr) {
                $this->addFlash('error', 'Veuillez indiquer une nouvelle date.');

                return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
            }

            try {
                // BF_3.12 : report = ancienne session « reportée » + nouvelle session.
                [, $reportTardif] = $reservationService->reporter(
                    $session,
                    new \DateTimeImmutable($nouvelleDateStr),
                );

                if ($reportTardif) {
                    // BF_6.2 : un report tardif est sanctionné comme une annulation tardive.
                    $desactive = $sanctionService->sanctionner(
                        $projet->getEtudiant(),
                        sprintf('Report tardif (réservation #%d)', $session->getId()),
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

    /**
     * Supprime un plan joint. Autorisé seulement tant que la demande n'a pas été
     * tranchée (brouillon ou en attente) : le RETEX déconseille de modifier les
     * pièces d'une demande déjà validée.
     */
    #[Route('/plans/{id}/supprimer', name: 'plan_supprimer', methods: ['POST'])]
    public function supprimerPlan(
        Request $request,
        \App\Entity\PlanProjet $plan,
        EntityManagerInterface $em,
        \App\Service\PlanUploadService $plans,
        JournalService $journal,
    ): Response {
        $projet = $plan->getProjet();
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if (!$this->plansModifiables($projet)) {
            $this->addFlash('error', 'Les fichiers ne peuvent plus être modifiés : la demande a déjà été traitée.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        if ($this->isCsrfTokenValid('plan_supprimer'.$plan->getId(), $request->request->getString('_token'))) {
            $plans->supprimer($plan->getFichier());
            $journal->tracer($this->getUser(), 'Plan retiré', $projet->getTitre().' : '.$plan->getNomOriginal());
            $em->remove($plan);
            $em->flush();
            $this->addFlash('success', 'Fichier retiré.');
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Ajoute un ou plusieurs plans à une demande encore modifiable.
     */
    #[Route('/{id}/plans/ajouter', name: 'plan_ajouter', methods: ['POST'])]
    public function ajouterPlans(
        Request $request,
        Projet $projet,
        EntityManagerInterface $em,
        \App\Service\PlanUploadService $plans,
        JournalService $journal,
    ): Response {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if (!$this->plansModifiables($projet)) {
            $this->addFlash('error', 'Les fichiers ne peuvent plus être modifiés : la demande a déjà été traitée.');

            return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
        }

        if ($this->isCsrfTokenValid('plan_ajouter'.$projet->getId(), $request->request->getString('_token'))) {
            $fichiers = $request->files->all('plans');
            $ajoutes = 0;
            foreach ($fichiers as $fichier) {
                if (null === $fichier) {
                    continue;
                }
                $nom = $plans->stocker($fichier);
                $em->persist(new \App\Entity\PlanProjet($projet, $nom, $fichier->getClientOriginalName()));
                ++$ajoutes;
            }
            if ($ajoutes > 0) {
                $em->flush();
                $journal->tracer($this->getUser(), 'Plan(s) ajouté(s)', $projet->getTitre());
                $this->addFlash('success', $ajoutes > 1 ? 'Fichiers ajoutés.' : 'Fichier ajouté.');
            } else {
                $this->addFlash('error', 'Aucun fichier reçu.');
            }
        }

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    /**
     * Les fichiers d'une demande ne sont modifiables que tant qu'elle n'a pas
     * été tranchée (brouillon ou en attente).
     */
    private function plansModifiables(Projet $projet): bool
    {
        return in_array($projet->getStatut()->value, ['brouillon', 'en_attente'], true);
    }
}
