# Calendrier, vues par rôle et disponibilité free/busy

Ce document décrit trois choses liées : le calendrier de consultation des réservations, sa portée différenciée selon le rôle, et la bande de disponibilité « libre / occupé » affichée pendant la réservation. Il complète `reservation.md` (le cœur métier) ; le flux iCal d'abonnement (BF_3.1) et le choix du jeton sont décrits dans le journal des décisions et le guide de mise en production. Décisions liées : DEC-039 à DEC-042.

Besoins du cahier des charges couverts : BF_3.22 (calendrier des réservations machines, admin), BF_6.3 (vues selon le rôle).

---

## 1. Calendrier rendu côté serveur

Le calendrier est une grille mensuelle rendue en Twig, sans librairie JavaScript (DEC-041). La navigation entre mois se fait par liens serveur (paramètre `mois` au format AAAA-MM). Une liste détaillée des réservations à venir accompagne la grille sous celle-ci.

Pourquoi pas de librairie : FullCalendar avait d'abord été retenu, mais sa grille refusait de se rendre malgré un flux de données valide, y compris en hébergeant le bundle localement. Le diagnostic a confirmé que les données étaient correctes (le flux JSON renvoyait bien les réservations) ; l'échec venait du rendu de la librairie, non débogable côté serveur. Une grille HTML serveur est plus robuste pour un contexte de réseau institutionnel potentiellement restrictif, élimine toute dépendance externe, et donne le contrôle total du style.

Compromis assumé : pas de glisser-déposer ni de vues semaine/jour interactives. Pour une visualisation des réservations à venir, la grille mensuelle suffit.

### Style skeuomorphique (DEC-042)

La grille adopte un relief discret : en-tête façon bloc-éphéméride (dégradé clair, ombre portée), flèches de navigation en relief qui s'enfoncent au clic, cases du mois en léger creux, jour courant surélevé et encadré, réservations en pastilles colorées (bleu préparation, orange réalisation). Les effets reposent sur `linear-gradient` et `box-shadow` (relief vers le haut pour le saillant, `inset` pour le creux), aux tokens réels du design system.

---

## 2. Portée différenciée selon le rôle (DEC-039)

Le calendrier n'expose pas la même chose à tous, conformément à BF_6.3. La portée suit le principe du moindre privilège :

```
Rôle            Voit                                         Méthode repository
─────────       ─────────────────────────────────────        ──────────────────────────
Admin           Toutes les réservations, toutes machines      aVenir()
                (BF_3.22)
Formateur/BDE   Les réservations des projets qu'il a           aVenirParValideur(user)
                validés (périmètre de validation)
Étudiant        Ses propres réservations uniquement            aVenirParEtudiant(user)
```

Le contrôleur (`CalendrierController::vue`) choisit la portée selon le rôle, du plus large au plus restreint, et la route `/calendrier` est ouverte à tout utilisateur connecté (`ROLE_USER`), pas réservée à l'admin. L'étudiant accède donc à sa propre vue, avec un bouton « Réserver un créneau » qui le mène à ses projets.

Fondement (RETEX) : Microsoft Bookings, Zoho Bookings, Skedda et Google Workspace appliquent tous le moindre privilège par défaut sur les ressources partagées. Le contenu d'un calendrier pouvant révéler des informations sensibles, on masque ce qui ne concerne pas l'utilisateur (confidentialité par conception). L'EC01 énonce BF_6.3 comme principe directeur sans détailler chaque rôle ; le détail formateur/BDE est tranché par cohérence avec leur fonction de validation.

Réserve : le périmètre formateur/BDE repose sur `getValideur()` du projet, donc sur les projets déjà validés. Il se construit au fil des validations, ce n'est pas un périmètre figé d'avance. Si le FabLab veut une autre logique (par exemple par type de projet, le formateur validant les pédagogiques et le BDE les personnels), l'ajustement est localisé.

---

## 3. Disponibilité free/busy à la réservation (DEC-040)

