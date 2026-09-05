#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: rollback.sh <root> <target_sha>}"
TARGET_SHA="${2:?Usage: rollback.sh <root> <target_sha> — an explicit target release is required, HEAD~1 inference is forbidden}"

require_dir "${ROOT}/releases/${TARGET_SHA}" "rollback target release"

log "Rolling back to explicit target release ${TARGET_SHA}"
"${DIR}/activate-release.sh" "$ROOT" "$TARGET_SHA"
