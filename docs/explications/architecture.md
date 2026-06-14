# Architecture : GeniusLab

GeniusLab est un monolithe modulaire Symfony, déployé en conteneur. Ce document décrit le système tel qu'il est construit : ses couches, son modèle de données, sa sécurité, sa couche de présentation et son infrastructure. Chaque choix renvoie, quand c'est pertinent, à sa décision dans le journal (`DEC-NNN`).

Le produit gère le FabLab du campus CCI Nord de l'École du Numérique (La Réunion) : vitrine, soumission et validation de projets, réservation de machines (capacité 15 personnes), gestion du stock, sanctions, tableau de bord, journal d'activité. Commanditaires : l'équipe pédagogique qui gère aujourd'hui le laboratoire. Le détail du référentiel fonctionnel (huit familles, une trentaine de besoins) figure en section 0 du journal de décisions.

Stack : Symfony 7.4 LTS, PHP 8.5, PostgreSQL 16, Twig + Symfony UX (Stimulus, Turbo), FrankenPHP + Caddy en Docker.

---

## 0. Principes de conception : DRY et KISS

Deux principes gouvernent toutes les décisions de ce projet. Ils ne sont pas décoratifs : ils tranchent concrètement, et plusieurs décisions du journal sont des applications directes de l'un ou de l'autre.

