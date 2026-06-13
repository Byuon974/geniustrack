# Patterns de code : GeniusLab

Ce document recense les patrons de conception récurrents du code, transverses aux fonctionnalités. Chaque pattern est présenté par son intention, son implémentation réelle dans le projet, et la justification qui l'a fait choisir. Il sert de référence pour comprendre comment le code est écrit, et pourquoi de cette façon.

Les documents de famille (réservation, stock, sanctions) renvoient à ces patterns plutôt que de les réexpliquer.

---

## 1. Verrou pessimiste pour la concurrence d'écriture

### Intention

Empêcher deux écritures concurrentes de franchir ensemble un plafond (la capacité de 15 personnes). Sans protection, le scénario classique de la mise à jour perdue se produit : deux requêtes lisent la même capacité disponible, décident toutes deux que la place existe, écrivent toutes deux, et le plafond est dépassé.

### Implémentation

Dans `ReservationService::creerSession()`, la création s'effectue dans une transaction qui verrouille la machine avant de lire la capacité du créneau :

```php
$this->em->lock($machine, LockMode::PESSIMISTIC_WRITE);
// ... re-vérification de la disponibilité machine sous verrou ...
$dejaPresents = $this->reservations->sommePersonnesSurCreneau($debut, $fin, verrouiller: true);
if ($dejaPresents + $nbPersonnes > Reservation::CAPACITE_MAX_FABLAB) {
    throw new ReservationImpossibleException(/* places restantes */);
}
```

Le repository propage le verrou jusqu'à la requête de comptage via un paramètre `verrouiller`. Le calcul du chevauchement de créneaux utilise la règle des intervalles semi-ouverts (début avant fin et fin après début).

### Justification (RETEX)

Pour un système de réservation à forte contention, le verrou pessimiste est le choix adapté : il garantit un accès exclusif et prévient le conflit avant qu'il survienne, là où le verrou optimiste (colonnes de version, détection au commit) conviendrait à des conflits rares avec retentatives bon marché. Le pic d'ouverture des créneaux est précisément un cas de conflits fréquents.

Le verrou porte sur la ligne machine, pas sur la table : c'est l'application de la règle « ne verrouiller que ce qui est nécessaire, préférer un verrou de ligne à un verrou de table pour réduire la portée ». La machine est la ligne stable qui sérialise les écritures concurrentes sur ses créneaux.

> Ce pattern est la raison d'être du choix PostgreSQL (DEC-002) : SQLite n'autorise qu'un seul écrivain global, ce qui transforme le verrou ciblé en goulot pour toute la base.

---

## 2. Machine à états par le composant Workflow

### Intention

Garantir qu'un projet ne change de statut que par des transitions légales, sans confier cette cohérence à la discipline des développeurs.

### Implémentation

`ProjetWorkflowService` est le seul point qui fait avancer un projet. Il s'appuie sur le composant Workflow de Symfony, configuré en machine à états dans `config`. Aucun contrôleur n'appelle `setStatut()` directement.

```php
public function valider(Projet $projet, User $valideur): void
{
    if (!$this->security->isGranted($roleRequis)) {
        throw new ReservationImpossibleException(/* ... */);
    }
    $projet->setValideur($valideur);
    $this->appliquer($projet, 'valider');   // délègue au Workflow
}
```

Une transition interdite (par exemple brouillon vers terminé) est refusée par le framework, pas par une condition écrite à la main.

### Justification

La cohérence de la machine à états est garantie structurellement. Centraliser les transitions dans un service unique élimine la dispersion des `setStatut()` dans les contrôleurs, source classique d'états incohérents.

---

## 3. Observateurs sur la machine à états (listeners workflow)

### Intention

Réagir à une transition de projet (tracer, notifier) sans coupler ces effets à la logique de transition elle-même.

### Implémentation

Deux listeners écoutent le même événement `workflow.projet.entered`, déclarés par attribut :

```php
#[AsEventListener(event: 'workflow.projet.entered')]
class ProjetJournalListener
{
    public function __invoke(EnteredEvent $event): void
    {
        $projet = $event->getSubject();
        $action = match ($projet->getStatut()) {
            ProjetStatut::Valide => 'Projet validé',
            ProjetStatut::Refuse => 'Projet refusé',
            // ...
        };
        // journalise l'action métier
    }
}
```

`ProjetJournalListener` trace l'action métier (BF_8.1), `ProjetNotificationListener` envoie mail et notification in-app (DEC-029). Les deux sont déclenchés par la transition, pas appelés explicitement par le service.

### Justification

Le service de workflow ne connaît ni le journal ni les notifications : il fait avancer l'état, et les observateurs réagissent. Ajouter un effet de bord (un nouveau type de notification) se fait en ajoutant un listener, sans toucher au service. C'est le découplage propre du pattern observateur, appliqué à la machine à états.

> Ces listeners écoutent un événement de Workflow, pas un événement Doctrine : flusher l'EntityManager à l'intérieur est sans danger (pas de récursion de cycle de persistance).

---

## 4. Modèle ledger et dérivation d'état

### Intention

Conserver l'historique complet d'un fait (qui, quand, pourquoi) plutôt qu'un compteur sans mémoire, tout en exposant une API simple aux vues.

### Implémentation

Les sanctions sont une table append-only. Chaque sanction est une ligne immuable ; l'état (le nombre de sanctions actives) se dérive en comptant les lignes non levées, il n'est jamais stocké :

```php
public function getNbSanctions(): int
{
    return $this->sanctions
        ->filter(static fn (Sanction $s): bool => $s->estActive())
        ->count();
}
```

