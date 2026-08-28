#!/usr/bin/env bash
set -Eeuo pipefail

: "${APP_ROOT:?APP_ROOT is required}"
: "${BACKUP_ROOT:?BACKUP_ROOT is required}"

for command in git php curl mariadb mariadb-dump sha256sum; do
  command -v "$command" >/dev/null 2>&1 || {
    echo "PREFLIGHT_FAILED missing command: $command" >&2
    exit 1
  }
done

cd "$APP_ROOT"
git rev-parse --is-inside-work-tree >/dev/null
[[ -z "$(git status --porcelain=v1)" ]] || { echo "PREFLIGHT_FAILED working tree is not clean" >&2; exit 1; }
[[ -f "$APP_ROOT/.env" ]] || { echo "PREFLIGHT_FAILED .env missing" >&2; exit 1; }
[[ -f "$APP_ROOT/deploy/backup.sh" ]] || { echo "PREFLIGHT_FAILED backup.sh missing" >&2; exit 1; }
[[ -f "$APP_ROOT/bin/release-expired-reservations.php" ]] || { echo "PREFLIGHT_FAILED reservation job missing" >&2; exit 1; }

mkdir -p "$BACKUP_ROOT"
[[ -w "$BACKUP_ROOT" ]] || { echo "PREFLIGHT_FAILED backup root is not writable" >&2; exit 1; }

php -l "$APP_ROOT/public/index.php" >/dev/null
php -l "$APP_ROOT/public/health.php" >/dev/null

echo "PREFLIGHT_OK"
