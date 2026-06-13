<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Pages légales (mentions, données personnelles). Contenu statique servi
 * publiquement : exigence RGPD/conformité pour un service en ligne.
 */
class PageController extends AbstractController
{
    #[Route('/mentions-legales', name: 'page_mentions', methods: ['GET'])]
    public function mentions(): Response
    {
        return $this->render('page/mentions.html.twig');
    }

    #[Route('/donnees-personnelles', name: 'page_donnees', methods: ['GET'])]
    public function donnees(): Response
    {
        return $this->render('page/donnees.html.twig');
    }

    #[Route('/regles-utilisation', name: 'page_regles', methods: ['GET'])]
    public function regles(): Response
    {
        return $this->render('page/regles.html.twig');
    }
}
