# Bibliothèque de composants UI

Catalogue des composants Twig partagés de GeniusLab. Le but est le même que le
`components/ui` d'un projet React : une source unique pour chaque brique
d'interface, afin que la cohérence visuelle soit portée par les composants et
non refaite à chaque page.

Inspiré des principes de composition d'un design system mûr (composant
présentationnel, slots optionnels, variantes sémantiques) sans en copier le
code : ici tout est Twig + CSS, sans dépendance JS pour le rendu.

## Principes transverses

Cinq règles s'appliquent à tous les composants ci-dessous.

1. **Présentationnel.** Un composant présente, il ne décide pas de logique
   métier. La couleur d'un statut, le texte d'une accroche, la cible d'un
   bouton sont décidés par l'appelant, pas par le composant.
2. **Slots optionnels.** Un paramètre absent ne produit aucun DOM. Pas de
   balise vide, pas d'espace réservé inutile. Le composant se réduit
   proprement quand on lui donne moins.
3. **Variantes sémantiques.** Quand un composant a des états (badge), on
   déclare le SENS (`succes`, `danger`) et la couleur en découle. La logique
   sens vers couleur vit dans le composant, jamais dispersée dans les pages.
4. **Intention documentée.** Chaque composant porte en tête un commentaire qui
   dit à quoi il sert, ses paramètres, et un exemple d'appel.
5. **Tokens uniquement.** Les couleurs, espacements et rayons viennent de
   `tokens.css`. Aucune valeur hexadécimale en dur dans les composants.

## Convention de nommage des paramètres

