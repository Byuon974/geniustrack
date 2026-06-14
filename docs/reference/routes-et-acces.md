# Référence : routes, méthodes HTTP et contrôle d'accès

Ce document inventorie les points d'entrée HTTP du logiciel (les routes), explique
la discipline GET / POST appliquée partout, et décrit le modèle de contrôle d'accès
(RBAC) et de cloisonnement des données. L'objectif est de montrer que chaque route
a un rôle clair, que rien n'agit de façon surprenante, et que l'accès est vérifié
à plusieurs niveaux.

## 1. La discipline GET / POST

Le logiciel suit une règle simple et constante, conforme à la sémantique HTTP :

- **GET = consultation, sans effet de bord.** Une requête GET lit et affiche. Elle
  ne modifie jamais l'état du système. On peut la rejouer, la mettre en favori, la
  recharger sans conséquence. Exemples : afficher la liste des projets, voir une
  fiche, consulter le tableau de bord, télécharger un export.
- **POST = action qui modifie l'état.** Créer, valider, refuser, annuler, supprimer,
  importer : toute opération qui change les données passe par POST. Une telle requête
  n'est jamais déclenchée par un simple affichage, et elle est protégée par un jeton
  anti-CSRF.

Cette séparation n'est pas cosmétique. Elle évite les effets de bord accidentels
(un lien qui supprimerait en étant simplement visité, un robot d'indexation qui
déclencherait des actions), et elle rend le comportement du logiciel prévisible :
le verbe HTTP annonce l'intention.

Quelques pages acceptent **GET et POST sur la même route** (création, édition,
import) : le GET affiche le formulaire vide ou pré-rempli, le POST traite la
soumission. C'est le motif standard d'un formulaire Symfony, où une seule action
gère l'affichage et le traitement.

## 2. Inventaire des routes par domaine

Légende : (G) GET, (P) POST, (G/P) les deux sur la même route.

### Espace public (aucune authentification)
| Route | Méthode | Rôle |
|---|---|---|
| `app_home` | G | Vitrine publique |
| `app_machine_fiche` | G | Fiche publique d'une machine |
| `page_mentions`, `page_donnees`, `page_regles` | G | Pages légales (RGPD, règlement) |
| `app_login` | G/P | Connexion (G affiche, P authentifie) |
| `calendrier_ical` | G | Flux iCal, protégé par jeton dans l'URL |

### Espace étudiant (ROLE_ETUDIANT)
| Route | Méthode | Rôle |
|---|---|---|
| `projet_index`, `projet_show` | G | Consulter ses projets |
| `projet_new` | G/P | Déposer un projet |
| `projet_soumettre` | P | Soumettre un brouillon à validation |
| `projet_retracter` | P | Rétracter une demande en attente (retour brouillon) |
| `projet_supprimer` | P | Supprimer un de ses projets (brouillon ou en attente) |
| `projet_resoumettre` | P | Re-soumettre un projet refusé |
| `plan_ajouter`, `plan_supprimer` | P | Ajouter ou retirer un fichier de plan (statuts modifiables) |
| `reservation_creer` | G | Afficher la page de réservation (calendrier, créneaux, panier) |
| `reservation_ajouter`, `reservation_retirer` | P | Ajouter un créneau au panier, en retirer un |
| `reservation_verifier` | G | Page de vérification avant confirmation |
| `reservation_confirmer` | P | Confirmer le panier (crée les réservations sous verrou) |
| `reservation_disponibilite` | G | Fragments AJAX : densités du mois (JSON), créneaux du jour, machines d'un créneau |
| `reservation_annuler`, `reservation_reporter` | P | Annuler ou reporter un créneau |
| `reservation_reporter_page` | G | Page de report dédiée (calendrier + créneaux libres) |
| `plan_telecharger` | G | Télécharger le plan d'un projet |
| `calendrier_vue` | G | Voir le calendrier |
| `app_notifications` | G | Lire ses notifications |
| `app_notifications_tout_lire` | P | Marquer tout comme lu |

### Espace de validation (ROLE_FORMATEUR, ROLE_BDE)
| Route | Méthode | Rôle |
|---|---|---|
| `demande_index`, `demande_show` | G | Consulter les demandes à valider |
| `demande_valider`, `demande_refuser` | P | Statuer sur une demande |
| `pilotage_dashboard` | G | Tableau de bord de pilotage |
| `pilotage_supervision` | G | Activité (analyse temporelle ; libellé menu « Activité ») |

