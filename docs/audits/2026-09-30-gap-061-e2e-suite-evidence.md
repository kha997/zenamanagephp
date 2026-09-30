# GAP-061 — The tests/E2E suite has never worked: Gate-1 evidence

**Date:** 2026-09-30 (+07:00)

**Canonical base:** `aac94caf890d7229baa3f514860786088e252ee8`

**Branch:** `docs/GAP-061-e2e-suite-never-worked`

**Scope:** Read-only investigation and Gate-1 documentation. No test, code,
workflow or deployment change. Local runs used SQLite in a disposable worktree
state that was reverted (GAP-060 implementation attempt, see
`docs/owner-decisions/GAP-060/02-design-v2.md`).

## Subject

`tests/E2E/CriticalUserFlowsE2ETest.php` (8 tests, created 2025-09-26 in
`9a0b43a2`) and `tests/E2E/DashboardE2ETest.php` (7 tests, created 2025-09-18 in
`70b20699`). `tests/E2E/TransactionIsolationColdStartTest.php` (GAP-040 proof)
is **not** part of this gap — it passes nightly (GAP-060).

`tests/E2E` is in no `phpunit.xml` testsuite; until GAP-060 the only runner was
the nightly workflow, which used `--stop-on-failure`, so every run stopped at the
first test and the other 14 were never executed.

## Local run of the whole suite (canonical code, SQLite)

`17 tests: 13 errors, 2 failures, 2 skipped` (the 2 skipped are the MySQL-only
GAP-040 proof).

### CriticalUserFlowsE2ETest (8/8 broken)

| Test | Result | Cause class |
|---|---|---|
| user authentication flow | expected redirect `/app/dashboard`, got `/app/today`; after correcting it, a later `assertSee` on the page fails | stale expectations (product moved to the Today workspace) |
| project management flow | `apiAs()` undefined → after importing `Tests\Traits\AuthenticationTrait`: 401 | test never imported the trait; fixture user has only scalar `role` (no RBAC assignment) |
| task management flow | same | same |
| dashboard flow | same | same |
| error handling flow | missing `error` key in response | stale response-envelope expectation |
| multi-tenant isolation flow | 403 where 200 expected | scalar-only fixture vs canonical RBAC |
| API rate limiting flow | 401 where 429 expected | stale login contract/throttle expectation |
| accessibility flow | 401 | same as project flow |

`setUp()` creates the user with `User::factory()->create(['role' =>
'project_manager'])` — the same stale scalar-only fixture pattern GAP-053 fixed
in `DashboardPerformanceTest` with `createTenantUserWithRbac()`.

### DashboardE2ETest (7/7 broken)

All 7 fail in fixtures before any request: `SQLSTATE[23000]: NOT NULL
constraint failed: rfis.title` — the RFI fixture omits a column that is now
required.

## Routes still exist

Of 51 request calls in the two files, every route exists in the runtime route
table (1171 routes) except the intentional negative probe
`/api/v1/nonexistent-endpoint`. The suite is broken by fixture/auth/expectation
drift, not by removed APIs. No application bug has been identified so far.

## Overlap with suites that already run in CI

Files under `tests/Feature`, `tests/Integration`, `tests/Browser` that already
exercise the same surfaces: `/login` 33, `/api/v1/projects` 7,
`/api/v1/tasks` 8, `/api/v1/auth/login` 6, `/api/v1/dashboard` 7, dashboard
widgets 8, role-based dashboard 6, customization 4, alerts 4, `/app/today` 4;
tenant-isolation tests 53; throttle/429 tests 14.
`/api/v1/project-manager/dashboard/{stats,timeline}` is covered only by
`tests/Performance/PerformanceMonitoringTest.php` (stats) and nothing else
(timeline).

So the two files add little unique single-endpoint coverage; what they were
meant to add is **multi-step user journeys**, and those journeys reflect the
2025 product (dashboard-first, generic project/task CRUD), not the current
Operator/Today product.

## The decision this needs (partly business)

What end-to-end journeys ZenaManage should protect is a product question. The
technical options are:

- **Retire** the two broken files (their single-endpoint coverage already exists
  in CI); optionally write new journey tests later.
- **Repair** them as they are (update fixtures to canonical RBAC, fix
  expectations) — keeps 2025-era journeys that may not match what matters now.
- **Replace** them with a small set of journeys the Owner names (e.g. log in →
  Today → approve a document; lead → opportunity → project; RFI open → answer →
  close) — a new feature needing the Owner's list.

## Out of scope for this Gate 1

Any test change; choosing the journeys; deployment.
