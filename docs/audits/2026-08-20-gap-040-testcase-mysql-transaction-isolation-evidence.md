# GAP-040 — `TestCase::ensureSqliteZenaRbacTables()` DDL Defeats MySQL Transaction Isolation: Gate 1 Evidence

> Fact-finding pass for Gate 1. Records **facts** only, verified against exact `origin/main` head `87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`. No solutions recommended, no code changed to produce this evidence. This document also corrects one inaccuracy inherited from `OPERATIONAL_GAP_REGISTER.md`'s GAP-040 row (§4) rather than restating it uncritically.

## Attestation

- **Work ID:** GAP-040.
- **Baseline:** `origin/main @ 87a4307fdcf8117d8cac4b11c2cb27cb637ada5a` (confirmed via `git merge-base --is-ancestor` and file-content diff before writing this document).
- **Method:** (a) static read of `tests/TestCase.php`, the RefreshDatabase trait source (`vendor/laravel/framework/.../RefreshDatabase.php`) and its invocation path (`Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle::setUpTraits()`); (b) static read of `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php`; (c) a live, isolated PHP reproduction of the `tests/bootstrap.php` env-override mechanism (no code changed) to establish which CI paths actually reach a real MySQL connection; (d) cross-reference against GAP-039's own Gate-1 evidence (`docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md`), which independently enumerated every workflow's true DB backend and is treated here as the authoritative inventory rather than re-deriving it from scratch; (e) MySQL's documented list of statements that cause an implicit `COMMIT` (`CREATE TABLE`, `DROP TABLE` are both on that list for InnoDB/MyISAM) — cited as an established database-engine fact, not re-derived, since a live MySQL server was not available in this environment (§5 explains why and what was reproduced instead).

---

## 1. The structural defect (confirmed by direct code read)

`tests/TestCase.php:81-93` — `setUp()`:

```php
protected function setUp(): void
{
    $this->prepareSqliteDatabaseFile();
    parent::setUp();                          // <-- RefreshDatabase trait's hook runs HERE, if the concrete test class uses it
    $this->withoutVite();
    $this->ensureTestingSchema();
    $this->registerArrayBindingWatch();
    $this->ensureSqliteSubmittalsTable();      // <-- line 88, calls ensureSqliteZenaRbacTables() unconditionally (line 200)
    $this->ensureSqliteDocumentsBackupTable();
    ...
}
```

`ensureSqliteSubmittalsTable()` (`tests/TestCase.php:163-201`) guards its own table creation with `if (!Schema::hasTable('submittals'))`, but its last line, `$this->ensureSqliteZenaRbacTables();` (line 200), is called with **no guard of any kind** — no driver check, no existence check.

`ensureSqliteZenaRbacTables()` (`tests/TestCase.php:203-242`) itself:

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

Eight DDL statements (4×`dropIfExists`, 4×`create`), executed **every `setUp()`, on every driver, unconditionally**.

Its two sibling helpers, by contrast, are guarded:
- `ensureSqliteDocumentsBackupTable()` (line 244-279): `if (env('DB_CONNECTION') !== 'sqlite') { return; }` (driver guard) **and** `if (Schema::hasTable('documents_backup')) { return; }` (existence guard).
- `ensureSqliteSubmittalsTable()` itself (line 163-165): `if (!Schema::hasTable('submittals'))` (existence guard).

`ensureSqliteZenaRbacTables()` has neither. This asymmetry with its two siblings is a direct code fact, not an inference.

## 2. Why the existence guard's absence is not cosmetic: the tables never pre-exist

`database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php:16-40` renames (not copies) `zena_roles→roles`, `zena_permissions→permissions`, `zena_role_permissions→role_permissions`, `zena_user_roles→user_roles` during every `migrate:fresh`:

```php
foreach ($tableMappings as $oldTable => $newTable) {
    if (Schema::hasTable($oldTable) && !Schema::hasTable($newTable)) {
        Schema::rename($oldTable, $newTable);
    }
}
```

