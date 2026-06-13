#!/usr/bin/env bash
#
# sauvegarde-geniuslab.sh — Sauvegarde la base PostgreSQL de GeniusLab (BNF_3.5).
#
# Produit un dump compressé de la base, horodaté, dans un répertoire de
# sauvegardes, puis purge les dumps plus vieux que la rétention choisie. Pensé
# pour tourner en tâche planifiée (cron) sur l'hôte Docker : il appelle
# « docker compose exec » pour lancer pg_dump dans le conteneur « database »,
# sans exposer de port ni copier d'identifiants en clair.
#
# Exemple de ligne cron (sauvegarde quotidienne à 2h du matin) :
#   0 2 * * * cd /chemin/vers/geniuslab && ./sauvegarde-geniuslab.sh >/dev/null 2>&1

set -Eeuo pipefail

# ─────────────────────────────────────────────────────────────────────────────
# Constantes & journalisation
# ─────────────────────────────────────────────────────────────────────────────
readonly NOM_PROJET="sauvegarde-geniuslab"
readonly VERSION="1.0.0"
readonly REP_LOGS="${HOME}/.logs/${NOM_PROJET}"
readonly FICHIER_LOG="${REP_LOGS}/$(date +%Y-%m-%d).log"
readonly DEBUT_MS=$(($(date +%s%N) / 1000000))

# Valeurs par défaut (surchargées par les options ou l'environnement).
SERVICE_DB="database"           # nom du service Docker hébergeant PostgreSQL
REP_SAUVEGARDES="./var/sauvegardes"
RETENTION_JOURS=14              # nombre de jours de dumps conservés
DRY_RUN=0
VERBOSE=0

# Couleurs uniquement si le terminal est interactif (TTY).
if [[ -t 1 ]]; then
    C_DEBUG=$'\033[2;37m'; C_INFO=$'\033[0;36m'; C_WARN=$'\033[0;33m'
    C_ERROR=$'\033[0;31m'; C_RESET=$'\033[0m'
else
    C_DEBUG=""; C_INFO=""; C_WARN=""; C_ERROR=""; C_RESET=""
fi

# Journalise sur le terminal (avec couleur si TTY) et dans le fichier (sans).
journaliser() {
    local niveau="$1"; shift
    local message="$*"
    local horodatage; horodatage="$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    local couleur=""
    case "${niveau}" in
        DEBUG) couleur="${C_DEBUG}"; [[ "${VERBOSE}" -eq 0 ]] && return 0 ;;
        INFO)  couleur="${C_INFO}"  ;;
        WARN)  couleur="${C_WARN}"  ;;
        ERROR) couleur="${C_ERROR}" ;;
    esac
    mkdir -p "${REP_LOGS}"
    printf '%s%s%s %s\n' "${couleur}" "${niveau}" "${C_RESET}" "${message}" >&2
    printf '%s pid=%d level=%s msg="%s"\n' \
        "${horodatage}" "$$" "${niveau}" "${message}" >> "${FICHIER_LOG}"
}

# Ligne machine-parsable finale, émise une seule fois quoi qu'il arrive.
#
# Le code de sortie effectif est la seule source de vérité : le trap EXIT le
# lit via $?, et le statut s'en déduit (0 = SUCCESS, sinon ERROR). Plus besoin
# d'appeler resume_final à la main à chaque point de sortie ni de répéter le
# statut et le code : un simple « exit N » suffit partout.
resumer() {
    local code=$?
    local duree_ms=$(( $(($(date +%s%N) / 1000000)) - DEBUT_MS ))
    local statut="SUCCESS"
    [[ "${code}" -ne 0 ]] && statut="ERROR"
    printf 'STATUS=%s|CODE=%s|DURATION_MS=%s|RETENTION_JOURS=%s\n' \
        "${statut}" "${code}" "${duree_ms}" "${RETENTION_JOURS}"
}
trap resumer EXIT

# Journalise le contexte sur erreur ou interruption ; le code de sortie réel
# (capté par le trap EXIT) produit la ligne finale.
trap 'journaliser ERROR "Arrêt anormal (code $?). Voir ${FICHIER_LOG}."' ERR
trap 'journaliser WARN "Interruption demandée."; exit 130' INT TERM

afficher_aide() {
    cat <<AIDE
Utilisation : ${NOM_PROJET}.sh [options]

Description :
  Sauvegarde la base PostgreSQL de GeniusLab dans un dump compressé horodaté,
  puis purge les dumps dépassant la rétention. Conçu pour cron sur l'hôte Docker.

Options :
  -s, --service NOM     Nom du service Docker PostgreSQL (défaut : ${SERVICE_DB}).
  -d, --dossier CHEMIN  Dossier des sauvegardes (défaut : ${REP_SAUVEGARDES}).
  -r, --retention N     Jours de conservation des dumps (défaut : ${RETENTION_JOURS}).
      --dry-run         Simule sans écrire ni supprimer de fichier.
  -v, --verbose         Affiche les messages de débogage.
  -h, --help            Affiche cette aide.

Codes de sortie :
  0  Succès.
  1  Erreur d'argument ou de validation.
  2  Échec du dump ou de la purge.

Dépendances :
  docker (avec le plugin compose), accès au projet, pg_dump dans le conteneur.

Exemples :
  ${NOM_PROJET}.sh
  ${NOM_PROJET}.sh --retention 30 --dossier /var/backups/geniuslab
  ${NOM_PROJET}.sh --dry-run --verbose
AIDE
}

