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

TMP_LINK="${ROOT}/current.tmp.$$"
ln -sfn "$RELEASE_DIR" "$TMP_LINK"

# True atomic switch: rename() is a single, atomic syscall — there is never a
# moment where `current` does not exist or points at a half-written state.
# GNU mv's -T ("no target directory") disables mv's POSIX directory-following
# special case (which would otherwise move TMP_LINK *into* the directory
# `current` resolves to, rather than replacing `current` itself) and performs
# a plain rename(). Production hosts are Linux/GNU coreutils (see
# docs/runbooks/gap-049-host-provisioning.md), so this atomic path is what
# actually runs in production.
if mv --help 2>&1 | grep -q -- '-T'; then
  mv -T "$TMP_LINK" "${ROOT}/current"
else
  # Non-GNU mv (e.g. BSD/macOS — local dev/test runs of this script only,
  # never production): `ln -sfn` correctly replaces an existing
  # symlink-to-directory rather than following it, matching GNU `mv -T`'s
  # target-replacement semantics, but internally performs an unlink+symlink
  # rather than a single atomic rename, so there is a brief window (absent
  # on the production/GNU path above) where `current` does not exist.
  # Acceptable only because this branch never executes on a real deploy host.
  ln -sfn "$RELEASE_DIR" "${ROOT}/current"
  rm -f "$TMP_LINK"
fi

log "current -> releases/${SHA}"
