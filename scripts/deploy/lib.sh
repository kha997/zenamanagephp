#!/usr/bin/env bash
# Shared helpers for GAP-049 release scripts. Sourced, not executed directly.
set -euo pipefail

log() {
  echo "[deploy] $*" >&2
}

fail() {
  echo "[deploy][ERROR] $*" >&2
  exit 1
}

require_dir() {
  local dir="$1"
  local label="$2"
  [ -d "$dir" ] || fail "${label} not found: ${dir}"
}
