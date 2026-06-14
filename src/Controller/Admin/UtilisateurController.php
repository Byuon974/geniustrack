<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UtilisateurType;
use App\Repository\UserRepository;
use App\Service\ImportUtilisateurService;
use App\Service\JournalService;
use App\Service\SanctionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Gestion des utilisateurs par l'admin (BF_6.x).
 * Création de comptes, attribution des rôles, activation/désactivation,
 * et levée de sanctions. Complète la commande console (amorçage) par une
 * interface graphique.
 */
#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN')]
class UtilisateurController extends AbstractController
{
    #[Route('', name: 'admin_utilisateur_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $repository): Response
    {
        $parPage = 20;
        $page = max(1, $request->query->getInt('page', 1));
        $recherche = trim($request->query->getString('q'));
        $role = $request->query->getString('role');
        $tri = $request->query->getString('tri', 'nom');
        $sens = $request->query->getString('sens', 'asc');

        $resultat = $repository->paginer($page, $parPage, $recherche, $role, $tri, $sens);
        $total = $resultat['total'];
        $nbPages = max(1, (int) ceil($total / $parPage));

        return $this->render('admin/utilisateur/index.html.twig', [
            'utilisateurs' => $resultat['resultats'],
            'page' => $page,
            'nbPages' => $nbPages,
            'total' => $total,
            'recherche' => $recherche,
            'roleFiltre' => $role,
            'tri' => $tri,
            'sens' => $sens,
        ]);
    }