**DRY (Don't Repeat Yourself) : une règle, un seul endroit.** Une connaissance métier ne doit exister qu'à un seul endroit du code. Quand elle est dupliquée, les copies divergent tôt ou tard, et un correctif en oublie toujours une.

- La règle des 15 personnes, le quota de sessions, la garde « projet validé » vivent dans `ReservationService`, pas répétées à chaque contrôleur qui réserve (section 2). Quand un audit a trouvé la garde de statut seulement dans le contrôleur, le correctif a été de la ramener dans le service, son lieu unique (DEC-063).
- L'affichage des messages flash est centralisé dans le gabarit, une fois, au lieu d'être recopié sur chaque page : dix-sept copies retirées, et plus aucun message muet (DEC-066).
- Le rôle qui valide un type de projet est porté par l'enum (`ProjetType::roleValideur()`) et réutilisé par le contrôleur, le service et le voter, jamais réécrit (pattern enum à comportement, `patterns-code.md`).

**KISS (Keep It Simple) : la solution la plus simple qui répond au besoin réel.** On ne construit pas pour un futur hypothétique. La complexité doit être justifiée par un besoin présent et concret, sinon elle est un coût net.

- Monolithe plutôt que microservices, MVC en couches plutôt qu'architecture distribuée, pour le périmètre d'un campus (section 1, DEC-008).
- Pas de soft delete générique (un flag `supprimee` partout) là où l'état métier « hors service » existait déjà : réutiliser l'état plutôt qu'ajouter un mécanisme (DEC-065).
- `try/catch` local pour quatre points d'appel plutôt qu'un listener d'exception global : la solution simple suffit, le mécanisme général serait de la sur-ingénierie (DEC-063).
- Une grille de calendrier rendue en Twig côté serveur plutôt qu'une dépendance JavaScript non débogable (DEC-041).

Le mot qui revient dans le journal pour écarter une option est « sur-ingénierie » : c'est KISS en action. Le réflexe, face à un besoin, est de chercher la solution la plus simple qui le couvre vraiment, puis de s'arrêter là. Quand un audit révèle un trou, on le bouche au plus près des données et sans ajouter de machinerie : un correctif KISS, pas une refonte.

Ces deux principes se complètent : DRY évite la duplication d'une logique, KISS évite la création d'une logique inutile. Ensemble, ils tirent le code vers le minimum de pièces, chacune à un seul endroit.

---

## 1. Principe directeur : monolithe modulaire en couches

GeniusLab est un monolithe assumé (DEC-008). Pour un périmètre interne (un campus, une trentaine de fonctionnalités, une petite équipe), un monolithe bien structuré est plus simple à développer, déployer et déboguer qu'une architecture distribuée. La discipline ne porte pas sur le découpage en services réseau, mais sur la séparation stricte des couches.

```
Couche              Emplacement                  Responsabilité
─────────────       ──────────────────────       ──────────────────────────────────
Controller          src/Controller               reçoit la requête, rend la réponse
                                                  zéro règle métier
Form + DTO          src/Form, src/Dto            validation des entrées, découplage
Service             src/Service                  toute la logique métier
Repository          src/Repository               accès aux données, requêtes, verrous
Entity + Enum       src/Entity, src/Enum         modèle de données et invariants
```

Règle de dépendance : chaque couche ne connaît que celle directement en dessous. Le contrôleur appelle un service, le service appelle des repositories et manipule des entités, le repository parle à la base. Jamais l'inverse, jamais de saut de couche (un contrôleur ne fait pas de requête Doctrine directement).

Le métier vit dans les services, pas dans les contrôleurs. La règle des 15 personnes (BF_3.9, DEC-010) en est l'exemple type : placée dans `ReservationService` avec une transaction et un verrou pessimiste, elle est testable sans HTTP et n'est pas dupliquée à chaque point d'entrée. Un contrôleur délègue, il ne décide pas.

Le code est groupé par domaine fonctionnel (réservation, projet, stock, machine), pas par type technique fourre-tout. Quand un domaine grossit, il se range dans un sous-namespace sans rien casser.

---

## 2. Cadre théorique : MVC et architecture en couches

Cette section met en correspondance l'implémentation avec les patrons d'architecture enseignés, et précise où la correspondance est exacte et où elle demande une nuance.

### Le patron Modèle-Vue-Contrôleur

Le patron MVC sépare une application en trois responsabilités : le Modèle (les données et la logique du domaine), la Vue (la présentation et l'interaction utilisateur), le Contrôleur (le point d'entrée qui reçoit la requête, sollicite le modèle et choisit la vue à rendre). Dans GeniusLab, la Vue correspond aux gabarits Twig, le Contrôleur aux classes de `src/Controller`, et le Modèle au couple entités plus enums de `src/Entity` et `src/Enum`.

Une nuance mérite d'être posée, car elle distingue une mise en correspondance réfléchie d'un placage mécanique : Symfony n'est pas un framework MVC au sens strict. Son fonctionnement est de nature Requête/Réponse, et ses entités constituent une couche de persistance plutôt qu'une couche modèle complète au sens du MVC classique. La logique métier n'est donc pas portée par les entités : elle est déléguée à une couche dédiée. C'est ce qui amène à enrichir le triptyque MVC par une architecture en couches.

### L'enrichissement par la couche service

Le MVC classique ne nomme pas d'endroit pour la logique métier complexe. En son absence, le contrôleur interagit directement avec l'accès aux données et porte lui-même les règles. Cette concentration produit des défauts documentés : des contrôleurs gonflés de logique métier, une duplication de cette logique entre plusieurs contrôleurs, des tests difficiles parce que la logique est couplée à la couche web, et des frontières de transaction floues.

La couche service répond à ces défauts en s'intercalant entre le contrôleur et le modèle. GeniusLab adopte la chaîne en couches canonique :

```
Couche MVC / rôle           Couche applicative        Emplacement
─────────────────────       ──────────────────        ────────────────
Contrôleur (interface)      Présentation              src/Controller
(intercalé)                 Logique métier            src/Service
(intercalé)                 Accès aux données         src/Repository
Modèle (données)            Domaine                   src/Entity, src/Enum
```

Le contrôleur traduit la requête HTTP et délègue. Le service porte toute la règle métier (capacité, quota, transactions, machine à états). Le repository encapsule les requêtes et les verrous. L'entité porte les données et les invariants du domaine. La règle de dépendance est descendante : une couche ne connaît que celle immédiatement en dessous.

### Justification du périmètre des couches

L'ajout de couches n'est pas gratuit : une couche supplémentaire ne se justifie que si elle apporte un regroupement logique qui augmente la maintenabilité, la scalabilité ou la flexibilité. GeniusLab s'arrête à quatre couches (présentation, métier, accès aux données, domaine) sans séparer les interfaces de service de leurs implémentations, ni introduire de couche application distincte du domaine. Pour le périmètre du projet (un campus, une trentaine de fonctionnalités), ce niveau de séparation suffit : aller plus loin relèverait de la sur-ingénierie, contraire à la discipline du projet.

---

## 3. Modèle de données

### Entités du domaine

```
Entité              Rôle                                     Points clés
──────────────      ──────────────────────────────          ──────────────────────────
User                compte (étudiant ou staff)               rôles RBAC, actif/inactif,
                                                              relation vers sanctions
Projet              demande de projet                        type, statut (machine à états),
                                                              étudiant, valideur, machines
SessionReservation  réservation (enveloppe)                  type (préparation/réalisation),
                                                              statut, dates, nb personnes
Reservation         occupation d'une machine                 session, machine
                    dans une session
Machine             équipement du FabLab                     état, durée de créneau, photo
Consommable         article de stock                         quantité, seuil, catégorie
PlanProjet          fichier joint à un projet                BF_3.7, ex DemandeFichier
ContenuVitrine      contenu éditable de la page publique     clé, valeur (texte ou image)
JournalActivite     trace d'événements métier                append-only (BF_8.1)
Sanction            sanction d'un étudiant                   ledger append-only (DEC-028)
Notification        notification in-app                      luLe nullable (DEC-029)
```

### Énumérations

```
Enum                Valeurs
──────────────      ────────────────────────────────────────────────────
MachineEtat         active, maintenance, hors_service
ProjetStatut        brouillon, en_attente, valide, refuse, en_cours, termine
ProjetType          pedagogique, personnel
ReservationType     preparation, realisation
ReservationStatut   planifiee, effectuee, annulee, reportee
```

Les enums portent des comportements métier, pas seulement des valeurs. `ProjetType::roleValideur()` dérive le rôle habilité à valider (pédagogique vers formateur, personnel vers BDE). `ProjetStatut::couleur()` et `::libelle()` portent la présentation. Centraliser ces dérivations dans l'enum évite de disperser la règle.

### Le modèle ledger des sanctions (DEC-028)

Une sanction est une ligne immuable : étudiant, motif, auteur (null si automatique), date de création, date de levée (`leveeLe` nullable). Une sanction est active tant que `leveeLe` est null. Le nombre de sanctions actives d'un étudiant se DÉRIVE en comptant les lignes actives, il n'est plus stocké dans un compteur.

> Principe falsifiable : `User::getNbSanctions()` ne lit aucune colonne, il filtre la collection de sanctions sur celles qui sont actives. Modifier ce comportement pour relire un compteur figé est une régression.

La levée d'une sanction l'horodate, elle ne la supprime pas : l'historique reste complet (qui a sanctionné, quand, et qui a levé). La désactivation automatique d'un compte se déclenche au seuil de sanctions actives, jamais sur un compteur figé.

### Le centre de notifications (DEC-029)

Une notification persiste destinataire, type, message, lien interne optionnel, date de création et `luLe` nullable. Le `luLe` nullable sert d'indicateur lu/non-lu tout en gardant la date de lecture. Une notification in-app est créée en plus du mail, seulement quand le destinataire est un compte identifié. L'alerte de stock bas, qui vise une adresse d'admin, reste un mail seul.

---

## 4. Machine à états du projet

Le statut d'un projet ne se modifie jamais à la main (`setStatut`) dans un contrôleur. Toute transition passe par `ProjetWorkflowService`, qui s'appuie sur le composant Workflow de Symfony. Une transition illégale (par exemple brouillon vers terminé) est refusée par le framework : la cohérence de la machine à états est garantie structurellement, pas par convention.

```
Transitions valides
───────────────────
brouillon    → en_attente      (soumettre)
en_attente   → valide          (valider, selon rôle : DEC-012, DEC-033)
en_attente   → refuse          (refuser, avec motif)
refuse       → en_attente      (resoumettre)
valide       → en_cours        (démarrer)
en_cours     → termine         (clôturer)
```

La validation est différenciée selon le type de projet (DEC-012) : un projet pédagogique se valide par un formateur, un projet personnel par le BDE. La vérification d'habilitation passe par le composant Security (`isGranted`), qui respecte la hiérarchie des rôles (DEC-033) : un admin, qui hérite de formateur et BDE, peut donc valider les deux types.

---

## 5. Sécurité et contrôle d'accès

### Hiérarchie des rôles

```
Rôle               Hérite de
─────────────      ────────────────────────
ROLE_ETUDIANT      (base)
ROLE_FORMATEUR     ROLE_ETUDIANT
ROLE_BDE           ROLE_ETUDIANT
ROLE_ADMIN         ROLE_FORMATEUR, ROLE_BDE
```

Conséquence directe : un admin voit et peut traiter les demandes des deux types. Toute vérification d'habilitation doit passer par `isGranted` (qui applique la hiérarchie), jamais par une lecture brute de `getRoles()` (DEC-033).

### Garde-fous métier sur les comptes

> Le système de sanctions ne s'applique qu'aux étudiants (DEC-031). `SanctionService::sanctionner()` ignore tout membre du staff (admin, formateur, BDE) en tête de méthode : un admin ne peut donc pas être sanctionné, ni s'auto-désactiver par accumulation.

> Un compte admin ne peut jamais être désactivé (DEC-032). La désactivation en masse saute les comptes admin et signale l'opération ignorée.

Ces deux règles ferment les portes par lesquelles l'administration de la plateforme aurait pu se verrouiller elle-même.

### Voter de projet

`ProjetVoter` porte deux attributs. `PROJET_EDIT` autorise la modification au propriétaire du projet et à l'admin. `PROJET_VIEW` autorise la consultation (la demande et ses plans) au propriétaire, à l'admin, et au valideur habilité pour le type de projet (formateur pour pédagogique, BDE pour personnel), sans droit de modification. Cette séparation lecture/écriture suit le moindre privilège : un valideur examine une demande et télécharge ses plans sans pouvoir altérer le projet de l'étudiant. Les contrôleurs délèguent à ce voter plutôt que de vérifier la propriété à la main.

### Surfaces protégées

L'accès est différencié par zone (BF_6.3). Le flux iCal du calendrier est public au sens session mais protégé par un jeton dans l'URL (BF_3.1). Les requêtes sont paramétrées via Doctrine (anti-injection, BNF_3.2) et le HTML est auto-échappé par Twig.

---

## 6. Couche de présentation et design system

### Organisation

La couche front est en Twig, enrichie par Symfony UX (Stimulus pour le comportement, Turbo pour la navigation sans rechargement). Pas de SPA (DEC-005).

### Système de composants

Les composants partagés vivent dans `templates/components` (badge, table de données, en-tête de page, état vide, icône). Deux règles les gouvernent :

Le badge se déclare par variante sémantique, pas par couleur (DEC-021) : `variante: 'succes'` plutôt que `couleur: 'green'`. La logique « sens vers couleur » vit dans le composant.

Un composant partagé porte la structure, jamais les valeurs métier (DEC-022) : vocabulaire CGSS, défauts délibérés, colonnes propres à une entité, formats français et 974, ordres de tri contextuels sont préservés lors de toute généralisation.

### Tokens et couleur fonctionnelle

Couleurs, espacements et rayons viennent exclusivement de `tokens.css` (DEC-020). La couleur encode du sens (DEC-024) :

```
Couleur            Rôle sémantique           Exemples d'usage
──────────         ──────────────────        ─────────────────────────────────
bleu               structure                 en-têtes, navigation, en cours
terracotta         action primaire           boutons soumettre, liens, actifs
vert               succès                     validé, terminé, disponible, valider
ambre              attention                  en attente, stock bas
rouge              erreur, destructif         refusé, sanction, rupture, supprimer
neutres chauds     fond dominant             surfaces, séparateurs
```

La couleur vit au-delà des micro-badges : filets de statut à gauche des lignes de liste, pastilles devant les libellés, cartes à filet coloré. Cadre 60-30-10 : le neutre chaud domine, le bleu structure, l'accent vibrant reste sur l'actionnable. L'action « Valider » porte le vert succès partout (DEC-025), cohérente avec le statut qu'elle produit.

### Accessibilité

Cible WCAG 2.1 AA et RGAA 4.1.2 (DEC-018) : contrastes vérifiés, focus visible, navigation clavier, libellés explicites, page d'accessibilité dédiée. La modale de confirmation est construite sur l'élément `<dialog>` natif (DEC-026), accessible et en français. Les champs numériques utilisent `inputMode`, pas `type="number"` (DEC-023).

---

## 7. Infrastructure et cycle de vie

### Conteneurisation

Socle symfony-docker : FrankenPHP + Caddy en un seul service applicatif (image php8.5), plus un service PostgreSQL 16. La version PHP de l'hôte n'entre pas en jeu, seul le conteneur exécute l'application (DEC-001).

### Dépendances de l'hôte

L'hôte n'exécute pas l'application : il a seulement besoin de Docker, de Composer (étape d'assemblage) et de quelques extensions PHP que Composer exige pour résoudre les dépendances. Les commandes complètes par distribution (APT pour Debian/Ubuntu, Pacman pour Arch) figurent dans le README.

