# Docker & testing : socle symfony-docker intégré

Le socle Docker officiel **symfony-docker** (dunglas, FrankenPHP + Caddy) est
désormais intégré au dépôt et adapté à GeniusLab. Plus besoin d'aller le chercher :
les fichiers sont à la racine.

## Fichiers du socle (repris du standard officiel)

| Fichier | Rôle | Adapté pour GeniusLab |
|---|---|---|
| `compose.yaml` | Services de base | **+ service PostgreSQL** (absent du standard par défaut) |
| `compose.override.yaml` | Surcharge dev | **+ port 5432 exposé, depends_on database** |
| `compose.prod.yaml` | Cible production | tel quel |
| `Dockerfile` | Image multi-stage FrankenPHP | **+ pdo_pgsql + pdo_sqlite** (PHP 8.5 du standard conservé) |
| `frankenphp/` | Caddyfile, entrypoint, conf.d | tel quel |
| `.dockerignore` | Exclusions de build | tel quel |
| `.github/workflows/ci.yaml` | CI officielle | tel quel |

Les seules modifications sont signalées ci-dessus : extensions PostgreSQL/SQLite
ajoutées, et le service PostgreSQL greffé (le standard le laisse à la recette Flex
de orm-pack ; on l'inscrit explicitement pour que `docker compose up` suffise).
La version PHP du standard (8.5) est conservée : voir DEC-001.

## Démarrage

```bash
docker compose build --pull --no-cache
docker compose up --wait
# → https://localhost (accepter le certificat TLS auto-généré)
```

Ou via les raccourcis : `make build` puis `make up`.

## Variables d'environnement

Créer un `.env` à la racine (ou laisser les défauts du compose) :

```
POSTGRES_DB=geniuslab
POSTGRES_USER=geniuslab
POSTGRES_PASSWORD=changeme-en-prod
POSTGRES_VERSION=16
```

Les défauts du compose.yaml (`app` / `!ChangeMe!`) fonctionnent en dev sans `.env`.

## Tester sans barrière

```bash
make test     # docker compose exec -e APP_ENV=test php bin/phpunit
```

Tests sur **SQLite en mémoire** (`.env.test` → `DATABASE_URL="sqlite:///:memory:"`).
Aucun service de base à démarrer pour tester, résultat identique sur toute machine.
PostgreSQL (service Docker) ne sert qu'au dev/prod, jamais touché par les tests.

## CI

La CI officielle (`.github/workflows/ci.yaml`) est reprise du standard. Elle build
l'image, démarre les services et lance les tests : même environnement qu'en local.

## Note sur Xdebug

Le Dockerfile officiel installe Xdebug dans l'étage **dev uniquement**, en mode
`off` par défaut (activable via la variable `XDEBUG_MODE`). C'est l'approche du
standard : présent mais inactif, sans coût quand on ne l'utilise pas. On garde tel
quel : c'est le réglage de référence, pas un ajout maison.

## Référence

Le dossier `docs/symfony-docker-ref/` contient les docs originales du projet
(existing-project, options, troubleshooting, makefile) pour approfondir.
