<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\DisponibiliteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fragments HTML de la page de reservation multi-machines.
 *
 * Deux modes selon les parametres :
 *  - sans fb_creneau : renvoie les creneaux du jour (pour une duree donnee) avec,
 *    pour chacun, le nombre de machines encore libres ;
 *  - avec fb_creneau (debut ISO) : renvoie les machines libres sur ce creneau
 *    precis, en cases a cocher (libres cochables, occupees grisees).
 *
 * L'anonymat des reservations d'autrui est preserve : on n'expose que des etats
 * et des disponibilites, jamais le projet ni la personne.
 */
#[IsGranted('ROLE_ETUDIANT')]
class DisponibiliteController extends AbstractController
{
    #[Route('/reservation/disponibilite', name: 'reservation_disponibilite', methods: ['GET'])]
    public function __invoke(
        Request $request,
        DisponibiliteService $disponibilite,
    ): Response {
        /** @var User $utilisateur */
        $utilisateur = $this->getUser();

        // Jour demande (AAAA-MM-JJ) ; a defaut, aujourd'hui.
        $jourParam = (string) $request->query->get('fb_jour', '');
        $jour = \DateTimeImmutable::createFromFormat('Y-m-d', $jourParam)
            ?: new \DateTimeImmutable('today');
        $jour = $jour->setTime(0, 0);

        // Duree envisagee (liste fermee) ; a defaut, le pas minimal.
        $duree = (int) $request->query->get('fb_duree', DisponibiliteService::PAS_MINUTES);
        if (!\in_array($duree, DisponibiliteService::dureesProposees(), true)) {
            $duree = DisponibiliteService::PAS_MINUTES;
        }

        // Mode « densités du mois » : JSON des états par jour pour le calendrier.
        $moisParam = (string) $request->query->get('fb_mois', '');
        if ('' !== $moisParam) {
            $ancre = \DateTimeImmutable::createFromFormat('Y-m', $moisParam);
            if (false === $ancre) {
                return new Response('', Response::HTTP_BAD_REQUEST);
            }

            return $this->json([
                'mois' => $ancre->format('Y-m'),
                'jours' => $disponibilite->densitesDuMois(
                    (int) $ancre->format('Y'),
                    (int) $ancre->format('n'),
                    $duree,
                    $utilisateur,
                ),
            ]);
        }

        // Mode « machines d'un creneau » si un debut precis est fourni.
        $creneauParam = (string) $request->query->get('fb_creneau', '');
        if ('' !== $creneauParam) {
            $debut = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $creneauParam);
            if (false === $debut) {
                return new Response('', Response::HTTP_BAD_REQUEST);
            }

            return $this->render('reservation/_machines_creneau.html.twig', [
                'debut' => $debut,
                'duree' => $duree,
                'machines' => $disponibilite->machinesLibresSurCreneau($debut, $duree),
            ]);
        }

        // Mode « creneaux du jour » : etat = nombre de machines libres.
        return $this->render('reservation/_disponibilite.html.twig', [
            'jour' => $jour,
            'duree' => $duree,
            'creneaux' => $disponibilite->creneauxAvecMachinesLibres($jour, $duree, $utilisateur),
        ]);
    }
}
