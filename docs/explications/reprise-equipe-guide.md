# Guide de reprise

Point d'entrée de la documentation GeniusLab. Cette page n'explique pas tout : elle oriente. Elle dit ce qu'est le projet, comment les décisions y sont prises et tracées, quels sont les choix structurants, et dans quel ordre lire le reste selon ce qu'on vient faire. Les détails vivent dans les documents pointés ; ici on donne la carte.

---

## 1. Le projet en bref

GeniusLab est l'application de gestion du FabLab du Campus CCI Nord (La Réunion) : réservation de machines, cycle de vie des projets étudiants (soumission, validation, réalisation), gestion du stock et des machines, comptes et sanctions, tableau de bord. Application web Symfony rendue côté serveur, sans interface séparée de type SPA.

Le périmètre fonctionnel est cadré par un cahier des charges (31 besoins fonctionnels, BF_1.1 à BF_8.1). La couverture de chacun est tenue à jour dans `../reference/audit-projet.md`.

---

## 2. Architecture décisionnelle : comment les choix sont faits

Le projet suit une discipline simple, héritée d'un pipeline éprouvé : **discuter, décider, documenter, exécuter, auditer**. Le principe central : on ne tranche pas une question structurante « au ressenti » quand une recherche ou le cahier des charges peut la fermer, et chaque choix structurant devient une **décision nommée** (DEC-NNN) avec sa justification et les alternatives écartées.

Cette traçabilité est le cœur de la maintenabilité du projet. Une personne qui reprend ne se demande pas « pourquoi est-ce fait ainsi » : elle lit la décision. Les décisions vivent dans `../reference/journal-decisions.md` (journal consolidé, 91 décisions à ce jour), organisé en familles : stack, métier, design, données et sécurité, méthode et outillage.

Quelques règles de méthode qui se lisent dans le code et la doc :

- La documentation ne ment jamais sur l'état réel du code : une doc qui décrit une stack abandonnée est une dette à corriger en priorité.
- Les choix tranchés s'appuient sur des sources (cahier des charges, RETEX, documentation officielle), pas sur des suppositions.
- Une décision relevant du métier (une nomenclature, une règle de gestion) se tranche avec le commanditaire, pas seule.

---

## 3. Les choix structurants en un coup d'œil

Pour situer rapidement le terrain, les décisions de fond et où les retrouver :

```
Domaine          Choix                                    Décision / doc
───────────────  ───────────────────────────────────────  ────────────────
Framework        Symfony 7.4 LTS, PHP 8.5 (FrankenPHP)     DEC-001
Base de données  PostgreSQL (prod/dev), SQLite (tests)     DEC-002
ORM              Doctrine                                  DEC-003
Front            Twig + Symfony UX (Stimulus/Turbo),       DEC-005
                 rendu serveur, pas de SPA
Architecture     Monolithe modulaire en couches            ARCHITECTURE.md §1-2
                 (contrôleur fin, service métier)
Réservation      Capacité 15, quota, concurrence gérée     RESERVATION.md
                 au niveau service
Sanctions        Modèle ledger (journal d'événements)      DEC-028
Sécurité accès   Voter par ressource + access_control      ARCHITECTURE.md §5,
                 par zone, deny-by-default                 AUDIT-SECURITE.md
Conteneurisation Docker, projet assemblé frère du kit      DEC-008, DEC-047
```

---

## 4. Par où commencer selon ce qu'on vient faire

**Découvrir le projet pour la première fois.** Si vous n'avez jamais lancé GeniusLab, commencez par le tutoriel `../demarrage/premier-tour.md` : il vous fait monter le projet de zéro et le prendre en main en une vingtaine de minutes, sans prérequis.

**Reprendre le développement.** Lire dans l'ordre : ce guide, puis `../guides/reprise-equipe.md` (installer et faire tourner le projet), puis `architecture.md` (les principes de conception DRY et KISS qui gouvernent le projet, puis la structure), puis `patterns-code.md` (les patterns transverses à respecter). Garder `../reference/journal-decisions.md` sous la main comme référence quand on se demande pourquoi un choix a été fait.

**Comprendre une fonctionnalité précise.** Aller au document dédié : `reservation.md` (cœur métier), `stock.md`, `sanctions-notifications.md`, `calendrier-disponibilite.md`, `dashboard-conception.md` (le tableau de bord différencié par rôle et le RETEX qui l'a guidé).

**Travailler l'interface.** `../reference/design-system.md` (tokens, règles), `../reference/composants-ui.md` (bibliothèque de composants), `ui-iteration.md` et `../reference/audit-ui-ux.md` (décisions visuelles et accessibilité).

**Auditer ou évaluer.** `../reference/audit-projet.md` (couverture du cahier des charges, points ouverts), `../reference/audit-securite.md` (sécurité). Ces deux documents sont les vues de synthèse ; ils renvoient au journal pour le détail.

**Décider d'une évolution.** Lire la famille concernée du `../reference/journal-decisions.md` pour ne pas rouvrir un débat déjà tranché, puis ajouter une nouvelle décision (DEC-NNN) qui documente le choix et ce qu'il remplace.

---

## 5. Cartographie de la documentation

La documentation se lit en quatre familles :

**Entrée et reprise** : le tutoriel `../demarrage/premier-tour.md` (premier contact), ce guide, `../guides/reprise-equipe.md` (opérations, dépannage), `../guides/docker-et-tests.md` (socle Docker, tests), `../guides/recette-manuelle.md` (scénarios à dérouler après assemblage pour valider les correctifs en conditions réelles).

**Décisions et architecture** : `../reference/journal-decisions.md` (toutes les décisions), `cadrage-technique.md` (cadrage initial et méthode), `architecture.md` et `architecture-couches.md` (structure), `patterns-code.md` (patterns transverses).

**Domaines fonctionnels** : `reservation.md`, `stock.md`, `sanctions-notifications.md`, `calendrier-disponibilite.md`.

**Interface et qualité** : `../reference/design-system.md`, `../reference/composants-ui.md`, `ui-iteration.md`, `../reference/audit-ui-ux.md`, plus les audits transverses `../reference/audit-projet.md` et `../reference/audit-securite.md`.

---

## 6. Le principe à garder en tête

Tout ce qui précède tient en une phrase : **les décisions sont tracées, la doc reflète le code réel, et on s'appuie sur des sources plutôt que sur des intuitions.** Une personne qui reprend le projet en respectant ce principe le maintiendra dans le même état de cohérence. Une personne qui l'ignore, en ajoutant du code sans décision ni mise à jour de la doc, créera la dette que cette discipline existe pour éviter.
