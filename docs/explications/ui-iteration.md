# Itération UI : journal des décisions visuelles

Ce document raconte comment l'interface a évolué, et selon quelle méthode. Là où `../reference/design-system.md` décrit l'UI telle qu'elle est, celui-ci décrit comment elle y est arrivée : le problème observé, la recherche qui a guidé la correction, le correctif, la leçon.

---

## La méthode : diagnostic, recherche, correctif

Les décisions visuelles ne se prennent pas à l'instinct. Le cycle est constant :

1. Un problème est observé concrètement (capture d'écran de l'application réelle, pas une supposition).
2. Une recherche sourcée ou un retour d'expérience est consulté avant de trancher, dès que la question peut se fermer par une référence.
3. Le correctif est appliqué en s'appuyant sur les tokens et composants existants.
4. La leçon est inscrite, pour que le même problème ne se reproduise pas ailleurs.

> Une maquette est souvent validée avant le code : reconstituer fidèlement l'écran avec les vrais tokens permet de juger une direction visuelle sans attendre l'assemblage. Cette maquette n'est pas le rendu final, c'est un aperçu de décision.

---

## Itération 1 : la couleur sous-exploitée

Problème observé : l'interface paraissait terne, les couleurs sémantiques (vert, ambre, rouge) ne vivaient que dans de minuscules badges.

Recherche : les sources sur la couleur fonctionnelle convergent : la couleur doit encoder du sens, une palette fonctionnelle compte douze à seize valeurs réparties en neutres, marque et états sémantiques. Le neutre chaud est la réaction documentée contre le blanc corporate froid.

Correctif : donner des rôles sémantiques clairs aux couleurs existantes et les faire vivre au-delà des badges (filets de statut à gauche des lignes, pastilles, cartes à filet coloré). Pas d'ajout de nouvelles teintes (DEC-024).

Leçon : la palette n'était pas pauvre, elle était sous-exploitée. L'erreur inverse (vert et rouge partout, effet sapin de Noël) est tout aussi mauvaise : l'accent reste réservé à l'actionnable.

---

## Itération 2 : la couleur de l'action « Valider »

Problème observé : le bouton « Valider » était en terracotta, la couleur de l'action primaire générique, alors que le statut « validé » est vert. Conflit sémantique : l'action qui mène au vert ne portait pas le vert.

Correctif : création d'un bouton `btn--success` vert, appliqué partout (cartes de demande et modale). Le terracotta reste à l'action primaire générique, le vert à la validation (DEC-025).

Leçon : la couleur d'une action doit s'aligner sur l'état qu'elle produit. Une incohérence entre l'action et son résultat brouille la lecture.

---

## Itération 3 : la popup de confirmation native

Problème observé : la confirmation de suppression utilisait `window.confirm()`, qui affiche une popup système en anglais, hors charte, mentionnant l'adresse technique de la page.

Correctif : une modale maison construite sur l'élément `<dialog>` natif, en français, stylée selon le design system, avec une variante de couleur selon l'action (vert pour valider, rouge pour supprimer). Accessible nativement : focus géré, touche Échap, clic sur le fond pour annuler (DEC-026).

Leçon : un composant natif du navigateur n'est pas neutre, il impose sa langue et son style. Le remplacer par `<dialog>` apporte le contrôle visuel sans sacrifier l'accessibilité, sans dépendance externe.

---

## Itération 4 : le tableau de bord qui n'en était pas un

Problème observé : après trois cartes de statut correctes, le tableau de bord s'effondrait en une seule colonne verticale de barres d'utilisation machine, qui poussait tout vers le bas. Pas de hiérarchie, pas de grille.

Recherche : le RETEX dashboard est unanime. L'œil scanne en Z ou en F, le KPI primaire va en haut à gauche. Les dashboards professionnels utilisent une grille systématique (douze à seize colonnes). Le test des cinq secondes juge la lisibilité : si on ne retient pas les métriques clés après cinq secondes, il faut simplifier. Modèle de pyramide inversée : signal en haut, détail actionnable dessous.

