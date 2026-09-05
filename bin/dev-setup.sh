#!/usr/bin/env bash
# TicketTrade — Dev Environment Bootstrap
#
# One-shot setup for a fresh checkout: ensures MariaDB is running,
# creates the dev + test databases, writes config/db.php and
# config/db.test.php if missing, installs Composer deps, and runs
# php migrate.php to apply the schema.
#
# Idempotent: safe to re-run. Does NOT seed the student_id_allowlist
# (Phase 9's job).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "[dev-setup] Root: $ROOT"

# 1. Probe MySQL
if ! command -v mysql >/dev/null 2>&1 && ! command -v mariadb >/dev/null 2>&1; then
    echo "[dev-setup] ERROR: neither mysql nor mariadb is installed." >&2
    echo "  Install MariaDB/MySQL or start a container:" >&2
    echo "    docker run -d --name tt-mysql -e MYSQL_ROOT_PASSWORD= -p 3306:3306 mariadb:10.11" >&2
    exit 1
fi

# Try a connection via the unix socket first
if mysql -uroot -e "SELECT 1" >/dev/null 2>&1; then
    DB_USER="root"
elif mysql -uuser -e "SELECT 1" >/dev/null 2>&1; then
    DB_USER="user"
elif mysql --login-path=local >/dev/null 2>&1; then
    DB_USER="$USER"
else
    DB_USER="${USER:-user}"
fi

SOCKET="/tmp/mysql.sock"
if [ ! -e "$SOCKET" ]; then
    SOCKET=""
fi

echo "[dev-setup] Using DB user: $DB_USER (socket: ${SOCKET:-default})"

# 2. Create databases
mysql -u"$DB_USER" ${SOCKET:+--socket="$SOCKET"} <<'SQL'
CREATE DATABASE IF NOT EXISTS tickettrade      DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS tickettrade_test DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
SQL

# 3. Write config/db.php if missing
if [ ! -f config/db.php ]; then
    if [ -n "$SOCKET" ]; then
        DSN="mysql:unix_socket=$SOCKET;dbname=tickettrade;charset=utf8mb4"
    else
        DSN="mysql:host=127.0.0.1;port=3306;dbname=tickettrade;charset=utf8mb4"
    fi
    # Run DSN + DB_USER through PHP var_export so any single-quote /
    # backslash / dollar / newline is safely string-literalized. The
    # shell heredoc would otherwise treat them as syntax.
    DSN_LITERAL=$(printf "%s" "$DSN" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
    USER_LITERAL=$(printf "%s" "$DB_USER" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
    cat > config/db.php <<EOF
<?php
return [
    'dsn'  => getenv('DB_DSN') ?: ${DSN_LITERAL},
    'user' => getenv('DB_USER') ?: ${USER_LITERAL},
    'pass' => getenv('DB_PASS') ?: '',
];
EOF
    echo "[dev-setup] Wrote config/db.php"
fi

# 4. Write config/db.test.php if missing
if [ ! -f config/db.test.php ]; then
    if [ -n "$SOCKET" ]; then
        DSN="mysql:unix_socket=$SOCKET;dbname=tickettrade_test;charset=utf8mb4"
    else
        DSN="mysql:host=127.0.0.1;port=3306;dbname=tickettrade_test;charset=utf8mb4"
    fi
    DSN_LITERAL=$(printf "%s" "$DSN" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
    USER_LITERAL=$(printf "%s" "$DB_USER" | php -r 'echo var_export(stream_get_contents(STDIN), true);')
    cat > config/db.test.php <<EOF
<?php
return [
    'dsn'  => getenv('DB_DSN') ?: ${DSN_LITERAL},
    'user' => getenv('DB_USER') ?: ${USER_LITERAL},
    'pass' => getenv('DB_PASS') ?: '',
];
EOF
    echo "[dev-setup] Wrote config/db.test.php"
fi

# 5. Composer install if vendor/ is missing
if [ ! -d vendor ]; then
    echo "[dev-setup] Running composer install..."
    composer install --no-interaction --no-progress
fi

# 6. Run migrations only if the DB is empty. Re-running migrations
# against a populated DB fails on UNIQUE constraints (categories
# already seeded) and other one-shot INSERTs. If a fresh DB is
# required, run `bin/test` which handles the reset.
EXPECTED_TABLES=$(ls migrations/*.sql 2>/dev/null | wc -l)
ACTUAL_TABLES=$(mysql -u"$DB_USER" ${SOCKET:+--socket="$SOCKET"} -N -B tickettrade -e \
  "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='tickettrade'" 2>/dev/null || echo 0)
if [ "$ACTUAL_TABLES" -lt "$EXPECTED_TABLES" ]; then
  echo "[dev-setup] Running php migrate.php (DB has $ACTUAL_TABLES tables, $EXPECTED_TABLES expected)..."
  php migrate.php
else
  echo "[dev-setup] Migrations already applied ($ACTUAL_TABLES/$EXPECTED_TABLES tables). Skipping."
fi

echo ""
echo "[dev-setup] DONE"
echo "  Dev DB ready, test DB ready, migrations applied."
echo "  Start the dev server: php -S 127.0.0.1:18001 -t public public/router.php"
echo "  Run the test suite:   APP_ENV=test vendor/bin/phpunit --testsuite=phase-2"
