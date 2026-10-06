#!/usr/bin/env bash
# Uploads the built site to the server over FTPS without touching anything it
# did not put there itself: photos (images/), the admin panel (admin/, api/),
# verification files and any other folder stay as they are.
#
#   FTP_HOST=… FTP_USER=… FTP_PASS=… FTP_DIR=public_html deploy-ftp.sh dist
#
# Each deploy stores the list of files it uploaded in .bnc-manifest on the
# server. Files listed by the previous deploy but missing from this build
# (a deleted trip, an old CSS bundle) are removed; nothing else ever is.
set -euo pipefail

DIST="${1:?usage: deploy-ftp.sh <dist-folder>}"
: "${FTP_HOST:?}" "${FTP_USER:?}" "${FTP_PASS:?}"
FTP_DIR="${FTP_DIR:-public_html}"
TLS_VERIFY="${FTP_TLS_VERIFY:-yes}"   # set to no only if the host's certificate does not match FTP_HOST
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

die() { echo "ERROR: $*" >&2; exit 1; }

# 1. The build must look like the whole site before anything is uploaded.
for f in index.html .htaccess .bnc-static-site sitemap.xml robots.txt 404.html; do
  [ -f "$DIST/$f" ] || die "$DIST/$f is missing; refusing to deploy an incomplete build"
done
pages=$(find "$DIST" -name index.html | wc -l)
[ "$pages" -ge 100 ] || die "only $pages pages in $DIST; refusing to deploy"
echo "Build: $pages pages"

safe_name() { [[ "$1" =~ ^[A-Za-z0-9_.][A-Za-z0-9._/-]*$ ]] && [[ "$1" != *..* ]]; }
# Paths a deploy may never delete, whatever an old manifest says.
protected() { case "$1" in images/*|admin/*|api/*|.well-known/*|.bnc-manifest|"") return 0;; esac; return 1; }

( cd "$DIST" && find . -type f ! -name .bnc-manifest | sed 's#^\./##' | LC_ALL=C sort ) > "$WORK/new-manifest"
while IFS= read -r f; do
  safe_name "$f" || die "unexpected file name in build: $f"
  protected "$f" && die "build contains protected path $f"
done < "$WORK/new-manifest"

lftp_run() {
  lftp -u "$FTP_USER","$FTP_PASS" "$FTP_HOST" -e "
    set cmd:fail-exit yes; set net:max-retries 3; set net:timeout 30;
    set ftp:ssl-force yes; set ftp:ssl-protect-data yes; set ssl:verify-certificate $TLS_VERIFY;
    $1
    bye"
}

# 2. Previous manifest (absent on the first deploy: then nothing is deleted).
lftp_run "cd '$FTP_DIR'; get -O '$WORK' .bnc-manifest" 2>/dev/null || true
touch "$WORK/.bnc-manifest"
LC_ALL=C sort -u "$WORK/.bnc-manifest" > "$WORK/old-manifest"

# 3. Upload everything (mirror without --delete never removes remote files).
#    Assets first, pages next, server rules last.
lftp_run "lcd '$DIST'; cd '$FTP_DIR';
  mirror -R --no-perms --parallel=4 _astro _astro;
  mirror -R --no-perms --parallel=4 -X .htaccess -X .bnc-manifest . .;
  put .htaccess"

# 4. Remove what the previous deploy uploaded and this build no longer has.
LC_ALL=C comm -23 "$WORK/old-manifest" "$WORK/new-manifest" > "$WORK/stale"
cmds=""
while IFS= read -r f; do
  [ -n "$f" ] || continue
  if protected "$f" || ! safe_name "$f"; then echo "skip protected/odd path: $f"; continue; fi
  cmds+="rm -f '$f'; "
done < "$WORK/stale"
if [ -n "$cmds" ]; then
  # Then the folders those files leave empty, deepest first (rmdir fails harmlessly on non-empty ones),
  # so a deleted page answers 404 rather than 403.
  dirs=$(while IFS= read -r f; do d=$(dirname "$f"); while [ "$d" != . ]; do echo "$d"; d=$(dirname "$d"); done; done < "$WORK/stale" \
    | LC_ALL=C sort -u | awk '{ print length, $0 }' | sort -rn | cut -d' ' -f2-)
  for d in $dirs; do protected "$d/" || ! safe_name "$d" || cmds+="rmdir '$d'; "; done
  echo "Removing $(wc -l < "$WORK/stale") files the site no longer has"
  lftp_run "cd '$FTP_DIR'; set cmd:fail-exit no; $cmds" 2>/dev/null || true
fi

# 5. Record this deploy.
cp "$WORK/new-manifest" "$WORK/upload-manifest"
lftp_run "cd '$FTP_DIR'; put '$WORK/upload-manifest' -o .bnc-manifest"
echo "Deployed $(wc -l < "$WORK/new-manifest") files to $FTP_HOST:$FTP_DIR"
