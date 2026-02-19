#!/usr/bin/env bash
set -euo pipefail

required_files=(
  "scripts/ssot/allow_orphan_baseline.txt"
  "scripts/ssot/collect_skip_inventory.php"
)

required_globs=(
  "scripts/ssot/baselines/*"
)

missing=0

for path in "${required_files[@]}"; do
  if [[ ! -f "$path" ]]; then
    echo "[ssot-baseline-guard] missing required file: $path" >&2
    missing=1
    continue
  fi

  if ! git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
    echo "[ssot-baseline-guard] file is not tracked by git: $path" >&2
    missing=1
  fi
done

for pattern in "${required_globs[@]}"; do
  shopt -s nullglob
  matches=( $pattern )
  shopt -u nullglob

  if [[ ${#matches[@]} -eq 0 ]]; then
    echo "[ssot-baseline-guard] no files matched required baseline pattern: $pattern" >&2
    missing=1
    continue
  fi

  for path in "${matches[@]}"; do
    if [[ ! -f "$path" ]]; then
      continue
    fi

    if ! git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
      echo "[ssot-baseline-guard] baseline file is not tracked by git: $path" >&2
      missing=1
    fi
  done
done

if [[ "$missing" -ne 0 ]]; then
  exit 1
fi

echo "[ssot-baseline-guard] OK"
