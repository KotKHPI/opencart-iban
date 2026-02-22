#!/usr/bin/env bash
set -euo pipefail

WEB_ROOT="/var/www/html"
OPENCART_SRC="/usr/src/opencart/upload"
VENDOR_SRC="${OPENCART_SRC}/system/storage/vendor"
VENDOR_DST="${WEB_ROOT}/system/storage/vendor"

echo "--- OpenCart 3 dev container ---"

INSTALLED_NOW="0"

if [ ! -f "${WEB_ROOT}/index.php" ]; then
  echo "--- Bootstrapping OpenCart files into ${WEB_ROOT} ---"
  mkdir -p "${WEB_ROOT}"
  cp -a "${OPENCART_SRC}/." "${WEB_ROOT}/"
fi

cd "${WEB_ROOT}"

if [ -d "${VENDOR_SRC}" ]; then
  if [ ! -f "${VENDOR_DST}/autoload.php" ] || [ ! -f "${VENDOR_DST}/react/promise/src/functions_include.php" ]; then
    echo "--- Vendor dependencies look incomplete, refreshing system/storage/vendor ---"
    rm -rf "${VENDOR_DST}"
    mkdir -p "$(dirname "${VENDOR_DST}")"
    cp -a "${VENDOR_SRC}" "$(dirname "${VENDOR_DST}")/"
  fi
fi

if [ -f "config-dist.php" ] && [ ! -s "config.php" ]; then
  cp "config-dist.php" "config.php"
fi

if [ -f "admin/config-dist.php" ] && [ ! -s "admin/config.php" ]; then
  cp "admin/config-dist.php" "admin/config.php"
fi

if [ ! -f "install.lock" ] && [ -f "install/cli_install.php" ]; then
  echo "--- Waiting for DB (${DB_HOSTNAME:-db}:${DB_PORT:-3306}) ---"
  php -r '
    $host = getenv("DB_HOSTNAME") ?: "db";
    $user = getenv("DB_USERNAME") ?: "opencart";
    $pass = getenv("DB_PASSWORD") ?: "opencart";
    $db   = getenv("DB_DATABASE") ?: "opencart";
    $port = (int)(getenv("DB_PORT") ?: 3306);
    for ($i = 0; $i < 60; $i++) {
      $link = @mysqli_connect($host, $user, $pass, $db, $port);
      if ($link) { mysqli_close($link); exit(0); }
      sleep(1);
    }
    fwrite(STDERR, "ERROR: DB is not reachable\n");
    exit(1);
  '

  echo "--- Installing OpenCart via CLI ---"

  set +e
  php install/cli_install.php install \
    --username "${OPENCART_USERNAME:-admin}" \
    --password "${OPENCART_PASSWORD:-admin}" \
    --email "${OPENCART_ADMIN_EMAIL:-admin@example.com}" \
    --http_server "${OPENCART_HTTP_SERVER:-http://localhost:8081/}" \
    --language "${OPENCART_LANGUAGE:-en-gb}" \
    --db_driver "${DB_DRIVER:-mysqli}" \
    --db_hostname "${DB_HOSTNAME:-db}" \
    --db_username "${DB_USERNAME:-opencart}" \
    --db_password "${DB_PASSWORD:-opencart}" \
    --db_database "${DB_DATABASE:-opencart}" \
    --db_port "${DB_PORT:-3306}" \
    --db_prefix "${DB_PREFIX:-oc_}"
  status=$?

  if [ $status -ne 0 ]; then
    echo "WARN: CLI install with --language failed, retrying without --language ..."
    php install/cli_install.php install \
      --username "${OPENCART_USERNAME:-admin}" \
      --password "${OPENCART_PASSWORD:-admin}" \
      --email "${OPENCART_ADMIN_EMAIL:-admin@example.com}" \
      --http_server "${OPENCART_HTTP_SERVER:-http://localhost:8081/}" \
      --db_driver "${DB_DRIVER:-mysqli}" \
      --db_hostname "${DB_HOSTNAME:-db}" \
      --db_username "${DB_USERNAME:-opencart}" \
      --db_password "${DB_PASSWORD:-opencart}" \
      --db_database "${DB_DATABASE:-opencart}" \
      --db_port "${DB_PORT:-3306}" \
      --db_prefix "${DB_PREFIX:-oc_}"
    status=$?
  fi
  set -e

  if [ $status -eq 0 ]; then
    touch "install.lock"
    INSTALLED_NOW="1"
  else
    echo "ERROR: OpenCart CLI install failed."
    echo "You can finish installation via browser: ${OPENCART_HTTP_SERVER:-http://localhost:8081/}install/"
  fi
fi

if [ -f ".htaccess.txt" ] && [ ! -f ".htaccess" ]; then
  cp ".htaccess.txt" ".htaccess"
fi

# Ensure OpenCart writable directories are accessible for the web user.
chown -R www-data:www-data \
  "system/storage" \
  "image" \
  "config.php" \
  "admin/config.php" \
  || true

exec "$@"
