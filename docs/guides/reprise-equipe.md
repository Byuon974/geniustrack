# Bonnes pratiques Docker et reprise d'équipe

Ce guide rassemble les pratiques Docker du projet et le minimum à connaître pour reprendre le travail sans refaire les erreurs déjà rencontrées. Il s'adresse à toute personne de l'équipe qui récupère le projet (installation, opérations quotidiennes, dépannage). Les décisions de fond correspondantes : DEC-041 à DEC-047 du journal.

---

## 1. Les deux objets à ne pas confondre

Il y a deux choses distinctes, et les mélanger est la source des ennuis rencontrés :

```
Le KIT                          Le PROJET ASSEMBLÉ
(la source)                     (ce qui tourne)
──────────────                  ─────────────────────
geniuslab-kit/                  geniuslab/
  assembler-geniuslab.sh          src/ (copié depuis le kit)
  Makefile                        compose.yaml
  src/, templates/ (à copier)     vendor/ (installé)
  docs/                           la base de données Docker
```

Le kit contient le code métier et l'outillage. L'assembleur en fabrique un projet Symfony exécutable. **Le projet assemblé doit être un dossier FRÈRE du kit, jamais imbriqué dedans.** Une imbrication produit deux projets Docker superposés qui se disputent le port 443 (incident réellement vécu, voir section 5).

Organisation cible :

```
~/un-dossier/
├── geniuslab-kit/      ← le kit (source)
└── geniuslab/          ← le projet assemblé (frère, créé par l'assembleur)
```

L'assembleur vise désormais ce dossier frère par défaut et refuse toute destination située à l'intérieur du kit.

---

## 2. Le point d'entrée des opérations : le Makefile

Une fois le projet assemblé, toutes les opérations courantes passent par `make`, lancé depuis le dossier du projet assemblé. `make` seul liste les cibles.

```
make up        Démarre l'environnement
make down      Arrête l'environnement
make logs      Suit les logs de l'application
make sh        Ouvre un shell dans le conteneur applicatif
make test      Lance la suite de tests
make db        Joue les migrations
make reseed    Recharge les données de démonstration (sans vider la base)
make activer   Réactive un compte : make activer EMAIL=prof@cci.re
make reset     Remise à zéro TOTALE : make reset CONFIRME=oui
```

Pourquoi un Makefile et pas des scripts séparés : les opérations Docker se résument à de courtes commandes que Compose sait déjà faire. Un point d'entrée unique aux cibles courtes évite la duplication (chaque ancien script refaisait sa propre validation et son démarrage). L'assembleur et la sauvegarde restent des scripts à part, car ce sont des responsabilités plus larges.

---

## 3. Installation depuis zéro (nouvelle personne)

Prérequis sur la machine : Docker avec Docker Compose, et Composer ou la Symfony CLI (uniquement pour l'étape d'assemblage). Détails d'installation Docker dans `docker-et-tests.md`.

```bash
# 1. Décompresser le kit, se placer DANS le kit
cd geniuslab-kit

# 2. Assembler : crée le projet en frère (../geniuslab)
./assembler-geniuslab.sh --kit .

# 3. Aller dans le projet assemblé et l'amorcer
cd ../geniuslab
make up
make db
docker compose exec php bin/console app:create-admin   # votre compte admin
docker compose exec php bin/console app:init-vitrine    # textes vitrine
make reseed                                             # (optionnel) données de test
```

Puis ouvrir `https://localhost` (accepter le certificat auto-signé) et se connecter.

L'assembleur accepte `--dry-run` (montre les actions sans rien faire), `--help`, et `--dest CHEMIN` pour choisir une autre destination (toujours hors du kit).

---

## 4. Opérations quotidiennes

Travail normal : `make up` au début, `make down` à la fin. Pour voir ce qui se passe, `make logs`. Pour une commande Symfony ponctuelle, `make sh` puis `bin/console ...`, ou directement `docker compose exec php bin/console ...`.

