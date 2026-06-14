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

## Itération 13 : la recette sur l'instance réelle (multi-créneaux, lenteur, densité)

Problème observé : sur l'application assemblée et nourrie de données denses, plusieurs griefs concordants. Le panier de réservation restait bloqué à un créneau, impossible d'en ajouter un second. Le calendrier mettait plus d'une seconde à s'afficher. La page de supervision laissait un large vide sous la courbe de stock et débordait la hauteur d'écran. Les barres d'utilisation du tableau de bord, enfin, ne montraient pas leurs trois niveaux faute de contraste dans le jeu de données.

Décision (DEC-101) : débogage sur faits, puis correctifs ciblés. Le multi-créneaux ne relevait pas du métier mais d'un formulaire de retrait imbriqué dans le formulaire d'ajout, invalide en HTML, qui faisait perdre ses champs au bouton « Ajouter » dès qu'une ligne existait ; sortir les formulaires de retrait et les relier par l'attribut `form` a suffi, vérifié sous navigateur. La lenteur venait d'une requête par créneau et par machine, soit des centaines de requêtes pour un mois ; une requête unique sur la période, le calcul fait ensuite en mémoire, et un squelette de chargement pour l'attente résiduelle. Le vide de la supervision tenait à une sparkline en hauteur automatique, étirée par sa largeur, et à des cartes qui copiaient la hauteur de leur voisine ; une hauteur de graphe fixe et un alignement des cartes sur leur hauteur naturelle ont rendu la page dense et sans défilement. La seed a été densifiée et pondérée par machine pour que les trois niveaux d'usage apparaissent réellement.

Leçon : sur une instance réelle, un bug d'apparence « métier » (le panier qui ne grandit pas) peut n'être qu'une faute de structure HTML ; prouver la cause avant de toucher au service a évité de modifier la logique de réservation, intacte. La lenteur perçue comme « réseau » était en fait un nombre de requêtes : la mesure désigne le bon levier. Et la densité d'un tableau de bord se gagne en bornant les graphes décoratifs, pas en les étirant pour combler le vide.

- Le déclencheur reste une observation concrète sur l'instance réelle, jamais une intuition.
- Prouver la cause (structure HTML, nombre de requêtes, comportement du SVG) précède tout correctif.
- La correction réutilise un mécanisme standard (attribut `form`, requête unique, hauteur bornée) plutôt qu'une invention.
- Présentation et métier restent étanches : aucun correctif de cette itération n'a touché une règle de capacité, de quota ou de verrou.

## Itération 14 : la supervision, d'une page fonctionnelle à une page juste

Problème observé : la page de supervision marchait, mais quatre défauts ressortaient sur l'instance réelle. Les courbes traçaient les mois futurs à zéro, donnant une fausse chute d'activité. Les graphes étaient nus, sans axes ni valeurs ni points. Rien ne permettait de lire un chiffre précis. Et le découpage mêlait une courbe et une liste par rangée, produisant des cartes de hauteurs disparates et un rendu désordonné. Le tout devait continuer de tenir sur un écran sans défilement.

