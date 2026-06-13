<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Upload de photos en natif (zéro dépendance, choix dirigeant).
 *
 * Gère explicitement ce que VichUploader ferait automatiquement : nommage sûr
 * (slug + uniqid), déplacement vers le répertoire public, et SUPPRESSION de
 * l'ancien fichier au remplacement : pour éviter l'accumulation d'orphelins,
 * le point faible de l'upload manuel.
 */
class PhotoUploadService
{
    public function __construct(
        private readonly string $repertoireMachines,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Stocke le fichier et renvoie son nom. Supprime l'ancien si fourni.
     *
     * @return string le nom de fichier à persister dans l'entité
     */
    public function remplacer(UploadedFile $fichier, ?string $ancien = null): string
    {
        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSur = $this->slugger->slug($nomOriginal)->lower();
        $nomFichier = sprintf('%s-%s.%s', $nomSur, uniqid(), $fichier->guessExtension() ?: 'bin');

        try {
            $fichier->move($this->repertoireMachines, $nomFichier);
        } catch (FileException $e) {
            throw new \RuntimeException('Échec de l\'enregistrement de la photo.', previous: $e);
        }

        // Nettoyage de l'ancien fichier (évite les orphelins).
        if (null !== $ancien) {
            $this->supprimer($ancien);
        }

        return $nomFichier;
    }

    public function supprimer(?string $nomFichier): void
    {
        if (null === $nomFichier) {
            return;
        }
        $chemin = $this->repertoireMachines.'/'.$nomFichier;
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    /**
     * Variante générique : stocke dans un répertoire explicite et nettoie
     * l'ancien fichier au même endroit. Permet de réutiliser la même logique
     * d'upload (nommage sûr, anti-orphelins) hors du contexte machines, par
     * exemple pour les images de la vitrine, sans dupliquer le service.
     */
    public function remplacerDans(string $repertoire, UploadedFile $fichier, ?string $ancien = null): string
    {
        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSur = $this->slugger->slug($nomOriginal)->lower();
        $nomFichier = sprintf('%s-%s.%s', $nomSur, uniqid(), $fichier->guessExtension() ?: 'bin');

        try {
            $fichier->move($repertoire, $nomFichier);
        } catch (FileException $e) {
            throw new \RuntimeException('Échec de l\'enregistrement de l\'image.', previous: $e);
        }

        if (null !== $ancien) {
            $cheminAncien = $repertoire.'/'.$ancien;
            if (is_file($cheminAncien)) {
                @unlink($cheminAncien);
            }
        }

        return $nomFichier;
    }
}
