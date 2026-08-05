#!/usr/bin/env bash
set -euo pipefail

# Evidence-freshness check (Task 9 / Correction 2, revised 2026-08-05).
# Two independent checks, both required for release eligibility:
#   1. Structural: recompute the CURRENT implementation-tree digest (a git
#      tree hash that excludes ONLY docs/owner-decisions/<work_id>/03-release.md)
#      and compare it against the Gate 3 packet's recorded digest(s). This
#      digest structurally excludes only the Gate 3 record file itself, so a
#      commit that touches ONLY that file (presenting/refreshing a packet)
#      never invalidates its own evidence — the self-reference bug an
#      earlier CI-check-based design had is architecturally impossible here.
#   2. Live: only when the packet's gate_status is already 'awaiting_owner'
#      (i.e. it is actively CLAIMING readiness), confirm every mandatory CI
#      check on the CURRENT PR head is green — this is a live, gh-dependent
#      fact the tree digest alone cannot express. Gated on gate_status, not
#      on owner_decision.value: this job runs CONCURRENTLY with the checks
#      it inspects, so most are legitimately still pending for any ordinary
#      preparing/blocked_technical packet at the moment this job executes —
#      gating on owner_decision.value == 'none' (true in both states) made
#      this check fail on every normal in-progress CI run, caught on this
#      script's own real CI run on PR #239.
#
# Usage: PR_NUMBER=<n> WORK_ID=<id> bash scripts/ci/check-evidence-freshness.sh
#
# PR_HEAD_SHA (optional): the real PR branch head SHA (GitHub Actions:
# github.event.pull_request.head.sha). On a `pull_request` trigger with
# actions/checkout@v5, `git rev-parse HEAD` resolves to the ephemeral
# GitHub-created merge commit, not the real PR branch head — so digest and
# check-state lookups against the wrong commit would be meaningless. When
# PR_HEAD_SHA is set, it is preferred; otherwise this falls back to
# `git rev-parse HEAD` (correct for `push` triggers and manual/local runs,
# where there is no merge commit).

: "${PR_NUMBER:?PR_NUMBER env var required}"
: "${WORK_ID:?WORK_ID env var required}"

# __DIR__ inside a `php -r` snippet resolves to the CURRENT WORKING DIRECTORY,
# not this script's directory (there is no real __FILE__ for inline code) —
# an earlier version of this script used `__DIR__ . "/../../vendor/autoload.php"`
# assuming __DIR__ meant "scripts/ci/", which only worked if CWD happened to be
# scripts/ci/ too. It doesn't when GitHub Actions invokes this as
# `bash scripts/ci/check-evidence-freshness.sh` from the repo root, which
# resolved to a nonexistent path two directories above the repo root and
# crashed with "Failed opening required .../vendor/autoload.php" (caught by
# this script's own real CI run on PR #239). Fixed by resolving this script's
# real directory in bash and passing it into every `php -r` snippet explicitly.
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

gate3_file="$(ls docs/owner-decisions/"$WORK_ID"/03-release*.md 2>/dev/null | sort | tail -n1 || true)"
if [ -z "$gate3_file" ]; then
  echo "No Gate 3 packet for $WORK_ID — nothing to check for staleness."
  exit 0
fi

owner_decision_value="$(php -r '
require $argv[2] . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["owner_decision"]["value"] ?? "none";
' "$gate3_file" "$SCRIPT_DIR")"

gate_status="$(php -r '
require $argv[2] . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["gate_status"] ?? "";
' "$gate3_file" "$SCRIPT_DIR")"

current_head_sha="${PR_HEAD_SHA:-$(git rev-parse HEAD)}"

# --- Check 1: structural (implementation-tree digest) ---
current_digest="$(php -r '
require $argv[3] . "/../../vendor/autoload.php";
require $argv[3] . "/../ssot/owner_governance_lint.php";
echo owner_governance_compute_implementation_tree_digest($argv[1], $argv[2], $argv[4]);
' "$current_head_sha" "$WORK_ID" "$SCRIPT_DIR" "$REPO_ROOT")"

