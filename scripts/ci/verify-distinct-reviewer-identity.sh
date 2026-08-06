#!/usr/bin/env bash
set -euo pipefail

# Verifies the Task 8b activation precondition: at least one repository
# collaborator, distinct from the current gh identity, exists with review
# access, and is named in .github/CODEOWNERS for governance paths.
# Exit 0 = precondition satisfied. Exit 1 = not satisfied (prints why).
#
# Usage: bash scripts/ci/verify-distinct-reviewer-identity.sh

current_identity="$(gh api user --jq '.login')"
echo "Current gh identity: $current_identity"

collaborators_json="$(gh api repos/kha997/zenamanagephp/collaborators)"
distinct_reviewers="$(printf '%s' "$collaborators_json" | \
  python3 -c "
import json, sys
data = json.load(sys.stdin)
current = '$current_identity'
distinct = [c['login'] for c in data if c['login'] != current and c.get('permissions', {}).get('pull')]
print('\n'.join(distinct))
")"

if [ -z "$distinct_reviewers" ]; then
  echo "❌ No repository collaborator distinct from '$current_identity' was found."
  echo "Task 8b activation precondition NOT satisfied — do not activate required_pull_request_reviews."
  exit 1
fi

echo "Distinct collaborator(s) found: $distinct_reviewers"

codeowners_content="$(cat .github/CODEOWNERS 2>/dev/null || true)"
covered=0
for reviewer in $distinct_reviewers; do
  if printf '%s' "$codeowners_content" | grep -q "@$reviewer"; then
    echo "✅ @$reviewer is a distinct collaborator AND is listed in .github/CODEOWNERS for a governance path."
    covered=1
  fi
done

if [ "$covered" -eq 0 ]; then
  echo "❌ No distinct collaborator is currently listed in .github/CODEOWNERS."
  echo "Task 8b activation precondition NOT satisfied — add the distinct reviewer to CODEOWNERS first."
  exit 1
fi

echo "✅ Task 8b activation precondition (distinct, CODEOWNERS-covered reviewer identity) is satisfied."
exit 0
