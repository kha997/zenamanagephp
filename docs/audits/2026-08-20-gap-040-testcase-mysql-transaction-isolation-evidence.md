# GAP-040 — `TestCase::ensureSqliteZenaRbacTables()` DDL Defeats MySQL Transaction Isolation: Gate 1 Evidence (v2, corrected)

> Fact-finding pass for Gate 1. Records **facts** only, verified against exact `origin/main` head `87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`. No solutions recommended, no code changed to produce this evidence.

**v2 supersedes v1 of this document.** v1's affected-surface inventory was built by mistake from a stale local checkout (a different branch, head `2bac5a5c`, still present in the repo root working directory alongside the correctly-pinned worktree used for this document) instead of the exact GAP-040 worktree pinned to `87a4307f`. That stale checkout predated several post-GAP-039 workflow changes — most importantly, `routes-guardrails.yml` no longer resolves to SQLite; it now explicitly exports `ZENA_INVARIANTS_DB=mysql` and runs a fail-closed MySQL preflight before `php artisan test --group=mysql-parity`. v1's structural defect analysis (§1-§3 below, code line numbers) was independently re-checked against the correct worktree and is unchanged — only the affected-surface inventory (§4) was wrong and is rebuilt here from scratch, directly against `origin/main`, not by re-copying GAP-039's own now-superseded pre-implementation Gate-1 inventory.

## Attestation

- **Work ID:** GAP-040.
- **Baseline:** `origin/main @ 87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`, verified this pass via `git worktree` pinned to that commit (`.worktrees/GAP-040-gate1-prep`, confirmed `git merge-base --is-ancestor 87a4307f HEAD`), with every command in this document run from inside that worktree — not the repo root, which remains on an unrelated branch for other in-progress work.
- **Method:** (a) direct read of `tests/TestCase.php`, the `RefreshDatabase` trait source and its invocation path; (b) direct read of `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php`; (c) a live, code-unmodified `php -r` reproduction of `tests/bootstrap.php`'s env-override mechanism; (d) **exhaustive, individually-verified re-inspection of every workflow file and every `scripts/ci/*-mysql` script on the exact pinned worktree** for `ZENA_INVARIANTS_DB` exports, `--group` selectors, and file/directory test targets; (e) for every test class or method reachable by a genuinely-MySQL path, direct grep of that specific file for `use RefreshDatabase` and its `@group` annotation(s), not an assumption from a job's name; (f) direct read of PHPUnit 11.5.56's actual group-filter merge logic (`vendor/phpunit/phpunit/src/TextUI/Configuration/Merger.php:720-725`) to confirm whether a CLI `--group X` overrides an XML-level `<exclude><group>X</group></exclude>` (it does, via `array_diff($excludeGroups, $groups)` — vendor/ is gitignored, environment-local, and not itself branch-dependent, so this is safe to consult regardless of repo-root branch state); (g) MySQL's documented implicit-commit-on-DDL statement list, cited as established engine behavior, not re-derived (a local live-MySQL reproduction was attempted and is discussed, not asserted, in §6).

---

## 1. The structural defect (unchanged from v1, re-verified against the correct worktree)

`tests/TestCase.php:81-93` — `setUp()`:

```php
protected function setUp(): void
{
    $this->prepareSqliteDatabaseFile();
    parent::setUp();                          // RefreshDatabase trait's hook runs HERE, if the test class uses it
    $this->withoutVite();
    $this->ensureTestingSchema();
    $this->registerArrayBindingWatch();
    $this->ensureSqliteSubmittalsTable();      // line 88 -> calls ensureSqliteZenaRbacTables() unconditionally (line 200)
    $this->ensureSqliteDocumentsBackupTable();
    ...
}
```

`ensureSqliteSubmittalsTable()` (`tests/TestCase.php:163-201`) guards its own table creation with `if (!Schema::hasTable('submittals'))`, but its last line, `$this->ensureSqliteZenaRbacTables();` (line 200), has no guard of any kind.

`ensureSqliteZenaRbacTables()` (`tests/TestCase.php:203-242`):

```php
private function ensureSqliteZenaRbacTables(): void
{
    Schema::dropIfExists('zena_role_permissions');
    Schema::dropIfExists('zena_user_roles');
    Schema::dropIfExists('zena_roles');
    Schema::dropIfExists('zena_permissions');

    Schema::create('zena_permissions', function (Blueprint $table) { ... });
    Schema::create('zena_roles', function (Blueprint $table) { ... });
    Schema::create('zena_role_permissions', function (Blueprint $table) { ... });
    Schema::create('zena_user_roles', function (Blueprint $table) { ... });
}
```

