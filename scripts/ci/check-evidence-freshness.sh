#!/usr/bin/env bash
set -euo pipefail

# Evidence-freshness check (Task 9 / Correction 2). Recomputes the CURRENT
# evidence digest for a work_id's Gate 3 packet and compares it against
# owner_decision_binding.evidence_digest. A mismatch means the recorded
# decision is stale — evidence changed after the decision was made — and
# this is detected here at CI-run time, on every run, with no notification
# infrastructure involved.
#
# Usage: PR_NUMBER=<n> WORK_ID=<id> bash scripts/ci/check-evidence-freshness.sh

: "${PR_NUMBER:?PR_NUMBER env var required}"
: "${WORK_ID:?WORK_ID env var required}"

gate3_file="$(ls docs/owner-decisions/"$WORK_ID"/03-release*.md 2>/dev/null | sort | tail -n1 || true)"
if [ -z "$gate3_file" ]; then
  echo "No Gate 3 packet for $WORK_ID — nothing to check for staleness."
  exit 0
fi

owner_decision_value="$(php -r '
require __DIR__ . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["owner_decision"]["value"] ?? "none";
' "$gate3_file")"

if [ "$owner_decision_value" = "none" ]; then
  echo "$gate3_file has owner_decision.value: none — nothing recorded yet, nothing to go stale."
  exit 0
fi

current_head_sha="$(git rev-parse HEAD)"

checks_json="$(gh pr checks "$PR_NUMBER" --json name,state)"
current_digest="$(php -r '
require __DIR__ . "/../../vendor/autoload.php";
require __DIR__ . "/../ssot/owner_governance_lint.php";
$checks = array_map(fn ($c) => ["name" => $c["name"], "conclusion" => $c["state"]], json_decode($argv[2], true));
echo owner_governance_compute_evidence_digest($argv[1], $checks);
' "$current_head_sha" "$checks_json")"

recorded_digest="$(php -r '
require __DIR__ . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["owner_decision_binding"]["evidence_digest"] ?? "";
' "$gate3_file")"

if [ "$current_digest" != "$recorded_digest" ]; then
  echo "::error::$gate3_file's owner_decision_binding.evidence_digest ($recorded_digest) no longer matches the current branch state ($current_digest)."
  echo "This decision is STALE. Required transition (per the design's §3.6 automatic-revert rule, enforced here without notification infrastructure):"
  echo "  Evidence changed -> existing owner decision becomes stale -> release eligibility becomes false"
  echo "  -> Gate 3 packet must return to preparing or blocked_technical -> evidence must be regenerated"
  echo "  -> only then may Gate 3 return to awaiting_owner."
  echo "This script reports the required transition. It does NOT rewrite $gate3_file itself — the human decision record is never silently edited by CI."
  exit 1
fi

echo "✅ $gate3_file's evidence binding matches current branch state — decision is not stale."
exit 0
