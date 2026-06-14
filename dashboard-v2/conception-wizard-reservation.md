# Conception : finaliser le wizard de réservation pour le test (Jean Dupont)

> Note d'aboutissement (DEC-099) : ce document de travail décrit l'état du parcours au moment de sa finalisation fonctionnelle. L'ergonomie a depuis été refondue (calendrier mensuel inline à pastilles de densité en remplacement de la liste déroulante de jours, créneaux en liste de style agenda, disposition trois colonnes sans défilement de page, compteur de personnes, page de report dédiée réutilisant le sélecteur). Le métier décrit ci-dessous reste exact et inchangé ; seules la présentation et l'interaction ont évolué. Voir DEC-099 au journal des décisions et `docs/explications/calendrier-disponibilite.md`.

Document de travail fusionnant `conception-dashboard.md` et `TRAVAIL-RESTANT.md`, recentré sur un objectif unique : **rendre le wizard de réservation fonctionnel pour un jeu d'essai avec le compte étudiant Jean Dupont**. Établi après audit du code réel (`ReservationController`, `DisponibiliteController`, `DisponibiliteService`, `wizard.html.twig`, partiels AJAX, `creneau_picker_controller.js`, `ChargerDemoCommand`, `Makefile`).

## 1. État réel après audit (ce qui est déjà fait)

Le parcours wizard est codé de bout en bout et cohérent :

- Route `GET /projets/{id}/reserver` → `ReservationController::page()` rend `wizard.html.twig` (panier en session, durées proposées, machines actives).
- Composition d'un créneau : choix jour + durée + personnes → chargement AJAX des créneaux du jour (`reservation_disponibilite`, partiel `_disponibilite`), clic sur un créneau → chargement des machines libres (`_machines_creneau`), cases à cocher, ajout au panier. Aucune saisie libre de date, durée en liste fermée. Conforme au RETEX FabLab.
- Panier, retrait, confirmation : `ajouter` / `retirer` / `confirmer`, tous protégés CSRF. La confirmation délègue la règle métier (capacité 15, quota 4 sessions, verrou) à `ReservationService::creerSession`.
- Entité `Reservation` : champ `dureeMinutes` présent, `CAPACITE_MAX_FABLAB = 15`, index déclarés.
- Compte de test présent dans `ChargerDemoCommand` : `jean.dupont@cci.re` / `demo1234!`, ROLE_ETUDIANT, avec trois projets de jeu d'essai dédiés : « [Test] Réservation nominale » (Validé, cas nominal), un brouillon (refus attendu), un « [Test] Créneau saturé » (cas limite capacité).
- Initialisation base : `make db` utilise `doctrine:schema:update --force --complete` — **pas de migrations**. L'absence du dossier `migrations/` n'est donc PAS bloquante pour le test ; le schéma se génère depuis les entités.

Conséquence : la section 3 de l'ancien `TRAVAIL-RESTANT.md` (« migrations Doctrine bloquantes ») est **caduque pour le scénario de démo**. Les migrations restent un sujet pour une mise en production réelle, hors périmètre du test.

## 2. Les écarts à corriger pour que le test passe (par priorité)

