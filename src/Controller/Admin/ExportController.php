<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Service\ExportDonneesService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Export des données pour l'analyse temporelle hors application.
 *
 * L'export annuel alimente l'analyse de l'évolution dans le temps en dehors de
 * GeniusLab, sur un tableur. Le tableau de bord et la supervision restent
 * synthétiques, l'analyse approfondie étant déléguée à l'export. Deux formats :
 * CSV (jeu principal, universel) et XLSX (trois jeux en onglets).
 *
 * Accès : réservé à l'administrateur. L'export contient des données nominatives
 * (noms des étudiants), son périmètre est donc plus restreint que la simple
 * consultation du tableau de bord (principe de minimisation, RGPD).
 */
#[Route('/pilotage')]
#[IsGranted('ROLE_ADMIN')]
final class ExportController extends AbstractController
{
    #[Route('/export/reservations/{annee}', name: 'pilotage_export_reservations', requirements: ['annee' => '\d{4}'], methods: ['GET'])]
    public function reservationsCsv(int $annee, ExportDonneesService $export): StreamedResponse
    {
        return $export->exporterReservationsCsv($annee);
    }

    #[Route('/export/supervision/{annee}.xlsx', name: 'pilotage_export_supervision_xlsx', requirements: ['annee' => '\d{4}'], methods: ['GET'])]
    public function supervisionXlsx(int $annee, ExportDonneesService $export): Response
    {
        return $export->exporterSupervisionXlsx($annee);
    }
}
