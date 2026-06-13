<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valide qu'une archive zip n'est pas une bombe de décompression.
 *
 * Lit le catalogue de l'archive via ZipArchive (les en-têtes déclarent la taille
 * compressée et décompressée de chaque entrée) SANS extraire le contenu, puis
 * applique les garde-fous de la contrainte. Si l'extension zip n'est pas
 * disponible ou le fichier n'est pas un zip, la validation passe : ce contrôle
 * est une défense spécifique au zip, pas un substitut aux autres validations.
 */
final class ArchiveSaineValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ArchiveSaine) {
            throw new UnexpectedTypeException($constraint, ArchiveSaine::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $chemin = $value instanceof UploadedFile ? $value->getPathname() : (string) $value;
        if (!is_file($chemin)) {
            return;
        }

        // On ne contrôle que les archives zip. Le type réel prime sur l'extension.
        $mime = $value instanceof UploadedFile ? $value->getMimeType() : null;
        $estZip = 'application/zip' === $mime
            || (null === $mime && str_ends_with(strtolower($chemin), '.zip'));
        if (!$estZip) {
            return;
        }

        // Sans l'extension zip, on ne peut pas inspecter : on ne bloque pas ici
        // (les autres contraintes restent actives), le sujet est tracé côté infra.
        if (!class_exists(\ZipArchive::class)) {
            return;
        }

        $zip = new \ZipArchive();
        if (true !== $zip->open($chemin)) {
            $this->context->buildViolation($constraint->messageIllisible)->addViolation();

            return;
        }

        $tailleTotale = 0;
        $entrees = $zip->numFiles;

        if ($entrees > $constraint->entreesMax) {
            $zip->close();
            $this->context->buildViolation($constraint->messageEntrees)
                ->setParameter('{{ entrees }}', (string) $entrees)
                ->setParameter('{{ max }}', (string) $constraint->entreesMax)
                ->addViolation();

            return;
        }

        for ($i = 0; $i < $entrees; ++$i) {
            $stat = $zip->statIndex($i);
            if (false === $stat) {
                continue;
            }

            $compresse = (int) ($stat['comp_size'] ?? 0);
            $decompresse = (int) ($stat['size'] ?? 0);
            $tailleTotale += $decompresse;

            // Ratio par entrée : une entrée qui décompresse bien au-delà de sa
            // taille compressée est le marqueur d'une bombe. On ignore les très
            // petites entrées (le ratio y est trompeur et sans danger).
            if ($compresse > 0 && $decompresse > 65536
                && ($decompresse / $compresse) > $constraint->ratioMax) {
                $zip->close();
                $this->context->buildViolation($constraint->messageRatio)->addViolation();

                return;
            }

            // Taille décompressée cumulée : plafond absolu, quel que soit le ratio.
            if ($tailleTotale > $constraint->tailleDecompresseeMax) {
                $zip->close();
                $this->context->buildViolation($constraint->messageTaille)
                    ->setParameter('{{ taille }}', $this->formaterOctets($tailleTotale))
                    ->setParameter('{{ max }}', $this->formaterOctets($constraint->tailleDecompresseeMax))
                    ->addViolation();

                return;
            }
        }

        $zip->close();
    }

    private function formaterOctets(int $octets): string
    {
        if ($octets >= 1073741824) {
            return round($octets / 1073741824, 1).' Go';
        }
        if ($octets >= 1048576) {
            return round($octets / 1048576, 1).' Mo';
        }

        return round($octets / 1024).' Ko';
    }
}
