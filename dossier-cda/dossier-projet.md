# Dossier de projet

## Titre professionnel Concepteur Développeur d'Applications (RNCP37873)

**Projet : GeniusLab, plateforme de gestion du FabLab du Campus CCI Nord**

---

> Note de rédaction (à retirer avant remise) : ce document suit le plan type du dossier projet du référentiel CDA (RNCP37873). Sa longueur cible est de 15 à 30 pages hors page de garde, sommaire et annexes (annexes : 20 pages au maximum). Les attendus exacts (nombre de pages, présence d'un résumé en anglais, gabarit imposé) peuvent varier selon le centre de formation : vérifier les consignes de votre session avant remise. Les marqueurs `[À COMPLÉTER]` signalent les informations personnelles ou les captures d'écran que vous devez fournir.

---

**Candidat :** [votre nom et prénom]

**Session :** 2025-2027

**Centre de formation :** [votre centre de formation]

**Projet réalisé en équipe :** trois alternants

---

## Sommaire

1. Liste des compétences mises en œuvre
2. Présentation du contexte et expression des besoins
3. Spécifications fonctionnelles
4. Spécifications techniques
5. Conception de la base de données
6. Réalisations (extraits de code significatifs)
7. Jeu d'essai de la fonctionnalité la plus représentative
8. Sécurité : tests et veille
9. Bilan et conclusion
10. Annexes

---

## 1. Liste des compétences mises en œuvre

Le projet GeniusLab couvre les compétences des trois activités types du référentiel CDA. Le tableau ci-dessous fait correspondre chaque compétence à l'endroit du dossier où sa mise en œuvre est démontrée.

### Activité type 1 : concevoir et développer des composants d'interface utilisateur en intégrant les recommandations de sécurité

| Compétence | Où elle est démontrée dans le projet |
|---|---|
| Maquetter un logiciel | Section 3 (spécifications fonctionnelles, maquettes), méthode de maquettage avant tout changement visuel |
| Développer une interface utilisateur de type desktop | Interfaces d'administration (machines, stock, utilisateurs, vitrine) en Twig + Symfony UX |
| Développer des composants d'accès aux données | Couche repository (requêtes de réservation, disponibilité, stock) |
| Développer la partie front-end d'une interface utilisateur web | Vitrine publique, espace étudiant, page de réservation (sélecteur de créneaux Stimulus + fragments AJAX) |
| Développer la partie back-end d'une interface utilisateur web | Contrôleurs Symfony, services métier |

### Activité type 2 : concevoir et développer la persistance des données en intégrant les recommandations de sécurité

| Compétence | Où elle est démontrée dans le projet |
|---|---|
| Concevoir une base de données | Section 5 (modèle de données, entités, relations) |
| Mettre en place une base de données | PostgreSQL 16, schéma dérivé des entités Doctrine |
| Développer des composants dans le langage d'une base de données | Requêtes DQL, contraintes d'intégrité, verrou pessimiste |

### Activité type 3 : concevoir et développer un logiciel multicouche répartie en intégrant les recommandations de sécurité

| Compétence | Où elle est démontrée dans le projet |
|---|---|
| Collaborer à la gestion d'un projet informatique et à l'organisation de l'environnement de développement | Pipeline de développement, versioning Git, méthode de décisions tracées |
| Concevoir un logiciel | Section 4 (architecture multicouche), section 5 (données) |
| Développer des composants métier | Services métier (réservation, sanctions, stock, notifications) |
| Construire un logiciel organisé en couches | Section 4 (architecture en couches : présentation, métier, accès aux données, domaine) |
| Développer une application mobile | Web responsive (le logiciel est conçu pour le mobile, flux conducteur adapté) ; l'orientation retenue pour le mobile est la PWA plutôt qu'une application de store (voir section 4 et perspectives) |
| Préparer et exécuter les plans de tests d'un logiciel | Section 7 (jeu d'essai), guide de recette manuelle |
| Préparer et exécuter le déploiement d'un logiciel | Section 4 (infrastructure Docker), guide de mise en production |

> La sécurité est transverse aux trois activités. Elle est traitée au fil des sections (validation des entrées, contrôle d'accès, persistance sécurisée) et synthétisée en section 8.

---

## 2. Présentation du contexte et expression des besoins

### 2.1 Le contexte

GeniusLab est le FabLab du Campus CCI Nord, rattaché à l'École du Numérique (EDN) à La Réunion, et c'est aussi le nom du logiciel développé pour le gérer, qui reprend celui du lieu. Un FabLab est un atelier de fabrication numérique mettant à disposition des machines (imprimantes 3D, découpe et gravure laser, plotteurs, postes de flocage, matériel IoT) que les étudiants réservent pour mener leurs projets.

Le projet a été mené comme projet de centre de formation (fil rouge), sur la base d'une commande des responsables du laboratoire. Les commanditaires sont Alexis Blard et Fabien Gayout, gérants du GeniusLab. Leur attente était claire : disposer d'un outil qui aide réellement à superviser l'activité du laboratoire tout en demandant le moins d'entretien possible, plutôt qu'une usine à gaz coûteuse à maintenir. Cette double exigence, utilité réelle pour le pilotage et faible coût d'entretien, a orienté l'ensemble des choix de conception. L'équipe projet était composée de trois alternants.

### 2.2 Le problème à résoudre

Avant GeniusLab, la gestion du FabLab ne reposait sur aucune méthodologie formalisée : le suivi des demandes et de l'activité tenait dans un tableur. Cette organisation posait plusieurs difficultés : pas de visibilité partagée sur l'occupation des machines, pas de processus formalisé de validation des projets, pas de suivi des stocks de consommables, et aucune traçabilité des actions.

Le besoin était donc de disposer d'un logiciel unique qui : présente le FabLab au public, permet aux étudiants de soumettre des projets et de réserver des créneaux machine, donne aux encadrants un circuit de validation, et offre à l'administration un pilotage du parc, des stocks et des accès.

À cette expression fonctionnelle s'ajoutait une exigence des gérants, déterminante pour la conception : l'outil devait réellement servir la supervision du laboratoire tout en réclamant le moins d'entretien possible. Cette contrainte a été traitée comme un critère de premier plan. Elle explique plusieurs partis pris : le traçage automatique et silencieux des mouvements de stock (l'historique se constitue sans saisie supplémentaire), l'ajout de machines par simple gestion sans toucher au code, le refus délibéré de fonctionnalités séduisantes mais coûteuses à maintenir, et une supervision qui dérive ses indicateurs de données déjà présentes plutôt que d'imposer un travail de tenue à jour. Cette exigence rejoignait une réalité du projet : conduit par des alternants, dont la présence est temporaire, il devait pouvoir vivre au-delà de ses concepteurs, ce qui place le faible entretien et la transmissibilité au cœur de la démarche (voir la conclusion, section 9.4).

### 2.3 L'expression des besoins

Le référentiel fonctionnel compte une trentaine de besoins répartis en huit familles. Cette structuration sert de fil directeur à toute la conception, et de référence objective pour vérifier, à la livraison, l'écart entre l'attendu et le réalisé.

| Famille | Codes | Cœur du besoin |
|---|---|---|
| Présentation | BF_1.x | Vitrine du FabLab, édition par l'administration |
| Présentation des projets | BF_2.x | Galerie des projets terminés partagés |
| Gestion des réservations | BF_3.x | Cœur métier : demande, validation, créneaux, capacité, annulation, report |
| Gestion des stocks | BF_4.x | Ajout, quantité, prédiction de rupture, alerte de seuil |
| Gestion des machines | BF_5.x | Ajout, états, consultation |
| Gestion des utilisateurs | BF_6.x | Connexion, sanctions, vues par rôle |
| Consultation de tableau de bord | BF_7.x | Tableau de bord, prévisions |
| Journal d'activité | BF_8.x | Trace des actions d'administration |

