#!/usr/bin/env bash
# Install a throwaway WordPress + WooCommerce store with WP Live Hype for testing.
#
# Usage: tests/setup-wordpress.sh <install-dir> <site-url> [plugin-zip]
#   Without a ZIP the plugin is symlinked from this repository.
# Env: DB_NAME (wp), DB_USER (root), DB_PASS (root), DB_HOST (127.0.0.1), WP (wp-cli command).
set -euo pipefail

DIR="$1"
URL="$2"
ZIP="${3:-}"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP="${WP:-wp} --path=$DIR --allow-root"

mkdir -p "$DIR"
$WP core download --skip-content --force >/dev/null
mkdir -p "$DIR/wp-content/plugins" "$DIR/wp-content/themes"
$WP config create --dbname="${DB_NAME:-wp}" --dbuser="${DB_USER:-root}" --dbpass="${DB_PASS:-root}" --dbhost="${DB_HOST:-127.0.0.1}" --force --skip-check >/dev/null
$WP db reset --yes >/dev/null 2>&1 || $WP db create >/dev/null
$WP core install --url="$URL" --title="WP Live Hype test store" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email >/dev/null
$WP theme install twentytwentyfour --activate >/dev/null
$WP rewrite structure '/%postname%/' >/dev/null
cp "$ROOT/tests/router.php" "$DIR/router.php"

$WP plugin install woocommerce --activate >/dev/null
if [ -n "$ZIP" ]; then
	$WP plugin install "$ZIP" --activate
else
	ln -sfn "$ROOT/wp-live-hype" "$DIR/wp-content/plugins/wp-live-hype"
	$WP plugin activate wp-live-hype
fi

$WP eval-file "$ROOT/tests/seed-store.php"
echo "Test store ready at $URL (admin / admin)"