`TestCase::ensureTestingSchema()` (line 95-110) runs `Artisan::call('migrate:fresh', ...)` on every `setUp()` (subject to `RefreshDatabaseState::$migrated` gating on the SQLite-only path — see §3's note on this). After that fresh migration, no table is literally named `zena_roles`/`zena_permissions`/`zena_role_permissions`/`zena_user_roles` — they were renamed away in the same migration run. So `Schema::dropIfExists(...)` on those names is always a no-op, and `Schema::create(...)` always executes a genuine `CREATE TABLE`, every single test, on every driver. The 4 `Schema::create()` calls are not a rare fallback path; they are the unconditional steady state.

## 3. The transaction-isolation mechanism (confirmed by framework source read)

`Illuminate\Foundation\Testing\Concerns\InteractsWithTestCaseLifecycle::setUpTraits()` is invoked from `setUpTheTestEnvironment()`, which is what `Illuminate\Foundation\Testing\TestCase::setUp()` calls — i.e., it runs **synchronously inside** the `parent::setUp()` call at `tests/TestCase.php:84`, before that call returns. For any concrete test class that `use RefreshDatabase;`, `setUpTraits()` calls `$this->refreshDatabase()` → `refreshTestDatabase()` → `$this->beginDatabaseTransaction()` (`vendor/laravel/framework/.../RefreshDatabase.php:93,127-149`), which opens a real DB transaction on the connection and registers a `rollBack()` in `beforeApplicationDestroyed` to undo the test's writes at teardown.

This means, for any `RefreshDatabase`-using test class: by the time `parent::setUp()` returns at `tests/TestCase.php:84`, a transaction is already open. The very next lines in `tests/TestCase.php`'s own `setUp()` (86-92) then run `ensureSqliteZenaRbacTables()`'s 8 unguarded DDL statements **inside that already-open transaction**.

On MySQL (InnoDB), `CREATE TABLE` and `DROP TABLE` are both statements that cause an implicit `COMMIT` of any open transaction (documented MySQL server behavior, independent of Laravel/PHP — MySQL Reference Manual §"Statements That Cause an Implicit Commit"). If that holds here, the transaction `beginDatabaseTransaction()` opened is silently committed away mid-`setUp()`, and the `rollBack()` registered for teardown has nothing real left to roll back — any writes the test makes after that point are **not** undone, and are visible to the next test that reuses the same MySQL database/connection.

**Independently, empirically re-verified in this pass** (`tests/TestCase.php:106` comment already documents awareness of a related but distinct ordering concern):
```
$ php -r 'putenv("DB_CONNECTION=mysql"); ... require "tests/bootstrap.php"; echo getenv("DB_CONNECTION");'
BEFORE bootstrap.php: DB_CONNECTION=mysql
AFTER bootstrap.php (ZENA_INVARIANTS_DB unset): DB_CONNECTION=sqlite
```
This reproduction (isolated `php -r`, no code changed, no test framework involved) confirms `tests/bootstrap.php`'s override still behaves exactly as GAP-039's evidence described: `DB_CONNECTION` only survives as `mysql` into the actual PHPUnit process when `ZENA_INVARIANTS_DB=mysql` is also exported first. This is the gate that determines which CI paths can even reach the MySQL-implicit-commit mechanism described above — see §4.

## 4. Which CI surfaces are actually affected — correction to the register's evidence line

`OPERATIONAL_GAP_REGISTER.md`'s GAP-040 row lists affected surfaces as "routes-guardrails parity step, `performance-tests`, `e2e-tests`, và 4 `scripts/ci/*-mysql`". Verifying this against GAP-039's own Gate-1 evidence (`docs/audits/2026-08-18-gap-039-mysql-fk-testing-integrity-evidence.md`, §3a/§3b — the canonical, empirically-verified inventory of which CI jobs reach real MySQL) shows this list is **not accurate**:

- `routes-guardrails.yml`'s `test-routes-guardrails` job (which runs `TenantIsolationProjectsTest`, a `RefreshDatabase`-using test): GAP-039 §3a row 10 lists its effective backend as **SQLite**, because it never exports `ZENA_INVARIANTS_DB=mysql`. Independently re-confirmed in this pass: `grep -n ZENA_INVARIANTS_DB .github/workflows/routes-guardrails.yml` returns no match, and the bootstrap-override reproduction in §3 shows what that absence does to `DB_CONNECTION`. **This job does not reach real MySQL and is not an affected surface for GAP-040's specific mechanism** (implicit-commit-on-DDL is a MySQL/InnoDB behavior; it does not apply the same way to SQLite, whose `CREATE`/`DROP TABLE` do not force-commit an open transaction in the same manner).
- `automated-testing.yml`'s `performance-tests` and `a11y-perf-testing.yml`'s `e2e-tests`: GAP-039 §3a rows 16a/16b and 8 list both as effective-backend **SQLite**, same mechanism (no `ZENA_INVARIANTS_DB=mysql`).
- "4 `scripts/ci/*-mysql`": only **3** such scripts exist on `origin/main` (`ls scripts/ci/ | grep mysql` → `document-workflow-concurrency-mysql`, `rfi-escalation-concurrency-mysql`, `zena-invariants-mysql`). No fourth script exists. (A `scripts/ci/lint-mysql-claim-truthfulness.php` referenced in one of GAP-039's now-closed historical submission branches was never merged to `main` — confirmed absent via `find . -iname "*mysql-claim*"`.)

**Genuinely affected surface, per GAP-039's own inventory (§3b there) plus this pass's RefreshDatabase-usage check:**

`scripts/ci/zena-invariants-mysql` is the only one of the 3 real-MySQL scripts whose test group uses `RefreshDatabase`. It runs `php artisan test --group=zena-invariants`, which includes 17 test classes; 12 of them `use RefreshDatabase` (confirmed via `grep -c "use RefreshDatabase"` per file, this pass) and therefore open a transaction before `TestCase::setUp()`'s unguarded DDL runs inside it: `ZenaListContractInvariantTest`, `ZenaAuditSchemaInvariantTest`, `ZenaSeederWiringInvariantTest`, `ZenaMySqlMigrationRiskInvariantTest`, `ZenaAuditPiiInvariantTest`, `ZenaApiContractPhase2InvariantTest`, `ZenaAuditInvariantTest`, `ZenaSeedParityInvariantTest`, `ZenaAuthFlowInvariantTest`, `ZenaRbacTenantSmokeTest`, `ZenaRbacPermissionStoreInvariantTest`, `ZenaErrorEnvelopeInvariantTest`. Each of these tests, when run under `zena-invariants-mysql`, opens a real MySQL transaction that is then implicitly committed away by the unguarded DDL before the test body runs.

`document-workflow-concurrency-mysql` and `rfi-escalation-concurrency-mysql` also reach real MySQL and also run the same unguarded DDL every `setUp()` (their test classes, `DocumentWorkflowConcurrencyTest` / `RfiEscalationConcurrencyTest`, extend `Tests\TestCase` directly), but **neither class `use`s `RefreshDatabase`** — they intentionally avoid it because they spawn separate OS processes to exercise real concurrent writes, which a single wrapping transaction would prevent from being meaningful. For these two, the specific "defeats `RefreshDatabase`'s isolation" failure mode does not apply (there is no `RefreshDatabase` transaction to defeat), but the same unguarded DDL still executes needlessly on every `setUp()` of every subprocess — a related, lower-severity concern (repeated schema churn on shared tables across concurrent subprocesses) worth noting for Gate 2 scoping but distinct from the primary defect.

## 5. Runtime reproduction: what was and was not demonstrated

- **Demonstrated (live, this pass):** the `tests/bootstrap.php` env-override mechanism that gates which CI paths reach real MySQL at all (§3's `php -r` reproduction — actual PHP process, actual file, no MySQL required).
- **Not demonstrated live in this pass:** the MySQL implicit-commit-defeats-transaction-rollback mechanism itself, end-to-end, against a real MySQL/MariaDB server. A local reproduction was attempted using this machine's bundled MariaDB (`/Applications/XAMPP/xamppfiles/bin/mysql.server start`) but failed on a filesystem permission error (`Can't create/write to file '.../var/mysql/mariadb.err' (Errcode: 13)`) before a server process could start; resolving that would require elevated/administrative filesystem changes to a shared local XAMPP install outside this task's scope, so it was not pursued further.
- **What stands in its place:** (a) the structural code trace in §1-§3, which is unambiguous — an open transaction, followed by unconditional `CREATE`/`DROP TABLE` statements, with no guard preventing either; (b) MySQL's own documented behavior that these statement types force an implicit commit, cited as an established, versioned fact of the database engine (not Laravel- or repo-specific, and not something this repo's code could alter or opt out of); (c) the fact that the CI script most exposed to this (`zena-invariants-mysql`) already runs a `mysql_preflight_connection` fail-closed check confirming it is genuinely talking to MySQL before tests run (per GAP-039 §3b), which corroborates that this is not a hypothetical-only environment — the script's own design assumes and enforces a real MySQL connection for exactly the group of tests now shown to be exposed to this defect.

This is recorded as a **demonstrated structural defect with a well-documented, un-reproduced-live runtime mechanism**, not as an empirically-observed data-leakage incident. No test failure attributable to this defect has been observed in current CI (consistent with the register's own note: "CI hiện tại xanh, chưa có test nào phụ thuộc trật tự bị lộ").

## 6. Relationship to GAP-039

Discovered during GAP-039's final whole-branch review (2026-08-20), per the register. GAP-039's own change is orthogonal but relevant: before GAP-039, `RefreshDatabaseState::$migrated` was reset on every test on the MySQL-parity path too, causing `migrate:fresh` to re-run per test and incidentally reset state that a leaked transaction might otherwise have left dirty. GAP-039 introduced a driver-conditional exception (`tests/TestCase.php:106`, `if (getenv('ZENA_INVARIANTS_DB') !== 'mysql') { RefreshDatabaseState::$migrated = false; }`) specifically to stop re-running that migration's MySQL branch on the live connection every test. This narrowed exception is what removes the automatic full-schema reset that was previously (accidentally) cleaning up after GAP-040's pre-existing defect on the MySQL-parity path — GAP-039 increased real exposure of GAP-040's defect, it did not create the defect itself (the unguarded DDL in `ensureSqliteZenaRbacTables()` predates GAP-039 and is unchanged by it).

## 7. Summary of facts

1. `TestCase::ensureSqliteZenaRbacTables()` (`tests/TestCase.php:203-242`), invoked unconditionally from `ensureSqliteSubmittalsTable()` (line 200), which itself is invoked unconditionally from every `setUp()` (line 88), executes 4 `DROP TABLE IF EXISTS` + 4 `CREATE TABLE` statements on every test, every driver — confirmed by direct code read, no driver guard, no existence guard, unlike its two sibling helpers.
2. These `CREATE TABLE` statements are not a rare fallback: `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` renames the `zena_*` variants of these 4 tables away during every `migrate:fresh`, so the tables never pre-exist under their `zena_` names — the `Schema::create()` calls execute every single test.
3. For any test class `use`-ing `RefreshDatabase` (confirmed via framework source: `setUpTraits()` runs inside `parent::setUp()`, before `tests/TestCase.php`'s own `setUp()` body continues), a real DB transaction is already open when this unguarded DDL runs.
4. On MySQL/InnoDB, `CREATE TABLE`/`DROP TABLE` are documented implicit-commit statements — if this holds here (not independently reproduced live in this environment; see §5 for why and what was reproduced instead), the transaction opened for isolation is silently committed away mid-`setUp()`, and the teardown `rollBack()` has nothing left to undo.
5. Only 3 CI script paths on `origin/main` genuinely reach real MySQL for PHPUnit (per GAP-039's own definitive inventory, independently spot-checked in this pass): `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`. Of these, only `zena-invariants-mysql` runs `RefreshDatabase`-using tests (12 of its 17 `--group=zena-invariants` test classes) and is therefore the surface where the specific "defeats RefreshDatabase isolation" failure mode applies. The register's claim that `routes-guardrails.yml`'s parity step, `performance-tests`, and `e2e-tests` are affected is **not supported** by GAP-039's own evidence — those three all resolve to SQLite in practice and should be removed from GAP-040's affected-surface list at Gate 2.
6. No live data-leakage incident has been observed in current CI; this is a structural defect with a well-documented but not-live-reproduced-in-this-environment runtime consequence, not a demonstrated production incident.
