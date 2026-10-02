#!/usr/bin/env bash
# Build an installable WordPress plugin ZIP for ALWAYS FINAL Social Proof.
#
# Usage: bin/build.sh
# Optional tools (used when available):
#   - terser (npx terser)  → minifies public/js/notifications.js
#   - wp-cli (wp)          → regenerates languages/always-final-social-proof.pot
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="always-final-social-proof"
SRC="$ROOT/$SLUG"
VERSION="$(sed -n 's/^ \* Version:[[:space:]]*//p' "$SRC/$SLUG.php" | head -n1 | tr -d '[:space:]')"
DIST="$ROOT/dist"

echo "Building $SLUG $VERSION"

# Lint every PHP file.
find "$SRC" -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

# Minify the frontend script (kept in sync with the readable source).
if command -v npx >/dev/null 2>&1; then
	npx --yes terser "$SRC/public/js/notifications.js" --compress --mangle --comments '/^!/' -o "$SRC/public/js/notifications.min.js"
	echo "Minified notifications.js"
else
	echo "terser not available; removing stale minified file so the readable source is served"
	rm -f "$SRC/public/js/notifications.min.js"
fi

# Regenerate the translation template.
if command -v wp >/dev/null 2>&1; then
	wp i18n make-pot "$SRC" "$SRC/languages/$SLUG.pot" --domain="$SLUG" --allow-root >/dev/null
	echo "Regenerated POT"
fi

mkdir -p "$DIST"
ZIP="$DIST/$SLUG-$VERSION.zip"
rm -f "$ZIP"
( cd "$ROOT" && zip -qr "$ZIP" "$SLUG" -x "*/.DS_Store" "*/node_modules/*" "*/.git*" )
echo "Created $ZIP ($(wc -c < "$ZIP") bytes)"
