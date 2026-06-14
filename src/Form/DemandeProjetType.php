<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Machine;
use App\Entity\Projet;
use App\Enum\MachineEtat;
use App\Enum\ProjetType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Demande de projet. Champs COMMUNS à toutes les machines + champs SPÉCIFIQUES
 * ajoutés selon la (les) machine(s) choisie(s), via FormEvents::PRE_SET_DATA
 * (pattern officiel Symfony pour les formulaires dynamiques).
 *
 * On NE fait PAS un FormRenderer générique (cf. audit UI : sur-ingénierie pour
 * 7 machines). Les champs spécifiques sont déclarés explicitement par type.
 */
class DemandeProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // ── Champs communs (doc de préparation : titre, description, type, machines) ──
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du projet',
                'attr' => ['maxlength' => 40, 'placeholder' => 'Ex. : Boîtier capteur température'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['maxlength' => 250, 'rows' => 3],
                'help' => '250 caractères maximum.',
            ])
            ->add('type', EnumType::class, [
                'class' => ProjetType::class,
                'choice_label' => fn (ProjetType $t) => $t->libelle(),
                'label' => 'Type de projet',
                'help' => 'Pédagogique (validé par un formateur) ou personnel (validé par le BDE).',
            ])
            ->add('machines', EntityType::class, [
                'class' => Machine::class,
                'choice_label' => 'nom',
                'multiple' => true,
                'expanded' => true,
                'label' => 'Machine(s) nécessaire(s)',
                'row_attr' => ['class' => 'choix-cases'],
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('m')
                    ->where('m.etat = :active')
                    ->setParameter('active', MachineEtat::Active->value)
                    ->orderBy('m.nom', 'ASC'),
            ])
            // BF_3.7 : import de plans (multiple, non mappé : traité au controller).
            ->add('plansFiles', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label' => 'Plans / fichiers (impression 3D, découpe… : optionnel)',
                'help' => 'Formats : STL, OBJ, PDF, SVG, ZIP. Jusqu\'à 10 fichiers, 25 Mo chacun, 80 Mo au total. Vous pouvez en sélectionner plusieurs à la fois.',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                // Habillé par le composant d'upload (zone drag-drop + liste).
                'attr' => [
                    'data-file-upload-target' => 'input',
                    'data-action' => 'change->file-upload#surChangement',
                ],
                'constraints' => [
                    // Limite le nombre de plans par demande. Un projet de FabLab
                    // assemble quelques pièces ; 10 couvre un assemblage confortable.
                    new \Symfony\Component\Validator\Constraints\Count(
                        max: 10,
                        maxMessage: 'Trop de fichiers ({{ count }}). Maximum : {{ limit }} par demande.',
                    ),
                    // Plafond du poids total cumulé : ni File (par fichier) ni Count
                    // (par nombre) ne le couvrent. 80 Mo cadrent un assemblage de
                    // plusieurs plans sans saturer le stockage.
                    new \Symfony\Component\Validator\Constraints\Callback(
                        callback: static function (?array $fichiers, \Symfony\Component\Validator\Context\ExecutionContextInterface $context): void {
                            if (null === $fichiers) {
                                return;
                            }
                            $total = 0;
                            foreach ($fichiers as $fichier) {
                                if ($fichier instanceof \Symfony\Component\HttpFoundation\File\File) {
                                    $total += $fichier->getSize();
                                }
                            }
                            $maxTotal = 80 * 1024 * 1024;
                            if ($total > $maxTotal) {
                                $context->buildViolation('Le poids total des fichiers dépasse la limite ({{ total }} Mo). Maximum : {{ max }} Mo.')
                                    ->setParameter('{{ total }}', (string) round($total / 1048576))
                                    ->setParameter('{{ max }}', '80')
                                    ->addViolation();
                            }
                        },
                    ),
                    new \Symfony\Component\Validator\Constraints\All([
                        new \Symfony\Component\Validator\Constraints\File(
                            // 25 Mo par fichier : large pour un STL/OBJ très détaillé
                            // (qui dépasse rarement 50 Mo), sans inviter les fichiers
                            // non optimisés. Aligné sur upload_max_filesize côté PHP.
                            maxSize: '25M',
                            mimeTypes: [
                                'application/sla', 'model/stl', 'application/octet-stream',
                                'application/pdf', 'image/svg+xml', 'application/zip',
                                'text/plain', 'application/vnd.ms-pki.stl',
                            ],
                            mimeTypesMessage: 'Formats acceptés : STL, OBJ, PDF, SVG, ZIP…',
                        ),
                        // Les .zip sont acceptés (un étudiant peut grouper ses
                        // fichiers), mais inspectés contre les bombes de
                        // décompression avant tout traitement. Les autres types
                        // passent ce contrôle sans effet.
                        new \App\Validator\ArchiveSaine(),
                    ]),
                ],
            ]);

        // ── Champs spécifiques selon les machines choisies ──
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $projet = $event->getData();
            $form = $event->getForm();

            // À la création (projet vide), aucune machine encore choisie : on
            // ajoute le champ générique « quantité » utile à la plupart des machines.
            $types = [];
            if ($projet instanceof Projet) {
                foreach ($projet->getMachines() as $machine) {
                    $types[$machine->getType()] = true;
                }
            }

            // Quantité : pertinente pour 3D, résine, flocage, plotteur (pas scanner/IoT).
            $typesAvecQuantite = ['impression_3d', 'resine', 'flocage', 'plotteur'];
            if (array_intersect(array_keys($types), $typesAvecQuantite) || empty($types)) {
                $form->add('quantite', IntegerType::class, [
                    'label' => 'Quantité',
                    'required' => false,
                    'mapped' => false, // stocké hors entité Projet (métadonnée de demande)
                    'attr' => ['min' => 1, 'max' => 10, 'inputmode' => 'numeric', 'data-stepper-target' => 'champ'],
                    'constraints' => [
                        new Assert\Positive(message: 'La quantité doit être au moins de 1.'),
                        new Assert\LessThanOrEqual(value: 10, message: 'La quantité ne peut pas dépasser {{ compared_value }}.'),
                    ],
                ]);
            }

            // Choix du support : flocage (t-shirt/casquette/mug) et graveuse (bois/acrylique).
            if (isset($types['flocage'])) {
                $form->add('support_flocage', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                    'label' => 'Support de flocage',
                    'choices' => ['T-shirt' => 'tshirt', 'Casquette' => 'casquette', 'Mug' => 'mug'],
                    'mapped' => false,
                    'required' => false,
                ]);
            }
            if (isset($types['graveuse_laser'])) {
                $form->add('support_gravure', \Symfony\Component\Form\Extension\Core\Type\ChoiceType::class, [
                    'label' => 'Matériau de gravure',
                    'choices' => ['Bois' => 'bois', 'Acrylique' => 'acrylique'],
                    'mapped' => false,
                    'required' => false,
                ]);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Projet::class]);
    }
}
