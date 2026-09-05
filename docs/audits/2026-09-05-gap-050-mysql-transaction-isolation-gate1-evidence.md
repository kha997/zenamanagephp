# GAP-050 — Gate 1 Forensic Audit: MySQL Invariant Transaction Isolation / CI Reliability

Date: 2026-09-05. Diagnostic-only audit. No tenant/RBAC/product behavior,
test assertions, or CI workflows were modified. All reproduction was
performed in disposable `git worktree`/Docker environments outside the
canonical repository tree; nothing described here is committed except this
document and the accompanying `01-request.md` packet.

## Owner Summary

`Zena RBAC/Tenant Invariants (MySQL parity)` fails
**deterministically (4/4, 100%) in this session** on canonical `main`
(`475e30eeb549042649b3871d175225fff80bdb11`) using the **exact CI
invocation** (`scripts/ci/zena-invariants-mysql` → `php artisan test
--group=zena-invariants`, real MySQL 8.0), on
`ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
(expected `E404.NOT_FOUND`, actual `TENANT_INVALID`, both HTTP 404). This
confirms and extends GAP-049's own diagnostic-only investigation (retained
locally at
`.superpowers/sdd/2026-09-03-gap-049-production-deployment-implementation/mysql-invariant-{provenance,rootcause}-report.md`
in worktree `agent-a7f5c8ceca0f6d013`, not committed), which had already
established via MySQL general-query-log evidence that a bare `ROLLBACK`
(not `ROLLBACK TO SAVEPOINT`) fires mid-suite immediately before
`TenantIsolationMiddleware::handle()`'s `Tenant::find($tenantId)` lookup,
wiping the tenant row the failing test had itself just inserted.

**This Gate 1 goes one step further and identifies the actual trigger
mechanism**, not just the symptom: Laravel's own `RefreshDatabase` trait
contains a documented internal "self-healing" check
(`vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php:158-159`)
that, at every test's teardown, resets `RefreshDatabaseState::$migrated =
false` whenever `PDO::inTransaction()` unexpectedly reads `false`. This
causes the **next** `RefreshDatabase` test in the same PHPUnit process to
silently re-run a full `migrate:fresh` (drop-and-recreate every table) via
`refreshTestDatabase()` — **on the live connection all subsequent tests
share** — mid-suite, with no log line announcing it. Direct MySQL
general-query-log evidence from this session's own reproduction (§E) proves
this fires **three additional, unscheduled times** during a single
41-test `--group=zena-invariants` run beyond the CI script's own initial
`migrate:fresh --force` (at `16:04:32`, `16:06:45`, `16:09:10`, in addition
to the intended one at `16:02:42`), always on the same live connection ID
(`72`) the test process uses throughout. The repo's own GAP-044-fixed DDL
helpers (`ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/
`ensureProjectTasksTable()`) are confirmed, this run, to **not** be the
trigger (their own built-in probe shows `pdo_in_transaction` stays `true`
before and after all three, every time). The most likely remaining trigger
surface — not proven to PHP-call-site certainty in the time available for
this Gate 1, consistent with GAP-049's own finding — is the nested
`SAVEPOINT`-based transaction Eloquent's `withSavepointIfNeeded()` opens for
every `firstOrCreate()`/`updateOrCreate()` call, which 8 of the 15
`@group zena-invariants` test files (including the failing one, in its own
`setUp()` and test bodies) use densely.

This is **confirmed order/volume-dependent, not per-file**: the failing
test class passes 100% reliably in isolation (this session, and GAP-049's
Round 1) but fails 100% reliably as part of the full `--group=zena-invariants`
suite (this session, 4/4; GAP-049's Round 2, 3/3; GAP-049's own base-commit
reproduction). This is consistent with — and materially explains — GAP-049's
own finding that the *specific* failing assertion varies between runs
(sometimes a different test in the same class fails instead): which test's
teardown happens to observe `PDO::inTransaction() === false` and which
test's `setUp()` happens to inherit the resulting stale
`RefreshDatabaseState::$migrated = false` flag is itself timing/order
sensitive.

