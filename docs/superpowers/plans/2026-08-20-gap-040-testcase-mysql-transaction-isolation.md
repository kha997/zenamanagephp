# GAP-040 — TestCase MySQL Transaction Isolation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eliminate all DDL execution on the transacted MySQL connection between `RefreshDatabase`'s transaction opening and a test body running — including the first `RefreshDatabase` test of a fresh process — while preserving the `zena_*` RBAC compatibility tables required by live application code, with proof anchored to the cold-start case on all 5 Gate-1-approved real-MySQL surfaces.

**Architecture:** `tests/TestCase.php::ensureSqliteZenaRbacTables()` gets an existence guard (skip if `zena_roles` already exists) and, on non-SQLite drivers, issues its `CREATE TABLE` statements through a second, dynamically-registered Laravel DB connection (`zena_ddl_bootstrap`) that points at the identical physical database but is never enlisted in `RefreshDatabase`'s transacted-connections list and never uses a pooled/persistent PDO handle — so its DDL's MySQL-implicit-commit only ever affects its own session, never the test's open transaction. A shared cold-start regression trait proves this behaviorally (not by inspecting source shape) on each of the 5 real-MySQL surfaces identified at Gate 1.

**Tech Stack:** PHP 8.2, Laravel 12, PHPUnit 11.5.56, MySQL 8.0 (CI service containers), SQLite (local/default test driver).

## Global Constraints

- No production migration, `RBACManager`, `Src\RBAC\Models\*`, production authorization behavior, or tenant semantics may be touched (Owner Gate 2 boundary). Any need to do so is a STOP-and-return-to-Owner condition, not a task in this plan.
- No `config/database.php` checked-in change unless the runtime-registration mechanism (Task 3) is empirically shown infeasible during this implementation — if that happens, STOP this task and return to Owner per the approved contract, do not silently substitute Option B as final.
- SQLite-driven test suite outcomes must be unchanged, before/after (Task 9).
- GAP-041 and GAP-042 (PR #270, unmerged) are not touched, not fixed, and not depended upon by this work.
- **No local real MySQL is available in this environment** (a local MariaDB start attempt failed on a filesystem permission error during Gate 1 evidence-gathering, and was not worked around). Every step in this plan that requires real MySQL is verified by pushing to this branch and inspecting the actual GitHub Actions run for the relevant workflow — this is the only real verification channel for MySQL-dependent behavior in this environment, consistent with existing comments in `scripts/ci/*-mysql` acknowledging the same constraint. Steps that only need SQLite (Task 9) are run locally.

---

### Task 1: Cold-start probe instrumentation in `TestCase.php`

**Files:**
- Modify: `tests/TestCase.php:203-242` (`ensureSqliteZenaRbacTables()`)

**Interfaces:**
- Produces: `public static ?array TestCase::$coldStartProbe` — when non-null, populated by `ensureSqliteZenaRbacTables()` with `table_existed_before_bootstrap` (bool), `transaction_level_before_bootstrap` (int), `transaction_level_after_bootstrap` (int). Consumed by Task 2's test.

This task only adds an observation point — it does not yet change the DDL's isolation behavior (that's Task 3). It exists so Task 2's regression test can be written and proven RED against the *current* (buggy) behavior before Task 3 fixes it — proper TDD ordering, and the literal "cold-start regression harness first" requirement.

- [ ] **Step 1: Add the static probe property and populate it inside `ensureSqliteZenaRbacTables()`**

Edit `tests/TestCase.php`, replacing the existing `ensureSqliteZenaRbacTables()` method body (lines 203-242) with an instrumented version that keeps the exact same DDL for now (Task 3 changes the DDL mechanism):

