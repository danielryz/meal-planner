#!/usr/bin/env bash
# Smoke tests against a running MealPlanner instance.
# Usage: BASE_URL=http://localhost:8080 bash scripts/smoke.sh

set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8080}"
COOKIE_JAR=$(mktemp)
PASS=0
FAIL=0

RED='\033[0;31m'
GREEN='\033[0;32m'
RESET='\033[0m'

check() {
    local label="$1"
    local expected="$2"
    local actual="$3"

    if [ "$actual" = "$expected" ]; then
        echo -e "  ${GREEN}PASS${RESET}  $label"
        ((PASS++))
    else
        echo -e "  ${RED}FAIL${RESET}  $label  (expected HTTP $expected, got $actual)"
        ((FAIL++))
    fi
}

http_code() {
    curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" "$@"
}

http_code_json() {
    curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
        -H "Content-Type: application/json" "$@"
}

echo ""
echo "MealPlanner smoke tests → $BASE_URL"
echo "────────────────────────────────────"

# --- Public pages ---
echo ""
echo "Public pages"
check "Login page loads"     200 "$(http_code "$BASE_URL/login")"
check "Register page loads"  200 "$(http_code "$BASE_URL/register")"
check "Unknown route is 404" 404 "$(http_code "$BASE_URL/this-does-not-exist")"

# --- Unauthenticated API redirects ---
echo ""
echo "API without auth (expect 302 redirect)"
check "GET /api/recipes"        302 "$(http_code "$BASE_URL/api/recipes")"
check "GET /api/my-recipes"     302 "$(http_code "$BASE_URL/api/my-recipes")"
check "GET /api/meal-plans"     302 "$(http_code "$BASE_URL/api/meal-plans")"
check "GET /api/grocery-lists"  302 "$(http_code "$BASE_URL/api/grocery-lists")"
check "GET /api/recipe-reviews" 302 "$(http_code "$BASE_URL/api/recipe-reviews")"

# --- Login ---
echo ""
echo "Authentication"

LOGIN_CODE=$(curl -s -o /dev/null -w "%{http_code}" -c "$COOKIE_JAR" \
    -X POST "$BASE_URL/login" \
    -d "email=employee@example.com&password=password")

check "Login with valid credentials" 302 "$LOGIN_CODE"

# If login failed, skip authenticated tests
if [ "$LOGIN_CODE" != "302" ]; then
    echo ""
    echo "  Skipping authenticated tests (login failed)"
else
    # --- Authenticated API calls ---
    echo ""
    echo "Authenticated API"

    check "GET /api/recipes returns 200"       200 "$(http_code "$BASE_URL/api/recipes")"
    check "GET /api/my-recipes returns 200"    200 "$(http_code "$BASE_URL/api/my-recipes")"
    check "GET /api/grocery-lists returns 200" 200 "$(http_code "$BASE_URL/api/grocery-lists")"
    check "GET /api/profile returns 200"       200 "$(http_code "$BASE_URL/api/profile")"

    # Non-existent recipe
    check "GET /api/recipes/999999 returns 404" 404 "$(http_code "$BASE_URL/api/recipes/999999")"

    # Draft creation
    DRAFT_CODE=$(http_code_json \
        -X POST "$BASE_URL/api/recipes/drafts" \
        --data-raw '{"title":"Testowy przepis dymny","description":"Opis testowy minimalny do testu smoke 20 znakow","ingredients":[{"name":"Makaron","amount":"200g"}],"steps":[{"instruction":"Ugotuj makaron"}]}')
    check "POST /api/recipes/drafts returns 201" 201 "$DRAFT_CODE"

    # Review queue (employee role)
    check "GET /api/recipe-reviews returns 200" 200 "$(http_code "$BASE_URL/api/recipe-reviews")"

    # Meal plan list
    check "GET /api/meal-plans returns 200" 200 "$(http_code "$BASE_URL/api/meal-plans")"
fi

# --- Summary ---
echo ""
echo "────────────────────────────────────"
TOTAL=$((PASS + FAIL))
echo "  Passed: $PASS / $TOTAL"

if [ "$FAIL" -gt 0 ]; then
    echo -e "  ${RED}Failed: $FAIL${RESET}"
    rm -f "$COOKIE_JAR"
    exit 1
else
    echo -e "  ${GREEN}All tests passed.${RESET}"
    rm -f "$COOKIE_JAR"
    exit 0
fi
