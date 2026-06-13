<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ajoute les en-têtes de sécurité HTTP sur chaque réponse.
 *
 * Mesure « peu coûteuse, fort impact » recommandée par l'état de l'art (OWASP,
 * recommandations sécurité web 2026) : ces en-têtes réduisent la surface
 * d'exploitation des failles côté navigateur (clickjacking, MIME sniffing, fuite
 * de référent, XSS). Posés ici plutôt que dans le serveur pour rester portables
 * et testables, quel que soit le runtime (FrankenPHP, PHP-FPM, serveur de test).
 *
 * La politique de sécurité du contenu (CSP) autorise les ressources du même
 * domaine et tolère les styles/scripts inline que génèrent Symfony UX et Turbo,
 * tout en interdisant l'évaluation dynamique (eval) et le cadrage du site par un
 * tiers. À durcir si l'application retire ses derniers inline.
 */
#[AsEventListener(event: KernelEvents::RESPONSE)]
final class EnTetesSecuriteListener
{
    public function __invoke(ResponseEvent $event): void
    {
        // Seule la réponse principale est concernée (pas les sous-requêtes).
        if (!$event->isMainRequest()) {
            return;
        }

        $reponse = $event->getResponse();
        $entetes = $reponse->headers;

        // Empêche l'affichage du site dans une iframe tierce (clickjacking).
        $entetes->set('X-Frame-Options', 'DENY');

        // Empêche le navigateur de deviner un type MIME différent du déclaré.
        $entetes->set('X-Content-Type-Options', 'nosniff');

        // Limite l'information de provenance transmise aux autres sites.
        $entetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Restreint les fonctionnalités sensibles du navigateur par défaut.
        $entetes->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Politique de sécurité du contenu : ressources du même domaine, images
        // et données en data: (icônes, aperçus), pas d'eval, pas de cadrage tiers.
        //
        // Note AssetMapper : en dev comme en prod, AssetMapper charge les fichiers
        // CSS importés via des modules à URL « data: » dans l'importmap (imports
        // factices documentés par Symfony). Sans « data: » dans script-src, le
        // navigateur bloque ces modules et TOUT le JavaScript de la page tombe
        // (menus, créneaux, etc.). On autorise donc data: pour les scripts : une
        // URL data: de script ne permet pas de charger de code tiers distant, le
        // risque reste donc faible. Réf. doc Symfony AssetMapper, section CSP.
        if (!$entetes->has('Content-Security-Policy')) {
            $entetes->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "img-src 'self' data:",
                "style-src 'self' 'unsafe-inline'",
                "script-src 'self' 'unsafe-inline' data:",
                "font-src 'self'",
                "connect-src 'self'",
                "frame-ancestors 'none'",
                "base-uri 'self'",
                "form-action 'self'",
            ]));
        }

        // HSTS : force HTTPS pour les visites futures, uniquement si déjà en HTTPS
        // (poser cet en-tête sur du HTTP n'a pas de sens et peut gêner le dev local).
        if ($event->getRequest()->isSecure()) {
            $entetes->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
