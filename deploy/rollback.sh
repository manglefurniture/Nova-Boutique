#!/usr/bin/env bash
set -Eeuo pipefail

: "${APP_ROOT:?APP_ROOT is required}"
target="${1:-}"
[[ -n "$target" ]] || { echo "usage: rollback.sh <commit-or-branch>" >&2; exit 64; }

cd "$APP_ROOT"
[[ -z "$(git status --porcelain=v1)" ]] || { echo "ROLLBACK_FAILED working tree is not clean" >&2; exit 1; }
git fetch --all --prune
git rev-parse --verify "${target}^{commit}" >/dev/null
git checkout --detach "$target"

echo "ROLLBACK_CODE_OK $(git rev-parse HEAD)"
echo "Database rollback is separate: restore only from a verified backup when schema compatibility requires it."