Repartir de données fraîches sans tout casser : `make reseed`. Repartir vraiment de zéro : `make reset CONFIRME=oui` (la confirmation est obligatoire car l'opération est destructrice). Le reset détruit le volume de la base (`<projet>_database_data`), pas seulement les conteneurs : la base est donc réellement recréée à neuf depuis les entités, puis repeuplée par le seed. C'est le modèle tabula rasa du projet, où le seed sert à la fois de données de démonstration et de jeu de données pour la recette.

Compte bloqué (désactivé par erreur ou par sanctions) : `make activer EMAIL=...`. Un administrateur ne peut plus se verrouiller dehors : le système empêche un admin de se désactiver lui-même ou de désactiver le dernier admin actif (DEC-044).

---

## 5. Dépannage des cas déjà rencontrés

**« permission denied » sur le socket Docker.** L'utilisateur n'est pas dans le groupe docker. Soit lancer avec `sudo`, soit s'ajouter au groupe (`sudo usermod -aG docker $USER` puis se reconnecter).

**« port is already allocated » (443 ou 80).** Un autre projet Docker occupe déjà le port. La cause la plus fréquente est un projet imbriqué (voir plus bas). Pour voir les projets actifs et leur emplacement :

```bash
docker compose ls
```

Si un projet de trop apparaît, l'arrêter par son nom :

```bash
docker compose -p <nom-du-projet> down -v
```

Pour faire tourner volontairement deux instances en parallèle, surcharger les ports plutôt que de les laisser entrer en collision :

```bash
HTTPS_PORT=8443 HTTP_PORT=8080 make up
```

**Deux projets concurrents (`geniuslab` et `geniuslab-kit`).** Symptôme d'un projet imbriqué : `docker compose ls` montre un projet dont le compose est sous `.../geniuslab-kit/geniuslab/`. Nettoyage :

```bash
docker compose -p geniuslab down -v          # arrête et supprime l'imbriqué
rm -rf chemin/vers/geniuslab-kit/geniuslab   # supprime le dossier imbriqué
```

Ne garder que le projet assemblé en frère du kit. Le nouvel assembleur empêche que cette imbrication se reforme.

**Le calendrier ou une page semble vide.** Vérifier d'abord les données via une commande console (`make sh` puis `bin/console ...`) avant de suspecter l'affichage. Le calendrier est rendu côté serveur (pas de dépendance JavaScript), donc une page vide signale un manque de données, pas un script cassé.

**Page « Vous êtes hors ligne » alors que le serveur tourne.** Cette page est la page de repli de l'application web progressive (PWA), servie par le service worker quand le navigateur ne joint pas le serveur. Si elle persiste après le redémarrage des conteneurs, c'est le cache du service worker qui reste accroché à son ancien état, pas un problème serveur. Vérifier d'abord que le serveur répond : `curl -k -I https://localhost` (une réponse `HTTP/... 200` ou `302` confirme que le serveur va bien). Puis forcer le navigateur à reprendre contact : rechargement forcé (Ctrl+Shift+R). Si la page résiste, désinscrire le service worker dans les outils de développement (onglet Application ou Stockage, section Service Workers, « Désinscrire »), vider le stockage du site, puis recharger.

**`--reset` échoue sur « Permission non accordée » lors de la suppression du dossier.** Les conteneurs FrankenPHP tournent en root et créent des fichiers root sur l'hôte (le `var/` de cache et logs notamment), qu'un `rm` en utilisateur normal ne peut pas effacer. L'assembleur gère désormais ce cas : il tente d'abord un `rm` sans élévation, puis bascule sur `sudo rm` si des fichiers root résistent, sous la protection de gardes de sécurité (refus des chemins système, du home nu, des chemins trop courts). Si une suppression manuelle est nécessaire : `sudo rm -rf chemin/vers/geniuslab`.

---

## 6. Principes Docker retenus

- **Le port hôte est paramétrable** (`${HTTPS_PORT:-443}` dans le compose) : ne jamais coder un port en dur, surcharger par variable au besoin.
- **La sortie d'un générateur va à côté de la source, jamais dedans** : l'assembleur crée le projet en frère du kit.
- **Une opération destructrice exige une confirmation explicite** : `make reset CONFIRME=oui`.
- **Préférer la commande Compose ou console à un script maison** quand elle suffit : moins de code à maintenir, comportement standard.
- **Diagnostiquer sur les faits avant de refondre** : prouver où est le problème (logs, `docker compose ls`, flux de données) plutôt que supposer la cause.