Décision (DEC-102) : refonte de la présentation, fondée sur le RETEX dataviz, sans toucher au métier. Les courbes s'arrêtent au mois courant, ce qui supprime la chute trompeuse. Chaque courbe gagne un axe gradué, un point par mois, une infobulle native au survol pour la valeur exacte, et l'étiquette permanente du dernier point comme repère et comme alternative au survol. Les cartes sont regroupées par nature : les deux courbes alignées en haut, les deux listes en bas, ce qui aligne les hauteurs rangée par rangée. La carte des mouvements reçoit un filtre par motif et un défilement interne à en-tête figé, repris de patterns déjà présents dans le projet (le conteneur d'ascenseur interne et le contrôleur de filtre de la galerie).

Leçon : « fonctionnel » n'est pas « juste ». Une courbe qui trace le futur à zéro n'est pas neutre, elle ment sur la tendance ; corriger la borne d'affichage valait plus que toute fioriture visuelle. Le regroupement par nature, plutôt que par ordre d'apparition des données, suffit à rendre une grille équilibrée. Et la refonte la plus visible n'a réutilisé que des mécanismes déjà éprouvés ailleurs dans l'interface, sans rien inventer ni toucher au calcul des indicateurs.

- Le déclencheur reste une observation concrète sur l'instance réelle.
- La recherche fonde la décision quand la question est tranchable (honnêteté des axes, tooltip contre étiquetage, densité sans défilement).
- La correction réutilise les composants existants (ascenseur interne, contrôleur de filtre) plutôt qu'une invention.
- Présentation et métier restent étanches : aucun calcul d'indicateur n'a été modifié.

## Itération 15 : passe de microcopie (états vides, messages d'erreur)

Problème observé : un audit des textes a révélé des états vides formulés différemment pour des situations identiques (« sur cette période » contre « sur la période », « pour le moment » ajouté de façon inégale, doublons « en stock » contre « en stock pour le moment »), et deux messages d'erreur jargonneux exposant des termes techniques à l'usager (« Jeton de sécurité invalide », « Action inconnue »).

Décision : harmonisation sans sur-correction. Les états vides divergents sont ramenés à une formulation unique par situation ; les répétitions strictement identiques entre pages sont conservées, car elles sont cohérentes. Les tournures correctes en contexte (notifications, vitrine destinée à un administrateur technique) sont laissées telles quelles. Les deux messages d'erreur jargonneux sont reformulés en langage orienté cause et remède : le jeton de sécurité invalide devient « Votre session a expiré. Merci de recommencer. », l'action inconnue devient « Action non reconnue, aucune modification effectuée. ». Constat secondaire utile : les états vides des listes (machines, stock) portaient déjà un bouton d'appel à l'action, le composant d'état vide étant conçu comme une invitation à agir et non un cul-de-sac.

Leçon : une passe de texte se mesure à sa retenue autant qu'à ses corrections. Harmoniser ne veut pas dire tout réécrire : on supprime les divergences inutiles, on garde les répétitions cohérentes et le jargon adapté à son audience. Le jargon visible par l'usager se traduit en cause et en action, pas en terme technique.

## Itération 16 : la grille de cases qui se désalignait

Problème observé : sur la page de nouvelle demande, la liste des machines à cocher se présentait en grille irrégulière. Au retour à la ligne, une case restait orpheline en fin de ligne et son libellé passait seul en dessous (« Station IoT / électronique » désaligné), au point de se mêler visuellement au champ de fichiers qui suivait.

Décision : corriger à la racine, pas par un calage ponctuel. La cause était structurelle. Le champ « expanded » génère par défaut une suite plate (case, libellé, case, libellé…) que la feuille de style disposait en `flex-wrap` : rien ne liait une case à son libellé, d'où la rupture au passage à la ligne. La correction introduit un thème de formulaire qui enveloppe chaque choix dans un bloc solidaire case + libellé, et passe le conteneur en grille régulière. Le thème hérite du gabarit standard et ne redéfinit que le rendu des champs expanded, sans toucher aux autres champs. Bénéfice de bord : tous les formulaires à cases du projet (gestion des utilisateurs comprise) profitent du même rendu propre, sans intervention par page.

Leçon : un désalignement n'est pas toujours un défaut de marge, c'est parfois un défaut de structure. Lier la case à son libellé une fois pour toutes, au niveau du thème, vaut mieux que dix correctifs de largeur fragiles. Et corriger au bon niveau profite à tout le reste sans effort supplémentaire.

- Le déclencheur reste une observation concrète sur l'instance réelle.
- La cause (structure plate, paires non liées) est établie avant tout correctif.
- La correction réutilise le mécanisme standard des thèmes de formulaire, sans hack de largeur.
- Le correctif est porté au niveau partagé, donc cohérent sur tous les formulaires à cases.

## Itération 17 : la page de demande, protections de saisie et compaction

Problème observé : la page de nouvelle demande étalait ses trois blocs sur toute la largeur, gâchant l'espace horizontal sur grand écran et allongeant la page sans raison. Surtout, les champs n'avaient pas de garde-fous : le titre n'avait pas de longueur validée (au-delà de la limite de colonne, l'insertion aurait échoué en erreur brute), la description était illimitée, et la quantité n'avait qu'un minimum côté navigateur, contournable, sans borne serveur.

