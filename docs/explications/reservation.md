# Réservation : le cœur métier

La réservation est la fonction centrale de GeniusLab (famille BF_3.x du cahier des charges). Ce document décrit ses règles, ses invariants et la façon dont la concurrence est maîtrisée. Il complète le journal de décisions (DEC-010 à DEC-013) et la documentation d'architecture.

Tout passe par `ReservationService` : aucun contrôleur ne crée, ne modifie ou n'annule une réservation directement. La règle métier vit dans le service, testable sans HTTP.

---

## 1. Modèle mental

Une réservation est une **session** : un groupe d'étudiants occupe un créneau (un début et une durée) pour y utiliser une ou plusieurs machines en parallèle, dans le cadre d'un projet. Deux types existent (DEC-013) : la préparation (accompagnement, mise au point) et la réalisation (usage machine effectif). Le statut suit un cycle : planifiée, effectuée, annulée, reportée.

Le modèle distingue l'enveloppe de l'occupation (DEC-100). L'entité `SessionReservation` porte ce qui est commun à la visite : le projet, le type, le créneau (début, fin, durée), l'effectif (nombre de personnes), le statut. Chaque machine utilisée est une `Reservation`, simple occupation rattachée à la session, qui ne porte que la machine. L'effectif, le type, le créneau et le statut se lisent sur la session, source unique de vérité : il n'existe pas d'occupation sans session, ni d'effectif dupliqué d'une machine à l'autre.

> Une réservation n'est jamais créée à la main. Elle passe par `ReservationService::creerSession()`, qui applique toutes les règles dans une transaction. Court-circuiter le service, c'est court-circuiter la capacité, le quota et le verrou de concurrence.

La durée du créneau n'est pas dérivée de la machine : elle est choisie au moment de réserver (de 30 minutes à 4 heures, en liste fermée) et portée par la session, qui la propage à toutes ses occupations. `definirCreneau()` pose début, fin et durée de façon cohérente sur la session.

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
1       quota de réalisations par projet   refus : maximum de sessions atteint
        (préparation non plafonnée)        (la préparation ne compte pas)
2       chaque machine réservable et        refus, sous verrou par machine
        libre sur le créneau (BF_3.8)
3       capacité 15 personnes               refus : places restantes affichées
        sur le créneau (BF_3.9)
