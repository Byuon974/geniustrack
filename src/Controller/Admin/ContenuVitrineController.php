<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContenuVitrine;
use App\Repository\ContenuVitrineRepository;
use App\Repository\MachineRepository;
use App\Service\PhotoUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Image;
use Symfony\Component\Validator\Constraints\Length;

/**
 * Contenu éditorial de la vitrine (BF_1.2) : l'admin édite les blocs de la
 * page d'accueil. Chaque bloc est TYPÉ (texte ou image), conformément au RETEX
 * CMS : on ne mélange pas texte et binaire dans un même champ. Les blocs texte
 * s'éditent en zone de texte, les blocs image par upload avec aperçu.
 *
 * Pas de page builder ni de versionning : un FabLab de campus ajuste une
 * poignée de textes et d'images, pas un site éditorial complet.
 */
#[Route('/admin/vitrine')]
#[IsGranted('ROLE_ADMIN')]
class ContenuVitrineController extends AbstractController
{
    #[Route('', name: 'admin_vitrine_index', methods: ['GET'])]
    public function index(
        ContenuVitrineRepository $repository,
        MachineRepository $machines,
        \App\Repository\ProjetRepository $projets,
    ): Response {
        return $this->render('admin/vitrine/index.html.twig', [
            'blocs' => $repository->findBy([], ['libelle' => 'ASC']),
            'nbMachines' => count($machines->actives()),
            'nbProjetsGalerie' => count($projets->pourGalerie()),
        ]);
    }

    #[Route('/{id}', name: 'admin_vitrine_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        ContenuVitrine $bloc,
        EntityManagerInterface $em,
        PhotoUploadService $uploads,
        #[Autowire('%app.repertoire_vitrine%')] string $repertoireVitrine,
    ): Response {
        $builder = $this->createFormBuilder($bloc);

        if ($bloc->estImage()) {
            // Bloc image : champ d'upload non mappé (le nom de fichier est géré
            // à la main après upload, comme pour les machines).
            // Consigne affichée : on annonce le format attendu pour que l'admin
            // dépose la bonne image du premier coup (RETEX : guider plutôt que
            // rejeter sans explication). Ciblée sur le hero, qui a un cadre précis.
            $aideImage = 'hero_image' === $bloc->getCle()
                ? 'Format paysage recommandé (environ 16/10), entre 600 et 2400 px de large. JPEG, PNG ou WebP, 4 Mo maximum.'
                : null;
            $builder->add('fichier', FileType::class, [
                'label' => $bloc->getLibelle(),
                'help' => $aideImage,
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Image(
                        maxSize: '5M',
                        // Le hero affiche l'image en cadre 16/10 (cadrage CSS).
                        // On resserre les bornes d'upload autour de ce format réel :
                        // assez grand pour rester net sur écran haute densité, mais
                        // borné pour qu'on dépose une image proche du format attendu
                        // plutôt qu'un fichier hors-cadre (anti-troll). Le min évite
                        // les vignettes illisibles, le max les images démesurées.
                        // maxPixels et detectCorrupted ferment en plus les images
                        // malveillantes (decompression bomb, fichier piégé).
                        minWidth: 600,
                        maxWidth: 2400,
                        maxHeight: 1600,
                        maxPixels: 4000000,
                        detectCorrupted: true,
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'Formats acceptés : JPEG, PNG, WebP.',
                        minWidthMessage: 'Image trop petite ({{ width }}px de large). Minimum : {{ min_width }}px pour rester nette.',
                        maxWidthMessage: 'Image trop large ({{ width }}px). Maximum : {{ max_width }}px.',
                        maxHeightMessage: 'Image trop haute ({{ height }}px). Maximum : {{ max_height }}px.',
                        maxPixelsMessage: 'Image trop lourde à traiter ({{ pixels }} pixels). Maximum : {{ max_pixels }}.',
                        corruptedMessage: 'Ce fichier image est corrompu ou invalide.',
                    ),
                ],
            ]);
        } else {
            // Le texte du hero est une accroche courte et très exposée : on le
            // borne pour qu'un contenu trop long (ou du vandalisme) ne casse pas
            // la mise en page. Défense en profondeur : maxlength à la saisie ET
            // contrainte serveur Length (la saisie seule se contourne).
            $estHero = 'hero_texte' === $bloc->getCle();
            $attrTexte = ['rows' => 5];
            $contraintesTexte = [];
            if ($estHero) {
                $attrTexte['maxlength'] = 180;
                $contraintesTexte[] = new Length(
                    max: 180,
                    maxMessage: 'L\'accroche ne doit pas dépasser {{ limit }} caractères.',
                );
            }
            $builder->add('valeur', TextareaType::class, [
                'label' => $bloc->getLibelle(),
                'attr' => $attrTexte,
                'constraints' => $contraintesTexte,
            ]);
        }

        $form = $builder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($bloc->estImage()) {
                /** @var UploadedFile|null $fichier */
                $fichier = $form->get('fichier')->getData();
                if ($fichier instanceof UploadedFile) {
                    $nom = $uploads->remplacerDans($repertoireVitrine, $fichier, $bloc->getImage());
                    $bloc->setImage($nom);
                }
            }
            $em->flush();
            $this->addFlash('success', 'Contenu mis à jour.');

            return $this->redirectToRoute('admin_vitrine_index');
        }

        return $this->render('admin/vitrine/edit.html.twig', [
            'bloc' => $bloc,
            'form' => $form,
        ]);
    }
}
