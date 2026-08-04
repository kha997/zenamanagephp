#!/usr/bin/env bash
set -euo pipefail

# Gate-3-before-ready check (Task 9). This is deliberately SEPARATE from
# owner_governance_lint.php: whether a PR is "marked Ready for review" is
# GitHub API state, not repository file state, so it cannot be a pure
# file-tree lint rule — see the approved design spec §7.5's own admission
# that this check is necessarily hybrid.
#
# Usage: PR_NUMBER=<n> bash scripts/ci/check-gate3-before-ready.sh

: "${PR_NUMBER:?PR_NUMBER env var required}"

is_draft="$(gh pr view "$PR_NUMBER" --json isDraft --jq '.isDraft')"
if [ "$is_draft" = "true" ]; then
  echo "PR #$PR_NUMBER is still a draft — gate-3-before-ready does not apply yet."
  exit 0
fi

body="$(gh pr view "$PR_NUMBER" --json body --jq '.body')"
work_id="$(printf '%s' "$body" | grep -oE '(GAP-[0-9]{3}|OWN-[0-9]{4}-[0-9]{3})' | head -n1 || true)"

if [ -z "$work_id" ]; then
  echo "No recognizable Work ID found in PR body's Owner Summary — nothing to check."
  exit 0
fi

if grep -qxF "$work_id" docs/owner-governance/legacy-work-ids.txt 2>/dev/null; then
  echo "Work ID $work_id is legacy-exempt from gate-3-before-ready."
  exit 0
fi

gate3_dir="docs/owner-decisions/$work_id"
if ! ls "$gate3_dir"/03-release*.md >/dev/null 2>&1; then
  echo "::error::PR #$PR_NUMBER is marked Ready for review for work_id $work_id, but no $gate3_dir/03-release*.md packet exists."
  exit 1
fi

echo "Gate 3 packet found for $work_id — gate-3-before-ready check passes structurally (owner_decision may still be 'none', which is correct pre-approval)."
exit 0
