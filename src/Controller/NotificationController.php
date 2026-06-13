<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Centre de notifications in-app (Lot 4). Tout utilisateur connecté consulte
 * ses notifications ; les non-lues sont marquées lues à l'ouverture de la liste
 * (pattern standard : ouvrir le centre, c'est avoir pris connaissance).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notifications', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        // On récupère la liste AVANT de marquer lu, pour afficher l'état
        // « non lu » des notifications fraîches dans cette même vue.
        $liste = $notifications->recentes($user);
        $notifications->marquerToutesLues($user);

        return $this->render('notification/index.html.twig', [
            'notifications' => $liste,
        ]);
    }

    #[Route('/notifications/tout-lire', name: 'app_notifications_tout_lire', methods: ['POST'])]
    public function toutLire(Request $request, NotificationRepository $notifications): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($this->isCsrfTokenValid('notif_tout_lire', (string) $request->request->get('_token'))) {
            $notifications->marquerToutesLues($user);
        }

        return $this->redirectToRoute('app_notifications');
    }
}
