<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Machine;
use App\Form\MachineType;
use App\Repository\MachineRepository;
use App\Repository\ReservationRepository;
use App\Service\PhotoUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des machines (BF_5.1, BF_5.2, BF_5.3). Réservé aux admins.
 * Controller mince : il orchestre le formulaire et délègue la persistance à
 * l'EntityManager / au repository. Aucune logique métier complexe ici (le CRUD
 * machine est volontairement trivial : pas de mécanisme superflu).
 */
#[Route('/admin/machines')]
#[IsGranted('ROLE_ADMIN')]
class MachineController extends AbstractController
{
    #[Route('', name: 'admin_machine_index', methods: ['GET'])]
    public function index(MachineRepository $repository): Response
    {
        return $this->render('admin/machine/index.html.twig', [
            'machines' => $repository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/nouveau', name: 'admin_machine_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PhotoUploadService $photos): Response
    {
        $machine = new Machine();
        $form = $this->createForm(MachineType::class, $machine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->traiterPhoto($form, $machine, $photos);
            $em->persist($machine);
            $em->flush();
            $this->addFlash('success', 'Machine ajoutée.');

            return $this->redirectToRoute('admin_machine_index');
        }

        return $this->render('admin/machine/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'admin_machine_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Machine $machine, EntityManagerInterface $em, PhotoUploadService $photos): Response
    {
        $form = $this->createForm(MachineType::class, $machine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->traiterPhoto($form, $machine, $photos);
            $em->flush();
            $this->addFlash('success', 'Machine mise à jour.');

            return $this->redirectToRoute('admin_machine_index');
        }

        return $this->render('admin/machine/edit.html.twig', [
            'machine' => $machine,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_machine_delete', methods: ['POST'])]
    public function delete(Request $request, Machine $machine, EntityManagerInterface $em, PhotoUploadService $photos, ReservationRepository $reservations): Response
    {
        // Protection CSRF : le token vient du formulaire de suppression.
        if ($this->isCsrfTokenValid('delete'.$machine->getId(), $request->request->getString('_token'))) {
            // On ne supprime pas une machine qui a un historique de réservations :
            // la clé étrangère l'interdit (erreur base), et surtout cet historique
            // doit être préservé. La fin de vie d'une machine référencée se gère
            // par l'état « hors service », pas par une suppression dure. La
            // suppression reste permise pour une machine jamais réservée.
            if ($reservations->compterPourMachine($machine) > 0) {
                $this->addFlash('error', 'Cette machine a un historique de réservations et ne peut pas être supprimée. Passez-la « hors service » pour la retirer de la réservation tout en conservant son historique.');

                return $this->redirectToRoute('admin_machine_index');
            }

            // Nettoie le fichier physique avant de retirer l'entité.
            $photos->supprimer($machine->getPhoto());
            $em->remove($machine);
            $em->flush();
            $this->addFlash('success', 'Machine supprimée.');
        }

        return $this->redirectToRoute('admin_machine_index');
    }

    /**
     * Traite le fichier photo uploadé (champ non mappé) : enregistre et met à
     * jour le nom sur l'entité, en supprimant l'ancien fichier au passage.
     */
    private function traiterPhoto($form, Machine $machine, PhotoUploadService $photos): void
    {
        /** @var \Symfony\Component\HttpFoundation\File\UploadedFile|null $fichier */
        $fichier = $form->get('photoFile')->getData();
        if (null !== $fichier) {
            $nom = $photos->remplacer($fichier, $machine->getPhoto());
            $machine->setPhoto($nom);
        }
    }
}