Aux besoins fonctionnels s'ajoutent des besoins non fonctionnels qui ont orienté les choix techniques : la performance (connexions simultanées, temps de chargement des pages), la disponibilité (tolérance à la panne d'un service), et la sécurité (authentification, contrôle d'accès, conformité RGPD et recommandations ANSSI). Le détail de ces exigences justifie les décisions de pile technique présentées en section 4.

> Le cahier des charges complet et la matrice des exigences figurent en annexe A.

---

## 3. Spécifications fonctionnelles

### 3.1 Les acteurs et leurs rôles

Le logiciel distingue quatre profils, organisés en hiérarchie de droits :

- **L'étudiant** soumet des projets, réserve des créneaux machine, consulte ses réservations et son tableau de bord.
- **Le formateur** valide les projets pédagogiques et examine les demandes associées.
- **Le BDE** (bureau des étudiants) valide les projets personnels.
- **L'administrateur** pilote le parc machine, les stocks, les comptes utilisateurs, la vitrine publique et consulte le journal d'activité. Il hérite des droits de formateur et de BDE.

Cette hiérarchie applique le principe de moindre privilège : chaque rôle ne dispose que des droits nécessaires à ses missions.

### 3.2 Les parcours fonctionnels principaux

**Soumettre et faire valider un projet.** L'étudiant crée un projet, y joint ses plans (fichiers 3D, PDF), et le soumet. La soumission le fait passer de l'état brouillon à l'état « en attente », ce qui notifie le valideur compétent (formateur pour un projet pédagogique, BDE pour un projet personnel). Le valideur examine la demande sur une page de détail qui présente la description, les machines demandées et les plans téléchargeables, puis valide ou refuse avec un motif communiqué à l'étudiant.

**Réserver un créneau machine.** Une fois son projet validé, l'étudiant ouvre la page de réservation. Il choisit un jour dans un calendrier mensuel (où chaque jour signale d'un coup d'œil s'il est libre, chargé ou complet) et une durée de session (de trente minutes à quatre heures), consulte la disponibilité des créneaux de la journée (affichage de type « libre / occupé / complet » avec le nombre de machines libres, sans révéler le détail des réservations d'autrui), sélectionne un créneau, coche une ou plusieurs machines à utiliser en parallèle, puis ajoute le créneau à son panier ; il peut en composer plusieurs avant de confirmer. Le système vérifie en temps réel la capacité du créneau (limite de 15 personnes simultanées au FabLab) et empêche toute double-réservation de la même machine sur un créneau qui se chevauche, une session longue bloquant l'ensemble des créneaux qu'elle recouvre.

**Annuler ou reporter une réservation.** L'étudiant peut annuler ou reporter une réservation planifiée. Une annulation tardive (moins de trois jours avant le créneau) déclenche une sanction. Au bout d'un certain nombre de sanctions, le compte est désactivé.

**Piloter le FabLab (administration).** L'administrateur ajoute des machines et gère leurs états (active, en maintenance, hors service), suit les stocks de consommables avec une prédiction de rupture et une alerte de seuil, gère les comptes, édite la vitrine publique, et consulte le journal des actions d'administration.

### 3.3 Les règles de gestion

Les spécifications fonctionnelles se concrétisent dans un ensemble de règles de gestion, vérifiées par la couche métier. Les principales sont les suivantes.

**Règles de réservation.** Une réservation n'est possible que sur un projet validé ou en cours. La machine visée doit être réservable (ni en maintenance, ni hors service). Un projet ne peut pas dépasser un quota de sessions de réalisation (fixé à 4). La capacité d'un créneau est plafonnée à 15 personnes simultanées, limite propre au FabLab. Ces règles sont vérifiées dans un ordre déterminé, des moins coûteuses aux plus coûteuses, et les vérifications sensibles à la concurrence (capacité, état de la machine) sont rejouées sous verrou au sein de la transaction, car l'état de la base a pu changer entre la première vérification et l'écriture.

| Règle | Condition | Effet si violée |
|---|---|---|
| Projet approuvé | Le projet est validé ou en cours | Refus : projet à valider d'abord |
| Machine réservable | La machine n'est ni en maintenance ni hors service | Refus : machine indisponible |
| Quota de sessions | Le projet n'a pas atteint 4 sessions de réalisation | Refus : maximum atteint |
| Capacité du créneau | Le créneau compte moins de 15 personnes | Refus : places restantes affichées |

**Règles d'annulation et de sanction.** Seule une réservation planifiée peut être annulée ou reportée. Une annulation tardive (moins de trois jours avant le créneau) déclenche une sanction. Au-delà d'un seuil de 5 sanctions actives, le compte de l'étudiant est désactivé. La levée d'une sanction par un administrateur ne réactive pas automatiquement le compte : la réactivation est un geste administratif distinct, par souci de séparation des opérations sensibles.

**Règles de validation des projets.** Un projet pédagogique est validé par un formateur, un projet personnel par le BDE. Un refus s'accompagne obligatoirement d'un motif, communiqué à l'étudiant. Une transition d'état (soumettre, valider, refuser) n'est possible que depuis l'état de départ autorisé, ce qui est garanti par une machine à états.

### 3.4 Cas d'usage détaillé : réserver un créneau

| Élément | Description |
|---|---|
| Acteur principal | L'étudiant |
| Préconditions | L'étudiant est authentifié ; il possède un projet validé |
| Déclencheur | L'étudiant ouvre la page de réservation de son projet |
| Scénario nominal | 1. Il choisit un jour et une durée. 2. Le système affiche les créneaux du jour avec, pour chacun, le nombre de machines libres. 3. Il sélectionne un créneau libre, choisit le type (préparation ou réalisation), coche une ou plusieurs machines à utiliser en parallèle et indique le nombre de personnes, puis ajoute le créneau au panier (répétable). 4. À la confirmation, le système vérifie les règles de gestion sous verrou pour chaque machine. 5. Une session est enregistrée au statut « planifiée », portant une occupation par machine cochée. 6. Une confirmation est affichée. |
| Scénarios alternatifs | 4a. Le projet n'est pas validé : refus. 4b. La machine est hors service : refus. 4c. Le créneau est complet : refus, places restantes affichées. 4d. Le quota de sessions est atteint : refus. |
| Postconditions | La capacité du créneau est diminuée du nombre de personnes ; la réservation apparaît dans le tableau de bord de l'étudiant et dans le planning. |

### 3.5 Maquettage

La conception visuelle a suivi une discipline systématique : toute évolution de l'interface a été maquettée et validée avant implémentation, sur la base d'un diagnostic appuyé sur des captures du logiciel réel plutôt que sur des intuitions. Le journal d'itérations UI retrace dix itérations de ce type (mise en valeur de la couleur d'action, refonte du tableau de bord, remplacement d'une dépendance de calendrier par un rendu serveur, centralisation des messages, etc.).

#### La refonte du tableau de bord : du wireframe à la conception adaptative

Une confrontation entre le wireframe d'origine et le rendu réel a révélé un écart instructif : le wireframe prévoyait un tableau de bord riche en graphiques (histogramme d'utilisation, donut de répartition, courbe annuelle des pics), là où le rendu livré se limitait à quelques compteurs. Plutôt que de combler cet écart en empilant des graphiques, la conception a été reprise par une démarche sourcée.

La recherche s'est appuyée sur le retour d'expérience documenté en matière de conception de tableaux de bord (2025-2026) et sur les outils de référence de gestion de FabLab (Fabman, Spacebring, Omnify, l'Invention Studio de Georgia Tech). Trois principes en sont ressortis : limiter le nombre d'indicateurs par écran (au-delà de cinq à neuf, l'engagement chute) ; soumettre chaque indicateur au test « so what ? » (s'il ne déclenche pas d'action, c'est une donnée de vanité) ; et adapter le contenu au rôle de l'utilisateur (contrôle d'accès dynamique), pour n'afficher que le pertinent.

