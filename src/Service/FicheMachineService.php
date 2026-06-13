<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Contenu éditorial des fiches machines (le « pourquoi du comment »).
 *
 * On sépare délibérément la donnée métier (entité Machine : nom, état, durée
 * de créneau) du contenu de présentation (usages, matériaux, atouts). Ce
 * contenu est indexé par type de machine, donc partagé par toutes les machines
 * d'un même type, et facile à enrichir sans toucher au modèle ni à la base.
 *
 * Fil rouge transversal : chaque fiche positionne l'outil pour le prototypage,
 * l'apprentissage et la personnalisation, jamais la production de masse.
 */
final class FicheMachineService
{
    /**
     * @return array<string, array{
     *     accroche: string,
     *     usages: list<string>,
     *     materiaux: list<string>,
     *     atouts: list<string>,
     *     bon_a_savoir: string
     * }>
     */
    private function contenus(): array
    {
        return [
            'impression_3d' => [
                'accroche' => "L'outil polyvalent du FabLab : déposer un fil de plastique fondu couche par couche pour donner forme à une idée en quelques heures.",
                'usages' => [
                    'Prototypes visuels et pièces de validation pour tester une forme avant production',
                    'Gabarits, supports et outillage personnalisé pour vos projets',
                    'Pièces de rechange et boîtiers sur mesure (carénages, encliquetage)',
                ],
                'materiaux' => [
                    'PLA : facile à imprimer, idéal pour débuter et pour les prototypes décoratifs',
                    'PETG : plus robuste, pour les pièces qui demandent de la résistance ou de l\'étanchéité',
                    'TPU : souple, pour les joints, amortisseurs et pièces flexibles',
                ],
                'atouts' => [
                    'Le passage de l\'idée à l\'objet en une séance',
                    'La technologie la plus accessible pour apprendre la fabrication numérique',
                    'Un coût matière faible qui autorise l\'erreur et l\'itération',
                ],
                'bon_a_savoir' => "L'impression à dépôt de filament couvre la grande majorité des projets pédagogiques. Pensez votre pièce pour l'itération : un premier essai imparfait fait partie de l'apprentissage.",
            ],
            'resine' => [
                'accroche' => "La précision avant tout : une résine photosensible durcie par la lumière, pour des détails fins que le filament ne peut pas restituer.",
                'usages' => [
                    'Figurines, miniatures et pièces décoratives très détaillées',
                    'Modèles de bijouterie et petites pièces mécaniques de précision',
                    'Maquettes aux surfaces lisses, sans couches visibles',
                ],
                'materiaux' => [
                    'Résines standard pour la précision et le rendu lisse',
                    'Résines techniques pour des propriétés spécifiques (rigidité, souplesse)',
                ],
                'atouts' => [
                    'Une résolution très supérieure à l\'impression à filament',
                    'Des surfaces parfaitement lisses, couches invisibles à l\'œil',
                    'Le meilleur choix pour les géométries complexes et les petits détails',
                ],
                'bon_a_savoir' => "La résine demande un post-traitement encadré (nettoyage, durcissement UV) et le port de gants. C'est une machine de précision, à réserver aux pièces qui le justifient plutôt qu'aux volumes importants.",
            ],
            'graveuse_laser' => [
                'accroche' => "Un faisceau qui découpe et grave avec une précision au dixième de millimètre, sur une large gamme de matériaux plats.",
                'usages' => [
                    'Découpe de pièces 2D pour maquettes, boîtes et assemblages',
                    'Gravure de logos, motifs et signalétique sur objets',
                    'Personnalisation : porte-clés, sous-verres, plaques, décoration',
                ],
                'materiaux' => [
                    'Bois et contreplaqué pour les pièces structurelles et la décoration',
                    'Acrylique (plexiglas) pour les pièces translucides et la signalétique',
                    'Carton, cuir et papier pour le prototypage rapide et léger',
                ],
                'atouts' => [
                    'Une précision et une netteté de coupe difficiles à égaler à la main',
                    'La répétabilité : dix pièces identiques aussi facilement qu\'une',
                    'Un même outil pour découper ET graver',
                ],
                'bon_a_savoir' => "Seuls les matériaux validés par l'équipe sont autorisés : certains plastiques dégagent des fumées toxiques au laser. En cas de doute, demandez avant de lancer une découpe.",
            ],
            'plotteur' => [
                'accroche' => "La découpe fine de matériaux souples : un cutter piloté qui suit vos tracés vectoriels au plus près.",
                'usages' => [
                    'Autocollants, stickers et étiquettes personnalisés',
                    'Pochoirs et masques de peinture',
                    'Découpe de flex et flock thermocollants pour la personnalisation textile',
                ],
                'materiaux' => [
                    'Vinyle adhésif pour stickers et signalétique',
                    'Flex et flock thermocollants pour le textile (étape avant la presse)',
                    'Papier et matériaux fins et flexibles',
                ],
                'atouts' => [
                    'Des contours nets et réguliers, même sur des formes complexes',
                    'La régularité : tous les visuels d\'une série à l\'identique',
                    'Le compagnon naturel de la presse à flocage pour la chaîne textile',
                ],
                'bon_a_savoir' => "Le plotter prépare le visuel ; il faut ensuite l'écheniller (retirer le surplus à la main) avant transfert. Prévoyez un tracé vectoriel propre, idéalement en une couleur par passe.",
            ],
            'flocage' => [
                'accroche' => "La touche finale de la chaîne textile : chaleur et pression pour fixer durablement un visuel sur un vêtement ou un objet.",
                'usages' => [
                    'Personnalisation de t-shirts, casquettes et tote bags',
                    'Marquage de visuels, logos et noms sur textile',
                    'Petites séries personnalisées pour un événement ou un projet',
                ],
                'materiaux' => [
                    'Flex et flock découpés au plotter (couleurs vives, rendu opaque)',
                    'Textiles coton de préférence pour un meilleur rendu',
                    'Transferts thermiques pour les visuels multicolores',
                ],
                'atouts' => [
                    'Un rendu de qualité, vif et durable, pour un coût abordable',
                    'Une mise en œuvre rapide une fois le visuel découpé',
                    'L\'aboutissement concret d\'un projet de personnalisation',
                ],
                'bon_a_savoir' => "La presse complète le plotter : on découpe d'abord le visuel, on le presse ensuite. Un t-shirt floqué se lave à l'envers pour préserver le marquage.",
            ],
            'iot' => [
                'accroche' => "Le terrain de jeu de l'électronique connectée : cartes, capteurs et actionneurs pour donner vie et intelligence à vos prototypes.",
                'usages' => [
                    'Premiers pas en électronique numérique et programmation',
                    'Preuves de concept communicantes : mesurer, piloter, alerter',
                    'Prototypes domotique et objets connectés (capteurs, LED, relais)',
                ],
                'materiaux' => [
                    'Cartes Arduino et ESP32 (Wi-Fi et Bluetooth intégrés)',
                    'Capteurs analogiques et numériques (température, mouvement, lumière)',
                    'Breadboard, LED, résistances et actionneurs pour le montage sans soudure',
                ],
                'atouts' => [
                    'Une porte d\'entrée idéale vers l\'Internet des objets',
                    'Une immense communauté et des ressources en libre accès',
                    'Du montage sans soudure pour itérer et apprendre sans risque',
                ],
                'bon_a_savoir' => "Commencez par le problème à résoudre, pas par le composant : un bon prototype IoT répond à un besoin concret avant d'empiler les capteurs.",
            ],
        ];
    }

    /**
     * Retourne la fiche éditoriale pour un type de machine, ou null si le type
     * n'est pas documenté (la vue affichera alors juste la description courte).
     *
     * @return array{accroche: string, usages: list<string>, materiaux: list<string>, atouts: list<string>, bon_a_savoir: string}|null
     */
    public function pourType(string $type): ?array
    {
        return $this->contenus()[$type] ?? null;
    }
}
