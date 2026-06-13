<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Consommable;
use App\Form\ConsommableType;
use App\Repository\ConsommableRepository;
use App\Service\MouvementStockService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * CRUD des consommables / stocks (BF_4.1, BF_4.2, BF_4.3, BF_4.4).
 * Même patron que MachineController : controller mince, délégation à
 * l'EntityManager. La prédiction de rupture est portée par l'entité
 * (méthodes joursAvantRupture / niveauUrgence), pas par le controller.
 */
#[Route('/admin/stocks')]
#[IsGranted('ROLE_ADMIN')]
class StockController extends AbstractController
{
    #[Route('', name: 'admin_stock_index', methods: ['GET'])]
    public function index(ConsommableRepository $repository): Response
    {
        // RETEX tableaux d'inventaire : le tri par défaut n'est pas alphabétique
        // mais prédictif. On remonte d'abord ce qui a besoin d'action, c'est à
        // dire le stock le plus bas par rapport à son seuil (ratio croissant).
        $articles = $repository->findAll();
        usort($articles, static function (Consommable $a, Consommable $b): int {
            $ra = $a->getSeuilMinimal() > 0 ? $a->getQuantite() / $a->getSeuilMinimal() : PHP_INT_MAX;
            $rb = $b->getSeuilMinimal() > 0 ? $b->getQuantite() / $b->getSeuilMinimal() : PHP_INT_MAX;

            return $ra <=> $rb;
        });

        // Catégories distinctes pour les filtres rapides (chips), triées.
        $categories = array_values(array_unique(array_map(
            static fn (Consommable $a): string => $a->getCategorie(),
            $articles
        )));
        sort($categories);

        return $this->render('admin/stock/index.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'sousSeuil' => $repository->sousSeuil(),
        ]);
    }

    #[Route('/predictions', name: 'admin_stock_predictions', methods: ['GET'])]
    public function predictions(ConsommableRepository $repository): Response
    {
        // On sépare le signal du bruit : seuls les articles dont la consommation
        // est renseignée produisent une prédiction. Les autres sont regroupés à
        // part, avec une invite à renseigner la consommation (RETEX : montrer
        // l'actionnable, replier le reste).
        $avecPrediction = [];
        $sansConsommation = [];
        foreach ($repository->findAll() as $article) {
            if (null === $article->joursAvantRupture()) {
                $sansConsommation[] = $article;
            } else {
                $avecPrediction[] = $article;
            }
        }

        // Les articles à risque sont triés par urgence (le plus pressant d'abord).
        usort($avecPrediction, static fn (Consommable $a, Consommable $b) => $a->joursAvantRupture() <=> $b->joursAvantRupture());

        // Les articles sans consommation : par nom, pour une liste lisible.
        usort($sansConsommation, static fn (Consommable $a, Consommable $b) => $a->getNom() <=> $b->getNom());

        return $this->render('admin/stock/predictions.html.twig', [
            'avecPrediction' => $avecPrediction,
            'sansConsommation' => $sansConsommation,
        ]);
    }

    #[Route('/nouveau', name: 'admin_stock_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, MouvementStockService $mouvements): Response
    {
        $article = new Consommable();
        $form = $this->createForm(ConsommableType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($article);
            // Mouvement initial : la quantité de départ entre dans l'historique
            // (variation depuis zéro), pour que le premier niveau ait une origine.
            $mouvements->tracerVariation($article, 0);
            $em->flush();
            $this->addFlash('success', 'Article ajouté au stock.');

            return $this->redirectToRoute('admin_stock_index');
        }

        return $this->render('admin/stock/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'admin_stock_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Consommable $article, EntityManagerInterface $em, MouvementStockService $mouvements): Response
    {
        // On capture la quantité avant édition, pour tracer le delta (ledger).
        $quantiteAvant = $article->getQuantite();

        $form = $this->createForm(ConsommableType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Traçage silencieux : un mouvement daté est écrit si la quantité a
            // changé, dans la même transaction que la mise à jour de l'article.
            $mouvements->tracerVariation($article, $quantiteAvant);
            $em->flush();
            $this->addFlash('success', 'Article mis à jour.');

            return $this->redirectToRoute('admin_stock_index');
        }

        return $this->render('admin/stock/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'admin_stock_delete', methods: ['POST'])]
    public function delete(Request $request, Consommable $article, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete'.$article->getId(), $request->request->getString('_token'))) {
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Article supprimé.');
        }

        return $this->redirectToRoute('admin_stock_index');
    }
}
