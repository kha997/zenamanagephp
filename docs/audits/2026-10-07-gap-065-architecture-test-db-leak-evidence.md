# GAP-065 — Architecture test commits DB rows that later RefreshDatabase tests inherit: Gate-1 evidence

**Date:** 2026-10-07 (+07:00)

**Canonical base:** `fbdc7b1c0fb1b6594e5216495121429659b60c35` (origin/main)

**Branch:** `docs/GAP-065-architecture-test-db-leak`

**Scope:** Read-only investigation and Gate-1 documentation. No test, app,
workflow or deployment change is committed. One local, uncommitted
experiment (Finding 4) was made and reverted (`git status` clean afterwards).

## Candidate-ID audit

`docs/owner-decisions/` tops out at GAP-063 on main; GAP-064 is taken by
Draft PR #337 (`docs/GAP-064-treasury-s2-ledger`). `git grep GAP-065` over every
`origin/*` branch: no match.

## Symptom (reproduced)

Command (worktree at the canonical base, SQLite in-memory testing DB from
`phpunit.xml`):

```
APP_KEY=base64:QUJDREVGR0hJSktMTU5PUFFSU1RVVldYWVo3ODkwMTI= php -d display_startup_errors=0 -d error_reporting=0 ./vendor/bin/phpunit tests/Feature/VendorApiTest.php tests/Architecture tests/Feature/Deployment/ProductionBootstrapCommandTest.php
```

Result: `Tests: 35, Errors: 1, Failures: 2`:

- `test_bootstraps_real_tenant_and_admin_on_empty_database` — fails at
  `ProductionBootstrapCommandTest.php:17` (`assertSame(0, Tenant::count())`):
  **1 tenant already exists before the test does anything.**