Ces principes ont conduit à une maquette de tableau de bord différencié par rôle, validée avant tout codage : une vue d'exploitation pour l'administrateur, une vue de validation pour le formateur et le BDE. Les graphiques annuels du wireframe ont été délibérément écartés du tableau de bord, car ils relèvent de l'analyse et non de l'action immédiate : leur place est sur un écran dédié. Cette analyse a précisément donné lieu, par la suite, à une page de supervision distincte (taux d'utilisation des machines, activité de réservation, fluctuations de consommables) et à un export des données, décrits plus loin. Séparer le tableau de bord opérationnel de la supervision analytique est un logiciel directe du principe de simplicité : chaque écran sert une intention claire.

> Les maquettes de conception (wizard de réservation, tableau de bord adaptatif, supervision) figurent en annexe C, et les captures du logiciel assemblé en annexe D. Y reporter deux à quatre visuels représentatifs, en mettant si possible la maquette en regard du rendu réel pour montrer la fidélité de la réalisation à la conception.

---

## 4. Spécifications techniques

### 4.1 La méthodologie de conception : DRY et KISS

Deux principes de conception ont gouverné l'ensemble des choix techniques du projet. Ils ne sont pas invoqués de façon décorative : ils ont tranché des décisions concrètes, et une partie des arbitrages consignés dans le journal de décisions en découle directement.

**DRY (Don't Repeat Yourself) : une connaissance, un seul endroit.** Une règle métier ne doit exister qu'à un seul endroit du code. Dupliquée, ses copies finissent par diverger, et un correctif en oublie toujours une. Ce principe se vérifie concrètement dans le projet :

- Les règles de réservation (capacité de 15 personnes, quota de sessions, garde « projet validé ») vivent dans le service de réservation, et non répétées dans chaque contrôleur. Lorsqu'un audit a révélé que la garde de statut n'existait que dans le contrôleur, le correctif a consisté à la ramener dans le service, son lieu unique.
- L'affichage des messages à l'utilisateur (succès, erreur) est centralisé une seule fois dans le gabarit principal, au lieu d'être recopié sur chaque page : dix-sept copies dispersées ont été retirées, supprimant le risque qu'un message d'erreur soit muet sur une page qui aurait oublié de l'afficher.
- La correspondance entre un type de projet et le rôle qui le valide est portée par une énumération et réutilisée par le contrôleur, le service et le voter, jamais réécrite.

**KISS (Keep It Simple) : la solution la plus simple qui répond au besoin réel.** La complexité n'est introduite que lorsqu'un besoin présent et concret la justifie ; sinon, elle est un coût net (à écrire, à comprendre, à maintenir). Ce principe se vérifie également :

- Le choix d'un monolithe en couches plutôt que d'une architecture distribuée, adapté au périmètre réel d'un campus.
- L'absence de mécanisme de suppression logique générique là où un état métier (« hors service ») remplissait déjà ce rôle : réutiliser l'existant plutôt qu'ajouter une couche.
- Une gestion d'erreur locale là où quatre points d'appel suffisaient, plutôt qu'un mécanisme global d'interception qui aurait été disproportionné.
- Un calendrier rendu côté serveur plutôt qu'une dépendance JavaScript fragile et difficile à déboguer.

Ces deux principes sont complémentaires : DRY évite de dupliquer une logique existante, KISS évite de créer une logique inutile. Ensemble, ils tendent vers un code fait du minimum de pièces, chacune à un seul endroit. Concrètement, le terme qui revient dans le journal pour écarter une option est « sur-ingénierie » : c'est le logiciel de KISS. Et la réponse type à un défaut découvert lors d'un audit a été de le corriger au plus près des données, sans ajouter de machinerie : un correctif simple, pas une refonte.

> Cette méthodologie n'est pas qu'une intention : elle est tracée. Chaque décision de conception structurante a été consignée avec sa justification et les alternatives écartées, ce qui permet, à la relecture, de vérifier que ces principes ont effectivement guidé les choix.

Le tableau ci-dessous démontre le logiciel des deux principes par des décisions concrètes, chacune vérifiable dans le code ou le journal de décisions.

| Principe | Décision concrète | Preuve vérifiable |
|---|---|---|
| DRY | Les règles de réservation centralisées dans le service, jamais dupliquées dans les contrôleurs ; une garde trouvée seulement dans le contrôleur a été ramenée dans le service | `ReservationService`, décision DEC-063 |
| DRY | Affichage des messages centralisé une fois dans le gabarit : 17 copies dispersées sur 14 pages retirées | `base.html.twig`, décision DEC-066 |
| DRY | Correspondance type de projet / rôle validateur portée par une énumération, réutilisée par contrôleur, service et voter | `ProjetType::roleValideur()` |
| KISS | Front en Twig + Stimulus plutôt qu'un logiciel monopage, adapté au périmètre | Décision DEC-005 |
| KISS | Pas de suppression logique générique là où l'état « hors service » existait déjà | Décision DEC-065 |
| KISS | Calendrier rendu côté serveur plutôt qu'une dépendance JavaScript fragile | Décision DEC-041 |
| KISS | Gestion d'erreur locale pour quelques points d'appel plutôt qu'un mécanisme global disproportionné | Décision DEC-063 |

### 4.2 La pile technique et sa justification

| Couche | Technologie | Justification |
|---|---|---|
| Langage et framework | PHP 8.5, Symfony 7.4 LTS | Framework mature, support long terme, écosystème riche, sécurité par défaut (protection CSRF, échappement Twig) |
| Base de données | PostgreSQL 16 | SGBD relationnel robuste, gestion transactionnelle et verrouillage adaptés à la concurrence des réservations |
| Présentation | Twig, Symfony UX (Stimulus, Turbo) | Rendu côté serveur, interactivité progressive sans SPA lourde |
| Serveur applicatif | FrankenPHP + Caddy | Serveur PHP moderne, TLS automatique |
| Conteneurisation | Docker | Environnement reproductible, déploiement homogène |

Le choix d'un monolithe modulaire plutôt que d'une architecture distribuée (microservices, SPA découplée) est délibéré : pour le périmètre du projet (un campus, une trentaine de fonctionnalités, une petite équipe), un monolithe bien structuré est plus simple à développer, déployer et déboguer. Ce choix illustre le principe de simplicité (KISS) qui gouverne le projet : la complexité n'est introduite que lorsqu'un besoin présent la justifie.

### 4.3 L'architecture en couches

Le logiciel est organisé en quatre couches à responsabilités séparées :

```
┌─────────────────────────────────────────────────────────┐
│  PRÉSENTATION                                            │
│  Contrôleurs Symfony, templates Twig, contrôleurs        │
│  Stimulus. Reçoit les requêtes, rend les réponses.       │
│  Ne contient aucune règle métier : il délègue.           │
└───────────────────────────┬─────────────────────────────┘
                            │ appelle
┌───────────────────────────▼─────────────────────────────┐
│  MÉTIER (services)                                       │
│  Règles de réservation, sanctions, prédiction de stock,  │
│  notifications. Lieu unique de la connaissance métier.   │
└───────────────────────────┬─────────────────────────────┘
                            │ interroge
┌───────────────────────────▼─────────────────────────────┐
│  ACCÈS AUX DONNÉES (repositories)                        │
│  Encapsule les requêtes (DQL), la détection de           │
│  chevauchement, le verrouillage pessimiste.              │
└───────────────────────────┬─────────────────────────────┘
                            │ hydrate / persiste
┌───────────────────────────▼─────────────────────────────┐
│  DOMAINE (entités, énumérations)                         │
│  Modélise les objets métier et leurs comportements.      │
│  PostgreSQL via Doctrine ORM.                            │
└─────────────────────────────────────────────────────────┘
```

- **La couche présentation** (contrôleurs, templates Twig, contrôleurs Stimulus) reçoit les requêtes et rend les réponses. Un contrôleur délègue, il ne décide pas : il ne contient pas de règle métier.
- **La couche métier** (services) porte toute la logique : règles de réservation, calcul des sanctions, prédiction de stock, notifications. C'est le lieu unique de la connaissance métier.
- **La couche d'accès aux données** (repositories) encapsule les requêtes à la base.
- **La couche domaine** (entités, énumérations) modélise les objets métier et leurs comportements.

Cette séparation applique le principe DRY : une règle métier vit à un seul endroit, le service, et n'est jamais dupliquée dans les contrôleurs. Le projet s'arrête volontairement à quatre couches, sans séparer les interfaces de service de leurs implémentations : pour ce périmètre, aller plus loin relèverait de la sur-ingénierie.

### 4.4 La sécurité par conception

La sécurité n'est pas invoquée comme un label : chaque recommandation de l'ANSSI et chaque exigence du RGPD pertinente pour le projet est réalisée par un mécanisme précis, identifiable dans le code. Le tableau ci-dessous fait correspondre l'exigence, le mécanisme qui la met en œuvre, et l'emplacement de sa preuve.

| Exigence (ANSSI / RGPD) | Mécanisme mis en œuvre | Où le vérifier |
|---|---|---|
| Stockage non réversible des mots de passe | Hachage par l'algorithme `auto` de Symfony (bcrypt ou argon2 selon disponibilité), jamais de mot de passe en clair | `config/packages/security.yaml` (`password_hashers: auto`) |
| Protection contre le bourrage d'identifiants (brute force) | Limitation des tentatives de connexion à 5 par minute et par couple identifiant/IP | `security.yaml` (`login_throttling: max_attempts: 5`) |
| Protection contre la falsification de requête (CSRF) | Jeton CSRF vérifié sur chaque action sensible (validation, refus, suppression, annulation) | 11 vérifications `isCsrfTokenValid` dans les contrôleurs |
| Protection contre l'injection de script (XSS) | Échappement automatique du HTML par Twig ; les rares `|raw` ne portent que du markup interne contrôlé, audité | Templates Twig (3 `|raw` audités, sans donnée utilisateur) |
| Validation des données entrantes | Validation déclarative côté serveur sur les entités, formulaires et validateurs dédiés | 11 fichiers portant des contraintes de validation |
| Contrôle d'accès (moindre privilège) | Hiérarchie de rôles pour les zones, voters pour les ressources individuelles, séparation lecture / écriture | `security.yaml` (`role_hierarchy`), `ProjetVoter` |
| Intégrité des données | Unicité des identifiants imposée au niveau base, contraintes d'intégrité référentielle, transactions | Contraintes ORM, verrou pessimiste sur la réservation |
| Confidentialité des fichiers personnels | Plans stockés hors de la racine web, servis par une route protégée par voter ; l'affichage de disponibilité n'expose pas les réservations d'autrui | `PlanUploadService`, route `plan_telecharger` |
| Minimisation des données (RGPD) | Seules les données nécessaires sont collectées sur l'utilisateur : email, nom, prénom, rôle | Entité `User` (champs limités) |
| Traçabilité et responsabilité (RGPD) | Journal d'activité enregistrant les actions métier significatives des administrateurs | `JournalService`, journal consultable (BF_8.1) |

Cette approche illustre le principe de défense en profondeur recommandé par l'ANSSI : la sécurité repose sur des contrôles redondants à chaque couche (validation à la saisie, contrôle d'accès au traitement, intégrité à la persistance), chaque couche restant protectrice même si une autre venait à céder. Un exemple concret de ce principe a été mis en évidence lors d'un audit : la règle « on ne réserve que sur un projet validé » existait dans le contrôleur ; elle demeurait cependant absente du service, et a donc été ramenée au plus près des données, pour qu'elle tienne quel que soit le point d'entrée.

**Le contrôle d'accès par rôle (RBAC) et le cloisonnement des données.** Le contrôle d'accès du logiciel suit le modèle RBAC (Role-Based Access Control) : les permissions sont attachées à des rôles, et non accordées au cas par cas. Quatre rôles hiérarchisés structurent l'accès : l'étudiant (dépôt de projets, réservation), le formateur et le BDE (validation, pilotage, héritant des droits étudiant), et l'administrateur (gestion complète, héritant des précédents). L'accès est vérifié à trois niveaux successifs, par défense en profondeur : par zone d'URL au niveau du pare-feu (un préfixe comme `/admin` exige un rôle minimal avant tout code applicatif), par contrôleur (chaque contrôleur redéclare le rôle requis), et par ressource (un voter vérifie qu'un étudiant n'agit que sur ses propres projets). Ce dernier niveau réalise un cloisonnement par propriétaire, proche dans son esprit du multi-tenant : un logiciel multi-tenant isole les données de plusieurs organisations sur une même instance, en filtrant chaque requête par l'identité du tenant et jamais par un paramètre fourni par le client. Le projet sert une seule organisation, mais applique la même discipline : la décision d'accès se fonde toujours sur l'identité de l'utilisateur authentifié côté serveur, le filtrage s'opère au niveau des données et non du seul affichage, et l'information partagée (la disponibilité d'un créneau) est anonymisée tandis que l'information personnelle reste isolée.

