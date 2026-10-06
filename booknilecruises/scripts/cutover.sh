#!/usr/bin/env bash
# Replaces the WordPress site on booknilecruises.net with the new static site.
# Runs ON the Hostinger server, from any computer:
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/cutover.sh | bash"
#
# In order, stopping at the first problem:
#   1. Takes a fresh backup (database + files) with backup-on-server.sh.
#   2. Downloads the built site and checks its checksum.
#   3. Moves the WordPress files (only those) out of public_html into a folder
#      next to it, not reachable from the web. Nothing is deleted. Anything else
#      in public_html (subdomain folders, verification files) stays where it is.
#   4. Moves the photo year folders (2025/, 2026/) from wp-content/uploads to
#      public_html/images (plugin folders stay with WordPress) and installs the site.
#   5. Checks the live pages, photos and redirects.
# If anything fails from step 3 on, WordPress is put back automatically.
# rollback.sh undoes it later; purge-wordpress.sh deletes WordPress for good.
set -Eeuo pipefail

ARTIFACT_BASE="${ARTIFACT_BASE:-https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises}"
VERIFY_ORIGIN="${VERIFY_ORIGIN:-https://booknilecruises.net}"
VERIFY="${VERIFY:-1}"

die() { echo "ERROR: $*" >&2; exit 1; }
fetch() { curl -fsSL "$1" -o "$2" || die "download failed: $1"; }

# WordPress's own top-level entries; everything else in public_html is left alone.
WP_ENTRIES=(wp-admin wp-includes wp-content index.php xmlrpc.php license.txt readme.html
  .htaccess .user.ini .maintenance error_log)

# --- locate the site (real path: ~/public_html can be a symlink) -------------
if [ -z "${SITE_DIR:-}" ]; then
  for candidate in "$HOME/domains/booknilecruises.net/public_html" "$HOME/public_html"; do
    if [ -f "$candidate/wp-config.php" ] || [ -f "$candidate/.bnc-static-site" ]; then SITE_DIR="$candidate"; break; fi
  done
fi
[ -n "${SITE_DIR:-}" ] && [ -d "$SITE_DIR" ] || die "site folder not found; re-run with SITE_DIR=/path/to/public_html"
SITE_DIR="$(cd "$SITE_DIR" && pwd -P)"
[ -f "$SITE_DIR/.bnc-static-site" ] && die "the new site is already installed in $SITE_DIR"
[ -f "$SITE_DIR/wp-config.php" ] || die "no wp-config.php in $SITE_DIR; refusing to touch a folder that is not the WordPress site"
[ -d "$SITE_DIR/wp-content/uploads" ] || die "no wp-content/uploads in $SITE_DIR"
PARENT="$(dirname "$SITE_DIR")"
STAMP="$(date +%Y%m%d-%H%M%S)"
OLD="$PARENT/wordpress-old-$STAMP"
WORK="$HOME/deploy/$STAMP"
mkdir -p "$WORK"
echo "Site folder:       $SITE_DIR"
echo "WordPress kept in: $OLD"

# --- 1. backup -------------------------------------------------------------
echo "== 1/5 Backup"
fetch "$ARTIFACT_BASE/scripts/backup-on-server.sh" "$WORK/backup.sh"
SITE_DIR="$SITE_DIR" bash "$WORK/backup.sh" || die "backup failed; nothing was changed"

# --- 2. download the new site ------------------------------------------------
echo "== 2/5 Download new site"
fetch "$ARTIFACT_BASE/deploy/site.tar.gz" "$WORK/site.tar.gz"
fetch "$ARTIFACT_BASE/deploy/site.tar.gz.sha256" "$WORK/site.tar.gz.sha256"
( cd "$WORK" && sha256sum -c site.tar.gz.sha256 ) || die "checksum mismatch; nothing was changed"
mkdir -p "$WORK/site"
tar -xzf "$WORK/site.tar.gz" -C "$WORK/site"
[ -f "$WORK/site/index.html" ] && [ -f "$WORK/site/.htaccess" ] && [ -f "$WORK/site/.bnc-static-site" ] \
  || die "downloaded site is incomplete; nothing was changed"
PAGES="$(find "$WORK/site" -name index.html | wc -l)"
[ "$PAGES" -ge 150 ] || die "downloaded site has only $PAGES pages; nothing was changed"
echo "New site: $PAGES pages"

# Top-level names the new site installs (plus images/), saved for rollback.
( cd "$WORK/site" && ls -A ) > "$WORK/new-site-entries.txt"
echo images >> "$WORK/new-site-entries.txt"

# Refuse if something that is not WordPress would be overwritten by the new site.
is_wp_entry() { local e; for e in "${WP_ENTRIES[@]}"; do [ "$1" = "$e" ] && return 0; done; case "$1" in wp-*) return 0;; esac; return 1; }
while IFS= read -r name; do
  if [ -e "$SITE_DIR/$name" ] && ! is_wp_entry "$name"; then
    die "$SITE_DIR/$name already exists and is not part of WordPress; move it away first. Nothing was changed."
  fi