Pendant la réservation, l'étudiant voit la disponibilité des machines pour le jour et le créneau visés, sans rien apprendre des réservations d'autrui. C'est le pattern free/busy, décliné à deux niveaux : la densité du jour sur le calendrier, et le décompte de machines libres sur chaque créneau.

### Les états d'un créneau

Pour le parcours multi-machines (page unique, DEC-090), l'état d'un créneau reflète le nombre de machines encore libres à cet horaire, calculé par `creneauxAvecMachinesLibres()`.

```
État        Signification                              Apparence (liste agenda)
─────       ──────────────────────────────────────     ────────────────────────────
libre       Toutes les machines disponibles             Pastille verte, cliquable
occupe      Au moins une machine prise, mais il          Pastille ambre, cliquable
            reste de la place (anonyme)
complet     Aucune machine libre sur le créneau          Pastille rouge, désactivé
```

Depuis DEC-099, les créneaux du jour sont présentés en liste verticale épurée de style agenda (`fb-liste`) : une ligne par créneau, avec une pastille d'état discrète, l'heure et le nombre de machines libres aligné à droite. L'état n'est plus porté par la seule couleur : la pastille s'accompagne toujours d'un libellé chiffré (« 6 machines libres ») ou de la mention « Complet », conforme à l'accessibilité. Les pavés-boutons colorés en grille de la version antérieure sont abandonnés (rendu jugé peu professionnel en revue de maquette).

L'anonymat est garanti côté serveur. Le `DisponibiliteService` charge en une seule requête toutes les occupations actives qui chevauchent la période demandée (un jour, ou un mois entier pour le calendrier), via `occupationsActivesSurPeriode()`, puis calcule l'état de chaque créneau en mémoire. Ce choix remplace une lecture machine par machine et créneau par créneau, qui multipliait les allers-retours vers la base (plusieurs centaines de requêtes pour un mois) et rendait le calendrier lent. Le serveur ne renvoie jamais le projet ni la personne d'une réservation d'autrui, seulement l'état du créneau et le décompte de machines libres.

### La densité du jour sur le calendrier (DEC-099)

Le sélecteur de date est un calendrier mensuel inline (et non plus une liste déroulante de jours). Chaque jour réservable porte une pastille de densité, calculée côté serveur par `densitesDuMois()` : libre, chargé, ou complet selon la proportion de créneaux du jour encore ouverts pour la durée envisagée. Le nombre de créneaux libres n'apparaît qu'au survol du jour ou sur le jour sélectionné, jamais en texte permanent dans la cellule. Les jours passés et les jours entièrement complets sont désactivés. Le principe free/busy tient au niveau du jour comme au niveau du créneau : seul l'état synthétique est exposé.

Pendant la récupération (calendrier d'un mois ou créneaux d'un jour), un squelette animé occupe la place du contenu à venir, plutôt qu'un texte « Chargement… ». L'attente résiduelle paraît moins brutale, et l'animation respecte la préférence système de mouvement réduit.

```
État du jour   Signification                               Pastille
────────────   ─────────────────────────────────────       ───────────
libre          La plupart des créneaux ouverts              verte
charge         Moins de la moitié des créneaux ouverts       ambre
complet        Aucun créneau libre (jour désactivé)          contour vide
indispo        Jour passé ou hors période (désactivé)        aucune
```

Fondement (RETEX) : Google Workspace propose explicitement le partage « See only free/busy » qui montre qu'une ressource est prise sans révéler les détails. GroupCal décrit la même mécanique : tous voient les créneaux marqués occupés, mais seul le propriétaire reconnaît les siens.

### Bornes horaires

Les heures d'ouverture du FabLab ne sont pas spécifiées par l'EC01. Le service retient une plage de 8h à 16h30, centralisée dans des constantes (`HEURE_OUVERTURE`, `MINUTE_OUVERTURE`, `FERMETURE_MINUTES`) et donc facile à ajuster si le FabLab fixe d'autres horaires. La fermeture est exprimée en minutes depuis minuit pour gérer la demi-heure proprement. Cette borne est une source de vérité unique, partagée par la génération des créneaux et par le calcul de capacité de la supervision (taux d'utilisation des machines).