### Espace d'administration (ROLE_ADMIN)
| Route | Méthode | Rôle |
|---|---|---|
| `admin_machine_index` | G | Liste du parc machines |
| `admin_machine_new`, `admin_machine_edit` | G/P | Créer ou modifier une machine |
| `admin_machine_delete` | P | Supprimer une machine |
| `admin_stock_index`, `admin_stock_predictions` | G | Consulter le stock et les prévisions |
| `admin_stock_new`, `admin_stock_edit` | G/P | Créer ou modifier un consommable |
| `admin_stock_delete` | P | Supprimer un consommable |
| `admin_utilisateur_index`, `admin_utilisateur_show` | G | Gérer les comptes |
| `admin_utilisateur_new`, `admin_utilisateur_edit` | G/P | Créer ou modifier un compte |
| `admin_utilisateur_lever_sanction`, `admin_utilisateur_batch` | P | Actions sur les comptes |
| `admin_utilisateur_import`, `admin_utilisateur_import_confirmer` | G/P, P | Import en masse |
| `admin_vitrine_index`, `admin_vitrine_edit` | G, G/P | Gérer la page d'accueil de la vitrine |
| `admin_galerie_index` | G | Curation de la galerie « projets réalisés » |
| `admin_galerie_basculer` | P | Mettre en avant ou retirer un projet de la galerie |
| `admin_galerie_image_plan`, `admin_galerie_image_upload` | P | Définir l'image de carte (plan réutilisé ou téléversé) |
| `admin_galerie_image_retirer` | P | Retirer l'image de carte (retour au placeholder) |
| `admin_projet_index` | G | Liste de tous les projets (statut, cycle de vie) |
| `admin_projet_transition` | P | Faire avancer un projet via une transition légale |
| `admin_projet_supprimer` | P | Supprimer un projet |
| `admin_journal` | G | Journal d'audit |
| `pilotage_export_reservations`, `pilotage_export_supervision_xlsx` | G | Exports CSV et XLSX |

On note la régularité : toute suppression et toute action sensible est en POST,
toute consultation est en GET. Aucune route ne fait autre chose que ce que son nom
et son verbe annoncent.

## 3. Le contrôle d'accès basé sur les rôles (RBAC)

### Le principe du RBAC

