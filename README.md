# GeniusLab : kit applicatif (Symfony 7.4)

Plateforme de gestion du FabLab du Campus CCI Nord (La Réunion) : présentation,
soumission et validation de projets, réservation de machines, gestion du stock,
sanctions, tableau de bord, supervision analytique et export des données. Stack
**Symfony 7.4 LTS / PHP 8.5 / PostgreSQL / Twig + Symfony UX / FrankenPHP (Docker)**.

## ⚡ Démarrage rapide

Ce kit contient le **code métier**, pas le squelette Symfony complet. Le script
d'assemblage crée un projet exécutable, dans un dossier **frère** du kit (à côté,
jamais imbriqué dedans) :

```bash
# Depuis le dossier du kit :
./assembler-geniuslab.sh --kit .
# Le projet est créé en frère : ../geniuslab

cd ../geniuslab
make up                                                  # build + démarrage
make db                                                  # schéma de la base
docker compose exec php bin/console app:create-admin     # crée votre compte admin
docker compose exec php bin/console app:init-vitrine     # amorce les textes vitrine
make reseed                                              # (optionnel) données de test
```

Puis ouvrez **https://localhost** (acceptez le certificat auto-signé) et
connectez-vous avec le compte admin créé.

Les opérations courantes passent ensuite par `make` (tapez `make` seul pour la
liste). Voir `docs/guides/reprise-equipe.md` pour le guide complet d'équipe.

> Prérequis : Docker + Docker Compose, et composer **ou** la Symfony CLI (pour
> l'assemblage). Voir `docs/guides/docker-et-tests.md` pour l'installation Docker.
> Le script accepte `--dry-run` (montre les actions sans rien faire) et `--help`.

## 🛠️ Commandes (Makefile)

Depuis le dossier du projet assemblé, `make` seul liste les cibles. Les
principales :

| Commande | Effet |
|---|---|
| `make up` | Démarre l'environnement (build au besoin) |
| `make down` | Arrête l'environnement |
| `make logs` | Suit les logs de l'application |
| `make sh` | Ouvre un shell dans le conteneur applicatif |
| `make test` | Lance la suite de tests |
| `make db` | Joue les migrations |
| `make reseed` | Recharge les données de démonstration (sans vider la base) |
| `make activer EMAIL=…` | Réactive un compte désactivé |
| `make reset CONFIRME=oui` | Remise à zéro TOTALE (vide la base) |

Pour faire tourner deux instances sans conflit de port :
`HTTPS_PORT=8443 HTTP_PORT=8080 make up`. Le guide complet (installation,
opérations, dépannage des cas déjà rencontrés) est dans
`docs/guides/reprise-equipe.md`.

## Prérequis et installation des dépendances (hôte)

L'application tourne dans Docker (PHP 8.5 via FrankenPHP) : la version PHP de l'hôte n'exécute pas l'application. L'hôte a seulement besoin de Docker, de Composer (ou la Symfony CLI) pour l'étape d'assemblage, et de quelques extensions PHP que Composer exige pour résoudre les dépendances.

### Debian / Ubuntu (APT)

```bash
sudo apt update
sudo apt install -y docker.io docker-compose-plugin git unzip \
    composer php-cli php-iconv php-mbstring php-intl php-xml \
    php-curl php-zip php-pgsql php-gd php-sqlite3
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"   # reconnexion nécessaire ensuite
```

### Arch / Manjaro / EndeavourOS (Pacman)

```bash
sudo pacman -Syu --needed docker docker-compose git unzip \
    composer php php-gd php-pgsql php-intl php-sqlite
sudo systemctl enable --now docker.service
sudo usermod -aG docker "$USER"   # reconnexion nécessaire ensuite
```

Sur Arch, l'extension `iconv` (exigée par `symfony/skeleton`) est fournie par le paquet `php` mais désactivée par défaut. L'activer dans un fichier dédié, avec les autres extensions courantes du paquet `php` :

```bash
sudo tee /etc/php/conf.d/geniuslab.ini >/dev/null <<'EOF'
extension=iconv
extension=mbstring
extension=intl
extension=gd
extension=pdo_pgsql
extension=pgsql
extension=zip
extension=sodium
EOF
php -m | grep -E 'iconv|mbstring|intl|gd|pgsql|zip|sodium'   # vérification
```

> Si `composer create-project` se plaint encore d'une extension manquante (`ext-xxx is missing`), soit elle est dans le paquet `php` (l'ajouter au fichier conf.d ci-dessus), soit c'est un paquet séparé `php-xxx` à installer via pacman. Ces extensions hôte ne servent qu'à satisfaire Composer pendant l'assemblage ; l'application s'exécute avec le PHP 8.5 du conteneur.