- `test_never_creates_fixed_or_default_password` — `Attempt to read property
  "password" on null` (`:47`): `production:bootstrap` exited 1 ("database
  already contains tenant data") so no admin was created.
- `test_second_bootstrap_fails_closed_idempotent` — got exit 1, expected 2
  (`:90`): same pre-existing tenant, no bootstrap marker.

Control runs: `VendorApiTest + ProductionBootstrapCommandTest` → OK (9 tests);
each file alone → OK.

## Finding 1 — the leaking test (bisected)

`VendorApiTest` + one `tests/Architecture/*` file + `ProductionBootstrapCommandTest`:

| Architecture file | Result |
|---|---|
| `DebugRouteBoundaryInvariantTest.php` | **1 error, 2 failures** |
| `DocumentMutationOwnershipTest.php` | OK |
| `NoCommandLineDatabasePasswordTest.php` | OK |
| `NoCommandLineSmtpPasswordTest.php` | OK |
| `RouteNameCollisionInvariantTest.php` | OK |
| `WorkflowReferencesExistTest.php` | OK |

Within `DebugRouteBoundaryInvariantTest`, filtering to one method at a time
(same three files): only **`test_quick_login_workflow_still_works`** reproduces
the failure; `test_quick_login_workflow_reports_missing_user_by_name`,
`test_dashboard_data_preserves_existing_response_shape` and
`test_environment_matrix_for_surviving_class_a_routes` do not.

That method (`tests/Architecture/DebugRouteBoundaryInvariantTest.php`, the
GAP-011 quick-login regression) runs
`User::factory()->create(['email' => 'gap011-regression@example.test'])`.
`UserFactory` defaults `tenant_id => Tenant::factory()`, so it writes **one
tenant and one user**. The class extends `Tests\TestCase` but uses neither
`RefreshDatabase` nor `DatabaseTransactions`, so nothing wraps or rolls back
those writes — they are committed to the shared in-memory SQLite connection.

## Finding 2 — why it is order-dependent

`Tests\TestCase::ensureTestingSchema()` (`tests/TestCase.php`) runs in every
test's `setUp()`:

- If the `tenants` table does **not** exist, it sets
  `RefreshDatabaseState::$migrated = false` and runs `migrate:fresh`.
- If it **does** exist, it returns immediately.

Case A — `tests/Architecture tests/Feature/Deployment` (no prior
`RefreshDatabase` test): the Architecture test's `ensureTestingSchema()`
migrates and resets `$migrated = false`; the leak happens; the first
`RefreshDatabase` test then sees `$migrated === false`, runs its own
`migrate:fresh`, and the leaked rows are wiped. Passes by accident.

Case B — `VendorApiTest` first: its `RefreshDatabase` migrates and sets
`$migrated = true`. The Architecture test finds `tenants` present, skips
migration, and commits its rows. `ProductionBootstrapCommandTest`'s
`RefreshDatabase` sees `$migrated === true`, only opens a transaction, and
inherits 1 tenant + 1 user. Fails.

So the defect is in the Architecture test; the bootstrap test is the victim.
It never asserted anything wrong.

## Finding 3 — exposure in CI today

`phpunit.xml`'s `Feature` suite lists `tests/Feature` **before**
`tests/Architecture`, and there is no `executionOrder`/random ordering.
`ci-cd.yml:86` (`php artisan test`) and `automated-testing.yml:811`
(`--testsuite=Feature`) therefore run `ProductionBootstrapCommandTest`
before the leak, so it does not fail CI today. **This is not verified against
a CI log.** It is derived from the configuration.

The leak is still live in CI's full run: the committed tenant + user persist
for every test after `DebugRouteBoundaryInvariantTest`, including the whole
`Integration` suite that `php artisan test` runs next. Those tests currently
pass with the extra rows, but any later test asserting empty or exact counts
would fail for the same reason. Any targeted, re-ordered or
`--order-by=random` run that places a `RefreshDatabase` test before and a
count-sensitive test after the Architecture directory fails as shown above.

## Finding 4 — hypothesis confirmed by a reverted experiment

Locally adding `use RefreshDatabase;` to `DebugRouteBoundaryInvariantTest`
(3 lines, never committed) and re-running the reproduction command:
`Tests: 35, Assertions: 2055` OK. The only output issue is the 2 PHPUnit
deprecations that are also present in the failing baseline run. The change was
reverted with `git checkout --`; the working tree was clean afterwards.

This shows test isolation is sufficient. It does not choose the design. That
is Gate 2.

## Finding 5 — same pattern elsewhere (heuristic, NOT verified)

A text scan for test files that call `factory()->…->create(` or `::create([`
and mention none of `RefreshDatabase`/`DatabaseTransactions`/
`DatabaseMigrations` lists 12 files. `DocumentMutationOwnershipTest` is a false
positive because it only scans source text. The bisect cleared the other
Architecture files. The remaining 10 were **not** run or analysed and may
inherit isolation in other ways:

`tests/Browser/SubmittalResubmitDirtyStateTest.php` (Dusk),
`tests/Feature/Api/{Component,Notification,Project,Task}ApiTest.php`,
`tests/Feature/Auth/AuthenticationTest.php`,
`tests/Feature/ProjectManagementTest.php`,
`tests/Feature/Services/DocumentWorkflowConcurrencyTest.php`,
`tests/Unit/Events/ProjectEventTest.php`,
`tests/Unit/Services/ProjectServiceTest.php`.

These are listed only so the Owner can decide scope. They are not claimed as
defects.

## Design options for Gate 2

1. **Isolate the class.** Add `RefreshDatabase` to
   `DebugRouteBoundaryInvariantTest`. This is the smallest change and was proved
   by Finding 4. Its subprocess `route:list`/`route:cache` tests do not use the
   test DB, so they are unaffected.
2. **Split the class.** Move the three HTTP regression tests (quick-login ×2,
   dashboard-data) into a `tests/Feature` class that uses `RefreshDatabase`.
   `tests/Architecture` would then stay DB-free and static/structural.
3. **Option 1 or 2, plus a guard.** Add a lightweight check that fails when a
   non-`RefreshDatabase`/`DatabaseTransactions` test class leaves committed
   rows behind. It would catch the Finding 5 class of issues automatically.
   This is larger and needs its own design.
4. **Fix the 10 Finding 5 candidates as well.** This widens scope and should
   use a separate Work ID.

Recommendation: Option 1 or 2 under GAP-065. Track Options 3 and 4 as
follow-ups.