```php
    /**
     * Test-only introspection point for the GAP-040 cold-start transaction-
     * isolation regression proof. Null in every ordinary test; set to an
     * empty array only by the dedicated cold-start test classes before
     * calling parent::setUp(), so this method can record what happened
     * during their setUp() without affecting any other test.
     *
     * @var array<string, bool|int>|null
     */
    public static ?array $coldStartProbe = null;

    private function ensureSqliteZenaRbacTables(): void
    {
        $tableExistedBeforeBootstrap = Schema::hasTable('zena_roles');

        if (self::$coldStartProbe !== null) {
            self::$coldStartProbe['table_existed_before_bootstrap'] = $tableExistedBeforeBootstrap;
            self::$coldStartProbe['transaction_level_before_bootstrap'] = DB::transactionLevel();
            if (config('database.default') !== 'sqlite') {
                self::$coldStartProbe['main_connection_id'] = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            }
        }

        if ($tableExistedBeforeBootstrap) {
            return;
        }

        Schema::dropIfExists('zena_role_permissions');
        Schema::dropIfExists('zena_user_roles');
        Schema::dropIfExists('zena_roles');
        Schema::dropIfExists('zena_permissions');

        Schema::create('zena_permissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('action');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zena_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->string('scope')->default('system');
            $table->boolean('allow_override')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('zena_role_permissions', function (Blueprint $table) {
            $table->string('role_id');
            $table->string('permission_id');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();
        });

        Schema::create('zena_user_roles', function (Blueprint $table) {
            $table->string('user_id');
            $table->string('role_id');
            $table->timestamps();
        });

        if (self::$coldStartProbe !== null) {
            self::$coldStartProbe['transaction_level_after_bootstrap'] = DB::transactionLevel();
        }
    }
```

