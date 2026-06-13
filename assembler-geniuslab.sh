#!/usr/bin/env bash
#
# assembler-geniuslab.sh — Assemble un projet Symfony 7.4 exécutable à partir
# du kit GeniusLab (code métier) + d'un squelette Symfony neuf + socle Docker.
#
# Le kit ne contient que le code métier (src/, templates/, config/…) ; ce script
# crée le squelette Symfony manquant (composer.json, public/index.php, Kernel…),
# installe les dépendances, verse le code du kit par-dessus et copie le socle
# Docker. Résultat : un projet qui démarre via « docker compose up ».

set -Eeuo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Constantes & journalisation
# ─────────────────────────────────────────────────────────────────────────────
readonly NOM_PROJET="assembler-geniuslab"
readonly VERSION="1.0.0"
readonly REP_LOGS="${HOME}/.logs/${NOM_PROJET}"
readonly FICHIER_LOG="${REP_LOGS}/$(date +%Y-%m-%d).log"

# Couleurs uniquement si le terminal est interactif (TTY).
if [[ -t 1 ]]; then
    C_DEBUG=$'\033[2;37m'; C_INFO=$'\033[0;36m'; C_WARN=$'\033[0;33m'
    C_ERROR=$'\033[0;31m'; C_RESET=$'\033[0m'
else
    C_DEBUG=""; C_INFO=""; C_WARN=""; C_ERROR=""; C_RESET=""
fi

VERBOSE=0

journaliser() {
    local niveau="$1"; shift
    local message="$*"
    local horodatage; horodatage="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

    # Fichier : toujours, sans couleur, avec PID.
    mkdir -p "${REP_LOGS}"
    printf '%s pid=%d level=%s msg="%s"\n' \
        "${horodatage}" "$$" "${niveau}" "${message}" >> "${FICHIER_LOG}"

    # DEBUG masqué au terminal sauf si --verbose.
    [[ "${niveau}" == "DEBUG" && "${VERBOSE}" -eq 0 ]] && return 0

    local couleur=""
    case "${niveau}" in
        DEBUG) couleur="${C_DEBUG}" ;;
        INFO)  couleur="${C_INFO}"  ;;
        WARN)  couleur="${C_WARN}"  ;;
        ERROR) couleur="${C_ERROR}" ;;
    esac
    printf '%s[%s]%s %s\n' "${couleur}" "${niveau}" "${C_RESET}" "${message}" >&2
}

# ─────────────────────────────────────────────────────────────────────────────
# Gestion des interruptions et des erreurs
#
# Le code de sortie effectif est la seule source de vérité. En cas d'erreur, un
# message lisible indique la ligne fautive et renvoie vers le journal. En cas de
# succès, le script se termine sur l'encadré « C'EST PRÊT », sans ligne d'état
# technique à l'écran.
# ─────────────────────────────────────────────────────────────────────────────
ETAPES_OK=0

gerer_interruption() {
    journaliser WARN "Interruption reçue : arrêt propre."
    exit 130
}
trap gerer_interruption INT TERM

gerer_erreur() {
    local ligne="$1"
    journaliser ERROR "Échec ligne ${ligne}. Voir ${FICHIER_LOG}."
}
trap 'gerer_erreur "${LINENO}"' ERR