Décision : protéger d'abord, compacter ensuite, en suivant le RETEX. Côté protections, la validation vit à deux niveaux complémentaires : les attributs HTML (longueur maximale, bornes numériques) aident la saisie en direct mais restent contournables ; la vraie garantie est portée par les contraintes serveur, sur l'entité pour le titre (3 à 40 caractères) et la description (250 caractères au plus), et sur le formulaire pour la quantité (au moins 1, au plus 10). Les messages d'erreur sont explicites et en français. Côté mise en page, le RETEX distingue nettement le tableau de bord, que l'on garde sur un écran, du formulaire de saisie, qu'il ne faut pas comprimer (Effortmark « long forms: scroll or tab? », documentation UX Mendix sur la colonne unique, étude Harms et al. 2015 sur les formulaires longs en mobile) : un formulaire qui scrolle un peu fonctionne bien (un écran complet, jusqu'à deux écrans de plus restent acceptables), la colonne unique reste la règle dans chaque bloc, et l'on déconseille de tout entasser, en particulier sur mobile où le scroll d'un formulaire long est la pire des méthodes face aux catégories tenant dans l'écran. La compaction consiste donc à resserrer les espacements et à faire cohabiter, sur grand écran seulement, les deux blocs courts (matériel et détails) sur une même rangée, le bloc projet restant pleine largeur. Sur mobile, tout repasse en pile.

Leçon : « tout sur un écran » est une règle de tableau de bord, pas de formulaire. Pour un formulaire, on réduit la hauteur en resserrant et en regroupant, jamais en sacrifiant la colonne unique ni la lisibilité. Et une protection de champ ne vaut que si elle existe côté serveur : l'attribut HTML est une commodité, pas une barrière.

- Le déclencheur est une observation concrète sur l'instance réelle, doublée d'une exigence de robustesse des saisies.
- La recherche tranche la question de la mise en page (formulaire contre tableau de bord) plutôt qu'une intuition.
- La protection est posée au niveau serveur, l'attribut HTML ne servant que le confort de saisie.
- La compaction préserve la colonne unique et le comportement mobile ; rien n'est comprimé.

## Itération 18 : le filet anti-débordement étendu aux affichages de projet

Problème observé : une demande au titre et à la description faits d'un mot interminable sans espaces (« TESTTESTTEST… », typique d'un test ou d'un vandalisme) débordait de sa carte sur la page de validation, le texte sortant à droite hors du cadre. Les protections de saisie posées juste avant empêchent d'en créer de nouvelles, mais les données déjà en base devaient s'afficher proprement.

Décision : réutiliser le filet déjà établi dans le projet, pas en inventer un. La feuille de style portait déjà, sur les bannières, un `overflow-wrap: anywhere` commenté comme filet anti-vandalisme : un mot très long est coupé au lieu de déborder. Ce même filet est étendu aux trois endroits où un titre ou une description de projet s'affiche dans un cadre contraint : les cartes de la page de validation, les listes du tableau de bord, et le tableau des projets de l'espace étudiant. Dans les contextes en flex (carte, ligne de tableau de bord), le titre reçoit en plus la possibilité de rétrécir et l'élément voisin (badge, type) est protégé contre la compression. Dans le tableau, comme la largeur maximale d'une cellule est ignorée en disposition automatique, le titre est borné via un span interne.

Leçon : un filet d'affichage et une protection de saisie sont deux lignes de défense distinctes et complémentaires. La saisie empêche de créer la donnée hostile ; l'affichage encaisse celle qui existe déjà. Et quand une solution est déjà adoptée quelque part, l'étendre vaut mieux que d'en créer une variante.

- Le déclencheur est une observation concrète sur l'instance réelle.
- La solution réutilise un mécanisme déjà présent et documenté dans le projet.
- Le correctif est porté aux trois affichages à risque, pas seulement à celui signalé.
- Affichage et saisie restent deux défenses séparées, chacune à sa place.

## Itération 19 : troncature des listes, page d'examen et bouton d'accès