**La discipline des méthodes HTTP.** Le logiciel respecte strictement la sémantique HTTP : une requête GET consulte sans jamais modifier l'état (on peut la recharger ou la mettre en favori sans conséquence), une requête POST porte toute action qui change les données (créer, valider, refuser, annuler, supprimer) et embarque un jeton anti-CSRF. Cette séparation rend le comportement prévisible et écarte les effets de bord accidentels, comme un lien qui agirait en étant simplement visité ou un robot d'indexation qui déclencherait une suppression. L'inventaire complet des soixante-deux routes du logiciel, avec leur méthode et le rôle requis, est consigné dans le document de référence `docs/reference/routes-et-acces.md`, qui atteste qu'aucune route ne fait autre chose que ce que son nom et son verbe annoncent.

### 4.5 Le déploiement

Le logiciel est conteneurisé avec Docker (socle symfony-docker officiel adapté), ce qui garantit un environnement reproductible du poste de développement à la production. Le déploiement et les opérations courantes sont pilotés par un Makefile unique (cibles courtes et auto-documentées : démarrer, amorcer la base, réinitialiser le jeu de démonstration). Le guide de mise en production détaille les variables d'environnement, les droits d'écriture des répertoires d'upload, et les vérifications de sécurité avant ouverture.

### 4.6 Les exigences non fonctionnelles

Au-delà des fonctionnalités, le projet répond à des exigences de qualité qui ont orienté les choix techniques.

