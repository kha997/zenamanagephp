# GAP-044 — `TestCase` Transaction Isolation + Permission Fixture Identity: Gate 2 Engineering Design

**Status:** Gate 1 approved (`docs/owner-decisions/GAP-044/01-request.md`, PR #283, Owner APPROVE 2026-08-22, head `c7945a1a8d502ba2f817f9d5d82d854fe5eab412`). Gate 2 design, awaiting Owner decision. Gate 3 not started; implementation, merge, and release are not authorized. No test, application, migration, or workflow code has been changed to produce this document — it is design only.

**Objective:** eliminate the compound test-infrastructure defect confirmed at Gate 1 — (A) `tests/TestCase.php`'s three unfixed sibling methods (`ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`, `ensureProjectTasksTable()`) implicit-committing `RefreshDatabase`'s transaction on real MySQL, and (B) `TenantUserFactoryTrait::ensurePermissionAttached()`'s permission lookup using the non-canonical `name` column instead of the unique, canonical `code` column, which collides with a pre-existing seeded row whose `name` is `NULL` — while restoring, in truthful and false-green-resistant evidence, the broader end-to-end `RefreshDatabase` transaction-isolation invariant GAP-040 previously (incompletely) claimed.

**Owner decision surface:** see `docs/owner-decisions/GAP-044/02-design.md` — this document is engineering evidence and design mechanics supporting that packet's recommendation, not itself a decision surface.

---

## 1. Recap of the compound defect (Gate 1, Owner-approved and Owner-reconciled)

**Surface 1 — transaction isolation.** `ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`, `ensureProjectTasksTable()` (`tests/TestCase.php`) create three tables that have no migration anywhere in the repository, guarded only by `Schema::hasTable()`, with no driver guard and no isolated-connection routing — the same defect class GAP-040 fixed for `ensureSqliteZenaRbacTables()` in the same file, left unfixed for these three siblings. On real MySQL, their `CREATE TABLE` DDL implicit-commits `RefreshDatabase`'s already-open transaction; Laravel's PHP-side `transactionLevel()` counter never learns about it.

**Surface 2 — permission fixture identity.** Confirmed by direct migration/seeder trace this pass (extending Gate 1's evidence, no new live reproduction needed — this is static-code confirmation of the Owner's own Gate-1-approval reconciliation):

- `database/migrations/2025_09_14_140000_create_zena_rbac_fixed.php:15-22` — the original `zena_permissions` schema (renamed to `permissions` by `2025_09_19_174648_rename_zena_tables_to_standard_names.php`) defines `code`, `module`, `action`, `description`, `is_active` — **no `name` column at all**.
- `database/migrations/2026_01_30_000001_add_name_to_permissions_table.php` — adds `name` **as `nullable()`**, later.
- `database/seeders/RoleSeeder.php:53-71` — seeds `permissions` rows (including `code='project.read'`) via `Permission::firstOrCreate(['code' => $permData['code']], ['module'=>..., 'action'=>..., 'description'=>..., 'is_active'=>true])` — **the `$values` array never includes `name`** — so the created row has `code='project.read'`, `name=NULL`.
- `database/seeders/DatabaseSeeder.php:26-27` — calls `RoleSeeder::class` **before** `PermissionSeeder::class`.
- `database/seeders/PermissionSeeder.php:84-89` — seeds the same `code='project.read'` via `Permission::firstOrCreate(['code' => $permData['code']], $permData + ['name' => $permData['code']])`. Because the row already exists (from `RoleSeeder`), `firstOrCreate`'s lookup finds it and returns it unchanged — **`name` is never backfilled**.
- `tests/Traits/TenantUserFactoryTrait.php:59-60` — `ensurePermissionAttached()` calls `Permission::firstOrCreate(['name' => $permissionName], [...])`, i.e. `['name' => 'project.read']` — **misses** the `name=NULL` row entirely, so `createOrFirst()` attempts a fresh `INSERT`, which collides with the pre-existing row's `code='project.read'` unique constraint (`permissions_code_unique`) — `UniqueConstraintViolationException`, SQLSTATE `23000`, MySQL `1062`.
- **Contrast:** `tests/Support/SSOT/FixtureFactory.php:65-76` (a sibling test fixture helper in the same repository) already uses the **canonical, collision-safe** pattern: `Permission::firstOrCreate(['code' => $permissionCode], ['name' => $permissionCode, 'module'=>..., 'action'=>..., 'description'=>...])` — lookup by `code`, matching the actual unique constraint. `TenantUserFactoryTrait` is the outlier, not `FixtureFactory`.

This matches the repository's pre-existing **AUD-28** finding (per Owner's Gate-1-approval reconciliation).

