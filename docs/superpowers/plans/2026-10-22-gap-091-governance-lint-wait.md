---
work_id: GAP-091
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-091/02-design.md
---

# GAP-091 — Owner Governance Lint false red on Gate 3 packets: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-091/02-design.md`).

1. **Helper** — `owner_governance_count_failed_checks()` in `scripts/ssot/owner_governance_lint.php` counts `fail` and
   `cancel` buckets.
2. **Live check** — `scripts/ci/check-evidence-freshness.sh` polls up to 240 × 15 s, fails at once on any failed or
   cancelled check, passes when nothing is blocking, and fails after the last poll. Poll count and interval can be
   overridden by `OWNER_GOV_LIVE_POLL_ATTEMPTS` and `OWNER_GOV_LIVE_POLL_INTERVAL`.
3. **Workflow** — `owner-governance-lint.yml` job `timeout-minutes: 75`.
4. **Tests** — unit tests for the helper; `EvidenceFreshnessLiveCheckTest` runs the script with a fake `gh`;
   `EnforcementBoundaryTest` asserts the new helper is used.
5. **Verify** — governance unit tests, actionlint, governance and docs lints, exact-head CI. Live green proof: the
   Gate 3 packet commit's Owner Governance Lint passes on its first attempt.
