# GeniusLab : Cadrage technique & mise sur les rails

**Projet** : Solution de gestion du FabLab GeniusLab (Campus CCI Nord, EDN)
**Document** : cadrage architecte : décisions, roadmap, modèle de données, squelette
**Stack retenue** : Symfony 7.4 LTS / PHP 8.5 / PostgreSQL / Twig + Symfony UX / FrankenPHP (Docker)
**Statut** : V1 du cadrage : à faire évoluer comme une doctrine vivante

---

## 0. Méthode de travail (esprit pipeline)

Ce projet suit une discipline héritée d'un pipeline éprouvé, adaptée au fait que **tout se fait ici** (pas de Claude Code) :

1. **Discuter** : clarifier le besoin réel avant d'écrire du code.
2. **Décider** : chaque choix structurant est une décision nommée (DEC-NNN) avec sa justification. On ne tranche pas une question technique « au feeling » quand une recherche peut la fermer.
3. **Documenter** : le cadrage et les décisions vivent dans le repo (`/docs`), pas dans la tête.
4. **Exécuter** : produire le code réel, par lots cohérents.
5. **Auditer** : vérifier que le livré respecte le cadrage et les exigences (matrice BF/BNF).

**Règle transversale** : la documentation ne doit jamais mentir sur l'état réel du code. Toute dérive (un doc qui décrit une stack abandonnée) est une dette à corriger en priorité : c'est exactement le cas des résidus « React » / « n8n » identifiés ci-dessous.

---

## 1. Décisions de stack (DEC)

### DEC-001 : Framework Symfony 7.4 LTS, runtime PHP 8.5
Symfony 7.4 est la version LTS (sortie nov. 2025), supportée en correctifs de bugs jusqu'à nov. 2028 et en sécurité jusqu'à nov. 2029. Pour un projet certifiant qui doit rester stable 1–2 ans sans maintenance lourde, c'est le choix le plus sûr.

**Runtime PHP 8.5** (révisé). En juin 2026, PHP 8.3 est déjà en phase « sécurité seulement » (fin du support actif au 31/12/2025) : démarrer un projet neuf dessus signifierait ne plus recevoir de correctifs de bugs, seulement les patchs de sécurité critiques : difficile à justifier devant un jury. PHP 8.5 (sortie nov. 2025) est en support actif complet jusqu'au 31/12/2027 (sécurité jusqu'à fin 2029). Symfony 7.4 exige PHP 8.2+, donc 8.5 est compatible (RETEX confirmé : 7.4 + 8.5 tourne en production). En Docker (image FrankenPHP php8.5), la version PHP de la machine hôte n'entre pas en jeu : le piège « 8.5 pas encore packagé sur tous les Ubuntu » est contourné. Seul point de vigilance : vérifier que chaque dépendance Composer déclare la compatibilité 8.5 (le cas pour Doctrine/Twig/Symfony UX).
**Écarté** : PHP 8.3 (support actif terminé) ; Symfony 8.x (cadence 6 mois, rythme de maintenance inutile ici : on garde la LTS et on fait juste évoluer le runtime).

