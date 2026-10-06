#!/usr/bin/env bash
# Replaces the WordPress site on booknilecruises.net with the new static site.
# Runs ON the Hostinger server, from any computer:
#
#   ssh -p 65002 USER@SERVER_IP "curl -fsSL https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises/scripts/cutover.sh | bash"
#
# What it does, in order (it stops at the first problem):
#   1. Takes a fresh backup (database + files) with backup-on-server.sh.
#   2. Downloads the built site and checks its checksum.
#   3. Moves everything WordPress out of public_html into a folder next to it
#      (not reachable from the web, nothing is deleted yet).
#   4. Moves the photos from wp-content/uploads to public_html/images.
#   5. Installs the new site and checks the live pages, photos and redirects.
#      If a check fails it puts WordPress back automatically.
# rollback.sh undoes it later; purge-wordpress.sh deletes WordPress for good.
set -euo pipefail

ARTIFACT_BASE="${ARTIFACT_BASE:-https://raw.githubusercontent.com/emam86/emam25/claude/bold-wozniak-afuxs1/booknilecruises}"
VERIFY_ORIGIN="${VERIFY_ORIGIN:-https://booknilecruises.net}"
VERIFY="${VERIFY:-1}"

die() { echo "ERROR: $*" >&2; exit 1; }
fetch() { curl -fsSL "$1" -o "$2" || die "download failed: $1"; }

# --- locate the site -------------------------------------------------------
if [ -z "${SITE_DIR:-}" ]; then
  for candidate in "$HOME/domains/booknilecruises.net/public_html" "$HOME/public_html"; do
    if [ -d "$candidate" ]; then SITE_DIR="$candidate"; break; fi
  done
fi
[ -n "${SITE_DIR:-}" ] && [ -d "$SITE_DIR" ] || die "site folder not found; re-run with SITE_DIR=/path/to/public_html"
[ -f "$SITE_DIR/.bnc-static-site" ] && die "the new site is already installed in $SITE_DIR"
[ -f "$SITE_DIR/wp-config.php" ] || die "no wp-config.php in $SITE_DIR; refusing to touch a folder that is not the WordPress site"
[ -d "$SITE_DIR/wp-content/uploads" ] || die "no wp-content/uploads in $SITE_DIR"
PARENT="$(dirname "$SITE_DIR")"
STAMP="$(date +%Y%m%d-%H%M%S)"
OLD="$PARENT/wordpress-old-$STAMP"
WORK="$HOME/deploy/$STAMP"
mkdir -p "$WORK"
echo "Site folder:      $SITE_DIR"
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

# --- 3+4. swap ---------------------------------------------------------------
restore() {
  echo "Restoring WordPress..." >&2
  if [ -d "$SITE_DIR/images" ]; then
    mkdir -p "$OLD/wp-content/uploads"
    find "$SITE_DIR/images" -mindepth 1 -maxdepth 1 -exec mv -t "$OLD/wp-content/uploads/" {} +
  fi
  mkdir -p "$PARENT/new-site-failed-$STAMP"
  find "$SITE_DIR" -mindepth 1 -maxdepth 1 ! -name '.well-known' -exec mv -t "$PARENT/new-site-failed-$STAMP/" {} +
  find "$OLD" -mindepth 1 -maxdepth 1 -exec mv -t "$SITE_DIR/" {} +
  rmdir "$OLD" 2>/dev/null || true
  echo "WordPress is back in $SITE_DIR" >&2
}

echo "== 3/5 Move WordPress out of public_html"
mkdir "$OLD"
find "$SITE_DIR" -mindepth 1 -maxdepth 1 ! -name '.well-known' -exec mv -t "$OLD/" {} +

echo "== 4/5 Move photos to /images and install the new site"
mkdir -p "$SITE_DIR/images"
# Only the year folders hold media; plugin logs and caches stay with WordPress.
for year in "$OLD/wp-content/uploads"/[0-9][0-9][0-9][0-9]; do
  [ -d "$year" ] && mv "$year" "$SITE_DIR/images/"
done
cp -a "$WORK/site/." "$SITE_DIR/"
printf 'old=%s\nstamp=%s\nsite=%s\n' "$OLD" "$STAMP" "$SITE_DIR" > "$HOME/deploy/LAST_CUTOVER"

# --- 5. verify ---------------------------------------------------------------
echo "== 5/5 Check the live site"
if [ "$VERIFY" = "1" ]; then
  status() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$1"; }
  location() { curl -s -o /dev/null -w '%{redirect_url}' --max-time 20 "$1"; }
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
    die "the new site was not kept; WordPress is live again"
  fi
  echo "All checks passed."
else
  echo "Checks skipped (VERIFY=0)."
fi

echo
echo "Done. The new site is live in $SITE_DIR"
echo "WordPress is kept, not reachable from the web, in: $OLD"
echo "To undo:            curl -fsSL $ARTIFACT_BASE/scripts/rollback.sh | bash"
echo "To delete WordPress: see purge-wordpress.sh (after you have checked the site)"
