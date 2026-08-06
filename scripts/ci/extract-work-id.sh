#!/usr/bin/env bash
# Exact-token Work-ID extractor shared by scripts/ci/check-gate3-before-ready.sh
# and .github/workflows/owner-governance-lint.yml, so both CI paths cannot
# silently diverge on what counts as a valid Work ID.
#
# Reads candidate text from stdin, writes the first token that matches the
# canonical Work-ID pattern IN FULL to stdout (nothing if none match).
#
# This validates each whole candidate token against the canonical pattern —
# it never substring-matches — so an invalid token such as "GAP-010bb" is
# never silently reduced to the different, valid-looking "GAP-010b", and
# "GAP-0010" is never reduced to "GAP-001". Portable POSIX ERE (grep -E),
# deliberately avoiding grep -P/-oP since it is not available on every grep
# implementation CI or contributors may run.

set -euo pipefail

candidates="$(grep -oE '[A-Za-z0-9_/-]+' || true)"

while IFS= read -r token; do
  [ -z "$token" ] && continue
  if printf '%s' "$token" | grep -qE '^(GAP-[0-9]{3}[a-z]?|OWN-[0-9]{4}-[0-9]{3})$'; then
    printf '%s\n' "$token"
    exit 0
  fi
done <<< "$candidates"

exit 0
