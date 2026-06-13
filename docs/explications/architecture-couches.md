# Architecture : monolithe modulaire en couches

GeniusLab est un **monolithe**, et c'est un choix assumé, pas un défaut. Pour un
périmètre interne (un campus, ~30 fonctionnalités, une équipe), un monolithe bien
structuré est plus simple à développer, déployer, déboguer et soutenir devant un
jury qu'une architecture distribuée. La discipline ne porte pas sur le découpage
en services réseau, mais sur la **séparation stricte des couches** à l'intérieur
du monolithe. C'est ce qu'on appelle un *monolithe modulaire*.

## Les couches (du haut vers le bas)

```
┌─────────────────────────────────────────────┐
│  Controller        (src/Controller)          │  ← reçoit la requête HTTP,
│  « mince »                                    │    rend la réponse. ZÉRO règle métier.
├─────────────────────────────────────────────┤
│  Form + DTO        (src/Form, src/Dto)        │  ← validation des entrées,
│                                               │    découplage formulaire ↔ entité.
├─────────────────────────────────────────────┤
│  Service           (src/Service)              │  ← TOUTE la logique métier :
│  « cœur »                                     │    capacité, quota, workflow, transaction.
├─────────────────────────────────────────────┤
│  Repository        (src/Repository)           │  ← accès aux données, requêtes,
│                                               │    verrous. Pas de règle métier.
├─────────────────────────────────────────────┤
│  Entity + Enum     (src/Entity, src/Enum)     │  ← le modèle de données et les
│                                               │    invariants portés par le domaine.
└─────────────────────────────────────────────┘
```

## Règle de dépendance

Chaque couche ne connaît que celle directement en dessous. Le controller appelle
un service ; le service appelle des repositories et manipule des entités ; le
repository parle à la base. **Jamais l'inverse**, et jamais de saut de couche
(un controller ne fait pas de requête Doctrine directement).

## Pourquoi le métier est dans les Services, pas les Controllers

Exemple concret du Lot 3 : la règle « 15 personnes maximum » (BF_3.9).

- **Mauvais** : la vérification dans le controller. Elle serait dupliquée à chaque
  point d'entrée (wizard web, future API, commande d'import) et impossible à
  tester sans simuler une requête HTTP.
- **Bon (retenu)** : la règle vit dans `ReservationService::creerSession()`, dans
  une transaction avec verrou pessimiste. Le controller délègue. Le test
  `ReservationServiceTest` la vérifie sans HTTP, sur SQLite en mémoire.

## Le composant Workflow comme garde-fou de la machine à états

Le statut d'un projet ne se modifie jamais « à la main » (`setStatut`) dans un
controller. Il passe par `ProjetWorkflowService`, qui s'appuie sur le composant
Workflow de Symfony. Toute transition illégale (ex. `brouillon → terminé`) est
refusée par le framework. La cohérence de la machine à états est ainsi **garantie
structurellement**, pas par convention.

## Découpage par domaine fonctionnel

À l'intérieur des couches, le code est groupé par domaine (réservation, projet,
stock, machine…), pas par type technique fourre-tout. Quand un domaine grossit,
on peut le ranger dans un sous-namespace (`Service\Reservation\…`) sans rien
casser. C'est la trajectoire d'évolution propre du monolithe modulaire : on
modularise à l'intérieur avant d'envisager (si un jour c'est justifié, ce qui est
peu probable ici) d'extraire un service réseau.