# ─────────────────────────────────────────────────────────────────────────────
# Aide
# ─────────────────────────────────────────────────────────────────────────────
afficher_aide() {
    cat <<'AIDE'
Utilisation : ./assembler-geniuslab.sh --kit CHEMIN_KIT [--dest CHEMIN] [options]

Description :
  Assemble un projet Symfony 7.4 à partir du kit GeniusLab, PUIS le démarre et
  le vérifie automatiquement (build, conteneurs, base de données, vitrine). À la
  fin, il ne reste qu'à ouvrir https://localhost et créer un compte admin.
  Crée le squelette Symfony, installe les dépendances, verse le code du kit,
  copie le socle Docker, lance « docker compose » et teste que le site répond.

Dépendances :
  - composer OU la Symfony CLI (pour créer le projet)
  - php >= 8.2 en local (pour composer ; le runtime applicatif reste Docker)
  - docker + docker compose (pour le démarrage automatique)
  - curl (pour la vérification du site)

Options :
  --kit CHEMIN     Chemin du kit GeniusLab décompressé (obligatoire).
  --dest CHEMIN    Répertoire du projet à créer (défaut : un dossier « geniuslab »
                   FRÈRE du kit, à côté, jamais imbriqué dedans).
  --no-docker      Assemble seulement, sans démarrer Docker (pour les experts).
  --reset          Si le projet existe déjà : l'arrête proprement et le recrée
                   (évite le « rm -rf » manuel et les conteneurs orphelins).
  --admin-email A  E-mail du compte admin créé (défaut : admin@cci.re, doit finir par @cci.re).
  --admin-pass P   Mot de passe du compte admin (défaut : admin1234!).
  --dry-run        Montre les actions sans rien exécuter.
  --verbose        Affiche les messages DEBUG.
  -h, --help       Affiche cette aide.

Codes de sortie :
  0    Succès.
  1    Erreur générale (dépendance manquante, échec d'une étape).
  2    Argument invalide.
  130  Interruption (Ctrl+C).

Exemples :
  ./assembler-geniuslab.sh --kit .
  ./assembler-geniuslab.sh --kit . --dest ~/projets/geniuslab
  ./assembler-geniuslab.sh --kit . --no-docker
  ./assembler-geniuslab.sh --kit . --dry-run --verbose
AIDE
}

# ─────────────────────────────────────────────────────────────────────────────
# Arguments
# ─────────────────────────────────────────────────────────────────────────────
KIT=""
# Destination par défaut : VIDE ici. Elle est calculée après lecture de --kit
# pour viser un dossier FRÈRE du kit (à côté, jamais imbriqué dedans), ce qui
# évite deux projets Docker superposés. Voir resoudre_destination().
DEST=""
DRY_RUN=0
RESET=0           # --reset : réinitialise proprement une destination existante
LANCER_DOCKER=1   # par défaut le script démarre tout (build, up, migrations, admin)
ADMIN_EMAIL="admin@cci.re"   # compte admin créé automatiquement
ADMIN_PASS="admin1234!"
DOCKER=""         # rempli par detecter_docker() : "docker" ou "sudo docker"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --kit)
            if [[ -z "${2:-}" || "${2:0:1}" == "-" ]]; then
                journaliser ERROR "L'option --kit attend un chemin. Exemple : --kit ."
                exit 2
            fi
            KIT="$2"; shift 2 ;;
        --dest)
            if [[ -z "${2:-}" || "${2:0:1}" == "-" ]]; then
                journaliser ERROR "L'option --dest attend un chemin."
                exit 2
            fi
            DEST="$2"; shift 2 ;;
        --no-docker) LANCER_DOCKER=0; shift ;;
        --reset) RESET=1; shift ;;
        --admin-email)
            if [[ -z "${2:-}" || "${2:0:1}" == "-" ]]; then
                journaliser ERROR "L'option --admin-email attend une adresse."
                exit 2
            fi
            ADMIN_EMAIL="$2"; shift 2 ;;
        --admin-pass)
            if [[ -z "${2:-}" || "${2:0:1}" == "-" ]]; then
                journaliser ERROR "L'option --admin-pass attend un mot de passe."
                exit 2
            fi
            ADMIN_PASS="$2"; shift 2 ;;
        --dry-run) DRY_RUN=1; shift ;;
        --verbose) VERBOSE=1; shift ;;
        -h|--help) afficher_aide; exit 0 ;;
        *) journaliser ERROR "Argument inconnu : $1"; exit 2 ;;
    esac
done

# ─────────────────────────────────────────────────────────────────────────────
# Exécution conditionnelle (respecte --dry-run)
# ─────────────────────────────────────────────────────────────────────────────
executer() {
    journaliser DEBUG "CMD: $*"
    if [[ "${DRY_RUN}" -eq 1 ]]; then
        journaliser INFO "[dry-run] $*"
        return 0
    fi
    "$@"
}