Problème observé : sur la page de validation, même après le filet de coupure de mot, une description faite d'un seul mot interminable s'étalait sur quatre lignes dans la carte. La page d'examen d'une demande, elle, débordait encore : titre et description sortaient du cadre à droite. Et le lien « Examiner la demande » était noyé en lien souligné au milieu d'une ligne de métadonnées, peu repérable.

Décision : trois ajustements complémentaires. Pour la liste, on passe de la simple coupure de mot à une troncature multi-lignes : titre et description sont limités à deux lignes, le surplus est masqué avec une ellipse. C'est la différence entre couper un mot pour qu'il ne déborde pas et borner le nombre de lignes affichées. Pour la page d'examen, le filet de coupure est étendu au titre de l'en-tête de page et aux sections de détail, qui n'en bénéficiaient pas. Pour l'accès, le lien d'examen devient un bouton à part entière, au contour de la couleur primaire et précédé d'une icône, distinct des actions Valider et Refuser sans leur faire concurrence.

Leçon : couper un mot et limiter le nombre de lignes sont deux besoins distincts. Le premier empêche le débordement horizontal, le second protège la hauteur d'une carte dans une liste. Une liste de cartes a tout intérêt à borner ses textes pour rester scannable, en renvoyant le détail complet à la page dédiée, dont l'accès doit être franc.

- Le déclencheur est une observation concrète sur l'instance réelle.
- La troncature multi-lignes complète le filet de coupure, elle ne le remplace pas.
- Le filet d'affichage est porté partout où la donnée apparaît, page d'examen comprise.
- L'action principale d'une carte (accéder au détail) est rendue explicite et repérable.

## Itération 20 : refléter le cycle de vie de la demande dans la liste

Problème observé : les actions de rétractation et de soumission n'existaient que sur la page de détail. Depuis la liste « Mes projets », rien ne signalait qu'un brouillon attendait d'être soumis, ni qu'une demande en attente pouvait être retirée. L'état le plus ambigu était le brouillon, visuellement proche d'une demande active alors qu'il n'était pas parti en validation.

Décision : la liste signale et oriente, le détail porte les actions sensibles. Ce partage suit le RETEX des files d'approbation (Moxo, dvsum, et le principe NN/g de liste scannable où l'action principale prime) : une vue de liste rend l'état et la prochaine action lisibles d'un coup, sans devenir un panneau de commande. Sous le badge d'un brouillon, une mention « Non soumis » lève l'ambiguïté. Dans la colonne de droite, une action contextuelle par ligne, selon le statut : « Soumettre » pour un brouillon, « Gérer » pour une demande en attente, « Détail » seul pour le reste. Le bouton « Soumettre » mène à la page de détail plutôt que de soumettre directement, pour que l'étudiant vérifie ses fichiers avant l'envoi. Les actions à conséquence (rétracter, supprimer, modifier les fichiers) restent sur le détail, avec confirmation : les répartir dans chaque ligne de tableau alourdirait la lecture et rendrait les erreurs plus faciles, l'inverse du but recherché.

Leçon : une liste doit rendre lisible l'état et la prochaine action de chaque élément, sans devenir un panneau de commande. On y met l'orientation (où aller, quoi faire ensuite), on y laisse hors champ les gestes irréversibles, qui méritent une page et une confirmation. Refléter un cycle de vie, ce n'est pas dupliquer toutes ses commandes partout.

- Le déclencheur est le besoin de cohérence entre la liste et les actions ajoutées au détail.
- Le statut le plus ambigu (brouillon non soumis) est explicité par une mention.
- Une seule action contextuelle par ligne, l'orientation prime sur la commande.
- Les gestes irréversibles restent sur le détail, avec confirmation.

## Itération 21 : un composant d'upload unifié, glisser-déposer

Problème observé : l'ajout de fichiers était pénible des deux côtés. À la création, l'input était techniquement multiple mais rien ne l'indiquait, et le rendu natif (« Aucun fichier sélectionné ») n'invitait pas à la sélection groupée. À la modification, l'ajout passait par un cycle « Parcourir puis Ajouter » à répéter, sans vue d'ensemble des fichiers retenus. Deux présentations différentes pour le même geste, et une multiplication des clics.