Les paramètres sont en français, cohérents d'un composant à l'autre :
`titre`, `soustitre`, `label`, `message`, `actions`. Quand un composant attend
un fragment HTML déjà rendu (boutons d'actions), on le passe via `include()`
concaténé et marqué `|raw` côté composant.

---

## badge

Pastille de statut courte. À utiliser pour un état (actif, en maintenance,
stock bas), jamais pour du texte libre.

**Appel recommandé, par variante sémantique :**

```twig
{{ include('components/badge.html.twig', {variante: 'succes', label: 'Actif'}) }}
```

| Paramètre  | Requis | Rôle |
|------------|--------|------|
| `label`    | oui    | Texte affiché dans la pastille |
| `variante` | option | Sens : `succes`, `alerte`, `danger`, `info`, `neutre`, `accent` |
| `couleur`  | option | Couleur directe (rétrocompatibilité) : `green`, `amber`, `red`, `blue`, `gray`, `slate` |

**Comportement.** Si `variante` est fournie, elle prime et la couleur est
déduite par le mapping interne. Sinon `couleur` est utilisée telle quelle.
Si rien n'est fourni, repli sur `gray`.

**Table des variantes :**

| Variante | Couleur | Usage type |
|----------|---------|------------|
| `succes` | vert    | Actif, validé, disponible, OK |
| `alerte` | ambre   | Désactivé, en maintenance, à surveiller |
| `danger` | rouge   | Stock bas, refusé, bloqué |
| `info`   | bleu    | Information neutre, type, catégorie |
| `neutre` | gris    | État par défaut, sans charge sémantique |
| `accent` | ardoise | Mise en avant discrète (image, étiquette) |

**Préférer la variante à la couleur.** Écrire `variante: 'succes'` plutôt que
`couleur: 'green'` rend l'intention lisible et centralise la décision de teinte.
Les appels par `couleur` restent valides pour ne pas casser l'existant.

---

## page_header

En-tête de page commun à tous les écrans d'administration et de gestion. Porte
le `<h1>` sémantique de la page.

```twig
{{ include('components/page_header.html.twig', {
    titre: 'Stocks',
    soustitre: 'Niveaux et seuils des consommables du FabLab.'
}) }}
```

| Paramètre   | Requis | Rôle |
|-------------|--------|------|
| `titre`     | oui    | Titre `<h1>` de la page |
| `soustitre` | option | Courte phrase de contexte sous le titre |
| `actions`   | option | Fragment HTML de boutons (déjà rendu) aligné à droite |

**Comportement.** Le sous-titre et le bloc d'actions ne sont rendus que s'ils
sont fournis. Un seul `<h1>` par page : ne pas réécrire un autre titre dans le
contenu (le header global de l'application affiche déjà ce titre en petit comme
repère).

**Passer des actions.** Les boutons sont concaténés et passés en chaîne :

```twig
{{ include('components/page_header.html.twig', {
    titre: 'Stocks',
    actions:
        '<a href="' ~ path('admin_stock_predictions') ~ '" class="btn btn--ghost">Prédictions</a>' ~
        '<a href="' ~ path('admin_stock_new') ~ '" class="btn btn--primary">'
        ~ include('components/icon.html.twig', {name: 'plus', size: 16}) ~ ' Ajouter</a>'
}) }}
```

---

## empty

État vide d'une liste : une invitation à agir, pas un cul-de-sac. À afficher
quand une collection est vide.

```twig
{{ include('components/empty.html.twig', {
    message: 'Aucun article en stock pour le moment.',
    action_label: 'Ajouter le premier',
    action_path: path('admin_stock_new')
}) }}
```

| Paramètre      | Requis | Rôle |
|----------------|--------|------|
| `message`      | oui    | Phrase expliquant l'absence de contenu |
| `action_label` | option | Libellé du bouton d'action principal |
| `action_path`  | option | Cible du bouton (URL via `path()`) |

**Comportement.** Le bouton n'est rendu que si `action_path` est fourni. Un
état vide sans action est légitime (page en lecture seule) ; avec action, il
oriente l'utilisateur vers la première étape.

**Note de cohérence.** Ce composant nomme son texte `message` là où
`page_header` nomme le sien `titre`. Les deux coexistent pour des raisons
historiques ; à terme on pourrait aligner sur `titre`/`soustitre`.

---

## icon

Icône SVG inline, style lucide (trait fin, 24x24, `currentColor`). Aucune
dépendance JS : les chemins sont définis dans le composant et héritent de la
couleur du texte parent.

```twig
{{ include('components/icon.html.twig', {name: 'home'}) }}
{{ include('components/icon.html.twig', {name: 'plus', size: 16}) }}
```

| Paramètre | Requis | Rôle |
|-----------|--------|------|
| `name`    | oui    | Nom de l'icône (voir liste) |
| `size`    | option | Taille en pixels (défaut 24) |

**Comportement.** L'icône hérite de la couleur du texte parent (`currentColor`),
donc elle s'adapte au contexte sans réglage. Pour une icône purement décorative
à côté d'un texte, c'est le comportement attendu.

**Icônes disponibles.** Pour en ajouter une, insérer son tracé SVG (chemins
lucide) dans le dictionnaire `paths` du composant.

```
home, arrowLeft, clock, search, image, zap, clipboard, printer, calendar,
bell, book, award, barChart, pieChart, fileText, archive, ban, layout, pkg,
wrench, shield, settings, helpCircle, eye, logout, user, plus, menu,
chevron-left, chevron-right, minus, x
```

---

## stepper

Saisie d'une petite quantité par deux boutons « moins » et « plus » encadrant
une valeur, sans clavier. Pilote un champ réel (souvent masqué) qui porte la
valeur soumise. Unifie le comptage de petites valeurs à travers le logiciel
(quantité d'une demande, nombre de personnes d'un créneau).

```twig
{{ include('components/stepper.html.twig', {
    input_html: form_widget(form.quantite),
    label_moins: 'Une unité de moins',
    label_plus: 'Une unité de plus',
    depart: form.quantite.vars.value|default(1)
}) }}
```

| Paramètre     | Requis | Rôle |
|---------------|--------|------|
| `input_html`  | oui    | Balisage de l'input réel (porte `data-stepper-target="champ"`) |
| `label_moins` | option | Libellé accessible du bouton « moins » |
| `label_plus`  | option | Libellé accessible du bouton « plus » |
| `depart`      | option | Valeur affichée au départ si l'input est vide (défaut 1) |

**Comportement.** Le contrôleur Stimulus `stepper` lit les bornes (`min`, `max`)
sur l'input réel et reste la source de vérité de la valeur soumise. L'input est
masqué par CSS mais conservé dans le document. Même classe visuelle que le
stepper de la réservation : apparence et comportement identiques partout.

---

## upload

Zone de glisser-déposer pour l'import de fichiers, cliquable et accessible au
clavier, avec liste des fichiers retenus et retrait individuel. Masque l'input
natif et synchronise la sélection. Utilisé au dépôt d'un projet et à l'ajout de
plans en modification.

```twig
{{ include('components/upload.html.twig', {
    input_html: form_widget(form.plansFiles),
    label: 'Plans / fichiers (optionnel)',
    formats: 'STL, OBJ, PDF, SVG, ZIP, JPEG, PNG, WebP · 10 fichiers max, 25 Mo chacun, 80 Mo au total'
}) }}
```

| Paramètre    | Requis | Rôle |
|--------------|--------|------|
| `input_html` | oui    | Balisage de l'input fichier réel (masqué, conservé) |
| `label`      | option | Libellé de la zone |
| `formats`    | option | Rappel des formats et limites acceptés |

**Comportement.** Les limites client (nombre, taille) reflètent les contraintes
serveur, qui restent la vraie barrière. Un refus est signalé par un message
agrégé par motif, pas une ligne par fichier.

---

## data_table

Coquille unique pour toutes les listes : barre d'outils (recherche, filtres
optionnels), conteneur à ascenseur avec en-tête figé, état « aucun résultat »,
tri par colonne et pagination. S'emploie avec `embed` (pas `include`), car la
page fournit ses colonnes et lignes via deux blocs.