    #[Route('/nouveau', name: 'admin_utilisateur_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        JournalService $journal,
    ): Response {
        $user = new User();
        $form = $this->createForm(UtilisateurType::class, $user, ['mode_creation' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $motDePasse = $form->get('motDePasse')->getData();
            $user->setPassword($hasher->hashPassword($user, $motDePasse));
            $em->persist($user);
            $em->flush();
            $journal->tracer($this->getUser(), 'Compte créé', $user->getNomComplet());
            $this->addFlash('success', 'Compte créé.');

            return $this->redirectToRoute('admin_utilisateur_index');
        }

        return $this->render('admin/utilisateur/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'admin_utilisateur_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        User $user,
        \App\Repository\ProjetRepository $projets,
        \App\Repository\JournalActiviteRepository $journal,
    ): Response {
        return $this->render('admin/utilisateur/show.html.twig', [
            'utilisateur' => $user,
            'projets' => $projets->parEtudiant($user),
            'historique' => $journal->parCible($user->getNomComplet()),
        ]);
    }

    #[Route('/{id}/modifier', name: 'admin_utilisateur_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        User $user,
        EntityManagerInterface $em,
        JournalService $journal,
        UserRepository $repository,
    ): Response {
        $etaitActif = $user->estActif();
        $form = $this->createForm(UtilisateurType::class, $user, ['mode_creation' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Garde-fou anti-verrouillage (pattern de référence) : un admin ne
            // peut ni se désactiver lui-même, ni désactiver le dernier admin
            // actif. Sinon plus personne ne peut administrer la plateforme.
            $estAdmin = \in_array('ROLE_ADMIN', $user->getRoles(), true);
            $onLeDesactive = $etaitActif && !$user->estActif();

            if ($onLeDesactive && $estAdmin) {
                $cestMoi = $this->getUser() === $user;
                $dernierAdmin = $repository->compterAdminsActifs() <= 1;

                if ($cestMoi || $dernierAdmin) {
                    $user->setActif(true); // on annule la désactivation
                    $this->addFlash('error', $cestMoi
                        ? "Vous ne pouvez pas désactiver votre propre compte administrateur."
                        : "Impossible de désactiver le dernier administrateur actif.");

                    return $this->redirectToRoute('admin_utilisateur_edit', ['id' => $user->getId()]);
                }
            }

            $em->flush();

            if ($etaitActif !== $user->estActif()) {
                $journal->tracer(
                    $this->getUser(),
                    $user->estActif() ? 'Compte réactivé' : 'Compte désactivé',
                    $user->getNomComplet(),
                );
            } else {
                $journal->tracer($this->getUser(), 'Compte modifié', $user->getNomComplet());
            }

            $this->addFlash('success', 'Compte mis à jour.');

            return $this->redirectToRoute('admin_utilisateur_show', ['id' => $user->getId()]);
        }

        return $this->render('admin/utilisateur/edit.html.twig', [
            'utilisateur' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/lever-sanction', name: 'admin_utilisateur_lever_sanction', methods: ['POST'])]
    public function leverSanction(
        Request $request,
        User $user,
        SanctionService $sanctionService,
        JournalService $journal,
    ): Response {
        if ($this->isCsrfTokenValid('lever'.$user->getId(), $request->request->getString('_token'))) {
            $sanctionService->leverSanction($user);
            $journal->tracer($this->getUser(), 'Sanction levée', $user->getNomComplet());
            // Lever une sanction ne réactive pas le compte : si l'étudiant est
            // toujours désactivé, on le rappelle pour éviter toute ambiguïté.
            if (!$user->estActif()) {
                $this->addFlash('success', 'Sanction levée. Le compte reste désactivé : réactivez-le explicitement si nécessaire.');
            } else {
                $this->addFlash('success', 'Sanction levée.');
            }
        }

        return $this->redirectToRoute('admin_utilisateur_edit', ['id' => $user->getId()]);
    }

    /**
     * Import CSV/XLSX en deux temps : à l'upload, on AFFICHE un aperçu validé
     * ligne par ligne sans rien écrire. L'écriture n'a lieu qu'à la
     * confirmation (route import_confirmer), sur le fichier conservé en zone
     * temporaire.
     */
    #[Route('/import', name: 'admin_utilisateur_import', methods: ['GET', 'POST'])]
    public function import(
        Request $request,
        ImportUtilisateurService $import,
        #[Autowire('%kernel.project_dir%/var/imports')] string $repertoireImports,
    ): Response {
        $apercu = null;
        $jeton = null;

        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $fichier */
            $fichier = $request->files->get('fichier');
            if (!$fichier instanceof UploadedFile) {
                $this->addFlash('error', 'Aucun fichier reçu.');

                return $this->redirectToRoute('admin_utilisateur_import');
            }

            $extension = strtolower($fichier->getClientOriginalExtension());
            if (!in_array($extension, ['csv', 'xlsx', 'xls'], true)) {
                $this->addFlash('error', 'Format non pris en charge. Utilisez un fichier CSV ou XLSX.');

                return $this->redirectToRoute('admin_utilisateur_import');
            }

            // On conserve le fichier sous un nom opaque le temps de la confirmation.
            if (!is_dir($repertoireImports)) {
                @mkdir($repertoireImports, 0775, true);
            }
            $jeton = bin2hex(random_bytes(8)).'.'.$extension;
            $fichier->move($repertoireImports, $jeton);

            $apercu = $import->analyser($repertoireImports.'/'.$jeton);
        }

        return $this->render('admin/utilisateur/import.html.twig', [
            'apercu' => $apercu,
            'jeton' => $jeton,
        ]);
    }

    #[Route('/import/confirmer', name: 'admin_utilisateur_import_confirmer', methods: ['POST'])]
    public function importConfirmer(
        Request $request,
        ImportUtilisateurService $import,
        #[Autowire('%kernel.project_dir%/var/imports')] string $repertoireImports,
    ): Response {
        $jeton = $request->request->getString('jeton');
        // Jeton opaque attendu : nom de fichier sans séparateur de chemin.
        if ('' === $jeton || str_contains($jeton, '/') || str_contains($jeton, '\\')) {
            $this->addFlash('error', 'Import expiré ou invalide. Recommencez.');

            return $this->redirectToRoute('admin_utilisateur_import');
        }
        if (!$this->isCsrfTokenValid('import', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré. Merci de recommencer.');

            return $this->redirectToRoute('admin_utilisateur_import');
        }

        $chemin = $repertoireImports.'/'.$jeton;
        if (!is_file($chemin)) {
            $this->addFlash('error', 'Fichier introuvable. Recommencez l\'import.');

            return $this->redirectToRoute('admin_utilisateur_import');
        }

        $crees = $import->importer($chemin);
        @unlink($chemin);

        $this->addFlash('success', sprintf('%d compte(s) importé(s).', $crees));

        return $this->redirectToRoute('admin_utilisateur_index');
    }

    /**
     * Actions groupées sur une sélection d'utilisateurs (activer/désactiver).
     * Reçoit une liste d'ids (champ caché alimenté côté client) et l'action.
     */
    #[Route('/actions-groupees', name: 'admin_utilisateur_batch', methods: ['POST'])]
    public function batch(
        Request $request,
        UserRepository $repository,
        EntityManagerInterface $em,
        JournalService $journal,
    ): Response {
        if (!$this->isCsrfTokenValid('batch', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Votre session a expiré. Merci de recommencer.');

            return $this->redirectToRoute('admin_utilisateur_index');
        }

        $action = $request->request->getString('action_groupee');
        $idsBruts = $request->request->getString('ids');
        $ids = array_filter(array_map('intval', explode(',', $idsBruts)));

        if ([] === $ids) {
            $this->addFlash('error', 'Aucun utilisateur sélectionné.');

            return $this->redirectToRoute('admin_utilisateur_index');
        }

        $actif = match ($action) {
            'activer' => true,
            'desactiver' => false,
            default => null,
        };
        if (null === $actif) {
            $this->addFlash('error', 'Action non reconnue, aucune modification effectuée.');

            return $this->redirectToRoute('admin_utilisateur_index');
        }

        $touches = 0;
        $adminsIgnores = 0;
        foreach ($repository->findBy(['id' => $ids]) as $user) {
            // Garde-fou : un compte admin ne se désactive jamais (sinon risque
            // de verrouiller l'administration de la plateforme).
            if (false === $actif && \in_array('ROLE_ADMIN', $user->getRoles(), true)) {
                ++$adminsIgnores;
                continue;
            }
            if ($user->estActif() !== $actif) {
                $user->setActif($actif);
                $journal->tracer(
                    $this->getUser(),
                    $actif ? 'Compte réactivé' : 'Compte désactivé',
                    $user->getNomComplet(),
                );
                ++$touches;
            }
        }
        $em->flush();

        if ($adminsIgnores > 0) {
            $this->addFlash('error', sprintf('%d compte(s) administrateur ignoré(s) : un admin ne peut pas être désactivé.', $adminsIgnores));
        }

        $this->addFlash('success', sprintf(
            '%d compte(s) %s.',
            $touches,
            $actif ? 'activé(s)' : 'désactivé(s)'
        ));

        return $this->redirectToRoute('admin_utilisateur_index');
    }
}