### Bloquant — template mort `creer.html.twig`
`templates/reservation/creer.html.twig` est l'ancien formulaire mono-machine (`form.machine`, `form.dateDebut`, `form.type`, `form.nbPersonnes`). Plus aucune route ne le rend : `reservation_creer` rend désormais `wizard.html.twig`. Le `form` qu'il attend n'existe plus dans le contrôleur. Risque : confusion, voire erreur si une route ou un test le réveille.
**Action :** supprimer `creer.html.twig` (et vérifier l'absence de FormType associé orphelin), ou le documenter comme retiré. Trancher avant le test pour ne pas tester deux parcours.

### À vérifier — propagation de la durée choisie (ancien §4)
L'ancien `TRAVAIL-RESTANT` signalait une durée par défaut de 30 min figée. Dans le code actuel, `creneau_picker` propage bien la durée sélectionnée (`fb_duree`, `champDuree`) jusqu'au service. Le « 30 min » n'est plus qu'un fallback légitime tant qu'aucune durée n'est choisie.
**Action :** vérification visuelle sur l'app assemblée — changer la durée recharge bien les créneaux ET conditionne les machines libres affichées. Probablement déjà OK ; à confirmer, pas à recoder.

### À confirmer — cohérence des noms SQL natifs (ancien §1)
`reservationsParMois` / `minutesReserveesParMachine` réécrits en SQL natif : confirmer au premier lancement que les noms de table/colonnes (`reservation`, `date_debut`, `machine_id`) correspondent à la convention Doctrine réelle. Hors chemin du wizard mais touche le dashboard affiché après réservation.

## 3. Le scénario de test « Jean Dupont » (cas nominal)

À dérouler sur l'app assemblée, une fois la base initialisée (`make reset CONFIRME=oui` ou `make db` + `make reseed`) :

1. Connexion `jean.dupont@cci.re` / `demo1234!`.
2. Mes projets → ouvrir « [Test] Réservation nominale » (statut Validé) → bouton Réserver.
3. Choisir un jour (J+1 conseillé), une durée (ex. 1 h), un nombre de personnes.
4. Les créneaux du jour s'affichent groupés matin / après-midi, avec le nombre de machines libres ; la pause 12 h-13 h est exclue, la fermeture 16 h 30 respectée.
5. Cliquer un créneau libre → cocher une ou plusieurs machines → Ajouter au panier.
6. Composer un second créneau (vérifier le plafond `MAX_CRENEAUX`).
7. Confirmer → redirection vers le projet, flash de succès, session créée (une occupation par machine cochée).

Cas limites à éprouver avec les projets dédiés : projet brouillon → réservation refusée ; « [Test] Créneau saturé » → capacité atteinte gérée proprement ; quota de 4 sessions de réalisation.

## 4. Liste de conception consolidée (à cocher)

Priorité haute — préalable au test :
- [x] Supprimer/retirer `templates/reservation/creer.html.twig` et son FormType orphelin éventuel.
- [ ] Initialiser la base de démo et confirmer la création du compte Jean Dupont et de ses trois projets de test.
- [ ] Dérouler le scénario nominal (section 3) de bout en bout.

Priorité moyenne — robustesse du wizard :
- [x] Vérifier visuellement que le changement de durée recharge créneaux + machines (ancien §4).
- [ ] Éprouver les trois cas limites (brouillon, saturé, quota 4 sessions).
- [x] Vérifier l'accessibilité du picker au clavier (créneaux = vrais `<button>`, cases à cocher labellisées : déjà conforme dans les partiels, à confirmer au lecteur d'écran).

Priorité basse — hors chemin du test, à ne pas oublier :
- [x] Confirmer les noms SQL natifs des agrégations dashboard (ancien §1).
- [ ] Caper l'export `entrePeriode` si un export devient trop lourd (ancien §1).
- [ ] Générer un jeu de migrations propre pour la mise en production réelle (ancien §3, hors démo).
- [x] Tracer une décision (DEC) : choix `schema:update` en démo vs migrations en prod.

> État (juin 2026) : le wizard est posé et stable. Les items encore décochés se répartissent en deux familles. D'abord la **recette manuelle** que le décideur déroule sur l'instance assemblée (initialiser la démo, jouer le scénario nominal de bout en bout, éprouver les trois cas limites) : ces vérifications relèvent de l'exécution sur machine réelle, pas du code. Ensuite le **hors-démo** laissé volontairement ouvert (caper l'export `entrePeriode` si une volumétrie le justifie un jour ; générer un jeu de migrations propre pour la production, puisque la démo s'appuie sur `schema:update`). Ces points ne bloquent ni le test ni la démonstration.

## 5. Volet dashboard (repris de conception-dashboard.md, non bloquant pour le test)

Le plan de mise en œuvre du tableau de bord adaptatif reste valable et indépendant du wizard :
- [x] Compléter `ProjetRepository` : demandes en attente par valideur ; exposer créneaux du jour et projets encadrés.
- [x] Adapter `DashboardController` : jeu de données par rôle (admin vs formateur).
- [ ] Décliner le template en deux partiels partageant les composants de carte (DRY).
- [x] Vérifier que chaque vue n'expose que les données du périmètre du rôle.

