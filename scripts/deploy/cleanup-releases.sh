#!/usr/bin/env bash
set -euo pipefail
DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${DIR}/lib.sh"

ROOT="${1:?Usage: cleanup-releases.sh <root> [keep_count]}"
KEEP_COUNT="${2:-3}"

RELEASES_DIR="${ROOT}/releases"
require_dir "$RELEASES_DIR" "releases directory"

CURRENT_TARGET=""
if [ -L "${ROOT}/current" ]; then
  CURRENT_TARGET="$(basename "$(readlink -f "${ROOT}/current")")"
fi

# Portable equivalent of `mapfile -t` (mapfile is bash 4+ only and is
# unavailable under the bash 3.2 shipped as /bin/bash on macOS/BSD hosts).
SORTED=()
while IFS= read -r name; do
  SORTED+=("$name")
done < <(cd "$RELEASES_DIR" && ls -1t)

KEEP=()
[ -n "$CURRENT_TARGET" ] && KEEP+=("$CURRENT_TARGET")

count=0
for name in "${SORTED[@]}"; do
  if [ "$count" -lt "$KEEP_COUNT" ]; then
    KEEP+=("$name")
  fi
  count=$((count + 1))
done

for name in "${SORTED[@]}"; do
  keep=false
  for k in "${KEEP[@]}"; do
    [ "$name" = "$k" ] && keep=true && break
  done
  if [ "$keep" = false ]; then
    log "Removing eligible old release: ${name}"
    rm -rf "${RELEASES_DIR:?}/${name}"
  fi
done

log "Cleanup complete. shared/ untouched by design (never referenced above)."
