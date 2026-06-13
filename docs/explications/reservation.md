# Réservation : le cœur métier

La réservation est la fonction centrale de GeniusLab (famille BF_3.x du cahier des charges). Ce document décrit ses règles, ses invariants et la façon dont la concurrence est maîtrisée. Il complète le journal de décisions (DEC-010 à DEC-013) et la documentation d'architecture.

Tout passe par `ReservationService` : aucun contrôleur ne crée, ne modifie ou n'annule une réservation directement. La règle métier vit dans le service, testable sans HTTP.

---

## 1. Modèle mental

Une réservation est un créneau pendant lequel un étudiant occupe une machine pour un projet. Deux types existent (DEC-013) : la préparation (accompagnement, mise au point) et la réalisation (usage machine effectif). Le statut suit un cycle : planifiée, effectuée, annulée, reportée.

> Une réservation n'est jamais créée à la main. Elle passe par `ReservationService::creerSession()`, qui applique toutes les règles dans une transaction. Court-circuiter le service, c'est court-circuiter la capacité, le quota et le verrou de concurrence.

La durée d'un créneau n'est pas saisie : elle est dérivée de la machine. `setDateDebut()` calcule `dateFin` en ajoutant la durée de créneau de la machine. La machine doit donc être définie avant la date.

### Ce que le logiciel archive, et ce qu'il n'archive pas

GeniusLab archive les mouvements du FabLab : le titulaire d'une réservation, ses machines, ses créneaux. Il n'archive pas l'assemblée présente. Le nombre de personnes d'un créneau est une donnée de capacité (combien de places le créneau consomme sur la limite de quinze), pas un registre nominatif des participants. Si un étudiant réserve pour lui et quatorze camarades, ces quatorze-là n'entrent pas en base : c'est un trou assumé et voulu. Les identifier tous serait impossible en pratique et inutile au regard du besoin. Ce principe cadre la portée du logiciel : il sert à piloter l'usage des machines et à tracer les engagements de réservation, il ne fait pas de la surveillance de fréquentation. La première utilisation de GeniusLab par un étudiant est signalée au valideur sur la fiche de demande et conservée dans l'archive du projet, mais elle relève du même esprit : une information de pilotage humain, pas un dispositif de contrôle.

---

## 2. Les règles appliquées à la création

`creerSession()` applique les règles dans cet ordre, les moins coûteuses d'abord :

```
Ordre   Règle                              Effet si violée
─────   ──────────────────────────────     ────────────────────────────────
0       projet validé ou en cours          refus : projet à valider d'abord
                                            (BF_3.3)
1       machine réservable                 refus : machine en maintenance
                                            ou hors service (BF_3.8)
2       quota de réalisations par projet   refus : maximum de sessions atteint
3       machine active (re-vérifiée         refus, sous verrou
        sous verrou)
4       capacité 15 personnes               refus : places restantes affichées
        sur le créneau (BF_3.9)
```

La règle 0 garantit qu'on ne réserve que sur un projet approuvé : portée dans le service (et non plus seulement à l'écran), elle tient quel que soit le point d'entrée. La règle 1 est une vérification rapide hors transaction (cas le plus fréquent, inutile de verrouiller pour le rejeter). Les règles 3 et 4 sont rejouées à l'intérieur de la transaction, sous verrou, parce que l'état de la base a pu changer entre la vérification rapide et l'écriture.

Les actions d'annulation et de report vérifient d'abord l'état de la réservation : seule une réservation planifiée peut être annulée ou reportée. Cette garde évite d'agir deux fois sur un créneau déjà clos, ce qui, pour l'annulation, pourrait sinon infliger une seconde sanction pour le même créneau.

### La capacité de 15 personnes (BF_3.9, DEC-010)

La constante `Reservation::CAPACITE_MAX_FABLAB = 15` plafonne le nombre de personnes simultanément présentes sur un créneau. À la création, le service additionne les personnes déjà prévues sur le créneau et le nombre demandé : si la somme dépasse 15, il refuse et indique les places restantes.

### Le quota de sessions de réalisation (DEC-011)

Un projet est limité à `Projet::MAX_SESSIONS_REALISATION` sessions de réalisation. Le comptage exclut les réservations annulées et reportées :

> Un créneau abandonné (annulé ou reporté) ne consomme pas de session. Sans cette exclusion, reporter une réservation ferait monter le compteur à tort et bloquerait un projet légitime.

---

## 3. Maîtrise de la concurrence

Le pic d'écritures à l'ouverture des créneaux est le moment critique : plusieurs étudiants tentent de réserver le même créneau en même temps. Sans protection, deux réservations concurrentes peuvent franchir ensemble le plafond de 15.

> La transaction verrouille la MACHINE (verrou pessimiste en écriture) avant de lire la capacité du créneau. Deux requêtes concurrentes sur la même machine sont ainsi sérialisées : la seconde attend que la première ait fini, et relit une capacité à jour.

Le choix de verrouiller la machine plutôt que le créneau est délibéré : le créneau n'existe pas encore comme ligne verrouillable au moment de la lecture, alors que la machine est une ligne stable. Verrouiller la machine ferme la fenêtre de concurrence sans table de verrous dédiée.

C'est précisément cette concurrence d'écriture qui a motivé le choix de PostgreSQL plutôt que SQLite (DEC-002) : SQLite n'autorise qu'un seul écrivain à la fois, ce qui transforme le verrou en goulot global.

---

## 4. Annulation et report

