# GAP-044 — Gate 1 Evidence: `SAVEPOINT trans2 does not exist` root cause

**Date:** 2026-08-22
**Gate:** 1 (root-cause investigation only — no fix, no Gate 2, no implementation)
**Canonical baseline:** `main @ 4a89693ba0a3efa1bb377645cae2fbe481865f81`
**Investigation branch:** `docs/GAP-044-gate1-investigation`
**Registered:** `OPERATIONAL_GAP_REGISTER.md` row `GAP-044` (added by PR #278, commit `25cab7f4`, discovered during GAP-041 LIVE execution; reconfirmed LIVE by GAP-043 Gate 3, squash `c345df2d`)

## Owner Summary

`SAVEPOINT trans2 does not exist` (`SQLSTATE[42000]`, error 1305) is thrown from
`Illuminate\Database\Eloquent\Builder::withSavepointIfNeeded()` →
`Illuminate\Database\Concerns\ManagesTransactions::performRollBack()`
(`vendor/laravel/framework/.../ManagesTransactions.php:309`) whenever a nested
`DB::transaction()`/`firstOrCreate()`-style savepoint call has to roll back on a
connection whose real MySQL transaction was **silently implicit-committed away
earlier in the same test's `setUp()`**, while Laravel's PHP-side
`transactionLevel()` counter never learned about it.

The implicit commit is caused by three methods in `tests/TestCase.php` —
`ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`,
`ensureProjectTasksTable()` — which create three tables that **have no
migration anywhere in the repository** (`interaction_logs`, `project_phases`,
`project_tasks`), guarded only by `Schema::hasTable()` existence checks, with
**no driver guard and no isolated-connection routing**. On MySQL, `CREATE
TABLE` is a documented implicit-commit statement. This is the **exact same
structural defect class GAP-040 fixed for `ensureSqliteZenaRbacTables()`**
(isolating its DDL onto a separate `zena_ddl_bootstrap` connection) — but
GAP-040's fix did not touch these three sibling methods, which retain the
original, unfixed unconditional-DDL-on-the-transacted-connection pattern.

This was independently reproduced LIVE on genuine MySQL 8.0 via a disposable,
never-merged evidence harness (branch
`investigate/GAP-044-disposable-evidence-harness`, deleted after evidence
capture, GitHub Actions run `32557247386`) with `DB::transactionLevel()` /
`PDO::inTransaction()` / `CONNECTION_ID()` probes bracketing every relevant
`setUp()` boundary. The probe data is a direct, server-observed proof of the
full causal chain below — not an inference from log text alone.

**Revision note (this pass):** Owner review of the first submission of this
document identified that the three implicated sibling helpers already
existed and were already called from `TestCase::setUp()` at the GAP-040
baseline, and asked whether GAP-040's own already-Owner-approved cold-start
rollback proof could itself be a second, distinct false-green — i.e.,
whether the "marker row absent" result GAP-040's proof reports is actually
caused by the *verifier* test's own `migrate:fresh` wiping the schema
(triggered by this exact implicit-commit mechanism poisoning
`RefreshDatabaseState::$migrated`), not by a genuine `RefreshDatabase`
rollback. A second disposable, never-merged discriminating harness (branch
`investigate/GAP-044-gap040-proof-discriminator`, GitHub Actions runs
`32560499974`/`32560820613` via throwaway PR #284, closed unmerged, branch
deleted) was built and run on genuine MySQL to answer this directly. **Result:
CONFIRMED FALSE-GREEN — see §H1 below.**

**Revision note 2 (this pass):** the same review also required identifying
the original masked exception behind the `ROLLBACK TO SAVEPOINT` path. Two
initial attempts using the writer/verifier harness's environment did not
reproduce it. A third, **exact-match** disposable harness
(`investigate/GAP-044-exact-match-harness`, GitHub Actions run `32562591732`,
deleted after capture) that faithfully replicated the authoritative failing
run's full pipeline — including its `db:seed` step, missing from the
earlier attempts — reproduced it on the first try via runtime-only,
never-committed vendor instrumentation. **Result: CONFIRMED — the original
exception is `Illuminate\Database\UniqueConstraintViolationException`
(SQLSTATE 23000, MySQL 1062, duplicate `code='project.read'`), a genuine,
independent seeding/lookup-key mismatch — see §I1 below.**

## A. Is the symptom reproduced on current canonical `main`?

**Yes.** Reproduced LIVE on `main`'s exact tree (canonical baseline
`4a89693b`, identical `tests/TestCase.php`/`tests/Traits/TenantUserFactoryTrait.php`/
`tests/Performance/*.php` content — the disposable harness's only diff was
additive `STDERR` probe instrumentation plus GAP-041's already-Owner-approved
selector overlay, `--group=performance --fail-on-empty-test-suite`, needed
because GAP-041 is still open and `main`'s current `automated-testing.yml`
would otherwise silently select 0 tests):

- `PerformanceMonitoringTest.php`: `Tests: 1 failed, 9 passed (43 assertions)` — the 1 failure is `test_api_performance_budgets`, `PDOException: SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT trans2 does not exist`.
- `DashboardPerformanceTest.php`: `Tests: 2 failed, 17 passed (153 assertions)` — one failure is `it_can_load_dashboard_with_large_dataset_quickly`, the **same** `SAVEPOINT trans2 does not exist` error; the other failure, `it_can_load_alerts_with_large_dataset_quickly`, is a **separate, unrelated** latency-budget assertion miss (`504.7ms` vs `450ms` budget) — this is GAP-045, not GAP-044 (confirmed by its distinct stack trace, `tests/Performance/DashboardPerformanceTest.php:310`, an `assertLessThan` failure, not a `PDOException`).

Both failing stack traces are **byte-identical in shape**, both bottoming out at:

```
PDOException: SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT trans2 does not exist
  at vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php:309
    305▕             if ($pdo->inTransaction()) {
    306▕                 $pdo->rollBack();
    307▕             }
    308▕         } elseif ($this->queryGrammar->supportsSavepoints()) {
  ➜ 309▕             $this->getPdo()->exec(
    310▕                 $this->queryGrammar->compileSavepointRollBack('trans'.($toLevel + 1))
    311▕             );
    312▕         }
      +10 vendor frames
  11  tests/Traits/TenantUserFactoryTrait.php:59
  12  tests/Traits/TenantUserFactoryTrait.php:47
```

