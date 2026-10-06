#!/usr/bin/env bash
# Puts WordPress back after cutover.sh, from the folder it kept next to
# public_html. Only the files the new site installed are moved aside (not
# deleted); anything else in public_html is left alone. Runs ON the server:
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/rollback.sh | bash"
set -euo pipefail
die() { echo "ERROR: $*" >&2; exit 1; }

STATE="$HOME/deploy/LAST_CUTOVER"
[ -f "$STATE" ] || die "no cutover record at $STATE"
get() { sed -n "s/^$1=//p" "$STATE"; }
OLD="$(get old)"; STAMP="$(get stamp)"; SITE_DIR="$(get site)"; ENTRIES="$(get entries)"
[ -d "$OLD" ] || die "WordPress folder $OLD is gone (deleted by purge-wordpress.sh?). Restore from the backup in ~/backups instead."
[ -f "$OLD/wp-config.php" ] || die "$OLD does not look like the WordPress site"
[ -f "$SITE_DIR/.bnc-static-site" ] || die "$SITE_DIR does not hold the new site; nothing to roll back"
[ -f "$ENTRIES" ] || die "list of installed files $ENTRIES is missing"
ASIDE="$(dirname "$SITE_DIR")/new-site-rolled-back-$(date +%Y%m%d-%H%M%S)"

echo "Moving photos back to wp-content/uploads"
mkdir -p "$OLD/wp-content/uploads"
if [ -d "$SITE_DIR/images" ]; then
  find "$SITE_DIR/images" -mindepth 1 -maxdepth 1 -exec mv -t "$OLD/wp-content/uploads/" {} +
  rmdir "$SITE_DIR/images"
fi
echo "Moving the new site aside to $ASIDE"
mkdir "$ASIDE"
while IFS= read -r name; do
  [ "$name" = images ] && continue
  if [ -e "$SITE_DIR/$name" ]; then mv "$SITE_DIR/$name" "$ASIDE/"; fi
done < "$ENTRIES"
echo "Restoring WordPress into $SITE_DIR"
find "$OLD" -mindepth 1 -maxdepth 1 -exec mv -t "$SITE_DIR/" {} +
rmdir "$OLD"
mv "$STATE" "$STATE.rolled-back-$STAMP"
echo "Done. WordPress is live again."