### Après installation

```bash
./assembler-geniuslab.sh --kit . --reset
```

L'assembleur crée le squelette, copie le code métier, lance Docker et génère le schéma depuis les entités via `doctrine:schema:update --force --complete`. Le projet suit un modèle tabula rasa : la base est recréée à neuf et reconstruite par le seed (pas de migrations à maintenir). Pour peupler des données de test :

```bash
docker compose exec php bin/console app:charger-demo
```

## Qu'est-ce que ce kit contient

- `src/` : entités, services, controllers, sécurité (le code métier)
- `templates/` : vues Twig
- `config/` : services, sécurité, workflow, messenger
- `assets/` : styles et controllers Stimulus
- `tests/` : tests fonctionnels et unitaires (SQLite en mémoire)
- socle Docker : `compose.yaml`, `Dockerfile`, `frankenphp/`
- `.env` : configuration avec défauts prêts à l'emploi (dev)
- `assembler-geniuslab.sh` : assemblage en projet exécutable

## Documentation (dossier `docs/`)

La documentation suit le cadre **Diátaxis** : chaque document a une intention
unique (apprendre, faire, consulter, comprendre), ce qui évite de mélanger les
modes et facilite la navigation. Elle est rangée en sous-dossiers correspondants.

**Commencez par `docs/explications/reprise-equipe-guide.md`** : c'est le point
d'entrée qui présente l'architecture décisionnelle et oriente la lecture selon ce
que vous venez faire.

### `docs/demarrage/` : apprendre (tutoriel)

| Document | Sujet |
|---|---|
| `premier-tour.md` | Parcours guidé de zéro : assembler, démarrer, explorer, première action |

### `docs/guides/` : faire (how-to)

| Document | Sujet |
|---|---|
| `reprise-equipe.md` | Installer, faire tourner, opérer le projet au quotidien ; dépannage |
| `mise-en-production.md` | Variables d'environnement, droits d'upload, amorçage, sécurité avant ouverture |
| `docker-et-tests.md` | Socle Docker et exécution des tests |
| `recette-manuelle.md` | Scénarios de validation runtime des correctifs (à dérouler après assemblage) |

### `docs/reference/` : consulter (information)

| Document | Sujet |
|---|---|
| `journal-decisions.md` | Toutes les décisions (DEC-001 à 091), choix et alternatives écartées |
| `design-system.md` | Tokens, composants, accessibilité, règles d'usage |
| `composants-ui.md` | Bibliothèque de composants Twig (badge, table, états) |
| `audit-projet.md` | Couverture du cahier des charges, cohérence, points ouverts |
| `audit-securite.md` | Attaques simulées, failles corrigées, durcissements (OWASP 2026) |
| `audit-ui-ux.md` | Audit d'accessibilité et décisions UI/UX |

### `docs/explications/` : comprendre (discussion)

| Document | Sujet |
|---|---|
| `reprise-equipe-guide.md` | **Point d'entrée** : architecture décisionnelle, parcours de lecture, carte |
| `architecture.md` | Architecture complète (couches, données, sécurité, design, infra) |
| `architecture-couches.md` | Le monolithe modulaire en couches |
| `cadrage-technique.md` | Cadrage initial, méthode de travail, stack, justifications |
| `patterns-code.md` | Patterns transverses (verrou, workflow, ledger, voter, enums) |
| `reservation.md` | Cœur métier : règles, concurrence, annulation/report (BF_3.x) |
| `stock.md` | Gestion du stock et prédiction de rupture (BF_4.x) |
| `sanctions-notifications.md` | Sanctions et notifications, mécanique ledger (BF_6.2, BF_3.6) |
| `calendrier-disponibilite.md` | Calendrier par rôle, disponibilité free/busy (BF_3.22, BF_6.3) |
| `ui-iteration.md` | Comment l'interface a évolué : décisions visuelles et méthode |