**No tenant/RBAC/product code was touched by this Gate 1.** All
reproduction happened in disposable worktrees/containers; the only
"instrumentation" used was a temporary, never-committed `DB::listen()` +
`PDO::inTransaction()` probe added directly to a throwaway worktree's
`tests/TestCase.php` and discarded afterward (§D). This document is
evidence, not a fix proposal for immediate implementation — see §J for
remediation candidates requiring Owner Gate-2 authorization before any code
change.

## A. Canonical starting point and exact reproduction commands

- Canonical SHA (confirmed matches `origin/main`):
  `475e30eeb549042649b3871d175225fff80bdb11`
  (`docs+feat(GAP-049): production deployment hardening — Owner Gate-3
  Round-2 approved`).
- Reproduction environment: isolated `git worktree add /tmp/gap050-audit
  475e30eeb549042649b3871d175225fff80bdb11` (never checked out to another
  ref; the pinned session worktree on `docs/GAP-032-document-status-semantics`
  was never touched). `vendor/` copied (not symlinked) from the main
  checkout per the repo's documented worktree-vendor-symlink gotcha;
  `composer.lock` confirmed byte-identical between the two trees before
  copying; `composer dump-autoload` run in the new worktree.
- Real MySQL 8.0 via Docker (`docker run --rm -d -e MYSQL_ROOT_PASSWORD=root
  -e MYSQL_DATABASE=laravel -p 3307:3306 mysql:8.0`; port 3307 used because
  3306 was occupied by an unrelated local container on this machine; the
  actual host/port do not affect the CI script, which resolves them via
  `scripts/ci/lib/mysql-fail-closed.sh`'s `zena_mysql_resolve_env()`).
- Exact CI invocation reproduced verbatim, via `scripts/ci/zena-invariants-mysql`
  (the same script `.github/workflows/automated-testing.yml`'s
  `zena-invariants-mysql` job runs):
  ```
  source scripts/ci/lib/mysql-fail-closed.sh
  zena_mysql_resolve_env
  zena_mysql_print_config
  zena_mysql_ensure_connection
  zena_mysql_preflight_connection
  php artisan optimize:clear
  php artisan migrate:fresh --force
  php artisan migrate:status
  zena_mysql_ensure_connection
  php artisan test --group=zena-invariants
  ```
- One local-machine-only environment fix was required and does not affect
  CI: two broken Homebrew PHP extensions (`imagick`, `memcached`) print
  `PHP Startup` warnings to **stdout** on this specific macOS PHP 8.2 CLI
  install, corrupting `$(php -r '...')` command substitutions used inside
  `mysql-fail-closed.sh`. Worked around locally via a scoped
  `PHP_INI_SCAN_DIR` pointing at a copy of `conf.d` with those two `.ini`
  files removed. Not a repo defect; GitHub Actions runners do not have this
  problem. No system-wide PHP configuration was modified.

## B. Failure signature and reproducibility rate

```
1) Tests\Feature\Zena\ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource
Failed asserting that two strings are identical.
--- Expected
+++ Actual
@@ @@
-'E404.NOT_FOUND'
+'TENANT_INVALID'

at tests/Feature/Zena/ZenaApiContractPhase2InvariantTest.php:70
Tests: 1 failed, 40 passed (1271 assertions)
```

**Reproduced 4 times out of 4 attempts this session** (100%), using the
exact CI command against the exact canonical SHA, across ~300-415s runs
each. Identical assertion, identical file/line, identical HTTP status (404
both expected and actual — only the JSON `error.code` differs) every time.

This matches GAP-049's own Gate-3 CI record exactly: 4/4 failed CI reruns
on the implementation branch head, all the identical test/signature, and
GAP-049's own local diagnostic reproduction (3/3 identical failures against
both the branch head and the canonical base commit
`dfd936dbbd88400013488e0bb2e3bb21e126e535`).

## C. Deterministic/minimal reproduction

