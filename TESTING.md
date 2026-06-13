# TESTING : guide rapide

Aide-mémoire pour lancer, tester et vérifier GeniusLab. Pour le détail du socle
Docker, voir `docs/DOCKER-TESTING.md`. Pour les commandes courtes, `make` seul
liste les cibles.

## Prérequis

Docker et Docker Compose. Rien d'autre : PHP 8.5, PostgreSQL et les dépendances
vivent dans les conteneurs.

## Démarrer l'application

```bash
make build      # (re)construit les images (première fois ou après changement Docker)
make up         # démarre tout, attend que ce soit prêt
make db         # crée la base et joue les migrations
```

L'application est alors sur https://localhost (accepter le certificat TLS
auto-généré au premier accès).

Pour amorcer les contenus de la vitrine (textes et image d'accroche) :

```bash
docker compose exec php bin/console app:init-vitrine
```

Pour créer un compte administrateur :

```bash
docker compose exec php bin/console app:create-admin
```

## Lancer les tests

```bash
make test       # toute la suite (PHPUnit, base SQLite en mémoire, APP_ENV=test)
```

Un test ciblé :

```bash
docker compose exec -e APP_ENV=test php bin/phpunit tests/Service/ReservationServiceTest.php
```

La suite couvre les services critiques (réservation, sanctions, cycle projet,
report), la sécurité (authentification), et le CRUD machines.

## Vérifications rapides sans Docker

Validation syntaxique de tous les templates et du PHP, utile en cours d'édition :

```bash
# Lint PHP de tous les fichiers source
find src -name '*.php' -exec php -l {} \;

# Tirets cadratins interdits dans le texte FR (doit ne rien renvoyer)
grep -rn '—' templates/ src/ assets/
```

## Tester les parcours d'administration

Les écrans d'admin sont le cœur fonctionnel. Parcours à dérouler manuellement
après `make up`, connecté comme administrateur :

### Utilisateurs

- Liste : la recherche filtre en direct, les colonnes triables réordonnent, le
  tableau a un ascenseur interne (en-tête figé au défilement).
- Cliquer un nom ouvre sa fiche : statut, rôles, compteur de sanctions sur 5,
  ses projets. La levée de sanction réactive le compte sous le seuil.
- Création : un e-mail hors domaine `@cci.re` doit être refusé.

### Machines et stock

- Création, édition, suppression (la suppression demande confirmation).
- Stock : filtres par catégorie (chips), alerte synthétique en tête quand des
  articles passent sous le seuil.

### Contenu éditorial

- Éditer un bloc texte, puis un bloc image (upload) : la vignette doit
  apparaître, et l'image d'accroche se refléter sur la page d'accueil publique.

### Journal

- Toute action admin (création, suppression, levée de sanction) doit y laisser
  une trace, recherchable.

## Tester le chemin critique : réservation

Le scénario le plus sensible (capacité maximale du FabLab, BF_3.9) :

- Réserver un créneau jusqu'à la capacité de 15 personnes : la réservation qui
  dépasse doit être refusée avec un message indiquant la place restante.
- Reporter puis annuler une réservation : une annulation à moins de trois jours
  doit déclencher une sanction sur le compte de l'étudiant.

Garantie de concurrence : la création de session verrouille la machine
(verrou pessimiste) avant de lire la capacité, à l'intérieur d'une transaction.
Deux réservations simultanées sur le même créneau sont donc évaluées l'une
après l'autre, y compris quand le créneau est encore vide : on ne peut pas
dépasser 15 personnes par une course entre deux requêtes.

Ces règles sont couvertes par `tests/Service/ReservationServiceTest.php` et
`tests/Service/SanctionServiceTest.php` ; les dérouler aussi à la main valide le
parcours complet de bout en bout.

## Dépannage de l'assemblage

### « phpoffice/phpspreadsheet requires ext-gd »

L'assembleur lance `composer` sur la machine locale, dont le PHP n'a pas
forcément les extensions `gd` et `zip` que PhpSpreadsheet déclare. L'assembleur
règle cela en amont : il déclare ces extensions comme fournies par la plateforme
cible (`composer config platform.ext-gd 1`), parce que l'environnement réel
(Docker, PHP 8.5) les fournit bien. Le `Dockerfile` installe d'ailleurs `gd` et
`zip`, donc la déclaration correspond à la réalité d'exécution. Aucune action
manuelle n'est nécessaire : relancer `./assembler-geniuslab.sh --kit . --reset`
suffit.

### Erreur « current working directory is outside of container mount namespace root »

Cette erreur sur `docker compose exec` (et la boucle du worker sur
`public/index.php` qui l'accompagne) vient du bind-mount `./:/app` combiné au
`working_dir /app`. Quand le conteneur est réutilisé après une recréation du
dossier projet, son répertoire de travail reste lié à un ancien namespace de
montage devenu invalide : Docker refuse alors tout `exec` et le worker ne
retrouve plus son point d'entrée. Ce n'est pas `public/index.php` qui manque sur
l'hôte, c'est le lien de montage du conteneur qui est rompu.

Correctif appliqué : l'assembleur démarre les conteneurs avec
`--force-recreate`, ce qui garantit un namespace de montage neuf.

Si l'erreur survient en cours d'usage (sans réassembler), recréez le conteneur
au lieu d'`exec` dessus :

    cd geniuslab
    docker compose down
    docker compose up -d --force-recreate

puis relancez votre commande, par exemple la création du compte admin.
