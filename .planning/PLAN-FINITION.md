# Plan de finition GeniusLab

Document de pilotage des derniers pourcentages avant de déclarer le projet fini.
On coche au fur et à mesure. Trois blocs : ce que je livre dans le code (tests + CI),
ce qui était cru « à faire » et ne l'est plus (déjà fermé), et la recette que toi seul
peux dérouler (assemblage réel).

Rappel du cadre : tabula rasa pendant le dev (`./assembler-geniuslab.sh --kit . --reset`
recrée le schéma depuis les entités). Aucune migration générée ici ; les migrations sont
une tâche de gel, quand tout est fonctionnel.

---

## Bloc 1 : code livré (tests + CI)

À récupérer dans le zip rendu, puis à vérifier d'une commande après assemblage.

### 1.1 Recâblage du CI (`.github/workflows/ci.yaml`)

Aujourd'hui le CI est le squelette symfony-docker brut : PHPUnit, la création de la base
de test et le schema-validate sont **en commentaires** (les tests ne tournent jamais en
CI), et le job `security` lance `composer audit --locked` alors qu'il n'y a pas de
`composer.lock` dans le kit (job voué à l'échec).

- [x] Décommenter et activer l'étape **création de la base de test** (`doctrine:database:create -e test`).
- [x] Décommenter et activer l'étape **PHPUnit** (`bin/phpunit`), de sorte que la suite
      tourne vraiment à chaque push/PR.
- [x] Décommenter le **schema:validate** (`-e test`), cohérent avec le modèle tabula rasa
      (le schéma est dérivé des entités, donc le validateur doit passer).
- [x] Réparer le job `security` : `composer audit` n'a de sens que sur le projet assemblé
      (avec lock). Choix retenu : conditionner l'étape à la présence d'un `composer.lock`,
      sinon la sauter proprement (pas d'échec faux positif sur le kit).
- [x] (Le job `lint` super-linter est déjà fonctionnel : ne pas y toucher.)

> État 1.1 : fichier `.github/workflows/ci.yaml` livré et recâblé. Ces cases sont faites au
> sens « le CI est écrit correctement » ; sa première exécution verte se constatera au
> premier push une fois le dépôt débloqué.

### 1.2 Tests du chemin critique (transforment les réserves « à confirmer » en « prouvé »)

Nouveaux tests fonctionnels PHPUnit (SQLite mémoire, schéma créé à la volée via
`SchemaTool`, même pattern que `ReservationServiceTest`). But : prouver la logique sans
avoir à cliquer.

Légende : `[x]` test écrit et présent dans le zip ; `[ ]` non couvert par un test (relève
de la recette à l'écran). Tous les `[x]` deviennent une preuve une fois `make test` vert (3.2).

- [x] **Réservation multi-machines** : un créneau coché à N machines produit N réservations
      (une par machine, même horaire). → `ReservationDureeVariableTest::testReservationMultiMachinesSurUnMemeCreneau`
- [x] **Capacité 15** : la réservation qui ferait dépasser 15 personnes est refusée.
      → déjà couvert par `ReservationServiceTest::testCapaciteQuinzePersonnesPlafonnee` (existant)
- [x] **Durée choisie propagée** : un créneau réservé avec une durée de la liste fermée
      porte bien cette durée de bout en bout (pas figée à 30).
      → `ReservationDureeVariableTest::testDureeDeSessionEstPropagee` + `testDureeParDefautRetombeSurLaMachine`
- [x] **Pause déjeuner** : aucun créneau ne chevauche 12h-13h.
      → `DisponibiliteServiceTest::testPauseDejeunerExclueDesCreneaux`
- [x] **Fermeture respectée** : le dernier créneau ne dépasse pas 16h30.
      → `DisponibiliteServiceTest::testFermetureRespectee`
- [x] **Machines libres par créneau** : décompte correct avant/après réservation.
      → `DisponibiliteServiceTest::testMachinesLibresParCreneau`
- [x] **Durées proposées en liste fermée** : [30, 60, … 240].
      → `DisponibiliteServiceTest::testDureesProposeesEstUneListeFermee`
- [x] **Supervision, taux d'utilisation** : valeur exacte sur une fenêtre d'un jour ouvré
      (510 min de capacité, 120 min réservées → 24 %). → `SupervisionServiceTest::testTauxUtilisationSurUnJourOuvre`
- [x] **Supervision, tri décroissant** par taux. → `SupervisionServiceTest::testTriDecroissantParTaux`
- [ ] **Durée hors liste refusée** : non couvert par un test de service (la validation se fait
      au niveau du contrôleur de disponibilité, qui retombe sur `PAS_MINUTES`). À constater en recette.
- [ ] **Début hors créneau refusé** : idem, garde au niveau contrôleur (renvoie 400). À constater en recette.

> État 1.2 : 9 tests écrits, ajoutés dans 3 fichiers (`tests/Service/`). Total du projet :
> 12 fichiers de tests. Ils ne sont « prouvés » qu'une fois `make test` vert chez toi (3.2),
> car PHP n'a pas pu être exécuté dans l'environnement de préparation.

### 1.3 Doc de pilotage remise au propre

- [x] `.planning/TRAVAIL-RESTANT.md` **supprimé** : son contenu utile est repris ici
      (les points déjà fermés sont au Bloc 2). Il contenait des affirmations devenues
      fausses (« migrations bloquantes » alors que le modèle est tabula rasa pendant
      le dev ; entité `MouvementStock` présentée comme écartée alors qu'elle existe).
      Ce plan est désormais l'unique document de pilotage de la finition.

---

### 1.4 Finition mobile-first du wizard + nettoyage du code mort (DEC-092)

La réservation se fait surtout sur mobile : le wizard reçoit les finitions tactiles
manquantes, et l'on supprime les reliquats de l'ancien parcours mono-machine.

- [x] **Barre d'action « Confirmer » sticky** sous 860px (`.resa-sticky`, pilotée par
      `data-panier` sur `.reservation`, masquée sur desktop et tant que le panier est vide).
      Garde le CTA dans la zone du pouce (RETEX : +4 à 15 % de complétion mobile).
- [x] **Encoches iOS** respectées (`env(safe-area-inset-bottom)`).
- [x] **Cibles tactiles** : créneaux >= 48px (Material), puces machines et « Retirer » >= 44px (Apple HIG).
- [x] **Bouton du panier** marqué `resa-confirmer--inline` (desktop seul) pour éviter le doublon avec la barre sticky.
- [x] **Orphelins supprimés** (reliquats mono-machine, non référencés) :
      `templates/reservation/creer.html.twig`, `src/Form/CreneauType.php`, `src/Dto/CreneauDto.php`.
- [x] **Archives parasites retirées** de la racine du dépôt (`avant-apres-geniuslab.zip`, `dashboard-v2-geniuslab.zip`).

> État 1.4 : livré dans le zip. La barre sticky et les cibles tactiles se constatent
> à l'œil sur un vrai mobile (voir recette 3.3).

## Bloc 2 : déjà fermé (vérifié dans le code, ne rien faire)

Points autrefois listés « à faire » mais que la refonte DEC-090 a déjà
réglés. Conservés ici pour mémoire, pour ne pas rouvrir un chantier clos.

- [x] **Bug « durée figée 30 min »** (autrefois suspecté) : le contrôleur de
      disponibilité lit `fb_duree`, le Stimulus `creneau-picker` envoie la durée choisie, le
      service la propage. Le `'30'` n'est qu'un repli défensif égal à `PAS_MINUTES`. Plus un
      bug, seulement à prouver par test (voir 1.2).
- [x] **Pause déjeuner 12h-13h** : implémentée dans `DisponibiliteService`
      (`PAUSE_DEBUT_MINUTES` / `PAUSE_FIN_MINUTES`, exclusion des créneaux chevauchants).
- [x] **Durées en liste fermée** : `dureesProposees()` = [30, 60, … 240], validée côté
      contrôleur (repli sur `PAS_MINUTES` si hors liste).
- [x] **Performance des agrégations (DEC-078)** : SQL natif + index déclarés sur les entités.
      Reste seulement à constater en recette sur jeu volumineux (Bloc 3), pas à coder.
- [x] **Pagination des listings (audité sain)** : entités à fort volume déjà paginées ; les
      `findAll()` restants portent sur des volumes bornés.

---

## Bloc 3 : recette runtime (toi seul, sur projet assemblé)

Ne peut pas se faire ici (ni Docker ni PHP dans l'environnement de préparation). À dérouler
après `./assembler-geniuslab.sh --kit . --reset`. C'est la preuve finale que le projet est fini.

### 3.1 Le projet démarre

- [ ] `./assembler-geniuslab.sh --kit . --reset` se termine sur « C'EST PRÊT » sans erreur.
- [ ] `make up` puis page d'accueil accessible sur https://localhost (certificat accepté).
- [ ] `make db` (schéma) + `make reseed` (démo) sans erreur.
- [ ] `bin/console app:create-admin` puis connexion admin réussie.

### 3.2 La suite de tests passe (preuve du Bloc 1)

- [ ] `make test` : **tous les tests verts**, y compris les nouveaux (1.2). C'est ta preuve
      en une commande que le chemin critique est sain.

### 3.3 Chemin critique cliqué une fois (rendu réel, ce que les tests ne voient pas)

- [ ] Réserver un créneau avec une durée ≠ 30 min : l'aperçu de disponibilité correspond à
      la durée choisie ; les pastilles libres/occupées s'affichent correctement.
- [ ] Cocher plusieurs machines sur un même créneau et confirmer : autant de réservations créées.
- [ ] Pousser un créneau jusqu'à 15 personnes : la 16e est refusée avec message clair.
- [ ] Reporter puis annuler à moins de 3 jours : sanction appliquée sur le compte.
- [ ] Wizard sans scroll parasite à l'écran courant (taille d'écran normale).
- [ ] **Sur mobile** : la barre « Confirmer » sticky apparaît dès qu'un créneau est au panier, reste au-dessus de la barre home iOS, et n'occulte pas la dernière ligne du panier.
- [ ] **Sur mobile** : créneaux et puces machines se tapent sans erreur (cibles 44-48px), scroll de la zone créneaux confortable au pouce.

### 3.4 Supervision et export

- [ ] Page de supervision : taux d'utilisation, courbe mensuelle et courbe de stock non vides
      (le seed étalé sur l'année doit les remplir).
- [ ] Export CSV : ouvre dans un tableur, accents corrects, séparateur point-virgule.
- [ ] Export XLSX : trois onglets, en-têtes mis en forme, accès réservé admin.

### 3.5 Verdict

- [ ] Tout coché ci-dessus → **projet déclaré fonctionnel**. C'est le moment (et seulement
      là) d'envisager le passage aux migrations Doctrine pour figer le schéma en vue d'un
      déploiement avec données à préserver.

---

## Ordre conseillé

1. Récupérer le zip, remplacer les fichiers.
2. Assembler (`--reset`), faire 3.1.
3. `make test` (3.2) : si rouge, me remonter la sortie exacte.
4. Dérouler 3.3 / 3.4 à la main.
5. Cocher 3.5, puis (plus tard) basculer en migrations.