**Interaction between the two surfaces (Gate 1, §I1 of the evidence document):** Surface 1's transaction desynchronization is what converts Surface 2's genuine, independently-thrown `UniqueConstraintViolationException` — which Eloquent's `createOrFirst()` is specifically designed to catch and recover from via a second lookup — into the confusing, unrecoverable `SAVEPOINT trans2 does not exist` PDOException, because the nested-transaction rollback attempt triggered by Surface 2's exception fails due to Surface 1's already-implicit-committed transaction. **Fixing only one surface does not fully close GAP-044** (see §3, Option B).

## 2. The exact desired invariant (Gate 1 §H1/§I1-derived, Owner-specified in §G of the Gate-1-approval authorization)

1. On any driver, by the time a `RefreshDatabase`-using test's body runs, the compatibility/fixture tables it needs exist, with no regression to current SQLite behavior.
2. On real MySQL, **no DDL statement of any kind may execute on the `RefreshDatabase`-transacted connection** between the moment the transaction opens and the moment the test body begins — including the first test of a fresh process — matching the invariant GAP-040 already established for `ensureSqliteZenaRbacTables()`, now extended to all three remaining siblings.
3. `TenantUserFactoryTrait::ensurePermissionAttached()`'s permission lookup must use the column the database actually enforces uniqueness on (`code`), so a pre-existing seeded permission (regardless of its `name` value, including `NULL`) is found and reused, never re-inserted.
4. Regression evidence for (2) must be structurally immune to the specific false-green mode Gate 1 discovered in GAP-040's own proof (§H1): a marker's disappearance must be attributed to a genuine `ROLLBACK`, not to a `migrate:fresh` schema wipe triggered by `RefreshDatabaseState::$migrated` having been reset by the very defect under test.

## 3. Options considered

### Option A — Complete test-infrastructure remediation (both surfaces) — RECOMMENDED

**Surface 1:** extend GAP-040's proven isolated-connection pattern
(`zenaRbacBootstrapSchema()`/`zena_ddl_bootstrap`, `tests/TestCase.php`) to
the three remaining sibling methods. Two sub-shapes, to be settled at
implementation time based on what proves cleanest against the existing
helper, not selected here:

- **A1 (generalize the existing mechanism):** extract GAP-040's
  `zenaRbacBootstrapSchema()` into a general-purpose
  `nonTransactedBootstrapSchema()` (or similar) usable by all four
  `ensure*Table()` methods, removing the `zena`-specific naming while
  preserving its exact mechanics (dynamic connection registration, forced
  non-persistent PDO handle, `DB::purge()`).
- **A2 (per-method reuse):** call the existing `zenaRbacBootstrapSchema()`
  method (renamed or not) directly from the three other `ensure*Table()`
  methods, minimal diff, no extraction.

Either sub-shape satisfies the invariant identically; A1 is preferred if it
does not materially complicate the diff, since it removes the now-inaccurate
`zena`-specific naming from a mechanism 4 unrelated tables will share.

**Surface 2:** change `TenantUserFactoryTrait::ensurePermissionAttached()`'s
lookup key from `name` to `code`:

```php
// current (defective):
$permission = Permission::firstOrCreate(
    ['name' => $permissionName],
    ['code' => $permissionName, 'module' => ..., 'action' => ..., 'description' => ...]
);

// proposed:
$permission = Permission::firstOrCreate(
    ['code' => $permissionName],
    ['name' => $permissionName, 'module' => ..., 'action' => ..., 'description' => ...]
);
```

This exactly matches `FixtureFactory::createTenantUserWithRbac()`'s already-existing, already-correct pattern (§1) — not a novel mechanism, an alignment to an existing sibling convention in the same codebase. `$permissionName`'s value shape is unchanged (still dot-notation strings like `project.read`); only the lookup/create-attribute key assignment swaps.

