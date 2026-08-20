---
work_id: GAP-040
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-040/02-design.md
---

# GAP-040 — `TestCase` MySQL Transaction Isolation: Gate 2 Engineering Design

**Status:** Gate 1 approved (`docs/owner-decisions/GAP-040/01-request.md`, PR #269, Owner APPROVE 2026-08-20). Gate 2 design v2 (Owner requested changes on v1 — see revision notes in §4 and §5), awaiting Owner decision. Gate 3 not started; implementation, merge, and release are not authorized. No test, application, migration, or workflow code has been changed to produce this document — it is design only.

**Objective:** `tests/TestCase.php::ensureSqliteZenaRbacTables()` must **completely eliminate** DDL execution on the transacted connection between the moment a real-MySQL `RefreshDatabase` transaction opens and the moment the test body runs — including the first test of a fresh process, with no known residual case — on every one of the 5 directly-exposed surfaces identified at Gate 1, while preserving the compatibility role these tables currently play for live application code (see §1 — a load-bearing dependency discovered during this design pass that materially changes which fix is safe) and without changing production schema, RBAC behavior, migrations, tenant semantics, or application authorization.

**Owner decision surface:** see `docs/owner-decisions/GAP-040/02-design.md` — this document is engineering evidence and design mechanics supporting that packet's recommendation, not itself a decision surface. Script/config/test-naming choices below are engineering-owned.

---

## 1. Critical discovery made during Gate 2 preparation: `zena_roles`/`zena_permissions` are not test-only artifacts

Gate 1's governance classification assumed the `zena_*` RBAC tables were "legacy pre-rename artifacts of a test-only helper, not the live `roles`/`permissions` tables the application actually uses." **This is corrected here — it is not accurate on the current baseline:**

- `src/RBAC/Models/Role.php:25` — `protected $table = 'zena_roles';`
- `src/RBAC/Models/Permission.php:24` — `protected $table = 'zena_permissions';`
- `app/Services/RBACManager.php:6-9` — imports and uses `Src\RBAC\Models\Role`, `UserRoleCustom`, `UserRoleProject`, `UserRoleSystem` (all `zena_`-backed).
- `RBACManager` backs `app/Http/Middleware/RBACMiddleware.php` (the `rbac:` route middleware used across dozens of controllers), and `Src\RBAC\Providers\RBACServiceProvider` is registered in `config/app.php:203`; `src/RBAC/routes/api.php` is mounted via `require base_path(...)` in `routes/api.php:1028` — confirmed live, wired-in application code, not dead code.
- Separately, `App\Models\Role`/`App\Models\Permission` use Laravel's default table-name convention (`roles`/`permissions` — the migration's *rename target*). **Two parallel RBAC systems coexist in this codebase**, backed by different table sets.

