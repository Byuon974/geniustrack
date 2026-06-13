# Audit UI/UX : wireframes face au cahier des charges et aux RETEX

Objectif : traduire fidèlement l'intention des wireframes en composants Twig +
Stimulus, en confrontant chaque parti pris aux bonnes pratiques sourcées, et en
**retirant les mécanismes qui ne servent pas le périmètre**. Principe directeur :
retraduire, pas enrichir.

---

## 1. Inventaire des écrans wireframés

Le wireframe abouti (`geniuslab v1.20`) propose ~25 pages et une bibliothèque de
composants déjà pensée. Classement par rapport au cahier des charges :

### Écrans alignés sur le CdC (à traduire tels quels)
Vitrine, Réservation, Mes réservations, Projets, Calendrier, Règlement & FAQ,
Demandes (formateur/BDE), Dashboard, Archives, Blacklist/Gestion utilisateurs,
CMS Vitrine, Stocks, Machines, Journal, Mentions légales, Confidentialité,
Accessibilité, Notifications, Profil, Paramètres.

### Écrans HORS périmètre CdC : à challenger
| Écran wireframé | Statut | Recommandation |
|---|---|---|
| **Formations** | Hors CdC | **Reporter.** Le RDV de prépa (benchmark) couvre déjà le besoin de validation préalable. Une vraie gestion de formations est un module à part entière non demandé. |
| **Certifications** | Hors CdC | **Reporter.** Idem : gold-plating. |
| **Événements** | Hors CdC | **Reporter.** Aucun BF ne le mentionne. |
| **GlobalSearch** | Hors CdC | **Reporter en V2.** Utile mais non demandé ; coût d'indexation non trivial. |
| **GuidedTour** | Hors CdC | **Optionnel.** Sympathique mais à ne traiter qu'en toute fin si le temps reste. |
| **Rapports / export PDF** | Partiel | **Garder l'export CSV** (simple, demandé implicitement par le dashboard) ; **différer le PDF** (mise en page coûteuse). |

> Ces écrans sont la principale source de « mécanismes inutiles ». Les retirer du
> périmètre V1 n'appauvrit pas le produit : ils ne répondent à aucun BF.

---

## 2. La réservation : du stepper envisagé à la page unique retenue

Le wireframe initial proposait un **Stepper** (formulaire multi-étapes). Après
itérations (DEC-088, DEC-090), le choix retenu est une **page unique
multi-machines** avec panier de créneaux. Cette section retrace pourquoi, en
conservant les RETEX qui ont éclairé la décision.

### Ce que disent les recherches
- Le multi-étapes améliore la complétion **sur les cas difficiles** (première
  fois, mobile, processus de décision à champs nombreux ou conditionnels) ; la
  page unique l'emporte **sur les cas simples** où l'utilisateur connaît ses
  réponses d'avance et où la tâche est atomique.
- **5 à 9 éléments maximum par écran** (au-delà, surcharge cognitive) : la page de
  réservation s'y tient (jour, durée, personnes, puis créneaux puis machines).
- **Validation immédiate** : l'utilisateur voit tout de suite la disponibilité du
  créneau plutôt qu'à la fin d'un tunnel.
- **Retour de disponibilité instantané** : griser les créneaux complets et
  afficher le nombre de machines libres réduit les « rage clicks ».
- Sur mobile, une page unique évite les délais de chargement entre étapes (source
  de friction sur connexion cellulaire) à condition d'un CTA toujours visible.

### Traduction pour GeniusLab (état du code)
La réservation n'est finalement pas un tunnel : c'est une tâche atomique aux
champs simples et fermés. Un stepper à 2-3 étapes aurait été trop maigre. Le
parcours retenu :

```
choisir jour + durée → créneaux du jour (nb de machines libres, matin/après-midi)
→ cliquer un créneau → cocher les machines libres → ajouter au panier → confirmer
```

