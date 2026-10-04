#!/usr/bin/env bash
set -Eeuo pipefail

RESOLVED_SCRIPT="$(readlink -f -- "${BASH_SOURCE[0]}")"
SCRIPT_DIR="$(dirname -- "$RESOLVED_SCRIPT")"
APP_DIR_DEFAULT="$(dirname -- "$SCRIPT_DIR")"
if [[ "$(basename -- "$(dirname -- "$APP_DIR_DEFAULT")")" == "releases" ]]; then
    APP_DIR_DEFAULT="$(dirname -- "$(dirname -- "$APP_DIR_DEFAULT")")"
fi
APP_DIR="${APP_DIR:-$APP_DIR_DEFAULT}"
BRANCH="${BRANCH:-main}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
RELEASES_DIR="$APP_DIR/releases"
CURRENT_LINK="$APP_DIR/current"

if [[ ! -d "$APP_DIR/.git" || ! -f "$APP_DIR/.env" ]]; then
    echo "ERROR: expected a Git checkout and preserved .env at $APP_DIR." >&2
    exit 1
fi
if [[ ! -d "$APP_DIR/storage" ]]; then
    echo "ERROR: shared storage directory is missing at $APP_DIR/storage." >&2
    exit 1
fi

mkdir -p "$RELEASES_DIR"
exec 9>"$APP_DIR/.deploy.lock"
if ! flock -n 9; then
    echo "ERROR: another deployment is already running." >&2
    exit 1
fi

if [[ -e "$CURRENT_LINK" && ! -L "$CURRENT_LINK" ]]; then
    echo "ERROR: $CURRENT_LINK exists but is not a symlink; migrate it manually before deploying." >&2
    exit 1
fi
if [[ ! -e "$CURRENT_LINK" && ! -L "$CURRENT_LINK" ]]; then
    ln -s "$APP_DIR" "$CURRENT_LINK"
fi

previous_target=""
if [[ -L "$CURRENT_LINK" ]]; then
    previous_target="$(readlink -f -- "$CURRENT_LINK")"
fi
release_switched=0
maintenance_enabled=0
release_dir=""

restore_on_exit() {
    local status=$?
    trap - EXIT

    if (( status != 0 && release_switched == 1 )) && [[ -n "$previous_target" ]]; then
        local rollback_link="$APP_DIR/.current-rollback-$$"
        ln -s "$previous_target" "$rollback_link"
        mv -Tf "$rollback_link" "$CURRENT_LINK"
        echo "Deployment failed; restored previous release at $previous_target." >&2
    fi

    if (( maintenance_enabled == 1 )); then
        if ! "$PHP_BIN" "$CURRENT_LINK/artisan" up; then
            echo "ERROR: could not disable maintenance mode." >&2
            status=1
        fi
    fi

    if (( status == 0 )); then
        prune_old_releases
        echo "Deployment completed: $commit (release ${release_id})."
    fi

    exit "$status"
}

prune_old_releases() {
    local release_paths=()
    mapfile -t release_paths < <(
        find "$RELEASES_DIR" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
            | sort -nr \
            | awk -v keep="$KEEP_RELEASES" 'NR > keep { sub(/^[^ ]+ /, ""); print }'
    )

    local current_target="$(readlink -f -- "$CURRENT_LINK")"
    local old_release
    for old_release in "${release_paths[@]}"; do
        [[ "$old_release" == "$current_target" ]] && continue
        rm -rf -- "$old_release"
    done
}

trap restore_on_exit EXIT

git -c safe.directory="$APP_DIR" -C "$APP_DIR" fetch --prune origin "$BRANCH"
commit="$(git -c safe.directory="$APP_DIR" -C "$APP_DIR" rev-parse "origin/$BRANCH^{commit}")"
release_id="$(date -u +%Y%m%d%H%M%S)-${commit:0:7}"
release_dir="$RELEASES_DIR/$release_id"
if [[ -e "$release_dir" ]]; then
    echo "ERROR: release path already exists: $release_dir" >&2
    exit 1
fi

