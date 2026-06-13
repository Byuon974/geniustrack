<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ConsommableRepository;
use App\Repository\JournalActiviteRepository;
use App\Repository\ProjetRepository;
use App\Repository\ReservationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pilotage (BF_7.1 tableau de bord, BF_8.1 journal, BF_3.22 calendrier).
 * Controller de lecture : il agrège via les repositories et passe les données
 * aux vues. Pas d'écriture, pas de logique métier.
 *
 * Le tableau de bord est différencié par rôle (BF_6.3 : vues selon le rôle) :
 * l'administrateur pilote l'exploitation (parc, stock, demandes globales) ;
 * le formateur et le BDE voient les demandes du type qu'ils valident et les
 * sessions des projets correspondants.
 *
 * Choix d'URL : le tableau de bord vit sous /pilotage (ouvert aux rôles de
 * pilotage : admin, formateur, BDE), et non sous /admin, que le pare-feu
 * verrouille strictement à ROLE_ADMIN. Le journal d'activité, lui, reste sous
 * /admin car il est réservé à l'administrateur. Les chemins sont donc portés
 * par chaque méthode plutôt que par un préfixe de classe unique.
 */
class DashboardController extends AbstractController
{
    #[Route('/pilotage/tableau-de-bord', name: 'pilotage_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_FORMATEUR')]
    public function dashboard(
        ReservationRepository $reservations,
        ProjetRepository $projets,
        ConsommableRepository $stocks,
        \App\Repository\MachineRepository $machines,
    ): Response {
        $depuis30j = new \DateTimeImmutable('-30 days');

        // L'administrateur voit la vue d'exploitation ; le formateur et le BDE
        // voient la vue de validation, filtrée sur le type qu'ils valident.
        if ($this->isGranted('ROLE_ADMIN')) {
            $enAttente = $projets->enAttenteDeValidation();

            // Créneaux du jour : réservations dont le début tombe aujourd'hui.
            $aujourdhui = new \DateTimeImmutable('today');
            $demain = $aujourdhui->modify('+1 day');
            $creneauxDuJour = $reservations->compterEntre($aujourdhui, $demain);

            // Parc machines : actives sur total, pour l'indicateur « N sur M ».
            $machinesActives = count($machines->findBy(['etat' => \App\Enum\MachineEtat::Active]));
            $machinesTotal = count($machines->findAll());

            return $this->render('admin/dashboard/index.html.twig', [
                'utilisationMachines' => $reservations->utilisationParMachine($depuis30j),
                'projetsEnAttente' => count($enAttente),
                'demandesATraiter' => \array_slice($enAttente, 0, 5),
                'articlesSousSeuil' => $stocks->sousSeuil(),
                'machinesActives' => $machinesActives,
                'machinesTotal' => $machinesTotal,
                'creneauxDuJour' => $creneauxDuJour,
            ]);
        }

        // Vue formateur / BDE : on déduit le type validé du rôle porté.
        /** @var \App\Entity\User $utilisateur */
        $utilisateur = $this->getUser();
        $typeValide = $this->isGranted('ROLE_BDE')
            ? \App\Enum\ProjetType::Personnel
            : \App\Enum\ProjetType::Pedagogique;

        $mesDemandes = $projets->enAttenteParType($typeValide);

        return $this->render('admin/dashboard/validation.html.twig', [
            'typeValideLibelle' => $typeValide->libelle(),
            'mesDemandesEnAttente' => count($mesDemandes),
            'demandesATraiter' => \array_slice($mesDemandes, 0, 6),
            'prochainesSessions' => $reservations->aVenirParValideur($utilisateur),
        ]);
    }

    #[Route('/admin/journal', name: 'admin_journal', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function journal(JournalActiviteRepository $journal): Response
    {
        // BF_8.1 : trace des événements.
        return $this->render('admin/dashboard/journal.html.twig', [
            'entrees' => $journal->recentes(),
        ]);
    }
}
