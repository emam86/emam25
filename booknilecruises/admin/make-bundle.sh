#!/usr/bin/env bash
# Packs everything for Hostinger as two zips, both extracted in domains/booknilecruises.net/:
#
#   bnc-1-panel.zip   step 1: panel and API, plus today's content for the one-time import.
#                     Safe to upload while the current site is live: it changes nothing visitors see.
#     bnc-app/                 app code (+ seed/export.json)
#     bnc-config.php           only with --first-install (fresh random tokens, server paths)
#     public_html/admin/       panel entry point
#     public_html/api/         API entry point
#
#   bnc-2-site.zip    step 2, after the import: switches the public site to PHP pages from the database.
#     public_html/index.php, public_html/.htaccess, public_html/assets/, public_html/img/
#
# Usage: admin/make-bundle.sh [--first-install] [output-folder]   (default: ../deploy)
#   Never use --first-install for an update: it would overwrite the live configuration.
set -euo pipefail
FIRST=0
if [ "${1:-}" = --first-install ]; then FIRST=1; shift; fi
HOME_DIR="${BNC_SERVER_HOME:-/home/u857861630}"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$HERE")"
OUT_DIR="$(mkdir -p "${1:-$ROOT/deploy}" && cd "${1:-$ROOT/deploy}" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# --- step 1: panel -----------------------------------------------------------
P="$STAGE/panel"
mkdir -p "$P/bnc-app/seed" "$P/public_html"
cp -a "$HERE/app/." "$P/bnc-app/"
rm -rf "$P/bnc-app/cache"   # request caches are local state
cp -a "$HERE/public/admin" "$P/public_html/admin"
cp -a "$HERE/public/api" "$P/public_html/api"
( cd "$ROOT/site" && node scripts/export-seed.mjs "$P/bnc-app/seed/export.json" > /dev/null )
if [ "$FIRST" = 1 ]; then
  token() { head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'; }
  sed -e "s#'install_token' => ''#'install_token' => '$(token)'#" \
      -e "s#'export_token' => ''#'export_token' => '$(token)'#" \
      -e "s#/home/u000000000/domains/booknilecruises.net/public_html/images#$HOME_DIR/domains/booknilecruises.net/public_html/images#" \
      "$HERE/config.sample.php" > "$P/bnc-config.php"
  grep -q "'install_token' => '[0-9a-f]\{64\}'" "$P/bnc-config.php" || { echo "token fill failed" >&2; exit 1; }
else
  cp "$HERE/config.sample.php" "$P/bnc-config.sample.php"
fi

# --- step 2: public site -----------------------------------------------------
S="$STAGE/site"
mkdir -p "$S/public_html"
cp -a "$HERE/public/site/." "$S/public_html/"

# Nothing local may ship, and a real config file only on first install.
if find "$STAGE" -name '*.log' -o -path '*/tests/*' -o -path '*/cache/*' | grep -q . \
   || { [ "$FIRST" = 0 ] && [ -e "$P/bnc-config.php" ]; }; then
  echo "refusing: local or secret files in the bundle" >&2; exit 1
fi
for part in panel site; do
  n=$([ $part = panel ] && echo 1 || echo 2)
  out="$OUT_DIR/bnc-$n-$part.zip"
  rm -f "$out"
  ( cd "$STAGE/$part" && zip -qr -X "$out" . )
  echo "$out ($(du -h "$out" | cut -f1), $(unzip -l "$out" | tail -1 | awk '{print $2}') files)"
done