- Saisie entièrement guidée : jour et durée en listes fermées, créneau par clic
  sur une proposition serveur, machines en cases à cocher (aucune date au clavier).
- État porté par un panier sérialisable en session, par projet ; chaque action
  (ajout, retrait, confirmation) est un POST distinct avec son jeton CSRF.
- Un créneau à N machines produit N réservations (une par machine, même horaire),
  créées via `ReservationService` à la confirmation (capacité, quota, verrou).
- **Mobile-first** (DEC-092) : empilement composition puis panier, grille deux
  colonnes au-delà de 860px, barre « Confirmer » sticky dans la zone du pouce,
  cibles tactiles 44-48px.
- **Pas de SPA, pas de front lourd** : fragments de disponibilité chargés en AJAX
  par un mince contrôleur Stimulus, le reste rendu côté serveur en Twig.

> Le détail métier (règles, invariants, concurrence, principes du wizard maison)
> vit dans `docs/explications/reservation.md`. Cette section ne garde que le
> raisonnement UI/UX qui a conduit du stepper à la page unique.

### Mécanisme à NE PAS ajouter
Le wireframe n'en abuse pas, mais par principe : pas de sauvegarde temps réel à
chaque frappe (`LiveTextarea` du wireframe), pas d'autosave par champ. La
sauvegarde par étape suffit et évite une couche de complexité (debounce, conflits,
endpoints par champ).

---

## 3. Formulaires spécifiques par machine : point d'architecture

Le doc de préparation révèle que **chaque type de machine a son formulaire** :
- Impression 3D : titre, description, formateur, quantité, image STL, dimensions, nb personnes
- Résine : idem 3D
- Flocage : + choix support (t-shirt/casquette/mug), limite de quantité
- Graveuse laser : + choix support (acrylique/bois)
- Scanner 3D : titre, description, formateur, nb personnes (pas de quantité)
- Mini-ordinateur (Raspberry/Arduino) : idem scanner
- Plotteur découpe : + quantité

Le wireframe a la bonne intuition avec un composant **FormRenderer** (rendu de
formulaire piloté par configuration). C'est le bon pattern, à condition de ne pas
le sur-généraliser.

### Traduction Symfony (sans usine à gaz)
Champs **communs** dans un form de base (`DemandeBaseType`) ; champs **spécifiques**
ajoutés par machine via un petit registre. Deux options, par ordre de préférence :

1. **Form Type par machine** (recommandé, le plus lisible) : `Demande3DType`,
   `DemandeFlocageType`… qui héritent d'un `DemandeBaseType`. Symfony gère
   l'héritage de formulaires nativement. Explicite, typé, testable.
2. **Champs dynamiques pilotés par une config** (le FormRenderer du wireframe) :
   plus « élégant » mais introduit de l'indirection. À éviter sauf si le nombre de
   machines explose : ce qui n'arrivera pas (7 machines).

> Décision : **option 1**. Sept form types qui héritent d'une base. Pas de moteur
> de formulaire générique : ce serait précisément le « mécanisme inutile » à fuir.

---

## 4. Composants UI à conserver (traduction Twig + Stimulus)

Le wireframe a déjà une bonne bibliothèque. Correspondance avec ce qu'on garde :

| Composant wireframe | Traduction | Justification |
|---|---|---|
| `Stepper` | : | **Abandonné** : réservation en page unique (DEC-090), pas de tunnel à étapes |
| Sélecteur de créneaux | Stimulus `creneau-picker` + fragments AJAX | Cœur de la réservation page unique |
| `StatusBadge` | Twig Component (utilise `ProjetStatut::couleur()`) | Cohérent avec nos enums |
| `Modal` / `ConfirmDialog` | Stimulus controller + `<dialog>` natif | Confirmations (annulation, blacklist) |
| `Toast` | Stimulus + flash messages Symfony | Feedback léger |
| `Empty` (état vide) | Twig Component | Listes vides (aucune réservation…) |
| `Skeleton` | CSS only | Chargement perçu |
| `KPI`, `Donut`, `MiniBar` | Chart.js (déjà dans la stack front) | Dashboard (BF_7.1) |
| `Pagination` | Twig Component | Listes longues |
| `GlobalSearch` | : | **Reporté** (hors CdC) |
| `GuidedTour` | : | **Reporté** (optionnel) |
| `LiveTextarea` | textarea simple | Autosave par champ retiré |

