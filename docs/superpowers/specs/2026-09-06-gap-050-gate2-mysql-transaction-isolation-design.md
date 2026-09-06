---
work_id: GAP-050
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-050/02-design.md
---

# GAP-050 — Gate 2 Design/Research: MySQL Invariant Transaction Isolation

Date: 2026-09-06. Design/research only, per Owner Gate-1 approval
(`docs/owner-decisions/GAP-050/01-request.md`, head
`c3d45b62ac7126a718f0093f7f9bebd5714b0742`, PR #303, merged to `main` as
`c5516c582d40e5c94b24c4de98121f64d539dfd1`). **No remediation code was
implemented. No tenant/RBAC/product behavior was changed.** All
investigation below happened in disposable `git worktree`/Docker
environments; nothing was committed except this document.

## Owner Summary

Gate 1 established that `Zena RBAC/Tenant Invariants (MySQL parity)`
fails deterministically because of an unscheduled, mid-suite
`migrate:fresh` triggered by Laravel's own `RefreshDatabase` self-healing
check, and hypothesized (without proof) that dense `firstOrCreate()`/
`updateOrCreate()` usage was the likely trigger for the underlying
`PDO::inTransaction()` desync. **This Gate 2 investigation disproves that
specific hypothesis** and narrows the trigger to a much tighter, precisely
bracketed window using exhaustive, framework-source-level instrumentation
(never committed) across 11 independent full-suite reproductions on
canonical `main` — every one failed identically (11/11, 100%).

**What is now proven:** in the reproducing run, `PDO::inTransaction()`
reads `true` at the end of the failing test's own last instrumented
Eloquent-level statement (`grantPermissionToUser()`'s pivot-table
`syncWithoutDetaching()` calls), and reads `false` by the very first line
of the next HTTP request's first middleware
(`TenantIsolationMiddleware::handle()`). In that exact window: **zero SQL
queries were issued** (proven via a complete `DB::listen()` capture — the
next query after the pivot-sync inserts is `TenantIsolationMiddleware`'s
own `Tenant::find()` call, already reading a dead transaction), **zero
Eloquent-level nested transactions or savepoints were opened** (proven via
exhaustive instrumentation of every method in
`Illuminate\Database\Concerns\ManagesTransactions` — `beginTransaction`,
`createSavepoint`, `commit`, `performRollBack` — none fired in this
window), and **Sanctum's own token guard was never invoked at all**
(proven via instrumentation of `Laravel\Sanctum\Guard::__invoke()` — zero
hits anywhere in the entire 41-test suite, because this test's earlier
`Auth::attempt()` call during login already authenticates the 'web'
session guard, which Laravel's built-in `Authenticate` middleware's
`authenticate()` reuses in preference to re-resolving via Sanctum).

**This is a genuinely important negative result**, directly responsive to
the Owner's instruction not to assume `firstOrCreate()`/`updateOrCreate()`
is the cause until proven: it is not the direct trigger in this
reproduction — the transaction dies later, inside HTTP-kernel middleware
dispatch, with no SQL statement, no Eloquent transaction call, and no
Sanctum guard call visible to instrumentation that fully covers all three.
**The exact PHP (or driver-level) statement responsible is NOT pinned to
certainty in the time invested in this Gate 2** — see §D for the
honest accounting of what remains unresolved and why further narrowing
would require instrumentation below the reach of PHP-level tooling. This
is reported as a genuine, effort-bounded limit, not a stopping point
chosen for convenience.

## A. Method (per-run reproduction discipline)

