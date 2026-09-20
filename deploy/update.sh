#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${APP_DIR:-/var/www/speed.mn}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"

cd "$APP_DIR"

if [[ ! -d .git ]]; then
    echo "ERROR: $APP_DIR is not a Git working tree." >&2
    echo "Clone the repository there once before running this script." >&2
    exit 1
fi

echo "Updating $APP_DIR from origin/$BRANCH"
git fetch --prune origin "$BRANCH"
if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: local changes exist in $APP_DIR; refusing to overwrite them." >&2
    git status --short
    exit 1
fi
git merge --ff-only "origin/$BRANCH"

if command -v php-fpm8.4 >/dev/null 2>&1; then
    PHP_FPM_SERVICE="php8.4-fpm"
else
    PHP_FPM_SERVICE=""
fi

"$PHP_BIN" artisan down --render="errors::503" || true
trap '"$PHP_BIN" artisan up' EXIT

sudo -u www-data "$COMPOSER_BIN" install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan optimize:clear
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 664 {} \;

sudo systemctl reload apache2
if [[ -n "$PHP_FPM_SERVICE" ]]; then
    sudo systemctl reload "$PHP_FPM_SERVICE"
fi

echo "Deployment completed: $(git rev-parse --short HEAD)"