Décision : un seul composant d'upload, réutilisé partout, fondé sur le RETEX (zone de dépôt cliquable et glisser-déposer, sélection multiple explicite, liste de contrôle des fichiers choisis avec retrait individuel avant l'envoi). Un contrôleur Stimulus habille un input natif resté multiple : la zone pilote l'input, la liste reflète la sélection, et à chaque ajout ou retrait l'input réel est reconstruit (DataTransfer) pour que le formulaire envoie exactement les fichiers listés. Le même partiel sert à la création (où il enveloppe le champ rendu par Symfony) et à la modification (où il enveloppe un input manuel) : présentation unique, un seul point de maintenance. L'accessibilité est prise en compte : la zone est focalisable et s'active au clavier, l'input reste l'élément réel soumis.

Leçon : un même geste mérite un même composant. Dédoubler la présentation d'une action, c'est dédoubler les défauts et les corrections. Mieux vaut un composant unique, accessible, qui montre ce qui sera envoyé avant de l'envoyer, que deux variantes austères qui multiplient les clics.

- Le déclencheur est une observation concrète, doublée d'un constat d'anti-pattern.
- La présentation est unifiée entre création et modification, un seul contrôleur.
- L'input natif reste la source de vérité, reconstruit à chaque changement.
- L'accessibilité (focus, clavier) et le retour visuel avant envoi sont assurés.

## Itération 22 : faire respecter les limites d'upload en amont

Problème observé : le composant d'upload acceptait n'importe quel nombre de fichiers (66 vus en test), la liste s'étirait sans fin et gonflait la carte, et rien ne signalait que le surplus serait rejeté. La limite n'existait que côté serveur, donc le refus n'arrivait qu'après l'envoi, une fois le mal fait à l'écran.

Décision : appliquer les limites dès l'ajout, côté client, en miroir des contraintes serveur (dix fichiers, vingt-cinq mégaoctets par fichier, quatre-vingts au total). Le contrôleur reçoit ces seuils en paramètres plutôt qu'en dur, refuse les fichiers hors limite, et affiche la liste des refus avec leur motif. La liste des fichiers retenus est bornée en hauteur avec un défilement interne, pour que la carte ne grandisse jamais. Cette validation client reste une commodité : les contraintes serveur demeurent la vraie barrière, si bien qu'un contournement du script est toujours refusé à la réception.

Leçon : une limite doit se faire sentir au moment du geste, pas après l'envoi. Laisser l'utilisateur empiler soixante-six fichiers pour les rejeter ensuite, c'est lui faire perdre son temps et casser la mise en page au passage. Le bon réflexe est de refléter la règle serveur côté client, d'expliquer chaque refus, et de borner tout affichage de liste pour qu'il ne déborde jamais.

- Le déclencheur est une observation concrète et un comportement absurde à l'écran.
- La limite est appliquée à la source du geste, avec un motif de refus explicite.
- La validation client double la règle serveur sans la remplacer.
- Toute liste affichée est bornée et défile, jamais étirée sans fin.

## Itération 23 : message de refus agrégé plutôt que liste détaillée

Correction de l'itération 22. La validation client des limites d'upload affichait une ligne par fichier refusé, avec son nom et son motif. Sur une sélection abusive (soixante-six fichiers pour une limite de dix), cela produisait cinquante-six lignes identiques, qui étiraient la zone d'erreur et reproduisaient le débordement même qu'on cherchait à supprimer. Le bornage posé à l'itération 22 ne couvrait que la liste des fichiers acceptés, pas celle des refus.

