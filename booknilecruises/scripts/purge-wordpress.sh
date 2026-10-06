#!/usr/bin/env bash
# Deletes the WordPress files that cutover.sh kept next to public_html.
# Irreversible: afterwards only the backup in ~/backups can bring WordPress back.
# Run it only after checking the new site, and only with the confirmation word:
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/purge-wordpress.sh | CONFIRM=DELETE-WORDPRESS bash"
#
# The WordPress database is not touched here; delete it in hPanel > Databases.
set -euo pipefail
die() { echo "ERROR: $*" >&2; exit 1; }

[ "${CONFIRM:-}" = "DELETE-WORDPRESS" ] || die "set CONFIRM=DELETE-WORDPRESS to delete WordPress for good"
STATE="$HOME/deploy/LAST_CUTOVER"
[ -f "$STATE" ] || die "no cutover record at $STATE"
OLD="$(sed -n 's/^old=//p' "$STATE")"; SITE_DIR="$(sed -n 's/^site=//p' "$STATE")"
[ -d "$OLD" ] || die "$OLD is already gone"
[ -f "$OLD/wp-config.php" ] || die "$OLD does not look like a WordPress folder; refusing"
[ -f "$SITE_DIR/.bnc-static-site" ] || die "the new site is not live in $SITE_DIR; refusing"

# Refuse unless a verified backup exists.
LATEST="$(ls -1dt "$HOME"/backups/booknilecruises-* 2>/dev/null | head -n1 || true)"
[ -n "$LATEST" ] || die "no backup in ~/backups; run backup-on-server.sh first"
[ -s "$LATEST/database.sql.gz" ] && [ -s "$LATEST/files.tar.gz" ] || die "latest backup $LATEST is incomplete"
gzip -t "$LATEST/database.sql.gz" && tar -tzf "$LATEST/files.tar.gz" > /dev/null || die "latest backup $LATEST is corrupt"
echo "Backup verified: $LATEST"

# Leftover folders from failed or rolled-back attempts go too.
PARENT="$(dirname "$SITE_DIR")"
rm -rf -- "$OLD"
find "$PARENT" -mindepth 1 -maxdepth 1 -type d \( -name 'new-site-failed-*' -o -name 'new-site-rolled-back-*' \) -exec rm -rf -- {} +
echo "WordPress files deleted. Keep $LATEST (download it to your computer)."
echo "Last step: delete the WordPress database in hPanel > Databases."