Correctif : cartes de statut à filet de couleur sémantique en haut, puis une grille à deux colonnes (utilisation machine d'un côté, raccourci actionnable vers les demandes à traiter de l'autre). Le raccourci complète la carte-compteur au lieu de la dupliquer.

Leçon : un tableau de bord se juge à l'agencement, pas à la quantité de données. Plusieurs zones côte à côte qu'on embrasse d'un regard valent mieux qu'une seule colonne qui défile.

---

## Itération 5 : la page de demande non cadrée

Problème observé : la page de nouvelle demande alignait ses champs sans structure visible, et les cases à cocher des machines débordaient sur une seule ligne.

Correctif : découpage en sections claires (Votre projet, Matériel nécessaire, Détails), chacune dans un bloc titré. Cases machines passées en disposition qui passe à la ligne au lieu de déborder.

Leçon : un formulaire long a besoin de sections qui regroupent les champs par intention. Le regroupement visuel guide l'utilisateur autant que les libellés.

---

## Itération 6 : le seed trop pauvre pour juger l'UI

Problème observé : le calendrier et le tableau de bord paraissaient vides ou inertes, non par défaut d'interface, mais parce que les données de démonstration étaient trop maigres (une seule réservation conditionnelle).

Correctif : seed enrichi à douze réservations futures planifiées, des deux types, réparties sur plusieurs machines (DEC-038).

Leçon : une interface ne se juge pas sur une base vide. Sans données représentatives, on confond un écran correct mais affamé avec un écran défaillant. Le seed dense est un outil de design, pas seulement de test.

Mise à jour ultérieure : la même leçon a guidé l'arrivée de la durée variable et de la supervision. Le seed a été enrichi en conséquence, avec des réservations aux durées variées (pour que le taux d'utilisation soit réaliste) et un historique de mouvements de stock datés (pour que les fluctuations de consommables ne soient pas vides). Une fonctionnalité analytique sans données représentatives ne se juge pas.

---

## Itération 7 : la densité et le single-screen des pages « Modifier »

Problème observé (captures réelles) : champs de hauteur inégale, espacements irréguliers obligeant à viser à la souris, cases de rôles rejetées à l'extrême droite loin de leur libellé, barres de stock illisibles (toutes pleines, sans repère), et pages « Modifier » qui débordaient en défilement.

Recherche (form density, loi de Fitts) : hauteur de champ alignée sur le bouton (32px), espacement régulier (16px entre champs), proximité label/champ pour réduire les fixations oculaires, deux colonnes pour compacter sans rogner la lisibilité.

Correctif (DEC-043) : champs à 32px d'espacement constant ; pages « Modifier » en deux colonnes denses tenant sur un écran ; cases collées à leur libellé ; barres de stock proportionnelles au seuil avec repère et couleur de niveau ; pages utilisateur compactées (identité en bandeau, indicateurs en ligne, historique condensé, tableau en zone défilante).

Leçon : la densité n'est pas du tassement. Des règles chiffrées issues du RETEX donnent un rythme régulier qui se lit mieux ET tient sur un écran.

---

## Itération 8 : le calendrier qui refusait de se rendre

Problème observé (captures réelles, console) : le cadre du calendrier restait vide. Le diagnostic a établi sur des faits, pas des suppositions, que les données étaient correctes (le flux JSON renvoyait bien les réservations) : l'échec venait du rendu de la librairie FullCalendar, non débogable côté serveur, même en hébergeant le bundle localement.

Décision (DEC-041, DEC-042) : abandon de la librairie au profit d'une grille mensuelle rendue côté serveur en Twig, sans dépendance JavaScript, au style skeuomorphique (relief sur l'en-tête et les flèches, cases en creux, jour courant surélevé). Le bundle et l'ancien template ont été retirés pour éviter le code mort.

Leçon : s'acharner sur une dépendance non débogable a un coût. Quand les données sont prouvées correctes et que seul le rendu échoue, une solution serveur sans dépendance est souvent plus robuste et plus contrôlable, surtout en réseau institutionnel.

