# GAP-043 — Gate 2 Design: `PerformanceMonitoringTest` MySQL schema-introspection portability fix

**Date:** 2026-08-21
**Gate:** 2 (design only — no implementation, no test-code commit, no application/schema/migration change, no Gate 3)
**Canonical baseline:** `origin/main` `25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac` (verified: no drift from the Gate-1-reviewed baseline — `origin/main` resolves to the exact same SHA the Owner reviewed at Gate 1 approval)
**Design branch:** `docs/GAP-043-gate2-design` (worktree `.worktrees/GAP-043-gate2-design`)

## Gate-1 provenance (carried forward byte-identically)

- Gate-1 approval PR: [#279](https://github.com/kha997/zenamanagephp/pull/279), final approval-record head `91b7f5535c99a701f751a22e444586d9738ed110`
- Owner-reviewed Gate-1 evidence head: `6d698645caff4546deee4d1e5cf40c7ec1c7fe40`
- Reviewed canonical baseline: `25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac`
- `docs/audits/2026-08-21-gap-043-performance-test-mysql-portability-evidence.md` and `docs/owner-decisions/GAP-043/01-request.md` were copied byte-for-byte from PR #279 head `91b7f553` into this branch's first commit (`62817e88`), diffed against the source blobs before committing, and confirmed identical. No intermediate/invalid Gate-1 revision was carried forward — only the final approved one.

Binding Gate-1 findings this design treats as settled fact (not re-litigated):
1. `tableInsertDefaults()` (`tests/Performance/PerformanceMonitoringTest.php:445-460`) calls `DB::select("PRAGMA table_info({$table})")` — SQLite-only syntax, hard `SQLSTATE[42000]` error on MySQL.
2. 7 of 10 test methods reach it via `createTestData()`; 6 fail directly on the PRAGMA call today, 1 (`test_api_performance_budgets`) is masked earlier by GAP-044's SAVEPOINT defect, 3 never reach it.
3. The defect is test-only — no application/schema/RBAC/tenant/business-domain code is implicated.
4. Gate 1 selected no technical solution; that is this Gate 2's job.

## Drift reconciliation

`origin/main` at investigation time (`25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac`) is byte-identical to the baseline the Owner reviewed at Gate-1 approval. No drift occurred between Gate-1 approval and Gate-2 design start, so no reconciliation was required on any GAP-043 evidence surface.

## Semantic reconstruction: why `tableInsertDefaults()` exists

`createTestData()` builds bulk fixtures via raw `DB::table($table)->insert($chunk)` (through `insertChunked()`), not Eloquent `create()`/`save()`, because the largest test (`test_large_dataset_performance`) inserts up to 5,000 rows and per-row Eloquent hydration/events would be far too slow for a performance-budget test. Raw insert bypasses two things Eloquent normally provides for free:

1. **Uniform column set per row.** Laravel's query builder `insert()` compiles one INSERT statement's column list from the *first* row of the batch and then binds every subsequent row's values positionally against that same column list (`vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php`, `insert()`/`toSqlSchema` path — it does not re-derive columns per row). If two rows in the same `array_chunk()` batch have different key sets (e.g. one task has `completed_at` because it's `status === 'completed'`, another doesn't), the resulting SQL/bindings pairing silently misaligns — a correctness bug, not a driver-portability one. `modelToInsertRow()` avoids this by forcing every row into the *full* column-listing shape (`array_fill_keys(array_keys($columns), null)` as the base), so every row in a batch always has the identical, identically-ordered key set regardless of what the factory happened to set.
2. **DB-level (not app-level) column defaults.** Eloquent's `save()` lets untouched attributes fall through to whatever the database applies. A raw `insert()` with an explicit `NULL` for an omitted key does **not** let the database apply its default — an explicit `NULL` always wins over a `DEFAULT` clause, and if that column is `NOT NULL`, the insert fails outright. `tableInsertDefaults()` exists to read each column's *declared schema default* and merge it in ahead of the factory's explicit attributes (`array_replace($nullFill, $defaults, $attributes)`), so any column the factory left unset still gets its real default value instead of an insert-breaking `NULL`.

This was verified against actual schema/factory state, not inferred from names — see the column inventory below.

## Column/default inventory: `projects` and `tasks`

Built from `database/migrations/2025_09_15_041906_create_projects_table.php` + `2025_09_15_144442_unify_projects_table_schema.php` + `2025_09_17_162520_add_created_by_to_projects_table.php` + `2025_09_20_071616_optimize_existing_tables_structure.php` (`optimizeProjectsTable()`/`optimizeTasksTable()`) + `2026_02_11_120000_add_actual_cost_to_projects_table.php` for `projects`; `2025_09_15_042450_create_tasks_table.php` + `2025_09_17_043044_add_missing_fields_to_tasks_table.php` + `2025_09_20_071616_...` + `2026_02_02_101000_rename_tasks_dependencies_column.php` + `2026_02_14_000001_add_completed_at_to_tasks_table.php` + `2026_03_12_000001_add_s1_2_generator_columns.php` for `tasks`; cross-checked against `database/factories/ProjectFactory.php` and `database/factories/TaskFactory.php` (the `App\Models\Project`/`App\Models\Task` factories actually used by `PerformanceMonitoringTest`, confirmed by its `use App\Models\Project;`/`use App\Models\Task;` imports).

### `projects`

| Column | Nullable | NOT NULL + DB default | NOT NULL, no default | Set by factory? |
|---|---|---|---|---|
| `id` (ulid PK) | no | no | **yes** | yes (test sets explicitly via `newModelKey()`) |
| `code` | no | no | **yes** (unique) | yes |
| `name` | no | no | **yes** | yes |
| `tenant_id`, `client_id`, `pm_id`, `created_by`, `template_id` | yes | — | — | yes (tenant_id/pm_id/created_by explicitly overridden by the test; client_id/template_id left null by factory, which is valid — nullable) |
| `description`, `start_date`, `end_date`, `tags`, `settings`, `last_activity_at` | yes | — | — | yes (factory sets all except `last_activity_at`, which stays null — valid, nullable) |
| `status`, `progress`, `budget_total`, `budget_planned`, `budget_actual`, `priority` | no | yes | — | yes (factory sets all) |
| `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost` | no | yes | — | **no** — not in `ProjectFactory::definition()` at all |
| `created_at`, `updated_at`, `deleted_at` | timestamps nullable/soft-delete | — | — | set explicitly by `createTestData()` itself, not the factory |

### `tasks`

| Column | Nullable | NOT NULL + DB default | NOT NULL, no default | Set by factory? |
|---|---|---|---|---|
| `id` (ulid PK) | no | no | **yes** | yes (test overrides via `newModelKey()`) |
| `project_id` | no | no | **yes** | yes |
| `name` | no | no | **yes** | yes |
| `tenant_id`, `assignee_id`, `created_by`, `component_id`, `phase_id`, `parent_id`, `title`, `assigned_to`, `conditional_tag`, `updated_by`, `work_instance_id`, `work_instance_step_id` | yes | — | — | tenant_id/assignee_id/created_by explicitly overridden by the test; the rest left null by factory or absent from it — valid, nullable |
| `description`, `start_date`, `end_date`, `estimated_hours`, `actual_hours`, `spent_hours`, `tags`, `watchers`, `completed_at`, `last_activity_at` | yes | — | — | most set by factory; `spent_hours`, `completed_at`, `last_activity_at` left null — valid, nullable |
| `status`, `priority`, `progress_percent`, `visibility`, `is_hidden`, `client_approved`, `order` | no | yes | — | yes (factory sets all) |
| `estimated_cost`, `actual_cost` | no | yes | — | yes (factory explicitly sets both to `0`) |
| `risk_level`, `complexity`, `effort_points`, `time_spent`, `is_billable` | no | yes | — | **no** — not in `TaskFactory::definition()` at all |
| `dependencies_json` | yes | — | — | factory sets key `dependencies` (pre-rename name); after the 2026-02-02 rename migration this key no longer matches any column and is silently dropped by `TaskFactory::filterTaskAttributes()`'s `array_intersect_key` against live `Schema::getColumnListing('tasks')` — resolves to `NULL`, which is valid since the column is nullable |
| `created_at`, `updated_at`, `deleted_at` | timestamps nullable/soft-delete | — | — | set explicitly by `createTestData()` |

### Answering the design questions (A–G)

**A. Why does `modelToInsertRow()` fill every column with NULL first?** To guarantee every row in an `insertChunked()` batch has an identical column-key set (uniform shape), which raw multi-row `insert()` requires for correct SQL/binding alignment (see semantic reconstruction above). This is unrelated to defaults — it is the row-shape mechanism the defaults get merged into.

**B/C. Which DB defaults does `tableInsertDefaults()` actually contribute?** Confirmed via the inventory above: `risk_level`, `complexity`, `effort_points`, `time_spent`, `is_billable` (tasks) and `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost` (projects) are all `NOT NULL` columns with a DB-level default that the factories do not set. Every other `NOT NULL` column either has no default and is explicitly set by the factory/test (`id`, `code`, `name`, `project_id`), or is nullable (any other unset column resolves to a valid `NULL`).

**D. Required for constraint validity, semantics, or merely defensive?** **Required for constraint validity**, not merely defensive. Without `tableInsertDefaults()`, the columns named above would be written as explicit `NULL` by `modelToInsertRow()`'s base fill, which violates their `NOT NULL` constraint on both MySQL and SQLite (SQLite enforces `NOT NULL` the same as MySQL — it was never silently permissive here; the reason this has only ever run on SQLite without incident is that `tableInsertDefaults()` was successfully reading real defaults via `PRAGMA table_info`, not that SQLite skips the constraint). None of the test assertions read these particular columns' values (they assert timing/query-count/memory budgets — confirmed by Gate 1 Section B and re-confirmed here by reading all 7 `createTestData()`-calling test bodies), so semantic/assertion fidelity is not at stake — only insert-time constraint satisfaction is.

**E. Effect of omitting absent columns instead of inserting NULL?** Correct in isolation (the DB applies its own default when a column is *absent from the INSERT column list*), but incompatible with the uniform-row requirement from (A) once combined with heterogeneous per-row attribute sets (e.g. `completed_at` present only for completed tasks) — see F.

**F. Does batch insert require identical key sets, and how does that constrain the design?** Yes (confirmed by reading `Illuminate\Database\Query\Builder::insert()` — it derives the SQL column list once from the batch and binds every row positionally against it). This is why any design that keeps the current single-batch-per-table `insertChunked()` structure must keep filling every row to the *full* schema-column shape; it rules out solving GAP-043 by having rows only carry their own explicitly-set attributes.

**G. Performance impact for the 1,000-project / 5,000-task fixture?** `tableInsertDefaults()` is called exactly twice per `createTestData()` invocation (once per table), not once per row — its cost is two schema-metadata queries total regardless of fixture size, negligible against 1,000+5,000 row inserts. Any framework-native replacement that is also a single call per table (not per row) preserves this — see Option A below.

## Framework/API verification

Installed Laravel version (`composer.lock`): **`laravel/framework` v12.63.0**.

Verified directly in `vendor/laravel/framework/src/Illuminate/Database/Schema/Builder.php:397`:

```php
/**
 * @return list<array{name: string, type: string, type_name: string, nullable: bool,
 *                     default: mixed, auto_increment: bool, comment: string|null,
 *                     generation: array{type: string, expression: string|null}|null}>
 */
public function getColumns($table)
```

`Schema::getColumns($table)` is a **base `Builder` method** — not driver-specific, not overridden differently in `MySqlBuilder` or `SQLiteBuilder` (both extend `Builder` without touching `getColumns()`; only `dropAllTables()`/schema-listing helpers are overridden). It is the successor to the now-deprecated Doctrine-DBAL-based schema inspection this project does not otherwise depend on. Its output is normalized per-driver by the connection's query processor before returning:

- `Illuminate\Database\Query\Processors\MySqlProcessor::processColumns()` — maps MySQL's `information_schema` columns query into the shared shape, `'default' => $result->default` (raw `COLUMN_DEFAULT` value, unquoted for non-string literals, e.g. `low` for an enum default).
- `Illuminate\Database\Query\Processors\SQLiteProcessor::processColumns()` — maps `PRAGMA table_xinfo`-derived output into the same shape, `'default' => $result->dflt_value`-equivalent (SQLite quotes string literal defaults, e.g. `'low'`).

Both feed the same `'name'`/`'nullable'`/`'default'` keys into caller code, so `tableInsertDefaults()` can call one API and get one shape back from either driver — no `if ($isSqlite)` branch needed. The quote-stripping already present in `tableInsertDefaults()` (`preg_replace("/^'(.*)'$/", '$1', ...)`) already tolerates both the quoted (SQLite) and unquoted (MySQL) representations, since it only strips quotes when present.

No LOCAL probe against a live MySQL/SQLite instance was run for this Gate 2 — the verification above is static (reading the installed framework source directly, which is authoritative for "does this API exist and what does it return" without needing to execute it) and is judged sufficient at design stage. Gate 3 will require LIVE evidence per the acceptance contract below, which is where this gets exercised for real.

## Option comparison

### Option A — Framework-native portable metadata (`Schema::getColumns()`) — **RECOMMENDED**

Replace the `DB::select("PRAGMA table_info({$table})")` loop with a single `Schema::getColumns($table)` call; keep the existing `dflt_value`→`$defaults[$column->name]` extraction logic (adjusted for the array-based row shape `getColumns()` returns vs. the PRAGMA row objects) and the existing quote-stripping regex.

- **Availability:** confirmed present in installed Laravel 12.63.0.
- **SQLite/MySQL equivalence:** same method, same return shape, both drivers — verified above.
- **Default normalization:** processor-level normalization already handles both quoted (SQLite) and unquoted (MySQL) string defaults; existing quote-stripping regex remains correct as a defensive pass.
- **Expression handling:** `getColumns()` exposes a `generation` key for computed/generated columns; none of the load-bearing default columns identified in the inventory are generated, so this does not need special handling for GAP-043's scope, but the field is available if a future column needs it.
- **Bespoke parsing required:** none — this eliminates all raw-SQL string parsing, replacing it with structured array access.
- **Maintenance:** lowest of all four options — one line of intent ("read column defaults portably"), no per-driver knowledge required, follows the framework's own abstraction rather than reimplementing it.

### Option B — Driver-specific introspection (branch SQLite vs. MySQL)

Keep `tableInsertDefaults()etdefaults`'s current PRAGMA-based branch for SQLite, add a parallel MySQL branch reading `information_schema.COLUMNS` directly.

- **Clarity:** worse than A — two code paths to read and keep in sync for identical semantic intent.
- **Custom SQL:** required for both branches (PRAGMA already bespoke; a new hand-written `information_schema` query would be needed too).
- **Metadata-format normalization:** now the test helper's own responsibility (reconciling MySQL's unquoted defaults against SQLite's quoted ones) instead of the framework's — duplicating work `Schema::getColumns()` already does correctly.
- **Maintenance cost:** higher — any future driver added to CI (e.g. PostgreSQL, mentioned nowhere in this repo today but a real possibility) would need a third branch.
- **Verdict:** the pattern this repo already uses elsewhere (e.g. `2025_09_20_145756_disable_foreign_keys_for_testing.php`) is justified there because Laravel has no portable API for disabling FK checks — but for column-default introspection, a portable API *does* exist (Option A), so paying this complexity cost here is not justified.

### Option C — Remove schema-default reconstruction; let the DB apply its own defaults on omitted columns

Redesign `modelToInsertRow()` so absent attributes are omitted from each row rather than explicitly NULL-filled, relying on the DB's own `DEFAULT` clause at insert time.

- **Batch insert key consistency:** **blocking problem.** As established in F above, `Illuminate\Database\Query\Builder::insert()` requires every row in one batch to share an identical column-key set. Rows differ today in which optional attributes are set (e.g. `completed_at` only for completed tasks), so naively omitting "whatever the factory didn't set" per-row would produce non-uniform rows and corrupt the insert. Making this option viable would require either (a) grouping rows into sub-batches by attribute-shape before each `insertChunked()` call, or (b) reintroducing a full-schema uniform shape anyway (defeating the point of "omit columns").
- **NOT NULL fields:** would still need *some* mechanism to guarantee the identified load-bearing columns (`risk_level`, `complexity`, etc.) are never NULL-filled, which is most of what Option A already solves more simply.
- **Determinism:** sub-batching by shape is more moving parts than the current single-batch-per-table design, for a test helper whose sole purpose is inserting throwaway fixtures — added complexity out of proportion to the problem.
- **Fixture performance:** sub-batching by shape could reduce batch sizes (more, smaller batches instead of one `array_chunk(...,500)` pass), which cuts against the "efficient bulk fixture generation" criterion for the 5,000-task case.
- **Verdict: rejected.** Solves a portability problem by introducing a new correctness/complexity problem in the same helper. Option A solves the actual bug (SQLite-only syntax) without touching the (working) row-uniformity design.

### Option D — Explicit deterministic fixture values (drop introspection, hardcode the small set of load-bearing defaults)

Since the inventory above found the load-bearing set is genuinely small (5 task columns, 6 project columns), hardcode those exact values (`'risk_level' => 'low', 'complexity' => 'moderate', 'effort_points' => 1, 'time_spent' => 0, 'is_billable' => true`, etc.) directly in `modelToInsertRow()` or the factory calls, and delete `tableInsertDefaults()` entirely.

- **Schema drift risk:** real. If a future migration changes `risk_level`'s default from `'low'` to something else, or adds a new `NOT NULL`-with-default column to `projects`/`tasks`, this hardcoded list silently goes stale and the test starts failing on the *next* schema change instead of adapting automatically — exactly the kind of coupling `tableInsertDefaults()` was written to avoid (per Gate 1 Section B's "no driver branching anywhere, no SQLite-only doc comment" finding: this helper was written to track the schema, not fight it).
- **Duplication:** the six-and-five-value lists duplicate information the schema/migrations already declare as the source of truth.
- **Readability:** marginally better locally (no introspection call to trace), but at the cost of a second place recording facts the migrations already record.
- **Determinism:** equally deterministic to Option A within a single schema version; less robust across schema versions.
- **Business-semantics risk:** low here (these are defensive fixture defaults, not business-meaningful assertions), but the schema-drift risk alone is enough to reject this in favor of Option A, which has none of that risk and is not meaningfully more complex.
- **Verdict: rejected**, mainly on schema-drift-risk grounds — Option A gets the same portability fix with zero ongoing coupling to today's specific default values.

