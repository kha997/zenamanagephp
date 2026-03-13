# Project Create Browser Suite Reconciliation

## Decision
- `tests/Browser/Projects/ProjectCreateCanaryTest.php` is the canonical CI canary for `/app/projects/create`.
- `tests/Browser/Projects/ProjectCreateTest.php` remains as a broader smoke/audit suite and is not the primary canary path.

## Why
- `ProjectCreateCanaryTest` is route-anchored (`visitRoute('app.projects.create')`), narrower, and aligned with the locked browser strategy: replace mixed legacy coverage with focused, evidence-backed canaries.
- `ProjectCreateTest` currently mixes broader checks (render, validation, cancel, submit), so its role is smoke/audit expansion rather than minimal canary.

## Scope Rules
- Do not patch app/domain logic for browser alignment.
- Do not delete or merge `ProjectCreateTest.php` in this round.
- Do not open the next canary surface until this role split is committed and CI is wired to the canary.

## CI Wiring
- Update `Button Test Suite` browser target from `ProjectCreateTest.php` to `ProjectCreateCanaryTest.php`.

## Exit Criteria For Future Consolidation
- CI remains stable with `ProjectCreateCanaryTest.php` as the canonical browser gate.
- Broader create-flow coverage is intentionally mapped and either retained as smoke/audit or consolidated in a later, explicit change proposal.