Décision : remplacer la liste détaillée par un message agrégé unique. Les refus sont comptés par motif (limite de nombre, taille d'un fichier, total cumulé) et résumés en une phrase, par exemple « 56 fichiers écartés : limite de 10 fichiers atteinte ». Lister chaque fichier refusé n'apporte rien : ces fichiers ne sont ni envoyés ni enregistrés, ils n'existent que le temps de la sélection dans le navigateur. C'est d'ailleurs le choix des bibliothèques d'upload éprouvées (Dropzone, Uppy, FilePond), qui synthétisent les rejets au lieu de les énumérer.

Leçon : un retour d'erreur doit informer, pas submerger. Quand des dizaines d'éléments échouent pour la même raison, l'utilisateur a besoin du motif et du nombre, pas d'un inventaire. Et tout affichage dynamique, y compris une zone d'erreur, doit être pensé pour ne jamais croître sans limite : la borne ne se met pas seulement sur la liste « heureuse », mais sur toute zone qui se remplit à partir d'une entrée utilisateur.

- La correction porte sur une zone oubliée au bornage précédent.
- Le message est compté par motif et tient en une phrase.
- Énumérer des fichiers jamais envoyés est du bruit, pas de l'information.
- Toute zone alimentée par l'utilisateur doit être bornée, pas seulement la principale.

## Itération 24 : input masqué et stepper unifié

Deux défauts d'intégration observés sur la page de demande. D'abord l'input de fichier natif restait visible sous la zone de glisser-déposer, avec son bouton « Parcourir » et son « Aucun fichier sélectionné » : le composant d'upload n'avait jamais reçu la règle qui masque l'input réel, présente seulement dans la maquette. Ensuite la quantité s'affichait en champ numérique brut, avec les fléchettes natives du navigateur, alors que le nombre de personnes d'un créneau utilisait déjà un stepper « moins / valeur / plus ». Deux champs au même rôle, rendus différemment selon l'écran : une incohérence qui fait amateur.

Décision. L'input de fichier est masqué proprement (position absolue, dimensions réduites, plutôt que display:none qui rendrait le clic programmatique fragile) : il reste dans le document pour porter la sélection envoyée, la zone de dépôt étant la seule surface visible. Pour la quantité, le stepper est extrait en composant réutilisable (un contrôleur Stimulus et un partiel) et employé tel quel, avec la classe visuelle déjà utilisée par la réservation : même apparence, même comportement, un seul point de maintenance. Le composant lit les bornes sur l'input réel (min, max) et reste la source de vérité de la valeur soumise.

Bloc « Détails » simplifié. Ce bloc occupait une colonne séparée qui se vidait quand peu de champs s'appliquaient (souvent la seule quantité), créant un déséquilibre. Les champs de détail (quantité, supports) rejoignent désormais le bloc « Matériel » sous une sous-section, et la colonne fantôme disparaît. Le choix de ne pas révéler ces champs en direct au cochage d'une machine est assumé : la règle « quelle machine entraîne quel champ » vit côté serveur (doctrine du projet), et la dupliquer en JavaScript pour un gain mineur fragiliserait l'ensemble. La cohérence prime sur l'effet.

Leçon : un composant doit être unique pour un rôle donné, et son intégration doit être vérifiée en conditions réelles, pas seulement en maquette. Le masquage d'un input oublié au passage du prototype au code, ou un stepper réimplémenté ici et pas là, sont le même défaut : une intention de design qui ne se propage pas jusqu'au bout. On extrait, on réutilise, on vérifie à l'écran.

- Les deux défauts viennent d'une intention de maquette non propagée au code intégré.
- L'input réel est masqué mais conservé ; la valeur soumise reste correcte.
- Le stepper devient un composant unique, partagé avec la réservation.
- Le bloc Détails fusionne pour ne plus laisser de colonne vide.

## Itération 25 : refonte de la navigation latérale

La barre latérale avait grossi au fil des ajouts sans plan d'ensemble. Trois défauts s'étaient installés. L'icône « presse-papier » servait quatre fois (Mes projets, Demandes, Projets d'administration), brouillant la lecture. Trois entrées tournaient autour du mot « projet » sans distinction de rôle. Des libellés génériques (« Supervision », « Tableau de bord », « Contenu de la vitrine ») ne situaient pas l'utilisateur dans le contexte d'un FabLab. La page Galerie, ajoutée récemment, n'était même pas dans le menu.

M�thode. La refonte s'appuie sur le RETEX de navigation (limiter à cinq-sept liens par cluster, regrouper logiquement, libellés courts et descriptifs, éviter les multi-niveaux ; les sections de conformité forment un groupe distinct dé-emphasé) et sur le vocabulaire du logiciel FOSS de référence du domaine (Fab-Manager) : membres pour les comptes, machines pour le matériel, activité pour le suivi statistique. Le registre est assumé FabLab.