**Run details:**
- Database: genuine MySQL `8.0` GitHub Actions service container (`mysql:8.0`), not SQLite, not mocked.
- Environment: `ZENA_INVARIANTS_DB=mysql`, `DB_CONNECTION=mysql`, fail-closed preflight (`zena_mysql_ensure_connection`/`zena_mysql_preflight_connection`) passed.
- Truthful command: `php artisan test "<file>" --group=performance --fail-on-empty-test-suite` (GAP-041's already-approved overlay; without it `main`'s current job silently selects 0 tests and reports false-green — this is why the disposable harness carried this one-line, already-Owner-approved overlay from GAP-043's own precedent).
- Actual population: 10 tests executed for `PerformanceMonitoringTest.php`, 19 for `DashboardPerformanceTest.php` — not "No tests found."
- Workflow run: `32557247386` (`workflow_dispatch` on the disposable branch), jobs `96993284455` (monitoring) and `96993284478` (dashboard).
- **Consistency:** reproduces on both files, on the very first execution attempt, with no observed flake — the failing test is always the first `RefreshDatabase`-using test declared in its class.

## B. Under exactly what MySQL/test conditions?

Genuine MySQL 8.0, `RefreshDatabase`-using test class extending `Tests\TestCase`, using `Tests\Support\SSOT\FixtureFactory`/`Tests\Traits\TenantUserFactoryTrait`, running as the **first** such test to execute in a fresh PHPUnit process (cold start) — i.e. the very first test method PHPUnit picks up by declaration order within the invoked file(s).

## C. What is the first failing operation?

`Illuminate\Database\Eloquent\Builder::createOrFirst()` (called from
`Permission::firstOrCreate()`, `tests/Traits/TenantUserFactoryTrait.php:59`,
inside `ensurePermissionAttached()`, called from `assignApiRoles()` at line
47, called from `createTenantUser()`, called from
`createTenantUserWithRbac()`, called from the failing test class's own
`setUp()` — **before the test method body ever runs**). `createOrFirst()`
wraps its `create()` call in `withSavepointIfNeeded()`
(`vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php:1957-1962`):

```php
public function withSavepointIfNeeded(Closure $scope): mixed
{
    return $this->getQuery()->getConnection()->transactionLevel() > 0
        ? $this->getQuery()->getConnection()->transaction($scope)
        : $scope();
}
```

Because `transactionLevel()` (Laravel's **PHP-side** counter) still reports
`1` (it was never told the real transaction died), this always takes the
`->transaction($scope)` branch. That opens a **savepoint**, not a real
transaction (`createTransaction()`'s `elseif ($this->transactions >= 1 &&
supportsSavepoints())` branch issues `SAVEPOINT trans2`). When the wrapped
`create()` closure throws (a genuine race/constraint condition inside
`Permission::create()`/`Role::create()` — see §I, not fully resolved by this
Gate 1), Laravel's `handleTransactionException()` calls `rollBack()`, which
for the nested level issues `ROLLBACK TO SAVEPOINT trans2` — **unguarded**,
unlike the level-0 rollback path which is guarded by `if
($pdo->inTransaction())`. MySQL replies "does not exist" because the
savepoint was already discarded (see §D-E).

## D. Where was `trans2` originally created?

`Illuminate\Database\Concerns\ManagesTransactions::createSavepoint()`
(`ManagesTransactions.php:163-168`):

```php
protected function createSavepoint()
{
    $this->getPdo()->exec(
        $this->queryGrammar->compileSavepoint('trans'.($this->transactions + 1))
    );
}
```

Called from `beginTransaction()` → `createTransaction()`, triggered by
`withSavepointIfNeeded()`'s `->transaction($scope)` call described in §C.
This issues a raw, **unguarded** `SAVEPOINT trans2` SQL statement on a PDO
handle that, at that moment, has **no real active MySQL transaction** (see
§E) — MySQL/InnoDB accepts the statement (it does not error at *creation*
time; error 1305 is specific to `RELEASE`/`ROLLBACK TO` on a name that isn't
found), but because there is no genuine surrounding transaction, the
savepoint exists only within its own single, immediately-autocommitted
statement and is gone by the time anything tries to reference it again.

## E. What caused MySQL to lose the real transaction context (i.e., what caused `trans2` to not persist)?

**Directly proven by the disposable-harness probe log**, bracketing every
`setUp()` boundary with `DB::transactionLevel()`, `PDO::inTransaction()`,
and `SELECT CONNECTION_ID()`, for every test in both files (91 probe lines
for `PerformanceMonitoringTest`, 171 for `DashboardPerformanceTest`). The
exact flip, for **every single test method**, same connection ID throughout:

```
label=before_sibling_ddl              transactionLevel=1 pdoInTransaction=true  connectionId=21 interaction_logs_exists=false
label=after_ensureInteractionLogsTable transactionLevel=1 pdoInTransaction=false connectionId=21 interaction_logs_exists=true
```

`ensureInteractionLogsTable()` (`tests/TestCase.php`) runs
`Schema::create('interaction_logs', ...)` **directly on the default
(transacted) connection**, with no driver guard and no isolated-connection
routing (unlike `ensureSqliteZenaRbacTables()`, GAP-040's fixed sibling).
`interaction_logs`, `project_phases`, and `project_tasks` have **zero
migrations anywhere in the repository** (confirmed: `grep -rl
"interaction_logs" database/migrations/` and `grep -rl "project_tasks"
database/migrations/` both return no matches; `project_phases` appears only
in a code comment in `2026_07_10_090000_create_design_items_table.php`
noting the FK target "has no [migration]"). This means, on every fresh
MySQL database/process, these three tables are **always** absent when
`RefreshDatabase`'s already-open transaction reaches this code — the
`CREATE TABLE` DDL genuinely executes on the live, transacted connection
every time the guard's existence check misses. On MySQL/InnoDB, `CREATE
TABLE` is a documented implicit-commit statement: the probe shows
`pdoInTransaction` flip from `true` to `false` at exactly this boundary,
with `DB::transactionLevel()` staying at `1` throughout (Laravel's PHP-side
counter has no way to observe a server-side implicit commit).

This is structurally and mechanically identical to the defect GAP-040 fixed
for `ensureSqliteZenaRbacTables()` (`zena_roles`/`zena_permissions`/etc.) —
except GAP-040's isolated-`zena_ddl_bootstrap`-connection fix was applied
only to that one method, not to its three siblings
(`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`),
which retain the original unconditional-DDL-on-the-transacted-connection
pattern GAP-040 itself first diagnosed and fixed elsewhere in the same file.

## F. Did physical connection identity change?

**No** — `connectionId=21` observed identically across every probe line, for
every test, in both jobs' full runs. No reconnect/`CONNECTION_ID()` change
was observed. This rules out a "MySQL server has gone away" / lost-connection
reconnect as the mechanism (that was a live, considered alternative
hypothesis before the probe data was collected — the probe evidence
positively excludes it for these two runs).

## G. Did implicit COMMIT occur?

**Yes — directly proven**, not inferred: `PDO::inTransaction()` reads `true`
immediately before `ensureInteractionLogsTable()` runs and `false`
immediately after, on the *same* PDO handle/connection ID, with no
intervening explicit `COMMIT`/`ROLLBACK` call anywhere in the traced code
path between those two probe points. This is the direct, server-reported
signal (`PDO::inTransaction()` reflects the driver's own transaction-state
tracking, not Laravel's) that the implicit commit happened exactly at the
`CREATE TABLE` boundary.

## H. What is the relationship to GAP-040?

**Classification: (A) — GAP-044 is caused by residual/incomplete GAP-040
behavior**, established from evidence, not assumed:

- GAP-040's own Gate 1 evidence (`docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md`) diagnosed `TestCase::ensureSqliteZenaRbacTables()`'s unconditional, unguarded `Schema::create()`/`dropIfExists()` calls on the transacted connection as the root cause of MySQL transaction-isolation defeat, and GAP-040's Gate 2/3 (`docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md`, `docs/owner-decisions/GAP-040/03-release.md`) fixed it by routing that one method's DDL through a dynamically-registered, non-transacted `zena_ddl_bootstrap` connection (Option C), proven via a cold-start regression harness on 5 approved real-MySQL surfaces.
- GAP-040's approved scope was explicitly `tests/TestCase.php::ensureSqliteZenaRbacTables()` — its Gate 2 spec, Gate 3 evidence, and final-diff review (`git diff origin/main...f8f4d110`) confirm the fix touched only that one method; its three siblings (`ensureInteractionLogsTable`, `ensureProjectPhasesTable`, `ensureProjectTasksTable`), sitting a few lines below it in the same file, were never in scope and were never mentioned in any GAP-040 gate document.
- GAP-040 explicitly did **not** claim to fix every unguarded-DDL-on-transacted-connection defect in `tests/TestCase.php` — only the one it diagnosed (`ensureSqliteZenaRbacTables()`). GAP-040's own release evidence (§7, "Rủi ro tồn dư") does not mention these three siblings at all; they were not discovered during that investigation.
- GAP-044's root cause (§D-G above) is the **exact same defect class** — unconditional `Schema::create()` DDL, guarded only by an existence check, executed directly on the shared/transacted connection with no driver guard and no isolated-connection routing — applied to three different, migration-less, test-only tables GAP-040's fix never touched.
- `ensureSqliteZenaRbacTables()`'s own bootstrap (via `zenaRbacBootstrapSchema()`/`zena_ddl_bootstrap`) is independently confirmed, this pass, to **not** flip `pdoInTransaction` itself — probe data shows `after_ensureSqliteSubmittalsTable` (which calls `ensureSqliteZenaRbacTables()` internally) still reads `pdoInTransaction=true`; the flip to `false` happens strictly later, at `after_ensureInteractionLogsTable`. **This narrow claim about the one method's own bootstrap mechanism is unchanged.** However — critically, and corrected in this revision — this does **not** mean GAP-040's overall Owner-approved cold-start *rollback proof* (the end-to-end claim that a marker row written by the writer test is genuinely gone by the time the verifier reads it, proving real transactional rollback) remains valid. §H1 below reports a live-confirmed second false-green mechanism that defeats that proof through the *same* three unfixed sibling methods, independently of `ensureSqliteZenaRbacTables()` itself.

GAP-040's approved acceptance contract ("no DDL statement may execute on
[the transacted] connection between the moment `RefreshDatabase`'s
transaction opens and the moment the test body runs... on every one of the
5 directly-exposed surfaces") is **not actually satisfied on current `main`**
for those same 5 surfaces, because three siblings of the method GAP-040 did
fix still violate that exact invariant. This is stated as a fact established
by this Gate 1's evidence, not as a request to reopen or re-litigate GAP-040's
already-released decision record.

### H1. GAP-040's Gate-3 cold-start rollback proof — CONFIRMED FALSE-GREEN (new finding, this revision)

**This is a governance-significant finding about a previously Owner-approved
Gate-3 evidence record. It is reported as new evidence for Owner
consideration. GAP-040's historical decision record is preserved unedited;
nothing in `docs/owner-decisions/GAP-040/*` is modified by this PR.**

GAP-040's Gate 3 release evidence (`docs/owner-decisions/GAP-040/03-release.md`
§3) presented, as its primary regression proof, a writer/verifier test pair
in which a marker row is written inside a cold-start test, and a second,
independent-PDO check confirms the row is absent — interpreted as proof that
`RefreshDatabase`'s real rollback removed it. The Owner's review of this
Gate-1 submission identified a second, distinct possible explanation:
that the three sibling methods now known to implicit-commit the outer
transaction (§D-G) were **already present at the GAP-040 baseline**, and
could cause the *verifier* test's own `parent::setUp()` to run
`migrate:fresh` (via `RefreshDatabaseState::$migrated` being reset to
`false` by `RefreshDatabase`'s own self-healing check, §J) — which would
wipe the marker row via schema reset, not via any genuine transactional
rollback, producing an indistinguishable "marker absent" result.

**A disposable, never-merged discriminating harness was built and run on
genuine MySQL 8.0 to test this directly** (branch
`investigate/GAP-044-gap040-proof-discriminator`, self-contained — it
touches no GAP-040 file; it forces cold start via the same public
`Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated` mechanism
GAP-040's own trait uses, independently reimplemented; GitHub Actions runs
`32560499974` and `32560820613`, via throwaway Draft PR #284 against
`routes-guardrails.yml`'s `--group=mysql-parity` step, closed unmerged,
branch deleted immediately after evidence capture). The harness captured,
via an independent raw-PDO connection (env-var-driven, not
`config()`-driven, so it works even before the Laravel app is booted) at
every boundary the Owner specified:

```
[GAP044-DISC] test=...::test_a_writer   label=after_full_writer_parent_setUp        pdoInTransaction=false connectionId=16
[GAP044-DISC] test=...::test_a_writer   label=after_marker_insert                    pdoInTransaction=false connectionId=16
[GAP044-DISC] test=...::test_a_writer   label=independent_pdo_before_writer_teardown tenant_id=01M0... marker_visible=true
[GAP044-DISC] test=...::test_b_verifier label=before_verifier_parent_setUp           migrated=false marker_visible=true
[GAP044-DISC] test=...::test_b_verifier label=after_verifier_parent_setUp            pdoInTransaction=false connectionId=16
[GAP044-DISC] test=...::test_b_verifier label=verifier_summary tenant_id=01M0... migrated_before_verifier_setUp=false
    marker_visible_before_verifier_setUp=true marker_visible_after_verifier_setUp=false
    CONCLUSION=DISAPPEARED_DURING_VERIFIER_SETUP_WITH_MIGRATED_FALSE_ie_LIKELY_MIGRATE_FRESH
```

Reproduced identically across both runs (`32560499974` and `32560820613`,
the second after a role-name correction unrelated to this conclusion).
Reading each captured fact directly:

1. **Writer PDO state after FULL `parent::setUp()`:** `pdoInTransaction=false` — the writer test's own transaction was *already* implicit-committed by the time its `setUp()` finished, before the marker was even written. This is the same mechanism as §D-G, now observed on the exact GAP-040 writer/verifier scenario.
2. **Marker visible via independent PDO, BEFORE the writer's own teardown runs:** `true` — the marker row is visible to a completely independent connection *immediately* after insertion, before any rollback could possibly have occurred. This alone proves the writer's insert was never actually protected by an isolated transaction — it was committed on write, exactly consistent with (1).
3. **Marker state immediately before the verifier's `parent::setUp()` runs (i.e., after the writer's own teardown, before any migrate:fresh the verifier's own setup might trigger):** still `true` — the marker had **not yet disappeared** at this point.
4. **`RefreshDatabaseState::$migrated` at that same point:** `false` — confirming `RefreshDatabase`'s self-healing check (§J) fired during the writer's teardown (because `pdoInTransaction()` read `false` there too), meaning the verifier's own `refreshTestDatabase()` is about to run `migrate:fresh`.
5. **Marker state after the verifier's `parent::setUp()` completes:** `false` — the marker is gone.
6. **Conclusion, directly derivable from (3)+(4)+(5):** the marker disappeared *during the verifier's own `parent::setUp()`*, at exactly the point where `$migrated=false` causes `migrate:fresh` to run — **not** during the writer's teardown (where it was still present, per (3)), and not via any rollback (there was no genuine open transaction to roll back, per (1)-(2)).

**This confirms the Owner's second false-green hypothesis exactly as
specified: GAP-040's writer/verifier "marker absent" result is explained by
`migrate:fresh` schema-wiping the marker between the writer's teardown and
the verifier's read, not by `RefreshDatabase`'s rollback genuinely working.**
The same three unfixed sibling methods that cause GAP-044's visible
`SAVEPOINT` failures also silently defeat the specific evidence GAP-040's
Gate 3 relied on to prove its own fix — because those three methods were
never part of GAP-040's fix and were present, unchanged, at every stage of
GAP-040's own Gate 3 evidence-gathering.

**Explicit corrections, per Owner instruction:**
- GAP-040's *implementation surface* was one helper (`ensureSqliteZenaRbacTables()`only).
- GAP-040's Owner-*approved acceptance contract* was broader: real-MySQL `RefreshDatabase` transaction isolation preserved end-to-end from the first test of a fresh process onward, proven by cold-start rollback evidence, across all 5 approved surfaces.
- The evidence GAP-040 used to claim that broader contract was satisfied is now shown, by direct experiment, to be **unreliable in this exact scenario** — a `migrate:fresh`-caused disappearance is observationally indistinguishable, in GAP-040's own proof design, from a genuine rollback-caused disappearance.
- **This Gate 1 does NOT claim GAP-040's technical acceptance contract remains satisfied.** That claim cannot currently be made with confidence, given this finding.
- This Gate 1 does **not** reclassify GAP-040's released status, does **not** edit any GAP-040 file, and does **not** decide what governance action (if any) GAP-040 itself requires — that is an Owner decision, reported here as a discovery, consistent with how GAP-040's own Gate 2 handled its own mid-investigation discovery about `zena_roles`/`zena_permissions` (§1 of that gap's design spec) without reopening GAP-039.

## I. What exact code path owns the root cause?

`tests/TestCase.php`:
- `ensureInteractionLogsTable()` (creates `interaction_logs`, no migration exists for this table)
- `ensureProjectPhasesTable()` (creates `project_phases`, no migration exists for this table)
- `ensureProjectTasksTable()` (creates `project_tasks`, no migration exists for this table)

All three: existence-guarded only (`if (Schema::hasTable(...)) return;`), no
driver guard, `Schema::create(...)` issued via the default `Schema` facade
(default/active connection — the one `RefreshDatabase` has already opened a
transaction on), called unconditionally from `TestCase::setUp()` for every
test class extending `Tests\TestCase`, immediately after the (now GAP-040-fixed)
`ensureSqliteSubmittalsTable()`/`ensureSqliteZenaRbacTables()`/`ensureSqliteDocumentsBackupTable()`
calls.

The mechanism in §C-H fully explains *why* a `ROLLBACK TO SAVEPOINT trans2`
fails with "does not exist" whenever it is attempted on a connection whose
real transaction was implicit-committed away — that part is proven
end-to-end from server-observed probe data plus exact Laravel framework
source.

### I1. The masked original exception — CONFIRMED (this revision, via an exact-match harness)

**Revision note:** two earlier attempts (historical record — disposable
branch `investigate/GAP-044-gap040-proof-discriminator`, same run as §H1)
tried to capture this exception: (1) a manual replica of
`ensurePermissionAttached()`'s exact logic with the real role/permission
values, and (2) a faithful call to the real, unmodified
`TenantUserFactoryTrait::createTenantUser()`. **Neither reproduced any
exception** — both succeeded cleanly. Both ran on `routes-guardrails.yml`'s
environment, which lacks the `php artisan db:seed --env=testing --force`
step the authoritative failing run (`32557247386`, job `96993284455`)
actually has — this later proved to be the decisive missing ingredient
(see below: the collision is with seeded data). Per Owner instruction, a
third, **exact-match** disposable harness was built that reuses the real
`automated-testing.yml` `performance-tests` job unmodified (same MySQL 8.0
service, same `migrate` + `db:seed --env=testing --force`, same
`PerformanceMonitoringTest.php`, same `FixtureFactory`/`TenantUserFactoryTrait`
path, same `--group=performance --fail-on-empty-test-suite` selector),
adding only two purely-additive, non-committed diagnostics: (a) a
runtime patch (`scripts/ci/gap044-disposable-patch-vendor.php`, applied
after `composer install`, never committed as a vendor change) to the
**installed** copy of `Illuminate\Database\Concerns\ManagesTransactions.php`,
logging the original `Throwable` — class, message, code, previous exception,
connection state — immediately **before** `handleTransactionException()`
calls `$this->rollBack()` (the exact point the Owner specified, where the
real `ROLLBACK TO SAVEPOINT` failure would otherwise mask it); and (b) a
disposable query-listener ring buffer added to `tests/TestCase.php`
(`$gap044RecentQueries`, gated on `GAP044_CAPTURE_ORIGINAL_THROWABLE=1`,
read by the vendor patch) showing the SQL immediately preceding the capture.
Neither addition changes control flow or swallows the exception — the real
`SAVEPOINT trans2 does not exist` symptom still occurred identically
afterward, confirming the instrumentation did not alter the failure.

**Reproduced on the first exact-match attempt, identically on both
`PerformanceMonitoringTest.php` and `DashboardPerformanceTest.php`**
(GitHub Actions run `32562591732`, jobs `97006383932` and `97006383949`,
disposable branch `investigate/GAP-044-exact-match-harness`, workflow-
dispatched, deleted after evidence capture):

```
[GAP044-ORIGINAL-THROWABLE] class=Illuminate\Database\UniqueConstraintViolationException
message=SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry
'project.read' for key 'permissions.permissions_code_unique' (Connection: mysql,
Host: 127.0.0.1, Port: 3306, Database: zenamanage_test, SQL: insert into
`permissions` (`name`, `code`, `module`, `action`, `description`, `id`,
`updated_at`, `created_at`) values (project.read, project.read, project, read,
Project read, 01m0m9qp1qp7eve2qbrx0ay5pz, ...))
code='23000' previous_class=PDOException
previous_message=SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate
entry 'project.read' for key 'permissions.permissions_code_unique'
transactionLevel=2 pdoInTransaction=false connectionId=22 connectionName=mysql
```

The `GAP044-RECENT-QUERY` buffer immediately preceding it shows the exact
operation chronologically: `insert tenants` → `insert users` → `select roles
where name='project_manager'` (miss) → `insert roles` (`project_manager`,
succeeds) → `insert user_roles` → `select permissions where name='project.read'`
(**miss — no row found**) → the failing `insert into permissions (...code='project.read'...)`.

**Original Throwable, fully identified:**
- **Class:** `Illuminate\Database\UniqueConstraintViolationException` (wrapping a `PDOException`).
- **SQLSTATE / driver code:** `23000`, MySQL error `1062`.
- **Model / operation:** `App\Models\Permission::firstOrCreate(['name' => 'project.read'], [...])`, called from `TenantUserFactoryTrait::ensurePermissionAttached()` — the lookup (`WHERE name = 'project.read'`) finds nothing, so Eloquent's `createOrFirst()` attempts `Permission::create([...])`.
- **Exact create attributes:** `name=project.read, code=project.read, module=project, action=read, description="Project read"`.
- **Exact conflict:** the `INSERT` collides on the **`code`** column's unique index (`permissions_code_unique`) — a row with `code='project.read'` **already exists** in the `permissions` table, even though the earlier `WHERE name='project.read'` lookup found no matching row (i.e., that pre-existing row's `name` value is **not** `'project.read'`).
- **Row origin:** the job's DB-setup step runs `Database\Seeders\PermissionSeeder`, which does create a `code='project.read'` row (confirmed in the job's seeder output log, `PermissionSeeder ... DONE`) via `Permission::firstOrCreate(['code' => $permData['code']], $permData + ['name' => $permData['code']])` — which on its face sets `name` equal to `code`. Whether this is genuinely the same row the test collided with, or the collision is with some other/duplicate write, could not be fully confirmed without a live row dump of the `permissions` table at the moment of failure — this specific detail (why the pre-existing row's `name` does not match its `code`, given the seeder's own code sets them equal) is flagged as an open, secondary uncertainty in §O; it does not affect the certainty of the primary finding (a genuine `UniqueConstraintViolationException` from a real duplicate `code`, not a phantom/masked artifact).
- **Timing:** the capture occurs ~0.7s after `php artisan test` starts — consistent with `RefreshDatabaseState::$migrated` already being `true` when this test began (i.e., `RefreshDatabase`'s own internal `migrate:fresh` did not re-run for this test, leaving the job-level seed data from `PermissionSeeder` intact) rather than a freshly re-migrated (and hence necessarily empty) `permissions` table — consistent with, though not independently proven beyond, the observed timing.

**Complete causal chain, now fully established:**

1. Job-level DB setup runs `php artisan migrate --env=testing --force` then `php artisan db:seed --env=testing --force`, which (via `PermissionSeeder`) creates a `permissions` row with `code='project.read'`.
2. PHPUnit process starts; the first `RefreshDatabase` test's `setUp()` opens a real transaction, then `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` (§D-G) implicit-commit it via unguarded `CREATE TABLE` DDL — confirmed again here (`pdoInTransaction=false` at capture time).
3. `createTenantUserWithRbac()` → `TenantUserFactoryTrait::ensurePermissionAttached()` calls `Permission::firstOrCreate(['name' => 'project.read'], [...])`.
4. The lookup `WHERE name = 'project.read'` does not match the pre-existing seeded row (name mismatch, §O), so Eloquent proceeds to `createOrFirst()`'s `create()` path.
5. `withSavepointIfNeeded()` sees `transactionLevel() > 0` (Laravel's PHP-side counter, still stuck at `1` from the implicit commit in step 2) and wraps the `create()` in `DB::transaction()`, which opens `SAVEPOINT trans2` — auto-discarded per the mechanism in §D, since there is no real active transaction on the connection.
6. The `INSERT` genuinely fails: `UniqueConstraintViolationException` (1062, duplicate `code`) — a **real, independent, correctly-thrown exception**, unrelated to GAP-044's transaction mechanism.
7. `DB::transaction()`'s catch (`handleTransactionException`) attempts to roll back to the (already-discarded) savepoint — `ROLLBACK TO SAVEPOINT trans2` — which fails with `SAVEPOINT trans2 does not exist` (§C-D), because the savepoint never really existed inside a live transaction.
8. This secondary `PDOException` propagates out of `withSavepointIfNeeded()`/`transaction()` as a completely different exception type than the original `UniqueConstraintViolationException` — **bypassing `createOrFirst()`'s own built-in graceful-recovery `catch (UniqueConstraintViolationException $e) { return ...->first() ?? throw $e; }` block entirely**, since by the time control reaches that catch, the exception in flight is no longer a `UniqueConstraintViolationException`. This is a second, previously-unrecognized consequence of GAP-044's mechanism: it does not just cause a confusing error message — it actively **defeats Eloquent's own designed-for-this-exact-scenario race/duplicate recovery mechanism**.
9. The resulting `PDOException: SAVEPOINT trans2 does not exist` is what surfaces as the LIVE test failure.

**Classification (per Owner's A/B/C/D framework): B — a test-data/seeding
defect** (a genuine `name`/`code` lookup-vs-uniqueness mismatch between
`TenantUserFactoryTrait`'s lookup key and `PermissionSeeder`'s data,
producing a real duplicate-key collision on first contact) **compounded by,
but analytically separable from, GAP-044's transaction mechanism** (which
converts what Eloquent's own `createOrFirst()` is specifically designed to
handle gracefully into an unrecoverable, misleading error). **Not
classification A** — this is not normal/expected race handling working as
intended; Eloquent's own recovery path exists specifically for this
scenario and is being defeated by GAP-044's mechanism, not exercised
successfully. Per §7 of the Owner's authorization, this seeding/lookup
mismatch is documented here and characterized, but **not fixed** and **not
absorbed into GAP-044's implementation scope** — that is an Owner scoping
decision if pursued.

## J. Trace the shared call path (per Owner authorization §5)

- `tests/Performance/PerformanceMonitoringTest.php` / `tests/Performance/DashboardPerformanceTest.php`: both `use RefreshDatabase, FixtureFactory, AuthenticationTrait;`, both call `createTenant()` + `createTenantUserWithRbac()` in their own `setUp()`, **before** `parent::setUp()`'s sibling-DDL implicit commit has any chance to matter to the test body — but *after* it has already happened, since `parent::setUp()` runs first inside `TestCase::setUp()`.
- `tests/Support/SSOT/FixtureFactory.php`: `createTenantUserWithRbac()` calls `$this->createTenantUser($tenant, ...)` then separately does its own `Role::firstOrCreate()`/`Permission::firstOrCreate()` calls for the RBAC role/permission sync (lines 53-79) — **not** the code path that failed in this run (the LIVE failure's stack bottoms out in `TenantUserFactoryTrait.php`, called from `createTenantUser()`, which runs first).
- `tests/Traits/TenantUserFactoryTrait.php`: `createTenantUser()` → `assignApiRoles()` (line 22) → for each role, `Role::firstOrCreate()` (line 37) then `ensurePermissionAttached()` (line 47, **the exact failing frame**) → for each permission, `Permission::firstOrCreate()` (line 59, **the exact failing frame**).
- `tests/TestCase.php`: `setUp()` — `parent::setUp()` (opens the real level-1 transaction) → `ensureSqliteSubmittalsTable()`/`ensureSqliteZenaRbacTables()` (GAP-040-fixed, confirmed **not** to flip `pdoInTransaction` in the probe data) → `ensureSqliteDocumentsBackupTable()` (driver-guarded, no-op on MySQL) → `ensureInteractionLogsTable()` (**the implicit-commit trigger, confirmed by probe**) → `ensureProjectPhasesTable()` → `ensureProjectTasksTable()`.
- `vendor/laravel/framework/.../ManagesTransactions.php` / `Eloquent/Builder.php`: `withSavepointIfNeeded()` → `transaction()` → `beginTransaction()`/`createTransaction()`/`createSavepoint()` (creates the doomed `SAVEPOINT trans2`) → closure throws → `handleTransactionException()` → `rollBack()` → `performRollBack($toLevel=1)` → unguarded `ROLLBACK TO SAVEPOINT trans2` → MySQL error 1305.
- `Illuminate\Foundation\Testing\RefreshDatabase` (framework trait, not repo code): its own `beginDatabaseTransaction()` teardown callback (`beforeApplicationDestroyed`) contains a **self-healing check** — `if ($connection->getPdo() && ! $connection->getPdo()->inTransaction()) { RefreshDatabaseState::$migrated = false; }` — which is what causes the *next* `RefreshDatabase` test in the same process to re-run `migrate:fresh` (confirmed by probe: `pdoInTransaction=true` again at the very next test's `after_parent_setUp`, and `tenants_exists=true`/`interaction_logs_exists=false` again, showing a genuine fresh migration cycle). This is a real Laravel framework behavior, not a repo defect, but it is a material part of the observed per-test cycling pattern.

## K. Search for implicit-commit / connection-state changes (per Owner authorization §6)

Within the exact failing interval (`parent::setUp()` returning through the
failing `Permission::firstOrCreate()` call):

| Candidate | Executed in the failing trace? | Physical CONNECTION_ID | Classification |
|---|---|---|---|
| `ensureSqliteZenaRbacTables()`'s `Schema::create()` calls (GAP-040-fixed) | Yes, every test | Uses **separate** `zena_ddl_bootstrap` connection (per GAP-040 fix) — confirmed **not** the implicit-commit trigger (`pdoInTransaction` stays `true` through `after_ensureSqliteSubmittalsTable`) | Ruled out |
| `ensureInteractionLogsTable()`'s `Schema::create('interaction_logs', ...)` | **Yes, every test, on the default/transacted connection** | 21 (same as main connection — no isolation) | **Confirmed implicit-commit trigger** |
| `ensureProjectPhasesTable()`/`ensureProjectTasksTable()`'s `Schema::create()` | Yes, every test, but transaction already implicit-committed by `ensureInteractionLogsTable()` moments earlier — these run in already-autocommit mode | 21 | Same defect class, redundant with the above once it has already fired once per test |
| `TestCase::ensureTestingSchema()`'s `Artisan::call('migrate:fresh', ...)` | **No**, not in the failing test's own `setUp()` — guarded by `Schema::hasTable('tenants')`, which is `true` by the time it runs (this job's separate "DB setup & migrations" workflow step already ran `migrate` before PHPUnit started) | N/A | Ruled out for this specific failure; still theoretically a cold-start risk on a truly fresh database with no pre-migration, not exercised by this job's setup |
| Laravel's *own* internal `RefreshDatabase::migrateDatabases()` (`artisan migrate:fresh` on the first test of the process) | Yes, but runs **before** `beginDatabaseTransaction()` — outside any transaction, not itself implicated | 21 | Ruled out as the trigger (expected/safe framework behavior) |
| Reconnect / "server has gone away" | **No** — `connectionId` never changes across the entire failing sequence | 21 throughout | Ruled out by direct evidence |
| Application code inside `Permission::create()`/`Role::create()` itself (further DDL) | Not traced — plain Eloquent `create()`/`INSERT`, no DDL expected, not independently verified | — | Not yet excluded with certainty (see §L) |

## L. Blast-radius analysis

All 5 of GAP-040's approved real-MySQL, `RefreshDatabase`-using surfaces
extend `Tests\TestCase` (confirmed: `TenantIsolationProjectsTest`,
`DatabaseConstraintsTest`, `CriticalUserFlowsE2ETest`, `DashboardE2ETest`,
`DocumentStatusMigrationTest` all `extends TestCase`), meaning
`ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()`'s
unconditional, unguarded DDL runs identically for all of them.

- **CONFIRMED AFFECTED (implicit-commit mechanism, §D-G, empirically proven via probe):** `tests/Performance/PerformanceMonitoringTest.php`, `tests/Performance/DashboardPerformanceTest.php` — both directly probed, both show the exact `pdoInTransaction` flip on every test.
- **CONFIRMED AFFECTED (same mechanism, by direct code inspection — not independently probed this pass):** every other `RefreshDatabase`-using test class extending `Tests\TestCase` running on a genuine MySQL connection, since `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` are called unconditionally from `TestCase::setUp()` with no test-class-specific opt-out — this includes, by code-path necessity, GAP-040's own 5 approved surfaces (`TenantIsolationProjectsTest`, `DatabaseConstraintsTest`, `zena-invariants-mysql`'s 12 classes, `treasury-check-constraints-mysql`'s 16 files, `CriticalUserFlowsE2ETest`/`DashboardE2ETest`, `DocumentStatusMigrationTest`) — meaning their *first* `RefreshDatabase` test of a process is silently exposed to the same implicit-commit-transaction-defeat GAP-040 believed it had eliminated. **This does not mean all of them will visibly *fail*** — visible failure additionally requires something in that specific test's own call path to hit an unguarded nested-transaction rollback (§M, "not yet tested").
- **POTENTIALLY AFFECTED (visible SAVEPOINT failure specifically):** any `RefreshDatabase`-using test class that also uses `FixtureFactory`/`TenantUserFactoryTrait` (102 files repo-wide use one or both) or any other code path that calls Eloquent's `firstOrCreate()`/`createOrFirst()`/`updateOrCreate()` (which shares the same `withSavepointIfNeeded()` mechanism) as the *first* `RefreshDatabase` test of a genuine-MySQL process. Not independently verified against MySQL this pass beyond the two Performance test files.
- **CONFIRMED UNAFFECTED (by direct grep, this pass):** `TenantIsolationProjectsTest.php` and `DatabaseConstraintsTest.php` (`routes-guardrails.yml`'s `--group=mysql-parity` surface) do **not** import or use `FixtureFactory`/`TenantUserFactoryTrait` — they are exposed to the underlying implicit-commit-transaction-defeat mechanism (data-leakage risk, same class as GAP-040's original concern) but are less likely to hit *this specific* visible `SAVEPOINT`-error symptom, absent some other nested-transaction-consuming call in their own setup. None of the 17 `@group zena-invariants` test classes import `FixtureFactory`/`TenantUserFactoryTrait` either (confirmed by grep).
- **NOT YET TESTED:** whether the `treasury-check-constraints-mysql` (16 files), `CriticalUserFlowsE2ETest`/`DashboardE2ETest`, or `DocumentStatusMigrationTest` surfaces actually exhibit a *visible* SAVEPOINT failure when run as the first test of a fresh process — not independently reproduced this pass; flagged for Gate 2 scoping, not claimed as proven exposure.
- **CONFIRMED UNAFFECTED (structurally, not just by absence of symptom):** any test using SQLite (SQLite's `CREATE TABLE` does not implicit-commit the way MySQL's does — established at GAP-040 Gate 1 and unchanged here) — the entire default (non-`ZENA_INVARIANTS_DB=mysql`) test suite (`unit-tests`, `feature-tests`, `api-tests-*`, `integration-tests`) is unaffected by this mechanism.

## M. Control / working examples (per Owner authorization §8)

`ensureSqliteZenaRbacTables()` (same file, called immediately before the
three unfixed siblings, same `setUp()` chain, same connection, same
`RefreshDatabase`-transacted context) is the clearest available control: it
creates 4 tables under the exact same "existence-guard, cold-start,
MySQL-transacted" conditions, and per GAP-040's own Gate 3 evidence plus this
pass's fresh probe data, does **not** flip `pdoInTransaction` — because
GAP-040 routed its DDL through a separate, non-transacted `zena_ddl_bootstrap`
connection. The only material difference between the working case
(`ensureSqliteZenaRbacTables`) and the three failing cases
(`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`)
is exactly that one architectural choice — isolated bootstrap connection vs.
direct `Schema::create()` on the default connection. No other difference
(fixture sequence, fixture ordering, transaction nesting depth, teardown
behavior) was found between the working and failing cases.

## N. Ranked hypotheses

**H1 — Sibling-DDL implicit commit defeats the outer transaction; the first
subsequent nested-transaction consumer (`Eloquent::firstOrCreate()`'s
savepoint-safety wrapper) fails when it tries to roll back a savepoint that
was never really inside a live transaction. (PRIMARY, CONFIRMED)**
- Evidence for: full server-observed probe chain (§D-G), exact Laravel-source-level trace of the failing operation (§C-D), exact stack-trace frame match (`TenantUserFactoryTrait.php:59`/`:47`) in both failing tests, structural/mechanical identity with GAP-040's already-proven defect pattern, a clean control example (§M) isolating the single architectural difference.
- Evidence against: none found.
- Discriminating experiment: instrument `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/`ensureProjectTasksTable()` with the same probe pattern as GAP-040's `coldStartProbe` and observe `pdoInTransaction` before/after on real MySQL. **Result: performed this pass, confirms H1 (§D-G).**

**H2 — The specific exception thrown inside `createOrFirst()`'s wrapped
`create()` call is a genuine `UniqueConstraintViolationException` colliding
with a job-level-seeded `permissions` row. (CONFIRMED, this revision — see §I1)**
- Evidence for: direct capture via an exact-match disposable harness (§I1) — `Illuminate\Database\UniqueConstraintViolationException`, SQLSTATE `23000`/MySQL `1062`, `Duplicate entry 'project.read' for key 'permissions.permissions_code_unique'`, on `Permission::firstOrCreate(['name'=>'project.read'], [...])`'s `create()` call, reproduced identically on both `PerformanceMonitoringTest.php` and `DashboardPerformanceTest.php` on the first attempt.
- Evidence against: none found on the exact-match harness. The two earlier attempts (§I1, historical) did not reproduce it — resolved by identifying the missing ingredient (the `db:seed` step) rather than contradicting H2.
- Discriminating experiment: performed this pass (§I1) via an exact-match disposable harness replicating the authoritative job's full pipeline (MySQL 8.0, `migrate`+`db:seed`, real `PerformanceMonitoringTest.php`, truthful selector) plus a runtime-only, never-committed vendor instrumentation patch capturing the original `Throwable` immediately before Laravel's own rollback call. **Result: confirms H2.**

**H3 — GAP-040's Gate-3 cold-start rollback proof is a false-green, defeated
by the same three unfixed sibling methods causing the verifier's
`migrate:fresh` to wipe the marker instead of a genuine rollback removing it.
(CONFIRMED, this revision — see §H1)**
- Evidence for: full server-observed writer/verifier probe chain (§H1) — marker visible immediately after write (proving no real isolation ever existed), still visible immediately before the verifier's own `parent::setUp()`, `RefreshDatabaseState::$migrated=false` at that exact point (proving `migrate:fresh` is about to run), marker gone immediately after — reproduced identically across two independent runs.
- Evidence against: none found.
- Discriminating experiment: performed this pass, a dedicated disposable writer/verifier harness (§H1) — result confirms H3.

Neither H1, H2, nor H3 proposes a fix. All three are now confirmed
findings: H1 is the transaction-desynchronization mechanism itself; H2 is
the specific triggering condition (a real, independent seeding/lookup
mismatch producing a genuine duplicate-key exception that H1's mechanism
then masks and whose graceful Eloquent recovery it defeats); H3 is
GAP-040's own proof being defeated by the same H1 mechanism.

## O. What remains uncertain

- **The exact reason the pre-existing `permissions` row's `name` column does not match its `code` column** (§I1) — `PermissionSeeder`'s own code sets `name` equal to `code` for this row, so a live row-dump of the `permissions` table at the moment of collision would be needed to fully explain the mismatch (e.g. a different/earlier write, a subtly different seeder path, or a data artifact from a prior run in the same ephemeral database) — not independently confirmed this pass. This is a narrow, secondary detail; it does not weaken the confirmed finding that a genuine `UniqueConstraintViolationException` on the `code` column occurs.
- Whether GAP-040's other 4 approved surfaces (beyond `PerformanceMonitoringTest`/`DashboardPerformanceTest`, which were directly probed for §D-G, and the `mysql-parity` surface directly probed for §H1) would show a *visible* SAVEPOINT failure if run as the first test of a fresh process — the underlying implicit-commit mechanism is confirmed present for all of them by code-path necessity (§L), but visible failure was only independently reproduced for the two Performance test files; the false-green marker-disappearance mechanism (§H1) was independently confirmed on the `mysql-parity` surface too.
- Whether any other nested-transaction-consuming call pattern besides `Eloquent::firstOrCreate()`/`createOrFirst()`/`updateOrCreate()` could also trigger this same class of visible failure elsewhere in the repo, and whether other seeded/looked-up value pairs (beyond `project.read`) have the same latent `name`/`code` mismatch.
- Whether `DashboardPerformanceTest`'s separate `it_can_load_alerts_with_large_dataset_quickly` latency-budget miss (504.7ms vs 450ms, GAP-045) is itself measurably affected by running in a process where an earlier test's transaction was implicitly defeated (i.e., whether GAP-044 and GAP-045 have any causal interaction beyond both surfacing in the same LIVE run) — not investigated this pass, out of GAP-044's scope per the Owner authorization.
- **What governance action, if any, GAP-040's now-questionable Gate-3 evidence record requires** — this Gate 1 reports the discovery (§H1) but does not decide this; it is an Owner decision, and no GAP-040 file is edited by this PR.
- **What governance action, if any, the confirmed seeding/lookup-key mismatch (§I1, classification B) requires** — reported here as a distinct finding, not fixed, not absorbed into GAP-044's scope; an Owner scoping decision if pursued (per Owner authorization §7).

## P. Scope confirmation / Design Dependency Preflight

The problem is genuinely test-infrastructure-only, same classification as
GAP-040: `tests/TestCase.php`'s three sibling methods create test-only
tables (`interaction_logs`, `project_phases`, `project_tasks`) that have no
migration and no production counterpart; the failing `Permission`/`Role`
models involved (`App\Models\Permission`/`App\Models\Role`, the real
migrated `permissions`/`roles` tables, distinct from GAP-040/GAP-042's
`zena_*` tables) are exercised only through normal Eloquent
`firstOrCreate()` calls in test fixture helpers — no production
schema/migration/RBAC-authorization-behavior/tenant-semantics change is
implicated by anything traced in this Gate 1. **No Design Dependency
Preflight is triggered.** Should Gate 2 design work later reveal that a
complete fix requires touching production schema/migrations/RBAC
authorization/tenant semantics, work must stop and the appropriate preflight
must run before any such design proceeds — nothing in this Gate 1's evidence
currently indicates that will be necessary (the working control case,
§M, shows the same class of defect was fixable entirely within
`tests/TestCase.php` for GAP-040).

## Q. Evidence sources

| # | Evidence | Type | Source |
|---|---|---|---|
| 1 | Full probe log, `PerformanceMonitoringTest.php` (91 `[GAP044-PROBE]` lines, all 10 tests, transactionLevel/pdoInTransaction/CONNECTION_ID/table-existence at every `setUp()` boundary) | **LIVE** | GitHub Actions run `32557247386`, job `96993284455`, disposable branch `investigate/GAP-044-disposable-evidence-harness` (deleted after capture, never merged) |
| 2 | Full probe log, `DashboardPerformanceTest.php` (171 `[GAP044-PROBE]` lines, all 19 tests) | **LIVE** | same run, job `96993284478` |
| 3 | Exact failing stack traces (both files), `Tests: 1 failed, 9 passed`/`Tests: 2 failed, 17 passed` | **LIVE** | same run/jobs |
| 4 | `tests/TestCase.php` source (`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`/`ensureSqliteZenaRbacTables`/`zenaRbacBootstrapSchema`) | **STATIC** | `tests/TestCase.php` at canonical `main` `4a89693b` |
| 5 | `tests/Traits/TenantUserFactoryTrait.php`, `tests/Support/SSOT/FixtureFactory.php` source | **STATIC** | same baseline |
| 6 | `Illuminate\Database\Concerns\ManagesTransactions` / `Illuminate\Database\Eloquent\Builder::withSavepointIfNeeded`/`createOrFirst`/`firstOrCreate` / `Illuminate\Foundation\Testing\RefreshDatabase` framework source | **STATIC** | `vendor/laravel/framework/src/Illuminate/Database/...`, `vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php` — read from the sibling checkout at `/Applications/XAMPP/xamppfiles/htdocs/zenamanage-golden/vendor` (this worktree has no `vendor/` installed; the sibling checkout's `composer.lock`-pinned `laravel/framework` version matches this repo's) |
| 7 | Migration inventory confirming `interaction_logs`/`project_phases`/`project_tasks` have no migration | **STATIC** | `grep -rl "interaction_logs" database/migrations/`, `grep -rl "project_tasks" database/migrations/` (both empty), `database/migrations/2026_07_10_090000_create_design_items_table.php` (comment referencing missing `project_phases`) |
| 8 | GAP-040's Gate 1/2/3 records confirming approved scope was `ensureSqliteZenaRbacTables()` only | **STATIC** (documentary) | `docs/owner-decisions/GAP-040/01-request.md`, `02-design.md`, `03-release.md`; `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md`; `docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md`; `docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md` |
| 9 | GAP-043's Gate 3 LIVE evidence, prior masking-interaction note | **STATIC** (documentary) | `docs/owner-decisions/GAP-043/03-release.md`, `docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md` |
| 10 | Blast-radius grep: which `RefreshDatabase` classes use `FixtureFactory`/`TenantUserFactoryTrait`, which GAP-040 surfaces `extends TestCase` | **STATIC** | `grep -rl "@group zena-invariants" tests/`, `grep -rl "@group mysql-parity" tests/`, direct `grep -n "extends TestCase"` on GAP-040's 5 approved surface files |
| 11 | Writer/verifier discriminating probe log confirming GAP-040's cold-start rollback proof is false-green (§H1): marker visible immediately after write, still visible before verifier's `parent::setUp()`, `RefreshDatabaseState::$migrated=false` at that point, marker gone after — reproduced on 2 independent runs | **LIVE** | GitHub Actions runs `32560499974` and `32560820613`, job `97001212756`/`97001970755` (`test-routes-guardrails`, `--group=mysql-parity`), disposable branch `investigate/GAP-044-gap040-proof-discriminator`, throwaway Draft PR #284 (closed unmerged, branch deleted after capture) |
| 12 | First 2 masked-exception-capture attempts (§I1, historical): both the manual replica and the faithful real-trait call succeeded cleanly with no exception, under the confirmed implicit-commit condition but without job-level seeding | **LIVE** | GitHub Actions runs `32560499974`/`32560820613`, job `97001970755` (`test_c_capture_masked_exception`/`test_d_faithful_reproduction`) |
| 13 | Exact-match harness: original `Throwable` captured via runtime-only vendor instrumentation (never committed) immediately before Laravel's rollback call, identical on both Performance test files — `UniqueConstraintViolationException`, SQLSTATE 23000/MySQL 1062, duplicate `code='project.read'` on `permissions_code_unique` | **LIVE** | GitHub Actions run `32562591732`, jobs `97006383932` (`PerformanceMonitoringTest.php`) and `97006383949` (`DashboardPerformanceTest.php`), disposable branch `investigate/GAP-044-exact-match-harness` (workflow-dispatched, deleted after capture) |
| 14 | `database/seeders/PermissionSeeder.php`, `database/seeders/DatabaseSeeder.php` source confirming `PermissionSeeder` creates a `code='project.read'` row as part of the job's `db:seed` step | **STATIC** | same baseline |

## Explicit exclusions

Does not modify `tests/TestCase.php`, `FixtureFactory.php`,
`TenantUserFactoryTrait.php`, `database/seeders/PermissionSeeder.php`, any
workflow file, any migration, or any application/production code — this
document and its companion `docs/owner-decisions/GAP-044/01-request.md` are
the only contents of this Gate-1 PR. All three disposable evidence-harness
branches used across this investigation
(`investigate/GAP-044-disposable-evidence-harness`,
`investigate/GAP-044-gap040-proof-discriminator` via throwaway Draft PR
#284, and `investigate/GAP-044-exact-match-harness`) have been
deleted/closed unmerged and contributed no content to this PR's diff — this
includes the runtime-only vendor instrumentation
(`scripts/ci/gap044-disposable-patch-vendor.php`), which patched only the
`composer install`-generated, gitignored `vendor/` copy in that disposable
CI job and was never committed to any branch's `vendor/` directory, and was
never part of the disposable branch's own `vendor/` (gitignored, not
tracked). Does not reopen or modify GAP-040's already-released decision
record (`docs/owner-decisions/GAP-040/*`) — §H/§H1's findings are reported
as new evidence for Owner consideration, not as a unilateral
reclassification of GAP-040's release status. Does not touch GAP-041 (selector truthfulness —
only its already-Owner-approved overlay mechanism was reused, unmerged, for
evidence-gathering, exactly as GAP-043's precedent established), GAP-042
(RBAC production-fidelity — unrelated), or GAP-045 (latency budget — the one
`DashboardPerformanceTest` failure attributable to GAP-045 is noted as
structurally distinct and not investigated further here). Does not fix the
confirmed seeding/lookup-key mismatch (§I1) — documented and classified
only, not remediated, not absorbed into GAP-044's scope. Does not select a
technical fix mechanism for GAP-044 — that is Gate 2's decision, if
authorized. `OPERATIONAL_GAP_REGISTER.md` is not modified by this Gate-1 PR.
