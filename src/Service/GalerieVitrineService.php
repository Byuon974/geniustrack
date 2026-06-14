<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PlanProjet;
use App\Entity\Projet;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Gère l'image de la carte d'un projet en galerie publique.
 *
 * Deux sources possibles (décision produit) : réutiliser un fichier-image déjà
 * joint au projet (un PlanProjet dont l'extension est une image), ou téléverser
 * une image dédiée. Dans les deux cas, l'image finit dans le répertoire vitrine
 * PUBLIC (les plans, eux, vivent hors public, sous accès contrôlé) : on copie
 * donc le plan choisi vers la vitrine plutôt que d'exposer le dossier privé.
 */
class GalerieVitrineService
{
    /** Extensions considérées comme des images réutilisables en vitrine. */
    private const EXTENSIONS_IMAGE = ['jpg', 'jpeg', 'png', 'webp', 'svg'];

    public function __construct(
        private readonly string $repertoirePlans,
        private readonly string $repertoireVitrine,
        private readonly PhotoUploadService $photos,
        private readonly SluggerInterface $slugger,
    ) {
    }

    /**
     * Les plans du projet qui sont des images (donc utilisables comme visuel de
     * carte). On se fie à l'extension du nom stocké, qui la conserve.
     *
     * @return PlanProjet[]
     */
    public function plansImages(Projet $projet): array
    {
        $images = [];
        foreach ($projet->getPlans() as $plan) {
            if ($this->estImage($plan->getFichier())) {
                $images[] = $plan;
            }
        }

        return $images;
    }

    public function estImage(string $nomFichier): bool
    {
        $ext = strtolower(pathinfo($nomFichier, PATHINFO_EXTENSION));

        return in_array($ext, self::EXTENSIONS_IMAGE, true);
    }

    /**
     * Définit l'image de vitrine à partir d'un plan-image du projet : copie le
     * fichier privé vers le répertoire vitrine public, met à jour l'entité et
     * nettoie l'ancienne image. Le plan d'origine reste intact.
     *
     * @throws \RuntimeException si le plan n'appartient pas au projet, n'est pas
     *                           une image, ou si la copie échoue
     */
    public function definirDepuisPlan(Projet $projet, PlanProjet $plan): void
    {
        if ($plan->getProjet() !== $projet) {
            throw new \RuntimeException('Ce fichier n\'appartient pas au projet.');
        }
        if (!$this->estImage($plan->getFichier())) {
            throw new \RuntimeException('Ce fichier n\'est pas une image.');
        }

        $source = $this->repertoirePlans.'/'.$plan->getFichier();
        if (!is_file($source)) {
            throw new \RuntimeException('Fichier source introuvable.');
        }

        $ext = strtolower(pathinfo($plan->getFichier(), PATHINFO_EXTENSION)) ?: 'bin';
        $base = $this->slugger->slug(pathinfo($plan->getNomOriginal(), PATHINFO_FILENAME))->lower();
        $nomVitrine = sprintf('%s-%s.%s', $base, uniqid(), $ext);

        if (!@copy($source, $this->repertoireVitrine.'/'.$nomVitrine)) {
            throw new \RuntimeException('Échec de la copie de l\'image vers la vitrine.');
        }

        $this->remplacerImage($projet, $nomVitrine);
    }

    /**
     * Définit l'image de vitrine à partir d'un fichier téléversé dédié.
     */
    public function definirDepuisUpload(Projet $projet, UploadedFile $fichier): void
    {
        $nomVitrine = $this->photos->remplacerDans($this->repertoireVitrine, $fichier);
        $this->remplacerImage($projet, $nomVitrine);
    }

    /**
     * Retire l'image de vitrine (retour au placeholder) et supprime le fichier.
     */
    public function retirerImage(Projet $projet): void
    {
        $this->remplacerImage($projet, null);
    }

    /** Met à jour l'entité et supprime l'ancien fichier vitrine s'il existe. */
    private function remplacerImage(Projet $projet, ?string $nouveauNom): void
    {
        $ancien = $projet->getImageVitrine();
        $projet->setImageVitrine($nouveauNom);

        if (null !== $ancien && $ancien !== $nouveauNom) {
            $cheminAncien = $this->repertoireVitrine.'/'.$ancien;
            if (is_file($cheminAncien)) {
                @unlink($cheminAncien);
            }
        }
    }
}