> Réserve : les maquettes (`maquette-reservation-creneaux.html`, `maquette-dashboard-adaptatif.html`, `maquette-supervision-labo.html`) fixent l'agencement et la logique, pas le pixel final. Le rendu réel dépend de l'intégration Twig et de la charte. Les chiffres y sont illustratifs.

---

## 6. Journal d'intégration (mobile-first) — fait dans geniustrack-kit.zip

Audit croisé `geniustrack-main` vs `geniustrack-kit` : le mobile-first du wizard était déjà conçu et documenté par RETEX, mais réparti entre deux états. Opérations réalisées pour produire `geniustrack-kit.zip` :

- **Import mobile-first** dans le kit (depuis la version récente) : `assets/styles/app.css` et `templates/reservation/wizard.html.twig`. Apporte : barre d'action sticky « Confirmer » en bas d'écran sur mobile (`.resa-sticky`, masquée ≥ 860 px et tant que le panier est vide, via `data-panier`), cibles tactiles ≥ 48 px sur les créneaux et ≥ 44 px sur les puces machines, respect des encoches iOS (`env(safe-area-inset-bottom)`), anti-densification des pastilles (espacement contre les taps accidentels).
- **Suppression des orphelins** de l'ancien parcours mono-machine : `templates/reservation/creer.html.twig`, `src/Form/CreneauType.php`, `src/Dto/CreneauDto.php`. Vérifié : zéro référence résiduelle dans `src/`, `templates/`, `tests/`, `config/`. `PanierReservation` (DTO du parcours panier actif) conservé.
- **Correctif multi-créneaux (DEC-101)** : le formulaire de retrait d'une ligne du panier était imbriqué dans le formulaire d'ajout. L'imbrication de `form` étant invalide en HTML, le navigateur refermait le formulaire externe et le bouton « Ajouter » perdait ses champs dès qu'une ligne était présente, bloquant le panier à un créneau. Les formulaires de retrait sont sortis du formulaire principal, chaque bouton les ciblant via l'attribut `form="retirer-N"`. Vérifié sous navigateur.
- **Performance du calendrier (DEC-101)** : le calcul de disponibilité passe d'une requête par créneau et par machine à une requête unique sur la période (`occupationsActivesSurPeriode`), le reste se faisant en mémoire. Un squelette de chargement remplace le texte « Chargement… ».
- **Nettoyage du dépôt** : retrait des zips parasites présents à la racine du kit.
- **Cohérence template/contrôleur** vérifiée : le wizard n'attend que `projet`, `panier`, `machinesDispo`, `dureesProposees`, tous fournis par `ReservationController::page()`.
- **Préservé** : CI recâblé (PHPUnit + base de test + schema-validate actifs, audit conditionné au lock), tests du chemin critique (`ReservationDureeVariableTest`, `DisponibiliteServiceTest`, `SupervisionServiceTest`), `PLAN-FINITION.md`.

### RETEX appliqués (sources 2025-2026)
- CTA sticky en bas d'écran : +4 à 15 % de complétion mobile observé sur des tunnels de réservation/checkout ; garde l'action dans la « thumb zone » (tiers inférieur).
- Cibles tactiles 44×44 px (Apple HIG) / 48×48 dp (Material) : sous ce seuil, taux d'erreur de tap fortement accru.
- Espacement ≥ 8 px entre cibles : réduit les activations accidentelles (cohérent WCAG 2.5.8).
- Créneaux indisponibles grisés + retour immédiat de disponibilité : réduit les « rage clicks » sur les tunnels de réservation.
- Divulgation progressive (créneau → machines → panier) plutôt que modales empilées : recommandé pour le mobile.

Reste hors de ce zip (recette runtime sur projet assemblé) : vérifier sur un vrai mobile que la barre sticky n'occulte pas la dernière ligne du panier, et que le scroll interne de la zone de créneaux reste confortable au pouce.
