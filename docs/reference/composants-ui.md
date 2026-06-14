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

## Composer une page

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
