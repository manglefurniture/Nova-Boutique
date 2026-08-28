#!/usr/bin/env bash
set -Eeuo pipefail

: "${APP_ROOT:?APP_ROOT is required}"
: "${BACKUP_ROOT:?BACKUP_ROOT is required}"
: "${DB_HOST:?DB_HOST is required}"
: "${DB_DATABASE:?DB_DATABASE is required}"
: "${DB_USERNAME:?DB_USERNAME is required}"
: "${DB_PASSWORD:?DB_PASSWORD is required}"

command -v git >/dev/null 2>&1 || { echo "git is required" >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "php is required" >&2; exit 1; }
command -v mariadb-dump >/dev/null 2>&1 || { echo "mariadb-dump is required" >&2; exit 1; }
command -v tar >/dev/null 2>&1 || { echo "tar is required" >&2; exit 1; }

[[ -f "$APP_ROOT/.env" ]] || { echo "BACKUP_FAILED .env missing" >&2; exit 1; }
upload_dir="$({
  php -r '
    $values = parse_ini_file($argv[1], false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        fwrite(STDERR, "Could not parse .env\n");
        exit(2);
    }
    $configured = trim((string) ($values["UPLOAD_DIR"] ?? ""));
    echo $configured !== "" ? $configured : $argv[2];
  ' "$APP_ROOT/.env" "$APP_ROOT/public/uploads/productos"
})" || { echo "BACKUP_FAILED could not resolve UPLOAD_DIR" >&2; exit 1; }
[[ -n "$upload_dir" && -d "$upload_dir" ]] || { echo "BACKUP_FAILED upload directory missing: $upload_dir" >&2; exit 1; }

stamp="$(date -u +%Y%m%d-%H%M%S)"
destination="${BACKUP_ROOT%/}/deploy-${stamp}"
mkdir -p "$destination"
chmod 700 "$destination"

cd "$APP_ROOT"
git rev-parse HEAD > "$destination/commit.txt"
git status --porcelain=v1 > "$destination/git-status.txt"

export MYSQL_PWD="$DB_PASSWORD"
mariadb-dump \
  --host="$DB_HOST" \
  --port="${DB_PORT:-3306}" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --routines --triggers --events \
  --default-character-set=utf8mb4 \
  "$DB_DATABASE" > "$destination/database.sql"
unset MYSQL_PWD

cp "$APP_ROOT/.env" "$destination/env.snapshot"
chmod 600 "$destination/env.snapshot"
printf '%s\n' "$upload_dir" > "$destination/upload-dir.txt"
tar -C "$upload_dir" -czf "$destination/uploads.tar.gz" .

[[ -s "$destination/database.sql" ]] || { echo "BACKUP_FAILED empty database dump" >&2; exit 1; }
[[ -s "$destination/uploads.tar.gz" ]] || { echo "BACKUP_FAILED empty upload archive" >&2; exit 1; }
sha256sum \
  "$destination/commit.txt" \
  "$destination/database.sql" \
  "$destination/upload-dir.txt" \
  "$destination/uploads.tar.gz" \
  > "$destination/SHA256SUMS"
printf '%s\n' "$destination"
echo "BACKUP_OK"