---

## Itération 9 : voir l'occupation sans voir les autres

Problème posé : permettre de réserver depuis le calendrier en voyant ce qui est déjà occupé, mais sans révéler ce que font les autres. Tension apparente entre visibilité de l'occupation et confidentialité.

Recherche (free/busy : Google Workspace, GroupCal) : le pattern de référence résout exactement cette tension. On partage la disponibilité d'une ressource (libre, occupé, complet) sans révéler le détail des réservations d'autrui ; le propriétaire seul reconnaît les siennes.

Correctif (DEC-040) : bande de disponibilité free/busy à l'étape « Créneaux » du wizard, anonyme côté serveur, au style skeuomorphique cohérent avec le calendrier (libres en relief pressable, occupés en creux inactif, sien surélevé).

Leçon : une contrainte qui semble contradictoire a souvent un pattern de référence éprouvé. Chercher le pattern du domaine évite d'inventer une demi-solution.

---

## Itération 10 : les messages d'erreur muets

Problème observé : lors de l'ajout d'une garde refusant la suppression d'une machine ayant un historique (DEC-065), un audit de l'UI a révélé que le message d'erreur ne s'afficherait jamais. La liste des machines, comme six autres pages, n'affichait que les messages de succès. Chaque page choisissait localement quels types de flash rendre, et beaucoup avaient omis les erreurs. Une action pouvait donc échouer en silence, sans rien expliquer à l'utilisateur.

Décision (DEC-066) : l'affichage des flashes est centralisé dans le gabarit, en haut du contenu, pour tous les types à la fois. Les dix-sept affichages locaux répartis sur quatorze templates sont retirés. Le seul bloc d'alerte qui n'était pas un flash (le motif de refus sur la fiche projet) a été préservé.

Leçon : une garde métier correcte côté code reste à moitié faite si l'UI ne la donne pas à voir. L'affichage des messages est une préoccupation transverse de présentation : centralisé une fois, il garantit qu'aucun message ne se perd et que toute redirection future en bénéficie sans y penser.

---

## Itération 11 : le wizard de réservation et sa cascade de bugs

Problème observé : la mise en place du parcours guidé de réservation (Form Flow natif de Symfony 7.4) a produit une longue série d'erreurs successives, chacune corrigée par une recherche sourcée, mais révélant à chaque fois une frontière de responsabilité mal posée. La séquence, instructive, mérite d'être consignée :

