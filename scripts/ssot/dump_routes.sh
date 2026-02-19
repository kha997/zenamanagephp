#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/app/ssot
php artisan route:list --json > storage/app/ssot/routes.json

echo "SSOT route map written to storage/app/ssot/routes.json"
