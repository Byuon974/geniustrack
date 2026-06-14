# Recette manuelle : valider les correctifs en conditions réelles

Certains correctifs touchent au comportement à l'exécution (rendu, redirections, gardes d'état, contraintes base) qui ne se vérifie pas par la seule analyse statique du code. Ce document liste les scénarios à dérouler une fois le projet assemblé et démarré (`make up`, base amorcée), pour confirmer que ces correctifs se comportent comme prévu. Chaque scénario indique l'action, le résultat attendu, et la décision d'origine.

Préalable : disposer d'un compte de chaque rôle utile (admin, formateur, étudiant), et d'au moins un projet validé pour l'étudiant. Le seed de démonstration (`make reseed`) fournit ce matériel.

## 1. Suppression d'une machine référencée (DEC-065)

Vérifie qu'une machine ayant un historique de réservations ne peut pas être supprimée et bascule proprement en « hors service ».

1. En admin, créer une machine (Ressources › Machines › Ajouter).
2. En étudiant, sur un projet validé, réserver un créneau sur cette machine (le wizard de réservation).
3. En admin, revenir sur la liste des machines et tenter de supprimer cette machine.
   - Attendu : la suppression est refusée. Un message d'erreur s'affiche en haut de la liste : la machine a un historique et doit être passée « hors service ». **Pas d'erreur serveur (500).**
4. Toujours en admin, ouvrir « Modifier » sur cette machine et passer son état à « Hors service ».
   - Attendu : l'état affiché dans la liste passe au badge rouge « Hors service ».
5. En étudiant, relancer le wizard de réservation.
   - Attendu : la machine hors service n'apparaît plus dans la liste des machines réservables.

Contrôle complémentaire : créer une seconde machine et la supprimer sans jamais la réserver.
   - Attendu : la suppression réussit (la garde ne bloque que les machines référencées).

## 2. Double annulation d'une réservation (DEC-063)

Vérifie que la garde d'état empêche une seconde annulation et le message s'affiche proprement.

1. En étudiant, sur un projet validé, réserver un créneau.
2. Annuler ce créneau.
   - Attendu : message de confirmation (ou message de sanction si l'annulation est tardive, à moins de trois jours du créneau).
3. Tenter d'annuler à nouveau le même créneau (par exemple en revenant en arrière dans le navigateur, ou via un double envoi).
   - Attendu : l'action est refusée avec un message clair (la réservation n'est plus annulable). **Pas d'erreur serveur, pas de seconde sanction.**

Même principe à vérifier pour le report (`reporter`) : reporter deux fois le même créneau doit être refusé au second essai.

## 3. Réservation sur un projet non validé (DEC-063)

Vérifie que la garde de statut tient côté service.

1. En étudiant, créer un projet et le laisser en brouillon (ne pas le soumettre), ou le soumettre sans le faire valider.
2. Tenter d'accéder au wizard de réservation pour ce projet.
   - Attendu : l'accès est refusé avec un message indiquant que le projet doit d'abord être validé.

## 4. Levée d'une sanction et réactivation (DEC-064)

Vérifie que lever une sanction ne réactive pas le compte.

1. Amener un compte étudiant au seuil de désactivation (cinq sanctions actives) : le compte devient inactif.
2. En admin, lever une sanction de ce compte.
   - Attendu : la sanction est levée, mais le message précise que **le compte reste désactivé**. Le compte n'est pas réactivé automatiquement.
3. En admin, réactiver explicitement le compte (édition du compte, ou `make activer EMAIL=...`).
   - Attendu : le compte redevient actif et l'étudiant peut se connecter.

## 5. Examen d'une demande et accès aux plans (DEC-061)

Vérifie que le valideur voit le détail et peut télécharger les plans.

1. En étudiant, soumettre un projet en y joignant un ou plusieurs plans (STL, PDF…).
2. En formateur (pour un projet pédagogique) ou BDE (pour un projet personnel), ouvrir Validation › Demandes › « Examiner la demande ».
   - Attendu : la page de détail affiche la description, les machines, et la liste des plans avec leur lien de téléchargement.
