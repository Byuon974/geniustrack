# Conception du tableau de bord adaptatif

Document de travail : recherches, RETEX et décisions de conception avant codage. La maquette associée est `maquette-dashboard-adaptatif.html`.

## 1. Le constat de départ

La confrontation wireframe / rendu réel a montré que le tableau de bord livré était en dessous de ce que la conception prévoyait, mais aussi que le wireframe surchargeait l'écran de graphiques (donut, courbe annuelle) supposant un historique inexistant. L'objectif n'est donc pas de copier le wireframe, mais de concevoir un tableau de bord juste : adaptatif par rôle, actionnable, ancré sur les données réellement disponibles.

## 2. Ce que dit le RETEX (sources 2025-2026)

La recherche sur les bonnes pratiques de tableau de bord et sur les solutions de gestion de FabLab (Fabman, Spacebring, Omnify, Invention Studio de Georgia Tech) converge sur quelques principes.

Limiter le nombre d'indicateurs. Au-delà de cinq à neuf éléments par écran, l'engagement chute, car on dépasse la capacité de la mémoire de travail. Chaque indicateur supplémentaire au-delà de l'optimum dégrade la précision de décision.

Le test « so what ? ». Pour chaque métrique, si sa valeur change, l'utilisateur sait-il quelle action prendre ? Si non, c'est une métrique de vanité (du type « total de projets depuis toujours ») qui n'a pas sa place. Une bonne métrique provoque l'action et mène à l'écran qui permet d'agir.

Adapter au rôle (contrôle d'accès dynamique). Un responsable d'exploitation et un encadrant n'ont pas les mêmes décisions à prendre. Le tableau de bord n'affiche que ce qui est pertinent pour le rôle, ce qui réduit l'encombrement et renforce la sécurité. La vue d'exploitation (admin) suit les machines, le stock, les accès ; la vue d'encadrement (formateur) suit les validations et les sessions.

Pour un FabLab spécifiquement, les indicateurs de référence tournent autour de l'utilisation des machines, la disponibilité des équipements, les réservations à traiter et l'affluence, plutôt que des analyses financières.

## 3. Les décisions de conception

Deux vues, un même langage. L'admin et le formateur ont chacun leur tableau de bord, mais avec la même grammaire visuelle (cartes d'alerte à seuil, panneaux de détail, liens d'action). Seul le contenu change selon le rôle, pas l'interface.

Vue admin : piloter l'exploitation. Quatre cartes d'alerte (demandes en attente, stock sous le seuil, machines disponibles, créneaux du jour) et deux panneaux (utilisation des machines sur 30 jours, demandes à traiter). Chaque carte porte un lien vers l'écran d'action correspondant.

Vue formateur : valider et encadrer. Trois cartes (mes demandes à valider, sessions à venir de mes projets, projets que j'encadre) et deux panneaux (mes demandes en attente, prochaines sessions). Le formateur ne voit ni le stock ni le parc machine, hors de son périmètre.

Pas de graphique tant que l'historique est mince. On écarte délibérément donut et courbe annuelle. Les filets de couleur à seuil (rouge / ambre / vert) hiérarchisent le regard sans nécessiter de bibliothèque graphique ni de données historiques. Cette retenue est une application directe de KISS.

## 4. Les données : ce qui existe, ce qui manque

Disponible immédiatement (méthodes de repository existantes) : utilisation par machine sur N jours, projets en attente de validation, articles sous le seuil, machines actives, réservations à venir (globales, par étudiant, par valideur).

À ajouter côté code pour la vue formateur : une requête « demandes en attente filtrées par valideur » (l'existant donne les demandes en attente globales, et les réservations à venir par valideur, mais pas encore les demandes en attente propres à un valideur). Le décompte des créneaux du jour et le nombre de projets encadrés sont des agrégations simples à exposer.

## 5. Mise en œuvre (réalisée, DEC-071)

L'intégration a suivi ce plan :

1. `ProjetRepository::enAttenteParType()` ajoutée : la validation se faisant par type de projet (pédagogique pour le formateur, personnel pour le BDE), et non par valideur nommément assigné, la requête filtre sur le type plutôt que sur un champ valideur.
2. `DashboardController` ouvert à ROLE_FORMATEUR (dont l'admin hérite) et routant selon le rôle réel : vue d'exploitation pour l'admin, vue de validation pour le formateur et le BDE. Le journal d'activité reste protégé en ROLE_ADMIN.
3. Un template `validation.html.twig` réutilise les composants et classes existants (stat-carte, dash-bloc, page_header, empty) plutôt que d'en créer de nouveaux (DRY).
4. Le vocabulaire reste celui du projet (« Demandes à valider », « Prochaines sessions de vos projets »), sans terme générique importé du RETEX.

Évolutions possibles non encore branchées : le décompte des créneaux du jour et le nombre de projets encadrés, mentionnés dans la maquette de conception, sont des agrégations simples à exposer si le besoin se confirme.

> Réserve : rendu visuel et comportement runtime à confirmer après assemblage. Les chiffres de la maquette sont illustratifs.