**No production seeder change; no `RoleSeeder.php` change.** `AUD-28` (the `name=NULL` seeded-row condition) is documented and characterized (§4) but deliberately left alone — Surface 2's fix makes the test fixture tolerate `AUD-28`'s existing behavior correctly, rather than requiring `AUD-28` to be fixed first.

**Assessment:** fully closes both confirmed root causes. Diff is small and precisely bounded: `tests/TestCase.php` (repeat of GAP-040's already-proven pattern) + `tests/Traits/TenantUserFactoryTrait.php` (one lookup-key swap, one line). No production code, no migration, no seeder, no workflow file.

### Option B — Fix only Surface 1, leave the permission-lookup mismatch as-is

Extends GAP-040's isolated-connection pattern to the three sibling methods (Surface 1 only), does not touch `TenantUserFactoryTrait`.

**Demonstration required by Owner authorization §F — attempted here:** with Surface 1 alone fixed, `RefreshDatabase`'s transaction is never implicit-committed, so `transactionLevel()` accurately reflects the real transaction state throughout `setUp()`. When `TenantUserFactoryTrait::ensurePermissionAttached()` still calls `Permission::firstOrCreate(['name' => 'project.read'], [...])` against a database where `RoleSeeder`'s `name=NULL` row already occupies `code='project.read'`, the lookup **still misses** (nothing about Surface 1's fix changes what rows exist or how they're looked up), and the `INSERT` **still collides** on `permissions_code_unique` — `UniqueConstraintViolationException` is still genuinely thrown. The only change is that this time, `withSavepointIfNeeded()`'s savepoint is opened on a **genuinely live** transaction (Surface 1 fixed), so the subsequent `ROLLBACK TO SAVEPOINT trans2` **succeeds** (the savepoint genuinely exists), and `createOrFirst()`'s own `catch (UniqueConstraintViolationException $e) { return ...->first() ?? throw $e; }` block **now runs as designed** — but its own retry lookup is *also* `where(['name' => 'project.read'])`, which **still misses** the `name=NULL` row, so `?? throw $e` re-throws the **original** `UniqueConstraintViolationException` (1062), not the SAVEPOINT PDOException (1305).

**Conclusion (per Owner authorization §F): Option B does not resolve GAP-044 end-to-end — it merely changes which exception the test fails with, from 1305 (`SAVEPOINT trans2 does not exist`) to 1062 (`Duplicate entry 'project.read' for key 'permissions_code_unique'`), on the exact same test, at the exact same call site.** `PerformanceMonitoringTest`/`DashboardPerformanceTest` would still fail. **Classified as incomplete for end-to-end GAP-044 remediation**, per the Owner's own discriminating framework in authorization §F. Not recommended as a standalone option; retained here only as the required comparison baseline.

### Option C — Replace runtime creation of the three migration-less tables with a different lifecycle architecture (e.g. pre-transaction migration/setup)

Instead of routing DDL through an isolated connection at `setUp()` time (Option A's Surface 1), move `interaction_logs`/`project_phases`/`project_tasks` creation to occur **before** `RefreshDatabase` opens its transaction at all — e.g. as part of a one-time, job-level or process-level bootstrap step, or by converting them into real (test-environment-only, or universally-applied) migrations.

**Scope/regression-risk comparison against Option A:**

- **If converted to real migrations** (applied on every environment including production, mirroring GAP-040's explicitly-declined Option D): this is a **production schema change** — directly the kind of change GAP-040's Gate 2 explicitly declined for the analogous `zena_*` tables, and would very likely require the Design Dependency Preflight this Gate 2 currently does not trigger. Rejected for the same reason GAP-040 rejected its own Option D: conflates a test-infrastructure fix with an undiscussed production schema change.
- **If converted to a test-environment-only, pre-`RefreshDatabase` bootstrap step** (e.g. a one-time `TestCase`-level static/process-level guard that runs once per PHP process, before any test's `parent::setUp()`, using PHPUnit's own bootstrap or a `TestListener`): technically avoids the implicit-commit mechanism entirely (no DDL ever runs after a transaction opens, because none of these tables' DDL runs inside `setUp()` at all after the first process-wide bootstrap). However, this is a **larger, less-proven architectural change** — it does not reuse GAP-040's already-Gate-3-evidenced isolated-connection mechanism, requires new regression evidence design from scratch (rather than extending an already-accepted pattern), and changes *when* in the test lifecycle these tables become available (a behavior change other, unrelated tests could depend on in subtle ways not audited here). It also does not address Surface 2 at all — would still require pairing with the `TenantUserFactoryTrait` fix.

**Assessment:** materially higher scope and regression risk than Option A for no demonstrated additional benefit — Option A's isolated-connection mechanism already fully satisfies the invariant (§2.2) with a proof design already accepted once by Owner (GAP-040 Gate 3, modulo the false-green correction now applied in §5) and a minimal, well-understood diff. Not recommended.

## 4. RoleSeeder / AUD-28 — explicit treatment (per Owner authorization §E)

`database/seeders/RoleSeeder.php` is **not modified** by this design. The
`name=NULL`-on-`code='project.read'` condition it produces is a real,
independently-existing condition (matching the repository's pre-existing
AUD-28 finding), but GAP-044's preferred remediation (Option A, Surface 2)
makes the **test fixture** (`TenantUserFactoryTrait`) correctly tolerate
that condition by looking up permissions the way the database actually
enforces uniqueness — rather than requiring `RoleSeeder`'s seed data to be
"fixed" first. This mirrors GAP-040's own precedent of leaving a discovered,
adjacent, pre-existing production-adjacent condition (there: whether
`zena_roles`/`zena_permissions` genuinely work in a fresh production
database, spun out as GAP-042) undisturbed and separately tracked rather
than folded into the gap that discovered it.

If Gate 2 (or later Gate 3 evidence) reveals that `RoleSeeder`'s
`name=NULL` behavior is itself undesirable beyond this test-fixture
interaction — e.g. if any production code path also looks up permissions
by `name` and would suffer the same miss — that is explicitly **out of
GAP-044's scope** and must be raised as a separate Owner scoping decision
(new or existing work item, e.g. AUD-28 itself), not silently absorbed here.
No such production code path was searched for or found in Gate 1 or this
Gate 2 design pass; this is stated as an open question, not a claim either
way.

## 5. Regression evidence design — discriminating, false-green-resistant (per Owner authorization §G)

Per Owner's explicit instruction, the acceptance contract must not repeat
GAP-040's mistake. The following are the minimum required proof elements,
directly incorporating Owner authorization §G items 1-7:

1. **Cold-start transacted-state proof.** On genuine MySQL, immediately
   after a test's *full* `parent::setUp()` completes (i.e. after all four
   `ensure*Table()` calls have run), assert **both**:
   - `DB::connection()->getPdo()->inTransaction() === true` (server-observed truth, not Laravel's own counter), and
   - `DB::transactionLevel()` is consistent with that (`=== 1`).
   This must hold on the **first** test of a fresh process (cold start), not merely on a warmed-up process.
2. **No-implicit-commit proof, per helper.** For each of the three
   newly-fixed methods (`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`),
   capture `PDO::inTransaction()` immediately before and immediately after
   that specific method's DDL executes, on a cold-start process where the
   target table does not yet exist (forcing the DDL to actually run) —
   value must be unchanged (`true` → `true`) at each boundary. This is the
   same probe pattern already used and proven in GAP-040 Gate 3 and this
   Gate 1's own evidence-gathering (§D-G, §H1 of the audit document) —
   reused for the fix's own regression proof, not invented fresh.
3. **Genuine-rollback proof, explicitly distinguishing rollback from `migrate:fresh` (the exact false-green GAP-040's proof missed).** A writer/verifier pair must prove, as a sequence of independently-observable facts — not inferred from "marker absent in the next test" alone:
   - the marker is visible via an **independent, non-Laravel-managed PDO connection** immediately after the writer's insert (proving the write path is exercised);
   - `RefreshDatabaseState::$migrated` is captured **immediately before** the verifier's own `parent::setUp()` runs, and must be `true` at that point (proving no `migrate:fresh` is about to run — this is the crucial discriminator GAP-040's proof omitted);
   - the marker's visibility is checked via independent PDO **at that same pre-verifier-setup boundary** — it must already be **absent**, attributable **only** to the writer's own teardown `ROLLBACK`, not to anything the verifier's setup could have done (since the verifier's setup has not run yet at this checkpoint).
   This is exactly the discriminating design used in this Gate 1's own §H1 investigation (which is what caught GAP-040's false-green in the first place) — reused here as the required proof shape for GAP-044's own fix, not a new invention.
4. **Re-verify GAP-040's 5 approved surfaces** (`routes-guardrails.yml`'s `--group=mysql-parity`, `zena-invariants-mysql`, `treasury-check-constraints-mysql`, `e2e-tests`, `ci-cd.yml`'s GAP-032 MySQL step) against the now-restored end-to-end invariant, using proof design (3) above — not GAP-040's original (now-known-unreliable) proof shape.
5. **Authoritative seeded performance pipeline, run truthfully.** `php artisan migrate && php artisan db:seed --env=testing --force`, genuine MySQL, then `php artisan test tests/Performance/PerformanceMonitoringTest.php --group=performance --fail-on-empty-test-suite`. The prior `SAVEPOINT trans2 does not exist` failure on `test_api_performance_budgets` must disappear, and no `UniqueConstraintViolationException`/1062 failure may remain attributable to the permission-lookup fix.
6. **Same for `DashboardPerformanceTest.php`** under the same genuine seeded MySQL setup. `it_can_load_dashboard_with_large_dataset_quickly` must pass (no 1305, no 1062). **`it_can_load_alerts_with_large_dataset_quickly`'s separate latency-budget assertion (GAP-045) must be reported separately** — not silently folded into GAP-044's pass/fail, and not treated as a GAP-044 regression if it independently fails on its own (pre-existing, unrelated) latency budget.
7. **Regression coverage for the specific seeded condition.** A test (or an assertion within the existing cold-start-style proof) that seeds a `permissions` row with `code='project.read'`, `name=NULL` (replicating `RoleSeeder`'s exact real-world shape) and proves `TenantUserFactoryTrait::ensurePermissionAttached()` (or the `Permission::firstOrCreate` call site directly) reuses that existing row by its canonical `code` identity, producing **zero** additional `INSERT` attempt — not merely "no exception," a positive assertion that the existing row's `id` is what gets attached.

## 6. GAP-040 governance (per Owner authorization §H)

This design does not edit any GAP-040 Gate artifact. If GAP-044 is
successfully released with the evidence design in §5, it is expected to
**restore** the broader end-to-end `RefreshDatabase` transaction-isolation
property GAP-040's Gate 3 previously (incompletely, per this Gate 1's §H1
finding) claimed to have established. Any post-release reconciliation of
GAP-040's own historical Gate-3 technical-assurance record (e.g. an
addendum, a correction note, or a governance-lint consideration) is
explicitly **out of scope for GAP-044** and will be handled separately by
Owner governance once GAP-044 has truthful release evidence — consistent
with Owner's own direction in the Gate-1 approval.

## 7. Explicit non-goals (boundary of this Gate 2)

- Does not modify `database/seeders/RoleSeeder.php` or any other seeder.
- Does not modify any GAP-040/GAP-041/GAP-042/GAP-043/GAP-045 artifact.
- Does not investigate or resolve whether any production (non-test) code path also looks up `Permission` by `name` (§4, explicitly flagged as unsearched).
- Does not change production schema, migrations, RBAC/authorization behavior, or tenant semantics under any option.
- Does not select between Option A's A1/A2 sub-shapes — an implementation-time decision within the approved Option A boundary, not a Gate 2 decision.
- Does not propose fixing GAP-045 (the separate, unrelated `DashboardPerformanceTest` latency-budget finding) — explicitly called out in §5.6 to be reported, not treated as GAP-044 scope.

## 8. Design Dependency Preflight

Unchanged from Gate 1: this remains solely a test-infrastructure fix (both
surfaces — `tests/TestCase.php` and `tests/Traits/TenantUserFactoryTrait.php`
are test-only files; no production schema, RBAC/authorization behavior, or
tenant semantics are proposed to change under Option A). **Design
Dependency Preflight is not triggered.** If implementation discovers a
need to touch production code, migrations, or business-domain semantics
beyond this boundary, work must stop and the appropriate preflight must run
before continuing.
