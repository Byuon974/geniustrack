# Conception de la supervision du laboratoire

Document de travail : recherches, RETEX et décisions de conception avant codage. La maquette associée est `maquette-supervision-labo.html`.

## 1. Le besoin

Le tableau de bord existant répond à l'action immédiate : demandes à traiter, stock sous le seuil, activité du jour. Il restait un besoin distinct : lire l'évolution du laboratoire dans le temps, pour décider des investissements et de l'approvisionnement. Trois axes ont été retenus : l'activité de réservation, le taux d'utilisation des machines, et les fluctuations de consommables.

## 2. Ce que dit le RETEX (sources 2025-2026)

La recherche sur les tableaux de bord distingue trois familles selon l'usage. Le tableau opérationnel sert le suivi quotidien et l'action immédiate. Le tableau analytique sert l'exploration des tendances, avec exploration en profondeur et filtrage. Le tableau stratégique suit des objectifs de long terme. Le tableau de bord de GeniusLab est opérationnel ; la supervision est analytique. Mélanger les deux nuit à l'un comme à l'autre : un écran d'action surchargé de graphiques de tendance perd en lisibilité, et une analyse noyée dans des alertes temps réel perd en recul.

Pour le taux d'utilisation des machines, la formule est unanime dans le RETEX : il s'agit des heures réellement réservées rapportées aux heures disponibles sur la période. Les heures disponibles doivent exclure les fermetures planifiées (nuits, week-ends), sous peine de dégonfler artificiellement le taux. Une saturation durable invite à envisager un second équipement ; une faible occupation signale une capacité disponible.

Pour les consommables, l'indicateur de référence est le taux de consommation et de réapprovisionnement sur une période. Sans historique de mouvements, cette analyse est impossible : le consommable ne porte que son niveau courant.

## 3. Les décisions de conception

Page distincte. La supervision est une page à part entière, accessible aux rôles de pilotage (admin, formateur, BDE), et non un ajout au tableau de bord. Elle reprend la distinction opérationnel / analytique du RETEX.

Taux d'utilisation dérivé d'une source unique. La capacité d'ouverture n'est pas une valeur inventée : elle se dérive des bornes horaires déjà définies pour la génération des créneaux (8h à 16h30), comptées sur les jours ouvrés de la période. Si le FabLab change ses horaires, on modifie un seul endroit et le taux suit (principe DRY). La formule applique le RETEX : minutes réservées sur minutes disponibles.

Fluctuations fondées sur un historique tracé. Les consommables n'avaient pas d'historique. Plutôt qu'une saisie dédiée (qui dupliquerait l'inventaire), le traçage est automatique et silencieux : chaque ajustement de stock écrit un mouvement daté en arrière-plan, dans un historique immuable. Voir le journal de décisions (DEC-074).

Export plutôt que graphiques lourds. L'analyse fine se fait hors application, sur les données exportées (CSV et XLSX, trois jeux). La page reste sobre et lisible, l'analyse approfondie étant déléguée au tableur de l'exploitant.

## 4. Ce qui est réellement disponible

Les trois axes s'appuient sur des données réelles, sans simulation. L'activité de réservation et le taux d'utilisation se calculent sur les réservations existantes (dates, durées, machines). Les fluctuations de consommables s'appuient sur l'historique des mouvements, qui se constitue à partir des ajustements postérieurs à la mise en place du traçage : la courbe se remplit avec le temps, ce qui est le comportement attendu.
