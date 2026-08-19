#!/usr/bin/env bash
# Builds the installable plugin ZIP (top folder: steigerwald-news-widget/).
# Excludes .git, agent dirs, dev artifacts and any pre-built zip.
set -euo pipefail

cd "$(dirname "$0")/.."
REPO="$PWD"
PLUGIN_DIR="steigerwald-news-widget"
BUILD_ROOT="$(mktemp -d)"
DEST="$BUILD_ROOT/$PLUGIN_DIR"
mkdir -p "$DEST"

# Release ZIP ships runtime code only. tests/ and docs/ are development
# artifacts and are intentionally excluded.
cp -r admin includes languages public uninstall.php \
      steigerwald-news-widget.php readme.txt README.md CHANGELOG.md "$DEST/"

mkdir -p "$REPO/build"
ZIP="$REPO/build/sn-news-widget.zip"
rm -f "$ZIP"
( cd "$BUILD_ROOT" && zip -r "$ZIP" "$PLUGIN_DIR" -x '*.git*' >/dev/null )
rm -rf "$BUILD_ROOT"

echo "Built $ZIP"
ls -la "$ZIP"