3. Télécharger un plan.
   - Attendu : le téléchargement fonctionne (le valideur a le droit de lecture, pas seulement le propriétaire ou l'admin).

## 6. Messages flash sur toutes les pages (DEC-066)

Vérifie que les messages s'affichent partout, et sans doublon.

1. Déclencher une action qui produit un message de succès (par exemple créer une machine) et une action qui produit un message d'erreur (par exemple le refus de suppression du scénario 1).
   - Attendu : chaque message s'affiche une seule fois, en haut du contenu, quelle que soit la page d'arrivée.

## 7. Limites d'upload des plans (DEC-062)

Vérifie les trois limites et leurs messages.

1. En étudiant, à la soumission d'un projet, tenter de joindre un fichier de plus de 25 Mo.
   - Attendu : refus, message indiquant la limite par fichier.
2. Tenter de joindre plus de dix fichiers.
   - Attendu : refus, message sur le nombre maximal.
3. Joindre plusieurs fichiers dont le total dépasse 80 Mo.
   - Attendu : refus, message sur le poids total.

## 8. Réservation à durée variable et chevauchement (DEC-075)

Vérifie le créneau souple : heure de début au pas de 30 minutes, durée réglable, et blocage des créneaux qu'une session longue recouvre.

1. En étudiant, sur un projet validé, lancer la page de réservation et choisir un jour dans le calendrier.
2. Vérifier que l'heure de début se choisit au pas de 30 minutes (08:00, 08:30, 09:00...) et que le dernier début proposé ne fait pas dépasser la fermeture (16h30) compte tenu de la durée.
3. Choisir une durée de plusieurs heures et confirmer : la réservation couvre bien la plage début + durée.
4. Tenter une seconde réservation dont le créneau chevauche la première sur la même machine : elle doit être refusée (machine déjà réservée sur ce créneau).

## 9. Supervision du laboratoire (DEC-076)

Vérifie la page analytique et le calcul du taux d'utilisation.

1. En admin ou formateur, ouvrir Pilotage › Supervision.
2. Vérifier les trois axes : réservations par mois, taux d'utilisation des machines (barres colorées par niveau), derniers mouvements de stock.
3. Changer d'année dans le filtre de période et vérifier que les données suivent.
4. Vérifier qu'une machine très réservée affiche un taux élevé (rouge) et une machine peu réservée un taux faible (vert).

## 10. Export enrichi CSV et XLSX (DEC-077)

Vérifie les deux formats et les trois jeux.

1. En admin, depuis la supervision, cliquer sur « Export CSV » : un fichier de réservations se télécharge, les accents s'affichent correctement à l'ouverture dans un tableur.
2. Cliquer sur « Export XLSX » : un classeur se télécharge avec trois onglets (réservations, taux machines, mouvements stock).
3. En formateur (non admin), vérifier que l'accès à l'export est refusé (données nominatives, réservé à l'admin).

## 11. Jeux d'essai du dossier projet (cas nominal, erreur, limite)

Ces trois scénarios correspondent au jeu d'essai de la fonctionnalité la plus représentative (création de réservation) décrit au chapitre 7 du dossier projet. Le jeu de démonstration (`make reseed`) prépare leurs données : trois projets préfixés « [Test] » et un créneau déjà saturé à 15 personnes.

### 11.1 Cas nominal (réservation réussie)

1. Se connecter avec le compte étudiant propriétaire du projet « [Test] Réservation nominale » (Jean).
2. Lancer le wizard de réservation sur ce projet validé, choisir une machine active et un créneau libre, indiquer 3 personnes.
3. Confirmer. Résultat attendu : la réservation est créée au statut « planifiée », et la capacité du créneau diminue de 3.

### 11.2 Cas d'erreur (projet en brouillon)

1. Se connecter avec le compte propriétaire du projet « [Test] Projet brouillon » (Marie).
2. Tenter de lancer une réservation sur ce projet au statut brouillon.
3. Résultat attendu : la réservation est refusée, avec le message indiquant que le projet doit être validé au préalable.

### 11.3 Cas limite (créneau saturé à 15 personnes)

1. Repérer le créneau saturé chargé par la démonstration : dans trois semaines à 8h00, sur la première machine active, déjà occupé par 15 personnes (deux réservations de 8 et 7).
2. Avec le projet « [Test] Créneau saturé », tenter de réserver ce même créneau pour 1 personne de plus.
3. Résultat attendu : la réservation est refusée pour capacité atteinte, et la capacité du créneau reste à 15.

## 12. Sélecteur de réservation et report (DEC-099)

Vérifie l'ergonomie du sélecteur de date refondu et la page de report dédiée.

### 12.1 Calendrier à densités et sélection

1. En étudiant, sur un projet validé, ouvrir la page de réservation.
2. Vérifier que le calendrier mensuel s'affiche, que les jours passés sont désactivés, et que chaque jour réservable porte une pastille (verte, ambre ou contour vide selon la disponibilité).
3. Survoler un jour ou le sélectionner : le nombre de créneaux libres apparaît ; il n'est pas affiché en permanence dans la cellule.
4. Cliquer un jour : la liste des créneaux du jour se charge au centre. Cliquer un créneau libre : le panneau de droite bascule du panier vers les machines libres, avec le compteur de personnes et le bouton d'ajout.
5. Vérifier que cocher une machine ne déplace pas la mise en page, et que la page ne défile pas (seules les listes internes défilent). Régler le nombre de personnes avec le compteur (de 1 à 15).

### 12.2 Report d'un créneau

1. Sur la page d'un projet ayant une réservation planifiée, cliquer « Reporter ».
2. Résultat attendu : une page dédiée s'ouvre avec le même calendrier et la même liste de créneaux, la durée d'origine rappelée et verrouillée, aucun champ de date à saisir au clavier.
3. Choisir un nouveau jour puis un créneau libre : le récapitulatif se met à jour et le bouton « Confirmer le report » s'active.
4. Confirmer. Résultat attendu : la réservation est déplacée, le créneau d'origine libéré. Un report à moins de trois jours déclenche une sanction (BF_6.2), conformément à la règle métier inchangée.

### 12.3 Bandeau de confirmation fermable

1. Après une confirmation de réservation, vérifier que le bandeau de succès affiche une croix de fermeture et qu'un clic dessus le retire.

## 13. Choix du type et réservation multi-machines (DEC-100)

Vérifie le modèle « session » : le type préparation/réalisation est choisi à la réservation, l'effectif est compté une fois pour la session, et report comme annulation portent sur la session entière.

### 13.1 Choix du type à la réservation

1. En étudiant, sur un projet validé, lancer la page de réservation et choisir un jour puis un créneau libre.
2. Vérifier la présence du sélecteur « Type de session » (Réalisation par défaut, ou RDV de préparation).
3. Composer un créneau de type « préparation », cocher une machine, ajouter au panier, confirmer.
   - Attendu : la session apparaît sur la page projet avec la mention « préparation ».
4. Composer autant de sessions de réalisation que le quota l'autorise, puis une de plus.
   - Attendu : la réalisation excédentaire est refusée (quota atteint), mais une session de préparation reste possible (la préparation n'est pas plafonnée).

### 13.2 Plusieurs machines sur un même créneau (une session)

1. Composer un créneau, cocher plusieurs machines libres, indiquer un nombre de personnes, confirmer.
   - Attendu : une seule session est créée sur la page projet, listant toutes les machines cochées.
2. Vérifier sur le créneau que la capacité consommée correspond au nombre de personnes saisi une fois, pas multiplié par le nombre de machines (composer une autre réservation sur le même créneau et constater que les places restantes décroissent du bon montant).

### 13.3 Report et annulation au niveau de la session

1. Sur une session à plusieurs machines, lancer le report : choisir un nouveau créneau et confirmer.
   - Attendu : toutes les machines de la session se déplacent ensemble vers la nouvelle date ; l'ancienne session passe « reportée ».
2. Sur une autre session à plusieurs machines, annuler.
   - Attendu : la session entière passe « annulée », toutes ses machines libérées en même temps.