### `<dialog>` natif plutôt qu'une lib de modale
Le HTML moderne a `<dialog>` natif (focus trap, fermeture Échap, backdrop). Pas
besoin d'une dépendance JS de modale. Un mince controller Stimulus suffit.

---

## 5. Conformité transversale (déjà bien vue par le wireframe)

Le wireframe v1.20 intègre déjà mentions légales, page RGAA, bannière RGPD. À
conserver et brancher sur nos BNF :
- **Accessibilité RGAA** (page dédiée présente) → cohérent avec une exigence
  d'accessibilité ; garder.
- **Police dyslexie** (BNF_6.3) : option OpenDyslexic dans Paramètres : le wireframe
  a déjà la page Paramètres, y ajouter le toggle.
- **Responsive** (BNF_6.1) : mobile-first, la réservation se faisant surtout sur
  mobile (barre d'action sticky, cibles tactiles 44-48px, voir DEC-092).
- **Design tokens** (BNF_7.1) : variables CSS calquées sur la charte : le wireframe
  utilise déjà des variables, à mapper sur l'identité GeniusLab.

---

## 6. Synthèse : ce qu'on traduit, ce qu'on coupe

**On traduit fidèlement** : la réservation en page unique multi-machines (et non le
stepper du wireframe, abandonné — DEC-090), les formulaires par machine
(héritage de form types), la bibliothèque de composants cœur, les pages alignées
CdC, la conformité RGAA/RGPD/dyslexie.

**On coupe ou reporte** (mécanismes inutiles au périmètre) : Formations,
Certifications, Événements, GlobalSearch, GuidedTour, export PDF, autosave par champ,
moteur de formulaire générique, lib de modale tierce, stepper multi-étapes.

Le résultat : une UI fidèle à l'intention des maquettes, mais débarrassée du
gold-plating, et entièrement réalisable en Twig + Symfony UX sans front lourd.

---

## 7. Décision prédiction de stock (BF_4.3) : tranchée par recherche

Question : faut-il un historique de mouvements de stock pour prédire les ruptures ?

**Décision : non. Consommation estimée saisie par l'admin.** Les recherches sur la
gestion d'inventaire en petite structure convergent : le suivi manuel convient
quand le volume est faible (quelques dizaines d'articles, un seul site, < 50
références), les SKU stables, le mouvement lent et le budget limité : tous les
critères de GeniusLab (~14 consommables). Le seuil de bascule vers un système
automatisé de mouvements n'est pas atteint, et le principe « dépenser l'effort là
où le coût de l'erreur est élevé, laisser le reste jusqu'à ce que le volume le
justifie » s'applique directement.

Conséquence dans le code :
- `Consommable::consommationMensuelleEstimee` saisi par l'admin.
- `joursAvantRupture()` = quantité ÷ (conso/30). `niveauUrgence()` croise avec le
  délai fournisseur (rouge si la rupture précède le réappro possible).
- **Écartés pour la prédiction** (gold-plating) : sparkline d'historique, bon de
  commande PDF auto-généré.

> Mise à jour : l'entité `MouvementStock` a finalement été introduite, non pas pour
> prédire les ruptures (la prédiction reste sur la consommation estimée, décision
> inchangée), mais pour **tracer les mouvements** et alimenter la supervision et les
> exports. La prédiction et la traçabilité sont deux besoins distincts : le premier
> n'exige pas d'historique, le second oui.
