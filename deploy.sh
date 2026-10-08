#!/usr/bin/env bash
# Production deploy for Mini Castle Wolfenstein (shared hosting / SiteGround).
#
# Usage:  ./deploy.sh [--branch main] [--skip-seed] [--skip-composer]
#
# Run it from the checkout on the server. It pulls the latest commit, installs
# production dependencies, runs migrations, refreshes the level data, rebuilds
# Laravel's caches, and wraps the whole thing in maintenance mode so visitors
# never see a half-deployed site. Built assets are committed in
# public_html/build, so no Node is needed on the host.
set -euo pipefail

BRANCH="main"
RUN_SEED=1
RUN_COMPOSER=1
for arg in "$@"; do
    case "$arg" in
        --branch=*) BRANCH="${arg#*=}" ;;
        --branch) shift; BRANCH="${1:-main}" ;;
        --skip-seed) RUN_SEED=0 ;;
        --skip-composer) RUN_COMPOSER=0 ;;
        -h|--help) sed -n '2,12p' "$0"; exit 0 ;;
    esac
done

cd "$(dirname "$(readlink -f "$0")")"

log()  { printf '\033[1;34m==>\033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mERROR:\033[0m %s\n' "$*" >&2; exit 1; }

# --- preflight -----------------------------------------------------------
command -v php >/dev/null      || fail "php not found in PATH"
command -v composer >/dev/null || fail "composer not found in PATH"
command -v git >/dev/null      || fail "git not found in PATH"
php -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || fail "PHP 8.2 or newer is required (found $(php -r 'echo PHP_VERSION;'))"
[ -d .git ] || fail "not a git checkout: $(pwd)"

if [ ! -f .env ]; then
    log "No .env found, creating one from .env.example"
    cp .env.example .env
    sed -i.bak 's/^APP_ENV=.*/APP_ENV=production/; s/^APP_DEBUG=.*/APP_DEBUG=false/' .env && rm -f .env.bak
    echo "    -> edit .env to set APP_URL before going live"
fi

# --- deploy --------------------------------------------------------------
UP_NEEDED=0
cleanup() {
    if [ "$UP_NEEDED" = 1 ]; then
        log "Bringing the site back up"
        php artisan up >/dev/null || true
    fi
}
trap cleanup EXIT

log "Fetching origin/$BRANCH"
git fetch --quiet origin "$BRANCH"
if git diff --quiet HEAD "origin/$BRANCH" -- ; then
    log "Already at origin/$BRANCH ($(git rev-parse --short HEAD)); redeploying anyway"
fi

log "Entering maintenance mode"
php artisan down --retry=15 --secret="deploy-$(date +%s)" >/dev/null || true
UP_NEEDED=1

log "Checking out origin/$BRANCH"
git checkout --quiet "$BRANCH"
git reset --quiet --hard "origin/$BRANCH"

if [ "$RUN_COMPOSER" = 1 ]; then
    log "Installing PHP dependencies"
    composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist --quiet
fi

if ! grep -q '^APP_KEY=.\+' .env; then
    log "Generating application key"
    php artisan key:generate --force --quiet
fi

if grep -q '^DB_CONNECTION=sqlite' .env; then
    DB_FILE="$(grep '^DB_DATABASE=' .env | cut -d= -f2- || true)"
    DB_FILE="${DB_FILE:-database/database.sqlite}"
    [ -f "$DB_FILE" ] || { log "Creating SQLite database at $DB_FILE"; touch "$DB_FILE"; }
fi

log "Fixing storage permissions"
chmod -R ug+rwX storage bootstrap/cache

log "Running migrations"
php artisan migrate --force --no-interaction

if [ "$RUN_SEED" = 1 ]; then
    log "Refreshing level data"
    php artisan db:seed --class=LevelSeeder --force --no-interaction
fi

[ -f public_html/build/manifest.json ] || fail "public_html/build/manifest.json is missing; run 'npm run build' and commit the result"

log "Rebuilding caches"
php artisan optimize:clear --quiet
php artisan config:cache --quiet
php artisan route:cache --quiet
php artisan view:cache --quiet
php artisan event:cache --quiet 2>/dev/null || true

log "Deployed $(git rev-parse --short HEAD) on branch $BRANCH"
