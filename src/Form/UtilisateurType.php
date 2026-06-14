<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

/**
 * Création / édition d'un compte par l'admin.
 * À la création (mode_creation), le mot de passe est demandé. En édition, on
 * ne touche pas au mot de passe (champ absent).
 */
class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, ['label' => 'Prénom', 'attr' => ['maxlength' => 100], 'constraints' => [new NotBlank(), new Length(min: 1, max: 100)]])
            ->add('nom', TextType::class, ['label' => 'Nom', 'attr' => ['maxlength' => 100], 'constraints' => [new NotBlank(), new Length(min: 1, max: 100)]])
            ->add('email', EmailType::class, [
                'label' => 'Adresse e-mail (@cci.re)',
                'constraints' => [
                    new NotBlank(),
                    // BNF_3.1 : seules les adresses de campus.
                    new Regex(['pattern' => '/@cci\.re$/', 'message' => 'L\'adresse doit se terminer par @cci.re.']),
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => [
                    'Étudiant' => 'ROLE_ETUDIANT',
                    'Formateur' => 'ROLE_FORMATEUR',
                    'BDE' => 'ROLE_BDE',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'help' => 'Un compte peut cumuler plusieurs rôles.',
                'row_attr' => ['class' => 'choix-cases'],
            ])
            ->add('actif', ChoiceType::class, [
                'label' => 'Statut du compte',
                'choices' => ['Actif' => true, 'Désactivé' => false],
                'expanded' => true,
                'row_attr' => ['class' => 'choix-cases'],
            ]);

        if ($options['mode_creation']) {
            $builder->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'mapped' => false,
                // min 8 pour la robustesse ; max 128 pour autoriser les phrases de
                // passe longues tout en évitant les saisies abusives (le hachage
                // d'une entrée démesurée peut peser, NIST SP 800-63B borne ainsi).
                'attr' => ['maxlength' => 128],
                'constraints' => [new NotBlank(), new Length(min: 8, max: 128)],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'mode_creation' => false,
        ]);
        $resolver->setAllowedTypes('mode_creation', 'bool');
    }
}