recorded_evidence_digest="$(php -r '
require $argv[2] . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["technical_evidence"]["implementation_tree_digest"] ?? "";
' "$gate3_file" "$SCRIPT_DIR")"

if [ -n "$recorded_evidence_digest" ] && [ "$recorded_evidence_digest" != "not_computed_while_blocked" ] && [ "$current_digest" != "$recorded_evidence_digest" ]; then
  echo "::error::$gate3_file's technical_evidence.implementation_tree_digest ($recorded_evidence_digest) no longer matches the current implementation tree ($current_digest)."
  echo "The implementation has changed since this packet's evidence was recorded. Required transition:"
  echo "  Evidence changed -> existing technical_evidence becomes stale -> release eligibility becomes false"
  echo "  -> Gate 3 packet must return to preparing or blocked_technical -> evidence must be regenerated"
  echo "  -> only then may Gate 3 return to awaiting_owner."
  echo "This script reports the required transition. It does NOT rewrite $gate3_file itself."
  exit 1
fi

if [ "$owner_decision_value" != "none" ]; then
  recorded_binding_digest="$(php -r '
require $argv[2] . "/../../vendor/autoload.php";
$fm = Symfony\Component\Yaml\Yaml::parse(preg_replace("/^---\n(.*?)\n---\n.*$/s", "$1", file_get_contents($argv[1])));
echo $fm["owner_decision_binding"]["implementation_tree_digest"] ?? "";
' "$gate3_file" "$SCRIPT_DIR")"

  if [ "$current_digest" != "$recorded_binding_digest" ]; then
    echo "::error::$gate3_file's owner_decision_binding.implementation_tree_digest ($recorded_binding_digest) no longer matches the current implementation tree ($current_digest)."
    echo "This decision is STALE. Required transition (per the design's §3.6 automatic-revert rule, enforced here without notification infrastructure):"
    echo "  Evidence changed -> existing owner decision becomes stale -> release eligibility becomes false"
    echo "  -> Gate 3 packet must return to preparing or blocked_technical -> evidence must be regenerated"
    echo "  -> only then may Gate 3 return to awaiting_owner."
    echo "This script reports the required transition. It does NOT rewrite $gate3_file itself — the human decision record is never silently edited by CI."
    exit 1
  fi
fi

# --- Check 2: live (all mandatory CI checks on the current PR head are green) ---
# Gated on gate_status == 'awaiting_owner' specifically, NOT on
# owner_decision_value == 'none' — "none" is true both before readiness
# (preparing/blocked_technical, where nothing is being claimed yet and
# CI is normally still running) and at readiness (awaiting_owner, before a
# decision is made). Gating on owner_decision_value alone made this check
# fail on every ordinary CI run for a preparing/blocked packet, since this
# job runs CONCURRENTLY with the very checks it inspects — most of which
# are still legitimately pending at the moment this job executes. Gating on
# gate_status instead means this check only fires when the packet is
# actively CLAIMING to be ready for a decision, which is the only moment a
# not-yet-green CI state is actually a governance violation worth failing
# the build over.
if [ "$gate_status" = "awaiting_owner" ]; then
  checks_json="$(gh pr checks "$PR_NUMBER" --json name,state,bucket)"
  non_pass_count="$(printf '%s' "$checks_json" | php -r '
require $argv[1] . "/../ssot/owner_governance_lint.php";
echo owner_governance_count_blocking_checks(json_decode(stream_get_contents(STDIN), true));
' "$SCRIPT_DIR")"

  if [ "$non_pass_count" != "0" ]; then
    echo "::error::gate_status is 'awaiting_owner' but $non_pass_count check(s) on PR #$PR_NUMBER's current head are not green (pending or failed) — technical_readiness cannot be 'ready' yet."
    echo "gate_status must not reach 'awaiting_owner' until every mandatory check on the current head is green."
    exit 1
  fi
fi

echo "✅ $gate3_file's implementation-tree digest matches the current implementation tree ($current_digest) — evidence is fresh, decision is not stale."
exit 0
