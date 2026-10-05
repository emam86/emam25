#!/usr/bin/env bash
# Full backup of the booknilecruises.net WordPress site. Runs ON the Hostinger
# server and changes nothing on the site. One command from any computer
# (Windows PowerShell, cmd, macOS, Linux):
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/backup-on-server.sh | bash"
#
# Writes database.sql.gz and files.tar.gz to ~/backups/booknilecruises-<date>/,
# outside public_html so they are never served to the web.
set -euo pipefail

die() { echo "ERROR: $*" >&2; exit 1; }

# Find the WordPress folder: SITE_DIR if given, else the usual Hostinger paths.
if [ -z "${SITE_DIR:-}" ]; then
  for candidate in "$HOME/domains/booknilecruises.net/public_html" "$HOME/public_html"; do
    if [ -f "$candidate/wp-config.php" ]; then SITE_DIR="$candidate"; break; fi
  done
fi
if [ -z "${SITE_DIR:-}" ]; then
  echo "WordPress folders found under $HOME:" >&2
  find "$HOME" -maxdepth 4 -name wp-config.php -printf '  %h\n' 2>/dev/null >&2 || true
  die "booknilecruises.net not found. Re-run with SITE_DIR=/path/to/public_html in front of bash."
fi
echo "Site folder: $SITE_DIR"

STAMP="$(date +%Y%m%d-%H%M)"
OUT="$HOME/backups/booknilecruises-$STAMP"
mkdir -p "$OUT"
chmod 700 "$HOME/backups" "$OUT"

# Read the database settings from wp-config.php without printing the password.
# A text match, because including wp-config.php would boot all of WordPress.
cfg() { sed -nE "s/^[[:space:]]*define\([[:space:]]*['\"]$1['\"][[:space:]]*,[[:space:]]*['\"](.*)['\"][[:space:]]*\);.*/\1/p" "$SITE_DIR/wp-config.php" | head -n1; }
DB_NAME="$(cfg DB_NAME)"; DB_USER="$(cfg DB_USER)"; DB_PASS="$(cfg DB_PASSWORD)"; DB_HOST="$(cfg DB_HOST)"
[ -n "$DB_NAME" ] && [ -n "$DB_USER" ] || die "could not read DB_NAME / DB_USER from wp-config.php"

# DB_HOST may be "localhost", "localhost:3306" or "127.0.0.1:3306".
DB_PORT=""
case "${DB_HOST:-localhost}" in
  *:*[0-9]) DB_PORT="${DB_HOST##*:}"; DB_HOST="${DB_HOST%:*}" ;;
esac
DB_HOST="${DB_HOST:-localhost}"

echo "1/3 Dumping database $DB_NAME"
if command -v mysqldump >/dev/null 2>&1; then
  MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --routines --triggers --no-tablespaces \
    -h "$DB_HOST" ${DB_PORT:+-P "$DB_PORT"} -u "$DB_USER" "$DB_NAME" | gzip > "$OUT/database.sql.gz" \
    || die "mysqldump failed (see the message above)"
elif command -v wp >/dev/null 2>&1; then
  wp db export - --path="$SITE_DIR" --quiet | gzip > "$OUT/database.sql.gz" || die "wp db export failed"
else
  die "neither mysqldump nor wp-cli is available on this server"
fi

echo "2/3 Archiving files (cache folders skipped)"
tar -czf "$OUT/files.tar.gz" -C "$(dirname "$SITE_DIR")" \
  --exclude="$(basename "$SITE_DIR")/wp-content/cache" \
  --exclude="$(basename "$SITE_DIR")/wp-content/litespeed" \
  "$(basename "$SITE_DIR")" || die "tar failed"

echo "3/3 Verifying"
gzip -t "$OUT/database.sql.gz" || die "database archive is corrupt"
tar -tzf "$OUT/files.tar.gz" > /dev/null || die "files archive is corrupt"
TABLES="$(gzip -dc "$OUT/database.sql.gz" | grep -c 'CREATE TABLE' || true)"
[ "$TABLES" -gt 0 ] || die "database dump has no tables"
echo "Tables in dump: $TABLES"
du -sh "$OUT"/*
echo "Backup done: $OUT"