| Exigence | Réponse apportée |
|---|---|
| Performance | Rendu côté serveur (pas de chargement d'un logiciel monopage volumineuse), interactivité progressive avec Turbo, requêtes ciblées avec pagination sur les listes |
| Concurrence | Verrou pessimiste et transactions sur les écritures de réservation, garantissant l'intégrité de la capacité même en cas d'accès simultanés |
| Disponibilité | Conteneurisation reproductible, sauvegarde planifiable de la base par script dédié |
| Sécurité | Authentification, contrôle d'accès par rôle et par ressource, validation des entrées, conformité aux recommandations ANSSI et au RGPD (voir 4.3) |
| Maintenabilité | Architecture en couches, principes DRY et KISS, plus de soixante-dix décisions de conception tracées et justifiées, documentation organisée par intention de lecture |
| Accessibilité | Composants d'interface respectant les recommandations WCAG (gestion du focus, contrastes, intitulés explicites) |

La traçabilité des décisions mérite une mention particulière : chaque choix structurant du projet a été consigné dans un journal de décisions, avec sa justification et les alternatives écartées. Cette discipline, héritée d'un pipeline de développement où chaque décision est argumentée avant d'être implémentée, garantit qu'une personne reprenant le projet comprend non seulement ce qui a été fait, mais pourquoi.

### 4.7 La conception d'un composant d'interface complexe : le parcours de réservation

Le parcours de réservation illustre la conception d'un composant d'interface non trivial, les exigences de robustesse qu'il impose, et la capacité à remettre en cause une approche quand elle ne tient pas.

La réservation enchaîne des décisions liées : choisir un créneau, les machines à utiliser, le nombre de personnes, en respectant la disponibilité réelle et les règles de capacité. La première conception envisagée fut un *wizard* (assistant à étapes successives, présentées une à la fois), au motif qu'il réduit la charge mentale et valide au fil de l'eau. Il a d'abord été bâti sur le mécanisme de formulaires multi-étapes natif du framework, récemment introduit.

Cette première approche a buté sur une série de difficultés irréductibles dans le délai du projet : désynchronisation entre l'étape affichée et le contenu réellement rendu, impossibilité de prendre en compte un rendu personnalisé des créneaux à cause du partage de données entre étapes, et persistance d'une saisie de date au clavier que la conception voulait précisément interdire. Le diagnostic, posé après audit, a conclu que le composant natif était trop récent et trop peu documenté pour la personnalisation que le métier exigeait.

La décision d'ingénierie fut double. D'abord, abandonner le composant natif au profit d'une solution « maison » dont on maîtrise chaque ligne (état en session, actions explicites, créneaux pré-générés cliquables). Ensuite, et c'est le point décisif, reconnaître que la tâche elle-même ne justifiait pas un tunnel à étapes : une fois retiré le rendez-vous de préparation (qui relève de l'humain, pas du logiciel), il ne restait qu'une saisie atomique aux champs simples et connus d'avance. Les retours d'expérience confirment qu'un assistant à deux ou trois étapes est trop maigre, et qu'une **page unique** convertit mieux dans ce cas, surtout sur mobile. Le parcours final est donc une page unique disposée en trois colonnes sans défilement (sur le modèle des plateformes de réservation de référence comme Cal.com et Calendly) : un calendrier mensuel dont chaque jour porte une pastille de disponibilité, la liste des créneaux du jour choisi, et le panier des créneaux composés. On clique un jour, on clique un créneau, on choisit le type (préparation ou réalisation), on coche les machines, on règle le nombre de personnes par un compteur, on ajoute au panier, on confirme. Le report d'un créneau emprunte le même sélecteur, sur une page dédiée, plutôt qu'une saisie de date au clavier. Un créneau à plusieurs machines forme une seule session portant une occupation par machine : l'effectif et le type sont saisis une fois pour la session, jamais dupliqués par machine.

Ce double virage assumé illustre une compétence à part entière : savoir reconnaître qu'une dépendance, même fournie par le framework, coûte parfois plus qu'elle ne rapporte, et qu'une structure d'interface présupposée (le tunnel) n'est pas toujours la bonne.

La mise au point a fait émerger des principes qui garantissent la solidité du composant, chacun correspondant à une frontière de responsabilité à respecter :

- l'état du parcours (le panier de créneaux) est une donnée simple et sérialisable, stockée en session par projet, prévisible et inspectable, et non l'effet d'une mécanique cachée ;
- chaque action (ajout, retrait, confirmation) est une transition explicite du contrôleur, en POST avec son propre jeton anti-CSRF ;
- la saisie est entièrement guidée : jour et durée dans des listes fermées, créneau par un clic sur une proposition serveur, machines en cases à cocher, ce qui interdit toute valeur arbitraire (anti-vandalisme) ;
- l'aperçu de disponibilité partage l'état d'un créneau sans révéler les réservations d'autrui ;
- les règles métier (capacité, quota, verrou de concurrence) ne sont jamais dupliquées dans le parcours : à la confirmation, le contrôleur délègue au service métier, seul garant des invariants.

Cette expérience a été consignée comme doctrine réutilisable : un composant d'interface complexe est robuste lorsque chaque responsabilité reste à sa place (le contrôleur pour la navigation et l'état, le service pour les règles, la présentation pour l'affichage). Le franchissement d'une de ces frontières est la cause typique des régressions. C'est une compétence de conception d'interface qui dépasse l'écriture de code : savoir où placer la responsabilité, et savoir renoncer à une structure quand la tâche ne la justifie pas.

---

## 5. Conception de la base de données

### 5.1 Le modèle de données

Le modèle relationnel s'organise autour de l'utilisateur, du projet et de la réservation. Le dictionnaire ci-dessous décrit les entités principales et leurs attributs significatifs.

**Utilisateur** (`User`)

| Attribut | Type | Rôle |
|---|---|---|
| id | entier | Identifiant |
| email | chaîne (unique) | Identifiant de connexion |
| roles | tableau | Rôles de sécurité |
| password | chaîne | Mot de passe haché |
| nom, prenom | chaîne | Identité |
| actif | booléen | Compte actif ou désactivé |

**Projet** (`Projet`)

| Attribut | Type | Rôle |
|---|---|---|
| id | entier | Identifiant |
| titre | chaîne | Intitulé du projet |
| description | texte | Description (facultative) |
| type | énumération | Pédagogique ou personnel |
| statut | énumération | Brouillon, en attente, validé, en cours, terminé, refusé |
| etudiant | relation | Le propriétaire (User) |
| valideur | relation | Le valideur ayant statué (User, facultatif) |
| motifRefus | texte | Motif en cas de refus (facultatif) |

**Machine** (`Machine`) : nom, description, photo, type (espace machine), état (active, maintenance, hors service).

**SessionReservation** (`SessionReservation`) : l'enveloppe d'une réservation. Projet, type (préparation ou réalisation), date de début, date de fin, durée en minutes, statut (planifiée, effectuée, annulée, reportée), nombre de personnes. Porte une à plusieurs occupations machine.

**Reservation** (`Reservation`) : l'occupation d'une machine au sein d'une session. Ne porte que sa session et la machine ; le créneau, le type, l'effectif et le statut se lisent sur la session.

**PlanProjet** (`PlanProjet`) : projet rattaché, nom du fichier stocké, nom d'origine, date de création.

**Sanction** (`Sanction`) : étudiant sanctionné, motif, auteur de la sanction, date de création, date de levée (nulle tant que la sanction est active).

**Notification** (`Notification`) : destinataire, type, message, lien éventuel, date de création, date de lecture (nulle tant que non lue).

**Consommable** (`Consommable`) : consommable avec quantité, seuil d'alerte, catégorie alignée sur les espaces machine.

**MouvementStock** (`MouvementStock`) : consommable concerné, variation (signée), motif (réassort, consommation projet, perte, inventaire), date du mouvement, quantité après le mouvement, note éventuelle. Historique immuable : chaque ajustement de stock écrit une ligne, jamais modifiée ni supprimée, ce qui permet d'analyser les fluctuations dans le temps.

### 5.2 Les relations

Les relations entre entités sont les suivantes :

- Un **utilisateur** peut posséder plusieurs **projets** (relation un-à-plusieurs via `etudiant`), et peut être le valideur de plusieurs projets (via `valideur`).
- Un **projet** est rattaché à un et un seul étudiant, référence éventuellement un valideur, et peut concerner plusieurs **machines** (relation plusieurs-à-plusieurs).
- Un **projet** possède plusieurs **sessions de réservation** et plusieurs **plans** (relations un-à-plusieurs, avec suppression en cascade : supprimer un projet supprime ses plans et ses sessions).
- Une **session de réservation** porte sur un **projet** (relation plusieurs-à-un) et regroupe une ou plusieurs **occupations** (relation un-à-plusieurs, en cascade : supprimer une session supprime ses occupations).
- Une **occupation** (`Reservation`) rattache une **session** à une **machine** (deux relations plusieurs-à-un).
- Une **sanction** vise un étudiant et référence son auteur (deux relations vers `User`).
- Une **notification** vise un destinataire (relation plusieurs-à-un vers `User`).

```
        ┌──────────────┐
        │     User     │
        │──────────────│
        │ id           │
        │ email (uniq) │
        │ roles        │
        │ nom, prenom  │
        │ actif        │
        └──────┬───────┘
        │ 1
        ┌──────────────┼───────────────┬───────────────┐
        │ étudiant     │ valideur      │ destinataire  │ étudiant/auteur
        │ *            │ *             │ *             │ *
   ┌────▼─────┐   ┌────▼─────┐   ┌─────▼──────┐   ┌────▼──────┐
   │  Projet  │   │  Projet  │   │Notification│   │ Sanction  │
   │──────────│   └──────────┘   │────────────│   │───────────│
   │ id       │                  │ message    │   │ motif     │
   │ titre    │                  │ luLe       │   │ leveeLe   │
   │ type     │                  └────────────┘   └───────────┘
   │ statut   │
   └──┬────┬──┘
   1│    │ 1
        │    └──────────────┐
   *│                   │ *
 ┌────▼───────┐   ┌──────────▼────────┐
 │ PlanProjet │   │ SessionReservation│
 │────────────│   │───────────────────│
 │ fichier    │   │ type              │
 │ nomOriginal│   │ dateDebut         │
 └────────────┘   │ dateFin           │
                  │ statut            │
                  │ nbPersonnes       │
                  └─────────┬─────────┘
                       1│
                       │ *
                  ┌──────────▼────────┐        ┌──────────────┐
                  │ Reservation       │ *    1 │   Machine    │
                  │ (occupation)      │────────│──────────────│
                  │───────────────────│        │ nom          │
                  │ session           │        │ type         │
                  │ machine           │        │ etat         │
                  └───────────────────┘        └──────────────┘
        Projet ─*──*─ Machine (machines souhaitées du projet)
```

> Le schéma entité-association est représenté ici sous forme textuelle, suffisante pour la lecture du modèle dans la version actuelle du dossier. Il pourra, si besoin, être doublé en annexe d'un diagramme généré à partir des entités Doctrine.

### 5.3 Le passage au modèle physique

Le modèle est traduit en base PostgreSQL par l'ORM Doctrine : chaque entité devient une table, chaque relation plusieurs-à-un une clé étrangère, et la relation plusieurs-à-plusieurs entre projet et machine une table de jonction. Le schéma est dérivé directement des entités et reconstruit à neuf à chaque assemblage, ce qui permet de recréer la base à l'identique sur n'importe quel environnement à partir du seul code, sans état accumulé. Les contraintes d'intégrité (unicité de l'email, clés étrangères non nulles sur les réservations) sont portées par la base elle-même, et non seulement par le logiciel : c'est la base, en dernier ressort, qui garantit la cohérence des données.

### 5.4 Les choix de conception structurants

**Le modèle « ledger » des sanctions.** Plutôt que de stocker un compteur de sanctions modifiable, chaque sanction est une ligne immuable horodatée, et le nombre de sanctions actives se dérive par requête. Ce choix préserve l'historique : on ne perd jamais la trace d'une sanction, même levée. Lever une sanction l'horodate comme levée sans la supprimer.

**La gestion de la concurrence sur les réservations.** Le cœur métier du logiciel est la réservation, soumise à un risque de concurrence : deux étudiants pourraient réserver le dernier créneau au même instant. La détection de chevauchement repose sur une comparaison d'intervalles semi-ouverts, et l'intégrité de la capacité est garantie par un verrou pessimiste posé au sein d'une transaction, ce qui sérialise les écritures concurrentes sur une même machine. Seule la base de données, par son atomicité, peut empêcher de manière fiable la double-réservation : la règle vit donc au plus près des données.

**La fin de vie des entités référencées.** Une machine qui possède un historique de réservations n'est pas supprimable (sa suppression violerait l'intégrité référentielle et perdrait l'historique) : elle est passée à l'état « hors service », qui la retire de la réservation tout en conservant ses données. L'état métier existant sert de mécanisme d'archivage, sans introduire de suppression logique générique.

---

## 6. Réalisations : extraits de code significatifs

Cette section présente les extraits de code les plus représentatifs des compétences mises en œuvre, organisés selon les trois activités types du référentiel. Pour chaque extrait, l'argumentation précise ce qu'il fait, pourquoi ce choix a été retenu, et quelle compétence il démontre.

### 6.1 Activité type 1 : composants d'interface utilisateur

#### 6.1.1 Un composant front-end interactif (Stimulus)

L'interactivité de l'interface repose sur des contrôleurs Stimulus, qui enrichissent le HTML rendu côté serveur sans recourir à un logiciel monopage lourde. L'exemple ci-dessous gère, sur la file de validation des demandes, l'apparition du champ « motif de refus » uniquement au moment où le valideur clique sur « Refuser », pour garder l'affichage dense le reste du temps.

```javascript
import { Controller } from '@hotwired/stimulus';

/*
 * Révèle un formulaire masqué (le motif de refus) et masque les boutons
 * d'action pendant la saisie, puis restaure l'état au clic sur Annuler.
 */
export default class extends Controller {
    static targets = ['zone', 'actions'];

    ouvrir() {
        this.zoneTarget.hidden = false;
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = true;
        }
        const champ = this.zoneTarget.querySelector('input[type="text"]');
        if (champ) {
            champ.focus();
        }
    }

    fermer() {
        this.zoneTarget.hidden = true;
        if (this.hasActionsTarget) {
            this.actionsTarget.hidden = false;
        }
    }
}
```

**Argumentation.** Ce composant illustre le développement de la partie front-end d'une interface web avec une interactivité progressive. Le choix de Stimulus plutôt qu'un framework de SPA est cohérent avec l'architecture du projet : le rendu reste côté serveur (sécurisé, indexable), et le JavaScript n'ajoute que le comportement nécessaire. L'accessibilité est prise en compte (le focus est porté sur le champ dès son apparition, pour l'utilisateur au clavier). **Compétence démontrée :** développer la partie front-end d'une interface utilisateur web.

#### 6.1.2 Le contrôle d'accès à l'affichage par voter

L'accès à une ressource individuelle (consulter une demande et ses plans) est régi par un voter, qui distingue la lecture de l'écriture :

```php
return match ($attribute) {
    // Édition : le propriétaire ou un admin uniquement.
    self::EDIT => $estProprietaire || $estAdmin,
    // Consultation : propriétaire, admin, ou le valideur habilité pour ce
    // type de projet, sans droit de modification.
    self::VIEW => $estProprietaire || $estAdmin
        || \in_array($projet->getType()->roleValideur(), $user->getRoles(), true),
    default => false,
};
```

**Argumentation.** Ce code applique le principe de moindre privilège : un valideur obtient le droit de consulter une demande (pour décider) sans obtenir celui de la modifier. La logique « quel rôle valide quel type de projet » n'est pas dupliquée : elle est portée par l'énumération du type de projet (`roleValideur()`) et réutilisée ici, conformément au principe DRY. **Compétence démontrée :** développer des composants en intégrant les recommandations de sécurité (contrôle d'accès).

### 6.2 Activité type 2 : persistance des données

#### 6.2.1 La détection de chevauchement de créneaux (requête d'accès aux données)

Le cœur de la réservation repose sur une requête qui calcule combien de personnes occupent déjà le FabLab sur un créneau donné. L'effectif étant porté par la session (et non par chaque machine), la somme se calcule directement sur les sessions. La difficulté est de détecter correctement le **chevauchement** de deux créneaux, et non seulement leur égalité.

```php
public function sommePersonnesSurCreneau(
    \DateTimeImmutable $debut,
    \DateTimeImmutable $fin,
    bool $verrouiller = false,
): int {
    // Verrou pessimiste (DEC-098) : PostgreSQL interdit FOR UPDATE sur un
    // agrégat. On verrouille d'abord les lignes de session concernées par une
    // requête sans agrégat, PUIS on calcule la somme sans verrou.
    if ($verrouiller) {
        $this->createQueryBuilder('sl')
            ->select('sl.id')
            ->where('sl.statut IN (:actifs)')
            ->andWhere('sl.dateDebut < :fin')
            ->andWhere('sl.dateFin > :debut')
            ->setParameter('actifs', [
                ReservationStatut::Planifiee->value,
                ReservationStatut::Effectuee->value,
            ])
            ->setParameter('debut', $debut)
            ->setParameter('fin', $fin)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    return (int) $this->createQueryBuilder('s')
        ->select('COALESCE(SUM(s.nbPersonnes), 0)')
        ->where('s.statut IN (:actifs)')
        // chevauchement d'intervalles semi-ouverts : s.debut < fin ET s.fin > debut
        ->andWhere('s.dateDebut < :fin')
        ->andWhere('s.dateFin > :debut')
        ->setParameter('actifs', [
            ReservationStatut::Planifiee->value,
            ReservationStatut::Effectuee->value,
        ])
        ->setParameter('debut', $debut)
        ->setParameter('fin', $fin)
        ->getQuery()
        ->getSingleScalarResult();
}
```

**Argumentation.** La condition de chevauchement `dateDebut < fin ET dateFin > debut` est la formule correcte pour des intervalles semi-ouverts : deux créneaux qui se touchent (l'un finit quand l'autre commence) ne se chevauchent pas, ce qui est le comportement attendu pour des créneaux successifs. La requête ne compte que les sessions actives (planifiées ou effectuées), excluant les annulées et reportées : une session abandonnée ne doit pas faire paraître un créneau occupé. Le verrou pessimiste, posé sur des lignes (et non sur l'agrégat, interdit par PostgreSQL), sécurise la concurrence (voir 6.2.2). **Compétence démontrée :** développer des composants d'accès aux données et dans le langage d'une base de données.

#### 6.2.2 La gestion de la concurrence : cœur métier sécurisé

La fonctionnalité la plus représentative du projet est la création d'une réservation. Elle concentre les règles métier, la gestion de la concurrence et la défense en profondeur. Une réservation est une session : un créneau, un type, un effectif, et une à plusieurs machines occupées.

```php
public function creerSession(
    Projet $projet,
    ReservationType $type,
    \DateTimeImmutable $debut,
    int $nbPersonnes,
    int $dureeMinutes,
    array $machines,
): SessionReservation {
    // Règle 0 : on ne réserve que sur un projet validé ou en cours.
    // Cette garde est portée dans le service, et non seulement dans le
    // contrôleur : la règle métier doit tenir quel que soit le point d'entrée.
    if (!\in_array($projet->getStatut(), [ProjetStatut::Valide, ProjetStatut::EnCours], true)) {
        throw new ReservationImpossibleException(
            'Ce projet doit être validé avant de pouvoir réserver un créneau.'
        );
    }

    // Règle 1 : quota de sessions de RÉALISATION par projet (la préparation
    // n'est pas plafonnée). On exclut les sessions annulées et reportées.
    // ...

    // Règle 2 : chaque machine doit être réservable et libre sur le créneau ;
    // on la verrouille (verrou pessimiste) avant de lire la capacité.
    // Règle 3 : capacité 15, effectif de la session compté une seule fois.

    // Transaction atomique : une session + ses occupations, tout ou rien.
    $this->em->getConnection()->beginTransaction();
    // ...
}
```

**Argumentation.** Cet extrait illustre trois principes. La **défense en profondeur** : la règle « projet validé » est portée par le service, au plus près des données, et non seulement par l'écran. Un audit a révélé que cette règle n'existait initialement que dans le contrôleur, ce qui la rendait contournable par tout autre point d'entrée ; elle a été ramenée dans le service. La **gestion de la concurrence** : la vérification de capacité et l'insertion se font dans une transaction sous verrou pessimiste posé sur chaque machine, car deux réservations simultanées sur le dernier créneau libre constituent une situation de course que seule l'atomicité de la base peut résoudre de façon fiable. La **clarté des règles** : chaque règle est numérotée, commentée, et lève une exception métier explicite qui devient un message clair pour l'utilisateur. **Compétence démontrée :** développer des composants métier en intégrant les recommandations de sécurité.

### 6.3 Activité type 3 : logiciel multicouche et sécurité

#### 6.3.1 La validation d'un upload contre les archives piégées

L'upload des plans de projet accepte les archives ZIP, ce qui ouvre un vecteur d'attaque classique : la « bombe de décompression » (un fichier minuscule se décompressant en plusieurs giga-octets). Un validateur dédié inspecte le catalogue de l'archive **avant** toute extraction.

```php
$zip = new \ZipArchive();
if (true !== $zip->open($chemin)) {
    $this->context->buildViolation($constraint->messageIllisible)->addViolation();
    return;
}

$tailleTotale = 0;
$entrees = $zip->numFiles;

// Rejet si l'archive contient trop d'entrées.
if ($entrees > $constraint->entreesMax) {
    $zip->close();
    $this->context->buildViolation($constraint->messageEntrees)/* ... */->addViolation();
    return;
}

for ($i = 0; $i < $entrees; ++$i) {
    $stat = $zip->statIndex($i);
    if (false === $stat) { continue; }

    $compresse = (int) ($stat['comp_size'] ?? 0);
    $decompresse = (int) ($stat['size'] ?? 0);
    $tailleTotale += $decompresse;

    // Ratio par entrée : une entrée qui décompresse bien au-delà de sa taille
    // compressée est le marqueur d'une bombe. On ignore les très petites entrées.
    if ($compresse > 0 && $decompresse > 65536
        && ($decompresse / $compresse) > $constraint->ratioMax) {
        $zip->close();
        $this->context->buildViolation($constraint->messageRatio)->addViolation();
        return;
    }
}
```

**Argumentation.** La protection s'appuie sur l'inspection des métadonnées du catalogue (`statIndex`) sans extraire le contenu : ratio de décompression par entrée, taille décompressée totale, nombre d'entrées. La seule taille du fichier reçu resterait trompeuse, puisqu'un ZIP de quelques kilo-octets peut décompresser en pétaoctets. Cette approche est tirée de l'état de l'art en matière de protection contre les bombes de décompression. **Compétence démontrée :** concevoir et développer un logiciel en intégrant les recommandations de sécurité.

#### 6.3.2 Construction en couches : le métier vit dans les services

L'organisation en couches est visible dans la circulation d'une requête : le contrôleur reçoit, délègue au service qui décide, lequel s'appuie sur le repository pour l'accès aux données. Le contrôleur ne contient aucune règle métier. Ce découpage, combiné au pilotage du cycle de vie d'un projet par une machine à états (composant Workflow de Symfony), garantit qu'aucune transition d'état illégale n'est possible : une transition est vérifiée (`can()`) avant d'être appliquée (`apply()`).

**Argumentation.** La séparation stricte des responsabilités rend chaque couche testable et remplaçable, et garantit qu'une règle métier vit à un seul endroit (DRY). Le recours à une machine à états pour le cycle de vie du projet (brouillon, en attente, validé, en cours, terminé, refusé) externalise la cohérence des transitions vers un composant éprouvé plutôt que de la disperser dans des conditions. **Compétence démontrée :** construire un logiciel organisé en couches.

#### 6.3.3 Contrôle d'accès dynamique : le tableau de bord différencié par rôle

Le tableau de bord adapte son contenu au rôle de l'utilisateur (BF_6.3). L'administrateur voit la vue d'exploitation (parc, stock, demandes globales) ; le formateur et le BDE voient une vue de validation filtrée sur le type de projet qu'ils valident. La protection s'appuie sur deux couches complémentaires : le pare-feu au niveau de l'URL et l'attribut au niveau du contrôleur.

```yaml
# config/packages/security.yaml : filtrage au niveau de l'URL (pare-feu).
access_control:
    # Le tableau de bord de pilotage est ouvert aux rôles qui pilotent.
    - { path: ^/pilotage, roles: [ROLE_ADMIN, ROLE_FORMATEUR, ROLE_BDE] }
    # Le reste de l'administration reste strictement réservé à l'admin.
    - { path: ^/admin, roles: ROLE_ADMIN }
```

```php
// Le contrôleur choisit le contenu selon le rôle réel de l'utilisateur.
#[Route('/pilotage/tableau-de-bord', name: 'pilotage_dashboard', methods: ['GET'])]
#[IsGranted('ROLE_FORMATEUR')]
public function dashboard(/* ... repositories ... */): Response
{
    if ($this->isGranted('ROLE_ADMIN')) {
        // Vue d'exploitation : parc, stock, demandes globales.
        return $this->render('admin/dashboard/index.html.twig', [/* ... */]);
    }

    // Vue formateur / BDE : on déduit le type validé du rôle porté,
    // la validation se faisant par type de projet (pédagogique / personnel).
    $typeValide = $this->isGranted('ROLE_BDE')
        ? ProjetType::Personnel
        : ProjetType::Pedagogique;

    $mesDemandes = $projets->enAttenteParType($typeValide);

    return $this->render('admin/dashboard/validation.html.twig', [/* ... */]);
}
```

**Argumentation.** La sécurité repose sur une défense en profondeur : le pare-feu rejette au niveau de l'URL toute requête dont le rôle ne correspond pas à la zone, et l'attribut `#[IsGranted]` redouble ce contrôle au niveau du contrôleur. Le choix de placer le tableau de bord sous `/pilotage` plutôt que sous `/admin` est dicté par la sécurité : `/admin` est verrouillé à l'administrateur seul, et l'y maintenir aurait soit bloqué le formateur, soit contraint à affaiblir ce verrou. Le contenu, lui, n'est jamais surexposé : chaque rôle ne reçoit que les données de son périmètre, et la navigation reflète ces accès (les liens d'administration et le journal d'activité restent réservés à l'administrateur). **Compétence démontrée :** concevoir et développer un logiciel en intégrant les recommandations de sécurité, avec un contrôle d'accès basé sur les rôles.

> Note : les extraits ci-dessus sont fidèles au code du projet. D'autres extraits (prédiction de rupture de stock, modèle ledger des sanctions, génération du flux iCal) peuvent être ajoutés en annexe si le jury souhaite couvrir d'autres compétences.

---


## 7. Jeu d'essai de la fonctionnalité la plus représentative

La fonctionnalité retenue pour le jeu d'essai est la **création d'une réservation**, fonctionnalité la plus représentative du projet (cœur métier, concurrence, sécurité).

### 7.1 Cas nominal

| Élément | Valeur |
|---|---|
| Données en entrée | Le projet validé « [Test] Réservation nominale » (chargé par le jeu de démonstration), une machine active, un créneau libre, 3 personnes prévues |
| Données attendues | Une réservation créée au statut « planifiée », la capacité du créneau diminuée de 3 |
| Données obtenues | [À COMPLÉTER : exécuter le scénario sur le logiciel assemblé et reporter le résultat réel] |
| Analyse de l'écart | [À COMPLÉTER : conforme / écart constaté et explication] |

### 7.2 Cas d'erreur : projet non validé

| Élément | Valeur |
|---|---|
| Données en entrée | Le projet « [Test] Projet brouillon » au statut brouillon (chargé par le jeu de démonstration), une machine active, un créneau libre |
| Données attendues | Refus de la réservation, message « Ce projet doit être validé avant de pouvoir réserver un créneau » |
| Données obtenues | [À COMPLÉTER] |
| Analyse de l'écart | [À COMPLÉTER] |

### 7.3 Cas limite : créneau complet (capacité atteinte)

| Élément | Valeur |
|---|---|
| Données en entrée | Le créneau saturé chargé par le jeu de démonstration (15 personnes, dans trois semaines à 8h sur la première machine active), une demande pour 1 personne de plus via le projet « [Test] Créneau saturé » |
| Données attendues | Refus de la réservation, capacité inchangée |
| Données obtenues | [À COMPLÉTER] |
| Analyse de l'écart | [À COMPLÉTER] |

> Le guide de recette manuelle du projet (`docs/guides/recette-manuelle.md`) détaille sept scénarios reproductibles supplémentaires (double annulation, suppression de machine référencée, levée de sanction, limites d'upload, etc.), à dérouler une fois le projet assemblé. Ils peuvent alimenter les annexes.

> Pour gagner du temps, le jeu de démonstration (`make reseed`) prépare les données de ces trois scénarios : les projets « [Test] Réservation nominale », « [Test] Projet brouillon » et « [Test] Créneau saturé », ainsi qu'un créneau déjà rempli à 15 personnes. Il suffit alors de dérouler chaque scénario sur le logiciel et de reporter le résultat observé.

> La stratégie de test combine deux niveaux complémentaires. D'une part, une suite
> de tests automatisés (PHPUnit) couvre le chemin critique : règles de réservation
> (capacité, quota, concurrence), disponibilité des créneaux, calcul de supervision,
> authentification, cycle de vie des projets et sanctions ; elle est exécutée par
> l'intégration continue à chaque évolution. D'autre part, une recette manuelle
> formalisée par des scénarios reproductibles (ci-dessus et dans
> `docs/guides/recette-manuelle.md`) couvre le rendu réel et les parcours que les
> tests automatisés ne voient pas. Les deux niveaux se renforcent : l'automatisé
> garantit la non-régression des invariants métier, le manuel valide l'expérience
> effective sur le logiciel assemblé.

---

## 8. Sécurité : tests et veille

### 8.1 Test de sécurité de la fonctionnalité la plus représentative

La sécurité des uploads de plans a fait l'objet d'un test offensif ciblé, car c'est un vecteur d'attaque classique (déni de service par amplification).

| Élément | Valeur |
|---|---|
| Scénario | Soumission d'une archive ZIP « bombe de décompression » (fichier de quelques kilo-octets se décompressant en plusieurs giga-octets) |
| Comportement attendu | Rejet de l'archive avant toute extraction, sur la base de l'inspection de son catalogue (ratio de décompression, taille décompressée totale, nombre d'entrées) |
| Comportement obtenu | [À COMPLÉTER : tester sur le logiciel assemblé] |
| Mesure de protection | Validateur dédié qui lit les métadonnées de l'archive sans l'extraire et la rejette si les seuils sont dépassés |

D'autres protections ont été mises en place et peuvent être présentées : contrôle du type réel des fichiers (et non de l'extension déclarée), plafonnement des dimensions des images (protection contre le « pixel-flood »), contrôle d'accès aux plans par voter, limitation des tentatives de connexion.

### 8.2 Veille sur les vulnérabilités

La veille en sécurité s'inscrit dans une pratique quotidienne, tenue principalement par fils RSS. Les sources suivies relèvent de la veille technique de praticien plutôt que des seuls bulletins institutionnels : des sites comme Hackaday, Ars Technica et LWN.net pour le fond technique et système, des agrégateurs comme Hacker News et Lobste.rs où remontent les analyses de vulnérabilités et les retours d'expérience, les fils de la communauté ArchLinux, ainsi que des blogs techniques et des chaînes vidéo spécialisées, et Korben pour l'actualité francophone. Cette veille croise les sources : un sujet repéré sur un agrégateur est souvent recoupé avec l'analyse de fond d'un blog ou d'un fil de discussion, ce qui permet de distinguer une alerte réelle d'un effet d'annonce.

Cette démarche a directement nourri le durcissement du projet. La protection contre les bombes de décompression, mise en œuvre dans le validateur d'archives (voir section 6.3.1), procède de cette veille : ce type d'attaque, où un fichier de quelques kilo-octets se décompresse en plusieurs giga-octets, est régulièrement discuté sur ces plateformes à travers des cas concrets et des anecdotes de terrain. En avoir connaissance a conduit à inspecter le catalogue d'une archive avant toute extraction (nombre d'entrées, ratio de décompression, taille décompressée totale) plutôt que de se fier à la seule taille du fichier reçu, qui ne protège de rien. La veille a ainsi transformé une menace abstraite en un contrôle précis, vérifiable dans le code.

Cette pratique de veille au fil de l'eau complète les vérifications outillées du projet (échappement automatique de Twig, protection CSRF, hachage des mots de passe, limitation des tentatives de connexion) : la première fait découvrir les classes de menaces et les bonnes pratiques émergentes, les secondes garantissent que les protections connues restent en place à chaque évolution.

---

## 9. Bilan et conclusion

### 9.1 Couverture des besoins

Le logiciel couvre les huit familles de besoins du cahier des charges. Sont pleinement réalisés et opérationnels : la vitrine publique et sa gestion de contenu, la soumission et la validation des projets (avec un cycle de vie piloté par machine à états), la réservation de machines avec contrôle de capacité et de chevauchement, le calendrier et l'affichage de disponibilité, la gestion du stock de consommables, le dispositif de sanctions, les notifications, le journal d'activité et la gestion des utilisateurs. Chaque famille s'appuie sur un service métier et un contrôleur dédiés, sans recours à des données simulées : la logique est réelle et vérifiable dans le code.

Au-delà de ce socle, plusieurs extensions ont été menées pour outiller le pilotage dans la durée : une page de supervision analytique (activité de réservation, taux d'utilisation des machines, fluctuations de consommables), distincte du tableau de bord opérationnel, et un export des données en CSV et XLSX pour l'analyse hors logiciel. Ces ajouts prolongent le périmètre initial sans en combler de manque : le socle était complet, ils l'enrichissent. Cette honnêteté sur la frontière entre le réalisé et le projeté est elle-même un choix : livrer un ensemble fiable et vérifiable plutôt qu'un périmètre large mais fragile.

### 9.2 Un développement par itérations

Le projet s'est construit par itérations successives, chacune ajoutant une fonctionnalité ou consolidant l'existant. Cette démarche s'est appuyée sur un cycle de travail constant : discuter le besoin, décider en s'appuyant sur une source (cahier des charges ou retour d'expérience) plutôt que sur une intuition, documenter la décision, exécuter, puis auditer le résultat. Chaque tour de ce cycle a laissé une trace : plus de soixante-dix décisions de conception sont consignées dans le journal de décisions, chacune avec sa justification et les alternatives écartées.

Cette itération s'observe à deux niveaux.

**Sur l'interface**, dix itérations visuelles documentées partent chacune d'une observation concrète (une capture du logiciel réel révélant un défaut) et aboutissent à un correctif argumenté : la mise en valeur de la couleur d'action, la refonte d'un tableau de bord qui n'en était pas un, le remplacement d'une dépendance de calendrier défaillante par un rendu côté serveur plus robuste, ou encore la centralisation de l'affichage des messages après la découverte que certaines erreurs restaient muettes. À chaque fois, le déclencheur est un fait observé, pas une intuition abstraite.

**Sur la logique métier**, le projet s'est solidifié par des passes d'audit successives, confrontant le code aux pratiques éprouvées de projets équivalents. Ces audits ont révélé des failles que le développement initial avait laissées passer, et qui ont été corrigées avant la mise en service : une règle de sécurité présente dans le contrôleur tout en restant absente du service, et donc contournable ; une double-annulation capable d'infliger une double sanction ; la suppression d'une machine référencée qui aurait provoqué une erreur serveur ; un message d'erreur qui ne s'affichait sur aucune page. Aucune de ces failles n'était un défaut de conception du cœur du système, qui s'est révélé solide à chaque audit (gestion de la concurrence, intégrité des données, transitions d'état) : c'étaient des oublis de périphérie, désormais comblés.

C'est cette boucle (construire, observer, auditer, corriger, documenter) qui a fait passer le projet d'un prototype fonctionnel à un logiciel consolidé. La solidité s'est gagnée itération après itération, et surtout rendue vérifiable : chaque correction est tracée et justifiée, ce qui permet de comprendre l'état final du code comme le chemin qui y a mené.

### 9.3 Démarche de qualité

Le projet a été conduit avec une discipline documentée : chaque choix structurant a été tracé comme une décision argumentée, appuyée sur des sources et des retours d'expérience plutôt que sur des intuitions. La logique métier a fait l'objet d'un audit systématique, confrontée aux pratiques éprouvées de projets équivalents, ce qui a permis d'identifier et de corriger plusieurs failles de logique avant la mise en service. Deux principes ont gouverné l'ensemble : ne pas dupliquer une connaissance (DRY) et préférer la solution la plus simple répondant au besoin réel (KISS).

À cette discipline humaine s'ajoute une vérification automatisée. À chaque modification du code, une chaîne d'intégration continue (GitHub Actions) exécute deux contrôles avant qu'une fusion ne soit possible.

Le premier est un linter. Un linter est un outil qui analyse le code source sans l'exécuter, pour y repérer automatiquement les erreurs de syntaxe, les écarts par rapport aux conventions d'écriture et certaines fautes de structure. Le terme vient de l'anglais « lint », les peluches que l'on retire d'un tissu : le linter retire les imperfections du code. Là où un développeur peut laisser passer une accolade mal fermée, une indentation incohérente ou une balise non valide, le linter les signale systématiquement, sur chaque fichier et à chaque modification. Le projet utilise un linter généraliste (super-linter) capable de vérifier d'un même mouvement les différents langages présents : le PHP, les gabarits Twig, la configuration YAML, le JavaScript, le Dockerfile. L'intérêt est double : la cohérence (tout le code suit les mêmes règles, quel que soit son auteur) et la détection précoce (une erreur est attrapée à la validation, avant d'atteindre la production).

Le second contrôle est un audit des dépendances (`composer audit`), qui vérifie qu'aucune des bibliothèques tierces utilisées par le projet ne présente de vulnérabilité de sécurité connue. Cet audit relève directement de la veille de sécurité : il automatise la vérification qu'une faille publiée dans une dépendance ne reste pas silencieusement présente dans le projet.

Cette chaîne automatisée ne remplace pas le jugement humain, elle le complète : elle prend en charge les vérifications mécaniques et répétitives, ce qui libère l'attention pour les questions de conception et de logique métier qui, elles, demandent du discernement.

### 9.4 Apports personnels et perspectives

Ce projet m'a d'abord appris la valeur du travail en amont. J'ai mesuré que la planification et la recherche sont déterminantes, y compris pour un problème en apparence déjà résolu depuis des décennies : réserver une ressource, valider une demande, gérer un stock sont des besoins anciens, mais leur déclinaison juste dans un contexte précis ne va pas de soi. Plutôt que de réinventer, j'ai pris l'habitude d'examiner les solutions existantes (les outils de gestion de FabLab, les patterns éprouvés) et d'en tirer ce qui répondait aux besoins réels du laboratoire, sans copier ce qui ne servait pas. Cette discipline, chercher avant de coder et confronter chaque choix au besoin métier réel, est sans doute le principal acquis méthodologique du projet.

La difficulté la plus sérieuse a justement été de cet ordre : comprendre le besoin métier et m'y tenir, sans me laisser entraîner par mes propres lubies techniques. La tentation est constante, en développement, d'ajouter une fonctionnalité séduisante ou de soigner un détail qui n'intéresse personne, au détriment de ce qui est réellement attendu. Apprendre à distinguer ce qui sert l'utilisateur de ce qui me plaît à moi, et à prioriser le premier, a demandé un effort réel. Le tableau de bord en est un bon exemple : il aurait été tentant d'y empiler des graphiques, mais le besoin réel appelait de la sobriété et de l'action, et c'est cette lecture du besoin qui a tranché.

Sur le plan des compétences, ce projet a consolidé le travail en équipe et la documentation, deux dimensions que j'ai apprises à traiter comme des livrables à part entière, au même titre que le code. La conteneurisation avec Docker a également été un terrain d'approfondissement. Mais l'acquis qui me tient le plus à cœur est d'avoir produit un projet transmissible : un travail documenté, tracé dans ses décisions, qu'une autre équipe peut reprendre.

Cette transmissibilité rejoint directement l'exigence posée par les gérants au départ : un outil qui demande le moins d'entretien possible. Les deux préoccupations n'en font qu'une, vue sous deux angles. Un projet sobre en maintenance et un projet transmissible sont le même objet : un outil qui survit au départ de ceux qui l'ont conçu. C'est tout le sens d'un fil rouge mené par des alternants, dont la présence est par nature temporaire. Les partis pris décrits plus haut, du traçage de stock sans saisie à la documentation des décisions, servent à la fois le faible entretien voulu par les commanditaires et la reprise par une équipe future. Léguer un projet, plutôt que livrer un code que l'on est seul à comprendre, est une compétence professionnelle que ce projet m'a fait travailler concrètement, et c'était ici une demande explicite, pas seulement une bonne pratique.

#### Évolutions réalisées au-delà de la première version

Au-delà du socle initial, plusieurs évolutions ont été menées à terme une fois ce socle stabilisé.

L'analyse de l'activité dans le temps. Le tableau de bord a été conçu pour rester sobre et actionnable, en écartant les graphiques d'analyse qui l'alourdiraient sans servir l'action immédiate. Cette analyse a trouvé sa place sur un écran dédié : une page de supervision réunit l'activité de réservation par mois, le taux d'utilisation de chaque machine (rapporté à la capacité d'ouverture du laboratoire) et les fluctuations de consommables. Pour permettre cette dernière lecture, les mouvements de stock sont désormais tracés automatiquement dans un historique immuable, à chaque ajustement. En complément, un export des données en CSV et en tableur (trois jeux : réservations, taux d'utilisation, mouvements) permet une analyse approfondie hors du logiciel, dans l'outil de l'exploitant.

Cette séparation entre le tableau de bord (vue synthétique et actionnable, à l'écran), la supervision (lecture des tendances) et l'export (matière première de l'analyse hors ligne) correspond à un pattern établi : les plateformes de référence distinguent systématiquement la vue de pilotage de l'export tabulaire destiné à l'analyse hors logiciel. Le format CSV, dénominateur commun de presque tous les outils, garantit que l'utilisateur reste libre de ses moyens d'analyse.

L'orientation mobile. L'orientation retenue est la PWA (application web progressive) plutôt qu'une application native distribuée sur les stores : puisque le produit est déjà une application web responsive, la PWA permet une installation sur l'écran d'accueil et un usage proche du natif sans la charge d'un développement et d'une publication par store. Le manifeste, le service worker et le repli hors ligne ont été mis en place. Ce choix relève d'un pragmatisme assumé : ne pas multiplier les spécifications mobiles propres aux stores quand le besoin est couvert par le web.

La souplesse de réservation. Le créneau d'une heure fixe a laissé place à un créneau souple : heure de début au pas de trente minutes, durée réglable de trente minutes à quatre heures. Ce raffinement, né de l'usage, rapproche l'outil de la réalité d'un laboratoire où les travaux n'ont pas tous la même durée.

#### Perspectives d'évolution

D'autres évolutions prolongent naturellement l'architecture en place, et ont été délibérément reportées.

L'intégration de nouvelles machines au parc est immédiate : le modèle de données traite la machine comme une entité de premier rang, et l'ajout d'un équipement ne demande qu'une saisie en gestion, sans modification du code. L'enrichissement des notifications, déjà servies par un service dédié, pourrait s'étendre à de nouveaux canaux (notifications navigateur) sans toucher à la logique métier qui les déclenche. Une application mobile native reste envisageable si un besoin propre aux stores apparaissait, même si la PWA couvre l'usage actuel. Enfin, la conteneurisation Docker du projet ouvre la voie à un déploiement reproductible et à une montée en charge maîtrisée, terrain que je souhaite approfondir, notamment sur les aspects de mise en production réelle.

---

## 10. Annexes

Les annexes regroupent les pièces de référence du projet. Elles sont limitées à vingt pages : si l'ensemble dépasse cette limite, ne conserver que les extraits les plus représentatifs (par exemple quelques pages du cahier des charges plutôt que sa totalité) et renvoyer au dépôt pour le reste.

**Annexe A : cahier des charges et matrice des exigences.** Le cahier des charges complet du commanditaire et la matrice associant chaque exigence fonctionnelle (BF_1.x à BF_8.x) à sa réalisation. La synthèse de cette matrice figure déjà au paragraphe 2.3 du corps du dossier.

**Annexe B : schéma de la base de données.** Le modèle de données (entités et relations). Une vue d'ensemble en couches figure au paragraphe 4.1 du corps ; l'annexe en donne le détail entité par entité.

**Annexe C : maquettes de conception.** Les maquettes navigables produites avant développement, notamment la réservation de créneaux (page unique multi-machines), le tableau de bord adaptatif et la page de supervision. Elles illustrent la démarche « maquette avant code » décrite au paragraphe 5.x. Fichiers disponibles dans le dossier `dashboard-v2/` du dépôt.

**Annexe D : captures d'écran du logiciel.** Les écrans réels du logiciel assemblé, à mettre en regard des maquettes : tableau de bord, file de validation, wizard de réservation, supervision. À produire sur le logiciel en fonctionnement.

**Annexe E : résultats détaillés des jeux d'essai.** Les relevés complets des scénarios de test exécutés sur le logiciel assemblé (données obtenues, écarts), dont la synthèse figure au chapitre 7.

**Annexe F : journal des décisions techniques.** La liste synthétique des décisions de conception (références DEC), qui trace les choix structurants et leur justification tout au long du projet. Document de référence maintenu dans le dépôt (`docs/reference/journal-decisions.md`).

> Chaque annexe est appelée depuis le corps du dossier à l'endroit pertinent. Adapter les références (numéros de page) une fois la mise en page finale réalisée.