done < "$WORK/new-site-entries.txt"

# --- 3+4. swap, with automatic restore on any error ---------------------------
restore() {
  trap - ERR
  set +e
  echo "Restoring WordPress..." >&2
  mkdir -p "$OLD/wp-content/uploads" "$PARENT/new-site-failed-$STAMP"
  if [ -d "$SITE_DIR/images" ]; then
    find "$SITE_DIR/images" -mindepth 1 -maxdepth 1 -exec mv -t "$OLD/wp-content/uploads/" {} +
    rmdir "$SITE_DIR/images"
  fi
  while IFS= read -r name; do
    [ "$name" = images ] && continue
    [ -e "$SITE_DIR/$name" ] && mv "$SITE_DIR/$name" "$PARENT/new-site-failed-$STAMP/"
  done < "$WORK/new-site-entries.txt"
  find "$OLD" -mindepth 1 -maxdepth 1 -exec mv -t "$SITE_DIR/" {} +
  rmdir "$OLD"
  echo "WordPress is back in $SITE_DIR" >&2
}
trap 'restore; die "a step failed; WordPress was put back"' ERR

echo "== 3/5 Move WordPress out of public_html"
mkdir "$OLD"
cp "$SITE_DIR/wp-config.php" "$WORK/wp-config.php.copy"  # extra safety copy
moved=0
for entry in "$SITE_DIR"/* "$SITE_DIR"/.[!.]*; do
  [ -e "$entry" ] || continue
  if is_wp_entry "$(basename "$entry")"; then mv "$entry" "$OLD/"; moved=$((moved + 1)); fi
done
echo "Moved $moved WordPress entries."
LEFT="$(cd "$SITE_DIR" && ls -A)"
[ -n "$LEFT" ] && echo "Left in place (not WordPress): $(echo "$LEFT" | tr '\n' ' ')"

echo "== 4/5 Move media to /images and install the new site"
mkdir "$SITE_DIR/images"
# Only the year folders (2025/, 2026/, ...) hold the site's photos; plugin
# folders such as redux, revslider, wpforms or woocommerce_uploads stay with WordPress.
for year in "$OLD/wp-content/uploads"/[0-9][0-9][0-9][0-9]; do
  [ -d "$year" ] && mv "$year" "$SITE_DIR/images/"
done
cp -a "$WORK/site/." "$SITE_DIR/"
printf 'old=%s\nstamp=%s\nsite=%s\nentries=%s\n' "$OLD" "$STAMP" "$SITE_DIR" "$WORK/new-site-entries.txt" > "$HOME/deploy/LAST_CUTOVER"

# --- 5. verify ---------------------------------------------------------------
echo "== 5/5 Check the live site"
if [ "$VERIFY" = "1" ]; then
  status() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$1" || true; }
  location() { curl -s -o /dev/null -w '%{redirect_url}' --max-time 20 "$1" || true; }
  sleep 2
  FAIL=""
  [ "$(status "$VERIFY_ORIGIN/")" = "200" ] || FAIL="$FAIL home"
  [ "$(status "$VERIFY_ORIGIN/trip/blue-shadow-nile-cruise/")" = "200" ] || FAIL="$FAIL trip-page"
  [ "$(status "$VERIFY_ORIGIN/nile-cruise/")" = "200" ] || FAIL="$FAIL category-page"
  [ "$(status "$VERIFY_ORIGIN/images/2025/12/Blue-Shadow-Nile-Cruise9.jpg")" = "200" ] || FAIL="$FAIL photo"
  case "$(location "$VERIFY_ORIGIN/wp-content/uploads/2025/12/Blue-Shadow-Nile-Cruise9.jpg")" in
    */images/2025/12/Blue-Shadow-Nile-Cruise9.jpg) ;; *) FAIL="$FAIL old-photo-redirect" ;;
  esac
  case "$(location "$VERIFY_ORIGIN/cart/")" in */contact-us/) ;; *) FAIL="$FAIL page-redirect" ;; esac
  if [ -n "$FAIL" ]; then
    echo "Checks failed:$FAIL" >&2
    restore
    rm -f "$HOME/deploy/LAST_CUTOVER"
    die "the new site was not kept; WordPress is live again"
  fi
  echo "All checks passed."
else
  echo "Checks skipped (VERIFY=0)."
fi
trap - ERR

echo
echo "Done. The new site is live in $SITE_DIR"
echo "WordPress is kept, not reachable from the web, in: $OLD"
echo "To undo:             curl -fsSL $ARTIFACT_BASE/scripts/rollback.sh | bash"
echo "To delete WordPress: purge-wordpress.sh with CONFIRM=DELETE-WORDPRESS, after checking the site"