```
Distribution          Gestionnaire    Point de vigilance
──────────────        ────────────    ──────────────────────────────────
Debian / Ubuntu       apt             paquets php-* séparés par extension
Arch / Manjaro        pacman          iconv fourni par php mais désactivé,
                                       à activer dans /etc/php/conf.d
```

> Sur Arch, `symfony/skeleton` exige `ext-iconv`, fourni par le paquet `php` mais désactivé par défaut : l'activer via un fichier dans `/etc/php/conf.d`. Vérifier avec `php -m | grep iconv`.

### Schéma de base

Le schéma est créé et mis à jour depuis les entités à l'assemblage par `doctrine:schema:update --force --complete` (DEC-080, qui remplace DEC-034). Ce choix est assumé : le projet suit un modèle tabula rasa où la base est recréée à neuf et reconstruite par le seed, plutôt que de faire évoluer un schéma existant. Les migrations versionnées n'apporteraient rien dans ce modèle ; elles resteraient pertinentes pour un déploiement en production avec données à préserver.

> Signal de vigilance : `--complete` supprime toute colonne qui n'est plus déclarée dans les entités. Une colonne dont la valeur par défaut compte (par exemple `actif`) doit porter un défaut SQL explicite (`options: ['default' => true]`), car le défaut PHP ne s'applique pas lors d'une recréation de colonne.

### Sauvegarde

`sauvegarde-geniuslab.sh` (DEC-030) lance `pg_dump` dans le conteneur, compresse, applique une rotation par rétention (14 jours par défaut), rejette un dump vide ou tronqué. Conçu pour cron, lancé manuellement.

### Données de démonstration

`app:charger-demo` (DEC-038) peuple comptes, projets de tous statuts, douze réservations futures planifiées, notifications et une sanction. Idempotente, à lancer en développement après assemblage.

### Tests

La suite tourne sur SQLite en mémoire (DEC-002), ultra-rapide, sans service. Les services métier sont bien couverts (capacité, quota, workflow, sanctions). La couverture des contrôleurs reste le point faible identifié et ouvert.

---

## Trajectoire d'évolution

Le monolithe modulaire se densifie de l'intérieur : un domaine qui grossit se range dans un sous-namespace avant d'envisager (peu probable ici) l'extraction d'un service réseau. Les pistes V2 connues : SSO Azure AD, synchronisation calendrier Microsoft Graph, suivi filament temps réel (DEC-009).
