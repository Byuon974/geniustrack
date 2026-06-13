# Stock : gestion et prédiction de rupture

La gestion du stock (famille BF_4.x) couvre l'inventaire des consommables, l'alerte de seuil bas et la prédiction de rupture. Ce document décrit ses règles et l'algorithme de prédiction. Les patterns transverses (dérivation d'état, enums) sont décrits dans `patterns-code.md`.

---

## 1. Modèle mental

Un consommable porte une quantité, un seuil minimal, une unité, un délai fournisseur (jours pour être réapprovisionné) et une consommation mensuelle estimée. La logique de prédiction vit dans l'entité elle-même (méthodes `joursAvantRupture`, `estSousSeuil`, `niveauUrgence`), pas dans le contrôleur.

> La consommation est saisie par l'admin, pas mesurée automatiquement. C'est une donnée d'entrée de la prédiction, pas un calcul : sans elle, aucune prédiction n'est possible (DEC-014).

---

## 2. Alerte de seuil bas (BF_4.4)

Un article est sous le seuil quand sa quantité est inférieure ou égale au seuil minimal :

```php
public function estSousSeuil(): bool
{
    return $this->quantite <= $this->seuilMinimal;
}
```

Le tableau de bord et l'écran de stock affichent le nombre d'articles sous le seuil. Une notification mail alerte l'admin (le destinataire est une adresse d'admin, pas un compte : l'alerte reste un mail, sans notification in-app).

---

## 3. Prédiction de rupture (BF_4.3, DEC-014)

### L'algorithme

La prédiction est volontairement simple : jours restants égale stock divisé par consommation journalière. Elle renvoie null si aucune consommation n'est estimée.

```php
public function joursAvantRupture(): ?int
{
    if ($this->consommationMensuelleEstimee <= 0) {
        return null;   // pas de prédiction sans donnée d'entrée
    }
    $consoParJour = $this->consommationMensuelleEstimee / 30;
    return (int) floor($this->quantite / $consoParJour);
}
```

### Le niveau d'urgence croise le délai fournisseur

La pastille d'urgence ne regarde pas seulement les jours restants : elle les compare au délai de réapprovisionnement. Si la rupture arrive avant qu'une commande puisse être livrée, l'urgence est maximale (rouge), parce qu'il est déjà trop tard pour commander à temps.

```
Situation                                          Urgence
────────────────────────────────────────          ───────
rupture après le délai fournisseur                 vert
rupture proche du délai fournisseur                orange
rupture avant que la commande soit livrée          rouge
```

### Justification du choix (DEC-014)

Un modèle de prévision statistique (série temporelle) exige un historique de consommation dont le FabLab ne dispose pas au démarrage. Un modèle non alimenté produit des prédictions fausses, pires que pas de prédiction. L'algorithme simple (division stock sur consommation) est honnête sur son hypothèse : il dit explicitement « rien à prédire » quand la consommation n'est pas saisie, au lieu d'inventer un chiffre.

---

## 4. Présentation : séparer le signal du bruit

L'écran de prédiction sépare deux groupes (RETEX divulgation progressive) :

- Les articles à risque (consommation renseignée, rupture calculable) en haut, triés par urgence croissante.
- Les articles sans consommation renseignée, repliés dans un bloc dépliable, avec une invite à renseigner la donnée.

```php
if (null === $article->joursAvantRupture()) {
    $sansConsommation[] = $article;     // repli, invite à compléter
} else {
    $avecPrediction[] = $article;       // liste actionnable, triée par urgence
}
usort($avecPrediction, static fn ($a, $b) => $a->joursAvantRupture() <=> $b->joursAvantRupture());
```

L'écran montre d'abord l'actionnable, pas une longue liste indifférenciée. Un article sans consommation n'est pas une erreur, c'est une invitation à compléter la donnée qui rendra la prédiction possible.

---

## Points clés à retenir

- La consommation est une donnée saisie, pas mesurée : sans elle, `joursAvantRupture` renvoie null, et l'article bascule dans la liste « à compléter ».
- Le niveau d'urgence croise toujours le délai fournisseur : une rupture lointaine en valeur absolue peut être rouge si le réapprovisionnement est lent.
- La prédiction reste un algorithme simple et honnête (DEC-014) : pas de modèle statistique sans historique pour l'alimenter.
- La logique de stock vit dans l'entité `Consommable` : le contrôleur trie et présente, il ne calcule pas.
