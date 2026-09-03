#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: activate-release.sh <root> <sha>}"
SHA="${2:?Usage: activate-release.sh <root> <sha>}"

RELEASE_DIR="${ROOT}/releases/${SHA}"
require_dir "$RELEASE_DIR" "release directory"
[ -L "${RELEASE_DIR}/.env" ] || fail "release ${SHA} is not linked to shared .env — run link-shared.sh first"
[ -L "${RELEASE_DIR}/storage" ] || fail "release ${SHA} is not linked to shared storage — run link-shared.sh first"

# Portable atomic-ish symlink switch: `ln -sfn` (unlike plain `mv`) treats an
# existing "current" symlink-to-directory as the file to replace rather than
# following it and moving the release *into* that directory. GNU `mv -T`
# would do the same, but -T is not available on BSD/macOS mv, so we avoid it
# to keep this script portable across Linux deploy hosts and local/dev
# machines running the test suite.
ln -sfn "$RELEASE_DIR" "${ROOT}/current"

log "current -> releases/${SHA}"