### Recommendation: Option A

Option A is the only option that (1) eliminates the SQLite-only syntax, (2) requires no driver branching, (3) does not touch the working row-uniformity design that batch inserts depend on, and (4) carries no schema-drift risk. It is also the smallest diff that is *correct for the right reason* (not merely smallest by line count — Options C and D are smaller in some readings but introduce new risk).

## Recommended future implementation surface

**File:** `tests/Performance/PerformanceMonitoringTest.php` only — specifically the body of `tableInsertDefaults()` (lines 445-460). No other test or support file needs to change: `createTestData()`, `modelToInsertRow()`, and `insertChunked()` are unaffected (their contracts stay identical — `tableInsertDefaults()` continues to return `array<string, string>` keyed by column name).

No other TEST/support file is required. No application code, migration, model, or schema, RBAC/tenant, or CRM/Project/Service-Line/OPPM/Finance/Treasury semantics are implicated by this design — confirmed by the column inventory above (every load-bearing default is a plain scalar test-fixture default, not a business rule) and by Gate 1's independent scope confirmation. **The Design Dependency Preflight is not triggered.**

## RED/GREEN verification design (for the eventual Gate-3 implementation)

- **RED (pre-fix, already established at Gate 1):** LIVE run `32471481216`, job `96739005481` — `Tests: 7 failed, 3 passed`, 6 of the 7 failures showing `SQLSTATE[42000] ... near 'PRAGMA table_info(...)'`.
- **GREEN attribution boundary (binding — see acceptance contract below):** GAP-043's GREEN claim is scoped to *this helper's call path*, not the whole test class, because GAP-044 independently controls whether `test_api_performance_budgets` can even reach `createTestData()`'s body.

