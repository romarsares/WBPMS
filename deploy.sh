#!/bin/bash
# WBPMS controlled remote deployment.
#
# Usage:
#   bash deploy.sh [user@host]
#   bash deploy.sh [user@host] --migrate BACKUP_REFERENCE
#
# A normal deployment is application-only. Schema changes require an explicit
# --migrate flag and a recorded backup reference; the operator must still review
# the pending migration list and have payroll approval before continuing.
set -euo pipefail

TARGET="${1:-core@core-server}"
MIGRATE_FLAG="${2:-}"
BACKUP_REFERENCE="${3:-}"
REMOTE_DIR="/srv/app/wbpms"
ARCHIVE="wbpms-deploy.tar.gz"

if [ -n "${MIGRATE_FLAG}" ] && [ "${MIGRATE_FLAG}" != "--migrate" ]; then
    echo "Usage: bash deploy.sh [user@host] [--migrate BACKUP_REFERENCE]" >&2
    exit 64
fi

if [ "${MIGRATE_FLAG}" = "--migrate" ] && [ -z "${BACKUP_REFERENCE}" ]; then
    echo "ERROR: --migrate requires a recorded, restorable backup reference." >&2
    exit 64
fi

CURRENT_BRANCH="$(git branch --show-current)"
case "${CURRENT_BRANCH}" in
    dev|develop|*-dev) ;;
    *)
        echo "ERROR: deployments must originate from a development branch; current branch is '${CURRENT_BRANCH}'." >&2
        exit 1
        ;;
esac

if [ -n "$(git status --porcelain)" ]; then
    echo "ERROR: deployment requires a clean, committed worktree." >&2
    exit 1
fi

RELEASE_SHA="$(git rev-parse --verify HEAD)"
RELEASE_SHORT_SHA="$(git rev-parse --short=12 HEAD)"
RELEASE_DIR="${REMOTE_DIR}/releases/${RELEASE_SHA}"

echo "==> Building deployment archive from committed source ${RELEASE_SHORT_SHA} ..."
git archive --format=tar --prefix="./" "${RELEASE_SHA}" | gzip -9 > "/tmp/${ARCHIVE}"

echo "==> Uploading archive to ${TARGET}:${REMOTE_DIR} ..."
ssh "${TARGET}" "mkdir -p '${RELEASE_DIR}'"
scp "/tmp/${ARCHIVE}" "${TARGET}:${REMOTE_DIR}/${ARCHIVE}"

echo "==> Deploying on remote host ..."
ssh "${TARGET}" "RELEASE_SHA='${RELEASE_SHA}' MIGRATE_FLAG='${MIGRATE_FLAG}' BACKUP_REFERENCE='${BACKUP_REFERENCE}' bash -s" <<'REMOTE'
set -euo pipefail

REMOTE_DIR="/srv/app/wbpms"
ARCHIVE="wbpms-deploy.tar.gz"
RELEASE_DIR="${REMOTE_DIR}/releases/${RELEASE_SHA}"

echo "--- Extracting release ${RELEASE_SHA} ..."
tar -xzf "${REMOTE_DIR}/${ARCHIVE}" -C "${RELEASE_DIR}"
rm -f "${REMOTE_DIR}/${ARCHIVE}"

if [ ! -f "${REMOTE_DIR}/.env" ]; then
    echo "ERROR: no server-side ${REMOTE_DIR}/.env file. Create it from ${RELEASE_DIR}/.env.docker and set real credentials first." >&2
    exit 1
fi

compose() {
    docker compose -p wbpms --env-file "${REMOTE_DIR}/.env" -f "${RELEASE_DIR}/docker-compose.yml" "$@"
}

if [ "${MIGRATE_FLAG}" = "--migrate" ]; then
    echo "--- Backup reference: ${BACKUP_REFERENCE}"
    echo "--- Pending migration status (review before execution) ..."
    compose --profile migration run --rm migrate php /var/www/html/vendor/bin/phinx status -c /var/www/html/phinx.php -e production
    echo "--- Applying approved migrations ..."
    compose --profile migration run --rm migrate
fi

echo "--- Rebuilding application ..."
compose up -d --build --remove-orphans

echo "--- Waiting for application health ..."
for attempt in $(seq 1 12); do
    health_status="$(docker inspect --format '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' wbpms_app 2>/dev/null || true)"
    if [ "${health_status}" = "healthy" ]; then
        compose ps
        echo "==> Deploy complete. Release ${RELEASE_SHA} is healthy."
        exit 0
    fi
    sleep 5
done

compose ps
echo "ERROR: application did not become healthy. Retain the prior release and backup for rollback." >&2
exit 1
REMOTE

rm -f "/tmp/${ARCHIVE}"
