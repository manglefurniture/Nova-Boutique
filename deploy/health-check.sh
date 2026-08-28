#!/usr/bin/env bash
set -Eeuo pipefail

: "${APP_URL:?APP_URL is required}"
response="$(curl --fail --silent --show-error --max-time 10 "${APP_URL%/}/health.php")"
php -r '$d=json_decode($argv[1], true); if (!is_array($d) || ($d["ok"] ?? false) !== true) { fwrite(STDERR, "HEALTH_FAILED\n"); exit(1); }' "$response"
echo "HEALTH_OK"
