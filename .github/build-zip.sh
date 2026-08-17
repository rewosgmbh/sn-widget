#!/usr/bin/env bash
# Builds the installable plugin ZIP (top folder: steigerwald-news-widget/).
# Excludes .git and any pre-built zip. Run from repo root or via CI.
set -euo pipefail

cd "$(dirname "$0")/.."
REPO="$PWD"
PLUGIN_DIR="steigerwald-news-widget"
BUILD_ROOT="$(mktemp -d)"
DEST="$BUILD_ROOT/$PLUGIN_DIR"
mkdir -p "$DEST"

cp -r admin includes languages public tests docs uninstall.php \
      steigerwald-news-widget.php readme.txt README.md CHANGELOG.md "$DEST/"

ZIP="$REPO/steigerwald-news-widget.zip"
rm -f "$ZIP"
( cd "$BUILD_ROOT" && zip -r "$ZIP" "$PLUGIN_DIR" -x '*.git*' >/dev/null )
rm -rf "$BUILD_ROOT"

echo "Built $ZIP"
ls -la "$ZIP"