mkdir -p "$release_dir"
git -c safe.directory="$APP_DIR" -C "$APP_DIR" archive "$commit" -- \
    . \
    ':(exclude)public/skins/img' \
    ':(exclude)public/fastdl' \
    ':(exclude)fastdl' \
    | tar -x -C "$release_dir"

if [[ -e "$release_dir/.env" || -L "$release_dir/.env" ]]; then
    echo "ERROR: .env is present in the release archive; refusing to deploy it." >&2
    exit 1
fi
ln -s "$APP_DIR/.env" "$release_dir/.env"
rm -rf -- "$release_dir/storage"
ln -s "$APP_DIR/storage" "$release_dir/storage"
mkdir -p "$APP_DIR/storage/app/public" "$APP_DIR/storage/framework/cache" \
    "$APP_DIR/storage/framework/sessions" "$APP_DIR/storage/framework/views" "$APP_DIR/storage/logs"
ln -sfn "$APP_DIR/storage/app/public" "$release_dir/public/storage"

for preserved_path in public/skins/img public/fastdl fastdl; do
    preserved_target="$APP_DIR/$preserved_path"
    release_path="$release_dir/$preserved_path"
    if [[ -e "$preserved_target" || -L "$preserved_target" ]]; then
        if [[ -e "$release_path" && ! -L "$release_path" ]]; then
            rm -rf -- "$release_path"
        fi
        mkdir -p "$(dirname -- "$release_path")"
        ln -sfn "$preserved_target" "$release_path"
    fi
done

sudo chown -R www-data:www-data "$release_dir"
sudo -u www-data "$COMPOSER_BIN" install \
    --working-dir="$release_dir" \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

"$PHP_BIN" "$CURRENT_LINK/artisan" down --render="errors::503"
maintenance_enabled=1
"$PHP_BIN" "$release_dir/artisan" migrate --force

next_link="$APP_DIR/.current-$release_id"
ln -s "$release_dir" "$next_link"
mv -Tf "$next_link" "$CURRENT_LINK"
release_switched=1

"$PHP_BIN" "$CURRENT_LINK/artisan" optimize:clear
"$PHP_BIN" "$CURRENT_LINK/artisan" config:cache
"$PHP_BIN" "$CURRENT_LINK/artisan" view:cache
sudo chown -R www-data:www-data "$APP_DIR/storage" "$release_dir/bootstrap/cache"
sudo find "$APP_DIR/storage" "$release_dir/bootstrap/cache" -type d -exec chmod 775 {} \;
sudo find "$APP_DIR/storage" "$release_dir/bootstrap/cache" -type f -exec chmod 664 {} \;

sudo apachectl configtest
if command -v supervisorctl >/dev/null 2>&1; then
    if sudo supervisorctl status 'speedmn-worker:*' >/dev/null 2>&1; then
        sudo supervisorctl restart 'speedmn-worker:*'
    fi
    if sudo supervisorctl status speedmn-scheduler >/dev/null 2>&1; then
        sudo supervisorctl restart speedmn-scheduler
    fi
fi
sudo systemctl reload apache2
for php_fpm_service in php8.4-fpm php8.3-fpm php8.2-fpm; do
    if sudo systemctl is-active --quiet "$php_fpm_service"; then
        sudo systemctl reload "$php_fpm_service"
        break
    fi
done

"$PHP_BIN" "$CURRENT_LINK/artisan" up
maintenance_enabled=0
"$PHP_BIN" "$CURRENT_LINK/artisan" route:list --except-vendor >/dev/null
app_url="$(cd "$CURRENT_LINK" && "$PHP_BIN" -r 'require "vendor/autoload.php"; Dotenv\Dotenv::createImmutable(getcwd())->safeLoad(); echo $_ENV["APP_URL"] ?? "";')"
if [[ ! "$app_url" =~ ^https?:// ]]; then
    echo "ERROR: APP_URL is missing or invalid; deployment smoke test failed." >&2
    exit 1
fi
curl --fail --silent --show-error --location --max-time 20 --output /dev/null "$app_url"
"$PHP_BIN" "$CURRENT_LINK/artisan" speedmn:deploy-smoke-record
