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
CONFIRMED FALSE-GREEN — see §H1 (new) below.** A parallel attempt to capture
the masked original exception behind the `ROLLBACK TO SAVEPOINT` path (§I)
was also made using the same harness; it did **not** succeed in reproducing
the original throw and is reported honestly as unresolved (§I).

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

### I1. Attempt to capture the masked original exception (Owner authorization Part B) — attempted, NOT reproduced, reported honestly

Per Owner instruction, an attempt was made to capture the original,
unmasked `Throwable` inside `createOrFirst()`'s wrapped `create()` call —
the exception that must occur for the `ROLLBACK TO SAVEPOINT` path to be
reached at all — using disposable instrumentation only (no vendor edits).
Since Eloquent's `createOrFirst()`/`withSavepointIfNeeded()` cannot be
hooked from outside without editing vendor code, the disposable harness
(same branch as §H1) instead **manually reimplemented the identical
operation**: it opened the same savepoint Eloquent would (via
`DB::connection()->beginTransaction()`, gated on the identical
`transactionLevel() > 0` precondition `withSavepointIfNeeded()` checks),
attempted the same `Permission::create()`/`Role::create()` calls with the
same values, and caught any resulting exception in its own try/catch
**before** attempting any rollback — so a genuine original exception, had
one occurred, would have been captured unmasked.

Two variants were run, both on a forced cold start (`RefreshDatabaseState::$migrated
= false`, same technique as §H1), both confirmed to be operating under the
exact same implicit-commit condition (`pdoInTransaction=false` at entry,
matching §D-G):

1. **Manual replica** (`test_c_capture_masked_exception`): reimplemented `ensurePermissionAttached()`'s exact logic (role `project_manager`, permissions `project.read`/`project.write`, matching `createTenantUserWithRbac('project_manager', 'project_manager', ...)`'s real call shape) — **`Permission::create()` succeeded cleanly for both permissions, no exception thrown.**
2. **Faithful reproduction** (`test_d_faithful_reproduction`): called the **real, unmodified** `Tests\Traits\TenantUserFactoryTrait::createTenantUser()` directly (the test class simply `use`s the trait, exactly as any ordinary consuming test does — no trait file edited), with the exact real role (`project_manager`) — **also succeeded cleanly, no exception thrown, no `SAVEPOINT`/PDOException of any kind.**

**Result: the original masked exception was NOT reproduced by this Gate 1,
despite two independent attempts including a faithful call to the real,
unmodified trait under the confirmed implicit-commit condition.** This
means the LIVE `SAVEPOINT trans2 does not exist` failures observed in §A
require some additional condition beyond "cold start + implicit commit +
`firstOrCreate` on `project_manager`/`project.read`/`project.write`" that
this harness did not reproduce. A plausible, but **not confirmed**,
candidate difference: the real `performance-tests` CI job's DB-setup step
runs `php artisan migrate` **and** `php artisan db:seed --env=testing
--force` before PHPUnit starts, whereas `routes-guardrails.yml` (where this
harness ran, to reuse its already-truthful `--group=mysql-parity` selector)
runs only `php artisan migrate:fresh` with **no seeding** — meaning the two
environments' `roles`/`permissions` table contents differ before the
in-process `RefreshDatabase`-internal `migrate:fresh` and the subsequent
implicit-commit cycle occur. This difference was not tested further this
pass.

**Per Owner instruction, no assumption is made that this is "expected
Eloquent `createOrFirst` race handling."** The original exception's
identity remains unresolved. What is separately proven with certainty
(§D-H, independent of this unresolved item) is the exact mechanism by which
*any* exception reaching that call path, whatever its cause, becomes the
misleading `SAVEPOINT trans2 does not exist` message.

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
`create()` call (needed to reach the `ROLLBACK TO SAVEPOINT` code path at
all) is a genuine `UniqueConstraintViolationException` racing against
already-seeded `roles`/`permissions` rows from the job's one-time
`db:seed`. (SECONDARY, ATTEMPTED, NOT CONFIRMED — NOT REPRODUCED)**
- Evidence for: `createOrFirst()`'s own purpose (documented: "Attempt to create the record. If a unique constraint violation occurs...") is specifically designed around exactly this race; the failure is observed only on the very first `RefreshDatabase` test of a process, when the job-level one-time `db:seed` step's data and the in-test `RefreshDatabase`-internal `migrate:fresh` (which wipes and does not reseed) interact in a way not fully traced this pass.
- Evidence against: **actively tested and not observed.** §I1 reports two independent disposable-harness attempts — a manual replica of `ensurePermissionAttached()`'s exact logic, and a faithful call to the real, unmodified `TenantUserFactoryTrait::createTenantUser()` — both under the confirmed implicit-commit cold-start condition, both with the correct real role/permission values. Neither threw any exception at all; `Permission::create()` succeeded cleanly both times. This does not disprove H2 (the real `performance-tests` job's `db:seed` step, absent from the `routes-guardrails.yml` environment this harness ran in, is a plausible missing ingredient — §I1), but it means H2 is **not confirmed** and must not be assumed.
- Discriminating experiment: performed this pass (§I1) via disposable instrumentation; **inconclusive** — did not reproduce the original exception. A further experiment instrumenting the actual `performance-tests` job's own environment (with its real `db:seed` step) would be needed to resolve this, left for Gate 2 if pursued.

