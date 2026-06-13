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

Pendant la réservation (étape « Créneaux » du wizard), l'étudiant voit l'occupation de la machine choisie pour le jour visé, sans rien apprendre des réservations d'autrui. C'est le pattern free/busy.

### Les états d'un créneau

```
État        Signification                          Apparence
─────       ──────────────────────────────         ────────────────────────
libre       Aucune occupation, place disponible     Relief, cliquable (vert)
occupe      Au moins une réservation, mais il        Creux, inactif (gris)
            reste de la place (anonyme)
complet     Capacité de 15 atteinte sur le créneau   Creux, inactif (rouge)
mien        L'utilisateur a déjà une résa ici        Surélevé, encadré (bleu)
```

L'anonymat est garanti côté serveur. Le `DisponibiliteService` calcule l'état de chaque créneau d'une journée en réutilisant `sommePersonnesSurCreneau()` (la logique de capacité qui sert déjà au contrôle de la réservation) et `etudiantOccupeCreneau()` (pour distinguer « occupé » de « le sien »). Le serveur ne renvoie jamais le projet ni la personne d'une réservation d'autrui, seulement l'état du créneau.

Fondement (RETEX) : Google Workspace propose explicitement le partage « See only free/busy » qui montre qu'une ressource est prise sans révéler les détails. GroupCal décrit la même mécanique : tous voient les créneaux marqués occupés, mais seul le propriétaire reconnaît les siens.

### Bornes horaires

Les heures d'ouverture du FabLab ne sont pas spécifiées par l'EC01. Le service retient une plage de 8h à 16h30, centralisée dans des constantes (`HEURE_OUVERTURE`, `MINUTE_OUVERTURE`, `FERMETURE_MINUTES`) et donc facile à ajuster si le FabLab fixe d'autres horaires. La fermeture est exprimée en minutes depuis minuit pour gérer la demi-heure proprement. Cette borne est une source de vérité unique, partagée par la génération des créneaux et par le calcul de capacité de la supervision (taux d'utilisation des machines).

Les créneaux ne sont plus figés à une heure : l'heure de début se choisit au pas de 30 minutes, et la durée d'une session est réglable de 30 minutes à 4 heures par incréments de 30 (RETEX : l'intervalle de début et la durée sont deux réglages distincts). La durée est portée par la session de réservation, non plus déduite de la machine ; une session longue occupe et bloque tous les créneaux qu'elle recouvre, la détection de chevauchement reposant sur des intervalles semi-ouverts.

### Intégration au wizard

La bande free/busy s'intègre à l'étape « Créneaux » via un bloc d'aide « Voir les disponibilités », chargé dans un Turbo Frame séparé (`freebusy-frame`) pour ne pas perturber le flow de réservation existant. Un sélecteur machine + jour déclenche l'affichage. Au clic sur un créneau libre, un contrôleur Stimulus (`freebusy`) renseigne le champ date/heure du créneau.

Depuis le passage à la durée variable, l'aperçu de disponibilité tient compte de la durée envisagée : le contrôleur accepte un paramètre `fb_duree` (borné aux mêmes minimum et maximum que la session), transmis à `creneauxDuJour`. Un créneau de début est alors marqué disponible seulement si la plage début plus durée ne chevauche aucune réservation et ne dépasse pas la fermeture. À défaut de durée transmise, le pas minimal de 30 minutes s'applique.

---

## 4. Fichiers

```
Côté serveur
  src/Controller/CalendrierController.php       Vue calendrier + flux iCal
  src/Controller/DisponibiliteController.php     Fragment free/busy d'un jour
  src/Service/DisponibiliteService.php           Calcul des états de créneaux
  src/Repository/ReservationRepository.php       aVenir, aVenirParEtudiant,
                                                 aVenirParValideur,
                                                 etudiantOccupeCreneau

Côté template
  templates/calendrier/vue.html.twig             Grille mensuelle skeuomorphique
  templates/reservation/_disponibilite.html.twig Fragment de la bande free/busy
  templates/reservation/wizard.html.twig         Intégration à l'étape Créneaux

Côté front
  assets/controllers/freebusy_controller.js      Clic créneau, remplissage heure
  assets/styles/app.css                          Styles calendrier et free/busy
```

---

## 5. Couverture des besoins

```
Besoin     Couverture
──────     ─────────────────────────────────────────────────────────
BF_3.22    Calendrier de toutes les machines, vue admin
BF_6.3     Portée différenciée selon le rôle (admin, staff, étudiant)
BF_3.9     Capacité 15 reflétée dans l'état « complet » du free/busy
```