### Annulation (BF_3.11)

`annuler()` passe la réservation en statut annulée et renvoie un booléen : l'annulation est tardive si le créneau débute dans moins de trois jours. L'appelant (le contrôleur de projet) traduit ce signal en sanction de l'étudiant.

```
Délai avant le créneau     Annulation        Conséquence
──────────────────────     ──────────────     ──────────────────────────
3 jours ou plus            normale            aucune sanction
moins de 3 jours           tardive            une sanction (BF_6.2)
```

La séparation des responsabilités est nette : le service de réservation détecte le caractère tardif, le service de sanction applique la pénalité. Le service de réservation ne connaît pas les sanctions.

### Report (BF_3.12)

`reporter()` déplace une réservation à une nouvelle date. Comme l'annulation, il signale si le report est tardif (moins de trois jours), à traiter en sanction par l'appelant. Le créneau d'origine est libéré, ce qui n'augmente pas le quota (le report ne consomme pas de session).

---

## 5. Synchronisation calendrier (BF_3.1)

Les réservations à venir et planifiées sont exposées en flux iCal, protégé par un jeton dans l'URL. Les administrateurs s'abonnent à ce flux pour voir le planning du FabLab se mettre à jour automatiquement, sans passer par l'application. Le calendrier interne (BF_3.22) affiche les mêmes réservations à venir.

---

## 6. Le parcours de réservation : page unique multi-machines

### D'un wizard à étapes à une page unique

Le parcours a d'abord été conçu comme un *wizard* : un formulaire découpé en étapes successives (machine, rendez-vous de préparation, détails, créneaux, récapitulatif), présentées une à la fois. Cette piste a été abandonnée pour deux raisons cumulées (voir le journal d'itération UI, DEC-088 puis DEC-090).

D'abord, l'implémentation sur le mécanisme de formulaires multi-étapes natif du framework a buté sur une cascade de difficultés irréductibles ; la réécriture en solution « maison » maîtrisée ligne à ligne a été la bonne décision. Ensuite, et c'est l'essentiel, la tâche elle-même ne justifiait pas un tunnel : le rendez-vous de préparation relève de l'humain et non du logiciel, et une fois retiré, il ne restait qu'une saisie atomique aux champs simples et connus d'avance. Un assistant à deux ou trois étapes aurait été trop maigre ; une page unique convient mieux, surtout sur mobile, où la réservation se fait principalement.

### Le parcours retenu

Sur une seule page : choisir un jour et une durée, voir les créneaux du jour avec, pour chacun, le nombre de machines libres (groupés matin et après-midi), cliquer un créneau, cocher une ou plusieurs machines à utiliser en parallèle, indiquer le nombre de personnes, ajouter au panier ; répéter au besoin, puis confirmer. Un créneau associé à N machines produit N réservations au même horaire.

### Ce qui rend ce parcours solide et protégé

Les principes ci-dessous, dégagés pendant la mise au point, garantissent la robustesse du composant et valent pour tout parcours de saisie complexe :

- **Un état explicite et simple.** Le panier (la liste des créneaux composés) est un objet sérialisable stocké en session sous une clé propre au projet. L'état est une donnée inspectable, sans mécanique cachée, ce qui le rend prévisible.

- **Des actions explicites, une par transition.** L'ajout d'un créneau, son retrait et la confirmation sont des actions distinctes du contrôleur, chacune en POST et porteuse de son propre jeton anti-CSRF. Rien n'est implicite.

- **Une saisie entièrement guidée (anti-vandalisme).** Jour et durée se choisissent dans des listes fermées ; le créneau par un clic sur une proposition valide générée par le serveur ; les machines en cases à cocher. Aucune saisie de date au clavier, aucune durée arbitraire : le contrôleur rejette toute machine inactive, toute durée hors de la liste proposée, tout nombre de personnes hors capacité. Une classe entière d'erreurs et d'abus est éliminée en amont.

- **L'information partagée est anonymisée.** L'aperçu de disponibilité montre l'état d'un créneau (libre, occupé, complet) sans révéler qui a réservé.

- **La règle métier reste dans le service.** Le contrôleur collecte et ordonne la saisie ; il ne décide pas de la capacité, du quota ni du verrou. À la confirmation, il délègue à `ReservationService`, seul garant des invariants. La porte d'entrée, aussi soignée soit-elle, ne duplique pas les règles qui protègent les données.

La leçon transversale : le parcours est solide quand chaque responsabilité est à sa place. Le contrôleur gère la navigation et l'état, le service garde les règles, le gabarit se contente d'afficher. Dès qu'une de ces frontières est franchie, la fragilité réapparaît. Et la meilleure réponse à un composant qui devient trop complexe peut être de constater qu'il n'a pas lieu d'être. La discipline GET / POST et le contrôle d'accès qui encadrent ces routes sont détaillés dans la référence `routes-et-acces.md`.

---

## Points clés à retenir

- Toute réservation passe par `ReservationService` : court-circuiter le service contourne capacité, quota et verrou.
- Capacité 15 et machine active sont revérifiées sous verrou : la vérification rapide hors transaction ne suffit pas à garantir l'invariant.
- Le verrou porte sur la machine, pas sur le créneau : c'est la ligne stable qui sérialise les écritures concurrentes.
- Annulé ou reporté ne consomme pas de session de quota : seules les réservations actives comptent.
- Annulation ou report à moins de trois jours : signal de sanction renvoyé à l'appelant, jamais appliqué par le service de réservation lui-même.