# ─────────────────────────────────────────────────────────────────────────────
# Analyse des arguments
# ─────────────────────────────────────────────────────────────────────────────
while [[ $# -gt 0 ]]; do
    case "$1" in
        -s|--service)   SERVICE_DB="${2:?Valeur manquante pour --service}"; shift 2 ;;
        -d|--dossier)   REP_SAUVEGARDES="${2:?Valeur manquante pour --dossier}"; shift 2 ;;
        -r|--retention) RETENTION_JOURS="${2:?Valeur manquante pour --retention}"; shift 2 ;;
        --dry-run)      DRY_RUN=1; shift ;;
        -v|--verbose)   VERBOSE=1; shift ;;
        -h|--help)      afficher_aide; exit 0 ;;
        *) journaliser ERROR "Option inconnue : $1"; afficher_aide; exit 1 ;;
    esac
done

# ─────────────────────────────────────────────────────────────────────────────
# Validation défensive des entrées
# ─────────────────────────────────────────────────────────────────────────────
if ! [[ "${RETENTION_JOURS}" =~ ^[0-9]+$ ]] || [[ "${RETENTION_JOURS}" -lt 1 ]]; then
    journaliser ERROR "La rétention doit être un entier positif (reçu : ${RETENTION_JOURS})."
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    journaliser ERROR "Docker est introuvable dans le PATH."
    exit 1
fi

# Le conteneur doit tourner pour qu'on puisse y lancer pg_dump.
if ! docker compose ps --status running --services 2>/dev/null | grep -qx "${SERVICE_DB}"; then
    journaliser ERROR "Le service « ${SERVICE_DB} » n'est pas démarré (docker compose up requis)."
    exit 2
fi

# On lit les identifiants depuis l'environnement du conteneur : aucun secret
# n'est écrit ni passé en clair sur la ligne de commande de l'hôte.
lire_var_conteneur() {
    docker compose exec -T "${SERVICE_DB}" printenv "$1" 2>/dev/null | tr -d '\r\n'
}
DB_NAME="$(lire_var_conteneur POSTGRES_DB)"
DB_USER="$(lire_var_conteneur POSTGRES_USER)"

if [[ -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
    journaliser ERROR "POSTGRES_DB ou POSTGRES_USER introuvable dans le conteneur."
    exit 2
fi

# ─────────────────────────────────────────────────────────────────────────────
# Sauvegarde
# ─────────────────────────────────────────────────────────────────────────────
horodatage_fichier="$(date +%Y-%m-%d_%H-%M-%S)"
fichier_dump="${REP_SAUVEGARDES}/geniuslab_${horodatage_fichier}.sql.gz"

journaliser INFO "Base « ${DB_NAME} » (utilisateur ${DB_USER}), service « ${SERVICE_DB} »."
journaliser DEBUG "Destination : ${fichier_dump}"

if [[ "${DRY_RUN}" -eq 1 ]]; then
    journaliser WARN "Mode --dry-run : aucun fichier écrit ni supprimé."
    journaliser INFO "Aurait créé : ${fichier_dump}"
    journaliser INFO "Aurait purgé les dumps de plus de ${RETENTION_JOURS} jour(s)."
    exit 0
fi

mkdir -p "${REP_SAUVEGARDES}"

# pg_dump tourne DANS le conteneur ; sa sortie est compressée côté hôte. Le
# pipefail garantit qu'un échec de pg_dump fait échouer toute la chaîne.
journaliser INFO "Dump en cours…"
if ! docker compose exec -T "${SERVICE_DB}" \
        pg_dump --clean --if-exists --no-owner --username "${DB_USER}" "${DB_NAME}" \
        | gzip -9 > "${fichier_dump}"; then
    journaliser ERROR "Le dump a échoué."
    rm -f "${fichier_dump}"   # ne pas laisser de fichier tronqué
    exit 2
fi

# Un dump valide n'est jamais vide : on rejette un fichier suspect.
taille=$(stat -c %s "${fichier_dump}" 2>/dev/null || echo 0)
if [[ "${taille}" -lt 100 ]]; then
    journaliser ERROR "Dump anormalement petit (${taille} octets) : rejeté."
    rm -f "${fichier_dump}"
    exit 2
fi
journaliser INFO "Dump créé (${taille} octets) : ${fichier_dump}"

# ─────────────────────────────────────────────────────────────────────────────
# Rotation : purge des dumps trop anciens
# ─────────────────────────────────────────────────────────────────────────────
nb_purges=0
while IFS= read -r -d '' vieux; do
    rm -f "${vieux}"
    journaliser DEBUG "Purgé : ${vieux}"
    nb_purges=$((nb_purges + 1))
done < <(find "${REP_SAUVEGARDES}" -maxdepth 1 -type f -name 'geniuslab_*.sql.gz' \
            -mtime "+${RETENTION_JOURS}" -print0 2>/dev/null)

journaliser INFO "Purge terminée : ${nb_purges} dump(s) supprimé(s)."

exit 0
