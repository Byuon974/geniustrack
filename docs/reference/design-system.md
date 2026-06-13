# Design system : GeniusLab

Ce document décrit le système visuel tel qu'il est : ses tokens, ses composants et ses règles d'usage. Il sert de référence pour construire un nouvel écran cohérent. L'histoire des décisions visuelles (pourquoi ces choix, comment ils ont évolué) est dans `../explications/ui-iteration.md`.

Principe fondateur : aucune valeur visuelle en dur. Couleurs, espacements, rayons et typographies viennent tous de `tokens.css`. Un composant qui écrit une couleur hexadécimale en dur est une violation (DEC-020).

---

## 1. Couleur

La couleur encode du sens, elle n'est pas décorative (DEC-024). Chaque teinte a un rôle.

```
Token              Valeur      Rôle
─────────────      ───────     ─────────────────────────────────────
gl-blueprint       #1d4e6f     structure : navigation, en-têtes, primaire
gl-filament-ink    #b34e14     accent : bouton primaire, liens, actifs
gl-green           #2f8a5b     succès : validé, terminé, disponible, valider
gl-amber           #c9821a     attention : en attente, stock bas
gl-red             #c0392b     erreur, destructif : refusé, sanction, supprimer
gl-blue            #2a6da3     information neutre
gl-slate           #41576a     accent secondaire sobre
```

Chaque couleur sémantique a une variante douce (suffixe `-soft`) pour les fonds discrets (badge, filet de carte). Les neutres chauds portent l'interface :

```
Token           Valeur      Usage
─────────────   ───────     ──────────────────────────────────
gl-surface      #fbfaf8     cartes, tables : blanc réchauffé
gl-bg           #f3f1ec     fond général : sable doux, usage prolongé
gl-line         #e3e0d8     bordures, séparateurs
gl-ink          #14181c     texte principal : quasi-noir teinté
gl-ink-soft     #5c6670     texte secondaire
```

Le choix du neutre chaud (sable, pierre) plutôt qu'un blanc corporate froid est délibéré : moins clinique, plus reposant sur un usage prolongé. Cadre d'équilibre 60-30-10 : neutre dominant, bleu de structure, accent vibrant réservé à l'actionnable.

> Les variantes assombries (`gl-filament-ink`, `gl-filament-light`) existent pour garantir le contraste AA selon le fond : `gl-filament-ink` pour du texte ou un bouton sur fond clair, `gl-filament-light` sur fond sombre (bannière, barre latérale).

---

## 2. Typographie

```
Token             Police              Usage
─────────────     ───────────────     ──────────────────────
gl-font-display   Space Grotesk       titres, chiffres de stat
gl-font-body      Inter               texte courant
gl-font-mono      JetBrains Mono      valeurs techniques, code
```

Échelle de taille de `gl-text-xs` (0.75rem) à `gl-text-2xl` (2rem). En mode dyslexie, les deux polices d'affichage et de corps basculent sur OpenDyslexic (voir section accessibilité).

---

## 3. Espacement, rayon, ombre

```
Famille       Tokens disponibles
──────────    ────────────────────────────────────────────
espacement    gl-space-1 (0.25rem) à gl-space-8 (2rem)
rayon         gl-radius-sm (5px), gl-radius (8px), gl-radius-lg (10px)
ombre         gl-shadow (discrète), gl-shadow-lg (modale, survol)
```

> Tokens qui n'existent pas, à ne pas inventer : `gl-radius-md`, `gl-space-5`, `gl-space-7`. Un écran qui les référence ne se rendra pas comme prévu.

---

## 4. Composants

### Composants Twig

```
Composant              Rôle
────────────────       ───────────────────────────────────────
badge                  pastille de statut, pilotée par variante sémantique
data_table             table de données avec en-têtes, tri, lignes
empty                  état vide (message quand aucune donnée)
icon                   icône SVG inline, taille paramétrable
page_header            en-tête de page : titre, sous-titre, actions
```

Le badge se déclare par variante (`succes`, `alerte`, `danger`, `info`, `neutre`), pas par couleur (DEC-021) : la teinte est déduite, l'intention reste lisible.

### Comportements Stimulus

```
Contrôleur                 Comportement
───────────────────        ──────────────────────────────────────
confirm                    modale de confirmation maison, FR (DEC-026)
batch_selection            sélection multiple pour actions groupées
creneaux_collection        ajout/retrait dynamique de créneaux
datatable                  tri et filtrage de table côté client
gallery_filter             filtrage de la galerie de projets
lisibilite                 bascule taille de texte et police dyslexie
shell                      comportement de la coquille (navigation)
```

---

## 5. Accessibilité (DEC-018)

L'accessibilité est une contrainte de base, cible WCAG 2.1 AA et RGAA 4.1.2. Concrètement dans le système :

- Contrastes : les variantes assombries des couleurs d'accent (`-ink`) garantissent le ratio AA sur leur fond.
- Police dyslexie : le contrôleur `lisibilite` bascule l'interface en OpenDyslexic et ajuste la taille de texte, accessible depuis chaque page (boutons « Lisibilité » et « Police dyslexie »).
- Modale accessible : la confirmation utilise l'élément `<dialog>` natif (focus géré, touche Échap, clic sur le fond), pas une div custom (DEC-026).
- Champs numériques : `inputMode` plutôt que `type="number"` (DEC-023), pour éviter les compteurs natifs mal gérés.
- Champ fichier : restylé via `::file-selector-button`, sans toucher au comportement natif (DEC-027).

---

## 6. Règles d'usage pour un nouvel écran

- Partir des composants existants (page_header, data_table, badge, empty) avant d'en créer un nouveau.
- N'utiliser que des tokens : aucune couleur, taille ou rayon en dur.
- Choisir la couleur par son sens : vert pour ce qui réussit, ambre pour ce qui attend, rouge pour ce qui détruit, terracotta pour l'action primaire, bleu pour la structure.
- Préserver les valeurs métier : un composant partagé porte la structure, jamais le vocabulaire CGSS, les formats 974 ou les tris contextuels (DEC-022).
- Vérifier le contraste et la navigation clavier avant de considérer l'écran terminé.
