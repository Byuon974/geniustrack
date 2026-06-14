<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Machine;
use App\Enum\MachineEtat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MachineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la machine',
                'attr' => ['maxlength' => 150],
                'constraints' => [new NotBlank(), new Length(min: 2, max: 150)],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => \App\Catalogue\MachineTypes::choix(),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['maxlength' => 255, 'rows' => 3],
                'constraints' => [new Length(max: 255)],
            ])
            ->add('etat', EnumType::class, [
                'class' => MachineEtat::class,
                'choice_label' => fn (MachineEtat $e) => $e->libelle(),
                'label' => 'État',
            ])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo (JPEG/PNG/WebP, max 5 Mo)',
                'mapped' => false,   // géré à la main, pas une propriété de l'entité
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '5M',
                        // Bornes de dimensions et plafond de pixels : une image peut
                        // peser peu et pourtant décoder des dimensions énormes
                        // (decompression bomb / pixel flood) qui épuisent la mémoire
                        // au traitement. maxPixels borne le total décodé ; les bornes
                        // width/height cadrent une vraie photo de machine.
                        maxWidth: 4000,
                        maxHeight: 4000,
                        maxPixels: 8000000,
                        detectCorrupted: true,
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP.',
                        maxPixelsMessage: 'Image trop lourde à traiter ({{ pixels }} pixels). Maximum : {{ max_pixels }}.',
                        corruptedMessage: 'Ce fichier image est corrompu ou invalide.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Machine::class]);
    }
}