**Not file-isolated.** Running only the failing test class
(`php vendor/bin/phpunit -c phpunit.mysql.xml
tests/Feature/Zena/ZenaApiContractPhase2InvariantTest.php`) against a fresh
`migrate:fresh`'d real MySQL 8.0 database **passes cleanly, every time**
(this session; also GAP-049's Round 1 finding). The failure requires the
**full `--group=zena-invariants` invocation** (all 15 files, 41 tests,
honoring `phpunit.mysql.xml`'s named-testsuite `<exclude>` directives) or a
sufficiently large multi-file subset (GAP-049's Round 2 found
`tests/Feature/RouteHygieneTest.php tests/Feature/Zena` — 2 files — also
sufficient, on both the branch head and the canonical base commit).

This positively rules out: a defect local to the failing test's own logic,
a data problem specific to that one file's fixtures, or a `RefreshDatabase`
transaction-open/close bug that manifests on the very first test of a
process (GAP-044's family of bugs) — the failing test is never the first
`RefreshDatabase` test to run in this reproduction. It positively confirms:
this is a **cross-test, cross-process-lifetime state leak**, order- and
volume-dependent, consistent with GAP-049's own conclusion.

## D. Diagnostic instrumentation used (never committed)

A temporary probe was added directly to the disposable worktree's
`tests/TestCase.php::setUp()`, gated behind `getenv('GAP050_PROBE') ===
'1'` (inert unless explicitly exported), that:

1. At the start of every test's `setUp()` (immediately after
   `parent::setUp()`), logs the test name, `DB::connection()`'s
   `CONNECTION_ID()`, Laravel's PHP-side `DB::transactionLevel()`, and the
   PDO driver's own `PDO::inTransaction()` reading.
2. Registers `DB::listen()` to log any query whose SQL text matches
   `/^(COMMIT|ROLLBACK|SAVEPOINT|RELEASE)/i`, tagged with the current test
   name and a filtered backtrace.

**Finding from (2): zero matches across the entire 41-test run.** This is
itself a real, useful negative result: Laravel's `ManagesTransactions`
trait issues `BEGIN`/`COMMIT`/`ROLLBACK`/`SAVEPOINT ...`/`ROLLBACK TO
SAVEPOINT ...` via **raw `$pdo->exec()`/native `PDO::commit()`/
`PDO::rollBack()` calls**, which bypass `Connection::run()` and therefore
never reach `DB::listen()`. Any future instrumentation attempt must use
MySQL's own general query log (as GAP-044 and this Gate 1 both did, §E)
or `DB::listen()` is a dead end for this class of bug.

**Finding from (1): every single one of the 41 `setUp()` boundaries in this
run showed internally consistent state** — `txLevel` and `pdoInTx` were
always both `0`/`false` (non-`RefreshDatabase` test, e.g.
`RouteHygieneTest`, which does not `use RefreshDatabase` at all) or both
`1`/`true` (a `RefreshDatabase` test with a freshly opened transaction).
**No desynced pair (`txLevel=1` with `pdoInTx=false`, or vice versa) was
ever observed at any `setUp()` boundary.** Combined with §E's finding that
`RefreshDatabase`'s own teardown callback force-`rollBack()`s and
`disconnect()`s unconditionally on every test regardless of what it found
(see §F), this means: whenever the desync happens, it is self-healed by
Laravel's own teardown logic before the *next* test's `setUp()` runs — the
desync is only ever observable transiently, mid-test or at
teardown-in-progress, which is why a `setUp()`-boundary-only probe (as used
here) cannot directly catch it, and why GAP-044 needed a purpose-built
before/after-single-helper probe to catch its (different, already-fixed)
instance of this defect family.

## E. Direct MySQL general-query-log evidence (this session's own capture)

MySQL's general query log was enabled for one full reproduction run
(`SET GLOBAL general_log_file=...; SET GLOBAL log_output='FILE'; SET GLOBAL
general_log='ON'`) against the same Docker MySQL 8.0 instance, exact CI
invocation, canonical SHA. 38,857 log lines captured; the run failed with
the identical signature (§B). Grepping for `create table \`tenants\`` (a
statement that should occur **exactly once** per process — the CI script's
own pre-PHPUnit `migrate:fresh --force`) instead shows it **four times**,
all timestamped **after** PHPUnit itself started (`16:04:12`), all on the
**same connection ID (`72`)** the PHPUnit process uses for every other
query in the run:

```
16:02:42  connId 70   create table `tenants` ...   <- CI script's own migrate:fresh --force (separate OS process, expected)
16:04:32  connId 72   create table `tenants` ...   <- UNSCHEDULED, inside the PHPUnit process itself
16:06:45  connId 72   create table `tenants` ...   <- UNSCHEDULED
16:09:10  connId 72   create table `tenants` ...   <- UNSCHEDULED
```

Each of the 3 unscheduled occurrences is preceded, within the same
few-hundred-millisecond window, by a full `drop table
laravel.accounts, laravel.approvals, ... laravel.migrations, ...` statement
listing every table in the schema by name (Laravel's `migrate:fresh` DROP
phase) — i.e., this is not a partial/incremental schema change, it is a
complete, unscheduled schema wipe-and-rebuild, mid-test-suite, on the exact
connection every other test in the run depends on for its own transaction
isolation.

Correlating against the probe's `setUp()` markers (§D) for this same run:
the first unscheduled `migrate:fresh` (`16:04:32`) falls between test #2
(`RouteHygieneTest::test_zena_public_allowlist_is_consistent`, which does
**not** `use RefreshDatabase`) and test #3
(`ZenaApiContractPhase2InvariantTest::test_document_show_mismatch_header_returns_tenant_invalid`,
the failing class's first test) — i.e., on the very first `RefreshDatabase`
test to run after two non-transactional tests.

## F. Root cause of the unscheduled `migrate:fresh`: Laravel's own `RefreshDatabase` self-healing check

Direct read of this repo's exact vendored copy of the framework
(`vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php`,
lines 151-165):

```php
$this->beforeApplicationDestroyed(function () use ($database) {
    foreach ($this->connectionsToTransact() as $name) {
        $connection = $database->connection($name);
        $dispatcher = $connection->getEventDispatcher();

        $connection->unsetEventDispatcher();

        if ($connection->getPdo() && ! $connection->getPdo()->inTransaction()) {
            RefreshDatabaseState::$migrated = false;
        }

        $connection->rollBack();
        $connection->setEventDispatcher($dispatcher);
        $connection->disconnect();
        // ...
```

This callback runs at **every single test's teardown** (`beforeApplicationDestroyed`,
fired as the Laravel application container is torn down between tests). It:

1. Checks whether the PDO driver itself (not Laravel's PHP-side counter)
   still reports an open transaction. If not — meaning *something* during
   the test caused the real transaction to end early (an implicit commit,
   a driver-level auto-commit, or any other mechanism) — it marks the
   schema as needing a full re-migration on the next `RefreshDatabase`
   test, via `RefreshDatabaseState::$migrated = false`.
2. **Unconditionally** calls `rollBack()` and `disconnect()` regardless of
   what it found in step 1.

And in `refreshTestDatabase()` (lines 81-94): the **next** test whose
`setUp()` reaches this trait checks `if (! RefreshDatabaseState::$migrated)`
and, if false, calls `$this->migrateDatabases()` →
`$this->artisan('migrate:fresh', ...)` — a full drop-and-recreate — **before**
opening that test's own transaction.

This is a genuine, intentional Laravel framework defense mechanism (not a
repo-authored bug): it exists precisely to detect "something broke
transactional test isolation" and defensively re-migrate rather than let
subsequent tests silently share corrupted/leaked state. **The repo's own
`tests/bootstrap.php` comment (written for GAP-039) already names this
exact mechanism** as something to guard against re-arming
(`RefreshDatabaseState::$migrated` being reset), citing a *different*
concern (the FK-disabling migration's MySQL branch re-running) — but the
guard added there (skipping `TestCase::ensureTestingSchema()`'s own
explicit reset when `ZENA_INVARIANTS_DB=mysql`) does **not** and cannot
prevent *this* self-healing reset, because this one is internal to
`RefreshDatabase.php` itself and fires unconditionally whenever `PDO::inTransaction()`
reads false at teardown — it is not the same code path GAP-039 guarded.

**This confirms and sharpens GAP-049's own conclusion.** GAP-049's
general-log evidence found a bare `ROLLBACK` immediately before a
`Tenant::find()` call that returned null for a tenant the same test had
just inserted. This Gate 1 additionally proves that *unscheduled,
mid-suite full-schema resets are actually happening* on the shared
connection, timed exactly where GAP-049's symptom test runs relative to
other tests in the suite — a `migrate:fresh` triggered by this self-healing
check, executing on the live connection between two tests, is fully
sufficient by itself to explain a subsequent test's tenant/document rows
"vanishing" (they were never in the fresh schema to begin with, or the
transaction wrapping them was implicit-committed away by the DDL) —
independent of, and consistent with, GAP-049's own `ROLLBACK`-based
account of the same underlying instability.

## G. What has NOT been pinned to exact-certainty in the time available for this Gate 1

The trigger for the very *first* `PDO::inTransaction() === false` reading
that starts this cascade (§F step 1) was not traced to an exact PHP call
site. `DB::listen()` cannot see it (§D). The two most plausible mechanisms,
neither excluded nor confirmed with certainty this pass:

1. **Nested-transaction/`SAVEPOINT` handling inside `firstOrCreate()`/
   `updateOrCreate()`** (`Eloquent\Builder::withSavepointIfNeeded()` →
   `Connection::transaction()` → `ManagesTransactions::createSavepoint()`/
   `performRollBack()`), which GAP-044's own audit already traced in
   detail for a *different* trigger (DDL-on-transacted-connection) and
   found opens a real, raw `SAVEPOINT trans2` statement outside
   `Connection::run()` (so also invisible to `DB::listen()`). 8 of the 15
   `@group zena-invariants` files, including the failing one, call
   `firstOrCreate()`/`updateOrCreate()` in every test's `setUp()` and/or
   test body (§H) — dense enough usage that a rare per-call desync risk
   would plausibly surface within a 41-test run even if each individual
   call is very unlikely to trigger it.
2. Some other implicit-commit-causing statement elsewhere in the request
   lifecycle exercised by these tests (permission/role writes, document
   creation, auth token issuance) that was not enumerated in the time
   available.

Definitively resolving which exact call flips `PDO::inTransaction()` to
`false` would require `DB::listen()`-independent instrumentation (e.g. a
`register_shutdown_function`-free wrapper around `PDO::exec()`/`PDO::query()`
itself, or PHP-level tracing of `ManagesTransactions::createSavepoint()`/
`performRollBack()` call sites specifically) — a reasonable next step for
Gate 2/implementation, not required to establish that the mechanism in §F
is real and sufficient to explain the observed symptom.

## H. Blast radius

**Confirmed affected job:** `Zena RBAC/Tenant Invariants (MySQL parity)`
(`.github/workflows/automated-testing.yml`, `zena-invariants-mysql` job) —
the only job whose CI-exact invocation was reproduced this Gate 1.

**Files carrying `@group zena-invariants`** (15, confirmed via `grep -rl
"@group zena-invariants" tests/`): all of `tests/Feature/Zena/*.php` plus
`tests/Feature/RouteHygieneTest.php` (2 of its 3 methods only). **8 of
these 15 files use `firstOrCreate()`/`updateOrCreate()`** (the leading
suspect per §G):
`ZenaListContractInvariantTest`, `ZenaApiContractPhase2InvariantTest`,
`ZenaAuditPiiInvariantTest`, `ZenaRbacTenantSmokeTest`,
`ZenaAuditInvariantTest`, `ZenaAuthFlowInvariantTest`,
`ZenaErrorEnvelopeInvariantTest`, `ZenaRbacPermissionStoreInvariantTest`.
Any of these tests, anywhere in the run, is a candidate for being the
"trigger" test whose teardown observes `PDO::inTransaction() === false`;
which specific test happens to be first affected in any given run is
itself non-deterministic, which is consistent with GAP-049's own
observation that the *specific* failing assertion (and even which test
fails) varies between reproductions.

**Other real-MySQL, multi-test-per-process CI jobs sharing the same
structural risk** (same `RefreshDatabase` + real MySQL 8.0 + many tests in
one PHPUnit process pattern; **not independently reproduced or verified
this Gate 1** — flagged from static inspection only):
- `routes-guardrails.yml`'s `--group=mysql-parity` step
  (`TenantIsolationProjectsTest` + every `@group mysql-parity` test).
- `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`,
  `treasury-check-constraints-mysql` (`scripts/ci/*-mysql` entrypoints,
  same `mysql-fail-closed.sh` pattern).

None of these were run against real MySQL as part of this Gate 1; their
inclusion here is a scope-of-risk flag for Gate 2, not a proven finding.

**GAP-044's own fix (`ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/
`ensureProjectTasksTable()` routed through the isolated `zena_ddl_bootstrap`
connection) is confirmed, this session, to remain effective** and is *not*
implicated in this specific failure (§D's embedded GAP-044 probe read
`pdo_in_transaction: true` before and after all three helpers, every time,
in every reproduction run this Gate 1 performed).

## I. Production-risk assessment

**No production risk identified by this Gate 1.** The mechanism in §F is
specific to `RefreshDatabase`'s **test-only** teardown lifecycle
(`beforeApplicationDestroyed`, a PHPUnit-application-container-teardown
hook that has no equivalent in a production request lifecycle) and to the
CI script's own pattern of running dozens of independent `RefreshDatabase`
tests inside a single long-lived PHP process against real MySQL — a
scenario that does not occur in production (each production HTTP request
is its own process/request lifecycle in this application's deployment
model, per GAP-049's own architecture; no equivalent of "40 tests sharing
one PDO connection across artificial transaction boundaries" exists
outside the test harness). The underlying nested-`SAVEPOINT`
transaction-bookkeeping fragility flagged in §G (shared with GAP-040/
GAP-044's family) is a **test-infrastructure truthfulness risk** (a green
CI does not currently prove real MySQL transactional isolation end-to-end,
per GAP-044's own H1 finding about GAP-040's Gate-3 proof) rather than a
proven production data-integrity risk. This Gate 1 does not certify the
inverse (that production `DB::transaction()`/nested-transaction usage is
risk-free) — that would require a separate, production-code-scoped audit,
out of scope here.

## J. Remediation candidates (Gate 2 decision — not authorized for implementation by this Gate 1)

1. **Split `--group=zena-invariants` into multiple smaller CI job
   invocations** (e.g. one `php artisan test` process per file or per small
   file-group), each getting its own fresh `migrate:fresh` and PHP process.
   *Tradeoff:* directly addresses the volume/order-dependency this Gate 1
   proved (§C) without touching framework internals; increases CI job
   count/runtime overhead (extra `migrate:fresh` cycles, more container
   startup); does not fix the underlying transaction-bookkeeping fragility
   for any single job that still runs multiple `RefreshDatabase` tests
   sequentially, only reduces its probability by shrinking N.
2. **Trace and fix the exact `PDO::inTransaction()`-flipping call site**
   (§G) directly, once found — e.g. if it is confirmed to be
   `firstOrCreate()`/`updateOrCreate()`'s savepoint handling under some
   specific condition, either avoid that pattern in test fixture code
   (replace with explicit `where()->first() ?? create()` outside any
   nested-transaction wrapper) or find/report the actual Laravel-level
   defect if this can't be explained by user-land code alone. *Tradeoff:*
   the only candidate that fixes root cause rather than symptom; requires
   deeper instrumentation work not completed in this Gate 1 (§G); risk of
   scope creep into framework-internals territory the repo cannot directly
   patch (`vendor/`).
3. **Detect and fail loudly on the self-healing reset instead of letting it
   silently re-migrate**, e.g. a `TestCase`-level assertion or CI-step
   check that fails immediately if `RefreshDatabaseState::$migrated`
   becomes `false` after the first test of a `--group=zena-invariants` run
   (turning a currently-silent, misleading-symptom failure into an
   immediately diagnosable one, without necessarily fixing the underlying
   cause). *Tradeoff:* improves truthfulness/diagnosability quickly and
   cheaply; does not fix the flakiness itself; the failing test would still
   fail, just with a clearer error message.
4. **Do nothing / accept as known, documented pre-existing CI debt**,
   continuing the GAP-049-style per-Gate CI exception pattern. *Tradeoff:*
   zero engineering cost; perpetuates a red check indefinitely and risks
   the exception becoming normalized precedent despite GAP-049's explicit
   "not a general waiver" framing.

**Recommended candidate for Gate 2, per this Gate 1's own findings:**
**Candidate 1 combined with Candidate 3** — reduce the CI job's exposure to
this class of cross-test state leak by splitting the invocation (directly
addresses the proven volume-dependency, §C), paired with an explicit,
fail-loud detection of the self-healing reset (turns any future
recurrence — including in a differently-split job — into an immediately
diagnosable failure rather than a confusing downstream symptom mismatch).
Candidate 2 (exact root-cause fix) is the most technically satisfying but
is not yet ready for implementation authorization — Gate 2 should decide
whether to invest further diagnostic time in it or accept 1+3 as
sufficient for now, given Candidate 1+3 do not preclude pursuing Candidate
2 later.

## Explicit non-solutions / rejected masking approaches

- **Rerun-until-green.** Explicitly rejected as evidence by the Owner's own
  prior GAP-049 ruling and by this Gate 1's own instructions. Not used
  anywhere in this investigation; every reproduction run reported here ran
  exactly once and its outcome (pass or fail) is reported as observed, not
  filtered.
- **Changing the test's expected assertion** (accepting `TENANT_INVALID` as
  correct, or relaxing the assertion) — would hide a real test-harness
  defect and was explicitly out of scope per this Gate 1's instructions;
  not attempted.
- **Skipping or excluding the failing test/job** — would remove real
  tenant-isolation contract coverage without fixing anything; not
  attempted or recommended.
- **Retry/sleep-based flake suppression at the CI-step level** — would mask
  the underlying schema-reset race rather than address it, and risks
  silently tolerating a genuine mid-suite full-schema wipe in future,
  larger `--group=zena-invariants` runs where it could interact with a
  differently-timed test in a differently-visible way; not attempted or
  recommended.
- **Disabling `RefreshDatabase`'s self-healing check.** Explicitly
  considered and rejected: this Laravel framework behavior is a legitimate
  safety net (§F) — disabling it would convert a currently-visible (if
  confusing) failure into a silently-corrupted-schema class of bug, which
  is strictly worse.

## Evidence sources

| # | Source | Method | Notes |
|---|---|---|---|
| 1 | `origin/main` at `475e30eeb549042649b3871d175225fff80bdb11` | **STATIC/LIVE** | Confirmed via `git fetch origin main` + `git rev-parse` |
| 2 | `.github/workflows/automated-testing.yml`, `scripts/ci/zena-invariants-mysql`, `scripts/ci/lib/mysql-fail-closed.sh` | **STATIC** | Read directly; exact invocation reproduced verbatim |
| 3 | 4 full `--group=zena-invariants` runs against real MySQL 8.0 in an isolated worktree, canonical SHA | **LIVE** | 4/4 identical failure; ~300-415s each |
| 4 | 1 isolated single-file run of the failing test class | **LIVE** | 4/4 tests pass in isolation |
| 5 | Temporary `DB::listen()` + `PDO::inTransaction()`/`DB::transactionLevel()` probe added to a disposable worktree's `tests/TestCase.php`, never committed | **LIVE** | See §D; 41/41 `setUp()` boundaries logged, 0 `DB::listen()` transaction-control matches |
| 6 | MySQL general query log, one full 41-test run, real MySQL 8.0 (Docker) | **LIVE** | 38,857 lines; 4 `create table \`tenants\`` occurrences (1 expected + 3 unscheduled), all connId 72 |
| 7 | `vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php` (this repo's exact vendored/locked version) | **STATIC** | Read directly; §F quotes lines 81-94, 151-165 |
| 8 | GAP-049's own retained diagnostic reports (`mysql-invariant-{provenance,rootcause}-report.md`, worktree `agent-a7f5c8ceca0f6d013`, not committed) | **STATIC** | Read directly; corroborated, not re-derived from scratch |
| 9 | GAP-044 Gate-1 audit (`docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md`) | **STATIC** | Read directly; §J of that document independently names the same `RefreshDatabase` self-healing mechanism this Gate 1 confirms is actively firing on current `main` |
| 10 | Blast-radius greps: `grep -rl "@group zena-invariants" tests/`, cross-referenced with `firstOrCreate\|updateOrCreate` usage; CI workflow greps for other real-MySQL multi-test jobs | **STATIC** | This session |

## Explicit exclusions

Out of scope for this Gate 1, per its own instructions: any code change to
`app/`, `routes/`, test assertions, or CI workflows; a decision on which
remediation candidate (§J) to implement; exact PHP-call-site tracing of the
`PDO::inTransaction()` flip (§G, flagged as future work); verification of
the other real-MySQL CI jobs named in §H's second list (flagged, not
reproduced).
