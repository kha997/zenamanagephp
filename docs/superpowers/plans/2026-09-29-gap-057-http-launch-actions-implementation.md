---
work_id: GAP-057
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-057/02-design.md
---

# GAP-057 — Web-triggered migrations: implementation plan

Design: `docs/owner-decisions/GAP-057/02-design.md` (Option 1, approved 2026-09-29).

## Task 1 — RED tests
- `tests/Feature/GAP057/LaunchEndpointsRunNoArtisanTest.php`: super-admin,
  mocked Artisan; `GET launch-report` → 200, zero Artisan calls,
  `pre_launch_readiness` present, `pre_launch_actions` absent;
  `POST pre-launch-actions` → 409, zero calls, readiness in
  `error.details.data.readiness` (the `error.envelope` middleware keeps only
  `data` from an error body).
- `tests/Unit/GAP057/PreLaunchReadinessIsReadOnlyTest.php`: readiness keys,
  zero calls, `pending_migrations` 0 on a migrated test DB,
  `executePreLaunchActions` removed from the service.

## Task 2 — GREEN
- Service: replace `executePreLaunchActions()` with read-only
  `getPreLaunchReadiness()` (migrator repository vs migration files,
  `configurationIsCached()`, `routesAreCached()`); drop the unused Artisan import.
- Controller: `executePreLaunchActions()` → 409 + readiness under `data`;
  `generateLaunchReport()` embeds `pre_launch_readiness`.

## Task 3 — keep GAP-055 invariant
Rewrite `tests/Unit/GAP055/PreLaunchActionsNoCacheFlushTest.php` against
`getPreLaunchReadiness()` (no `cache:clear`, no command at all).

## Task 4 — verify
GAP-055/057 tests, route-middleware contracts, launch-checklist backup tests,
`tests/Unit/OwnerGovernance`; governance lint; push; full CI; Gate 3.
