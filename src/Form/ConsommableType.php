<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Consommable;
use App\Repository\ConsommableRepository;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ConsommableType extends AbstractType
{
    // Catégories de stock : les consommables se rangent par espace machine du
    // FabLab, conformément au cahier des charges (inventaire « par groupe » :
    // Impression 3D, Résine, Graveuse laser, Plotteur, Flocage, IoT, Petit
    // électronique). On s'appuie sur la source unique MachineTypes pour que le
    // formulaire, l'affichage et les données parlent la même nomenclature.
    // Les catégories créées à la volée (hors socle) s'ajoutent dynamiquement.

    // Unités : liste fermée pour éviter les variantes de saisie (bobine / bobines
    // / Bobine). Libellé affiché capitalisé, valeur technique en minuscule.
    private const UNITES = [
        'Bobine' => 'bobine',
        'Litre' => 'litre',
        'Pièce' => 'piece',
        'Mètre' => 'metre',
        'Rouleau' => 'rouleau',
        'Kilogramme' => 'kilogramme',
    ];

    public function __construct(private readonly ConsommableRepository $consommables)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de l\'article',
                'constraints' => [new NotBlank()],
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => $this->choixCategories(),
                'constraints' => [new NotBlank()],
            ])
            ->add('unite', ChoiceType::class, [
                'label' => 'Unité',
                'choices' => $this->choixUnites(),
                'constraints' => [new NotBlank()],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité en stock',
                'attr' => ['min' => 0, 'inputmode' => 'numeric'],
                'constraints' => [new PositiveOrZero()],
            ])
            ->add('seuilMinimal', IntegerType::class, [
                'label' => 'Seuil d\'alerte',
                'attr' => ['min' => 0, 'inputmode' => 'numeric'],
                'help' => 'En dessous de ce niveau, l\'article est signalé en stock bas.',
                'constraints' => [new PositiveOrZero()],
            ])
            ->add('consommationMensuelleEstimee', NumberType::class, [
                'label' => 'Consommation estimée par mois',
                'attr' => ['min' => 0, 'step' => '0.5', 'inputmode' => 'decimal'],
                'help' => 'Laisser à 0 si inconnu : aucune prédiction de rupture ne sera faite.',
                'constraints' => [new PositiveOrZero()],
            ])
            ->add('delaiFournisseurJours', IntegerType::class, [
                'label' => 'Délai fournisseur (jours)',
                'attr' => ['min' => 0, 'inputmode' => 'numeric'],
                'help' => 'Du jour de commande à la livraison.',
                'constraints' => [new PositiveOrZero()],
            ]);
    }

    /**
     * Fusionne les catégories de référence et celles déjà présentes en base.
     * Une catégorie saisie auparavant réapparaît ainsi dans la liste, libellée
     * proprement si elle fait partie du socle, sinon affichée telle quelle.
     *
     * @return array<string, string>
     */
    private function choixCategories(): array
    {
        $choix = \App\Catalogue\MachineTypes::choix();
        $valeursConnues = array_values($choix);

        foreach ($this->consommables->categoriesUtilisees() as $categorie) {
            if ('' === $categorie || in_array($categorie, $valeursConnues, true)) {
                continue;
            }
            // Catégorie hors socle : libellé = valeur telle qu'enregistrée.
            $choix[$categorie] = $categorie;
        }

        return $choix;
    }

    /**
     * Fusionne les unités de référence et celles déjà présentes en base. Évite
     * qu'un article portant une ancienne unité libre (hors liste fermée) soit
     * rejeté à l'édition par le ChoiceType.
     *
     * @return array<string, string>
     */
    private function choixUnites(): array
    {
        $choix = self::UNITES;
        $valeursConnues = array_values($choix);

        foreach ($this->consommables->unitesUtilisees() as $unite) {
            if ('' === $unite || in_array($unite, $valeursConnues, true)) {
                continue;
            }
            // Unité hors socle : libellé = valeur telle qu'enregistrée.
            $choix[$unite] = $unite;
        }

        return $choix;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Consommable::class]);
    }
}
