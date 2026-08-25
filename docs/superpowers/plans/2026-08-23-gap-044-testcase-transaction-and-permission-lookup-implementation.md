# GAP-044 — TestCase Transaction Isolation + Permission Lookup Identity: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the compound test-infrastructure defect confirmed at GAP-044 Gate 1/approved at Gate 2 (Option A) — (Surface 1) `tests/TestCase.php`'s `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` implicit-committing `RefreshDatabase`'s real-MySQL transaction, and (Surface 2) `TenantUserFactoryTrait::ensurePermissionAttached()`'s `Permission` lookup using the non-canonical `name` column instead of `code` — with regression proof structurally immune to the false-green mode GAP-044 Gate 1 discovered in GAP-040's own proof.

**Architecture:** Surface 1 reuses GAP-040's existing `zenaRbacBootstrapSchema()` isolated-connection mechanism (A2-style direct reuse, per Owner Gate-2 approval) for the three remaining helpers. Surface 2 swaps one lookup key. Regression coverage strengthens the existing shared `GAP040ColdStartTransactionIsolationAssertions` trait (permanently, per Owner direction) with the missing discriminator — capturing `RefreshDatabaseState::$migrated` and independent-PDO marker visibility *before* the verifier's own `parent::setUp()` runs, not after — and adds a new, SQLite-fast regression test for Surface 2's exact seeded shape (`code='project.read'`, `name=NULL`).

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit 11.5.56, MySQL 8.0 (CI service containers), SQLite (local/default test driver).

## Global Constraints

