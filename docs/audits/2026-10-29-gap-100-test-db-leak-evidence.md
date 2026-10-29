# GAP-100 — Test classes that commit DB rows later tests inherit (GAP-065 follow-up): Gate-1 evidence

**Date:** 2026-10-29 (+07:00)

**Canonical base:** `01595a7f2df94e2a9eb26d4c97824a306bbb30f8` (origin/main)

**Branch:** `docs/GAP-100-test-db-leak`

**Scope:** Read-only investigation and Gate-1 documentation. Two local, uncommitted experiments (Findings 2 and 4) were
made and reverted (`git status` clean afterwards). No test, app, workflow or deployment change is committed.

## Candidate-ID audit

`docs/owner-decisions/` tops out at GAP-099 on main. `git grep GAP-100` over every `origin/*` ref under `docs/`: no
match.

## Background

GAP-065 (PR #338) fixed `DebugRouteBoundaryInvariantTest`, which wrote a tenant and a user without `RefreshDatabase`.
Its evidence Finding 5 listed 10 more files from a text scan, explicitly unverified. The roadmap carries them, plus a
suite-wide leak guard, as open item 2.

## Finding 1 — the 10 GAP-065 candidates, checked

| File | Isolation | Verdict |
|---|---|---|
| `tests/Feature/Api/{Component,Notification,Project,Task}ApiTest.php` | `use DatabaseTrait;` | False positive: `Tests\Traits\DatabaseTrait` uses `RefreshDatabase` |
| `tests/Feature/Auth/AuthenticationTest.php` | `use DatabaseTrait;` | False positive (same) |
| `tests/Unit/Events/ProjectEventTest.php` | `use DatabaseTrait;` | False positive (same) |
| `tests/Unit/Services/ProjectServiceTest.php` | `use DatabaseTrait;` | False positive (same) |
| `tests/Feature/Services/DocumentWorkflowConcurrencyTest.php` | none | Writes only on real MySQL in separate processes (`requireRealMysql()` skips on SQLite); runs in its own CI job. Not a shared-process leak |
| `tests/Browser/SubmittalResubmitDirtyStateTest.php` | none | Dusk test (separate server DB). CI's Dusk job runs only `tests/Browser/Projects/` and `tests/Browser/Crm/`, so it never runs in CI. Not verifiable here (no Dusk server) |
| `tests/Feature/ProjectManagementTest.php` | none | **Real leak** (Findings 2–3) |

## Finding 2 — measured, not guessed: a temporary row-count probe over the whole suite

A local, uncommitted change to `tests/TestCase.php` snapshotted the row count of every SQLite table at the end of
`setUp()` and compared it in `tearDown()`, for every test class using none of `RefreshDatabase`,
`DatabaseTransactions`, `DatabaseMigrations` or `DatabaseTruncation`. Any difference was written to a scratch file.

Runs (SQLite in-memory, `phpunit.xml`):

- `--testsuite Unit,Feature,Integration` (2,830 tests; the `Feature` suite excludes `tests/Feature/Api`).
- `tests/Feature/Api` (823 tests): **no leak**.

Leaks recorded (complete list):

| Test | Rows left behind |
|---|---|
| `ProjectManagementTest::test_user_can_create_project_in_their_tenant` | tenants +1, users +1, projects +1, permissions +1, roles +1, role_permissions +1, user_roles +1 |
| `DeployMigrateCommandTest::test_command_accepts_expand_only_migrations` | migrations +1 |
| `DeployMigrateCommandTest::test_command_actually_executes_the_classified_fixture_migration_not_the_real_directory` | migrations +1 (and a `gap049_deploy_migrate_proof_*` table, which the row probe does not count) |

`DeployMigrateCommandTest` was not in the GAP-065 list: it writes through an Artisan command, not a factory, so the
text scan missed it. Its own assertion message says "RefreshDatabase has already fully applied" the real migrations,
but the class does not use `RefreshDatabase`.

The probe itself caused one artefact error (`ProductionReadinessEndpointTest::test_returns_503_when_database_probe_fails`
mocks the `DB` facade, so the probe's `DB::select` hit the mock). A real guard would need to handle that. The probe was
reverted with `git checkout -- tests/TestCase.php`.

## Finding 3 — the ProjectManagementTest leak breaks other tests (reproduced on main)

```
php ./vendor/bin/phpunit tests/Feature/VendorApiTest.php tests/Feature/ProjectManagementTest.php tests/Feature/Deployment/ProductionBootstrapCommandTest.php
```

Result: `Tests: 10, Errors: 1, Failures: 2` — the same three `ProductionBootstrapCommandTest` victims as GAP-065
(`test_bootstraps_real_tenant_and_admin_on_empty_database`, `test_never_creates_fixed_or_default_password`,
`test_second_bootstrap_fails_closed_idempotent`). Control without `ProjectManagementTest`: OK (9 tests). Same mechanism
as GAP-065 Finding 2 (`RefreshDatabaseState::$migrated` already true, so the later test only opens a transaction on top
of the committed rows).

With `DeployMigrateCommandTest` in the middle instead: OK (18 tests). Its leak (2 `migrations` rows and one empty table)
does not break any test found today; it would mislead a later plain `migrate` or `migrate:status` in the same process.

CI today: `phpunit.xml` runs `tests/Feature` in directory order, so `ProjectManagementTest` runs after
`ProductionBootstrapCommandTest` (`Deployment/` sorts before `ProjectManagementTest.php`). CI is green by order, as it
was for GAP-065. Derived from configuration, not from a CI log.

## Finding 4 — fix confirmed by a reverted experiment

Adding `use RefreshDatabase;` to both `ProjectManagementTest` and `DeployMigrateCommandTest` (3 lines each, never
committed) and running `VendorApiTest + ProjectManagementTest + DeployMigrateCommandTest + ProductionBootstrapCommandTest`:
`OK (19 tests, 61 assertions)`. Reverted with `git checkout -- tests`.

## Finding 5 — no suite-wide guard exists

`Tests\Support\RefreshDatabaseSelfHealingGuard` (GAP-050) detects an unexpected re-migration on the MySQL parity job
only. Nothing detects a test class that commits rows. The probe in Finding 2 shows such a guard is feasible and cheap
for the non-isolated classes only (it found all leaks in one run).

## Options for Gate 2 (not chosen here)

1. Isolate the 2 leaking classes only (`RefreshDatabase`).
2. Option 1 plus a permanent guard in `tests/TestCase.php` that fails a non-isolated test which leaves rows behind.
3. Option 1 plus the guard as an opt-in job or command (does not change ordinary runs).

Out of scope for every option: the Dusk test (`SubmittalResubmitDirtyStateTest`, not run in CI), the MySQL-only
concurrency test, application code, CI ordering.
