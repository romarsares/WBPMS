#!/bin/bash
# ============================================================
# WBPMS — Remote deploy script
# Usage:  bash deploy.sh [user@host]
# Default target: core@core-server
# ============================================================
set -euo pipefail

TARGET="${1:-core@core-server}"
REMOTE_DIR="/srv/app/wbpms"
ARCHIVE="wbpms-deploy.tar.gz"

echo "==> Building deployment archive ..."
# Pack everything that isn't in .dockerignore / .gitignore
# but DO include composer.lock, phinx.php, Dockerfile, docker/ etc.
tar \
  --exclude='./.git' \
  --exclude='./vendor' \
  --exclude='./.env' \
  --exclude='./storage/private/*' \
  --exclude='./.phpunit.cache' \
  --exclude='./.phpstan.cache' \
  --exclude='./coverage' \
  --exclude='./*.zip' \
  --exclude='./backup' \
  --exclude='./issues' \
  --exclude='./regression' \
  --exclude='./tools' \
  --exclude='./build' \
  --exclude='./WBPMS-build' \
  --exclude='./WBPMS-build.rar' \
  -czf "/tmp/${ARCHIVE}" .

echo "==> Uploading archive to ${TARGET}:${REMOTE_DIR} ..."
ssh "${TARGET}" "mkdir -p ${REMOTE_DIR}"
scp "/tmp/${ARCHIVE}" "${TARGET}:${REMOTE_DIR}/${ARCHIVE}"

echo "==> Deploying on remote host ..."
ssh "${TARGET}" bash -s <<'REMOTE'
set -euo pipefail
REMOTE_DIR="/srv/app/wbpms"
ARCHIVE="wbpms-deploy.tar.gz"

cd "${REMOTE_DIR}"

echo "--- Extracting archive ..."
tar -xzf "${ARCHIVE}"
rm -f "${ARCHIVE}"

# Create .env from .env.docker if .env doesn't exist yet
if [ ! -f .env ]; then
    if [ -f .env.docker ]; then
        cp .env.docker .env
        echo "--- Copied .env.docker → .env"
        echo "    IMPORTANT: Edit ${REMOTE_DIR}/.env and set real passwords/APP_KEY before proceeding."
        echo "    Then re-run: cd ${REMOTE_DIR} && docker compose up -d --build"
        exit 0
    else
        echo "ERROR: No .env file and no .env.docker template found. Aborting."
        exit 1
    fi
fi

echo "--- Rebuilding containers ..."
docker compose up -d --build --remove-orphans

echo "--- Waiting for app to be healthy ..."
sleep 5
docker compose ps

echo "==> Deploy complete. App is routed through Traefik for wbpms.coreflow.business."
REMOTE

echo "==> Done."
rm -f "/tmp/${ARCHIVE}"