### DEC-002 : Base de données : PostgreSQL (prod/dev) + SQLite (tests)
**Justification** : la BNF_1.1 cible 500 connexions simultanées et la BNF_4.1 exige une architecture scalable. SQLite n'autorise qu'**un seul écrivain à la fois** (verrou d'écriture global, même en mode WAL) et ne se prête pas au scaling horizontal (base = fichier dans le processus). Le pic de réservations à l'ouverture des créneaux (écritures concurrentes) est précisément son point faible.
**Compromis efficacité** : SQLite est conservé pour la **suite de tests** (base en mémoire, ultra-rapide, zéro service) : le meilleur des deux mondes.
**Écarté** : SQLite en production (techniquement viable pour quelques dizaines d'utilisateurs, mais contredit les exigences notées du CdC face au jury).

### DEC-003 : ORM : Doctrine ORM
Standard Symfony. Couvre BNF_3.2 (requêtes paramétrées → anti-injection) et BNF_5.2 (migrations versionnées, schéma maîtrisé).

### DEC-004 : Templates : Twig (auto-escaping natif)
Couvre BNF_3.2 (échappement automatique du HTML). **Remplace la mention erronée « échappement natif React »** de la matrice : résidu d'un prototype front JS, sans objet ici.

### DEC-005 : Front : Twig + Symfony UX (Stimulus + Turbo), pas de SPA
**Justification** (critères : efficacité + maintenabilité) : Twig + Stimulus couvre ~80 % des besoins d'interactivité d'un projet interne typique ; amener React/Vue pour quelques modales et un calendrier serait disproportionné. Turbo apporte le ressenti SPA (pas de rechargement complet) tout en gardant le HTML en Twig. Stack maintenable par une petite équipe.
**Risque noté** : départ des mainteneurs historiques de Turbo/Stimulus (raisons internes Basecamp, sans lien avec la techno) ; communauté et intégration Symfony UX restent solides. Non bloquant.
**Écarté** : SPA React/Vue (sur-ingénierie pour le périmètre), API découplée (reportable en V2 si besoin mobile natif).

### DEC-006 : Auth : Symfony Security, login email `.cci`
Login email/mot de passe restreint au domaine `.cci` (BNF_3.1) + contrôle d'accès par rôles (BF_6.3). **SSO Azure AD** (piste benchmark) = **V2**, hors périmètre note de cadrage.

### DEC-007 : Mail asynchrone : Symfony Mailer + Messenger
File d'attente native PHP (transport Doctrine/AMQP) pour les notifications (BF_3.6, BF_4.4). **Remplace** la reco benchmark « Redis/BullMQ » (Node-only, hors stack).

### DEC-008 : Conteneurisation : Docker
Socle officiel symfony-docker (FrankenPHP + Caddy, un seul service applicatif) + service PostgreSQL. Couvre BNF_4.1 (architecture conteneurisée) et reproductibilité dev/prod. On adopte le standard plutôt qu'une config maison.

### DEC-009 : Pistes archivées (hors périmètre V1)
- **Agent IA n8n + Cal.com** (doc `agent-reservation-n8n-calcom`) : architecture de réservation externalisée, contradictoire avec un développement Symfony maison. **Archivé** comme exploration. La réservation est développée en Symfony (décision dirigeant).
- **OctoPrint / suivi filament temps réel** (benchmark) : V2+.
- **SSO Azure AD, sync calendrier bidirectionnelle Microsoft Graph** : V2.

---

## 2. Périmètre fonctionnel (rappel synthétique)

Source : Livrable EC01 + matrice des exigences. ~30 besoins fonctionnels, 8 familles :

| Famille | BF | Cœur métier |
|---|---|---|
| Présentation | BF_1.x | Vitrine FabLab, machines, édition admin |
| Projets réalisés | BF_2.x | Galerie alimentée par les projets terminés + partagés |
| **Réservations** | BF_3.x | Demande projet → validation → réservation de créneaux (une ou plusieurs machines en parallèle). Cœur du système. |
| Stocks | BF_4.x | Inventaire, alertes, prédiction de rupture |
| Machines | BF_5.x | États (active/maintenance/HS), blocage réservation |
| Utilisateurs | BF_6.x | Auth `.cci`, rôles, sanctions |
| Dashboard | BF_7.x | Stats d'usage, prévisions |
| Journal | BF_8.x | Traçabilité des actions admin |

**Rôles** : Étudiant, Formateur, BDE, Admin.

**Workflow de validation différencié** (point structurant) :
- Projet **pédagogique** → validé/refusé par un **Formateur** (BF_3.4)
- Projet **personnel** → validé/refusé par le **BDE** (BF_3.5)

**Contraintes métier dures** (à ne jamais diluer) :
- Max **15 personnes** simultanées dans le FabLab (BF_3.9) → contrainte sur le choix de créneau.
- Machine en maintenance/HS = non réservable (BF_3.8).
- Temps d'utilisation **adapté par machine** (BF_3.10).
- Sanctions : report < 3 jours ouvrés, ou 5 reports cumulés (BF_6.2).
- **RDV de préparation obligatoire** avant fabrication (benchmark : bloquant).
- **Projets multi-sessions** : 1 à 4 créneaux par projet (benchmark : bloquant).

---

## 3. Modèle de données (entités Doctrine)

Schéma cible. Les entités spécifiques au métier portent les valeurs métier ; ne pas les généraliser à l'excès.

### Entités principales

```
User
  id, email (.cci), roles[], nom, prenom, statut (actif/sanctionné)
  → ROLE_ETUDIANT | ROLE_FORMATEUR | ROLE_BDE | ROLE_ADMIN

Machine
  id, nom, description, photo, type (3D/résine/découpe/...),
  duree_creneau_minutes, etat (active|maintenance|hors_service)
  → etat conditionne la réservabilité (BF_3.8)

Consommable / Stock
  id, nom, categorie (filament PLA/PETG/TPU, résine, pièces d'usure...),
  quantite, seuil_minimal, unite, delai_fournisseur_jours
  → prédiction rupture = quantite / conso_moyenne_30j (BF_4.3)

Projet
  id, titre, description, type (pedagogique|personnel),
  etudiant (User), statut (brouillon|en_attente|valide|refuse|en_cours|termine),
  partage_autorise (bool → alimente la galerie BF_2.2),
  valideur (User formateur ou BDE), motif_refus,
  created_at
  → machine: ManyToMany (un projet peut mobiliser plusieurs machines)

DemandeFichier
  id, projet, nom_fichier, chemin, type (plan_impression...)
  → BF_3.7 import de plans 3D/résine

Reservation (= une session)
  id, projet, machine, type (preparation|realisation),
  date_debut, date_fin, statut (planifiee|effectuee|annulee|reportee),
  nb_personnes_prevues (→ contrainte 15 max, BF_3.9)
  → un Projet a 1 RDV de prépa + 1 à 4 sessions de réalisation

Notification
  id, destinataire (User), type, contenu, lu, envoye_email, created_at

Sanction
  id, etudiant (User), motif, date, auteur (User admin)

JournalActivite
  id, acteur (User), action, cible, details (JSON), created_at
  → BF_8.1 traçabilité admin
```

### Relations clés
- `Projet 1-N Reservation` (multi-sessions)
- `Projet N-N Machine`
- `Projet N-1 User` (étudiant) + `Projet N-1 User` (valideur)
- `Reservation N-1 Machine`
- Contrainte applicative : somme des `nb_personnes_prevues` sur un créneau ≤ 15.

### Note sur les statuts de projet
Machine à états explicite, à inscrire en enum PHP 8.1+ :
`brouillon → en_attente → (valide | refuse) → en_cours → termine`
Un refus renvoie en `brouillon` avec motif (resoumission possible).

---

## 4. Roadmap de développement (lots)

Ordre pensé pour livrer une colonne vertébrale fonctionnelle tôt, puis enrichir. Chaque lot = une branche + un audit contre la matrice.

### Lot 0 : Fondations
- `composer create-project symfony/skeleton`, webapp pack
- Socle symfony-docker (FrankenPHP + Caddy) + PostgreSQL, `.env` / `.env.test` (SQLite)
- Qualité : PHP-CS-Fixer, PHPStan, config lock (BNF_5.1, BNF_5.2)
- CI minimale (GitHub Actions : lint + tests)

### Lot 1 : Auth & rôles (BF_6.x)
- Entité User, Security, login `.cci`, hiérarchie de rôles
- Vues différenciées par rôle (BF_6.3)
- Fixtures de démo (sans mots de passe en clair dans le repo)

### Lot 2 : Machines & Stocks (BF_5.x, BF_4.x)
- CRUD machines + états ; CRUD stocks + seuils
- Alertes stock bas ; widget prédiction de rupture (algo simple, cf. benchmark)

### Lot 3 : Cœur réservation (BF_3.x) : le gros morceau
- Wizard de demande de projet (machine → projet → matériau → fichier → récap)
- Étape RDV de préparation (bloquant benchmark)
- Multi-sessions (1 à 4 créneaux)
- Contrainte 15 personnes, blocage machine HS, durée par machine
- Workflow validation formateur (pédago) / BDE (perso)
- Annulation / report + déclenchement sanctions

### Lot 4 : Notifications (BF_3.6, BF_4.4)
- Mailer + Messenger (async), templates HTML responsives
- Centre de notifications + préférences par type d'événement

### Lot 5 : Vitrine & galerie (BF_1.x, BF_2.x)
- Page présentation éditable (admin)
- Galerie auto-alimentée par projets terminés + partagés

### Lot 6 : Dashboard, journal, calendrier (BF_7.x, BF_8.x, BF_3.22)
- Stats d'usage machines, fréquentation, prévisions
- Journal d'activité admin
- Calendrier des réservations + export iCal (.ics)

### Lot 7 : Finitions BNF transversales
- Responsive (BNF_6.1), compatibilité navigateurs (BNF_6.2)
- Police dyslexie optionnelle (BNF_6.3)
- Design tokens charte FabLab (BNF_7.1)
- Doc technique + guide admin + ADR (BNF_5.3)
- Sauvegardes : dump PostgreSQL quotidien (BNF_3.5)

---

## 5. Corrections à porter dans les livrables existants

À traiter avant ou pendant le Lot 0 (hygiène documentaire) :

1. **Matrice des exigences : BNF_3.2** : remplacer « échappement natif React » par « échappement natif Twig + requêtes paramétrées Doctrine ».
2. **Doc benchmark** : la pile « Redis/BullMQ » (Node) ne s'applique pas → Messenger (PHP). À noter si le doc est annexé au rendu.
3. **Doc n8n/Cal.com** : marquer explicitement « exploration archivée, non retenue » pour éviter qu'un lecteur (jury, repreneur) croie à une architecture hybride.

---

## 6. Couverture des BNF par la stack

| BNF | Exigence | Couverture |
|---|---|---|
| 1.1 | 500 connexions simultanées | PostgreSQL + FrankenPHP (worker mode) |
| 1.2 | Chargement < 1 s | Turbo (pas de full reload), cache Symfony |
| 2.1 | Tolérance de panne partielle | Services découplés (Messenger async) |
| 3.1 | Connexion `.cci` | Security + contrainte domaine |
| 3.2 | Anti-injection | Doctrine paramétré + Twig auto-escape |
| 3.3 | Anti-DDoS | Rate limiter Symfony + Caddy (FrankenPHP) en façade |
| 3.4 | URL sans ID interne exposé | UUID ou slugs sur les routes publiques |
| 3.5 | Sauvegarde auto | Dump PostgreSQL quotidien (cron) + Git |
| 4.1 | Scalabilité | Docker + PostgreSQL |
| 5.1 | Code clair | PHP-CS-Fixer + PHPStan + revue |
| 5.2 | Dépendances maîtrisées | composer.lock + mises à jour planifiées |
| 5.3 | Documentation | Doc technique + guide admin + ADR |
| 6.1 | Responsive | Twig + CSS responsive |
| 6.2 | Multi-navigateurs | Standards web, pas de dépendance exotique |
| 6.3 | Police dyslexie | Option OpenDyslexic dans les paramètres |
| 7.1 | Charte graphique | Design tokens CSS |

---

## 7. Prochaine étape concrète

Le Lot 0 (squelette réel) est prêt à être généré ici. Au choix pour la suite immédiate :
- Générer la commande `composer create-project` + arborescence + docker-compose
- Écrire les premières entités Doctrine (User, Machine, Projet, Reservation)
- Détailler le wizard de réservation (Lot 3, le plus complexe)
