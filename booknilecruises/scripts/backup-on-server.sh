#!/usr/bin/env bash
# Full backup of the WordPress site, run ON the Hostinger server over SSH:
#   ssh -p 65002 <user>@<server-ip> 'bash -s' < scripts/backup-on-server.sh
# Writes the files archive and a database dump to ~/backups/booknilecruises-<date>/,
# outside public_html so they are never served to the web. Changes nothing on the site.
set -euo pipefail

SITE_DIR="${SITE_DIR:-$HOME/domains/booknilecruises.net/public_html}"
STAMP="$(date +%Y%m%d-%H%M)"
OUT="$HOME/backups/booknilecruises-$STAMP"

[ -f "$SITE_DIR/wp-config.php" ] || { echo "wp-config.php not found in $SITE_DIR (set SITE_DIR)"; exit 1; }
mkdir -p "$OUT"
chmod 700 "$HOME/backups" "$OUT"

# Read the database credentials from wp-config.php without printing them.
# A text match, because including wp-config.php would boot all of WordPress.
cfg() { sed -nE "s/^[[:space:]]*define\([[:space:]]*['\"]$1['\"][[:space:]]*,[[:space:]]*['\"](.*)['\"][[:space:]]*\);.*/\1/p" "$SITE_DIR/wp-config.php" | head -n1; }
DB_NAME="$(cfg DB_NAME)"; DB_USER="$(cfg DB_USER)"; DB_PASS="$(cfg DB_PASSWORD)"; DB_HOST="$(cfg DB_HOST)"

echo "1/3 Dumping database $DB_NAME"
MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --routines --triggers \
  -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" | gzip > "$OUT/database.sql.gz"

echo "2/3 Archiving files (cache folders skipped)"
tar -czf "$OUT/files.tar.gz" -C "$(dirname "$SITE_DIR")" \
  --exclude='public_html/wp-content/cache' --exclude='public_html/wp-content/litespeed' \
  "$(basename "$SITE_DIR")"

echo "3/3 Verifying"
gzip -t "$OUT/database.sql.gz"
tar -tzf "$OUT/files.tar.gz" > /dev/null
zcat "$OUT/database.sql.gz" | grep -c 'CREATE TABLE' | xargs echo "tables in dump:"
du -sh "$OUT"/*
echo "Backup done: $OUT"