- un crash de typage (champ « nombre de personnes » nul au moment du mapping, avant validation) ;
- un rejet en boucle des soumissions (jeton anti-CSRF supprimé par un nettoyage trop zélé de l'affichage) ;
- un double rendu (les champs des autres étapes rendus en brut sous le wizard stylé) ;
- des accès à des champs inexistants dans le gabarit (accès nommés fragiles combinés au partage de données entre étapes) ;
- une option de configuration inexistante dans la version installée (libellés des boutons), prise pour acquise depuis une source secondaire ;
- enfin, le navigateur de boutons traité comme une donnée du parcours, déclenchant la recherche d'une propriété absente sur l'objet de données.

Décisions (DEC-082, DEC-083, DEC-086) : chaque correctif a consisté à remettre une responsabilité à sa place plutôt qu'à ajouter une rustine. Le gabarit ne rend que l'étape reçue, sans filtrer ; les boutons de navigation sont déclarés non liés aux données ; le jeton anti-CSRF est rendu à chaque transition ; les libellés français passent par un navigateur personnalisé composé des types de boutons individuels, et non par une option absente ; la saisie de créneau est guidée (sélection d'horaires), jamais libre. La doctrine complète du wizard est consignée dans `reservation.md`.

Leçon : un wizard concentre une difficulté particulière, le partage d'un état entre des étapes successives. Sa solidité ne vient pas d'un correctif unique mais du respect de frontières nettes : le contrôleur gère la navigation et l'état, le service garde les règles métier, le gabarit affiche l'étape sans raisonner, la saisie reste guidée. Chaque bug de cette cascade correspondait au franchissement d'une de ces frontières. Le coût élevé de cette mise au point tient aussi à un facteur de méthode : le parcours ne pouvait être validé qu'à l'exécution, hors de portée de la simple relecture de code.

Épilogue (DEC-088) : après cette cascade, le mécanisme natif a été abandonné au profit d'un wizard « maison » inspiré de systèmes de réservation libres éprouvés. Ce virage est lui-même une leçon : une dépendance, même fournie par le framework, peut coûter plus qu'elle ne rapporte quand elle est trop récente ou mal documentée pour l'usage visé. Lui préférer une solution dont on maîtrise chaque ligne (état en session, actions explicites, créneaux pré-générés cliquables) a supprimé d'un coup toute la classe de bugs liée à la magie du composant, et a permis d'imposer enfin la saisie guidée voulue (aucune date au clavier, durées en liste fermée, pause déjeuner respectée).

Aboutissement (DEC-090, DEC-092) : la réflexion est allée plus loin que le remplacement technique. La tâche, une fois retiré le rendez-vous de préparation, s'est révélée atomique : le tunnel à étapes lui-même était de trop. Le parcours final est une page unique multi-machines, mobile-first (barre d'action sticky, cibles tactiles), où l'on compose un panier de créneaux. La meilleure correction d'un composant trop complexe fut, ici, de constater qu'il n'avait pas lieu d'être.

---

## Itération 12 : le sélecteur de date, du menu déroulant au calendrier

Problème observé : sur captures de l'application réelle, le sélecteur de jour du parcours de réservation était une liste déroulante, et les créneaux des pavés-boutons colorés en grille. Trois griefs concordants sont remontés en revue de maquette : la liste déroulante de dates est chronophage pour un choix qui s'étale sur des semaines, les pavés colorés font peu professionnel, et le parcours débordait la hauteur d'écran, le faisant défiler comme une page ordinaire au lieu de tenir d'un seul tenant.

Décision (DEC-099) : refonte de l'ergonomie sans toucher au métier. La liste déroulante laisse place à un calendrier mensuel inline où chaque jour porte une pastille de densité (libre, chargé, complet), calculée côté serveur et révélée d'un coup d'œil ; le nombre de places n'apparaît qu'au survol, pour garder la cellule épurée. Les pavés deviennent une liste verticale de style agenda. La page adopte une grille à trois colonnes de hauteur bornée, sur le modèle de Cal.com et Calendly : seules les listes internes défilent, la page ne bouge pas, et le pied du panneau de droite est ancré pour qu'une interaction (cocher une machine) ne déplace jamais la mise en page. Le menu du nombre de personnes devient un compteur, conforme aux retours d'expérience sur les saisies de petites quantités. Le report, jusque-là un champ de date au clavier sur la fiche projet, gagne une page dédiée réutilisant le même sélecteur.

Leçon : chaque grief s'est résolu par un pattern établi plutôt que par une invention. Le calendrier qui porte sa propre densité supprime une lecture en fusionnant deux informations qui demandaient deux regards (où sont les jours, lesquels sont libres). La discipline « sans défilement » ne tient pas par des hauteurs fixes empilées au hasard, mais par une règle simple : une seule surface borne la hauteur, et tout ce qui peut grandir défile à l'intérieur. La refonte la plus visible de l'interface n'a pas touché une seule règle de capacité, de quota ou de verrou : présentation et métier sont restés étanches, ce qui a rendu le changement sûr.

- Le déclencheur est toujours une observation concrète (capture de l'application réelle), jamais une intuition abstraite.
- La correction s'appuie sur les tokens et composants existants, elle n'invente pas de valeur nouvelle sans raison.
- Quand la question peut se fermer par une recherche (couleur fonctionnelle, patterns de dashboard, densité de formulaire, free/busy), la recherche précède le correctif.
- Chaque correctif devient une règle réutilisable (une décision `DEC-NNN`), pour que la leçon serve aux écrans suivants.
- Le débogage sur faits (prouver où est le problème) précède toute décision de refonte ; on ne suppose pas la cause.
