#!/usr/bin/env bash
# Packs the admin panel for upload to Hostinger, laid out like the server:
#   domains/booknilecruises.net/
#     bnc-app/                 ← app code (+ seed/export.json for the first import)
#     bnc-config.sample.php    ← copy to bnc-config.php and fill in
#     public_html/admin/       ← panel entry point
#     public_html/api/         ← API entry point
# Usage: admin/make-bundle.sh [--first-install] [output.zip]   (default: ../deploy/admin-bundle.zip)
#   --first-install also ships bnc-config.php with fresh random tokens and the
#   server paths filled in (only the database details are left). Never use it for
#   an update: it would overwrite the live configuration.
set -euo pipefail
FIRST=0
if [ "${1:-}" = --first-install ]; then FIRST=1; shift; fi
HOME_DIR="${BNC_SERVER_HOME:-/home/u857861630}"
HERE="$(cd "$(dirname "$0")" && pwd)"
ROOT="$(dirname "$HERE")"
OUT="${1:-$ROOT/deploy/admin-bundle.zip}"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$STAGE/bnc-app/seed" "$STAGE/public_html"
cp -a "$HERE/app/." "$STAGE/bnc-app/"
cp -a "$HERE/public/admin" "$STAGE/public_html/admin"
[ -d "$HERE/public/api" ] && [ -f "$HERE/public/api/index.php" ] && cp -a "$HERE/public/api" "$STAGE/public_html/api"
cp "$HERE/config.sample.php" "$STAGE/bnc-config.sample.php"

# Today's site content for the panel's one-time import.
( cd "$ROOT/site" && node scripts/export-seed.mjs "$STAGE/bnc-app/seed/export.json" > /dev/null )

if [ "$FIRST" = 1 ]; then
  token() { head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'; }
  sed -e "s#'install_token' => ''#'install_token' => '$(token)'#" \
      -e "s#'export_token' => ''#'export_token' => '$(token)'#" \
      -e "s#/home/u000000000/domains/booknilecruises.net/public_html/images#$HOME_DIR/domains/booknilecruises.net/public_html/images#" \
      "$HERE/config.sample.php" > "$STAGE/bnc-config.php"
  grep -q "'install_token' => '[0-9a-f]\{64\}'" "$STAGE/bnc-config.php" || { echo "token fill failed" >&2; exit 1; }
fi

# Nothing local may ship, and a config file only on first install.
if find "$STAGE" -name '*.log' -o -path '*/tests/*' | grep -q . || { [ "$FIRST" = 0 ] && [ -e "$STAGE/bnc-config.php" ]; }; then
  echo "refusing: local or secret files in the bundle" >&2; exit 1
fi
mkdir -p "$(dirname "$OUT")"
rm -f "$OUT"
( cd "$STAGE" && zip -qr -X "$OUT" . )
echo "$OUT ($(du -h "$OUT" | cut -f1))"
unzip -l "$OUT" | tail -1