Note: the `dropIfExists` calls are dead code once the existence guard is in place (the guard already proved the table doesn't exist), but Task 1 keeps them for a minimal, reviewable diff — Task 3 removes them when it changes the DDL mechanism.

- [ ] **Step 2: Local sanity check (SQLite, no MySQL needed)**

Run: `./vendor/bin/phpunit tests/Feature/Zena/ZenaRbacTenantSmokeTest.php`
Expected: PASS, same as before this change (this class already exercises the `zena_*` tables on the default SQLite path; the probe being `null` here means the new code takes the exact same path it always did).

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase.php
git commit -m "test(GAP-040): add cold-start probe instrumentation to ensureSqliteZenaRbacTables"
```

---

### Task 2: Shared cold-start assertion trait + first regression test (Zena surface) — prove RED

**Files:**
- Create: `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`
- Create: `tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php`

**Interfaces:**
- Consumes: `TestCase::$coldStartProbe` (Task 1).
- Produces: `GAP040ColdStartTransactionIsolationAssertions::assertColdStartInvariantHeld(): void` and `::writeMarkerRow(): string` and `::assertMarkerRowAbsentViaIndependentConnection(string $marker): void` — consumed by every per-surface test file in Tasks 4-6.

This is the primary proof. It is deliberately written and pushed to CI *before* Task 3's fix, to observe it fail for the right reason (an already-open transaction whose level drops, or a leaked row) — not merely fail to compile.

- [ ] **Step 1: Write the shared trait**

Create `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`:

```php
<?php

namespace Tests\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Shared GAP-040 cold-start transaction-isolation regression proof.
 * Consumed by one test class per Gate-1-approved real-MySQL surface.
 */
trait GAP040ColdStartTransactionIsolationAssertions
{
    protected function assertColdStartInvariantHeld(): void
    {
        $this->assertSame(
            'mysql',
            config('database.default'),
            'This proof only exercises the GAP-040 invariant against a real MySQL connection.'
        );

        $probe = TestCase::$coldStartProbe;
        $this->assertNotNull($probe, 'Cold-start probe was not populated — setUp() must set TestCase::$coldStartProbe = [] before calling parent::setUp().');

        $this->assertFalse(
            $probe['table_existed_before_bootstrap'],
            'zena_roles already existed before bootstrap ran — this run is not exercising the cold-start case. This test must be the first RefreshDatabase test executed in its process/job.'
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

        // Only meaningful once the fix (Task 3) populates bootstrap_connection_id;
        // absent pre-fix, where no separate session exists yet to compare.
        if (array_key_exists('bootstrap_connection_id', $probe)) {
            $this->assertNotSame(
                $probe['main_connection_id'],
                $probe['bootstrap_connection_id'],
                'The RBAC compat bootstrap ran on the same MySQL session (CONNECTION_ID) as the main transacted connection — not a genuinely separate session.'
            );
        }
    }

    protected function writeMarkerRow(): string
    {
        $tenant = Tenant::factory()->create([
            'name' => 'gap040-cold-start-' . (string) Str::uuid(),
        ]);

        file_put_contents($this->coldStartMarkerFilePath(), $tenant->id);

        return $tenant->id;
    }

    protected function assertMarkerRowAbsentViaIndependentConnection(): void
    {
        $markerPath = $this->coldStartMarkerFilePath();

        if (!file_exists($markerPath)) {
            $this->markTestSkipped('No cold-start marker file found — the write-side test must run first, in the same process, before this verification test.');
        }

        $tenantId = trim((string) file_get_contents($markerPath));
        @unlink($markerPath);

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
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(
            0,
            $count,
            'A fresh, independent PDO connection (not reusing any Laravel-managed connection) still finds the cold-start test row — RefreshDatabase rollback did not take effect.'
        );
    }

    private function coldStartMarkerFilePath(): string
    {
        return storage_path('app/gap040-cold-start-marker.txt');
    }
}
```

- [ ] **Step 2: Write the first consumer test (Zena surface — covers both `routes-guardrails.yml`'s `--group=mysql-parity` and `automated-testing.yml`'s `zena-invariants-mysql` via dual `@group` tags)**

Create `tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

/**
 * @group mysql-parity
 * @group zena-invariants
 */
class ZenaTransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_cold_start_bootstrap_does_not_break_transaction_isolation(): void
    {
        $this->assertColdStartInvariantHeld();
        $this->writeMarkerRow();
    }

    public function test_b_rolled_back_write_is_absent_via_independent_connection(): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection();
    }
}
```

Method names are prefixed `test_a_`/`test_b_` so PHPUnit's default declaration-order execution (confirmed: no `--order-by=random` or `resolveDependencies` set on any of the 5 CI surfaces — checked `phpunit.xml`, `phpunit.mysql.xml`, and every relevant workflow/script invocation) runs the write before the independent-verification read, within the same process.

- [ ] **Step 3: Push and confirm RED on real MySQL, for the right reason**

```bash
git add tests/Support/GAP040ColdStartTransactionIsolationAssertions.php tests/Feature/Zena/ZenaTransactionIsolationColdStartTest.php
git commit -m "test(GAP-040): add cold-start transaction-isolation proof (Zena surface) — expected RED before the fix"
git push origin feature/GAP-040-mysql-transaction-isolation
```

Then trigger and inspect the real run (this branch's push triggers `routes-guardrails.yml`; trigger `automated-testing.yml`'s `zena-invariants-mysql` job the same way since it runs on push too):

```bash
gh run list --branch feature/GAP-040-mysql-transaction-isolation --limit 5 --json databaseId,workflowName,status
# wait for both routes-guardrails.yml and automated-testing.yml runs to complete, then:
gh run view <routes-guardrails-run-id> --log-failed | grep -A5 "ZenaTransactionIsolationColdStartTest"
gh run view <automated-testing-run-id> --log-failed | grep -A5 "ZenaTransactionIsolationColdStartTest"
```

Expected: FAIL on `test_b_rolled_back_write_is_absent_via_independent_connection` (or on the `transaction_level_after_bootstrap` assertion in `test_a_...`, depending on exact MySQL server behavior) — confirming the pre-fix code genuinely exhibits the defect this test is designed to catch. If instead it fails to run at all (config/autoload error), fix that first — an infrastructure failure is not the RED this task needs.

---

### Task 3: Implement the isolated-connection bootstrap (the fix)

**Files:**
- Modify: `tests/TestCase.php` (`ensureSqliteZenaRbacTables()`, from Task 1's version)

**Interfaces:**
- Produces: `ensureSqliteZenaRbacTables()` now routes MySQL-driver DDL through a `zena_ddl_bootstrap` connection registered at runtime; SQLite path unchanged.

- [ ] **Step 1: Replace the DDL execution with the isolated-connection mechanism**

Edit `tests/TestCase.php`, replacing the body added in Task 1 with:

```php
    private function ensureSqliteZenaRbacTables(): void
    {
        $tableExistedBeforeBootstrap = Schema::hasTable('zena_roles');

        if (self::$coldStartProbe !== null) {
            self::$coldStartProbe['table_existed_before_bootstrap'] = $tableExistedBeforeBootstrap;
            self::$coldStartProbe['transaction_level_before_bootstrap'] = DB::transactionLevel();
        }

        if ($tableExistedBeforeBootstrap) {
            return;
        }

        $schema = config('database.default') === 'sqlite'
            ? Schema::connection(config('database.default'))
            : $this->zenaRbacBootstrapSchema();

        $schema->create('zena_permissions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->string('module');
            $table->string('action');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('zena_roles', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name')->unique();
            $table->string('scope')->default('system');
            $table->boolean('allow_override')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('zena_role_permissions', function (Blueprint $table) {
            $table->string('role_id');
            $table->string('permission_id');
            $table->boolean('allow_override')->default(false);
            $table->timestamps();
        });

        $schema->create('zena_user_roles', function (Blueprint $table) {
            $table->string('user_id');
            $table->string('role_id');
            $table->timestamps();
        });

        if (config('database.default') !== 'sqlite') {
            DB::purge('zena_ddl_bootstrap');
        }

        if (self::$coldStartProbe !== null) {
            self::$coldStartProbe['transaction_level_after_bootstrap'] = DB::transactionLevel();
        }
    }

    /**
     * Registers (once) and returns a Schema builder for a second MySQL
     * session, pointed at the identical physical database as the active
     * connection, that is never enlisted in RefreshDatabase's
     * connectionsToTransact() and never a pooled/persistent PDO handle —
     * so its DDL's implicit commit only affects its own session, never the
     * test's already-open transaction (GAP-040).
     */
    private function zenaRbacBootstrapSchema(): \Illuminate\Database\Schema\Builder
    {
        $activeConnectionName = config('database.default');
        $bootstrapConfig = config("database.connections.{$activeConnectionName}");

        // Force a genuinely distinct session: a pooled/persistent PDO handle
        // with identical DSN+credentials could otherwise be silently reused
        // by PHP for both connection names, defeating the whole mechanism.
        $bootstrapConfig['options'] = array_filter(
            (array) ($bootstrapConfig['options'] ?? []),
            fn ($key) => $key !== \PDO::ATTR_PERSISTENT,
            ARRAY_FILTER_USE_KEY
        );
        $bootstrapConfig['options'][\PDO::ATTR_PERSISTENT] = false;

        config(['database.connections.zena_ddl_bootstrap' => $bootstrapConfig]);
        DB::purge('zena_ddl_bootstrap');

        if (self::$coldStartProbe !== null) {
            self::$coldStartProbe['bootstrap_connection_id'] = (int) DB::connection('zena_ddl_bootstrap')
                ->selectOne('SELECT CONNECTION_ID() AS id')->id;
        }

        return Schema::connection('zena_ddl_bootstrap');
    }
```

- [ ] **Step 2: Local sanity check (SQLite path unchanged)**

Run: `./vendor/bin/phpunit tests/Feature/Zena/ZenaRbacTenantSmokeTest.php`
Expected: PASS (identical outcome to Task 1 Step 2 — the SQLite branch takes `Schema::connection('sqlite')`, behaviorally identical to the bare `Schema::create(...)` calls it replaces).

- [ ] **Step 3: Commit**

```bash
git add tests/TestCase.php
git commit -m "fix(GAP-040): bootstrap zena_* RBAC compat tables on an isolated MySQL session"
```

- [ ] **Step 4: Push and confirm GREEN on both Zena surfaces**

```bash
git push origin feature/GAP-040-mysql-transaction-isolation
gh run list --branch feature/GAP-040-mysql-transaction-isolation --limit 5 --json databaseId,workflowName,status,conclusion
```

Wait for `routes-guardrails.yml` and `automated-testing.yml` (job `zena-invariants-mysql`) to complete. Expected: both `success`, and specifically `ZenaTransactionIsolationColdStartTest`'s two methods pass (confirm via `gh run view <id> --log | grep ZenaTransactionIsolationColdStartTest`).

If either is still RED: do not add a second fix on top. Return to systematic-debugging — re-read the actual failure (connection-pooling reuse is the most likely culprit; verify by adding a temporary `SELECT CONNECTION_ID()` comparison between the active and bootstrap connections in the failing run's log) before changing anything else.

---

### Task 4: Treasury surface companion test

**Files:**
- Create: `tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php`

**Interfaces:**
- Consumes: `GAP040ColdStartTransactionIsolationAssertions` (Task 2).

- [ ] **Step 1: Write the test**

Create `tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations\Treasury;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

class TreasuryTransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_cold_start_bootstrap_does_not_break_transaction_isolation(): void
    {
        $this->assertColdStartInvariantHeld();
        $this->writeMarkerRow();
    }

    public function test_b_rolled_back_write_is_absent_via_independent_connection(): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection();
    }
}
```

This directory (`tests/Unit/Migrations/Treasury`) is picked up automatically by `scripts/ci/treasury-check-constraints-mysql`'s step 3 full-directory scan — no workflow/script change needed. It does not depend on being the class-wide first test in the whole job (PHPUnit's default execution order runs test *classes* in the order the runner discovers files, and within a class in declaration order) — it only requires being first *within this class*, which is guaranteed by the `test_a_`/`test_b_` naming and this class not sharing `TestCase::$coldStartProbe` state with any other class. Whether `zena_roles` is already-existing by the time THIS class runs (because an earlier class in the same job already created it) is exactly what `test_a_`'s own assertion checks and fails loudly on if untrue — see Task 4 Step 3.

- [ ] **Step 2: Commit**

```bash
git add tests/Unit/Migrations/Treasury/TreasuryTransactionIsolationColdStartTest.php
git commit -m "test(GAP-040): add Treasury-surface cold-start transaction-isolation proof"
```

- [ ] **Step 3: Push and verify on real CI**

```bash
git push origin feature/GAP-040-mysql-transaction-isolation
gh run list --branch feature/GAP-040-mysql-transaction-isolation --workflow=automated-testing.yml --limit 3 --json databaseId,status,conclusion
```

Wait for the `treasury-check-constraints-mysql` job. Expected: `success`, with `TreasuryTransactionIsolationColdStartTest`'s two methods passing.

**If `test_a_...` fails specifically on `table_existed_before_bootstrap` being `true`** (i.e., an earlier Treasury test class already created `zena_roles` first): this is expected and acceptable — it means this specific class did not happen to be first, but the *existence guard* worked correctly (no second bootstrap, no second DDL) and, more importantly, Task 3's fix means whichever class *was* first also went through the isolated-connection path, so isolation was never at risk for anyone. In that case, do not treat this as a failure of the fix — record in the plan's execution notes which class in this job was actually first, and note that the invariant was still proven (just by a different class in the group). If genuinely no class in this job's run order captures the cold-start moment (unlikely, since this test class was deliberately alphabetically early — `Treasury...ColdStart` sorts among the T's, and directory scans are typically alphabetical — but must be empirically confirmed, not assumed), add a second, differently-named probe class or adjust ordering (e.g., a `0-` prefix directory/filename trick already avoided here on purpose — prefer confirming empirically before adding one).

---

### Task 5: E2E surface companion test

**Files:**
- Create: `tests/E2E/TransactionIsolationColdStartTest.php`

**Interfaces:**
- Consumes: `GAP040ColdStartTransactionIsolationAssertions` (Task 2).

- [ ] **Step 1: Write the test**

Create `tests/E2E/TransactionIsolationColdStartTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\E2E;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

class TransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_cold_start_bootstrap_does_not_break_transaction_isolation(): void
    {
        $this->assertColdStartInvariantHeld();
        $this->writeMarkerRow();
    }

    public function test_b_rolled_back_write_is_absent_via_independent_connection(): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection();
    }
}
```

Picked up automatically by `a11y-perf-testing.yml`'s `e2e-tests` job (`php artisan test tests/E2E --stop-on-failure`) — no workflow change needed. **Note:** per Gate 1 evidence, `a11y-perf-testing.yml` only runs on `schedule`/`workflow_dispatch`, not on push/PR — it will not run automatically on this branch's push. Trigger it explicitly.

- [ ] **Step 2: Commit**

```bash
git add tests/E2E/TransactionIsolationColdStartTest.php
git commit -m "test(GAP-040): add E2E-surface cold-start transaction-isolation proof"
git push origin feature/GAP-040-mysql-transaction-isolation
```

- [ ] **Step 3: Manually trigger `a11y-perf-testing.yml` and verify**

```bash
gh workflow run a11y-perf-testing.yml --ref feature/GAP-040-mysql-transaction-isolation
gh run list --branch feature/GAP-040-mysql-transaction-isolation --workflow=a11y-perf-testing.yml --limit 1 --json databaseId,status,conclusion
```

Wait for the `e2e-tests` job specifically (the workflow also runs `accessibility-tests`, `performance-budget`, `performance-heavy`, `lighthouse-ci` — none of those are relevant to GAP-040 and their outcome, including GAP-041's already-known zero-test issue on `performance-budget`/`performance-heavy`, is not this task's concern; do not fix or investigate them here). Expected: `e2e-tests` job `success`, with `TransactionIsolationColdStartTest`'s two methods passing.

---

### Task 6: Documents surface companion test + minimal CI invocation change

**Files:**
- Create: `tests/Feature/Documents/TransactionIsolationColdStartTest.php`
- Modify: `.github/workflows/ci-cd.yml` (the `test` job's "Prove GAP-032 migrations on MySQL 8.0" step)

**Interfaces:**
- Consumes: `GAP040ColdStartTransactionIsolationAssertions` (Task 2).

This is the one surface selected by an exact file path, not a group or directory scan — `ci-cd.yml`'s GAP-032 step runs `./vendor/bin/phpunit --configuration phpunit.mysql.xml tests/Feature/Documents/DocumentStatusMigrationTest.php`. A minimal, one-line CI invocation change (adding the new file path) is explicitly authorized by the Owner for exactly this purpose.

- [ ] **Step 1: Write the test**

Create `tests/Feature/Documents/TransactionIsolationColdStartTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Documents;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\GAP040ColdStartTransactionIsolationAssertions;
use Tests\TestCase;

