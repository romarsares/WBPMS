#!/bin/sh
# ============================================================
# WBPMS Docker entrypoint
# Waits for MySQL, runs Phinx migrations, then starts Apache.
# ============================================================
set -e

# ── Wait for MySQL ───────────────────────────────────────────
echo "[entrypoint] Waiting for MySQL at ${DB_HOST}:${DB_PORT} ..."
MAX_TRIES=30
i=0
until mysqladmin ping -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" --silent 2>/dev/null; do
    i=$((i + 1))
    if [ "$i" -ge "$MAX_TRIES" ]; then
        echo "[entrypoint] ERROR: MySQL did not become ready within ${MAX_TRIES} seconds. Aborting."
        exit 1
    fi
    echo "[entrypoint] MySQL not ready yet (attempt $i/${MAX_TRIES}). Sleeping 2s ..."
    sleep 2
done
echo "[entrypoint] MySQL is ready."

# ── Run Phinx migrations ─────────────────────────────────────
echo "[entrypoint] Running database migrations ..."
php /var/www/html/vendor/bin/phinx migrate -c /var/www/html/phinx.php -e production
echo "[entrypoint] Migrations complete."

# ── Flush stale sessions ──────────────────────────────────────
# After a deploy or DB restore, old session IDs in the browser
# will not match the new server state and cause CSRF failures.
# Clearing all sessions forces every user to log in fresh.
echo "[entrypoint] Flushing stale sessions ..."
mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" \
  -e "DELETE FROM sessions;" 2>/dev/null || true
echo "[entrypoint] Sessions flushed."

# ── Fix storage permissions (in case volume mounted at runtime) ──
chown -R www-data:www-data /var/www/html/storage
chmod -R 775 /var/www/html/storage

# ── Hand off to Apache ───────────────────────────────────────
echo "[entrypoint] Starting Apache ..."
exec "$@"