## LIVE Gate-3 acceptance contract

Minimum future GREEN evidence required to close GAP-043 (attribution-safe — does **not** require the whole `PerformanceMonitoringTest` class to be green):

1. No SQLite-only schema-introspection call (`PRAGMA table_info` or equivalent) remains anywhere on the `tableInsertDefaults()` code path.
2. A genuine LIVE MySQL run of `PerformanceMonitoringTest` progresses past the former PRAGMA failure point for all 6 tests that failed on it directly at Gate 1 (`test_page_performance_budgets`, `test_database_query_performance`, `test_memory_usage_performance`, `test_concurrent_request_performance`, `test_large_dataset_performance`, `test_cache_performance`) — i.e. none of the 6 fail with `SQLSTATE[42000]` referencing `PRAGMA` any longer.
3. The 7th caller (`test_api_performance_budgets`) is evaluated separately: if GAP-044 is still unresolved at the time GAP-043's fix lands, this test remaining red on its (GAP-044-owned) SAVEPOINT symptom does **not** block GAP-043's own GREEN claim — it is explicitly out of GAP-043's attribution. If GAP-044 has been fixed by then and this test still fails on a PRAGMA-shaped error, that *would* count against GAP-043.
4. Any newly exposed downstream failure (e.g. an assertion that turns out to implicitly depend on a specific default value once real MySQL defaults are read instead of SQLite's) is classified and reported, not silently patched around, per the same "classify, don't absorb" discipline Gate 1 used for the dormant PRAGMA findings.
5. SQLite compatibility is verified to remain valid (the 3 tests that don't call `createTestData()`, plus all 7 re-run under the existing SQLite-backed CI jobs, continue to pass) — this design does not weaken or remove SQLite support, only adds correct MySQL support alongside it.
6. Gate 3 must present this evidence from a genuine MySQL CI run (not a local/SQLite substitute) — consistent with the project's established LIVE-evidence discipline for this register.

Thresholds, `--group` test selection, and fail-on-empty-selection behavior in `automated-testing.yml`'s `performance-tests` job are **not weakened** by this design; nothing about the fix requires touching the CI workflow's selection logic (that is GAP-041's already-fixed domain).

## Explicit exclusions

- **GAP-041** (test-selection truthfulness): not reopened; already fixed and not touched by this design.
- **GAP-042** (RBAC production-fidelity): unrelated domain, not touched.
- **GAP-044** (SAVEPOINT defect masking `test_api_performance_budgets`): not investigated or fixed here; the acceptance contract above explicitly carves out how GAP-043's GREEN claim is unaffected by GAP-044's state. GAP-044's own Gate 1/2/3 lifecycle is a separate, independently-tracked effort.
- **GAP-045** (latency-budget assertions in `DashboardPerformanceTest.php`): confirmed at Gate 1 to be structurally unrelated (zero PRAGMA occurrences in that file); not touched.

## Dormant PRAGMA exclusion

The 11 other dormant, unguarded `PRAGMA` usages in Feature/Integration test files (10× `foreign_keys=OFF`, 1× `defer_foreign_keys`, cataloged in Gate 1 Section C) remain explicitly **out of scope**. They are mentioned here only as context, per the Owner's directive: not fixed, not refactored, no new gap auto-registered, and GAP-043's scope is not widened into repo-wide DB-portability work.

## Design Dependency Preflight disposition

**Not triggered.** The recommended implementation surface (Option A) is confined to one private test-helper method's body, touches no application code, migration, schema, model, RBAC/tenant behavior, or CRM/Project/Service-Line/OPPM/Finance/Treasury domain semantics. This matches Gate 1's own scope confirmation and is independently reconfirmed here by the column/default inventory (every load-bearing default identified is a plain fixture-level scalar, not a business rule).
