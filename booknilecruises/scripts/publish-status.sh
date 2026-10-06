#!/usr/bin/env bash
# Reports a publish job's state to the admin panel: publish-status.sh <running|succeeded|failed> "message"
# Needs SITE_URL, TOKEN, JOB_ID, RUN_URL. Never fails the workflow.
set -u
status="$1"; message="${2:-}"
# Runs without a panel job (hourly or started from the Actions tab) report only a finished deploy,
# as job_id null, so the panel can record it and notify IndexNow.
if [ -z "${JOB_ID:-}" ] && [ "$status" != succeeded ]; then exit 0; fi
job="${JOB_ID:-null}"
body=$(printf '{"job_id":%s,"status":"%s","run_url":"%s","message":"%s"}' "$job" "$status" "$RUN_URL" "${message//\"/\'}")
curl -fsS --retry 2 -X POST -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  --data "$body" "$SITE_URL/api/publish/status" > /dev/null || echo "warning: could not report '$status' to the panel"
exit 0
