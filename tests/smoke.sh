#!/usr/bin/env bash
# HTTP-level smoke tests: boots the real app (PHP built-in server + a real
# MySQL loaded from tests/schema.sql + tests/seed.sql) and hits a curated
# list of routes, asserting both the status code AND that each request
# completes within a time budget.
#
# The timing check exists because a wrong status code isn't the only way
# this app has broken before: ofx_admin_duplicates() was firing one live
# Github README fetch per repo with no cap, so it didn't 500 - it just
# hung, and a plain "did I get a 200" test would've missed that
# completely. --max-time here turns "hangs" into "fails loudly in CI"
# instead of "nobody notices until a real request hangs in production."
#
# Run locally: BASE_URL=http://localhost:8080 tests/smoke.sh (with
# php -S localhost:8080 router.php already running against a DB seeded
# from tests/schema.sql + tests/seed.sql)

set -uo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8080}"
MAX_TIME="${SMOKE_MAX_TIME:-8}"
FAILED=0

# path, expected_status, [method]
check() {
  local path="$1"
  local expected="$2"
  local method="${3:-GET}"
  local start end elapsed status

  start=$(date +%s.%N)
  status=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$MAX_TIME" -X "$method" "${BASE_URL}${path}")
  local curl_exit=$?
  end=$(date +%s.%N)
  elapsed=$(awk "BEGIN { printf \"%.2f\", $end - $start }")

  if [ "$curl_exit" -eq 28 ]; then
    echo "FAIL  $method $path -> timed out after ${MAX_TIME}s (this is the exact failure mode ofx_admin_duplicates() had)"
    FAILED=1
    return
  fi
  if [ "$curl_exit" -ne 0 ]; then
    echo "FAIL  $method $path -> curl error (exit $curl_exit)"
    FAILED=1
    return
  fi
  if [ "$status" != "$expected" ]; then
    echo "FAIL  $method $path -> HTTP $status (expected $expected) [${elapsed}s]"
    FAILED=1
    return
  fi
  echo "pass  $method $path -> HTTP $status [${elapsed}s]"
}

echo "Smoke testing against $BASE_URL (max ${MAX_TIME}s per request)"
echo

# --- public pages, should all be reachable without a session ---
check "/" 200
check "/categories" 200
check "/categories/graphics" 200
check "/addons" 200
check "/addons/smoketest-owner/ofxSmokeTest" 200
check "/freshest" 200
check "/newest" 200
check "/popular" 200
check "/browse" 200
check "/unsorted" 200
check "/versions" 200
check "/contributors" 200
check "/contributors/smoketest-owner" 200
check "/pages/howto" 200
check "/history" 200
check "/sitemap" 200

# --- public JSON feeds ---
check "/browse.json" 200
check "/banned.json" 200
check "/addon-repos.json" 200
check "/sitemap.xml" 200
check "/sitemap.json" 200

# --- unknown route ---
check "/this-route-does-not-exist-xyz" 404

# --- session-gated: no cookie sent, so every one of these must refuse,
#     not silently render admin/owner-only content ---
check "/admin/repos" 403
check "/admin/security" 403
check "/admin/duplicates" 403
check "/admin/flagged" 403
check "/my/addons" 403

# --- machine-to-machine API: no bearer token, must be rejected ---
check "/api/triage/batch" 403
check "/api/triage/submit" 403 "POST"

echo
if [ "$FAILED" -eq 0 ]; then
  echo "All smoke tests passed."
  exit 0
else
  echo "One or more smoke tests FAILED."
  exit 1
fi
