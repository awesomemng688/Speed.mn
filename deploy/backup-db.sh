#!/usr/bin/env bash
# Daily MySQL backup with 7-day retention.
# Usage: sudo bash deploy/backup-db.sh   (cron example in deploy/README.md)
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/awe/current}"
ENV_FILE="${ENV_FILE:-$APP_DIR/.env}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/speedmn}"
KEEP_DAYS="${KEEP_DAYS:-7}"

env_value() {
    grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_HOST="$(env_value DB_HOST)"
DB_PORT="$(env_value DB_PORT)"
DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"

umask 077
mkdir -p "$BACKUP_DIR"

DEFAULTS="$(mktemp)"
trap 'rm -f "$DEFAULTS"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\nport=%s\n' "$DB_USER" "$DB_PASS" "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" > "$DEFAULTS"

OUT="$BACKUP_DIR/${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump --defaults-extra-file="$DEFAULTS" --single-transaction --quick --no-tablespaces "$DB_NAME" | gzip -9 > "$OUT"

find "$BACKUP_DIR" -name "${DB_NAME}_*.sql.gz" -type f -mtime +"$KEEP_DAYS" -delete
echo "Backup written: $OUT"
