<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Upload des plans de projet (BF_3.7), en natif.
 *
 * Même pattern que PhotoUploadService mais pour des fichiers de plans
 * (impression 3D / découpe). Service distinct car répertoire et validation
 * diffèrent ; on évite une abstraction prématurée pour deux cas simples.
 */
class PlanUploadService
{
    public function __construct(
        private readonly string $repertoirePlans,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Stocke le fichier et renvoie son nom sur disque.
     */
    public function stocker(UploadedFile $fichier): string
    {
        $nomOriginal = pathinfo($fichier->getClientOriginalName(), PATHINFO_FILENAME);
        $nomSur = $this->slugger->slug($nomOriginal)->lower();
        $nomFichier = sprintf('%s-%s.%s', $nomSur, uniqid(), $fichier->guessExtension() ?: 'bin');

        try {
            $fichier->move($this->repertoirePlans, $nomFichier);
        } catch (FileException $e) {
            throw new \RuntimeException('Échec de l\'enregistrement du plan.', previous: $e);
        }

        return $nomFichier;
    }

    public function supprimer(?string $nomFichier): void
    {
        if (null === $nomFichier) {
            return;
        }
        $chemin = $this->repertoirePlans.'/'.$nomFichier;
        if (is_file($chemin)) {
            @unlink($chemin);
        }
    }

    /** Chemin absolu d'un plan stocké (pour le servir en téléchargement contrôlé). */
    public function cheminComplet(string $nomFichier): string
    {
        return $this->repertoirePlans.'/'.$nomFichier;
    }
}
