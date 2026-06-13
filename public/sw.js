/*
 * Service worker de GeniusLab.
 *
 * Objectif : rendre l'application installable (PWA) et offrir un repli hors
 * ligne minimal, sans jamais servir de contenu métier périmé. GeniusLab est
 * une application de gestion : les données (réservations, demandes, stock)
 * doivent toujours être fraîches. La stratégie est donc « réseau d'abord »
 * pour les pages : on tente le réseau, et l'on ne recourt au cache que si le
 * réseau échoue, auquel cas on présente la page hors ligne.
 *
 * Le cache porte un numéro de version dans son nom : changer ce numéro lors
 * d'une mise à jour invalide proprement l'ancien cache (nettoyage à l'activation).
 */

const VERSION = 'v1';
const CACHE = `geniuslab-${VERSION}`;

// Ressources de coque mises en cache à l'installation : juste de quoi afficher
// une page hors ligne propre. On ne précache pas les pages métier.
const PRECACHE = [
    '/offline.html',
    '/favicon.svg',
    '/icon-192.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(PRECACHE))
    );
    // Active immédiatement la nouvelle version sans attendre la fermeture des onglets.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    // Nettoyage : on supprime les caches des versions précédentes.
    event.waitUntil(
        caches.keys().then((cles) =>
            Promise.all(
                cles.filter((cle) => cle !== CACHE).map((cle) => caches.delete(cle))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const requete = event.request;

    // On ne gère que les requêtes GET de navigation ; le reste passe au réseau
    // sans interception (notamment les requêtes POST de formulaire).
    if (requete.method !== 'GET') {
        return;
    }

    // Pour les navigations de page : réseau d'abord, repli hors ligne si échec.
    if (requete.mode === 'navigate') {
        event.respondWith(
            fetch(requete).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Pour les ressources statiques de la coque déjà précachées : on les sert
    // depuis le cache si disponibles, sinon réseau.
    event.respondWith(
        caches.match(requete).then((reponse) => reponse || fetch(requete))
    );
});