Les créneaux ne sont plus figés à une heure : l'heure de début se choisit au pas de 30 minutes, et la durée d'une session est réglable de 30 minutes à 4 heures par incréments de 30 (RETEX : l'intervalle de début et la durée sont deux réglages distincts). La durée est portée par la session de réservation, non plus déduite de la machine ; une session longue occupe et bloque tous les créneaux qu'elle recouvre, la détection de chevauchement reposant sur des intervalles semi-ouverts.

### Intégration au parcours

Le calendrier et la liste de créneaux sont pilotés par le contrôleur Stimulus `creneau-picker`, qui orchestre tout le sélecteur côté client : il charge les densités du mois (`fb_mois`, JSON), dessine le calendrier, charge les créneaux du jour choisi (`fb_jour`, fragment HTML), puis les machines libres du créneau retenu (`fb_creneau`, fragment HTML). Au clic sur un créneau libre, le panneau de droite bascule du panier vers les cases à cocher des machines, et le champ caché du début est renseigné au format `Y-m-d\TH:i`.

Depuis le passage à la durée variable, l'aperçu de disponibilité tient compte de la durée envisagée : le contrôleur transmet `fb_duree` (borné aux mêmes minimum et maximum que la session) à chaque requête. Un créneau de début est marqué disponible seulement si la plage début plus durée ne chevauche aucune réservation et ne dépasse pas la fermeture. À défaut de durée transmise, le pas minimal de 30 minutes s'applique. Changer la durée recharge à la fois les densités du mois et les créneaux du jour.

La page de report (`reporter.html.twig`) réutilise exactement le même calendrier à densités et la même liste de créneaux, via le contrôleur jumeau `report-picker` : on y choisit un nouveau créneau pour la même durée (verrouillée), sans machines ni panier, et le créneau retenu alimente le champ `nouvelle_date` soumis à la route de report.

---

## 4. Fichiers

```
Côté serveur
  src/Controller/CalendrierController.php        Vue calendrier + flux iCal
  src/Controller/DisponibiliteController.php      Fragments du jour / machines
                                                  + densités du mois (fb_mois, JSON)
  src/Controller/ProjetController.php             Page de report (GET) + report (POST)
  src/Service/DisponibiliteService.php            États des créneaux, densitesDuMois
  src/Repository/ReservationRepository.php        aVenir, aVenirParEtudiant,
                                                  aVenirParValideur,
                                                  machineOccupeeSurCreneau

Côté template
  templates/calendrier/vue.html.twig              Grille mensuelle skeuomorphique (consultation)
  templates/reservation/wizard.html.twig          Parcours 3 colonnes : calendrier, créneaux, panier
  templates/reservation/_disponibilite.html.twig  Fragment des créneaux du jour (liste agenda)
  templates/reservation/_machines_creneau.html.twig Fragment des machines d'un créneau
  templates/reservation/reporter.html.twig        Page de report dédiée (même picker)

Côté front
  assets/controllers/creneau_picker_controller.js Calendrier à densités, créneaux, stepper
  assets/controllers/report_picker_controller.js  Picker de report (calendrier + créneaux)
  assets/styles/app.css                           Styles calendrier, créneaux, picker
```

Le calendrier de *consultation* (`templates/calendrier/vue.html.twig`, rendu serveur, DEC-041) et le calendrier de *réservation* (inline, rendu client, DEC-099) sont deux composants distincts pour deux usages distincts : l'un affiche les réservations à venir selon le rôle, l'autre sert à choisir une date disponible. Ils ne partagent pas de code.

---

## 5. Couverture des besoins

```
Besoin     Couverture
──────     ─────────────────────────────────────────────────────────
BF_3.22    Calendrier de toutes les machines, vue admin
BF_6.3     Portée différenciée selon le rôle (admin, staff, étudiant)
BF_3.9     Capacité 15 reflétée dans l'état « complet » du free/busy
```