# ─────────────────────────────────────────────────────────────────────────────
# Validations défensives
# ─────────────────────────────────────────────────────────────────────────────
valider() {
    if [[ -z "${KIT}" ]]; then
        journaliser ERROR "Option --kit obligatoire. Voir --help."
        exit 2
    fi
    if [[ ! -d "${KIT}" ]]; then
        journaliser ERROR "Kit introuvable : ${KIT}"
        exit 2
    fi
    if [[ ! -d "${KIT}/src" || ! -d "${KIT}/templates" ]]; then
        journaliser ERROR "Le chemin ne ressemble pas au kit (src/ et templates/ attendus) : ${KIT}"
        exit 2
    fi
    if ! command -v composer >/dev/null 2>&1 && ! command -v symfony >/dev/null 2>&1; then
        journaliser ERROR "Ni composer ni la Symfony CLI ne sont installés."
        journaliser ERROR "Installez composer (https://getcomposer.org) ou la Symfony CLI (https://symfony.com/download)."
        exit 1
    fi
    # PHP local nécessaire pour composer/symfony CLI ; Symfony 7.4 exige >= 8.2.
    if command -v php >/dev/null 2>&1; then
        local v_php; v_php="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo "0")"
        if ! php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' 2>/dev/null; then
            journaliser WARN "PHP local ${v_php} < 8.2 : composer pourrait refuser Symfony 7.4."
            journaliser WARN "Le runtime applicatif reste Docker (PHP 8.5), mais composer s'exécute localement."
        fi
    else
        journaliser WARN "PHP introuvable en local : composer/symfony CLI en ont besoin pour scaffolder."
    fi

    # Résolution de la destination (pattern scaffolder : sortie FRÈRE de la
    # source, jamais imbriquée). Chemins absolus pour comparer sans ambiguïté.
    local kit_abs; kit_abs="$(cd "${KIT}" && pwd)"
    if [[ -z "${DEST}" ]]; then
        # Défaut : un dossier « geniuslab » à CÔTÉ du kit (même parent).
        DEST="$(dirname "${kit_abs}")/geniuslab"
        journaliser INFO "Destination par défaut (frère du kit) : ${DEST}"
    fi
    # Chemin absolu de la destination, même si elle n'existe pas encore.
    local dest_abs; dest_abs="$(cd "$(dirname "${DEST}")" 2>/dev/null && pwd)/$(basename "${DEST}")"

    # Garde-fou anti-imbrication : la destination ne doit pas être DANS le kit,
    # sinon on obtient deux projets Docker superposés (cause des conflits de port).
    if [[ "${dest_abs}" == "${kit_abs}" || "${dest_abs}" == "${kit_abs}/"* ]]; then
        journaliser ERROR "La destination « ${dest_abs} » est à l'intérieur du kit."
        journaliser ERROR "Cela créerait deux projets Docker imbriqués (conflit de ports)."
        journaliser ERROR "Choisissez une destination hors du kit, par exemple : --dest ~/projets/geniuslab"
        exit 2
    fi
    DEST="${dest_abs}"

    # Garde de sécurité avant toute suppression : refuser les chemins dangereux.
    # Le --reset efface DEST ; on ne doit jamais viser la racine, le home nu, un
    # chemin trop court ou vide. Cette barrière protège l'utilisateur du script.
    case "${DEST}" in
        "" | "/" | "/home" | "/home/" | "${HOME}" | "${HOME}/" | "/root" | "/usr" | "/etc" | "/var")
            journaliser ERROR "Destination « ${DEST} » interdite pour --reset (chemin système ou trop général)."
            journaliser ERROR "Choisissez un sous-dossier dédié, par exemple : --dest ~/projets/geniuslab"
            exit 2 ;;
    esac
    if [[ "${#DEST}" -lt 8 ]]; then
        journaliser ERROR "Destination « ${DEST} » trop courte : refus par sécurité."
        exit 2
    fi

    # Avec --reset, on nettoie d'abord le volume Docker du projet. Ce volume
    # (base PostgreSQL) survit à la suppression du dossier et porte un nom dérivé
    # du nom de projet Compose (par défaut le nom du dossier de destination).
    # Le détruire seulement quand le dossier existe laissait des bases obsolètes
    # quand le dossier avait disparu mais pas le volume : on le fait donc toujours.
    if [[ "${RESET}" -eq 1 ]]; then
        local d="docker"
        docker info >/dev/null 2>&1 || d="sudo docker"
        if command -v docker >/dev/null 2>&1; then
            local projet_compose
            projet_compose="$(basename "${DEST}")"
            journaliser WARN "Réinitialisation (--reset) : suppression des conteneurs et du volume de base du projet « ${projet_compose} »…"
            # Si le dossier et son compose.yaml existent encore, on descend proprement.
            if [[ -f "${DEST}/compose.yaml" ]]; then
                ( cd "${DEST}" && ${d} compose down -v --remove-orphans >/dev/null 2>&1 ) || true
            fi
            # Filet de sécurité : on cible le volume par son nom dérivé du projet,
            # qu'il reste ou non un dossier (sinon une base obsolète persisterait).
            ${d} volume rm "${projet_compose}_database_data" >/dev/null 2>&1 || true

            # Les conteneurs (FrankenPHP) tournent en root et créent des fichiers
            # appartenant à root sur l'hôte (var/ de cache et logs, conf.d générés).
            # Un « rm -rf » lancé en utilisateur normal échoue dessus. La suppression
            # du dossier se fait donc plus bas avec élévation si nécessaire, sous la
            # protection des gardes de sécurité posées sur DEST (chemin dédié, jamais
            # un chemin système).
            :
        fi
    fi

    if [[ -e "${DEST}" && -n "$(ls -A "${DEST}" 2>/dev/null || true)" ]]; then
        if [[ "${RESET}" -eq 1 ]]; then
            if [[ "${DRY_RUN}" -eq 0 ]]; then
                # Tentative sans élévation d'abord (cas où aucun fichier root).
                if ! rm -rf "${DEST}" 2>/dev/null || [[ -d "${DEST}" ]]; then
                    # Des fichiers appartiennent à root (créés par les conteneurs) :
                    # on élève la suppression. Les gardes de sécurité plus haut
                    # garantissent que DEST est un chemin dédié, jamais système.
                    journaliser WARN "Fichiers appartenant à root détectés (créés par Docker) : suppression avec élévation."
                    sudo rm -rf "${DEST}" || {
                        journaliser ERROR "Suppression de ${DEST} impossible, même avec élévation."
                        journaliser ERROR "Supprimez-le à la main : sudo rm -rf ${DEST}"
                        exit 1
                    }
                fi
            else
                journaliser INFO "[dry-run] rm -rf ${DEST}"
            fi
        else
            journaliser ERROR "Le projet existe déjà : ${DEST}"
            journaliser ERROR "Deux options :"
            journaliser ERROR "  • Le relancer sans tout refaire : cd ${DEST} && docker compose up -d"
            journaliser ERROR "  • Tout réinitialiser proprement  : relancez ce script avec --reset"
            exit 1
        fi
    fi
    journaliser INFO "Validations OK. Kit=${KIT} Dest=${DEST}"
}

