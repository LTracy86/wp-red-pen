#!/bin/bash
# Deploy the current committed WP Red Pen to EVERY local WordPress install under the
# XAMPP htdocs root, then activate it. Red Pen is a default-workflow plugin (like WP
# Smooth Moves): it lives + stays current on ALL local sites so it can be dogfooded
# while working on any project. Run this after every Red Pen release.
#
# Scope: scans all of htdocs (not just the Claude Code Projects dir) so sites outside
# the projects folder (e.g. S2S/wordpress) are covered too, and any new local site is
# picked up automatically on the next run.
#
# Usage:  ./deploy-to-installs.sh
#
# It exports the committed HEAD (so installs get a clean, .git-free copy), extracts it
# into each install's wp-content/plugins/wp-red-pen/, and runs wp-cli activate.
set -e

REPO_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECTS_ROOT="$(cd "$REPO_DIR/.." && pwd)"
HTDOCS_ROOT="$(cd "$PROJECTS_ROOT/../.." && pwd)"   # /c/xampp/htdocs - covers all local sites
WP="$PROJECTS_ROOT/wp"   # the wp-cli wrapper (php wp-cli.phar)

VERSION="$(grep -m1 "WPRP_VERSION" "$REPO_DIR/wp-red-pen.php" | grep -o '0\.[0-9.]*' | head -1)"
echo "Deploying WP Red Pen $VERSION to all WordPress installs under $HTDOCS_ROOT"

TAR="$(mktemp)"
git -C "$REPO_DIR" archive --format=tar HEAD -o "$TAR"

# NUL-delimited read so install paths containing spaces ("Claude Code Projects") are
# handled correctly - an unquoted $(find ...) in a for-loop word-splits on the spaces.
while IFS= read -r -d '' wpload; do
	install="$(dirname "$wpload")"
	dest="$install/wp-content/plugins/wp-red-pen"
	mkdir -p "$dest"
	tar -xf "$TAR" -C "$dest"
	live="$(grep -m1 "WPRP_VERSION" "$dest/wp-red-pen.php" | grep -o '0\.[0-9.]*' | head -1)"
	act="$("$WP" --path="$install" plugin activate wp-red-pen --skip-plugins --skip-themes 2>&1 || true)"
	if echo "$act" | grep -qi "Success\|already active"; then state="active"; else state="present (activation: ${act%%$'\n'*})"; fi
	echo "  $install -> $live, $state"
done < <(find "$PROJECTS_ROOT" -maxdepth 3 -name wp-load.php -print0 2>/dev/null)

rm -f "$TAR"
echo "Done."