**Consequence for design:** `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` renames `zena_roles→roles` etc. on every `migrate:fresh` — after which, on any driver, `Src\RBAC\Models\Role`/`Permission` would be querying a table that does not exist, **unless** something recreates `zena_roles`/`zena_permissions` afterward. `ensureSqliteZenaRbacTables()`, despite its `Sqlite`-suggesting name, is that recreation — and it currently runs unconditionally on *every* driver, which is why RBAC-dependent code paths have not visibly broken on the MySQL-parity surfaces to date. **A naive fix that simply skips this DDL on the MySQL driver (mirroring `ensureSqliteDocumentsBackupTable()`'s guard literally) would silently remove `zena_roles`/`zena_permissions` from every MySQL-parity test run**, and any of the 5 exposed surfaces that exercises `rbac:`-gated routes would newly fail — the opposite of Gate 2's goal.

One exposed test, `tests/Feature/Zena/ZenaAuthFlowInvariantTest.php` (part of `zena-invariants-mysql`'s 12 `RefreshDatabase`-using classes), directly imports `Src\RBAC\Services\AuthService`, confirming at least one exposed surface actively depends on this compatibility layer.

**This finding does not itself trigger the Design Dependency Preflight** — no option below proposes changing `RBACManager`, `Src\RBAC\Models\*`, any migration, or production authorization behavior. It is scoped strictly as a **safety constraint on which `tests/TestCase.php`-only fix is acceptable**. Whether production genuinely has a working `zena_roles` table (i.e., whether this dependency is *also* silently broken outside tests) is an open question explicitly **out of scope for GAP-040** — flagged in §6 as a candidate separate gap, not investigated further here.

## 2. The exact desired invariant

For every test class that extends `Tests\TestCase`:

1. On any driver, by the time the test method body runs, `zena_permissions`, `zena_roles`, `zena_role_permissions`, `zena_user_roles` exist with their expected schema (preserving current behavior for RBAC-dependent tests — this must not regress).
2. If the test class `use`s `RefreshDatabase` and is running against a real MySQL connection, **no DDL statement may execute on that connection between the moment `RefreshDatabase::beginDatabaseTransaction()` opens the transaction and the moment the test method body begins** — i.e., the transaction must still genuinely be open (not implicit-committed) when the test runs, and a rollback at teardown must actually undo the test's writes.
3. The fix must not weaken or special-case SQLite behavior; the existing SQLite-driven test suite's current outcomes are the regression baseline (§7).

## 3. Options considered

### Option A — Driver-only guard (copy `ensureSqliteDocumentsBackupTable()`'s pattern exactly)

```php
private function ensureSqliteZenaRbacTables(): void
{
    if (env('DB_CONNECTION') !== 'sqlite') {
        return;
    }
    // ... existing 8 DDL statements, unchanged ...
}
```

**Rejected as the primary fix.** Per §1, this deletes `zena_roles`/`zena_permissions` from every MySQL-parity test run, at least one of which (`ZenaAuthFlowInvariantTest`) is confirmed to depend on them. This would trade a silent transaction-isolation defect for a loud, immediate test-breakage regression — unacceptable without first proving (exhaustively, across all 5 exposed surfaces, not just the one confirmed dependency) that nothing else transitively depends on RBAC middleware during those test runs. That proof is out of proportion to what Gate 2 should require before authorizing a direction.

### Option B — Existence guard only (copy `ensureSqliteSubmittalsTable()`'s pattern)

```php
private function ensureSqliteZenaRbacTables(): void
{
    if (Schema::hasTable('zena_roles')) {
        return;
    }
    // ... existing 8 DDL statements, unchanged (the 4 dropIfExists become dead
    // code once this guard is added, since the guard already proves the tables
    // don't exist — they can be removed in the same change) ...
}
```

**Safe with respect to §1** — never removes the tables on any driver, only stops recreating them once they already exist. Because `RefreshDatabaseState::$migrated` stays `true` across the MySQL-parity path after the first test in a process (GAP-039's own change, `tests/TestCase.php:106`), and `migrate:fresh` therefore only genuinely re-runs once per process, `zena_roles` will exist for every test after the first in a given job/process — meaning tests #2..N are no longer exposed to the implicit-commit mechanism at all.

**Residual gap, stated plainly:** the *first* `RefreshDatabase`-using test in a fresh process still finds `zena_roles` missing (the rename migration just removed it) and executes the CREATE statements inside its own already-open transaction — that one test, every process, remains exposed. This is a large reduction (thousands of DDL executions down to one per process) but not a complete fix of the stated invariant (§2.2).

### Option C — Existence guard + bootstrap on a separate, non-transactional connection

Same existence guard as Option B, but the DDL itself is issued through a second Laravel database connection pointing at the identical physical database as the active connection, never included in `connectionsToTransact()` and therefore never wrapped in `RefreshDatabase`'s transaction. MySQL's implicit-commit-on-DDL is a **per-session** behavior — a DDL statement on a separate connection/session does not touch a transaction open on a *different* session to the same server. Issuing the bootstrap DDL there closes Option B's residual gap completely: no DDL statement of any kind executes on the connection the test's transaction is open on, for any test, in any process, including the first.

**Connection-registration mechanism (corrected per Owner review — no checked-in `config/database.php` change):** register the secondary connection **at runtime, in `tests/TestCase.php` only**, using `config(['database.connections.zena_ddl_bootstrap' => [...]])` with the same driver/host/credentials as the active connection (read from the same env vars already in scope), before first use, followed by `DB::purge('zena_ddl_bootstrap')` if needed to force a fresh session. Issue the DDL via `DB::connection('zena_ddl_bootstrap')->getSchemaBuilder()->create(...)` in place of the default `Schema::create(...)`. This is test-bootstrap code only — no production configuration file changes, matching GAP-040's declared boundary of touching only `tests/TestCase.php`.

**Fallback if runtime registration proves technically unclean** (e.g. connection-pooling or persistent-connection interactions during implementation testing make dynamic registration unreliable): a minimal, test-environment-scoped entry in `config/database.php` (e.g. gated so it is inert unless a testing-only env var is present) is an **authorized minimal implementation surface** for GAP-040 specifically because it changes no schema, no RBAC behavior, no migration, and no tenant/authorization semantics — it is config plumbing for a second session to the same database, not a behavior change. This is stated explicitly so implementation is not blocked choosing between "no config change, ever" and "the intended fix" if the preferred runtime-only mechanism turns out to be infeasible; either way, no production schema/authorization/migration/tenant behavior changes are authorized under GAP-040.

Feasibility of the runtime-registration mechanism (does Schema-facade DDL cleanly target a named connection other than the default one via this route, does this interact correctly with `migrate:fresh` already having run on the primary connection) should be verified empirically during implementation, not asserted as proven here.

### Option D — Move the compatibility tables into the migration itself (not recommended for GAP-040)

Add a step to `2025_09_19_174648_rename_zena_tables_to_standard_names.php` (or a new migration immediately after it) that recreates `zena_roles`/`zena_permissions`/etc. as permanent compatibility tables/views alongside the renamed ones, everywhere the migration runs — including production. This would make `ensureSqliteZenaRbacTables()` unnecessary entirely (delete it), closing the gap completely with no residual case, and would also plausibly fix `Src\RBAC\Models\Role`'s dependency in *production*, not just tests, if it is genuinely broken there too (§1, open question).

**Not recommended within GAP-040's scope.** This changes a migration that runs in every environment including production — a schema change to shared infrastructure, not a test-only fix. It also conflates a test-transaction-isolation fix with a (possibly real, possibly not) production RBAC-table gap that has not been independently investigated or evidenced. Pursuing this here would risk exactly the kind of undisclosed production/schema change the Owner's Gate 1 approval explicitly guarded against. If the production dependency question (§1, §6) is confirmed as real, it should be designed and approved as its own governed change, with its own Design Dependency Preflight consideration, not folded into a test-infrastructure gap.

## 4. Recommendation (revised per Owner Gate 2 REQUEST CHANGES)

**Option C (or another technically equivalent complete solution) is the required target and the only acceptable Gate-3-complete result.** The approved invariant (§2.2) is that no DDL executes on the transacted connection at any point between transaction-open and test-body-start — full stop, including the first test of a fresh process. Option B does not satisfy that invariant; it only shrinks the window from "every test" to "one test per process." A known, named residual implicit-commit case is not an acceptable final state, and Owner has explicitly declined to accept it at Gate 2.

Option B's role is narrowed to an **implementation stepping stone / fallback experiment only** — useful for incrementally verifying the existence-guard mechanic works correctly and for isolating whether any regression is caused by the guard itself versus the separate-connection mechanism, but **it must never be shipped as GAP-040's completion**. If, during implementation, Option C (or an equivalent complete mechanism) proves infeasible or disproportionately risky, engineering must **stop and return to Owner with evidence and a proposed scope/risk reduction** — not silently ship Option B and report GAP-040 as resolved. This is a hard constraint on Gate 3 evidence, not a preference: Gate 3's technical evidence must demonstrate the cold-start case (§5) actually passes under real MySQL, on the actual mechanism shipped, or Gate 3 cannot claim completion.

Option D remains declined (§3, unchanged) — production migration changes are out of scope for GAP-040 regardless of Option C's feasibility.

## 5. Regression evidence design — proving the invariant, not just the guard's shape

Per Owner instruction: a test asserting "the source has an `if` guard" is not acceptable evidence, and an ordered-pair proof that runs *after* the compat tables already exist would miss the exact residual defect that matters most (the first test of a fresh process, when `zena_roles`/etc. have just been renamed away and do not yet exist). The required evidence is therefore anchored explicitly to the **cold-start case**, not to a same-process pair that could run after warm-up.

1. **Cold-start / fresh-process rollback proof (the primary, required proof).** Must be structured so it demonstrably runs as the *first* `RefreshDatabase`-using test to execute against a freshly-migrated real-MySQL database in its process — i.e., immediately after `migrate:fresh` has renamed `zena_roles`/`zena_permissions`/etc. away and before any other test in the process has had a chance to recreate them. Concretely, this must be provable as a sequence of server-observable facts, not inferred from execution order alone:
   - **(a) Fresh process, real MySQL:** the test run captures (e.g., logs or asserts) that it is operating against a real MySQL connection (reusing the existing `zena_mysql_ensure_connection`/`zena_mysql_preflight_connection` fail-closed checks already used on these CI paths).
   - **(b) Compat tables initially absent:** immediately upon entering the test (before the fix's bootstrap logic runs, or via a check placed to run first), assert `Schema::hasTable('zena_roles') === false` — proving this test is genuinely exercising the cold-start path, not accidentally running after another test already created the tables. A test harness or ordering mechanism must guarantee this test is first (e.g., a dedicated, isolated CI invocation, or a class specifically designed to run in isolation on this one concern).
   - **(c) `RefreshDatabase` transaction opens, compat bootstrap occurs, transaction verifiably still open afterward:** after `setUp()` completes (compat tables now created via whichever mechanism is shipped), assert `DB::transactionLevel() === 1` as a first-line sanity check (necessary but not sufficient on its own, since Laravel's in-process counter cannot see a server-side implicit commit — kept as an early, cheap signal, not the proof).
   - **(d) Distinctive write:** within this same cold-start test, write a distinctive, uniquely-identifiable row into an ordinary tenant-scoped table (not `zena_roles` itself, to avoid confusing "did the compat table get recreated" with "did the transaction roll back").
   - **(e) Teardown rollback + independent verification:** after this test's teardown runs (its `RefreshDatabase` rollback), a *second*, independent check — a separate test method or a separate process/connection querying fresh — must confirm that row does not exist. This must be a genuinely independent read (not reusing the same connection/transaction context as (d)), so the proof is of server-observable state, not in-process assumption.
   - This sequence can only fully pass if the transaction opened in (c) was never implicit-committed by the compat-table bootstrap in between — i.e., it is a complete, empirical proof of the exact invariant in §2.2, anchored to the specific case (first test, fresh process) where Option B was known to fail and where the shipped mechanism (Option C or equivalent) must succeed.
2. **Coverage across surfaces (§2 requirement, "not merely `zena-invariants-mysql`") — unchanged, still required.** The cold-start proof in #1 must be exercised at minimum once per distinctly-configured surface identified at Gate 1 as directly exposed: `routes-guardrails.yml`'s `--group=mysql-parity` path, `zena-invariants-mysql`, `treasury-check-constraints-mysql`, `e2e-tests`, and `ci-cd.yml`'s GAP-032 MySQL-proof step (`phpunit.mysql.xml`). These differ in `phpunit.xml` config, group filters, and invocation method (`php artisan test` vs. raw `phpunit`), any of which could plausibly affect whether the fix actually takes effect on that specific path — proving it once on `zena-invariants-mysql` and assuming the other 4 are covered by the same source change is exactly the "implementation shape, not proven invariant" mistake the Owner warned against. Each surface's invocation genuinely starts from a fresh process (a new CI job), so each is naturally a cold-start opportunity — implementation should determine the minimal way to place one instance of proof #1 as the first `RefreshDatabase` test reachable from each of the 5 paths (an implementation decision, not a Gate 2 one).

## 6. Concurrency paths (`document-workflow-concurrency-mysql`, `rfi-escalation-concurrency-mysql`)

These do not `use RefreshDatabase` (by design — they spawn separate OS processes for genuine concurrent writes, which a single wrapping transaction would prevent from being meaningful), so they are not exposed to the transaction-isolation-defeat mechanism this gap is about. Whichever guard option is implemented (B or C) applies uniformly to `ensureSqliteZenaRbacTables()` regardless of caller, so these paths automatically stop re-running the 8-statement DDL on every `setUp()` too (existence guard alone removes the redundant churn) — this is a free side benefit, not a change requiring separate design, and requires no special-casing.

## 7. SQLite regression protection

Whichever complete mechanism is shipped (Option C or an equivalent) preserves `ensureSqliteZenaRbacTables()`'s effective behavior on SQLite unchanged: the tables get created exactly once (existence-guarded), same as they always effectively were on the SQLite path once the first `migrate:fresh` renamed them away (SQLite's own `CREATE TABLE` inside an open transaction does not implicit-commit the way MySQL's does, so SQLite was never exposed to the primary defect — this was already established at Gate 1 and is unchanged). Acceptance: the existing full SQLite-backed test suite (`unit-tests`, `feature-tests`, `api-tests-*`, `integration-tests` — none of which set `ZENA_INVARIANTS_DB=mysql`, confirmed at Gate 1) must continue passing unchanged, before/after, as the regression baseline — no new SQLite-specific test is strictly required beyond that existing coverage continuing to pass.

## 8. Explicit non-goals (boundary of this Gate 2)

- Does not modify `RBACManager`, `Src\RBAC\Models\*`, or any migration file (Option D is explicitly declined for this work item).
- Does not investigate or resolve whether `zena_roles`/`zena_permissions` are genuinely functional in production — registered separately as **GAP-042** (§9), not chased here.
- Does not fix GAP-041 (the separate zero-test group-name-mismatch finding, registered independently).
- Does not check in a `config/database.php` change unless the runtime-registration mechanism (§3, Option C) proves infeasible, in which case a minimal, testing-scoped fallback entry is pre-authorized under the exact boundary stated there — not a general license to change production configuration.
- Does not change production schema, RBAC behavior, migrations, tenant semantics, or application authorization under any option.

## 9. Separately discovered: production-fidelity / RBAC-authorization risk concern (registered as GAP-042, not GAP-040 scope)

While tracing why `ensureSqliteZenaRbacTables()` exists (§1), a repo-wide search of `database/migrations/` for `zena_roles`/`zena_permissions` found exactly three references: the original creation migration (`2025_09_14_140000_create_zena_rbac_fixed.php`), a later column addition (`2025_09_17_165315_add_tenant_id_to_zena_roles_table.php`), and the rename-away migration (`2025_09_19_174648_rename_zena_tables_to_standard_names.php`). **No migration after the rename recreates `zena_roles`/`zena_permissions`.** The only code found anywhere in the repo that recreates them is the test-only helper this gate is about.

This may mean live `Src\RBAC\Models\Role`/`Permission` (and everything built on `RBACManager`) depend on tables that do not exist in a genuinely fresh production database — i.e., the MySQL-parity tests currently "work" only because of a test-only compatibility shim that production does not have. This is stated here as an **unverified production-fidelity / authorization-risk concern**, not a confirmed production incident — no production database was inspected, and it is possible some other mechanism (a seeder, a manually-applied hotfix, an out-of-band schema change) accounts for this in a real deployed environment. Per Owner direction, this is **not investigated or fixed under GAP-040** — it is registered as its own operational gap (GAP-042) requiring its own independent verification and governance before any remediation is considered.