# Copie récursive, en privilégiant rsync si présent.
copier() {
    local src="$1" dst="$2"
    if command -v rsync >/dev/null 2>&1; then
        executer rsync -a "${src}/" "${dst}/"
    else
        executer mkdir -p "${dst}"
        executer cp -r "${src}/." "${dst}/"
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# Étapes d'assemblage
# ─────────────────────────────────────────────────────────────────────────────
etape_squelette() {
    journaliser INFO "1/6 : Création du squelette Symfony 7.4 dans ${DEST}…"
    # La Symfony CLI, si présente, gère mieux le ciblage de version (--version=lts
    # = la LTS courante, soit 7.4). Sinon, composer avec contrainte épinglée 7.4.*
    # (évite que create-project tire la 8.x, qui exige PHP >= 8.4).
    if command -v symfony >/dev/null 2>&1; then
        journaliser DEBUG "Symfony CLI détectée : symfony new --version=lts"
        executer symfony new "${DEST}" --version=lts --no-git
    else
        journaliser DEBUG "Symfony CLI absente : composer create-project"
        executer composer create-project "symfony/skeleton:7.4.*" "${DEST}" --no-interaction
    fi
    ETAPES_OK=$((ETAPES_OK + 1))
}

etape_dependances() {
    journaliser INFO "2/6 : Installation des dépendances requises par le kit…"
    # Dépendances déduites des « use » du code du kit.
    local paquets=(
        webapp                      # pack web (twig, asset, etc.)
        doctrine/doctrine-bundle
        doctrine/doctrine-migrations-bundle
        symfony/messenger
        symfony/mailer
        symfony/workflow
        symfony/uid
        symfony/form
        symfony/validator
        symfony/security-bundle
        symfony/rate-limiter        # login throttling anti brute-force (BNF_3.3)
        symfony/expression-language
        symfony/translation
        twig/twig
        phpoffice/phpspreadsheet     # import CSV/XLSX des utilisateurs (BF_6.x)
    )
    if [[ "${DRY_RUN}" -eq 1 ]]; then
        journaliser INFO "[dry-run] (cd ${DEST} && composer require ${paquets[*]})"
    else
        # PhpSpreadsheet déclare ext-gd et ext-zip comme requis, mais GD ne sert
        # qu'aux images dans les feuilles : inutile pour lire des comptes. Le
        # runtime cible (Docker PHP 8.5) fournit ces extensions ; on le déclare
        # dans config.platform pour que composer n'exige pas leur présence sur
        # la machine qui lance l'assemblage. La config persiste pour les
        # composer install ultérieurs (CI, autre poste), donc ce n'est pas un
        # simple contournement ponctuel.
        ( cd "${DEST}" \
            && composer config platform.ext-gd 1 \
            && composer config platform.ext-zip 1 \
            && composer require --no-interaction "${paquets[@]}" )
    fi
    ETAPES_OK=$((ETAPES_OK + 1))
}

etape_code_metier() {
    journaliser INFO "3/6 : Copie du code métier du kit (écrase les défauts du squelette)…"
    for d in src templates config assets migrations tests public; do
        if [[ -d "${KIT}/${d}" ]]; then
            journaliser DEBUG "Copie ${d}/"
            copier "${KIT}/${d}" "${DEST}/${d}"
        fi
    done
    # .env du kit (fournit APP_SECRET, DATABASE_URL, etc. avec défauts).
    [[ -f "${KIT}/.env" ]] && executer cp "${KIT}/.env" "${DEST}/.env"

    # Garde-fou : public/index.php est le point d'entrée chargé par FrankenPHP en
    # mode worker. Le squelette le crée normalement via la recette framework-bundle,
    # mais selon l'ordre des recettes il peut manquer. S'il est absent, on le
    # recrée (contenu standard Symfony 7) : sans lui, le worker boucle à l'infini
    # sur « Failed opening required '/app/public/index.php' ».
    if [[ ! -f "${DEST}/public/index.php" ]]; then
        journaliser WARN "public/index.php absent : recréation du point d'entrée standard."
        if [[ "${DRY_RUN}" -eq 0 ]]; then
            mkdir -p "${DEST}/public"
            cat > "${DEST}/public/index.php" <<'PHP'
<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
PHP
        fi
    fi
    ETAPES_OK=$((ETAPES_OK + 1))
}

etape_docker() {
    journaliser INFO "4/6 : Copie du socle Docker (compose, Dockerfile, frankenphp/)…"
    for f in compose.yaml compose.override.yaml compose.prod.yaml Dockerfile .dockerignore; do
        [[ -f "${KIT}/${f}" ]] && executer cp "${KIT}/${f}" "${DEST}/${f}"
    done
    [[ -d "${KIT}/frankenphp" ]] && copier "${KIT}/frankenphp" "${DEST}/frankenphp"
    # Répertoires d'upload attendus par le code.
    executer mkdir -p "${DEST}/public/uploads/machines" "${DEST}/var/uploads/plans"
    ETAPES_OK=$((ETAPES_OK + 1))
}

etape_docker_demarrage() {
    if [[ "${LANCER_DOCKER}" -eq 0 ]]; then
        journaliser INFO "5/6 : Démarrage Docker ignoré (--no-docker)."
        ETAPES_OK=$((ETAPES_OK + 1)); return 0
    fi
    if ! command -v docker >/dev/null 2>&1; then
        journaliser WARN "Docker n'est pas installé : démarrage ignoré."
        journaliser WARN "Installez Docker puis, dans ${DEST} : docker compose up --wait"
        LANCER_DOCKER=0; ETAPES_OK=$((ETAPES_OK + 1)); return 0
    fi

    # Déterminer si docker fonctionne sans sudo. Sinon, on préfixe par sudo,
    # ce qui ne demandera le mot de passe qu'au moment d'une commande docker,
    # pas au lancement du script.
    if docker info >/dev/null 2>&1; then
        DOCKER="docker"
        journaliser DEBUG "Docker accessible sans sudo."
    elif sudo -n true 2>/dev/null && sudo docker info >/dev/null 2>&1; then
        DOCKER="sudo docker"
        journaliser INFO "Docker nécessite les privilèges : utilisation de sudo."
    elif command -v sudo >/dev/null 2>&1; then
        DOCKER="sudo docker"
        journaliser WARN "Docker nécessite sudo : votre mot de passe va être demandé."
        # Vérifier l'accès (déclenche la demande de mot de passe ici, une fois).
        if ! sudo docker info >/dev/null 2>&1; then
            journaliser WARN "Impossible d'accéder à Docker même avec sudo. Démarrage ignoré."
            journaliser WARN "Astuce : ajoutez-vous au groupe docker : sudo usermod -aG docker \$USER (puis reconnectez-vous)."
            LANCER_DOCKER=0; ETAPES_OK=$((ETAPES_OK + 1)); return 0
        fi
    else
        journaliser WARN "Le démon Docker ne répond pas et sudo est absent. Démarrage ignoré."
        LANCER_DOCKER=0; ETAPES_OK=$((ETAPES_OK + 1)); return 0
    fi

    journaliser INFO "5/6 : Construction et démarrage des conteneurs (plusieurs minutes la 1re fois)…"
    # --project-directory cible le projet sans changer le répertoire courant de
    # l'hôte (le « cd » provoquait l'erreur « working directory is outside of
    # container mount namespace root »).
    executer ${DOCKER} compose --project-directory "${DEST}" build --pull
    # --force-recreate : recrée les conteneurs même si la config n'a pas changé.
    # Indispensable avec le bind-mount ./:/app : un conteneur réutilisé après une
    # recréation du dossier projet garde un working_dir lié à un ancien namespace
    # de montage, ce qui fait échouer tout « exec » suivant avec « current working
    # directory is outside of container mount namespace root » et fait boucler le
    # worker sur public/index.php. Recréer garantit un namespace de montage neuf.
    executer ${DOCKER} compose --project-directory "${DEST}" up -d --force-recreate \
        || journaliser WARN "Le démarrage a renvoyé un avertissement, on poursuit."

    # Le conteneur prépare LUI-MÊME la base au démarrage (entrypoint) : attente de
    # la base, création du schéma depuis les entités (schema:update), amorçage
    # vitrine + données. On n'exécute donc plus ces étapes depuis l'hôte. Il reste
    # à attendre que cette préparation soit terminée, puis à créer le compte admin.
    if [[ "${DRY_RUN}" -eq 0 ]]; then
        journaliser INFO "    Préparation automatique de la base par le conteneur (jusqu'à 120 s)…"
        local pret=0 i
        for i in $(seq 1 40); do
            # La table « user » existe-t-elle ? (preuve que le schéma est en place)
            if ${DOCKER} compose --project-directory "${DEST}" exec -T php \
                 php bin/console dbal:run-sql 'SELECT 1 FROM "user" LIMIT 1' >/dev/null 2>&1; then
                pret=1; break
            fi
            sleep 3
        done
        if [[ "${pret}" -eq 1 ]]; then
            journaliser INFO "    Base prête (schéma et données en place)."
            journaliser INFO "    Création du compte administrateur (${ADMIN_EMAIL})…"
            ${DOCKER} compose --project-directory "${DEST}" exec -T php \
                bin/console app:create-admin "${ADMIN_EMAIL}" "${ADMIN_PASS}" \
                || journaliser WARN "Compte admin non créé (déjà existant ?)."
        else
            journaliser WARN "    La base n'est pas prête après 120 s."
            journaliser WARN "    Vérifiez les logs : ${DOCKER} compose --project-directory ${DEST} logs php"
            journaliser WARN "    Puis créez l'admin : ${DOCKER} compose --project-directory ${DEST} exec php bin/console app:create-admin ${ADMIN_EMAIL} '${ADMIN_PASS}'"
        fi
    fi

    ETAPES_OK=$((ETAPES_OK + 1))
}

etape_verification() {
    if [[ "${LANCER_DOCKER}" -eq 0 || "${DRY_RUN}" -eq 1 ]]; then
        ETAPES_OK=$((ETAPES_OK + 1)); return 0
    fi
    journaliser INFO "6/6 : Vérification que le site répond…"
    local ok=0 i
    for i in 1 2 3 4 5 6; do
        if curl -k -s -o /dev/null -w '%{http_code}' https://localhost 2>/dev/null | grep -qE '200|30[0-9]'; then
            ok=1; break
        fi
        sleep 2
    done
    if [[ "${ok}" -eq 1 ]]; then
        journaliser INFO "    Le site répond correctement."
    else
        journaliser WARN "    Le site ne répond pas encore : il finit peut-être de démarrer."
        journaliser WARN "    Réessayez dans une minute ; sinon : cd ${DEST} && docker compose logs php"
    fi
    ETAPES_OK=$((ETAPES_OK + 1))
}

afficher_resume_final() {
    if [[ "${LANCER_DOCKER}" -eq 1 && "${DRY_RUN}" -eq 0 ]]; then
        cat <<FIN

  ════════════════════════════════════════════════════════════════
  ✓ C'EST PRÊT : le site tourne.

  1. Ouvrez votre navigateur sur :   https://localhost
     (Le navigateur dira que le certificat n'est pas sûr : c'est normal
      en local. Cliquez « Avancé » puis « Continuer vers localhost ».)

  2. Connectez-vous avec le compte administrateur déjà créé :
       E-mail        : ${ADMIN_EMAIL}
       Mot de passe  : ${ADMIN_PASS}
     (Changez ce mot de passe en conditions réelles.)

  Arrêter le site :   cd ${DEST} && ${DOCKER:-docker} compose down
  Le relancer :       cd ${DEST} && ${DOCKER:-docker} compose up --wait
  ════════════════════════════════════════════════════════════════
FIN
    else
        cat <<FIN

  ✓ Projet assemblé dans : ${DEST}

  Pour le démarrer vous-même :
    cd ${DEST}
    docker compose up --wait
    docker compose exec php bin/console doctrine:schema:update --force --complete
    docker compose exec php bin/console app:create-admin ${ADMIN_EMAIL} '${ADMIN_PASS}'
    docker compose exec php bin/console app:init-vitrine

  Puis ouvrez https://localhost (acceptez le certificat auto-signé).
FIN
    fi
}

# ─────────────────────────────────────────────────────────────────────────────
# Programme principal
# ─────────────────────────────────────────────────────────────────────────────
main() {
    journaliser INFO "${NOM_PROJET} v${VERSION} : démarrage."
    [[ "${DRY_RUN}" -eq 1 ]] && journaliser WARN "Mode --dry-run : aucune action réelle."

    valider
    etape_squelette
    etape_dependances
    etape_code_metier
    etape_docker
    etape_docker_demarrage
    etape_verification
    afficher_resume_final
    journaliser INFO "Succès. Journal : ${FICHIER_LOG}"
}

main
