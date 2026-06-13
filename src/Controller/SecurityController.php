<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Authentification (BNF_3.1). Controller mince : le form_login natif de Symfony
 * gère la vérification des identifiants ; ce controller ne fait qu'afficher le
 * formulaire et exposer l'éventuelle erreur. Fournit la route app_login attendue
 * par la Vitrine et le contrôle d'accès.
 */
class SecurityController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si déjà connecté, inutile de réafficher le formulaire.
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            // Dernier email saisi (pré-remplissage) et erreur éventuelle.
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/deconnexion', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        // Interceptée par le firewall (clé logout). Ne s'exécute jamais.
        throw new \LogicException('Cette méthode est interceptée par la clé logout du firewall.');
    }
}
