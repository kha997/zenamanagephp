---
work_id: GAP-040
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-040/02-design.md
---

# GAP-040 — `TestCase` MySQL Transaction Isolation: Gate 2 Engineering Design

**Status:** Gate 1 approved (`docs/owner-decisions/GAP-040/01-request.md`, PR #269, Owner APPROVE 2026-08-20). Gate 2 design submitted, awaiting Owner decision. Gate 3 not started; implementation, merge, and release are not authorized. No test, application, migration, or workflow code has been changed to produce this document — it is design only.

**Objective:** `tests/TestCase.php::ensureSqliteZenaRbacTables()` must stop executing unconditional DDL inside an already-open, real-MySQL `RefreshDatabase` transaction, on every one of the 5 directly-exposed surfaces identified at Gate 1, without breaking the compatibility role these tables currently play for live application code (see §1 — a load-bearing dependency discovered during this design pass that materially changes which fix is safe).

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

Same existence guard as Option B, but the DDL itself is issued through a second Laravel database connection (e.g. `zena_ddl_bootstrap` in `config/database.php`, pointing at the identical physical database as the active connection, but never included in `connectionsToTransact()` and therefore never wrapped in `RefreshDatabase`'s transaction). MySQL's implicit-commit-on-DDL is a **per-session** behavior — a DDL statement on a separate connection/session does not touch a transaction open on a *different* session to the same server. Issuing the bootstrap DDL there closes Option B's residual gap completely: no DDL statement of any kind executes on the connection the test's transaction is open on, for any test, in any process.

Trade-offs: requires adding one new named connection to `config/database.php` (config-only, not a migration or schema change — the connection points at the exact same database, just isn't included in the transacted-connections list); requires the bootstrap logic to resolve the correct host/credentials for whichever environment is active (straightforward — same values as the active connection, read from the same env vars, just registered under a second connection name); slightly more moving parts than Option B. Feasibility (does Schema-facade DDL cleanly target a named connection other than the default one, does this interact correctly with `migrate:fresh` already having run on the primary connection) should be verified empirically during implementation, not asserted as proven here.

### Option D — Move the compatibility tables into the migration itself (not recommended for GAP-040)

Add a step to `2025_09_19_174648_rename_zena_tables_to_standard_names.php` (or a new migration immediately after it) that recreates `zena_roles`/`zena_permissions`/etc. as permanent compatibility tables/views alongside the renamed ones, everywhere the migration runs — including production. This would make `ensureSqliteZenaRbacTables()` unnecessary entirely (delete it), closing the gap completely with no residual case, and would also plausibly fix `Src\RBAC\Models\Role`'s dependency in *production*, not just tests, if it is genuinely broken there too (§1, open question).

**Not recommended within GAP-040's scope.** This changes a migration that runs in every environment including production — a schema change to shared infrastructure, not a test-only fix. It also conflates a test-transaction-isolation fix with a (possibly real, possibly not) production RBAC-table gap that has not been independently investigated or evidenced. Pursuing this here would risk exactly the kind of undisclosed production/schema change the Owner's Gate 1 approval explicitly guarded against. If the production dependency question (§1, §6) is confirmed as real, it should be designed and approved as its own governed change, with its own Design Dependency Preflight consideration, not folded into a test-infrastructure gap.

## 4. Recommendation

**Option B as the required minimum, with Option C authorized as the target if implementation confirms it is feasible without disproportionate complexity.** Rationale: Option B alone already collapses exposure from "every test, every process" (thousands of executions across the 5 surfaces) to "one test, per process" — a large, safe, low-risk improvement achievable with a one-line change matching an existing sibling pattern in the same file. Option C is the more complete answer to the invariant as stated in §2.2 and should be attempted, but its feasibility (a second DB connection cleanly coexisting with `migrate:fresh`, `RefreshDatabase`, and this repo's existing connection-pool config at lines 40-98 of `config/database.php`) has not been proven and should not gate Gate 2 approval — it is an implementation-time engineering decision, with Option B as the guaranteed-safe fallback if Option C proves impractical. Either way, the residual state (whether Option B's one-test-per-process gap remains, or Option C closes it) must be stated honestly in the Gate 3 technical evidence — not silently upgraded from "reduced" to "eliminated" without proof.

## 5. Regression evidence design — proving the invariant, not just the guard's shape

Per Owner instruction: a test asserting "the source has an `if` guard" is not acceptable evidence. The following are behavioral proofs, to be implemented against real MySQL (run as part of `zena-invariants-mysql`, the surface already confirmed to expose the defect):

1. **Direct transaction-liveness assertion.** Immediately after `setUp()` completes, in a dedicated regression test, assert `DB::transactionLevel() === 1` (Laravel exposes this via `Illuminate\Database\Connection::transactionLevel()`). If `ensureSqliteZenaRbacTables()`'s DDL had implicit-committed the `RefreshDatabase` transaction, MySQL would have silently closed it, but Laravel's own bookkeeping (`$transactions` counter in `Connection`) would not know that — this alone doesn't detect an implicit commit reliably (Laravel's counter can't see server-side state), so this check is necessary-but-not-sufficient and must be paired with #2.
2. **Behavioral write/rollback proof (the primary proof).** Two ordered test methods in the same test class, run in the same process, in a fixed, non-random execution order (PHPUnit preserves declaration order unless randomized — pin this explicitly for this pair, e.g. via `#[PHPUnit\Framework\Attributes\DependsOnClass]`-free explicit ordering, or a dedicated single-method test that writes then re-queries within `tearDown`/a second assertion phase): Test A writes a distinctive, uniquely-identifiable row into a real table (not `zena_roles` itself, to avoid confusing "did the compat table get recreated" with "did the transaction roll back" — use an ordinary tenant-scoped row, e.g. via the existing `FixtureFactory`/`AuthenticationTrait` helpers already used by several exposed surfaces). Test B (run after A, same process) asserts that row does **not** exist. This can only pass if `RefreshDatabase`'s rollback genuinely executed after Test A — which can only happen if the transaction was still open, which can only be true if no implicit commit occurred during Test A's `setUp()`. This is a direct, empirical proof of the exact mechanism GAP-040 is about, not an assertion about source code shape.
3. **Coverage across surfaces (§2 requirement, "not merely `zena-invariants-mysql`").** The proof in #2 must be exercised at minimum once per distinctly-configured surface identified at Gate 1 as directly exposed: `routes-guardrails.yml`'s `--group=mysql-parity` path, `zena-invariants-mysql`, `treasury-check-constraints-mysql`, `e2e-tests`, and `ci-cd.yml`'s GAP-032 MySQL-proof step (`phpunit.mysql.xml`). These differ in `phpunit.xml` config, group filters, and invocation method (`php artisan test` vs. raw `phpunit`), any of which could plausibly affect whether the fix actually takes effect on that specific path — proving it once on `zena-invariants-mysql` and assuming the other 4 are covered by the same source change is exactly the "implementation shape, not proven invariant" mistake the Owner warned against. Implementation should determine the minimal way to place one instance of proof #2 reachable from each of the 5 paths (a shared test class included in multiple groups, or one per surface — an implementation decision, not a Gate 2 one).

## 6. Concurrency paths (`document-workflow-concurrency-mysql`, `rfi-escalation-concurrency-mysql`)

These do not `use RefreshDatabase` (by design — they spawn separate OS processes for genuine concurrent writes, which a single wrapping transaction would prevent from being meaningful), so they are not exposed to the transaction-isolation-defeat mechanism this gap is about. Whichever guard option is implemented (B or C) applies uniformly to `ensureSqliteZenaRbacTables()` regardless of caller, so these paths automatically stop re-running the 8-statement DDL on every `setUp()` too (existence guard alone removes the redundant churn) — this is a free side benefit, not a change requiring separate design, and requires no special-casing.

## 7. SQLite regression protection

Both Option B and Option C preserve `ensureSqliteZenaRbacTables()`'s effective behavior on SQLite unchanged: the tables get created exactly once (existence-guarded), same as they always effectively were on the SQLite path once the first `migrate:fresh` renamed them away (SQLite's own `CREATE TABLE` inside an open transaction does not implicit-commit the way MySQL's does, so SQLite was never exposed to the primary defect — this was already established at Gate 1 and is unchanged). Acceptance: the existing full SQLite-backed test suite (`unit-tests`, `feature-tests`, `api-tests-*`, `integration-tests` — none of which set `ZENA_INVARIANTS_DB=mysql`, confirmed at Gate 1) must continue passing unchanged, before/after, as the regression baseline — no new SQLite-specific test is strictly required beyond that existing coverage continuing to pass.

## 8. Explicit non-goals (boundary of this Gate 2)

- Does not modify `RBACManager`, `Src\RBAC\Models\*`, or any migration file (Option D is explicitly declined for this work item).
- Does not investigate or resolve whether `zena_roles`/`zena_permissions` are genuinely functional in production (§1, §6 of the Gate 1 evidence's adjacent-finding discipline — flagged as an open question, not chased here).
- Does not fix GAP-041 (the separate zero-test group-name-mismatch finding, registered independently).
- Does not select Option B vs. Option C definitively — that determination is deferred to implementation, constrained by the honesty requirement in §4 that whichever is actually shipped must be accurately described as "reduced" (Option B) or "eliminated" (Option C) in Gate 3 evidence, never overstated.
