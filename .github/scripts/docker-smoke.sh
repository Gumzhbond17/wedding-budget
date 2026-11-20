#!/usr/bin/env bash
# Smoke test of the running Docker stack (docker compose up), as a browser would use it on the
# single origin: security headers, SPA routes, register, wedding, expense, payment, receipt
# upload and signed download, dashboard. Exits non-zero on the first failure.
set -euo pipefail

BASE="${BASE:-http://localhost:8080}"
JAR="$(mktemp)"
WORK="$(mktemp -d)"
trap 'rm -rf "$JAR" "$WORK"' EXIT

fail() { echo "FAIL: $*" >&2; exit 1; }
step() { echo "--- $*"; }

# Laravel URL-encodes the XSRF-TOKEN cookie; the header wants the decoded value.
xsrf() {
  local raw
  raw="$(awk '$6 == "XSRF-TOKEN" { print $7 }' "$JAR")"
  printf '%b' "${raw//%/\\x}"
}

# JSON API call with the session cookie, XSRF header and the SPA's Origin/Referer (Sanctum
# only treats requests from SANCTUM_STATEFUL_DOMAINS as stateful).
api() {
  local method="$1" path="$2" body="${3:-}"
  curl -fsS -b "$JAR" -c "$JAR" -X "$method" "$BASE/api/v1$path" \
    -H 'Accept: application/json' -H 'Content-Type: application/json' \
    -H "X-XSRF-TOKEN: $(xsrf)" -H "Origin: $BASE" -H "Referer: $BASE/" \
    ${body:+--data "$body"}
}

step "SPA and security headers"
headers="$(curl -fsS -D - -o /dev/null "$BASE/")"
for header in 'x-content-type-options: nosniff' 'referrer-policy: strict-origin-when-cross-origin' "content-security-policy: frame-ancestors 'none'"; do
  grep -qi "^$header" <<<"$headers" || fail "missing header: $header"
done
grep -qi '^strict-transport-security' <<<"$headers" && fail 'HSTS sent over plain HTTP'
curl -fsS -o /dev/null "$BASE/weddings/1/expenses" || fail 'SPA fallback route'
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/v1/auth/user")" = 401 ] || fail 'guest must get 401'

step "Register"
curl -fsS -b "$JAR" -c "$JAR" -o /dev/null -H "Referer: $BASE/" "$BASE/sanctum/csrf-cookie"
api POST /auth/register '{"name":"Smoke","email":"smoke@example.test","password":"Secret-pass-123","password_confirmation":"Secret-pass-123"}' >/dev/null
api GET /auth/user | jq -e '.data.email == "smoke@example.test"' >/dev/null || fail 'not logged in after register'

step "Wedding, expense, payment"
wedding="$(api POST /weddings '{"name":"ງານແຕ່ງ Smoke","base_currency":"LAK","budget_target":"300000000"}' | jq -r '.data.id')"
category="$(api GET "/weddings/$wedding/categories" | jq -r '[.data[] | select(.type == "expense")][0].id')"
expense="$(api POST "/weddings/$wedding/expenses" "{\"category_id\":$category,\"description\":\"Venue\",\"currency\":\"LAK\",\"estimated_amount\":\"50000000\"}" | jq -r '.data.id')"
payment="$(api POST "/weddings/$wedding/expenses/$expense/payments" '{"amount":"20000000","paid_date":"2026-01-15"}' | jq -r '.data.id')"

step "Receipt upload and signed download"
# Relative file names: works with any curl build, including Windows curl.exe under Git Bash.
cd "$WORK"
printf '%s' 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' | base64 -d >receipt.png
curl -fsS -b "$JAR" -c "$JAR" -o /dev/null -H 'Accept: application/json' -H "X-XSRF-TOKEN: $(xsrf)" \
  -H "Origin: $BASE" -H "Referer: $BASE/" -F "receipt=@receipt.png;type=image/png" \
  "$BASE/api/v1/weddings/$wedding/expenses/$expense/payments/$payment/receipt"
url="$(api GET "/weddings/$wedding/expenses/$expense/payments/$payment/receipt" | jq -r '.data.url')"
[[ "$url" == "$BASE/"* ]] || fail "signed URL not on the app origin: $url"
download="$(curl -fsS -D - -o downloaded.png "$url")"
grep -qi '^content-type: image/png' <<<"$download" || fail 'receipt content type'
grep -qi '^referrer-policy: no-referrer' <<<"$download" || fail 'receipt referrer policy'
cmp -s receipt.png downloaded.png || fail 'receipt bytes differ'

step "Dashboard"
api GET "/weddings/$wedding/dashboard" | jq -e '.data.summary.expenses.paid.amount == "20000000.00"' >/dev/null || fail 'dashboard paid total'

echo "Smoke test passed"
