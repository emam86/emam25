#!/usr/bin/env bash
# Puts WordPress back after cutover.sh, using the folder it kept next to
# public_html. The new site is moved aside, not deleted. Runs ON the server:
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/rollback.sh | bash"
set -euo pipefail
die() { echo "ERROR: $*" >&2; exit 1; }

STATE="$HOME/deploy/LAST_CUTOVER"
[ -f "$STATE" ] || die "no cutover record at $STATE"
OLD="$(sed -n 's/^old=//p' "$STATE")"; STAMP="$(sed -n 's/^stamp=//p' "$STATE")"; SITE_DIR="$(sed -n 's/^site=//p' "$STATE")"
[ -d "$OLD" ] || die "WordPress folder $OLD is gone (deleted by purge-wordpress.sh?). Restore from the backup in ~/backups instead."
[ -f "$OLD/wp-config.php" ] || die "$OLD does not look like the WordPress site"
[ -f "$SITE_DIR/.bnc-static-site" ] || die "$SITE_DIR does not hold the new site; nothing to roll back"
PARENT="$(dirname "$SITE_DIR")"
ASIDE="$PARENT/new-site-rolled-back-$(date +%Y%m%d-%H%M%S)"

echo "Moving photos back to wp-content/uploads"
mkdir -p "$OLD/wp-content/uploads"
[ -d "$SITE_DIR/images" ] && find "$SITE_DIR/images" -mindepth 1 -maxdepth 1 -exec mv -t "$OLD/wp-content/uploads/" {} +
echo "Moving the new site aside to $ASIDE"
mkdir "$ASIDE"
find "$SITE_DIR" -mindepth 1 -maxdepth 1 ! -name '.well-known' -exec mv -t "$ASIDE/" {} +
echo "Restoring WordPress into $SITE_DIR"
find "$OLD" -mindepth 1 -maxdepth 1 -exec mv -t "$SITE_DIR/" {} +
rmdir "$OLD"
mv "$STATE" "$STATE.rolled-back-$STAMP"
echo "Done. WordPress is live again."