Le même principe régit les notifications (indicateur `luLe` nullable, comptage des non-lues dérivé). Une sanction levée est horodatée, jamais supprimée.

### Justification

Un compteur perd l'historique. Le pattern ledger (table append-only dont on dérive l'état) capture chaque événement en restant lisible, sans la complexité de l'event sourcing complet qui contaminerait toute l'architecture (DEC-028). L'API historique (`getNbSanctions()`) est préservée pour les vues, mais elle calcule au lieu de lire un champ figé.

> Cohérence en mémoire : quand le service crée une sanction, il l'ajoute aussi à la collection de l'étudiant (`ajouterSanction`), pour que la dérivation reflète l'état sans recharger l'entité.

---

## 5. Voter pour l'autorisation fine

### Intention

Décider si un utilisateur peut agir sur une ressource précise (son projet), sans disperser la règle dans les contrôleurs.

### Implémentation

`ProjetVoter` répond à l'attribut `PROJET_EDIT` sur un sujet `Projet` :

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
{
    $user = $token->getUser();
    if (!$user instanceof User) {
        return false;
    }
    // Le propriétaire, ou un admin, peut éditer.
    return $subject->getEtudiant() === $user
        || \in_array('ROLE_ADMIN', $user->getRoles(), true);
}
```

Les contrôleurs délèguent par `denyAccessUnlessGranted('PROJET_EDIT', $projet)`.

### Justification

La règle de propriété vit en un seul endroit, testable et réutilisable. Le contrôleur ne sait pas comment l'autorisation est décidée, il demande seulement si elle est accordée.

> Distinction avec la hiérarchie des rôles : le voter tranche la propriété d'une ressource (ce projet est-il le mien), tandis que `isGranted('ROLE_X')` tranche l'habilitation par rôle, qui respecte la hiérarchie (DEC-033). Les deux mécanismes se complètent.

---

## 6. Extension Twig mémoïsée pour les vues

### Intention

Exposer une donnée transversale (le compteur de notifications non lues) à toutes les vues, sans la requêter dans chaque contrôleur.

### Implémentation

`NotificationExtension` fournit une fonction Twig `notifications_non_lues()`, évaluée une seule fois par requête grâce à un cache local :

```php
public function compterNonLues(): int
{
    if (null !== $this->cache) {
        return $this->cache;
    }
    $user = $this->security->getUser();
    $this->cache = $user instanceof User
        ? $this->notifications->compterNonLues($user)
        : 0;
    return $this->cache;
}
```

Le gabarit `base.html.twig` appelle cette fonction pour afficher le badge.

### Justification

Sans cette extension, chaque contrôleur devrait passer le compteur au template, ou un sous-requête se déclencherait à chaque rendu. La fonction Twig centralise l'accès, la mémoïsation évite la requête répétée dans une même page.

---

## 7. Enums à comportement métier

### Intention

Faire porter à une énumération non seulement ses valeurs, mais les dérivations métier qui en dépendent, pour éviter de disperser des `match` dans le code.

### Implémentation

`ProjetType` porte la dérivation du rôle valideur et du libellé :

```php
public function roleValideur(): string
{
    return match ($this) {
        self::Pedagogique => 'ROLE_FORMATEUR',
        self::Personnel => 'ROLE_BDE',
    };
}
```

De même, `ProjetStatut::couleur()` porte la couleur sémantique du statut, `MachineEtat` porte son libellé et sa réservabilité.

### Justification

La règle « pédagogique se valide par formateur » vit dans l'enum, pas dans le service de workflow ni dans un contrôleur. Quand un nouveau type apparaît, le compilateur signale tout `match` non exhaustif à compléter : la dérivation est sûre et centralisée.

---

## 8. Validation custom d'un fichier uploadé

### Intention
Valider une propriété d'un fichier que les contraintes natives ne couvrent pas, en inspectant son contenu réel plutôt qu'une métadonnée déclarée.

### Implémentation
Une paire contrainte + validateur dans `src/Validator/` : la contrainte (`ArchiveSaine`) porte les seuils et les messages, le validateur (`ArchiveSaineValidator`) lit le fichier et émet une violation si un seuil est dépassé. Symfony lie l'un à l'autre par convention de nom et autodécouvre le validateur (autoconfigure sur `src/`). La contrainte se branche dans le formulaire à côté des contraintes natives. Exemple : `ArchiveSaine` inspecte le catalogue d'un zip (taille décompressée et ratio par entrée) avant extraction, pour rejeter une bombe de décompression.

### Justification
Les contraintes natives (`File`, `Image`) valident le type et la taille, mais pas le contenu interne d'une archive ni le coût de décompression. Un validateur custom encapsule cette logique une fois, de façon réutilisable et testable, plutôt que de la disperser dans les contrôleurs.

---

## Synthèse : où vit quoi

```
Préoccupation                  Pattern                    Emplacement
──────────────────────         ────────────────────       ──────────────────────
Concurrence d'écriture         verrou pessimiste          ReservationService
Cohérence des transitions      machine à états Workflow    ProjetWorkflowService
Effets de transition           observateurs (listeners)    src/EventListener
Historique sans perte          ledger + dérivation         Sanction, Notification
Autorisation par ressource     voter                       ProjetVoter
Autorisation par rôle          hiérarchie + isGranted      config/security
Donnée transverse aux vues     extension Twig mémoïsée      NotificationExtension
Dérivation métier d'un type    enum à comportement         src/Enum
Validation fichier sur mesure  contrainte + validateur     src/Validator
```
