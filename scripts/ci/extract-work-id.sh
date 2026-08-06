#!/usr/bin/env bash
# Authoritative Work-ID resolver shared by scripts/ci/check-gate3-before-ready.sh
# and .github/workflows/owner-governance-lint.yml, so both CI paths cannot
# silently diverge on what a PR's Work ID is.
#
# Contract: the input (a PR body) must contain EXACTLY ONE declaration of
# the form "Work ID: <candidate>", and it must be the FIRST non-empty line.
# Any other mention of a Work-ID-shaped token elsewhere in the body (e.g. a
# "GAP-010b implementation authorized: NO" disclaimer line) is narrative,
# not authoritative, and must never be selected.
#
# Reads the complete body from stdin. On success, prints the resolved Work
# ID to stdout and exits 0. On ANY failure — missing declaration,
# declaration not on the first non-empty line, more than one declaration,
# empty candidate, non-canonical candidate, or a candidate followed by
# extra characters — prints a diagnostic to stderr, prints NOTHING to
# stdout, and exits nonzero. This extractor fails closed: it never
# silently succeeds with an empty result, and it never falls back to a
# token found later in the text.

set -uo pipefail

input="$(cat)"
# Normalize CRLF to LF so a Windows-authored PR body still parses correctly.
input="${input//$'\r'/}"

declaration_count="$(printf '%s\n' "$input" | grep -c '^Work ID: ' || true)"

if [ "$declaration_count" -eq 0 ]; then
  echo "extract-work-id: no authoritative 'Work ID: <candidate>' declaration found." >&2
  exit 1
fi

if [ "$declaration_count" -gt 1 ]; then
  echo "extract-work-id: multiple 'Work ID:' declarations found — exactly one authoritative declaration is required." >&2
  exit 1
fi

first_line="$(printf '%s\n' "$input" | sed -n '/./{p;q;}')"

case "$first_line" in
  "Work ID: "*) ;;
  *)
    echo "extract-work-id: the first non-empty line must be the authoritative declaration; got: '${first_line}'" >&2
    exit 1
    ;;
esac

candidate="${first_line#Work ID: }"

if [ -z "$candidate" ]; then
  echo "extract-work-id: empty Work ID candidate." >&2
  exit 1
fi

if ! printf '%s' "$candidate" | grep -qE '^(GAP-[0-9]{3}[a-z]?|OWN-[0-9]{4}-[0-9]{3})$'; then
  echo "extract-work-id: candidate '${candidate}' does not match the canonical Work-ID pattern." >&2
  exit 1
fi

printf '%s\n' "$candidate"
exit 0
