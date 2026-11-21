#!/bin/sh
# WBPMS Docker entrypoint.
#
# Wait for the database and then start the requested process. A normal restart
# must not alter payroll data: schema migrations are performed explicitly by the
# Compose "migrate" profile after release approval and backup confirmation.
set -eu

: "${DB_HOST:?DB_HOST is required}"
: "${DB_PORT:?DB_PORT is required}"
: "${DB_USER:?DB_USER is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
MAX_TRIES=30
i=0
until php -r '
    try {
        new PDO(
            sprintf("mysql:host=%s;port=%s", getenv("DB_HOST"), getenv("DB_PORT")),
            getenv("DB_USER"),
            getenv("DB_PASSWORD"),
            [PDO::ATTR_TIMEOUT => 2]
        );
        exit(0);
    } catch (Throwable) {
        exit(1);
    }
'; do
    i=$((i + 1))
    if [ "${i}" -ge "${MAX_TRIES}" ]; then
        echo "[entrypoint] ERROR: MySQL did not become ready within ${MAX_TRIES} attempts. Aborting." >&2
        exit 1
    fi
    echo "[entrypoint] MySQL not ready yet (attempt ${i}/${MAX_TRIES}). Sleeping 2s ..."
    sleep 2
done

echo "[entrypoint] MySQL is ready."

# The named volume is mounted after the image is built, so correct its ownership
# at container start before Apache drops privileges to www-data.
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

echo "[entrypoint] Starting process: $*"
exec "$@"
