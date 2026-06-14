<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\MouvementStockRepository;
use App\Repository\SessionReservationRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export des données pour l'analyse temporelle hors application.
 *
 * Le tableau de bord et la supervision restent synthétiques ; l'analyse
 * approfondie de l'évolution dans le temps se fait hors de l'application, sur
 * les données exportées. Trois jeux sont produits : réservations, taux
 * d'utilisation des machines, mouvements de consommables.
 *
 * Deux formats, selon l'usage (RETEX) :
 * - CSV : données brutes, format universel lisible par tout tableur. Mono-table
 *   par nature, il porte le jeu principal (réservations). Réponse en flux
 *   (StreamedResponse) et marque d'ordre d'octets UTF-8 pour les accents.
 * - XLSX : un classeur à trois onglets (un par jeu), pour conserver la mise en
 *   forme et regrouper les trois axes dans un seul fichier. Produit via
 *   PhpSpreadsheet, déjà utilisé par l'import.
 */
final class ExportDonneesService
{
    public function __construct(
        private readonly SessionReservationRepository $reservations,
        private readonly MouvementStockRepository $mouvements,
        private readonly SupervisionService $supervision,
    ) {
    }

    /**
     * Export CSV du jeu principal (réservations) d'une année. Format universel,
     * mono-table : pour les trois jeux réunis, voir l'export XLSX.
     */
    public function exporterReservationsCsv(int $annee): StreamedResponse
    {
        [$debut, $fin] = $this->bornesAnnee($annee);
        $jeu = $this->jeuReservations($debut, $fin);

        $reponse = new StreamedResponse(function () use ($jeu): void {
            $sortie = fopen('php://output', 'wb');

            // BOM UTF-8 : assure la lecture correcte des accents dans un tableur.
            fwrite($sortie, "\xEF\xBB\xBF");

            fputcsv($sortie, $jeu['entetes'], ';');
            foreach ($jeu['lignes'] as $ligne) {
                fputcsv($sortie, $ligne, ';');
            }

            fclose($sortie);
        });

        $nom = sprintf('geniuslab-reservations-%d.csv', $annee);
        $reponse->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $reponse->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $nom));

        return $reponse;
    }

    /**
     * Export XLSX à trois onglets (réservations, taux machines, mouvements) pour
     * une année. Regroupe les trois axes de supervision dans un seul classeur.
     */
    public function exporterSupervisionXlsx(int $annee): Response
    {
        [$debut, $fin] = $this->bornesAnnee($annee);

        $classeur = new Spreadsheet();
        $jeux = [
            'Réservations' => $this->jeuReservations($debut, $fin),
            'Taux machines' => $this->jeuTauxMachines($debut, $fin),
            'Mouvements stock' => $this->jeuMouvements($debut, $fin),
        ];

        $premier = true;
        foreach ($jeux as $titre => $jeu) {
            $feuille = $premier ? $classeur->getActiveSheet() : $classeur->createSheet();
            $feuille->setTitle($titre);
            $premier = false;

            $feuille->fromArray($jeu['entetes'], null, 'A1');
            if ($jeu['lignes'] !== []) {
                $feuille->fromArray($jeu['lignes'], null, 'A2');
            }

            $this->mettreEnFormeFeuille($feuille, \count($jeu['entetes']), \count($jeu['lignes']));
        }

        $contenu = $this->rendreXlsx($classeur);

        $reponse = new Response($contenu);
        $nom = sprintf('geniuslab-supervision-%d.xlsx', $annee);
        $reponse->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $reponse->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $nom));

        return $reponse;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable}
     */
    private function bornesAnnee(int $annee): array
    {
        return [
            new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $annee)),
            new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $annee + 1)),
        ];
    }

    /**
     * Jeu « réservations » : en-têtes et lignes, en vocabulaire métier.
     *
     * @return array{entetes: list<string>, lignes: list<list<string|int>>}
     */
    private function jeuReservations(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $entetes = ['Date de début', 'Date de fin', 'Durée (min)', 'Machine', 'Projet', 'Type de projet', 'Étudiant', 'Personnes', 'Statut'];
        $lignes = [];

        foreach ($this->reservations->entrePeriode($debut, $fin) as $session) {
            // Une ligne par machine occupée : l'analyse par machine reste
            // possible, et les données communes (créneau, effectif, statut)
            // proviennent de la session.
            foreach ($session->getOccupations() as $occupation) {
                $lignes[] = [
                    $session->getDateDebut()->format('Y-m-d H:i'),
                    $session->getDateFin()->format('Y-m-d H:i'),
                    $session->getDureeMinutes(),
                    $occupation->getMachine()->getNom(),
                    $session->getProjet()->getTitre(),
                    $session->getProjet()->getType()->libelle(),
                    $session->getProjet()->getEtudiant()->getNomComplet(),
                    $session->getNbPersonnes(),
                    $session->getStatut()->libelle(),
                ];
            }
        }

        return ['entetes' => $entetes, 'lignes' => $lignes];
    }

    /**
     * Jeu « taux d'utilisation des machines » sur la période.
     *
     * @return array{entetes: list<string>, lignes: list<list<string|int>>}
     */
    private function jeuTauxMachines(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $entetes = ['Machine', 'Minutes réservées', 'Capacité (min)', 'Taux (%)', 'Niveau'];
        $lignes = [];

        foreach ($this->supervision->tauxUtilisationMachines($debut, $fin) as $m) {
            $lignes[] = [
                $m['nom'],
                $m['minutes'],
                $m['capacite'],
                $m['taux'],
                $m['niveau'],
            ];
        }

        return ['entetes' => $entetes, 'lignes' => $lignes];
    }

    /**
     * Jeu « mouvements de stock » sur la période.
     *
     * @return array{entetes: list<string>, lignes: list<list<string|int>>}
     */
    private function jeuMouvements(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        $entetes = ['Date', 'Article', 'Motif', 'Variation', 'Quantité après'];
        $lignes = [];

        foreach ($this->mouvements->entrePeriode($debut, $fin) as $mv) {
            $lignes[] = [
                $mv->getEffectueLe()->format('Y-m-d H:i'),
                $mv->getConsommable()->getNom(),
                $mv->getMotif()->libelle(),
                $mv->getVariation(),
                $mv->getQuantiteApres(),
            ];
        }

        return ['entetes' => $entetes, 'lignes' => $lignes];
    }

    /**
     * Sérialise le classeur en chaîne binaire XLSX (via un flux mémoire).
     */
    /**
     * Applique une mise en forme professionnelle à une feuille (RETEX export
     * tableur) : en-tête en gras sur fond coloré, largeurs de colonnes ajustées
     * pour éviter la troncature, filtre automatique, et gel de la ligne d'en-tête
     * pour qu'elle reste visible au défilement.
     */
    private function mettreEnFormeFeuille(Worksheet $feuille, int $nbColonnes, int $nbLignes): void
    {
        if ($nbColonnes < 1) {
            return;
        }

        $derniereColonne = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($nbColonnes);
        $plageEntete = sprintf('A1:%s1', $derniereColonne);

        // En-tête : texte blanc gras sur fond bleu (couleur blueprint du projet).
        $feuille->getStyle($plageEntete)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $feuille->getStyle($plageEntete)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1D4E6F');
        $feuille->getStyle($plageEntete)->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $feuille->getRowDimension(1)->setRowHeight(22);

        // Largeurs ajustées au contenu (auto-fit), pour éviter la troncature.
        for ($i = 1; $i <= $nbColonnes; ++$i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $feuille->getColumnDimension($col)->setAutoSize(true);
        }

        // Filtre automatique sur toute la plage (en-tête + données) et gel de la
        // ligne d'en-tête : navigation et tri facilités dans un tableur.
        $derniereLigne = $nbLignes + 1;
        $feuille->setAutoFilter(sprintf('A1:%s%d', $derniereColonne, $derniereLigne));
        $feuille->freezePane('A2');
    }

    private function rendreXlsx(Spreadsheet $classeur): string
    {
        $writer = new Xlsx($classeur);
        $flux = fopen('php://temp', 'r+b');
        $writer->save($flux);
        rewind($flux);
        $contenu = stream_get_contents($flux);
        fclose($flux);

        return $contenu;
    }
}