class TransactionIsolationColdStartTest extends TestCase
{
    use RefreshDatabase;
    use GAP040ColdStartTransactionIsolationAssertions;

    protected function setUp(): void
    {
        self::$coldStartProbe = [];
        parent::setUp();
    }

    protected function tearDown(): void
    {
        self::$coldStartProbe = null;
        parent::tearDown();
    }

    public function test_a_cold_start_bootstrap_does_not_break_transaction_isolation(): void
    {
        $this->assertColdStartInvariantHeld();
        $this->writeMarkerRow();
    }

    public function test_b_rolled_back_write_is_absent_via_independent_connection(): void
    {
        $this->assertMarkerRowAbsentViaIndependentConnection();
    }
}
```

- [ ] **Step 2: Add the one-line CI invocation change**

Edit `.github/workflows/ci-cd.yml`, in the `test` job's "Prove GAP-032 migrations on MySQL 8.0" step (currently):

```yaml
      run: |
        php artisan migrate:fresh --force
        ./vendor/bin/phpunit --configuration phpunit.mysql.xml tests/Feature/Documents/DocumentStatusMigrationTest.php
```

Change to:

```yaml
      run: |
        php artisan migrate:fresh --force
        ./vendor/bin/phpunit --configuration phpunit.mysql.xml tests/Feature/Documents/DocumentStatusMigrationTest.php tests/Feature/Documents/TransactionIsolationColdStartTest.php