## Tests

```bash
docker compose exec -e APP_ENV=test php bin/phpunit
```

Les tests tournent sur SQLite en mémoire : aucun service à démarrer, résultat
identique sur toute machine.

## Couverture du cahier des charges

Les 30 besoins fonctionnels (BF) de la matrice sont couverts par le code. Parmi les besoins non fonctionnels d'infrastructure, la sauvegarde dispose d'un script réel (voir ci-dessous) ; les autres (500 connexions, temps de réponse) relèvent du dimensionnement de déploiement. Détails dans `docs/explications/cadrage-technique.md` et `docs/reference/journal-decisions.md`.

## Sauvegarde de la base

Le script `sauvegarde-geniuslab.sh` réalise un dump PostgreSQL compressé avec rotation (rétention 14 jours par défaut), à lancer manuellement ou via cron :

```bash
./sauvegarde-geniuslab.sh                    # dump dans var/sauvegardes/
./sauvegarde-geniuslab.sh --help             # options (rétention, dry-run, verbose)
```

Le script lit les identifiants depuis le conteneur PostgreSQL, refuse un dump vide ou tronqué, et journalise dans `$HOME/.logs/`. Pour un cron quotidien à 2 h :

```
0 2 * * * /chemin/vers/sauvegarde-geniuslab.sh >> "$HOME/.logs/cron-sauvegarde.log" 2>&1
```

## Fonctionnalités opérationnelles notables

- Sanctions en modèle ledger : historique complet (qui, quand, pourquoi, qui a levé), le compteur est dérivé des lignes actives, jamais stocké. Le staff (admin, formateur, BDE) n'est jamais sanctionné.
- Centre de notifications in-app : badge de non-lues dans la barre, marquage lu à l'ouverture, en plus des mails. Schéma de type Laravel (indicateur `luLe` nullable).
- Tableau de bord en grille : cartes de statut à filet sémantique, utilisation machine et raccourci de validation côte à côte.
- Réservations à durée variable : heure de début au pas de 30 minutes, durée réglable de 30 minutes à 4 heures, contrôle de capacité et de chevauchement sur la durée réelle.
- Supervision analytique : page distincte du tableau de bord, taux d'utilisation des machines (capacité dérivée des horaires d'ouverture), activité de réservation par mois, fluctuations de consommables.
- Traçage automatique du stock : chaque ajustement écrit un mouvement daté dans un historique immuable (modèle ledger, comme les sanctions), avec motif catégorisé.
- Export des données en CSV et XLSX : trois jeux (réservations, taux machines, mouvements), réservé à l'administrateur (données nominatives).
- Galerie « projets réalisés » : page de curation dédiée où l'admin met en avant les projets terminés et choisit l'image de leur carte d'accueil (un fichier déjà joint au projet, copié vers le dossier public, ou une image téléversée). La page Vitrine y renvoie ; l'accueil affiche l'image retenue ou un pictogramme par défaut.

## Conventions

Architecture en couches stricte (Controller → Service → Repository → Entity), les
règles métier dans les services, jamais dans les controllers. Code et commentaires
en français. Zéro dépendance superflue : on adopte le standard Symfony plutôt que
des bundles tiers quand le natif suffit.

## Assemblage manuel (alternative au script)

Si vous préférez ne pas utiliser le script (créez le projet **hors du kit**,
pas à l'intérieur, pour éviter deux projets Docker imbriqués) :

```bash
composer create-project symfony/skeleton:"7.4.*" geniuslab && cd geniuslab
composer require webapp doctrine/doctrine-bundle \
  symfony/messenger symfony/mailer symfony/workflow symfony/uid symfony/form \
  symfony/validator symfony/security-bundle symfony/expression-language symfony/translation twig/twig
# puis copier src/ templates/ config/ assets/ tests/ public/ et .env du kit
# le schéma est généré depuis les entités par :
#   bin/console doctrine:schema:update --force --complete
```
