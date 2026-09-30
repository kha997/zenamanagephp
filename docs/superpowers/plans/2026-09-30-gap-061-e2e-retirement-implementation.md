---
work_id: GAP-061
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-061/02-design.md
---

# GAP-061 — Retire the never-working E2E files: implementation plan

Design: `docs/owner-decisions/GAP-061/02-design.md` (Option 1, approved
2026-09-30; Owner product choice: retire now).

## Task 1 — delete
`git rm tests/E2E/CriticalUserFlowsE2ETest.php tests/E2E/DashboardE2ETest.php`.

## Task 2 — verify
- `./vendor/bin/phpunit tests/E2E` lists only `TransactionIsolationColdStartTest`.
- SSOT orphan-test-route lint (clean route map) passes.
- `tests/Architecture` + `tests/Unit/OwnerGovernance` green.
- `git grep` for the two class names in `app tests routes .github scripts`:
  only the GAP-060 workflow comment and the stale, unreferenced
  `scripts/ssot/orphan_routes.generated.txt`.
- Dispatch the nightly workflow on the branch; it stays green.
- Push; full PR CI; Gate 3.
