#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: link-shared.sh <root> <release_sha>}"
SHA="${2:?Usage: link-shared.sh <root> <release_sha>}"

RELEASE_DIR="${ROOT}/releases/${SHA}"
SHARED_ENV="${ROOT}/shared/.env"
SHARED_STORAGE="${ROOT}/shared/storage"

require_dir "$RELEASE_DIR" "release directory"
[ -f "$SHARED_ENV" ] || fail "shared .env not found: ${SHARED_ENV}"
require_dir "$SHARED_STORAGE" "shared storage directory"

ln -sfn "$SHARED_ENV" "${RELEASE_DIR}/.env"
rm -rf "${RELEASE_DIR}/storage"
ln -sfn "$SHARED_STORAGE" "${RELEASE_DIR}/storage"

log "Linked shared .env and storage into ${RELEASE_DIR}"