Eight DDL statements, executed **every `setUp()`, on every driver, unconditionally**. Its two siblings are guarded: `ensureSqliteDocumentsBackupTable()` (line 244-279) has both a driver guard (`if (env('DB_CONNECTION') !== 'sqlite') return;`) and an existence guard; `ensureSqliteSubmittalsTable()` itself has an existence guard. `ensureSqliteZenaRbacTables()` has neither.

## 2. Why the missing existence guard is not cosmetic

`database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php:16-40` **renames** (not copies) `zena_roles→roles`, `zena_permissions→permissions`, `zena_role_permissions→role_permissions`, `zena_user_roles→user_roles` during every `migrate:fresh` (which `TestCase::ensureTestingSchema()` runs every `setUp()`). After that, no table is literally named `zena_roles`/etc. — they were renamed away in the same run. So `Schema::create(...)` in `ensureSqliteZenaRbacTables()` is not a rare fallback; it is the unconditional steady state, every test, every driver.

## 3. The transaction-isolation mechanism

`Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle::setUpTraits()` runs synchronously inside `parent::setUp()` (`tests/TestCase.php:84`), before that call returns. For any test class `use`-ing `RefreshDatabase`, this opens a real DB transaction (`RefreshDatabase::beginDatabaseTransaction()`) and registers a teardown `rollBack()`. The very next lines in `tests/TestCase.php`'s own `setUp()` (86-92) then run `ensureSqliteZenaRbacTables()`'s 8 unguarded DDL statements **inside that already-open transaction**. On MySQL/InnoDB, `CREATE TABLE`/`DROP TABLE` are documented implicit-commit statements. If that holds here, the isolating transaction is committed away mid-`setUp()`, and the teardown `rollBack()` has nothing left to undo.

Independently re-verified this pass, isolated reproduction, no code changed:
```
$ php -r 'putenv("DB_CONNECTION=mysql"); ...; require "tests/bootstrap.php"; echo getenv("DB_CONNECTION");'
BEFORE bootstrap.php: DB_CONNECTION=mysql
AFTER bootstrap.php (ZENA_INVARIANTS_DB unset): DB_CONNECTION=sqlite
```
Confirms `tests/bootstrap.php`'s gate: a job's `DB_CONNECTION=mysql` only survives into the actual PHPUnit process when `ZENA_INVARIANTS_DB=mysql` is also exported. This gate is what §4 traces per CI path.

## 4. Affected-surface matrix — rebuilt directly from `origin/main @ 87a4307f`

Every workflow file and every `scripts/ci/*-mysql` script was individually re-inspected on the correctly-pinned worktree. Legend: **Real MySQL** = `ZENA_INVARIANTS_DB=mysql` confirmed exported for that exact test invocation (job env, step env, or inside the invoked script) and a fail-closed MySQL preflight runs; **RefreshDatabase** = the specific test class(es)/method(s) actually selected by that job's command use the trait, confirmed by direct grep of each file, not inferred from the job's name or purpose.

