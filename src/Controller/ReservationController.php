<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\PanierReservation;
use App\Entity\Machine;
use App\Entity\Projet;
use App\Entity\SessionReservation;
use App\Enum\ProjetStatut;
use App\Enum\ReservationType;
use App\Repository\MachineRepository;
use App\Security\Voter\ProjetVoter;
use App\Service\DisponibiliteService;
use App\Service\Exception\ReservationImpossibleException;
use App\Service\ProjetWorkflowService;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Page de reservation (sans etapes) : composer des creneaux, chacun associant un
 * horaire a une ou plusieurs machines en parallele, puis confirmer.
 *
 * Conception mobile-first inspiree des systemes de reservation FOSS eprouves : un
 * panier en session, une selection guidee (creneaux pre-generes cliquables, duree
 * en liste fermee, machines actives cochees). Aucune saisie libre. Le metier reste
 * dans ReservationService (capacite, quota, verrou), appele a la confirmation.
 */
#[Route('/projets/{id}/reserver', requirements: ['id' => '\d+'])]
#[IsGranted('ROLE_ETUDIANT')]
final class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly ProjetWorkflowService $workflow,
        private readonly MachineRepository $machines,
    ) {
    }

    #[Route('', name: 'reservation_creer', methods: ['GET'])]
    public function page(Request $request, Projet $projet): Response
    {
        $this->garderProjetReservable($projet);

        return $this->render('reservation/wizard.html.twig', [
            'projet' => $projet,
            'panier' => $this->panier($request, $projet),
            'machinesDispo' => $this->machines->actives(),
            'dureesProposees' => DisponibiliteService::dureesProposees(),
        ]);
    }

    #[Route('/ajouter', name: 'reservation_ajouter', methods: ['POST'])]
    public function ajouter(Request $request, Projet $projet): Response
    {
        $this->garderProjetReservable($projet);
        $this->verifierJeton($request, 'reservation_ajouter');
        $panier = $this->panier($request, $projet);

        if (\count($panier->creneaux) >= PanierReservation::MAX_CRENEAUX) {
            $this->addFlash('error', sprintf('Maximum %d créneaux par réservation.', PanierReservation::MAX_CRENEAUX));

            return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
        }

        $creneau = $this->lireCreneauSoumis($request);
        if (null === $creneau) {
            $this->addFlash('error', 'Choisissez un créneau et au moins une machine libre.');

            return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
        }

        $panier->creneaux[] = $creneau;
        $this->sauverPanier($request, $projet, $panier);

        return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
    }

    #[Route('/retirer/{index}', name: 'reservation_retirer', requirements: ['index' => '\d+'], methods: ['POST'])]
    public function retirer(Request $request, Projet $projet, int $index): Response
    {
        $this->garderProjetReservable($projet);
        $this->verifierJeton($request, 'reservation_retirer');
        $panier = $this->panier($request, $projet);

        if (isset($panier->creneaux[$index])) {
            unset($panier->creneaux[$index]);
            $panier->creneaux = array_values($panier->creneaux);
            $this->sauverPanier($request, $projet, $panier);
        }

        return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
    }

    #[Route('/verifier', name: 'reservation_verifier', methods: ['GET'])]
    public function verifier(Request $request, Projet $projet): Response
    {
        $this->garderProjetReservable($projet);
        $panier = $this->panier($request, $projet);

        // Pas de revue d'un panier vide : on renvoie composer un créneau.
        if ($panier->estVide()) {
            return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
        }

        return $this->render('reservation/verifier.html.twig', [
            'projet' => $projet,
            'panier' => $panier,
            'machinesDispo' => $this->machines->actives(),
        ]);
    }

    #[Route('/confirmer', name: 'reservation_confirmer', methods: ['POST'])]
    public function confirmer(Request $request, Projet $projet): Response
    {
        $this->garderProjetReservable($projet);
        $this->verifierJeton($request, 'reservation_confirmer');
        $panier = $this->panier($request, $projet);

        if ($panier->estVide()) {
            $this->addFlash('error', 'Ajoutez au moins un créneau avant de confirmer.');

            return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
        }

        try {
            $this->creerDepuisPanier($projet, $panier);
        } catch (ReservationImpossibleException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('reservation_creer', ['id' => $projet->getId()]);
        }

        $nbCreneaux = \count($panier->creneaux);
        $this->viderPanier($request, $projet);
        $this->addFlash('success', sprintf(
            '%d créneau%s réservé%s. Un récapitulatif vous a été envoyé par e-mail.',
            $nbCreneaux, $nbCreneaux > 1 ? 'x' : '', $nbCreneaux > 1 ? 's' : ''
        ));

        return $this->redirectToRoute('projet_show', ['id' => $projet->getId()]);
    }

    private function garderProjetReservable(Projet $projet): void
    {
        $this->denyAccessUnlessGranted(ProjetVoter::EDIT, $projet);

        if (!\in_array($projet->getStatut(), [ProjetStatut::Valide, ProjetStatut::EnCours], true)) {
            $this->addFlash('error', "Ce projet n'est pas encore validé.");

            throw $this->createNotFoundException('Projet non réservable.');
        }
    }

    private function cleSession(Projet $projet): string
    {
        return sprintf('reservation_panier_%d', $projet->getId());
    }

    private function panier(Request $request, Projet $projet): PanierReservation
    {
        $donnees = $request->getSession()->get($this->cleSession($projet), []);

        return PanierReservation::depuisSession(\is_array($donnees) ? $donnees : []);
    }

    private function sauverPanier(Request $request, Projet $projet, PanierReservation $panier): void
    {
        $request->getSession()->set($this->cleSession($projet), $panier->versSession());
    }

    private function viderPanier(Request $request, Projet $projet): void
    {
        $request->getSession()->remove($this->cleSession($projet));
    }

    private function verifierJeton(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /**
     * Lit un creneau soumis : debut (creneau propose), duree (liste fermee),
     * personnes, et la liste des machines cochees (toutes actives). Renvoie null
     * si la saisie est incomplete ou hors bornes.
     *
     * @return array{debut: string, duree: int, personnes: int, machines: list<int>}|null
     */
    private function lireCreneauSoumis(Request $request): ?array
    {
        $debut = (string) $request->request->get('debut');
        $duree = $request->request->getInt('duree');
        $personnes = $request->request->getInt('personnes', 1);
        $typeSoumis = (string) $request->request->get('type', ReservationType::Realisation->value);
        /** @var list<int> $machinesSoumises */
        $machinesSoumises = array_map('intval', (array) $request->request->all('machines'));

        if ('' === $debut || [] === $machinesSoumises) {
            return null;
        }

        if (!\in_array($duree, DisponibiliteService::dureesProposees(), true)) {
            return null;
        }

        if ($personnes < 1 || $personnes > SessionReservation::CAPACITE_MAX_FABLAB) {
            return null;
        }

        // Type en liste fermée (anti-vandalisme) : préparation ou réalisation.
        $type = ReservationType::tryFrom($typeSoumis);
        if (null === $type) {
            return null;
        }

        $debutDate = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $debut);
        if (false === $debutDate) {
            return null;
        }

        // Chaque machine doit exister et etre active (anti-vandalisme).
        $actives = $this->machines->actives();
        $machines = [];
        foreach (array_unique($machinesSoumises) as $mid) {
            $machine = $this->machines->find($mid);
            if (null === $machine || !\in_array($machine, $actives, true)) {
                return null;
            }
            $machines[] = $mid;
        }

        return [
            'debut' => $debutDate->format('Y-m-d\TH:i'),
            'duree' => $duree,
            'personnes' => $personnes,
            'type' => $type->value,
            'machines' => $machines,
        ];
    }

    /**
     * Cree les reservations du panier en UN SEUL lot atomique. Un creneau a N
     * machines produit N reservations au meme horaire, mais l'effectif (nombre
     * de personnes) ne compte QU'UNE FOIS par creneau : le meme groupe utilise
     * plusieurs machines en parallele, il n'occupe pas N fois la capacite. La
     * premiere reservation du creneau porte l'effectif reel ; les machines
     * supplementaires du meme creneau portent 0 (groupe deja compte).
     *
     * Tout le panier est cree dans une seule transaction : en cas d'echec sur
     * une machine, rien n'est cree, et le panier reste reproposable sans risque
     * de doublon.
     *
     * @throws ReservationImpossibleException
     */
    private function creerDepuisPanier(Projet $projet, PanierReservation $panier): void
    {
        $lots = [];
        foreach ($panier->creneaux as $creneau) {
            $debut = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $creneau['debut']);
            if (false === $debut) {
                throw new ReservationImpossibleException('Créneau invalide.');
            }

            $type = ReservationType::tryFrom((string) ($creneau['type'] ?? ReservationType::Realisation->value));
            if (null === $type) {
                throw new ReservationImpossibleException('Type de session invalide.');
            }

            // Les machines du créneau, résolues en entités. L'effectif est porté
            // UNE fois par la session, plus de ligne à 0 : le service crée une
            // occupation par machine, toutes rattachées à la même session.
            $machines = [];
            foreach ($creneau['machines'] as $machineId) {
                $machine = $this->machines->find((int) $machineId);
                if (null === $machine) {
                    throw new ReservationImpossibleException('Machine introuvable.');
                }
                $machines[] = $machine;
            }

            $lots[] = [
                'projet' => $projet,
                'type' => $type,
                'debut' => $debut,
                'nbPersonnes' => (int) $creneau['personnes'],
                'duree' => (int) $creneau['duree'],
                'machines' => $machines,
            ];
        }

        $this->reservationService->creerSessionsLot($lots);

        try {
            $this->workflow->demarrer($projet);
        } catch (\Throwable) {
            // Deja en cours : non bloquant.
        }
    }
}
