<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\MouvementStockRepository;
use App\Repository\SessionReservationRepository;
use App\Service\SupervisionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Supervision analytique du laboratoire (BF_7.x, vue de tendance).
 *
 * Distincte du tableau de bord opérationnel : celui-ci sert l'action immédiate,
 * la supervision sert la lecture de l'évolution dans le temps. Trois axes :
 * activité de réservation, taux d'utilisation des machines, fluctuations de
 * consommables. Controller de lecture : agrège via repositories et service,
 * passe les données à la vue, sans écriture.
 *
 * Accès : rôles de pilotage (admin, formateur, BDE), comme le tableau de bord.
 */
#[Route('/pilotage')]
#[IsGranted('ROLE_FORMATEUR')]
final class SupervisionController extends AbstractController
{
    #[Route('/supervision/{annee}', name: 'pilotage_supervision', requirements: ['annee' => '\d{4}'], defaults: ['annee' => null], methods: ['GET'])]
    public function index(
        ?int $annee,
        SessionReservationRepository $reservations,
        MouvementStockRepository $mouvements,
        SupervisionService $supervision,
    ): Response {
        $annee ??= (int) date('Y');
        $debut = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $annee));
        $fin = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $annee + 1));

        // Pour le taux d'utilisation, le dénominateur (temps d'ouverture) doit
        // refléter la période réellement écoulée : sinon, sur l'année en cours,
        // on diviserait par une capacité incluant des mois futurs sans aucune
        // réservation, ce qui écraserait tous les taux vers 0 %. On borne donc la
        // fin de la fenêtre du taux à demain matin si l'année est celle en cours.
        // (RETEX gestion d'atelier : taux = temps utilisé / temps d'ouverture
        // *sur la période écoulée*.) Les années passées gardent l'année entière.
        $finTaux = $fin;
        $demain = (new \DateTimeImmutable('today'))->modify('+1 day');
        if ($finTaux > $demain) {
            $finTaux = $demain;
        }

        return $this->render('admin/supervision/index.html.twig', [
            'annee' => $annee,
            'reservationsParMois' => $reservations->reservationsParMois($debut, $fin),
            'tauxMachines' => $supervision->tauxUtilisationMachines($debut, $finTaux),
            'stockParMois' => $mouvements->variationParMois($debut, $fin),
            'mouvementsRecents' => $mouvements->recents(10),
        ]);
    }
}