- Functional fix surface: exactly `tests/TestCase.php` and `tests/Traits/TenantUserFactoryTrait.php`. No other production/application code.
- Permanent regression-test/support files are explicitly authorized: `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php` (strengthen), the 5 existing consuming test classes (add capture-before-verifier-setup calls), and one new test file for Surface 2 (§Task 1).
- Do NOT modify: `database/seeders/RoleSeeder.php`, `PermissionSeeder.php`, any other seeder, any migration, any application/production code, any workflow file, any GAP-040/041/042/043/045 artifact (`docs/owner-decisions/GAP-0{40,41,42,43,45}/*`, `docs/audits/*gap-04{0,1,2,3,5}*`, `docs/superpowers/specs/*gap-04{0,1,2,3,5}*`, `docs/superpowers/plans/*gap-04{0,1,2,3,5}*`).
- No "while here" cleanup. No unrelated test refactor.
- **No local real MySQL is available in this environment** (broken PHP extensions, established constraint from GAP-040's own plan). Every MySQL-dependent step is verified by pushing to the implementation branch and inspecting the actual GitHub Actions run — the only real verification channel for MySQL-dependent behavior here, consistent with `scripts/ci/*-mysql` comments elsewhere in this repo.
- SQLite-driven test suite outcomes must be unchanged, before/after (Task 9).
- If A1-style generalization is attempted for Surface 1 and it materially widens `TestCase.php` beyond a mechanical rename/reuse, revert to the smaller A2-style implementation used in this plan (A2 is used throughout below; A1 is not attempted).
- If implementation evidence shows a production change is actually required, STOP and return to Owner — do not make it.

---

### Task 0: Create the implementation branch and Draft PR

**Files:** none (branch/PR setup only).

Per Owner Gate-2 authorization §G: do NOT reuse PR #285 (the Gate-2 design PR) as the implementation PR. Create a fresh branch from the Gate-2 approval-record head, so the approved Gate-1 + Gate-2 artifacts travel byte-identically with the implementation.

- [ ] **Step 1: Branch from the exact Gate-2 approval-record head**

```bash
git fetch origin docs/GAP-044-gate2-design
git rev-parse origin/docs/GAP-044-gate2-design   # must equal the Gate-2 approval-record commit SHA
git checkout -b feature/GAP-044-testcase-transaction-and-permission-lookup origin/docs/GAP-044-gate2-design
```

- [ ] **Step 2: Push and open a Draft PR**

```bash
git push -u origin feature/GAP-044-testcase-transaction-and-permission-lookup
```

Open a Draft PR targeting `main`. First non-empty PR body line must be exactly `Work ID: GAP-044`. Do not mark ready. This PR's `pull_request` trigger, plus the `feature/*`-matching push trigger, is what makes Task 4/5's CI verification possible without manual dispatch (except the E2E surface, Task 6, which remains schedule/`workflow_dispatch`-only).

- [ ] **Step 3: Confirm the carried-forward Gate-1/Gate-2 artifacts are present and byte-identical, and confirm fresh CI baseline is green before any code change**

```bash
git diff docs/GAP-044-gate1-investigation -- docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md docs/owner-decisions/GAP-044/01-request.md
git diff docs/GAP-044-gate2-design -- docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md docs/owner-decisions/GAP-044/02-design.md
```

Expected: both empty. Then confirm Owner Governance Lint and Routes Guardrails pass on this exact starting head (proving the branch cut cleanly, before Task 1 adds anything).

---

### Task 1: RED — Surface 2 permission-identity regression test (SQLite, fast)

**Files:**
- Create: `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`

**Interfaces:**
- Consumes: `Tests\Traits\TenantUserFactoryTrait::createTenantUser()` (existing, unmodified in this task), `App\Models\Permission`, `App\Models\Tenant`, `App\Models\User`.

This test seeds the exact real-world shape (`code='project.read'`, `name=NULL`, matching `RoleSeeder`'s actual output — confirmed in the Gate-2 engineering spec §1) directly via `Permission::create()`, then exercises `TenantUserFactoryTrait::createTenantUser()`'s real, unmodified code path, and asserts no duplicate row is created and the existing row's `id` is what gets attached. `permissions.code` has a real `unique()` constraint on every driver including SQLite (confirmed: `database/migrations/2025_09_14_140000_create_zena_rbac_fixed.php:17`), so this genuinely exercises the real bug without needing MySQL.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Permission;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-044 Surface 2 regression: TenantUserFactoryTrait::ensurePermissionAttached()
 * must look up Permission by its canonical, actually-unique `code` column,
 * not `name` — reusing an existing seeded row even when that row's `name`
 * is NULL (the real shape RoleSeeder produces; see
 * docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md
 * §1 for the confirmed RoleSeeder -> PermissionSeeder -> name=NULL
 * provenance, matching pre-existing AUD-28). Runs on SQLite (default) —
 * genuinely exercises `permissions.code`'s real unique constraint, which
 * exists on every driver, so no MySQL is needed for this specific proof.
 */
class PermissionCanonicalIdentityRegressionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_reuses_existing_permission_by_code_even_when_name_is_null(): void
    {
        // Replicate RoleSeeder's exact real-world output shape: a
        // permissions row identified by `code`, with `name` left NULL.
        $existing = Permission::create([
            'id' => (string) Str::ulid(),
            'code' => 'project.read',
            'name' => null,
            'module' => 'project',
            'action' => 'read',
            'description' => 'Read Project',
            'is_active' => true,
        ]);

        $this->assertNull($existing->fresh()->name, 'Test setup invariant broken: seeded permission must have a NULL name to replicate the real RoleSeeder shape.');

        $tenant = Tenant::factory()->create();

        $user = $this->createTenantUser(
            $tenant,
            ['name' => 'Regression User', 'email' => 'gap044-permission-identity@example.test'],
            ['project_manager'],
            ['project.read']
        );

        $this->assertSame(
            1,
            Permission::where('code', 'project.read')->count(),
            'A duplicate permission row was created for code=project.read — ensurePermissionAttached() is not reusing the existing row by canonical code identity.'
        );

        $attachedPermissionIds = $user->roles()
            ->first()
            ->permissions()
            ->pluck('permissions.id')
            ->all();

        $this->assertContains(
            $existing->id,
            $attachedPermissionIds,
            'The role was not attached to the pre-existing permission row (by its original id) — canonical code-based reuse did not occur.'
        );
    }
}
```

- [ ] **Step 2: Run locally to confirm RED, for the right reason**

Run: `./vendor/bin/phpunit tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`

Expected: **FAIL** with a `PDOException`/`QueryException` unique-constraint violation on `permissions.code` (or an equivalent SQLite `UNIQUE constraint failed: permissions.code` message) — the exact real bug, thrown from `ensurePermissionAttached()`'s current `Permission::firstOrCreate(['name' => $permissionName], ...)` lookup missing the `name=NULL` row and attempting a duplicate `code` insert. If it fails for any other reason (e.g. a fixture/setup error), fix that first — that is not the RED this task needs.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php
git commit -m "test(GAP-044): add Surface 2 permission canonical-identity regression test — expected RED before the fix"
```

---

### Task 2: GREEN — implement Surface 2 fix

**Files:**
- Modify: `tests/Traits/TenantUserFactoryTrait.php:55-71` (`ensurePermissionAttached()`)

**Interfaces:**
- Produces: `ensurePermissionAttached()`'s `Permission::firstOrCreate()` call now keyed by `code`.

- [ ] **Step 1: Change the lookup key**

Edit `tests/Traits/TenantUserFactoryTrait.php`, in `ensurePermissionAttached()`:

```php
    private function ensurePermissionAttached(Role $role, array $permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            $parts = explode('.', $permissionName);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionName],
                [
                    'name' => $permissionName,
                    'module' => $parts[0] ?? $permissionName,
                    'action' => $parts[1] ?? '*',
                    'description' => ucfirst(str_replace('.', ' ', $permissionName)),
                ]
            );

            $role->permissions()->syncWithoutDetaching($permission->id);
        }
    }
```

(Only the `firstOrCreate`'s first-argument array key changes from `['name' => $permissionName]` to `['code' => $permissionName]`; the second-argument creation-defaults array is unchanged in content, matching the Owner-approved candidate shape exactly.)

- [ ] **Step 2: Run the Task 1 test to confirm GREEN**

Run: `./vendor/bin/phpunit tests/Feature/Zena/PermissionCanonicalIdentityRegressionTest.php`
Expected: **PASS**.

- [ ] **Step 3: Run the full SQLite suite's Zena feature tests to check for regressions**

Run: `./vendor/bin/phpunit tests/Feature/Zena`
Expected: same pass/fail/skip counts as before this change (no new failures). `TenantUserFactoryTrait` is used by ~103 files repo-wide (per Gate 1 blast-radius grep) — this directory is the highest-density consumer and the fastest meaningful regression signal locally.

- [ ] **Step 4: Commit**

```bash
git add tests/Traits/TenantUserFactoryTrait.php
git commit -m "fix(GAP-044): Surface 2 — ensurePermissionAttached() looks up Permission by canonical code, not name"
```

---

### Task 3: Probe scaffolding for Surface 1 (instrumentation only, no functional change yet)

**Files:**
- Modify: `tests/TestCase.php` (add two small private probe helpers; no behavior change to any `ensure*Table()` method yet)

**Interfaces:**
- Produces: `private function gap044ProbeBeforeHelper(string $tableName): void`, `private function gap044ProbeAfterHelper(string $tableName): void` — populate `TestCase::$coldStartProbe['helpers'][$tableName]['pdo_in_transaction_before'|'pdo_in_transaction_after']`. Consumed by Task 4's strengthened trait assertions.

This task only adds observation points, mirroring GAP-040's own Task-1 pattern (instrumentation before fix, so the regression proof can be written and proven RED against current behavior before Task 5 fixes it).

- [ ] **Step 1: Add the two probe helper methods**

Edit `tests/TestCase.php`, immediately after the existing `zenaRbacBootstrapSchema()` method (after line 312, before `ensureSqliteDocumentsBackupTable()`):

```php
    /**
     * GAP-044 Surface 1 regression instrumentation. Records, per helper
     * table, whether the server-observed PDO transaction state survived
     * that helper's DDL — the exact evidence the GAP-044 Gate-1 audit
     * (docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md
     * §D-G) used to establish the implicit-commit defect. No-ops when
     * TestCase::$coldStartProbe is null (every ordinary test) or on
     * SQLite (which does not implicit-commit on DDL the way MySQL does).
     */
    private function gap044ProbeBeforeHelper(string $tableName): void
    {
        if (self::$coldStartProbe === null || config('database.default') === 'sqlite') {
            return;
        }

        self::$coldStartProbe['helpers'][$tableName]['pdo_in_transaction_before'] = DB::connection()->getPdo()->inTransaction();
    }

    private function gap044ProbeAfterHelper(string $tableName): void
    {
        if (self::$coldStartProbe === null || config('database.default') === 'sqlite') {
            return;
        }

        self::$coldStartProbe['helpers'][$tableName]['pdo_in_transaction_after'] = DB::connection()->getPdo()->inTransaction();
    }
```

- [ ] **Step 2: Wire the probes around the three unfixed helpers (observation only — DDL mechanism unchanged in this task)**

Edit `tests/TestCase.php`'s `ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`, `ensureProjectTasksTable()` to call the probes, without changing where the DDL runs yet:

```php
    private function ensureInteractionLogsTable(): void
    {
        if (Schema::hasTable('interaction_logs')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('interaction_logs');

        Schema::create('interaction_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('project_id');
            $table->string('linked_task_id')->nullable();
            $table->string('type');
            $table->text('description');
            $table->string('tag_path')->nullable();
            $table->string('visibility');
            $table->boolean('client_approved')->default(false);
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes();
        });

        $this->gap044ProbeAfterHelper('interaction_logs');
    }

    private function ensureProjectPhasesTable(): void
    {
        if (Schema::hasTable('project_phases')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('project_phases');

        Schema::create('project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_phase_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->gap044ProbeAfterHelper('project_phases');
    }

    private function ensureProjectTasksTable(): void
    {
        if (Schema::hasTable('project_tasks')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('project_tasks');

        Schema::create('project_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('phase_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days')->default(0);
            $table->float('progress_percent')->default(0.0);
            $table->string('status')->default('pending');
            $table->string('conditional_tag')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_task_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $this->gap044ProbeAfterHelper('project_tasks');
    }
```

- [ ] **Step 3: Local sanity check (SQLite, probes are no-ops there)**

Run: `./vendor/bin/phpunit tests/Feature/Zena/ZenaRbacTenantSmokeTest.php`
Expected: PASS, identical to before (SQLite path untouched by this task).

- [ ] **Step 4: Commit**

```bash
git add tests/TestCase.php
git commit -m "test(GAP-044): add Surface 1 per-helper transaction-state probe instrumentation"
```

---

### Task 4: RED — strengthen the shared cold-start proof with the false-green discriminator, wire into all 5 consuming surfaces

**Files:**
- Modify: `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`
- Modify: `tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php`
- Modify: `tests/Feature/Zena/ZenaInvariantsTransactionIsolationColdStartTest.php`
- Modify: `tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php`
- Modify: `tests/E2E/TransactionIsolationColdStartTest.php`
- Modify: `tests/Feature/Documents/TransactionIsolationColdStartTest.php`

**Interfaces:**
- Produces (trait): `captureDiscriminatingStateBeforeVerifierSetUp(): void`, `assertMarkerDisappearedViaRollbackNotMigrateFresh(): void`. Strengthens existing `proveColdStartAndWriteMarker(): string` to also assert the 3 new per-helper probes from Task 3.
- Consumes (5 test classes): the two new trait methods, called from each class's own `setUp()`/verifier test method.

This is the file explicitly named in the Owner's Gate-2 authorization: "The existing `GAP040ColdStartTransactionIsolationAssertions` proof is known to be insufficient against this exact false-green unless strengthened. If reusing it, strengthen it permanently." This task does that — not a GAP-040 governance-artifact edit (no `docs/owner-decisions/GAP-040/*` file is touched), a permanent strengthening of shared test infrastructure, explicitly authorized.

- [ ] **Step 1: Strengthen the trait**

Edit `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`, replacing its full contents:

```php
<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared cold-start transaction-isolation regression proof. Originally
 * built for GAP-040; permanently strengthened under GAP-044 (Gate 1
 * §H1: docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md)
 * after discovering the original proof was itself a false-green — the
 * writer/verifier marker's disappearance could be explained by the
 * *verifier's own* migrate:fresh (triggered by RefreshDatabaseState::$migrated
 * being reset by the very implicit-commit defect under test), not by a
 * genuine RefreshDatabase rollback. Consumed by one test class per
 * Gate-1-approved real-MySQL surface.
 *
 * Ordering/value-passing between writer and verifier is guaranteed by
 * PHPUnit's #[Depends] attribute on the consuming test classes, not by
 * method-name convention or discovery order.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    /**
     * GAP-044: state captured by captureDiscriminatingStateBeforeVerifierSetUp(),
     * read by assertMarkerDisappearedViaRollbackNotMigrateFresh(). Reset at
     * the start of every writer run so no state leaks between consuming
     * classes sharing this trait within the same PHPUnit process.
     */
    protected static ?bool $migratedBeforeVerifierSetUp = null;
    protected static ?bool $markerVisibleBeforeVerifierSetUp = null;
    protected static ?string $lastWrittenMarkerId = null;

    /**
     * Forces the next parent::setUp() to genuinely re-run migrate:fresh
     * before opening this test's RefreshDatabase transaction, so the
     * cold-start case is deterministically observed. Call this ONLY from
     * the writer test's setUp() — never from the verifier's — per the
     * class-level doc comment above. Safe to call unconditionally: it only
     * has an effect when the active connection is MySQL, and reads the
     * connection via getenv() (set by tests/bootstrap.php before any test
     * runs) rather than config(), since the app container does not exist
     * yet at the point this must run (before parent::setUp()).
     */
    protected function forceGenuineColdStartForNextSetUp(): void
    {
        self::$migratedBeforeVerifierSetUp = null;
        self::$markerVisibleBeforeVerifierSetUp = null;
        self::$lastWrittenMarkerId = null;

        if (getenv('DB_CONNECTION') === 'mysql') {
            RefreshDatabaseState::$migrated = false;
        }
    }

    /**
     * Writer half of the proof. Must run under a setUp() that called
     * forceGenuineColdStartForNextSetUp() first. Proves the cold-start
     * invariant with hard, non-skippable assertions on real MySQL — a
     * failure to observe genuine cold start is a real defect (the forcing
     * mechanism itself broken), not a legitimate alternate state, so it is
     * NOT swallowed as a skip. Returns the written row's id for the
     * verifier to consume via #[Depends].
     *
     * GAP-044: also asserts the same invariant across the three
     * transaction-breaking TestCase helpers fixed under GAP-044 Surface 1
     * (ensureInteractionLogsTable/ensureProjectPhasesTable/ensureProjectTasksTable),
     * using the probes added in tests/TestCase.php's gap044ProbeBeforeHelper()/
     * gap044ProbeAfterHelper().
     *
     * @group stress
     */
    protected function proveColdStartAndWriteMarker(): string
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('dependency: this proof only exercises the invariant against a real MySQL connection; this test class is also reachable from the default SQLite suite (no excluded @group), so a legitimate skip is correct there.');
        }

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        fwrite(STDERR, "\n[GAP-040/GAP-044 probe] " . json_encode($probe) . "\n");

        $this->assertFalse(
            $probe['table_existed_before_bootstrap'],
            'zena_roles already existed before bootstrap ran despite forceGenuineColdStartForNextSetUp() — the deterministic cold-start forcing mechanism itself is broken. This must fail, not skip: a genuine cold start was required and did not occur.'
        );

        $this->assertSame(
            1,
            $probe['transaction_level_before_bootstrap'],
            'RefreshDatabase transaction was not open before the RBAC compat bootstrap ran.'
        );

        $this->assertSame(
            1,
            $probe['transaction_level_after_bootstrap'],
            'Transaction level changed across the RBAC compat bootstrap — the bootstrap DDL affected the main transacted connection (implicit-commit defect present).'
        );

        $this->assertTrue(
            $probe['pdo_in_transaction_before_bootstrap'],
            'PDO::inTransaction() was already false before the bootstrap ran — RefreshDatabase never actually had an open transaction on this connection.'
        );

        $this->assertTrue(
            $probe['pdo_in_transaction_after_bootstrap'],
            'PDO::inTransaction() is false after the RBAC compat bootstrap — this is direct, server-reported proof of the GAP-040 implicit-commit defect.'
        );

        $this->assertArrayHasKey(
            'bootstrap_connection_id',
            $probe,
            'No separate bootstrap session was recorded — the isolated-connection mechanism did not run.'
        );

        $this->assertNotSame(
            $probe['main_connection_id'],
            $probe['bootstrap_connection_id'],
            'The RBAC compat bootstrap ran on the same MySQL session (CONNECTION_ID) as the main transacted connection — not a genuinely separate session.'
        );

        // GAP-044 Surface 1: the three previously-unfixed sibling helpers
        // must exhibit the identical invariant now.
        foreach (['interaction_logs', 'project_phases', 'project_tasks'] as $helperTable) {
            $this->assertArrayHasKey(
                $helperTable,
                $probe['helpers'] ?? [],
                "No GAP-044 probe data recorded for the {$helperTable} bootstrap helper on this cold-start run — either the table already existed (proof did not exercise cold start for it) or instrumentation is missing."
            );

            $this->assertTrue(
                $probe['helpers'][$helperTable]['pdo_in_transaction_before'],
                "PDO::inTransaction() was already false before the {$helperTable} bootstrap ran."
            );

            $this->assertTrue(
                $probe['helpers'][$helperTable]['pdo_in_transaction_after'],
                "PDO::inTransaction() is false after the {$helperTable} bootstrap ran — GAP-044 Surface 1 implicit-commit defect present for this helper."
            );
        }

        $tenant = Tenant::factory()->create([
            'name' => 'gap040-cold-start-' . (string) Str::uuid(),
        ]);

        self::$lastWrittenMarkerId = (string) $tenant->id;

        return $tenant->id;
    }

    /**
     * GAP-044: must be called from the verifier test class's own setUp()
     * BEFORE parent::setUp() runs. Captures, via RefreshDatabaseState::$migrated
     * and an independent PDO read, whether a migrate:fresh is about to
     * occur in the verifier's own parent::setUp() and whether the marker
     * is already gone at this exact boundary — the discriminator the
     * original GAP-040 proof lacked (confirmed false-green in GAP-044 Gate
     * 1 §H1: docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md).
     */
    protected function captureDiscriminatingStateBeforeVerifierSetUp(): void
    {
        if (getenv('DB_CONNECTION') !== 'mysql') {
            return;
        }

        self::$migratedBeforeVerifierSetUp = RefreshDatabaseState::$migrated;
        self::$markerVisibleBeforeVerifierSetUp = self::$lastWrittenMarkerId !== null
            ? $this->independentPdoSeesTenant(self::$lastWrittenMarkerId)
            : null;
    }

    /**
     * GAP-044: asserts the marker's eventual disappearance is attributable
     * to the writer's own RefreshDatabase rollback specifically, not to
     * the verifier's own parent::setUp() running migrate:fresh. Must be
     * called from the verifier test method, after
     * captureDiscriminatingStateBeforeVerifierSetUp() ran in that same
     * test's setUp(). Do NOT accept "marker absent after verifier setup"
     * alone as sufficient proof — that is exactly the false-green mode
     * this method exists to rule out.
     */
    protected function assertMarkerDisappearedViaRollbackNotMigrateFresh(): void
    {
        $this->assertNotNull(
            self::$migratedBeforeVerifierSetUp,
            'captureDiscriminatingStateBeforeVerifierSetUp() was not called from this test class\'s setUp() before parent::setUp() — cannot distinguish rollback from migrate:fresh without it.'
        );

        $this->assertTrue(
            self::$migratedBeforeVerifierSetUp,
            'RefreshDatabaseState::$migrated was false immediately before the verifier\'s own parent::setUp() ran — a migrate:fresh was about to execute and could explain the marker\'s disappearance via schema wipe rather than genuine rollback. This is the exact false-green mode confirmed in GAP-044 Gate 1 (docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md §H1).'
        );

        $this->assertFalse(
            self::$markerVisibleBeforeVerifierSetUp,
            'The marker row was still visible via independent PDO immediately before the verifier\'s own parent::setUp() ran, meaning it had not yet disappeared at that point — its later absence (if observed) cannot be attributed with confidence to the writer\'s teardown rollback.'
        );
    }

    /**
     * Verifier half of the proof. Must run under a setUp() that did NOT
     * call forceGenuineColdStartForNextSetUp() — no migrate:fresh, no
     * truncate, no reset of any kind may be *forced* between the writer's
     * teardown and this read (GAP-044's captureDiscriminatingStateBeforeVerifierSetUp()
     * independently verifies none *happened*, rather than merely not
     * forcing one). Queries via a brand-new, non-persistent PDO connection
     * — never a Laravel-managed connection — so the read cannot be
     * satisfied by in-process transaction visibility artifacts. A
     * missing/empty $tenantId is a hard failure (fail closed), never a
     * skip: PHPUnit's #[Depends] on the consuming test class is what
     * supplies it, and a broken dependency there is itself a defect worth
     * surfacing loudly, not hiding.
     *
     * @group stress
     */
    protected function assertMarkerRowAbsentViaIndependentConnection(string $tenantId): void
    {
        $this->assertNotSame('', $tenantId, 'No marker tenant id was supplied by the writer test — the #[Depends] value-passing itself is broken.');

        $this->assertFalse(
            $this->independentPdoSeesTenant($tenantId),
            'A fresh, independent PDO connection (not reusing any Laravel-managed connection) still finds the cold-start test row — RefreshDatabase rollback did not take effect.'
        );
    }

    private function independentPdoSeesTenant(string $tenantId): bool
    {
        $pdo = new \PDO(
            sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('database.connections.mysql.host'),
                config('database.connections.mysql.port'),
                config('database.connections.mysql.database')
            ),
            config('database.connections.mysql.username'),
            config('database.connections.mysql.password'),
            [\PDO::ATTR_PERSISTENT => false, \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM tenants WHERE id = ?');
        $stmt->execute([$tenantId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }
}
```

- [ ] **Step 2: Wire the new discriminator into all 5 consuming test classes**

For **each** of the 5 files below, apply the identical edit pattern (all 5 currently share byte-identical `setUp()`/`test_a`/`test_b` shapes, confirmed during planning):

- `tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php`
- `tests/Feature/Zena/ZenaInvariantsTransactionIsolationColdStartTest.php`
- `tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php`
- `tests/E2E/TransactionIsolationColdStartTest.php`
- `tests/Feature/Documents/TransactionIsolationColdStartTest.php`

Add a `VERIFIER_TEST` constant next to the existing `WRITER_TEST` one:

```php
    private const WRITER_TEST = 'test_a_writes_marker_after_proving_cold_start_invariant';
    private const VERIFIER_TEST = 'test_b_rolled_back_write_is_absent_via_independent_connection';
```

Change `setUp()` from:

```php
    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        if ($this->name() === self::WRITER_TEST) {
            $this->forceGenuineColdStartForNextSetUp();
        }
        parent::setUp();
    }
```

to:

```php
    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        if ($this->name() === self::WRITER_TEST) {
            $this->forceGenuineColdStartForNextSetUp();
        }
        if ($this->name() === self::VERIFIER_TEST) {
            $this->captureDiscriminatingStateBeforeVerifierSetUp();
        }
        parent::setUp();
    }
```

Change `test_b_rolled_back_write_is_absent_via_independent_connection()` from:

```php
    #[Depends(self::WRITER_TEST)]
    public function test_b_rolled_back_write_is_absent_via_independent_connection(string $tenantId): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection($tenantId);
    }
```

to:

```php
    #[Depends(self::WRITER_TEST)]
    public function test_b_rolled_back_write_is_absent_via_independent_connection(string $tenantId): void
    {
        $this->assertMarkerDisappearedViaRollbackNotMigrateFresh();
        $this->assertMarkerRowAbsentViaIndependentConnection($tenantId);
    }
```

- [ ] **Step 3: Commit**

```bash
git add tests/Support/GAP040ColdStartTransactionIsolationAssertions.php \
  tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php \
  tests/Feature/Zena/ZenaInvariantsTransactionIsolationColdStartTest.php \
  tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php \
  tests/E2E/TransactionIsolationColdStartTest.php \
  tests/Feature/Documents/TransactionIsolationColdStartTest.php
git commit -m "test(GAP-044): strengthen shared cold-start proof with rollback-vs-migrate:fresh discriminator — expected RED before the fix"
```

- [ ] **Step 4: Push and confirm RED on real MySQL, for the right reason**

```bash
git push origin feature/GAP-044-testcase-transaction-and-permission-lookup
```

This push (branch name matches the `feature/*` pattern `automated-testing.yml` and `ci-cd.yml` trigger on) plus the Draft PR opened in Task 0 (which triggers `routes-guardrails.yml` via `pull_request`) will run the `mysql-parity`, `zena-invariants-mysql`, and `treasury-check-constraints-mysql` surfaces automatically. Inspect:

```bash
gh run list --branch feature/GAP-044-testcase-transaction-and-permission-lookup --limit 10 --json databaseId,workflowName,status,conclusion
gh run view --job <routes-guardrails-job-id> --log | grep -A5 "ZenaTransactionIsolationColdStartTest\|assertMarkerDisappearedViaRollbackNotMigrateFresh\|helpers"
```

Expected: **FAIL**, specifically on one of:
- `proveColdStartAndWriteMarker()`'s new per-helper loop (`pdo_in_transaction_after` false for `interaction_logs`/`project_phases`/`project_tasks`), or
- `assertMarkerDisappearedViaRollbackNotMigrateFresh()`'s `$migratedBeforeVerifierSetUp` assertion (false, proving a migrate:fresh was about to run).

If it fails to run at all (autoload/syntax error), fix that first — an infrastructure failure is not the RED this task needs. Record the exact failing assertion and surface for the Gate-3 packet.

---

### Task 5: GREEN — implement Surface 1 fix (A2-style direct reuse)

**Files:**
- Modify: `tests/TestCase.php` (`ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`, `ensureProjectTasksTable()`, from Task 3's instrumented versions)

**Interfaces:**
- Produces: all three methods now route their `Schema::create()` through the existing `zenaRbacBootstrapSchema()` isolated connection on non-SQLite drivers — identical pattern to the already-fixed `ensureSqliteZenaRbacTables()`.

- [ ] **Step 1: Route the three helpers' DDL through the existing isolated connection**

Edit `tests/TestCase.php`, replacing each of the three methods (from Task 3's instrumented version) with:

```php
    private function ensureInteractionLogsTable(): void
    {
        if (Schema::hasTable('interaction_logs')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('interaction_logs');

        $schema = config('database.default') === 'sqlite'
            ? Schema::connection(config('database.default'))
            : $this->zenaRbacBootstrapSchema();

        $schema->create('interaction_logs', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('project_id');
            $table->string('linked_task_id')->nullable();
            $table->string('type');
            $table->text('description');
            $table->string('tag_path')->nullable();
            $table->string('visibility');
            $table->boolean('client_approved')->default(false);
            $table->string('created_by');
            $table->timestamps();
            $table->softDeletes();
        });

        if (config('database.default') !== 'sqlite') {
            DB::purge('zena_ddl_bootstrap');
        }

        $this->gap044ProbeAfterHelper('interaction_logs');
    }

    private function ensureProjectPhasesTable(): void
    {
        if (Schema::hasTable('project_phases')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('project_phases');

        $schema = config('database.default') === 'sqlite'
            ? Schema::connection(config('database.default'))
            : $this->zenaRbacBootstrapSchema();

        $schema->create('project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('name');
            $table->integer('order')->default(0);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_phase_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (config('database.default') !== 'sqlite') {
            DB::purge('zena_ddl_bootstrap');
        }

        $this->gap044ProbeAfterHelper('project_phases');
    }

    private function ensureProjectTasksTable(): void
    {
        if (Schema::hasTable('project_tasks')) {
            return;
        }

        $this->gap044ProbeBeforeHelper('project_tasks');

        $schema = config('database.default') === 'sqlite'
            ? Schema::connection(config('database.default'))
            : $this->zenaRbacBootstrapSchema();

        $schema->create('project_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->ulid('phase_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('duration_days')->default(0);
            $table->float('progress_percent')->default(0.0);
            $table->string('status')->default('pending');
            $table->string('conditional_tag')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->ulid('template_id')->nullable();
            $table->ulid('template_task_id')->nullable();
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if (config('database.default') !== 'sqlite') {
            DB::purge('zena_ddl_bootstrap');
        }

        $this->gap044ProbeAfterHelper('project_tasks');
    }
```

No change to `zenaRbacBootstrapSchema()` itself — reused exactly as-is (A2-style), per Owner's explicit preference.

- [ ] **Step 2: Local sanity check (SQLite path unchanged)**

Run: `./vendor/bin/phpunit tests/Feature/Zena/ZenaRbacTenantSmokeTest.php`
Expected: PASS, identical outcome to Task 3 Step 3.

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase.php
git commit -m "fix(GAP-044): Surface 1 — route ensureInteractionLogsTable/ensureProjectPhasesTable/ensureProjectTasksTable DDL through the existing isolated zena_ddl_bootstrap connection"
```

- [ ] **Step 4: Push and confirm GREEN on the surfaces reachable from a push/PR**

```bash
git push origin feature/GAP-044-testcase-transaction-and-permission-lookup
gh run list --branch feature/GAP-044-testcase-transaction-and-permission-lookup --limit 10 --json databaseId,workflowName,status,conclusion
```

Wait for `routes-guardrails.yml` (`mysql-parity`), `automated-testing.yml`'s `zena-invariants-mysql` and `treasury-check-constraints-mysql` jobs, and `ci-cd.yml`'s `test` job. Expected: all `success`, with the strengthened proof's new assertions (per-helper `pdo_in_transaction`, `assertMarkerDisappearedViaRollbackNotMigrateFresh()`) passing on each. If any is still RED: do not add a second fix on top — return to `systematic-debugging`, re-read the actual failure first.

---

### Task 6: GREEN verification — remaining GAP-040 surfaces (E2E) and full 5-surface confirmation

**Files:** none (verification only).

- [ ] **Step 1: Manually trigger the E2E surface**

`a11y-perf-testing.yml` only runs on `schedule`/`workflow_dispatch` (per GAP-040 Gate 1 evidence, unchanged) — it will not run automatically on push.

```bash
gh workflow run a11y-perf-testing.yml --ref feature/GAP-044-testcase-transaction-and-permission-lookup
gh run list --branch feature/GAP-044-testcase-transaction-and-permission-lookup --workflow=a11y-perf-testing.yml --limit 1 --json databaseId,status,conclusion
```

Wait for the `e2e-tests` job specifically (the workflow also runs `accessibility-tests`/`performance-budget`/`performance-heavy`/`lighthouse-ci` — irrelevant to GAP-044, not this task's concern, per GAP-040's own precedent). Expected: `e2e-tests` job `success`, with `TransactionIsolationColdStartTest`'s strengthened assertions passing.

- [ ] **Step 2: Collect and record all 5 surfaces' results against the exact head SHA**

```bash
git rev-parse HEAD
gh run list --branch feature/GAP-044-testcase-transaction-and-permission-lookup --limit 15 --json databaseId,workflowName,status,conclusion,headSha
```

Confirm, on the same head SHA: `routes-guardrails.yml` (`mysql-parity`) success, `automated-testing.yml`'s `zena-invariants-mysql` success, `automated-testing.yml`'s `treasury-check-constraints-mysql` success, `a11y-perf-testing.yml`'s `e2e-tests` success, `ci-cd.yml`'s `test` job success. Record the exact head SHA and all 5 run URLs — this is required evidence for the Gate-3 packet (spec §5 item 4).

---

### Task 7: Authoritative seeded performance-test pipelines (spec §5 items 5-6)

**Files:** none (verification only — these tests already exist, unmodified by this plan).

- [ ] **Step 1: Trigger the real `performance-tests` job on the implementation branch**

`automated-testing.yml`'s `performance-tests` job already runs on push to `feature/*` (matrix: `PerformanceMonitoringTest.php` and `DashboardPerformanceTest.php`), using the real `migrate` + `db:seed --env=testing --force` pipeline. Confirm the job ran as part of Task 5/6's pushes:

```bash
gh run list --branch feature/GAP-044-testcase-transaction-and-permission-lookup --workflow=automated-testing.yml --limit 5 --json databaseId,status,conclusion
gh run view --job <performance-tests-monitoring-job-id> --log | tail -100
gh run view --job <performance-tests-dashboard-job-id> --log | tail -100
```

**Note:** main's current `automated-testing.yml` still lacks GAP-041's `--group=performance --fail-on-empty-test-suite` selector fix (GAP-041 remains separately open, untouched by this plan) — the job as committed on `main` may silently select 0 tests. If so, this step alone is not sufficient evidence; proceed to Step 2.

- [ ] **Step 2: If the job silently selects 0 tests, obtain truthful evidence via a disposable, never-merged overlay (same precedent as GAP-043/GAP-044 Gate 1)**

If Step 1 shows `No tests found.`/0 tests executed, this is GAP-041's known, separately-tracked defect — not a GAP-044 regression. Obtain truthful evidence the same way GAP-043's Gate 3 and this gap's own Gate 1 did: a disposable, local-only overlay (never committed to this implementation branch, never merged) adding `--group=performance --fail-on-empty-test-suite` to the workflow step, pushed to a throwaway branch derived from this implementation branch's exact head, workflow-dispatched, evidence captured, branch deleted immediately after. Record the disposable overlay's run ID/job IDs in the Gate-3 packet exactly as GAP-043's `03-release.md` did, and note explicitly that GAP-041 remains open and unfixed.

- [ ] **Step 3: Confirm the required GAP-044 result**

From whichever of Step 1/Step 2 produced truthful test execution:
- `PerformanceMonitoringTest.php`'s `test_api_performance_budgets`: no longer fails with `SAVEPOINT trans2 does not exist` (1305) and no longer fails with a duplicate-permission `UniqueConstraintViolationException` (1062) attributable to GAP-044.
- `DashboardPerformanceTest.php`'s `it_can_load_dashboard_with_large_dataset_quickly`: no longer fails with 1305 or 1062.
- `DashboardPerformanceTest.php`'s separate `it_can_load_alerts_with_large_dataset_quickly` latency-budget assertion (GAP-045): report its actual result **separately** — do not modify the 450ms threshold, do not treat a GAP-045 failure as a GAP-044 failure, and do not claim the whole job is "green" if GAP-045's assertion is still red. State the true, complete result.

Record exact run/job IDs, test counts, and pass/fail detail for the Gate-3 packet.

---

### Task 8: Full SQLite regression suite (local)

**Files:** none (verification only).

- [ ] **Step 1: Run the full default (SQLite) suite locally**

```bash
./vendor/bin/phpunit --testsuite=Unit,Feature,Integration
```

Expected: same pass/fail/skip counts as a baseline run of the same command against unmodified `origin/main` (capture a baseline via `gh run list --branch main --workflow=automated-testing.yml --limit 1` for the `unit-tests`/`feature-tests`/`integration-tests` job summaries if a fresh local `origin/main` run isn't practical, per GAP-040's own precedent for this exact constraint).

- [ ] **Step 2: If any new failure appears, isolate whether it's caused by this branch's changes**

```bash
git stash
./vendor/bin/phpunit --filter <FailingTestName>
git stash pop
```

If the same test fails identically against unmodified `origin/main`, it's pre-existing and unrelated — do not fix it under GAP-044. If it only fails on this branch, that's a real regression — STOP and return to `systematic-debugging` before proceeding.

---

### Task 9: Final whole-branch review and implementation-tree integrity

**Files:** none.

- [ ] **Step 1: Re-read every changed file against the approved Gate-2 spec's non-goals**

Confirm the final diff touches only: `tests/TestCase.php`, `tests/Traits/TenantUserFactoryTrait.php`, `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`, the 5 consuming test classes listed in Task 4, and the 1 new test file from Task 1. No migration, no seeder, no `app/`/`src/` file, no workflow file.

```bash
git diff <gate-2-approval-record-head>...feature/GAP-044-testcase-transaction-and-permission-lookup --stat
```

- [ ] **Step 2: Confirm exclusions held**

```bash
git diff <gate-2-approval-record-head>...feature/GAP-044-testcase-transaction-and-permission-lookup -- database/seeders/RoleSeeder.php database/seeders/PermissionSeeder.php database/migrations OPERATIONAL_GAP_REGISTER.md .github/workflows
```

Expected: empty (RoleSeeder/PermissionSeeder/migrations/register/workflows untouched).

```bash
git diff <gate-2-approval-record-head>...feature/GAP-044-testcase-transaction-and-permission-lookup -- "docs/owner-decisions/GAP-040/**" "docs/owner-decisions/GAP-041/**" "docs/owner-decisions/GAP-042/**" "docs/owner-decisions/GAP-043/**" "docs/owner-decisions/GAP-045/**"
```

Expected: empty.

- [ ] **Step 3: Compute the canonical implementation-tree digest**

Follow the exact same governance mechanics used in GAP-040's/GAP-043's Gate-3 releases (`owner_governance_compute_implementation_tree_digest()`/`scripts/ssot/owner_governance_lint.php`, excluding only the not-yet-written `03-release.md` itself). Record the exact subject SHA and digest value.

- [ ] **Step 4: Verify Gate-1/Gate-2 artifact blobs did not drift**

```bash
git diff docs/GAP-044-gate1-investigation -- docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md docs/owner-decisions/GAP-044/01-request.md
git diff docs/GAP-044-gate2-design -- docs/superpowers/specs/2026-08-22-gap-044-testcase-transaction-and-permission-lookup-design.md docs/owner-decisions/GAP-044/02-design.md
```

Expected: both empty (byte-identical to their approved states).

- [ ] **Step 5: Summarize for Gate 3 preparation**

Compile: exact final head SHA, implementation-tree digest, all CI run URLs from Tasks 4-8, RED/GREEN evidence summary, GAP-045 result reported separately, confirmation of exclusions. This is the evidence the Gate-3 packet (prepared next, per Owner instruction — not submitted) will need. Do not mark the PR ready, do not merge.
