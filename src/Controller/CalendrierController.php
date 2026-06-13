<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Service\CalendrierIcalService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Expose le planning FabLab en flux iCal (BF_3.1).
 *
 * La route est PUBLIQUE au sens du firewall (un client iCal externe : Google,
 * Outlook : n'a pas de session), mais protégée par un JETON secret passé dans
 * l'URL. Le jeton vit dans la configuration serveur (variable d'environnement),
 * pas en base : on couvre le besoin d'abonnement sans exposer le planning à tout
 * internet, et sans ouvrir le chantier des jetons par utilisateur.
 *
 * URL d'abonnement : https://.../calendrier/{JETON}.ics
 */
class CalendrierController extends AbstractController
{
    public function __construct(
        private readonly string $jetonCalendrier,
    ) {
    }

    /**
     * Vue calendrier HTML, accessible à tout utilisateur connecté.
     * La PORTÉE dépend du rôle (BF_6.3 ; moindre privilège, cf. RETEX) :
     *   - admin : toutes les réservations, toutes machines (BF_3.22) ;
     *   - formateur / BDE : les réservations des projets qu'ils ont validés ;
     *   - étudiant : ses propres réservations uniquement.
     * Navigation par mois via le paramètre « mois » (AAAA-MM), liens serveur.
     */
    #[Route('/calendrier', name: 'calendrier_vue', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function vue(Request $request, ReservationRepository $reservations): Response
    {
        $utilisateur = $this->getUser();

        // Portée selon le rôle (du plus large au plus restreint).
        if ($this->isGranted('ROLE_ADMIN')) {
            $resa = $reservations->aVenir();
            $portee = 'admin';
        } elseif ($this->isGranted('ROLE_FORMATEUR') || $this->isGranted('ROLE_BDE')) {
            $resa = $reservations->aVenirParValideur($utilisateur);
            $portee = 'staff';
        } else {
            $resa = $reservations->aVenirParEtudiant($utilisateur);
            $portee = 'etudiant';
        }

        // Mois affiché : paramètre AAAA-MM, sinon mois courant.
        $moisParam = (string) $request->query->get('mois', '');
        $mois = \DateTimeImmutable::createFromFormat('Y-m-d', $moisParam . '-01')
            ?: new \DateTimeImmutable('first day of this month');
        $mois = $mois->setTime(0, 0);

        return $this->render('calendrier/vue.html.twig', [
            'reservations' => $resa,
            'portee' => $portee,
            'mois' => $mois,
            'jetonCalendrier' => $this->jetonCalendrier,
        ]);
    }

    #[Route('/calendrier/{jeton}.ics', name: 'calendrier_ical', methods: ['GET'])]
    public function ical(
        string $jeton,
        ReservationRepository $reservations,
        CalendrierIcalService $ical,
    ): Response {
        // Comparaison à temps constant pour éviter les attaques temporelles.
        if (!hash_equals($this->jetonCalendrier, $jeton)) {
            throw $this->createNotFoundException();
        }

        $flux = $ical->genererFlux($reservations->aVenir());

        $response = new Response($flux);
        $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
        $response->headers->set('Content-Disposition', 'inline; filename="planning-geniuslab.ics"');

        return $response;
    }
}
