<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Contrainte : l'archive zip ne doit pas être une « bombe de décompression ».
 *
 * Une zip-bomb est une archive minuscule qui décompresse en une quantité de
 * données démesurée, épuisant mémoire et disque (déni de service). La taille du
 * fichier zip lui-même ne protège pas : 42 Ko peuvent décompresser en pétaoctets.
 *
 * Le contrôle inspecte le catalogue de l'archive AVANT toute extraction (chaque
 * entrée déclare sa taille compressée et décompressée), et rejette selon trois
 * garde-fous tirés de l'état de l'art : un ratio de décompression trop élevé, une
 * taille décompressée totale trop grande, ou un nombre d'entrées excessif.
 *
 * Ne s'applique qu'aux fichiers zip : les autres types passent sans contrôle.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ArchiveSaine extends Constraint
{
    public string $messageRatio = 'Cette archive a un taux de compression anormal et a été refusée par sécurité.';
    public string $messageTaille = 'Le contenu décompressé de cette archive est trop volumineux ({{ taille }}). Maximum : {{ max }}.';
    public string $messageEntrees = 'Cette archive contient trop de fichiers ({{ entrees }}). Maximum : {{ max }}.';
    public string $messageIllisible = 'Cette archive est illisible ou corrompue.';

    public function __construct(
        /** Ratio maximal taille décompressée / taille compressée, par entrée. */
        public int $ratioMax = 100,
        /** Taille décompressée totale maximale, en octets (défaut : 200 Mo). */
        public int $tailleDecompresseeMax = 209715200,
        /** Nombre maximal d'entrées dans l'archive. */
        public int $entreesMax = 1000,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct([], $groups, $payload);
    }
}