| # | Workflow / script | Job / step | Command | Real MySQL? | Test(s) actually selected | `use RefreshDatabase`? | Exposure |
|---|---|---|---|---|---|---|---|
| 1 | `routes-guardrails.yml` | `test-routes-guardrails` → "Run MySQL-parity tests" | `php artisan test --group=mysql-parity` | **Yes** — `ZENA_INVARIANTS_DB: mysql` job-step env, `zena_mysql_ensure_connection`/`zena_mysql_preflight_connection` fail-closed preflight | `TenantIsolationProjectsTest` (class `@group mysql-parity`), `DatabaseConstraintsTest` (method-level `@group mysql-parity`) | **Yes**, both classes | **DIRECTLY EXPOSED** — primary defect |
| 2 | `automated-testing.yml` → `scripts/ci/zena-invariants-mysql` | `zena-invariants-mysql` | `php artisan test --group=zena-invariants` | **Yes** | 17 test classes under `--group=zena-invariants`; 12 confirmed `use RefreshDatabase`: `ZenaListContractInvariantTest`, `ZenaAuditSchemaInvariantTest`, `ZenaSeederWiringInvariantTest`, `ZenaMySqlMigrationRiskInvariantTest`, `ZenaAuditPiiInvariantTest`, `ZenaApiContractPhase2InvariantTest`, `ZenaAuditInvariantTest`, `ZenaSeedParityInvariantTest`, `ZenaAuthFlowInvariantTest`, `ZenaRbacTenantSmokeTest`, `ZenaRbacPermissionStoreInvariantTest`, `ZenaErrorEnvelopeInvariantTest` | **Yes**, 12 of 17 | **DIRECTLY EXPOSED** |
| 3 | `automated-testing.yml` → `scripts/ci/treasury-check-constraints-mysql` | `treasury-check-constraints-mysql`, step 3 ("Full Treasury suite") | `./vendor/bin/phpunit tests/Unit/Migrations/Treasury tests/Unit/Models/Treasury` | **Yes** — script comment: "the default connection here genuinely is mysql, since ZENA_INVARIANTS_DB=mysql" | 19 files under those two directories; 16 confirmed `use RefreshDatabase` (all `Treasury*SchemaTest`, `TreasuryCheckConstraintIntrospectionTest`, `TreasuryNativeCheckConstraintsTest`, `EnforcesRowInvariantsTest`) | **Yes**, 16 of 19 | **DIRECTLY EXPOSED** — largest single surface found; script also notes step 3's failures are non-gating (informational), which does not change exposure, only CI blast radius |
| 4 | same script, step 2 | `treasury-check-constraints-mysql` | `./vendor/bin/phpunit tests/Unit/Migrations/Treasury/TreasuryCheckConstraintIntrospectionTest.php --filter test_mysql_check_constraints_are_visible_in_information_schema` | **Yes** | `TreasuryCheckConstraintIntrospectionTest` | **Yes** | **DIRECTLY EXPOSED** (subset of #3, called out because it's gating, unlike step 3) |
| 5 | same script, step 1 | `treasury-check-constraints-mysql` | `./vendor/bin/phpunit tests/Unit/Migrations/Treasury/TreasuryNativeCheckConstraintsMysqlTest.php` | **Yes** | `TreasuryNativeCheckConstraintsMysqlTest` | **No** | Runs the unguarded DDL every `setUp()` on a real MySQL connection, but no `RefreshDatabase` transaction is open to defeat — related schema churn, not the primary isolation-defeat mechanism |
| 6 | `automated-testing.yml` → `scripts/ci/rfi-escalation-concurrency-mysql` | `rfi-escalation-concurrency-mysql` | `./vendor/bin/phpunit tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php` | **Yes** | `RfiEscalationConcurrencyTest` | **No** (intentionally — spawns separate OS processes for real concurrent writes, incompatible with a wrapping transaction) | Related schema churn only, not the primary defect |
| 7 | `automated-testing.yml` → `scripts/ci/document-workflow-concurrency-mysql` | `document-workflow-concurrency-mysql` | `./vendor/bin/phpunit tests/Feature/Concurrency/DocumentWorkflowConcurrencyTest.php` | **Yes** | `DocumentWorkflowConcurrencyTest` | **No** (same reason as #6) | Related schema churn only |
| 8 | `a11y-perf-testing.yml` | `e2e-tests` | `php artisan test tests/E2E --stop-on-failure` | **Yes** — job-level `ZENA_INVARIANTS_DB: mysql`, fail-closed preflight step | `CriticalUserFlowsE2ETest`, `DashboardE2ETest` (no class-level exclude group) | **Yes**, both classes | **DIRECTLY EXPOSED** |
| 9 | `ci-cd.yml` | `test` → "Prove GAP-032 migrations on MySQL 8.0" | `./vendor/bin/phpunit --configuration phpunit.mysql.xml tests/Feature/Documents/DocumentStatusMigrationTest.php` | **Yes** — step-level `ZENA_INVARIANTS_DB: mysql`, separate `phpunit.mysql.xml` config (still `bootstrap="tests/bootstrap.php"`, so the same gate applies) | `DocumentStatusMigrationTest` | **Yes** | **DIRECTLY EXPOSED** |
| 10 | `automated-testing.yml` | `performance-tests` (matrix: `PerformanceMonitoringTest.php`, `DashboardPerformanceTest.php`) | `php artisan test "${{ matrix.perf_file }}"` (no `--group` flag) | **Yes** — job-level `ZENA_INVARIANTS_DB: mysql` | Both classes are class-level `@group performance`; `phpunit.xml`'s default `<exclude><group>performance</group></exclude>` applies because no `--group` flag is passed here to cancel it (PHPUnit 11.5.56's `Merger.php:725` only cancels an exclude for groups also present in `--group`) | **Yes**, both classes, **but 0 tests actually execute** under this exact command | **NOT currently exposed** (no test runs at all) — but this is itself a separate, real defect: a fully real-MySQL job with fail-closed intent that silently runs zero tests. One `--group` config fix away from becoming directly exposed. Flagged for Gate 2 scoping, not claimed as GAP-040 exposure today. |
| 11 | `a11y-perf-testing.yml` | `performance-budget` | `./vendor/bin/phpunit -c phpunit.xml --group performance_budget` | **Yes** — job-level `ZENA_INVARIANTS_DB: mysql`, fail-closed preflight | No test in the repo is tagged literally `performance_budget` (only generic `@group performance` on `PerformanceMonitoringTest`/`DashboardPerformanceTest`) — 0 tests match | N/A — 0 tests selected | **NOT currently exposed** — same class of pre-existing group-naming defect as #10 |
| 12 | `a11y-perf-testing.yml` | `performance-heavy` | `./vendor/bin/phpunit -c phpunit.xml --group performance_heavy` | **Yes** | No test tagged `performance_heavy` — 0 tests match | N/A | **NOT currently exposed** — same class of defect as #10/#11 |
| 13 | `automated-testing.yml` | `unit-tests`, `feature-tests`, `api-tests-fast`, `api-tests-slow`, `integration-tests` | various `php artisan test ...` | **No** — `DB_CONNECTION: mysql` set at job level but `ZENA_INVARIANTS_DB` never exported for any of these 5 jobs (confirmed via `awk` scan of every job block in the file) | — | — | Genuinely SQLite, not affected |
| 14 | `button-tests.yml`, `production.yml` | all jobs | various | **No** — `grep -c ZENA_INVARIANTS_DB` returns 0 for both files | — | — | Genuinely SQLite, not affected |
| 15 | `nightly-matrix.yml`, `ci-cd-code-quality-debug.yml` | all jobs | various | **No** — `grep -c ZENA_INVARIANTS_DB` returns 0 for both files; these already honestly declare SQLite | — | — | Not affected, not misconfigured |

**Summary:** 5 genuinely-real-MySQL, `RefreshDatabase`-using surfaces are directly exposed to the primary defect (#1, #2, #3/#4, #8, #9) — spanning `routes-guardrails.yml`, `automated-testing.yml`'s `zena-invariants-mysql` and `treasury-check-constraints-mysql` jobs, `a11y-perf-testing.yml`'s `e2e-tests`, and `ci-cd.yml`'s GAP-032 MySQL proof step (the primary PR-gating pipeline). 3 real-MySQL surfaces (#5, #6, #7) run the same unguarded DDL but have no open `RefreshDatabase` transaction to defeat — related, lower-severity schema churn. 3 real-MySQL, fail-closed-preflight surfaces (#10, #11, #12) are currently inert (0 tests selected) due to an unrelated group-naming mismatch between the CI command and the test annotations — not exposed today, but not truly validated either, and one label fix away from exposure.

## 5. Corrections to specific prior claims

- **"Routes Guardrails MySQL-parity resolves to SQLite"** — **false** as of this baseline. `routes-guardrails.yml` explicitly exports `ZENA_INVARIANTS_DB=mysql` for its MySQL-parity step and runs the fail-closed preflight before `php artisan test --group=mysql-parity`, which includes `TenantIsolationProjectsTest` (`@group mysql-parity`, `use RefreshDatabase`). This was true of the pre-GAP-039 topology (and is what GAP-039's own Gate-1 evidence, written before GAP-039's implementation, correctly recorded for that earlier baseline) but is stale for the current, post-GAP-039-implementation `main`.
- **"performance/e2e jobs categorically resolve to SQLite"** — **false** as a category. `e2e-tests` (#8) is real MySQL and directly exposed. `performance-tests`/`performance-budget`/`performance-heavy` (#10-#12) are real MySQL but currently select 0 tests — not "SQLite", inert for an unrelated reason.
- **"Only `zena-invariants-mysql` can be affected"** — **false**. At minimum 5 distinct real-MySQL, `RefreshDatabase`-using surfaces are affected (§4).
- **"`scripts/ci/lint-mysql-claim-truthfulness.php` was never merged / is absent on `main`"** — **false**. It exists at `scripts/ci/lint-mysql-claim-truthfulness.php` (plus its test file and fixtures under `scripts/ci/__fixtures__/mysql-claim-truthfulness/`) on this exact baseline. The earlier claim was a direct consequence of the same stale-checkout error described above.
- **"4 `scripts/ci/*-mysql` scripts"** — this part of the register's original wording is correct on this baseline: `document-workflow-concurrency-mysql`, `rfi-escalation-concurrency-mysql`, `treasury-check-constraints-mysql`, `zena-invariants-mysql`.

## 6. Runtime reproduction: what was and was not demonstrated

- **Demonstrated (live, this pass):** the `tests/bootstrap.php` env-override gate (§3's `php -r` reproduction).
- **Demonstrated (static, high-confidence):** PHPUnit 11.5.56's actual group-filter precedence, read directly from `vendor/phpunit/phpunit/src/TextUI/Configuration/Merger.php:720-725` — this is what grounds §4's "0 tests selected" conclusions for rows #10-#12, rather than a guess about PHPUnit's behavior.
- **Not demonstrated live:** the MySQL implicit-commit-defeats-transaction-rollback mechanism itself, end-to-end, against a real MySQL/MariaDB server. A local reproduction was attempted using this machine's bundled MariaDB and failed on a filesystem permission error before a server process could start. No elevated or system-level modification was attempted to work around that, in keeping with this being unnecessary to establish the finding: the structural trace (§1-§3) is unambiguous on its own, and MySQL's implicit-commit-on-DDL behavior is documented, versioned engine behavior, not something specific to this repo that would need re-proving here.
- This is recorded as a **demonstrated structural defect, on a precisely re-verified set of real-MySQL/RefreshDatabase-using CI surfaces, with a well-documented but not-live-reproduced runtime mechanism** — not as an empirically observed data-leakage incident. No test failure attributable to this defect has been observed in current CI.

## 7. Relationship to GAP-039

Unchanged from v1: discovered during GAP-039's final whole-branch review. GAP-039's own change (`tests/TestCase.php:106`, narrowing when `RefreshDatabaseState::$migrated` gets reset) removed an incidental full-schema reset that was previously masking GAP-040's pre-existing defect on MySQL-parity paths. GAP-039 increased real exposure; it did not create the defect, which predates it and is unchanged by it.

## 8. Summary of facts

1. `TestCase::ensureSqliteZenaRbacTables()` (`tests/TestCase.php:203-242`) executes 8 unguarded DDL statements on every `setUp()`, every driver — confirmed, unchanged from v1.
2. These `CREATE TABLE` calls are not a rare fallback: the tables never pre-exist under their `zena_` names after any fresh migration, because a rename migration always relocates them first.
3. For any `RefreshDatabase`-using test class, this DDL runs inside an already-open transaction, confirmed via Laravel framework source.
4. On MySQL/InnoDB, `CREATE`/`DROP TABLE` are documented implicit-commit statements; if that holds here (not live-reproduced in this environment, §6), the isolating transaction is silently committed away mid-`setUp()`.
5. **Corrected this pass:** at minimum 5 distinct real-MySQL, `RefreshDatabase`-using CI surfaces are directly exposed — `routes-guardrails.yml`'s MySQL-parity step, `automated-testing.yml`'s `zena-invariants-mysql` and `treasury-check-constraints-mysql` jobs, `a11y-perf-testing.yml`'s `e2e-tests`, and `ci-cd.yml`'s GAP-032 MySQL proof step (part of the primary PR-gating pipeline). This is materially broader than v1's "only `zena-invariants-mysql`" claim, which was built from a stale, pre-GAP-039-implementation checkout.
6. 3 further real-MySQL surfaces (the two concurrency scripts, plus one step of the Treasury script) run the same unguarded DDL without an open `RefreshDatabase` transaction — related schema churn, not the primary isolation-defeat mechanism.
7. 3 real-MySQL, fail-closed-preflight surfaces (`performance-tests`, `performance-budget`, `performance-heavy`) currently execute zero tests due to an unrelated group-naming mismatch — not exposed today, but not meaningfully validated either; flagged for Gate 2 scoping as a distinct, adjacent defect.
8. No live data-leakage incident has been observed in current CI; this remains a structural defect with a well-documented but not-live-reproduced runtime consequence.
