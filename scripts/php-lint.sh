#!/usr/bin/env bash
set -euo pipefail

dirs=(
  app/Http/Controllers
  app/Http/Requests
  app/Services
  tests
)

fail=0
for d in "${dirs[@]}"; do
  if [[ -d "$d" ]]; then
    while IFS= read -r -d '' f; do
      php -l "$f" >/dev/null || { echo "LINT FAIL: $f"; fail=1; exit 1; }
    done < <(find "$d" -type f -name '*.php' -print0)
  fi
done

echo "PHP LINT OK"
exit 0