Le RBAC (Role-Based Access Control, contrôle d'accès basé sur les rôles) consiste
à n'accorder les permissions qu'en fonction du rôle de l'utilisateur, et non au cas
par cas. On définit quelques rôles, on attache des permissions à ces rôles, et on
attribue un ou plusieurs rôles à chaque compte. L'intérêt : les règles d'accès sont
centralisées, lisibles, et faciles à auditer.

### Les rôles du logiciel et leur hiérarchie

Quatre rôles, organisés en hiérarchie (un rôle supérieur hérite des permissions des
rôles inférieurs) :

```
ROLE_ADMIN ─── hérite de ─→ ROLE_FORMATEUR ─── hérite de ─→ ROLE_ETUDIANT
                        └─→ ROLE_BDE        ─── hérite de ─→ ROLE_ETUDIANT
```

- **ROLE_ETUDIANT** : dépose des projets, réserve des créneaux, consulte son espace.
- **ROLE_FORMATEUR** et **ROLE_BDE** : valident les demandes, accèdent au pilotage.
  Ils héritent des droits étudiant.
- **ROLE_ADMIN** : administration complète (parc, stock, comptes, vitrine). Il hérite
  de formateur et BDE.

La hiérarchie évite de répéter les permissions : il suffit de donner à un formateur
le rôle `ROLE_FORMATEUR` pour qu'il dispose aussi de tout ce qu'un étudiant peut
faire.

### La défense en profondeur : trois niveaux de vérification

L'accès se vérifie à trois niveaux successifs plutôt qu'à un seul endroit, de
sorte qu'un oubli à un niveau soit rattrapé par le suivant.

1. **Par zone d'URL (le pare-feu, `access_control`).** Chaque préfixe d'URL exige
   un rôle minimal avant même d'atteindre le code applicatif :
   - `/admin` exige `ROLE_ADMIN`,
   - `/pilotage` exige un rôle de pilotage (admin, formateur ou BDE),
   - `/demandes` exige formateur ou BDE,
   - `/projets` exige étudiant,
   - la vitrine et le login restent publics.

2. **Par contrôleur (l'attribut `IsGranted`).** Chaque contrôleur redéclare le rôle
   requis au-dessus de sa classe. C'est une seconde barrière, explicite et locale,
   qui protège même si la configuration de zone changeait.

3. **Par ressource (le voter).** Pour les actions sur un objet précis, un rôle ne
   suffit pas : il faut être autorisé sur **cet** objet. Le `ProjetVoter` vérifie
   qu'un étudiant n'agit que sur ses propres projets (voir section suivante).

Cette redondance est volontaire : c'est le principe de défense en profondeur. Une
faille à un niveau ne suffit pas à contourner l'ensemble.

## 4. Le cloisonnement des données (isolation par propriétaire)

### Multi-tenant : la notion, et ce que fait réellement ce logiciel

Un logiciel **multi-tenant** héberge plusieurs organisations (tenants) sur une même
instance, en garantissant qu'aucune ne voit les données d'une autre. La règle d'or
y est : chaque requête de données doit être filtrée par l'identifiant du tenant, et
ce filtrage ne doit jamais dépendre d'un paramètre fourni par le client (sans quoi
un utilisateur pourrait demander les données d'un autre tenant).

Ce logiciel n'est pas multi-tenant au sens strict : il sert une seule organisation
(le FabLab du Campus). Il applique en revanche un **cloisonnement par propriétaire**,
qui repose sur la même discipline de vérification : un étudiant ne doit accéder
qu'à ses propres projets et réservations, jamais à ceux d'un autre.

### Comment le cloisonnement est appliqué

Le `ProjetVoter` porte cette règle. Pour les attributs `PROJET_VIEW` et `PROJET_EDIT`,
il calcule si l'utilisateur est le propriétaire du projet (`projet.etudiant === user`)
ou un administrateur, et n'autorise que dans ces cas. Point important, conforme aux
bonnes pratiques : la décision se fonde sur l'identité de l'utilisateur authentifié
(côté serveur), jamais sur un identifiant transmis dans la requête. Un étudiant ne
peut donc pas forger une URL pour consulter le projet d'un camarade.

Le même esprit s'applique aux vues filtrées : la liste des projets d'un étudiant, ses
réservations à venir, ses notifications, sont toutes requêtées en filtrant sur
l'utilisateur courant, et non sur un paramètre d'URL.

### Les vérifications qu'impose ce modèle

Pour qu'un cloisonnement (par propriétaire ou par tenant) tienne, plusieurs contrôles
sont nécessaires, et présents ici :

- **Filtrer côté données, pas seulement à l'affichage.** Cacher un bouton ne protège
  rien : la requête elle-même doit exclure ce qui ne regarde pas l'utilisateur.
- **Fonder la décision sur l'identité serveur.** L'utilisateur courant vient du jeton
  de session vérifié, jamais d'un champ que le client pourrait modifier.
- **Vérifier à l'accès direct.** Toute route qui reçoit l'identifiant d'une ressource
  (afficher, modifier, supprimer un projet) passe par le voter avant d'agir, ce qui
  bloque l'accès direct par URL forgée.
- **Anonymiser ce qui est partagé.** Le calendrier de disponibilité montre l'état
  d'un créneau (libre, occupé, complet) sans révéler qui a réservé : l'information
  utile est partagée, l'information personnelle reste cloisonnée.

## 5. Points clés à retenir

- GET consulte sans effet de bord, POST agit et porte un jeton anti-CSRF : le verbe
  HTTP annonce l'intention, rien n'agit par surprise.
- Le RBAC s'appuie sur quatre rôles hiérarchisés et se vérifie à trois niveaux : zone
  d'URL, contrôleur, ressource. C'est de la défense en profondeur.
- Le cloisonnement par propriétaire (proche du multi-tenant) se décide toujours
  d'après l'identité serveur, jamais d'après un paramètre client, et se filtre au
  niveau des données, pas seulement de l'affichage.
- L'information partagée (disponibilité d'un créneau) est anonymisée ; l'information
  personnelle reste isolée.
