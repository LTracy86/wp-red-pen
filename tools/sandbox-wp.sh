#!/usr/bin/env bash
# Stand up a throwaway WordPress with WP Red Pen active, from nothing.
# Dev only. Needs php (with pdo_sqlite), curl and unzip. No MySQL.
#
#   tools/sandbox-wp.sh [dir] [port]
#
# Defaults: dir=/tmp/wprp-sandbox, port=8899. Admin login: admin / admin.
# The plugin is symlinked from this checkout, so edits show up live.
# Stop the server with: kill "$(cat <dir>/server.pid)"
set -euo pipefail

DIR="${1:-/tmp/wprp-sandbox}"
PORT="${2:-8899}"
URL="http://127.0.0.1:${PORT}"
REPO="$(cd "$(dirname "$0")/.." && pwd)"
WP="php ${DIR}/wp-cli.phar --path=${DIR}/wp --allow-root"

mkdir -p "$DIR"
cd "$DIR"

if [ ! -f wp-cli.phar ]; then
  curl -sSL -o wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi

if [ ! -f wp/wp-settings.php ]; then
  $WP core download --quiet
fi

SQLITE="wp/wp-content/plugins/sqlite-database-integration"
if [ ! -d "$SQLITE" ]; then
  curl -sSL -o sqlite.zip https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
  unzip -q -o sqlite.zip -d wp/wp-content/plugins/
  rm sqlite.zip
fi
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#${DIR}/${SQLITE}#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$SQLITE/db.copy" > wp/wp-content/db.php

if [ ! -f wp/wp-config.php ]; then
  $WP config create --dbname=wp --dbuser=wp --dbpass=wp --dbhost=localhost --skip-check --quiet \
    --extra-php <<PHP
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISABLE_WP_CRON', true );
PHP
fi

ln -sfn "$REPO" wp/wp-content/plugins/wp-red-pen

if ! $WP core is-installed 2>/dev/null; then
  $WP core install --url="$URL" --title="Red Pen Sandbox" --admin_user=admin \
    --admin_password=admin --admin_email=admin@example.com --skip-email --quiet
  $WP rewrite structure '/%postname%/' --quiet
  $WP post create --post_type=page --post_status=publish --post_title='Menu' \
    --post_content='<p>Sourdough, croissants and rye.</p>' --quiet
fi
$WP option update home "$URL" --quiet
$WP option update siteurl "$URL" --quiet
$WP plugin activate wp-red-pen --quiet

if [ -f server.pid ] && kill -0 "$(cat server.pid)" 2>/dev/null; then
  kill "$(cat server.pid)" || true
fi
# Router so pretty permalinks and /wp-json/ work under the built-in server.
cat > router.php <<'PHP'
<?php
$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$file = __DIR__ . '/wp' . $path;
if ( $path !== '/' && is_file( $file ) ) {
    return false;
}
if ( $path !== '/' && is_dir( $file ) && is_file( rtrim( $file, '/' ) . '/index.php' ) ) {
    chdir( rtrim( $file, '/' ) );
    $_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
    $_SERVER['PHP_SELF']    = $_SERVER['SCRIPT_NAME'];
    require rtrim( $file, '/' ) . '/index.php';
    return;
}
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/wp/index.php';
$_SERVER['SCRIPT_NAME']     = '/index.php';
$_SERVER['PHP_SELF']        = '/index.php';
require __DIR__ . '/wp/index.php';
PHP
PHP_CLI_SERVER_WORKERS=4 nohup php -S "127.0.0.1:${PORT}" -t wp router.php > server.log 2>&1 &
echo $! > server.pid
sleep 1
echo "WordPress running at ${URL} (admin / admin). Debug log: ${DIR}/wp/wp-content/debug.log"