**H3 — GAP-040's Gate-3 cold-start rollback proof is a false-green, defeated
by the same three unfixed sibling methods causing the verifier's
`migrate:fresh` to wipe the marker instead of a genuine rollback removing it.
(CONFIRMED, this revision — see §H1)**
- Evidence for: full server-observed writer/verifier probe chain (§H1) — marker visible immediately after write (proving no real isolation ever existed), still visible immediately before the verifier's own `parent::setUp()`, `RefreshDatabaseState::$migrated=false` at that exact point (proving `migrate:fresh` is about to run), marker gone immediately after — reproduced identically across two independent runs.
- Evidence against: none found.
- Discriminating experiment: performed this pass, a dedicated disposable writer/verifier harness (§H1) — result confirms H3.

Neither H1 nor H3 proposes a fix. H1/H3 are confirmed root-cause/consequence
findings; H2 is an open question about what specifically triggers the
`SAVEPOINT`-masking mechanism to become visible on the first test of a
process, actively tested and left unresolved by this Gate 1.

## O. What remains uncertain

- The exact exception type/message thrown inside `Permission::create()`'s (or `Role::create()`'s) `createOrFirst()`-wrapped closure that triggers the `ROLLBACK TO SAVEPOINT` attempt in the first place (§I1, H2) — **actively investigated this pass via two independent disposable-harness attempts, not reproduced.** The most plausible untested variable is the real `performance-tests` job's `db:seed` step, absent from the `routes-guardrails.yml` environment used for this attempt.
- Whether GAP-040's other 4 approved surfaces (beyond `PerformanceMonitoringTest`/`DashboardPerformanceTest`, which were directly probed for §D-G, and the `mysql-parity` surface directly probed for §H1) would show a *visible* SAVEPOINT failure if run as the first test of a fresh process — the underlying implicit-commit mechanism is confirmed present for all of them by code-path necessity (§L), but visible failure was only independently reproduced for the two Performance test files; the false-green marker-disappearance mechanism (§H1) was independently confirmed on the `mysql-parity` surface too.
- Whether any other nested-transaction-consuming call pattern besides `Eloquent::firstOrCreate()`/`createOrFirst()`/`updateOrCreate()` could also trigger this same class of visible failure elsewhere in the repo.
- Whether `DashboardPerformanceTest`'s separate `it_can_load_alerts_with_large_dataset_quickly` latency-budget miss (504.7ms vs 450ms, GAP-045) is itself measurably affected by running in a process where an earlier test's transaction was implicitly defeated (i.e., whether GAP-044 and GAP-045 have any causal interaction beyond both surfacing in the same LIVE run) — not investigated this pass, out of GAP-044's scope per the Owner authorization.
- **What governance action, if any, GAP-040's now-questionable Gate-3 evidence record requires** — this Gate 1 reports the discovery (§H1) but does not decide this; it is an Owner decision, and no GAP-040 file is edited by this PR.

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
| 12 | Masked-exception-capture attempt log (§I1): both the manual replica and the faithful real-trait call succeeded cleanly with no exception, under the confirmed implicit-commit condition | **LIVE** | same runs, job `97001970755` (`test_c_capture_masked_exception`/`test_d_faithful_reproduction`) |

## Explicit exclusions

Does not modify `tests/TestCase.php`, `FixtureFactory.php`,
`TenantUserFactoryTrait.php`, any workflow file, any migration, or any
application/production code — this document and its companion
`docs/owner-decisions/GAP-044/01-request.md` are the only contents of this
Gate-1 PR. Both disposable evidence-harness branches
(`investigate/GAP-044-disposable-evidence-harness` and
`investigate/GAP-044-gap040-proof-discriminator`, the latter via throwaway
Draft PR #284) have been deleted/closed unmerged and contributed no content
to this PR's diff. Does not reopen or modify GAP-040's already-released
decision record (`docs/owner-decisions/GAP-040/*`) — §H/§H1's findings are
reported as new evidence for Owner consideration, not as a unilateral
reclassification of GAP-040's release status. Does not touch GAP-041 (selector truthfulness —
only its already-Owner-approved overlay mechanism was reused, unmerged, for
evidence-gathering, exactly as GAP-043's precedent established), GAP-042
(RBAC production-fidelity — unrelated), or GAP-045 (latency budget — the one
`DashboardPerformanceTest` failure attributable to GAP-045 is noted as
structurally distinct and not investigated further here). Does not select a
technical fix mechanism for GAP-044 — that is Gate 2's decision, if
authorized. `OPERATIONAL_GAP_REGISTER.md` is not modified by this Gate-1 PR.