```

This is the only workflow-file change in this plan, and it does exactly what the Owner authorized: exercises the cold-start proof through the 5th approved surface, nothing else.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Documents/TransactionIsolationColdStartTest.php .github/workflows/ci-cd.yml
git commit -m "test(GAP-040): add Documents-surface cold-start proof, wire into ci-cd.yml's GAP-032 MySQL step"
```

- [ ] **Step 4: Push and verify**

```bash
git push origin feature/GAP-040-mysql-transaction-isolation
gh run list --branch feature/GAP-040-mysql-transaction-isolation --workflow="CI/CD Pipeline" --limit 1 --json databaseId,status,conclusion
```

(Confirm the exact workflow display name via `gh run list --branch feature/GAP-040-mysql-transaction-isolation --limit 10` if `"CI/CD Pipeline"` doesn't match — use whatever `name:` `ci-cd.yml` declares.)

Wait for the `test` job's "Prove GAP-032 migrations on MySQL 8.0" step. Expected: `success`, both files' tests passing.

---

### Task 7: All 5 surfaces green simultaneously (whole-branch cold-start confirmation)

**Files:** none (verification only).

- [ ] **Step 1: Trigger every surface on the current head in one pass**

```bash
git rev-parse HEAD
gh workflow run a11y-perf-testing.yml --ref feature/GAP-040-mysql-transaction-isolation
git push origin feature/GAP-040-mysql-transaction-isolation --force-with-lease  # only if a rebase was needed; otherwise the existing push from Task 6 already triggers routes-guardrails.yml, automated-testing.yml, and ci-cd.yml
```

- [ ] **Step 2: Collect and record all 5 results against the exact head SHA**

```bash
gh run list --branch feature/GAP-040-mysql-transaction-isolation --limit 10 --json databaseId,workflowName,status,conclusion,headSha
```

Confirm, on the same head SHA: `routes-guardrails.yml` success, `automated-testing.yml`'s `zena-invariants-mysql` success, `automated-testing.yml`'s `treasury-check-constraints-mysql` success, `a11y-perf-testing.yml`'s `e2e-tests` success, `ci-cd.yml`'s `test` job success. Record the exact head SHA and these 5 run URLs — this is the evidence Gate 3 will need.

If any is red for a substantive reason (not a known-unrelated flake like the pre-existing browser-tests segfake documented elsewhere in this repo's history): STOP, do not proceed to Task 8, return to systematic-debugging on that specific surface.

---

### Task 8: RBAC compatibility regression check

**Files:** none (verification only) — confirms the one already-known RBAC-dependent test (`ZenaAuthFlowInvariantTest`, part of `zena-invariants-mysql`) still passes, proving the fix did not remove `zena_roles`/`zena_permissions` availability.

- [ ] **Step 1: Confirm from the Task 7 `zena-invariants-mysql` run log**

```bash
gh run view <zena-invariants-mysql-run-id> --log | grep -A3 "ZenaAuthFlowInvariantTest"
```

Expected: all its test methods pass (same outcome as before this branch's changes — this class is not new, it is pre-existing coverage that must not regress). If it fails only after this branch's changes and passed before, this is a direct violation of the Gate 2 boundary (RBAC compatibility must be preserved) — STOP and return to systematic-debugging, do not proceed.

---

### Task 9: SQLite regression suite (local)

**Files:** none (verification only) — this is the one task that runs fully locally, since SQLite is available in this environment.

- [ ] **Step 1: Run the full default (SQLite) suite locally**

```bash
./vendor/bin/phpunit --testsuite=Unit,Feature,Integration
```

Expected: same pass/fail/skip counts as a baseline run of the same command against `origin/main` before this branch's changes (capture that baseline once, before Task 1, if not already known from recent CI history — `gh run list --branch main --workflow=automated-testing.yml --limit 1` for the `unit-tests`/`feature-tests`/`integration-tests` job summaries is an acceptable baseline source if a fresh local `origin/main` run isn't practical).

- [ ] **Step 2: If any new failure appears, isolate whether it's caused by this branch's changes**

```bash
git stash
./vendor/bin/phpunit --filter <FailingTestName>
git stash pop
```

If the same test fails identically against unmodified `origin/main`, it's a pre-existing, unrelated issue — do not fix it under GAP-040. If it only fails on this branch, that's a real regression — STOP and return to systematic-debugging before proceeding.

---

### Task 10: Final whole-branch review

**Files:** none.

- [ ] **Step 1: Re-read every changed file against the approved Gate 2 spec's non-goals**

Confirm the final diff touches only: `tests/TestCase.php`, `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php`, 4 new test files, and the single authorized line in `.github/workflows/ci-cd.yml`. No migration, no `app/Services/RBACManager.php`, no `src/RBAC/**`, no `config/database.php` (unless Task 3's runtime-registration mechanism was empirically shown infeasible and the documented fallback was used instead — if so, confirm that finding is written up for Gate 3, not silently present).

```bash
git diff origin/main...feature/GAP-040-mysql-transaction-isolation --stat
```

- [ ] **Step 2: Confirm GAP-041/GAP-042 untouched**

```bash
git diff origin/main...feature/GAP-040-mysql-transaction-isolation -- OPERATIONAL_GAP_REGISTER.md
```

Expected: empty (this branch does not touch the register at all — GAP-041/GAP-042 registration lives in PR #270, independent of this branch).

- [ ] **Step 3: Summarize for Gate 3 preparation**

Compile: exact final head SHA, the 5 CI run URLs from Task 7, confirmation from Task 8 and Task 9, and whether Task 3's primary (runtime-registration) or fallback (config) mechanism was used. This is the technical evidence Gate 3 will need — do not draft the Gate 3 packet itself as part of this plan (that is a separate, later step per the Owner's Gate 2 approval, only after all of the above is green).
