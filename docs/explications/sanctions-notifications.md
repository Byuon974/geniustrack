# Sanctions et notifications

Ces deux familles relèvent de la gestion des utilisateurs (BF_6.2) et de la communication (BF_3.6). Elles partagent une mécanique commune décrite dans `patterns-code.md` : le modèle ledger avec dérivation d'état. Ce document décrit leurs règles propres.

---

## 1. Sanctions

### Modèle mental

Une sanction est une ligne immuable : étudiant, motif, auteur (null si automatique), date de création, date de levée (`leveeLe` nullable). Une sanction est active tant qu'elle n'est pas levée. Le nombre de sanctions actives se dérive en comptant les lignes actives, il n'est jamais stocké dans un compteur.

> Lever une sanction l'horodate, ne la supprime pas. L'historique reste complet : qui a sanctionné, quand, pour quel motif, et qui a levé. Supprimer la ligne effacerait cette trace.

### Le seuil de désactivation

`SanctionService::sanctionner()` ajoute une sanction et, si le nombre de sanctions actives atteint `SEUIL_DESACTIVATION` (5), désactive le compte de l'étudiant :

```php
public function sanctionner(User $etudiant, string $motif, ?User $auteur = null): bool
{
    if ($this->estStaff($etudiant)) {
        return false;   // le staff n'est jamais sanctionné (DEC-031)
    }
    // ... ajout de la sanction ...
    if ($actives >= self::SEUIL_DESACTIVATION && $etudiant->estActif()) {
        $etudiant->setActif(false);   // désactivation au seuil
    }
}
```

La levée horodate la sanction sans la supprimer. Elle ne réactive PAS le compte, même si l'étudiant repasse sous le seuil : la réactivation est un geste admin distinct et délibéré (édition du compte, ou `make activer EMAIL=...`).

### Garde-fous (DEC-031, DEC-032)

> Le staff (admin, formateur, BDE) n'est jamais sanctionné : la garde est en tête de `sanctionner()`, ce qui protège tous les points d'appel. Sans elle, un admin annulant tardivement sa propre réservation pourrait s'auto-désactiver et verrouiller l'administration.

La désactivation manuelle en masse ignore aussi les comptes admin (DEC-032). Ces deux règles ferment les portes par lesquelles l'administration pourrait se verrouiller elle-même.

### Lien avec la réservation

Le service de réservation détecte qu'une annulation ou un report est tardif (moins de trois jours), mais n'applique pas la sanction : il renvoie le signal, et l'appelant invoque le service de sanction. La séparation est nette : la réservation détecte, la sanction pénalise.

---

## 2. Notifications

### Modèle mental

Une notification persiste destinataire, type, message, lien interne optionnel, date de création et `luLe` nullable. Le `luLe` nullable sert d'indicateur lu/non-lu tout en gardant la date de lecture (schéma de type Laravel, DEC-029).

> Une notification in-app est créée en plus du mail, seulement quand le destinataire est un compte identifié. Une alerte qui vise une adresse d'admin (stock bas) reste un mail seul.

### Les opérations du repository

```
Méthode                    Effet
─────────────────────      ─────────────────────────────────────────
compterNonLues             nombre de notifications non lues (badge)
recentes(limite=30)        les notifications récentes du destinataire
marquerToutesLues          horodate toutes les non-lues à l'ouverture
purgerLuesAnciennes(60)    supprime les notifications lues de plus de 60 j
```

### Marquage lu à l'ouverture (DEC-029)

Ouvrir le centre de notifications appelle `marquerToutesLues` : toutes les non-lues passent à lu, le badge retombe à zéro. C'est le comportement attendu d'un centre de notifications (l'ouverture vaut lecture).

### Le badge exposé à toutes les vues

Le compteur de non-lues est exposé à tous les gabarits par une extension Twig mémoïsée (`NotificationExtension`, voir `patterns-code.md` section 6), pour éviter de le requêter dans chaque contrôleur et de le recalculer à chaque rendu.

### Purge et rétention

`purgerLuesAnciennes` supprime les notifications lues de plus de 60 jours. Les non-lues ne sont jamais purgées (elles attendent leur lecture). Cette purge garde la table bornée sans perdre d'information utile.

---

## Points clés à retenir

- Sanctions et notifications dérivent leur état des lignes (ledger), elles ne stockent pas de compteur figé.
- Le staff n'est jamais sanctionnable, un admin n'est jamais désactivable : garde-fous inscrits dans les services, valables pour tous les points d'appel.
- La désactivation se déclenche à 5 sanctions actives ; lever une sanction ne réactive pas le compte (réactivation = geste admin explicite).
- Notification in-app uniquement pour un destinataire identifié : une alerte vers une adresse d'admin reste un mail.
- Ouvrir le centre vaut lecture : `marquerToutesLues` est appelé à l'ouverture, le badge retombe à zéro.
- Seules les notifications lues de plus de 60 jours sont purgées : aucune non-lue n'est jamais supprimée.