```

La règle 0 garantit qu'on ne réserve que sur un projet approuvé : portée dans le service (et non plus seulement à l'écran), elle tient quel que soit le point d'entrée. Les machines sont verrouillées une à une (verrou pessimiste), et la capacité du créneau est lue sous verrou, parce que l'état de la base a pu changer entre la vérification rapide et l'écriture.

Les actions d'annulation et de report vérifient d'abord l'état de la réservation : seule une réservation planifiée peut être annulée ou reportée. Cette garde évite d'agir deux fois sur un créneau déjà clos, ce qui, pour l'annulation, pourrait sinon infliger une seconde sanction pour le même créneau.

### La capacité de 15 personnes (BF_3.9, DEC-010)

La constante `SessionReservation::CAPACITE_MAX_FABLAB = 15` plafonne le nombre de personnes simultanément présentes sur un créneau. L'effectif étant porté une seule fois par la session (et non par chaque machine), le service additionne les personnes des sessions qui chevauchent le créneau et le nombre demandé : si la somme dépasse 15, il refuse et indique les places restantes. Une session à trois machines pour sept personnes consomme sept places, pas vingt et une.

### Le quota de sessions de réalisation (DEC-011, DEC-100)

Un projet est limité à `SessionReservation::MAX_SESSIONS_REALISATION` sessions de réalisation. Le comptage ne retient que les sessions de type réalisation, et exclut les annulées et reportées :

> Un créneau abandonné (annulé ou reporté) ne consomme pas de session. Sans cette exclusion, reporter une réservation ferait monter le compteur à tort et bloquerait un projet légitime.

La préparation n'est pas plafonnée : le quota du benchmark vise les sessions de fabrication, et aucun système de réservation observé ne plafonne un sous-type d'accompagnement. Le type de chaque session est choisi par l'étudiant au moment de réserver, et il est homogène pour toute la session.

---

## 3. Maîtrise de la concurrence

Le pic d'écritures à l'ouverture des créneaux est le moment critique : plusieurs étudiants tentent de réserver le même créneau en même temps. Sans protection, deux réservations concurrentes peuvent franchir ensemble le plafond de 15.

> La transaction verrouille chaque MACHINE de la session (verrou pessimiste en écriture) avant de lire la capacité du créneau. Deux sessions concurrentes touchant une même machine sont ainsi sérialisées : la seconde attend que la première ait fini, et relit une capacité à jour.

Le choix de verrouiller la machine plutôt que le créneau est délibéré : le créneau n'existe pas comme ligne verrouillable au moment de la lecture, alors que la machine est une ligne stable. Verrouiller les machines de la session ferme la fenêtre de concurrence sans table de verrous dédiée.

C'est précisément cette concurrence d'écriture qui a motivé le choix de PostgreSQL plutôt que SQLite (DEC-002) : SQLite n'autorise qu'un seul écrivain à la fois, ce qui transforme le verrou en goulot global.

---

## 4. Annulation et report

### Annulation (BF_3.11)

`annuler()` passe la session entière (donc toutes ses machines) en statut annulée et renvoie un booléen : l'annulation est tardive si le créneau débute dans moins de trois jours. L'appelant (le contrôleur de projet) traduit ce signal en sanction de l'étudiant.

```
Délai avant le créneau     Annulation        Conséquence
──────────────────────     ──────────────     ──────────────────────────
3 jours ou plus            normale            aucune sanction
moins de 3 jours           tardive            une sanction (BF_6.2)
```

La séparation des responsabilités est nette : le service de réservation détecte le caractère tardif, le service de sanction applique la pénalité. Le service de réservation ne connaît pas les sanctions.

### Report (BF_3.12)

`reporter()` déplace une session entière à une nouvelle date : l'ancienne session est marquée reportée, une nouvelle est créée à la nouvelle date avec les mêmes machines, le même type, le même effectif et la même durée. Comme l'annulation, il signale si le report est tardif (moins de trois jours), à traiter en sanction par l'appelant. Le créneau d'origine est libéré, ce qui n'augmente pas le quota (le report ne consomme pas de session).

L'annulation comme le report agissent sur la session entière, jamais sur une seule de ses machines : ce qui a été réservé sous une confirmation unique se déplace ou s'annule en bloc. Garder une machine et en lâcher une autre se fait en annulant la session et en en recréant une.

---

## 5. Synchronisation calendrier (BF_3.1)

Les réservations à venir et planifiées sont exposées en flux iCal, protégé par un jeton dans l'URL. Les administrateurs s'abonnent à ce flux pour voir le planning du FabLab se mettre à jour automatiquement, sans passer par l'application. Le calendrier interne (BF_3.22) affiche les mêmes réservations à venir.

---

## 6. Le parcours de réservation : page unique multi-machines

### D'un wizard à étapes à une page unique

Le parcours a d'abord été conçu comme un *wizard* : un formulaire découpé en étapes successives (machine, rendez-vous de préparation, détails, créneaux, récapitulatif), présentées une à la fois. Cette piste a été abandonnée pour deux raisons cumulées (voir le journal d'itération UI, DEC-088 puis DEC-090).

D'abord, l'implémentation sur le mécanisme de formulaires multi-étapes natif du framework a buté sur une cascade de difficultés irréductibles ; la réécriture en solution « maison » maîtrisée ligne à ligne a été la bonne décision. Ensuite, et c'est l'essentiel, la tâche elle-même ne justifiait pas un tunnel : le rendez-vous de préparation relève de l'humain et non du logiciel, et une fois retiré, il ne restait qu'une saisie atomique aux champs simples et connus d'avance. Un assistant à deux ou trois étapes aurait été trop maigre ; une page unique convient mieux, surtout sur mobile, où la réservation se fait principalement.

### Le parcours retenu

Sur une seule page disposée en trois colonnes de hauteur bornée (modèle Cal.com, Calendly) : à gauche un calendrier mensuel inline dont chaque jour porte une pastille de densité (libre, chargé, complet), au centre les créneaux du jour choisi en liste de style agenda (heure et nombre de machines libres), à droite le panier des créneaux déjà composés. Choisir un jour dans le calendrier charge ses créneaux ; cliquer un créneau fait basculer le panneau de droite vers les machines libres de ce créneau, à cocher, avec un stepper pour le nombre de personnes et le bouton d'ajout. On ajoute au panier, on recommence au besoin, puis on confirme. Un créneau associé à plusieurs machines produit une seule session portant autant d'occupations que de machines : l'effectif et le type sont saisis une fois pour la session.

La page ne défile pas : seules les listes internes (créneaux, machines, panier) défilent dans leur colonne, et le pied du panneau de droite reste ancré en bas pour que cocher une machine ne déplace rien. Sous 880px (mobile), les colonnes s'empilent et la barre d'action sticky garde la confirmation à portée du pouce (DEC-092). Le détail de cette ergonomie (calendrier à densités, créneaux en liste, stepper, disposition sans défilement, page de report jumelle) est consigné en DEC-099 et dans `../explications/calendrier-disponibilite.md`.

### Ce qui rend ce parcours solide et protégé

Les principes ci-dessous, dégagés pendant la mise au point, garantissent la robustesse du composant et valent pour tout parcours de saisie complexe :

- **Un état explicite et simple.** Le panier (la liste des créneaux composés) est un objet sérialisable stocké en session sous une clé propre au projet. L'état est une donnée inspectable, sans mécanique cachée, ce qui le rend prévisible.

- **Des actions explicites, une par transition.** L'ajout d'un créneau, son retrait et la confirmation sont des actions distinctes du contrôleur, chacune en POST et porteuse de son propre jeton anti-CSRF. Rien n'est implicite.

- **Une saisie entièrement guidée (anti-vandalisme).** Le jour se choisit par un clic dans le calendrier (jamais au clavier) ; la durée dans une liste fermée ; le créneau par un clic sur une proposition valide générée par le serveur ; les machines en cases à cocher ; le nombre de personnes par un stepper borné. Le report suit la même règle : une page dédiée réutilise le calendrier et les créneaux, sans aucun champ de date libre. Le contrôleur rejette toute machine inactive, toute durée hors de la liste proposée, tout nombre de personnes hors capacité. Une classe entière d'erreurs et d'abus est éliminée en amont.

- **L'information partagée est anonymisée.** L'aperçu de disponibilité montre l'état d'un créneau (libre, occupé, complet) sans révéler qui a réservé.

- **La règle métier reste dans le service.** Le contrôleur collecte et ordonne la saisie ; il ne décide pas de la capacité, du quota ni du verrou. À la confirmation, il délègue à `ReservationService`, seul garant des invariants. La porte d'entrée, aussi soignée soit-elle, ne duplique pas les règles qui protègent les données.

La leçon transversale : le parcours est solide quand chaque responsabilité est à sa place. Le contrôleur gère la navigation et l'état, le service garde les règles, le gabarit se contente d'afficher. Dès qu'une de ces frontières est franchie, la fragilité réapparaît. Et la meilleure réponse à un composant qui devient trop complexe peut être de constater qu'il n'a pas lieu d'être. La discipline GET / POST et le contrôle d'accès qui encadrent ces routes sont détaillés dans la référence `routes-et-acces.md`.

---

## Points clés à retenir

- Une réservation est une session (projet, type, créneau, effectif, statut) portant une à plusieurs occupations machine : l'effectif et le type sont sur la session, jamais dupliqués par machine.
- Toute réservation passe par `ReservationService` : court-circuiter le service contourne capacité, quota et verrou.
- Capacité 15 et machine active sont revérifiées sous verrou : la vérification rapide hors transaction ne suffit pas à garantir l'invariant.
- Le verrou porte sur la machine, pas sur le créneau : c'est la ligne stable qui sérialise les écritures concurrentes.
- Annulé ou reporté ne consomme pas de session de quota : seules les réservations actives comptent.
- Annulation ou report à moins de trois jours : signal de sanction renvoyé à l'appelant, jamais appliqué par le service de réservation lui-même.