Every run in this investigation used the exact CI invocation
(`scripts/ci/zena-invariants-mysql` → `php artisan test
--group=zena-invariants`) against canonical `main`
(`c5516c582d40e5c94b24c4de98121f64d539dfd1`, the SHA `origin/main` moved to
after Gate 1's PR #303 merged) in a disposable `git worktree` + Docker
MySQL 8.0 container, discarded after each phase. No rerun-until-green
pattern was used: every run's outcome (pass/fail) is reported as observed,
and instrumentation was progressively refined between runs to answer a
specific open question from the prior run, not to "try again for a better
result." **11 of 11 full-suite runs failed identically** on
`ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`,
confirming the defect is not a rare fluke in this environment — it is the
dominant, near-certain outcome of this exact invocation shape on this
exact canonical SHA.

Instrumentation was added directly to disposable copies of:
- `vendor/laravel/framework/src/Illuminate/Database/Concerns/ManagesTransactions.php`
  (every transaction/savepoint entry/exit point, with `PDO::inTransaction()`,
  Laravel's PHP-side `$this->transactions` counter, `CONNECTION_ID()`, and a
  filtered backtrace)
- `vendor/laravel/framework/src/Illuminate/Foundation/Testing/RefreshDatabase.php`
  (the self-healing check itself, Gate 1's own finding)
- `vendor/laravel/sanctum/src/Guard.php` (every branch of token/guard
  resolution)
- `tests/TestCase.php` (a complete `DB::listen()` capture of every SQL
  statement, tagged with the currently-running test)
- `tests/Feature/Zena/ZenaApiContractPhase2InvariantTest.php` (explicit
  state brackets between each statement of the failing test's body)
- `app/Http/Middleware/TenantIsolationMiddleware.php` and
  `RoleBasedAccessControlMiddleware.php` (entry-point state probes)

All instrumentation was gated behind `GAP050_TRACE=1` (never set in any
committed config), wrote to `/tmp/`-only paths, and was discarded with the
worktree at the end of each phase — **nothing described here touches any
committed file.** A first attempt at correlating separate log files by
wall-clock timestamp (`date('H:i:s.u')`) failed silently (PHP's `date()`
does not actually support sub-second `u` formatting — a real bug in the
diagnostic code itself, caught and fixed mid-investigation) and produced
same-second collisions that made precise ordering impossible; this was
corrected by routing every probe through one shared, monotonically
incrementing sequence counter (`gap050_seq()`, defined once in
`tests/bootstrap.php`) writing to one unified log file, giving unambiguous
statement-level ordering for the final, decisive run.

## B. The proven transaction-state transition

From the final, fully-instrumented run (11th reproduction), strictly
ordered by the shared sequence counter, for the failing test
(`test_document_show_returns_not_found_for_scoped_cross_tenant_resource`):

```
[000111] QUERY  select * from `permissions` where (`code` = ?) limit 1
[000112] QUERY  select * from `roles` where (`name` = ?) limit 1
[000113] QUERY  select * from `role_permissions` where `role_permissions`.`role_id` = ?
[000114] QUERY  insert into `role_permissions` (...)
[000115] QUERY  select * from `user_roles` where `user_roles`.`user_id` = ?
[000116] QUERY  insert into `user_roles` (...)
[000117] QUERY  select exists(select * from `roles` inner join `user_roles` ...
                 inner join ... `role_permissions` ... where ... `name` = ?) as `exists`
[000118] BRACKET after grantPermissionToUser   phpTx=1 pdoInTx=true  connId=119
─────────────────────────────────────────────────────────────────────────
   >>> test's own PHP code ends; ->getJson() begins; NO SQL query fires <<<
─────────────────────────────────────────────────────────────────────────
[000119] TENANT_MW_PROBE handle:enter          phpTx=1 pdoInTx=false connId=119
[000120] TENANT_MW_PROBE after Auth::user()    phpTx=1 pdoInTx=false connId=119 user=<userA id>
[000121] TENANT_MW_PROBE before Tenant::find   phpTx=1 pdoInTx=false connId=119 tenantId=<tenantA id>
[000122] QUERY  select * from `tenants` where `tenants`.`id` = ? limit 1   <- returns null
[000124] event=RefreshDatabase:teardown SELF_HEALING_FIRED connId=119
```

(Sequence 117's `exists(...)` query is `grantPermissionToUser()`'s own
tail activity — it completes, and bracket 118 immediately after it still
reads `pdoInTx=true`. The transition is strictly bracketed between
sequence 118 and 119: same connection ID throughout, `phpTx` — Laravel's
PHP-side counter — never changes, only the PDO driver's own
`inTransaction()` reading flips.)

**Directly proven, not inferred:**
1. The transition happens between the end of the test method's own
   Eloquent-level code and the first line of the next HTTP request's
   first middleware — i.e., somewhere inside Laravel's HTTP kernel
   bootstrap, the global middleware stack (`TrustProxies`, `HandleCors`,
   `PreventRequestsDuringMaintenance`, `ValidatePostSize`, `TrimStrings`,
   `ConvertEmptyStringsToNull`), the `api` middleware group
   (`SubstituteBindings`, `SecurityHeadersMiddleware`,
   `ErrorEnvelopeMiddleware`), or `auth:sanctum`'s `Authenticate::authenticate()`
   guard resolution.
2. **No SQL statement executes in this window** — the query log shows
   nothing between the pivot-sync queries (part of the prior test-body
   code) and `TenantIsolationMiddleware`'s own `Tenant::find()` query,
   which already observes the dead transaction.
3. **`Laravel\Sanctum\Guard::__invoke()` never fires anywhere in the
   entire 41-test run** (zero probe hits across every test, not just the
   failing one) — because this test class's `loginAndReturnToken()` calls
   `Auth::attempt($credentials)` against the **default guard**, which
   Laravel's built-in `Authenticate::authenticate()` middleware then
   re-confirms via `$this->auth->shouldUse($guard)` on success; since the
   'web' session guard's user is already cached in-process from the
   earlier login call (the same `$this->app` container, and hence the
   same guard instances, persist across multiple `->postJson()`/
   `->getJson()` calls within one PHPUnit test method), `Auth::user()`
   resolves without a fresh query and without ever touching Sanctum's
   token-lookup path — **the request's `Authorization: Bearer <token>`
   header is not actually what authenticates this specific request**, a
   separate, real finding about this test suite's authentication
   mechanics, orthogonal to the transaction defect itself (see §F).

## C. What this disproves

**`firstOrCreate()`/`updateOrCreate()` is not the direct trigger.** Every
nested `DB::transaction()`/`SAVEPOINT` call across the entire setUp() and
test body — captured exhaustively via full instrumentation of
`ManagesTransactions` — completed cleanly (matching `beginTransaction:enter`/
`createSavepoint:before`/`createSavepoint:after`/`beginTransaction:exit`
tuples, no unmatched or error-terminated sequences) in the window leading
up to the failure. The transaction was still genuinely open
(`pdoInTx=true`) at the last point any such call executed. This directly
answers the Owner's Gate-2 instruction: the leading Gate-1 hypothesis is
**not assumed proven — it is now affirmatively ruled out** as the
proximate cause in this reproduction.

**GAP-044's previously-fixed DDL helpers are not implicated** (consistent
with Gate 1): `ensureInteractionLogsTable()`/`ensureProjectPhasesTable()`/
`ensureProjectTasksTable()`'s own embedded probe shows `pdo_in_transaction`
staying `true` before and after all three, every run.

**Sanctum's token/guard mechanism is not implicated** — it is not even
invoked for the failing request (§B.3).

## D. What remains unresolved, and why

The exact single statement or driver-level event that flips
`PDO::inTransaction()` from `true` to `false` in the §B window was **not**
pinned to certainty. This is reported honestly as a genuine limit of the
instrumentation approach used, not a convenience stopping point:

- **Every SQL-level instrumentation surface available at the PHP/Laravel
  layer was exhausted for this window**: the query listener (`DB::listen()`)
  captures 100% of statements executed through Laravel's `Connection::run()`
  path; `ManagesTransactions` was instrumented at every one of its 5
  transaction/savepoint-touching methods; Sanctum's entire guard-resolution
  branch tree was instrumented. None fired in the window. This means the
  responsible mechanism, if it is application code at all, is calling
  `PDO::commit()`/`PDO::rollBack()`/`PDO::exec()` **directly** on the PDO
  handle, bypassing Laravel's `Connection` wrapper entirely — a genuinely
  unusual pattern that would not normally appear in idiomatic Eloquent/
  Query-Builder code, and no such call site was found via manual review of
  the request path (`SimpleDocumentController`, `TenantIsolationMiddleware`,
  `RoleBasedAccessControlMiddleware`, the global/`api` middleware group)
  in the time available.
- **The remaining live hypothesis, in order of plausibility, none proven:**
  1. A driver/PDO-internal event (e.g. `pdo_mysql`/`mysqlnd` behavior
     around connection/session state) not mediated by any PHP-level call
     Laravel or this application controls — would require instrumentation
     below PHP (e.g. a MySQL `general_log`/`performance_schema` capture
     correlated to microsecond precision with the PHP-level sequence
     counter used here, not attempted this pass because the SQL-level
     evidence already shows no statement fires in the window, meaning
     even a general-log capture would show nothing informative unless it
     also captured non-statement session events).
  2. Something in Laravel's own testing-HTTP-client machinery
     (`Illuminate\Foundation\Testing\Concerns\MakesHttpRequests::call()`,
     `Kernel::handle()`/`bootstrap()`) that touches the `Connection`
     object's internal PDO handle without issuing a query — read but not
     found in the relevant source during this investigation; a full
     instrumentation pass over every method `Kernel::handle()` transitively
     calls before reaching the first middleware was not completed.
  3. An artifact specific to reusing one `$this->app` container (and
     hence one live PDO connection) across two separate `->postJson()`/
     `->getJson()` calls within a single PHPUnit test method — a pattern
     GAP-040/GAP-044's own investigations did not specifically examine
     either (their scenarios were single-request-per-test).
- **Further narrowing was judged not reasonably achievable within this
  Gate 2's effort budget** given: (a) the window is already narrower than
  any single line of application code the team maintains — it spans only
  framework/package internals (Laravel core + Sanctum) between two
  precisely bracketed points; (b) each additional full-suite reproduction
  costs 4-8 minutes and the mechanism, while 100% reproducible in this
  exact invocation shape, has not been shown to be bisectable further
  without either patching `pdo_mysql` itself or adding
  `performance_schema`-level MySQL session-state tracing correlated to
  microsecond PHP-side timestamps, both of which are a materially larger
  scope than "design/research" and were not attempted.

**This is reported as UNRESOLVED at the exact-line level, STRONGLY
SUPPORTED at the window level** (§B), per the Owner's own three-tier
evidence classification (proven / strongly supported / unresolved).

## E. Blast radius — quantified

Real-MySQL, multi-`RefreshDatabase`-test-per-PHPUnit-process CI jobs share
the *structural precondition* for this defect class (many
`RefreshDatabase` tests sharing one live connection across one PHPUnit
process is what Gate 1 proved lets one test's self-healing reset corrupt a
later test's schema/transaction state) — **not independently verified
against real MySQL this Gate 2, quantified by static count only**:

| CI job | Invocation | RefreshDatabase test files | Structural risk |
|---|---|---|---|
| `Zena RBAC/Tenant Invariants (MySQL parity)` | `--group=zena-invariants` | 15 | **Confirmed defective** (this investigation) |
| `routes-guardrails.yml` MySQL-parity step | `--group=mysql-parity` | 5 (`DatabaseConstraintsTest`, `TenantIsolationProjectsTest`, `ServiceLineFoundationTest`, `ZenaTransactionIsolationColdStartTest`, `BackfillOpportunityServiceLinesTest`) | Same structural pattern, smaller N — not reproduced this pass |
| `treasury-check-constraints-mysql` step 3 (informational-only, not gated) | `tests/Unit/Migrations/Treasury tests/Unit/Models/Treasury` (explicit multi-directory) | 18 | Same structural pattern, **largest N of any real-MySQL job** — not reproduced this pass; already explicitly non-gating per the script's own comment, for an unrelated reason (Treasury CHECK-constraint scope), which may already be silently absorbing this exact defect class without anyone noticing since it never fails the build |
| `rfi-escalation-concurrency-mysql` | single file (`RfiEscalationConcurrencyTest.php`) | 1 | Low — matches this investigation's own single-file isolation runs, which passed 100% |
| `document-workflow-concurrency-mysql` | single file (`DocumentWorkflowConcurrencyTest.php`) | 1 | Low, same reasoning |
| `gap048-service-line-concurrency-mysql` | single file (`OpportunityServiceLineConcurrencyTest.php`) | 1 | Low, same reasoning |

**Recommendation for Gate 3 (if pursued): reproduce the `mysql-parity` (5
files) and Treasury (18 files, currently non-gating) jobs with the same
methodology before considering this defect class fully scoped** — the
Treasury job in particular is a plausible second live instance of the
same mechanism, currently masked by an unrelated pre-existing decision to
not gate on it.

## F. Secondary finding (real, but independent of the transaction defect)

`ZenaApiContractPhase2InvariantTest`'s Bearer-token-authenticated requests
do not actually authenticate via Sanctum's token guard in this test suite
— they authenticate via the 'web' session guard, left active in-process
from an earlier `Auth::attempt()` call during login, because Laravel's
built-in `Authenticate::authenticate()` middleware re-confirms whichever
guard the request already satisfies rather than always trying guards in
the order the route specifies (§B.3). This means these tests' `Authorization`
headers and issued Sanctum tokens are currently **not exercising the code
path they appear to be testing** — a real test-fidelity gap, unrelated to
GAP-050's transaction defect, flagged here as a discovery for a separate,
future Work ID (not GAP-050's scope; no action taken).

## G. Root-cause correction vs. containment — comparative evaluation

| Approach | What it fixes | What it doesn't fix | Cost |
|---|---|---|---|
| **A. Exact root-cause fix** (pending §D resolution) | The actual defect, wherever it turns out to be | N/A once found — but §D shows it is not yet found, and the remaining hypotheses (raw PDO call, driver-level event, testing-client artifact) each need a different, non-trivial diagnostic approach | Highest — unknown effort until the trigger is pinned; may require framework-level (not app-level) fixes the team cannot directly patch (`vendor/`) |
| **B. Suite splitting** (Gate 1's own containment candidate) | Reduces the *probability* of the self-healing cascade manifesting, by shrinking N (fewer `RefreshDatabase` tests sharing one process per job) | Does not fix the underlying transaction-state-loss mechanism; a future test addition could re-grow N past whatever safe threshold is found empirically; provides no guarantee, only probability reduction | Low — CI workflow change only, no app code |
| **C. Fail-loud detection** (Gate 1's own containment candidate) | Converts a confusing downstream symptom (`TENANT_INVALID` instead of `E404.NOT_FOUND`, in an unrelated-looking test) into an immediately diagnosable failure at the moment `RefreshDatabaseState::$migrated` is reset — via a lightweight test-suite-level assertion or a dedicated regression test (§H) | Does not reduce how often the corruption happens, does not identify which statement caused it | Low — a few lines of test/CI-script code |
| **D. Do nothing (status quo / repeat GAP-049's per-Gate exception pattern)** | Nothing | Nothing — leaves an 100%-reproducible-in-this-shape defect unaddressed indefinitely, and risks the "one documented exception" pattern normalizing into a general waiver, which GAP-049's own Owner ruling explicitly warned against | Zero engineering cost, growing governance/trust cost |

**Recommendation, updated from Gate 1's own (which had recommended B+C as
sufficient on its own):** given this Gate 2's finding that the defect is
NOT explained by application-level nested-transaction misuse (ruling out
the most actionable app-level fix), **B+C remain the correct immediate
Gate-3 candidate** — they are honest, low-risk, and address the observed
symptom class without masking it — but **A should remain explicitly open,
not closed off**, because §D's remaining hypotheses (a raw/driver-level
event) are not the kind of thing suite-splitting reliably eliminates
(smaller N reduces probability, not certainty, and the Treasury job's 18
files at, arguably, similar risk to the original 15-file zena-invariants
group shows N alone doesn't have an obviously safe threshold established
yet).

## H. Regression tests that would fail on the current defect and pass only on the intended fix

None of these exist yet — this is a design specification for Gate 3
implementation, not code written in this Gate 2.

1. **Self-healing detector test** (implements containment candidate C):
   a lightweight `TestCase`-level or dedicated PHPUnit extension that
   snapshots `RefreshDatabaseState::$migrated` at the start of the
   `--group=zena-invariants` run and asserts it never transitions from
   `true` back to `false` mid-run (only a documented explicit reset, if
   ever legitimately needed, would be excluded). **Fails today** (the
   defect flips it 1-3 times per run, per Gate 1's own general-log
   evidence and this Gate 2's own SELF_HEALING_FIRED captures). **Passes
   only once the underlying `PDO::inTransaction()` loss stops happening**
   — a test that can only go green by fixing the actual defect (candidate
   A) or by verifying candidate B has reduced the *specific job's* N low
   enough that the mechanism no longer manifests in practice (an
   empirical, not logical, guarantee — must be re-validated whenever new
   tests are added to that job).
2. **Deterministic multi-run stability test** (CI-level, not PHPUnit):
   run the exact `zena-invariants-mysql` CI invocation N times (N≥5,
   matching this Gate 2's own reproduction discipline) against a fresh
   MySQL container each time, asserting **zero** unscheduled
   `migrate:fresh` cycles (via the general-query-log `create table
   \`tenants\`` count method Gate 1 and this Gate 2 both used) across all
   N runs — this IS the acceptance criterion in §I, expressed as a test.
   **Fails today** (100% reproduction rate, 11/11 in this investigation).
3. **Sanctum-guard fidelity test** (separate scope, §F's own secondary
   finding — not required for GAP-050's acceptance, listed here because it
   surfaced from the same instrumentation and a future Work ID should not
   have to rediscover it): assert that a request carrying a Bearer token
   for user A, when the acting session guard was left authenticated as a
   *different* identity by an earlier request in the same test, is
   correctly re-authenticated via the token rather than silently reusing
   the stale session identity.

## I. Explicit acceptance criteria

GAP-050 is not release-ready until **all** of the following hold,
verified against real MySQL 8.0 using the exact CI invocation, not a
weakened or narrowed one:

1. The full `--group=zena-invariants` invocation (all 15 files, currently
   41 tests) passes on **5 consecutive independent runs**, each against a
   freshly `migrate:fresh`'d database, with **zero** exceptions —
   matching this Gate 2's own reproduction discipline (no
   rerun-until-green averaging; every run must independently pass).
2. A general-query-log capture (or equivalent) of at least one of those 5
   runs shows **exactly one** `create table \`tenants\`` occurrence — the
   CI script's own intentional pre-PHPUnit `migrate:fresh --force` — and
   **zero** unscheduled occurrences during the PHPUnit process itself.
3. `ZenaApiContractPhase2InvariantTest::test_document_show_returns_not_found_for_scoped_cross_tenant_resource`
   specifically continues to assert `E404.NOT_FOUND` — the intended
   tenant/RBAC/product semantics are **unchanged**; this criterion exists
   to positively guard against a fix that happens to pass by weakening
   this or any other assertion.
4. If candidate A (exact root-cause fix) was pursued and something was
   changed in `vendor/laravel/framework` or `vendor/laravel/sanctum`
   behavior expectations, that change must be justified with a filed
   upstream issue or a documented, tested local patch strategy — this
   repository does not silently fork framework behavior.
5. If candidate B (suite splitting) was pursued, the new job/invocation
   boundaries must be documented with the reasoning for where the splits
   land, and criterion 1 must be re-verified at the new, smaller N — not
   assumed safe by extrapolation.
6. If candidate C (fail-loud detection) was pursued, its own regression
   test (§H.1) must independently be proven to have failed on the
   pre-fix code and pass on the post-fix code (red/green evidence, per
   this repository's `superpowers:test-driven-development` convention).

## J. Explicitly rejected approaches (per Owner's Gate-2 direction)

- **Test-order changes** (reordering `@group zena-invariants` test
  execution, e.g. via a custom PHPUnit test-order strategy, to move the
  failing test earlier/later): would reduce the *observed* failure rate
  without addressing the mechanism, and — per Gate 1's own finding that a
  *different* test fails depending on invocation shape — provides no
  actual guarantee, only relocates which test intermittently absorbs the
  corruption. Rejected.
- **Sleeps / delays**: there is no evidence this is a timing/race
  condition in the concurrency sense (single PHP process, single MySQL
  connection, no parallelism) — a sleep would not address a
  state-desync mechanism and was never a plausible candidate. Rejected.
- **Retries** (rerun the job/test until it passes): explicitly the
  practice this Gate 1 and Gate 2 were both instructed not to use as
  evidence, and using it as a *fix* would be strictly worse — it
  actively launders a real, 100%-reproducible-in-this-shape defect into
  invisible, non-deterministic CI noise. Rejected.
- **Assertion weakening** (accepting `TENANT_INVALID` as equally valid,
  or relaxing `assertSame` to something looser): would hide a genuine
  test-harness defect behind a semantically meaningless pass, and was
  never authorized to touch intended tenant/RBAC/product semantics.
  Rejected.
- **Simply removing `RefreshDatabase`** (e.g. switching to manual
  transaction management or `DatabaseMigrations` per-test full rebuilds):
  `DatabaseMigrations` would itself run a full `migrate:fresh` **per
  test** (not just per unscheduled self-heal), turning today's
  intermittent multi-minute cost into a guaranteed one, without
  necessarily fixing the root desync (if the mechanism is a driver-level
  or testing-client artifact per §D, it could still occur, just
  differently). Removing test-transaction isolation entirely would
  defeat the entire purpose of `RefreshDatabase`-based testing across the
  whole suite, a materially larger regression than the problem being
  solved. Rejected.

## K. Fallback criteria — when process isolation/suite splitting becomes the accepted answer

Per the Owner's explicit instruction to state this clearly: falling back
to containment-only (candidates B+C, §G) as the **final, not merely
interim**, answer is justified if and only if:

1. A good-faith, time-boxed further investigation (recommended: one
   additional Gate-2-scoped session, following §D's three ranked
   hypotheses in order) fails to pin the exact trigger to a specific
   statement or a specific, named PHP/driver mechanism; **and**
2. That further investigation is documented with the same rigor as this
   Gate 2 (reproducible commands, evidence tables, ruled-out candidates)
   so a future investigator does not have to re-derive today's negative
   results from scratch; **and**
3. Candidate B, once implemented and validated per §I's acceptance
   criteria at the new invocation boundaries, is shown empirically to
   eliminate the failure across **at least 10 consecutive independent
   runs** (double this Gate 2's own reproduction count, since the
   remaining risk is that a smaller N merely makes the defect rarer, not
   impossible) at every job identified in §E's blast-radius table,
   including the currently-non-gating Treasury job; **and**
4. The Owner is presented with the residual risk explicitly (a
   containment-only fix means the defect class is not eliminated,
   merely made statistically unlikely at current test-suite sizes, and
   could resurface as any of those jobs' test counts grow) and accepts
   that residual risk knowingly, rather than the packet implying full
   resolution.

Falling back before condition 1 is satisfied (i.e., accepting containment
without a documented further attempt at root cause) would not meet this
bar and should not be presented to the Owner as anything other than a
deferred root-cause investigation.

## Evidence sources

| # | Source | Method | Notes |
|---|---|---|---|
| 1 | `origin/main` at `c5516c582d40e5c94b24c4de98121f64d539dfd1` (post Gate-1 merge) | **STATIC/LIVE** | Confirmed via `git fetch` + `git rev-parse` before starting Gate 2 |
| 2 | 11 full `--group=zena-invariants` runs against real MySQL 8.0 in disposable worktrees, canonical SHA | **LIVE** | 11/11 identical failure |
| 3 | Instrumented `vendor/laravel/framework/.../ManagesTransactions.php` (this repo's exact locked version) | **LIVE, disposable, never committed** | Every transaction/savepoint call traced with backtrace, test name, connection ID |
| 4 | Instrumented `vendor/laravel/framework/.../RefreshDatabase.php` self-healing callback | **LIVE, disposable, never committed** | Confirms Gate 1's finding remains active on the updated canonical SHA |
| 5 | Instrumented `vendor/laravel/sanctum/src/Guard.php` | **LIVE, disposable, never committed** | Zero invocations across the entire 41-test run |
| 6 | Complete `DB::listen()` query capture, unified sequence-ordered log | **LIVE, disposable, never committed** | 6,861 lines in the final run; zero queries in the bracketed window |
| 7 | `app/Http/Middleware/TenantIsolationMiddleware.php`,
    `RoleBasedAccessControlMiddleware.php` (this repo's actual code, read + temporarily instrumented, never committed) | **STATIC read + LIVE probe** | Confirms actual runtime middleware ordering empirically, not assumed from `$middlewarePriority` config alone |
| 8 | `.github/workflows/automated-testing.yml`, `routes-guardrails.yml`, `scripts/ci/*-mysql` entrypoints | **STATIC** | Blast-radius file/test counts (§E) |
| 9 | GAP-049's and GAP-050 Gate-1's own retained/committed evidence | **STATIC** | Cross-referenced, not re-derived |

## Explicit exclusions

Out of scope for this Gate 2, per its own instructions: any remediation
code change; a final decision between candidates A/B/C; MySQL
`general_log`/`performance_schema`-level tracing below the PHP layer
(flagged in §D as a possible next step, not attempted); reproduction of
the `mysql-parity` (5-file) and Treasury (18-file) jobs identified in §E
as sharing the structural risk pattern (flagged, not reproduced).