```twig
{% embed 'components/data_table.html.twig' with {
    recherche_placeholder: 'Rechercher un projet…',
    recherche_label: 'Rechercher un projet',
    filtres: chips
} %}
    {% block entetes %}{# th, certains avec data-sort-key #}{% endblock %}
    {% block lignes %}{# tr avec data-datatable-target="row" #}{% endblock %}
{% endembed %}
```

| Paramètre               | Requis | Rôle |
|-------------------------|--------|------|
| `recherche_placeholder` | oui    | Invite du champ de recherche |
| `recherche_label`       | oui    | Libellé accessible du champ |
| `vide_message`          | option | Texte si le filtre ne renvoie rien |
| `filtres`               | option | Balisage des chips de filtre (déjà rendu) |

**Comportement.** Le contrôleur Stimulus `datatable` prend en charge recherche,
tri, filtre par catégorie et pagination côté client : aucune logique à dupliquer
dans la page. Adapté au volume modeste d'un FabLab ; pour des centaines de lignes
on bascule sur un tri et une pagination serveur (voir la liste des membres).

---

## graphe (courbe d'activité)

Courbe d'évolution mensuelle interactive, rendue en SVG par le contrôleur
Stimulus `graphe`, sans dépendance. Sert les graphiques temporels de la page
Activité (réservations, niveau de stock). Le partiel `admin/supervision/_courbe.html.twig`
fournit la coquille ; le contrôleur dessine et gère les interactions.

```twig
{{ include('admin/supervision/_courbe.html.twig', {
    series: [{cle: 'actuelle', label: 2026, couleur: '#1d4e6f', data: [...], visible: true}],
    mois: ['Jan', 'Fév', ...],
    max: 14,
    grad: [14, 7, 0],
    aria: 'Réservations par mois',
    comparables: {'2025': [...], '2024': [...]}
}) }}
```

| Paramètre        | Requis | Rôle |
|------------------|--------|------|
| `series`         | oui    | Liste de `{cle, label, couleur, data, visible}` |
| `mois`           | oui    | Libellés courts de l'axe X |
| `max`            | oui    | Borne haute de l'axe Y |
| `grad`           | oui    | Trois repères d'axe Y (haut, milieu, bas) |
| `aria`           | option | Description accessible du graphe |
| `comparables`    | option | Dict `année → data` pour le menu « comparer à » |
| `legende_visible`| option | Affiche la légende cliquable (défaut vrai) |

**Comportement.** Survol croisé (curseur vertical + infobulle unique pour toutes
les séries visibles), zones de survol larges (toute la colonne du mois) pour une
visée facile, légende cliquable pour masquer une série. Si `comparables` est
fourni, un menu « comparer à » permet de choisir librement l'année superposée,
basculée côté client sans rechargement. Une série sans données (année vide) est
masquée d'office, série et entrée de légende comprises : on n'affiche jamais une
courbe plate trompeuse. L'échelle Y doit être fixée d'avance sur le maximum de
toutes les années comparables pour rester stable quel que soit le choix.

---



Le pattern de composition, repris de la pratique d'un design system mûr : la
page assemble des composants et gère l'espacement, elle ne fait pas de
présentation elle-même.

```twig
{% extends 'base.html.twig' %}
{% block title %}Stocks{% endblock %}
{% block body %}
    {{ include('components/page_header.html.twig', {
        titre: 'Stocks',
        soustitre: 'Niveaux et seuils des consommables du FabLab.'
    }) }}

    {% if articles is empty %}
        {{ include('components/empty.html.twig', {
            message: 'Aucun article en stock.',
            action_label: 'Ajouter le premier',
            action_path: path('admin_stock_new')
        }) }}
    {% else %}
        {# tableau, cartes, etc. #}
    {% endif %}
{% endblock %}
```

La hiérarchie visuelle (titre dominant, sous-titre discret, état vide centré,
badges sémantiques) est portée par les composants. Une nouvelle page qui les
réutilise est cohérente sans effort supplémentaire.