Nouvelle arborescence. Cinq groupes : Mon espace (Mes projets), Validation (Demandes à valider), Pilotage (Tableau de bord, Activité, Journal), Atelier (Machines, Consommables, Membres, Projets), Vitrine (Page d'accueil, Projets en avant). Les renommages : Supervision devient Activité, Stocks devient Consommables, Utilisateurs devient Membres, Contenu de la vitrine devient Page d'accueil, la Galerie devient Projets en avant et entre enfin dans le menu. Les projets d'administration quittent Pilotage pour Atelier (gestion des ressources), ce qui rapproche les objets gérés et éloigne le suivi. Chaque entrée reçoit une icône distincte : le presse-papier ne sert plus que pour Mes projets, Demandes prend une coche, Projets prend un document.

Logo cliquable. L'entrée « Voir le site public » de la barre latérale est supprimée au profit du logo de l'en-tête rendu cliquable vers l'accueil. C'est la convention web établie (cliquer le logo ramène à l'accueil), attendue par les utilisateurs et signalée par le RETEX comme un repère permanent ; un lien isolé dans la barre faisait doublon avec ce repère naturel et n'était pas le pattern attendu.

- L'icône presse-papier ne sert plus qu'à une entrée ; chaque libellé a son icône.
- Le vocabulaire suit la référence FOSS du domaine, registre FabLab assumé.
- La Galerie entre dans le menu ; les projets d'administration rejoignent l'Atelier.
- Le logo de l'en-tête ramène à l'accueil, selon la convention attendue.

## Itération 26 : la page Projets rejoint le composant de liste commun

La page d'administration des projets était la dernière liste à ne pas suivre le modèle des autres. Là où Machines, Consommables et Membres s'appuient sur le composant de tableau commun (recherche, tri, filtres, pagination, en-tête figé, ascenseur interne), la page Projets affichait une table maison sans recherche, sans tri, sans filtre ni pagination. Son fil d'ariane pointait encore vers Pilotage alors que l'entrée a été déplacée dans Atelier.

Décision. La page est migrée sur le composant de tableau commun, comme les autres listes : recherche plein texte, tri sur chaque colonne, et un filtre par statut (chips Tous / Brouillon / En attente / Validé / Refusé / En cours / Terminé), particulièrement utile pour isoler les projets en attente de décision ou les réalisations. Les actions propres aux projets (transitions du cycle de vie, suppression avec confirmation) sont conservées telles quelles dans la colonne d'actions. Le fil d'ariane est corrigé pour refléter la place réelle de la page dans le menu.

Le gain n'est pas qu'esthétique : en passant par le composant partagé, la page hérite automatiquement des évolutions futures du tableau commun, au lieu de figer un balisage divergent qu'il faudrait maintenir à part. Une liste de plus rentre dans le rang ; le coût de maintenance baisse d'autant.

- La page Projets utilise le composant de tableau commun, comme les autres listes.
- Recherche, tri par colonne, filtre par statut et pagination sont désormais disponibles.
- Les transitions et la suppression restent inchangées.
- Le fil d'ariane reflète la place de la page (Atelier).

## Itération 27 : seed enrichi pour juger la curation, et nettoyage typographique

Deux ajustements liés à l'évaluation de la galerie. D'abord la typographie : plusieurs tirets cadratins s'étaient glissés dans les pages galerie, projets et dans la base (titre, libellés, étiquette d'accessibilité), contraires à la règle du projet. Ils sont remplacés par des deux-points ou des reformulations, et les tirets servant de remplacement de donnée vide sont passés en tiret simple.

Ensuite le jeu de données de démonstration. La galerie ne pouvait pas être jugée : le seed ne comptait que deux projets terminés, tous deux déjà mis en avant. Impossible donc d'observer le cas central de la curation, un projet terminé qu'on n'a pas encore choisi d'afficher. Le seed est étendu à une douzaine de projets couvrant tous les statuts, avec cinq projets terminés répartis entre trois mis en avant et deux en attente de curation. Cela exerce à la fois le filtre par statut et le tri de la page Projets, et donne à la galerie de quoi montrer son regroupement réel. Choix assumé : aucun fichier n'est généré, seulement les métadonnées (statuts, mise en avant) ; la réutilisation d'un fichier-image joint se teste donc à la main, pas via le seed.

- Tirets cadratins éliminés des templates, conformément à la règle typographique.
- Le seed couvre tous les statuts et, surtout, des projets terminés mis en avant ET non mis en avant.
- Le filtre par statut et le tri de la page Projets ont désormais de quoi être éprouvés.
- Aucun fichier n'est généré par le seed : la réutilisation de plan-image reste un test manuel.

## Itération 28 : graphes d'activité interactifs

Les courbes de la page Activité étaient correctes mais peu interactives : points minuscules difficiles à viser, infobulle native limitée à une ligne, aucune comparaison, aucun moyen d'isoler une série. Le tracé était aussi étiré (preserveAspectRatio none), ce qui déformait les points.

Choix technique. Le SVG maison est conservé plutôt qu'une librairie. Le RETEX est net : pour un faible volume de points (douze mois, quelques machines), le SVG l'emporte sur une solution Canvas en interactivité par élément et en accessibilité, et une librairie n'apporterait son avantage qu'au-delà de plusieurs milliers de points. Chart.js, rendu en Canvas, aurait dégradé l'accessibilité soignée (titres natifs, focus clavier) et ajouté une dépendance à intégrer sans bundler. La décision est donc d'enrichir le SVG via un contrôleur Stimulus dédié, sans rien installer.

Interactions ajoutées. Un curseur vertical et une infobulle unique apparaissent au survol et présentent la valeur de toutes les séries visibles pour le mois pointé (divulgation progressive : le détail au survol, rien qui encombre par défaut). Les zones de survol couvrent toute la colonne du mois, ce qui rend la visée indépendante de la taille des points. Le graphe de réservations superpose l'année courante et l'année précédente, et une légende cliquable permet de masquer ou d'afficher chaque série.

Robustesse de la comparaison. Si l'année précédente n'a aucune donnée, sa série et son entrée de légende sont masquées d'office : conformément au RETEX, on n'affiche pas un visuel vide ou une courbe plate qui ferait douter de l'exactitude. Le serveur fournit désormais les réservations de l'année N-1 en plus de l'année courante ; le reste du comportement est porté côté client par le contrôleur, sans logique métier dupliquée.

- Le SVG maison est enrichi, sans dépendance, accessibilité préservée.
- Survol croisé (curseur + infobulle multi-séries) et zones de visée larges.
- Comparaison année courante / précédente, légende cliquable pour isoler une série.
- Une série de comparaison sans données est masquée, série et légende comprises.

## Itération 29 : choix libre de l'année de comparaison

L'itération précédente comparait l'année courante à l'année précédente, en dur. Le besoin réel est de choisir librement l'année de comparaison, sur le graphe des réservations.

Périmètre. Seul le graphe des réservations reçoit la comparaison : le graphe de stock affiche un niveau cumulé, qui se prête mal à la superposition de deux années (le cumul d'une année et celui d'une autre ne partagent pas d'origine commune). Ce choix est assumé.

Pattern. Le RETEX des outils d'analyse (Matomo, Google Analytics, Kaltura) converge sur un contrôle « comparer à » explicite, avec des présets et une option de choix. Transposé à notre granularité annuelle, cela donne un menu déroulant « Comparer à : Aucune / année… » placé au-dessus du graphe. La comparaison n'est pas imposée par défaut : elle s'active par choix. Seules les années qui comptent des données sont proposées, et une année sans réservation sélectionnée par erreur n'ajoute aucune courbe.

M�canisme. Le serveur envoie les données mensuelles de toutes les années disponibles (volume négligeable, quelques années de douze valeurs) ; le menu bascule la série de comparaison côté client, sans rechargement. L'échelle de l'axe Y est fixée d'avance sur le maximum de toutes les années comparables, pour qu'elle reste stable quel que soit le choix, et que les graduations restent cohérentes avec le tracé.

- Le graphe des réservations propose un choix libre de l'année de comparaison.
- Le graphe de stock cumulé reste sans comparaison (inadapté), choix assumé.
- Comparaison activable et non imposée ; seules les années avec données sont offertes.
- Bascule côté client sans rechargement ; échelle Y stable pour rester lisible.
