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

1. **Uniform column set per row.** Verified by reading `Illuminate\Database\Query\Builder::insert()` (`vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php:4092-4125`) and `Grammar::compileInsert()` (`.../Query/Grammars/Grammar.php:1217-1242`) directly: `insert()` calls `ksort($value)` on every record independently (so within one record, keys are alphabetized), then `compileInsert()` derives the SQL column list *once*, from `array_keys(array_first($values))` — the first record only — and separately parameterizes every record via `$this->parameterize($record)`, which just walks that record's own values regardless of how many there are; bindings are flattened across all records with `Arr::flatten($values, 1)`. Two concrete failure modes follow if records in one batch have non-identical key sets: (a) **different key *counts*** (e.g. one task row has `completed_at` present, another doesn't) produces a column list of length *N* from the first row paired against a `VALUES (...)` tuple of a *different* length for a later row — an invalid SQL column/value-count shape, which MySQL and SQLite both reject outright (`SQLSTATE 21S01`-class error), not a silent bug; (b) **same key *count* but different key *identities*** (e.g. row A has columns `[a,b,c]`, row B has `[a,b,d]` after `ksort`) compiles successfully but silently binds row B's `d` value into the SQL column list's `c` position — genuine silent misalignment, but only in this narrower same-cardinality-different-identity case. `modelToInsertRow()` avoids both failure modes entirely by forcing every row into the *full* column-listing shape (`array_fill_keys(array_keys($columns), null)` as the base), so every row in a batch always has the identical, identically-named key set regardless of what the factory happened to set.
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
| `status`, `progress`, `budget_planned`, `budget_actual`, `priority` | no | yes | — | yes (factory sets all) |
| `budget_total`, `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost` | no | yes | — | **no** — not in `ProjectFactory::definition()` at all (v1 of this design missed `budget_total` — corrected per Owner review; `ProjectFactory` sets `budget_planned`/`budget_actual` but never `budget_total`, a distinct column left over from the original `create_projects_table` migration before the `unify_projects_table_schema` migration added the `_planned`/`_actual` split) |
| `created_at`, `updated_at`, `deleted_at` | timestamps nullable/soft-delete | — | — | set explicitly by `createTestData()` itself, not the factory |

**Corrected load-bearing set for `projects`: 7 columns** — `budget_total`, `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost`.

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

**Corrected load-bearing set for `tasks`: 5 columns** — `risk_level`, `complexity`, `effort_points`, `time_spent`, `is_billable`.

**Total corrected load-bearing set: 12 table-column pairs (7 `projects` + 5 `tasks`)**, re-verified by a genuine `Schema::getColumns()` runtime probe against both drivers (see LOCAL probe evidence section below) — the probe's non-null-default output for `projects` lists exactly `status, priority, is_template, completion_percentage, progress, actual_cost, budget_total, estimated_hours, actual_hours, risk_level, budget_planned, budget_actual` (12 total columns with any DB default), of which `status, priority, progress, budget_planned, budget_actual` are the 5 already explicitly set by the factory (confirmed redundant with `tableInsertDefaults()` — its value is immediately overwritten by the later `attributes` layer in `array_replace($nullFill, $defaults, $attributes)`), leaving the same 7 load-bearing columns identified above. Symmetrically for `tasks`.

| `dependencies_json` | yes | — | — | factory sets key `dependencies` (pre-rename name); after the 2026-02-02 rename migration this key no longer matches any column and is silently dropped by `TaskFactory::filterTaskAttributes()`'s `array_intersect_key` against live `Schema::getColumnListing('tasks')` — resolves to `NULL`, which is valid since the column is nullable |
| `created_at`, `updated_at`, `deleted_at` | timestamps nullable/soft-delete | — | — | set explicitly by `createTestData()` |

### Answering the design questions (A–G)

**A. Why does `modelToInsertRow()` fill every column with NULL first?** To guarantee every row in an `insertChunked()` batch has an identical column-key set (uniform shape), which raw multi-row `insert()` requires for correct SQL/binding alignment (see semantic reconstruction above). This is unrelated to defaults — it is the row-shape mechanism the defaults get merged into.

**B/C. Which DB defaults does `tableInsertDefaults()` actually contribute?** Confirmed via the inventory above and the LOCAL runtime probe: **12 table-column pairs are `NOT NULL` with a DB-level default the factories don't set** — `budget_total`, `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost` (7 on `projects`) and `risk_level`, `complexity`, `effort_points`, `time_spent`, `is_billable` (5 on `tasks`). Every other `NOT NULL` column either has no default and is explicitly set by the factory/test (`id`, `code`, `name`, `project_id`), or is nullable (any other unset column resolves to a valid `NULL`).

**D. Required for constraint validity, semantics, or merely defensive?** **Required for constraint validity**, not merely defensive. Without `tableInsertDefaults()`, the columns named above would be written as explicit `NULL` by `modelToInsertRow()`'s base fill, which violates their `NOT NULL` constraint on both MySQL and SQLite (SQLite enforces `NOT NULL` the same as MySQL — it was never silently permissive here; the reason this has only ever run on SQLite without incident is that `tableInsertDefaults()` was successfully reading real defaults via `PRAGMA table_info`, not that SQLite skips the constraint). None of the test assertions read these particular columns' values (they assert timing/query-count/memory budgets — confirmed by Gate 1 Section B and re-confirmed here by reading all 7 `createTestData()`-calling test bodies), so semantic/assertion fidelity is not at stake — only insert-time constraint satisfaction is.

**E. Effect of omitting absent columns instead of inserting NULL?** Correct in isolation (the DB applies its own default when a column is *absent from the INSERT column list*). Whether this is usable in practice depends on *which* columns get omitted and whether that omission set is identical across every row in a batch — see F and Option C below, which found a workable uniform-omission variant this v1 design had dismissed too broadly.

**F. Does batch insert require identical key sets, and how does that constrain the design?** Yes — confirmed precisely by reading `Illuminate\Database\Query\Builder::insert()`/`Grammar::compileInsert()` (see the semantic-reconstruction section above for the exact mechanics: the SQL column list comes from the first record only, values are parameterized per-record independently). Every row in a batch must carry an **identical set of column keys** — not necessarily every column in the table, but whatever set is chosen must be the same for every row in that `insertChunked()` batch. This is why any design that keeps the current single-batch-per-table structure must keep every row's key set uniform; it does not, by itself, force that set to be the *full* schema shape — a uniform *subset* (omitting the same columns from every row) is also valid, which is what Option C2 below explores.

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

`Schema::getColumns($table)` is a **base `Builder` method** — not driver-specific, not overridden differently in `MySqlBuilder` or `SQLiteBuilder` (both extend `Builder` without touching `getColumns()`; only `dropAllTables()`/schema-listing helpers are overridden). It is the successor to the now-deprecated Doctrine-DBAL-based schema inspection this project does not otherwise depend on.

**Precise characterization of the portability contract (corrected v2, per Owner Correction A):** `Schema::getColumns()` is portable at the *Laravel API boundary* — the per-driver query processors (`MySqlProcessor::processColumns()`, `SQLiteProcessor::processColumns()`) normalize the metadata *structure* into a common shape (`name`, `nullable`, `default`, `type_name`, `generation`, …), so both drivers are queried through the same method and return the same set of keys. This does **not** mean SQLite and MySQL return byte-identical raw `default` representations — they don't, and the processors do not normalize that literal representation either (see LOCAL probe evidence immediately below, which shows exactly how the raw values differ). Reconciling that specific SQLite-quoted-vs-MySQL-unquoted literal difference is the job of the *existing* quote-stripping logic already present in `tableInsertDefaults()` (`preg_replace("/^'(.*)'$/", '$1', ...)`), not the framework processors — Option A keeps that logic as-is; it only replaces the raw-SQL introspection call feeding it.

**On whether any load-bearing default is a SQL expression (corrected v2, per Owner Correction B):** the `generation` key concerns *generated/computed columns specifically* (`STORED GENERATED`/`VIRTUAL GENERATED`) — `generation == null` proves only that a column is not a generated column; it is not, by itself, proof that the column's `default` is a plain literal rather than a SQL expression (e.g. `DEFAULT (CURRENT_TIMESTAMP)`), since a non-generated column can still carry an expression default. The actual basis for concluding none of the 12 load-bearing defaults are SQL expressions is: (a) the LOCAL probe's raw `default` values for all 12 are literal text (`'low'`, `'0.00'`, `'1'`, `'draft'`, etc. — quoted-string or bare-numeral literals, not function/expression syntax), and (b) the migrations that declare them (`optimizeProjectsTable()`/`optimizeTasksTable()` in `2025_09_20_071616_optimize_existing_tables_structure.php`, and the other migrations cited in the inventory above) all use Blueprint's plain `->default($scalar)` form, never a raw-expression default. `generation == null` is reported alongside this only as the separate, correct fact that these are not generated columns — not as evidence about expression-vs-literal status.

## LOCAL probe evidence

Per Owner instruction, a temporary, uncommitted characterization probe was run against both **genuine migrated MySQL 8.0** and **genuine migrated SQLite** using this repository's actual installed Laravel 12.63.0 runtime, before finalizing this v2 design. Both probes call `Schema::getColumns('projects')` / `Schema::getColumns('tasks')` directly (no mocking) and apply `tableInsertDefaults()`'s exact proposed normalization (null/`"NULL"` exclusion, string cast, existing single-quote-stripping regex) to the results.

**Setup (LOCAL, uncommitted, restored afterward):**
- **MySQL:** a throwaway, isolated `mysql:8.0` Docker container (`gap043-probe-mysql`, port `33061`, database `gap043_probe`) — not the repo's shared dev/CI database, torn down after the probe. `php artisan migrate --force` run against it from this branch's exact working tree (all 191 migrations, including every migration touching `projects`/`tasks`).
- **SQLite:** a throwaway file-based SQLite database in the session scratchpad, migrated the same way.
- Probe script: a standalone PHP script (not committed) that boots the Laravel container via `bootstrap/app.php`, then calls `Schema::getColumns()` for both tables on each connection and prints raw + normalized output.

**Result — `projects` (12 non-null-default columns found on both drivers, identical set):**

| Column | MySQL raw `default` | MySQL normalized | SQLite raw `default` | SQLite normalized | Match |
|---|---|---|---|---|---|
| `status` | `draft` | `draft` | `'draft'` | `draft` | ✅ |
| `priority` | `medium` | `medium` | `'medium'` | `medium` | ✅ |
| `is_template` | `0` | `0` | `'0'` | `0` | ✅ |
| `completion_percentage` | `0.00` | `0.00` | `'0'` | `0` | ✅ (semantically equivalent; decimal-vs-integer literal text, both correct for a `decimal(5,2)` column) |
| `progress` | `0.00` | `0.00` | `'0'` | `0` | ✅ (same as above; not load-bearing — factory sets `progress`) |
| `actual_cost` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `budget_total` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `estimated_hours` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `actual_hours` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `risk_level` | `low` | `low` | `'low'` | `low` | ✅ |
| `budget_planned` | `0.00` | `0.00` | `'0'` | `0` | ✅ (not load-bearing — factory sets it) |
| `budget_actual` | `0.00` | `0.00` | `'0'` | `0` | ✅ (not load-bearing — factory sets it) |

**Result — `tasks` (14 non-null-default columns found on both drivers, identical set):**

| Column | MySQL raw `default` | MySQL normalized | SQLite raw `default` | SQLite normalized | Match |
|---|---|---|---|---|---|
| `status` | `todo` | `todo` | `'todo'` | `todo` | ✅ |
| `priority` | `medium` | `medium` | `'medium'` | `medium` | ✅ |
| `is_hidden` | `0` | `0` | `'0'` | `0` | ✅ |
| `visibility` | `team` | `team` | `'team'` | `team` | ✅ |
| `client_approved` | `0` | `0` | `'0'` | `0` | ✅ |
| `progress_percent` | `0` | `0` | `'0'` | `0` | ✅ |
| `estimated_cost` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `actual_cost` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `risk_level` | `low` | `low` | `'low'` | `low` | ✅ |
| `complexity` | `moderate` | `moderate` | `'moderate'` | `moderate` | ✅ |
| `effort_points` | `1` | `1` | `'1'` | `1` | ✅ |
| `time_spent` | `0.00` | `0.00` | `'0'` | `0` | ✅ |
| `is_billable` | `1` | `1` | `'1'` | `1` | ✅ |
| `order` | `0` | `0` | `'0'` | `0` | ✅ (not load-bearing — factory sets it) |

**Raw-representation difference confirmed (this corrects v1's imprecise claim):** MySQL's `information_schema`-derived `default` value is the plain literal text with no surrounding quote characters (e.g. the PHP string is literally `draft`, 5 characters). SQLite's `default` value, because Laravel reads it from the column's declared-default clause as written in the table's DDL, is the literal text *including the embedded single-quote characters* (e.g. the PHP string is literally `'draft'`, 7 characters, with real `'` characters at position 0 and 6 — confirmed via `var_export()` in the probe output, which escapes those embedded quotes as `\'draft\'` when displaying the string). **The two drivers do not return byte-identical raw values.** The existing quote-stripping regex in `tableInsertDefaults()` (`preg_replace("/^'(.*)'$/", '$1', ...)`) is exactly what reconciles this: it strips the embedded quote characters when present (SQLite case) and passes the value through unchanged when absent (MySQL case), producing an identical normalized value on both drivers for all 26 non-null-default columns probed (12 on `projects`, 14 on `tasks`), including all 12 load-bearing ones.

**`generation` field:** `null` for every column on both tables, both drivers — confirming none of the 26 probed columns (including the 12 load-bearing ones) are computed/generated columns. This is a separate fact from whether a default is a literal or a SQL expression: that determination rests on the raw `default` values themselves (all plain quoted/unquoted literals — `'low'`, `'0.00'`, `'1'`, etc., no function/expression syntax) and the migrations' use of Blueprint's plain `->default($scalar)` form throughout, not on `generation`. All 12 load-bearing defaults are plain literal scalars, which is exactly the class of default `tableInsertDefaults()`'s normalization logic is designed to handle.

**Evidence label: LOCAL.** No committed file changes resulted from this probe — the throwaway MySQL container was removed and the scratchpad probe script/SQLite file were not committed (see confirmation in the Return section of this round's response). This does not substitute for Gate 3's mandatory genuine-MySQL-CI LIVE evidence requirement, which remains unchanged and non-waivable.

## Option comparison

### Option A — Framework-native portable metadata (`Schema::getColumns()`) — **RECOMMENDED**

Replace the `DB::select("PRAGMA table_info({$table})")` loop with a single `Schema::getColumns($table)` call; keep the existing `dflt_value`→`$defaults[$column->name]` extraction logic (adjusted for the array-based row shape `getColumns()` returns vs. the PRAGMA row objects) and the existing quote-stripping regex.

- **Availability:** confirmed present in installed Laravel 12.63.0.
- **SQLite/MySQL equivalence:** same method, same return shape, both drivers — verified above.
- **Default normalization:** processor-level normalization handles the metadata *structure* (common `name`/`nullable`/`default`/`type_name` keys across drivers); it does not normalize the raw literal representation itself — SQLite's raw `default` is quoted (embeds literal `'...'` characters), MySQL's is unquoted. Reconciling that difference is the job of `tableInsertDefaults()`'s existing quote-stripping regex, which Option A keeps unchanged and which the LOCAL probe confirms produces identical normalized values on both drivers for all 12 load-bearing defaults.
- **Expression handling:** `getColumns()` exposes a `generation` key for computed/generated columns specifically — it is evidence about generated-vs-not, not about literal-vs-expression defaults. The 12 load-bearing default columns are confirmed non-generated (`generation == null`) *and separately* confirmed to be plain literal defaults (not SQL expressions) by reading their raw probe values and their migrations' plain `->default($scalar)` declarations — see the LOCAL probe evidence section for the full basis.
- **Bespoke parsing required:** none — this eliminates all raw-SQL string parsing, replacing it with structured array access.
- **Maintenance:** lowest of all four options — one line of intent ("read column defaults portably"), no per-driver knowledge required, follows the framework's own abstraction rather than reimplementing it.

### Option B — Driver-specific introspection (branch SQLite vs. MySQL)

Keep `tableInsertDefaults()`'s current PRAGMA-based branch for SQLite, add a parallel MySQL branch reading `information_schema.COLUMNS` directly.

- **Clarity:** worse than A — two code paths to read and keep in sync for identical semantic intent.
- **Custom SQL:** required for both branches (PRAGMA already bespoke; a new hand-written `information_schema` query would be needed too).
- **Metadata normalization:** worse than A — the helper would have to own two driver-specific query/result shapes before feeding their raw default values through the same literal-normalization semantics. This reimplements the metadata abstraction `Schema::getColumns()` already provides; it does not avoid the helper's existing quote-stripping responsibility.
- **Maintenance cost:** higher — any future driver added to CI (e.g. PostgreSQL, mentioned nowhere in this repo today but a real possibility) would need a third branch.
- **Verdict:** the pattern this repo already uses elsewhere (e.g. `2025_09_20_145756_disable_foreign_keys_for_testing.php`) is justified there because Laravel has no portable API for disabling FK checks — but for column-default introspection, a portable API *does* exist (Option A), so paying this complexity cost here is not justified.

### Option C — Remove schema-default reconstruction; let the DB apply its own defaults on omitted columns

Redesign `modelToInsertRow()` so some attributes are omitted from the INSERT rather than explicitly NULL-filled, relying on the DB's own `DEFAULT` clause. Re-evaluated per Owner review into three concrete variants rather than one blanket rejection:

**C1 — Per-row omission with heterogeneous keys** (each row omits whatever its own factory instance happened to leave unset): **rejected.** Confirmed via the exact `Builder::insert()`/`compileInsert()` mechanics in the semantic-reconstruction section above — this produces either a hard column/value-count SQL error (when omission counts differ row-to-row, e.g. `completed_at` present on completed tasks and absent on others) or silent value misalignment (when counts coincidentally match but identities differ). Both are worse than the current SQLite-only-syntax bug, not fixes for it.

**C2 — Uniform omission** (deliberately omit the *same* fixed set of columns — the 12 load-bearing pairs identified above — from *every* row in a batch, regardless of that row's other attributes, so all rows keep an identical key set): **feasible, evaluated fairly, still rejected in favor of Option A.** This is technically workable *today* specifically because the 12 load-bearing columns are never set by either factory for any row (confirmed by the inventory above and the LOCAL probe) — so a static, table-wide omission list wouldn't need to vary per row. Trade-offs against Option A:
  - **Implementation surface:** larger, not smaller. `modelToInsertRow()`'s base-fill (`array_fill_keys(array_keys($columns), null)`) would need to become "all columns *except* this static omit-set," and `createTestData()` would need to compute and thread that omit-set alongside (or instead of) the current `$defaults` array — a structural change to two methods instead of a body-only change to one.
  - **Same introspection dependency, no complexity savings:** the omit-set still has to be derived from the schema (which `NOT NULL` columns have a DB default and are never set by the factory) — i.e. it still requires calling `Schema::getColumns()` or equivalent. C2 doesn't eliminate the framework-API dependency Option A already uses; it just uses the result differently (build an omit-list) for no reduction in moving parts.
  - **Future drift risk, and a real regression Option A doesn't have:** `array_replace($nullFill, $defaults, $attributes)`'s merge order means Option A's `$defaults` are *always* overridable by an explicit `$attributes` value — if a future factory state (e.g. a hypothetical `->highRisk()` state on `TaskFactory`) starts explicitly setting `risk_level` for some rows, Option A picks that up for free. C2's static omission would silently *drop* that value for every row (the key is never present in the INSERT at all, by design), including rows that intended to override it — a correctness regression that would need its own detection/exception logic to avoid, reintroducing C1's per-row-heterogeneity problem.
  - **Weaker generic fixture behavior:** Option A's default-merge model generalizes correctly to "factory sets some rows differently, defaults fill the rest"; C2's omission model only generalizes correctly to "no row ever sets these columns," which is an assumption about current usage, not a structural guarantee.
  - **Verdict:** C2 is *inferior*, not impossible — rejected on implementation-surface, drift-risk, and generic-fixture-behavior grounds, not on infeasibility.

**C3 — Group-by-key-set batching** (partition rows into sub-batches by their actual attribute shape before each `insertChunked()` call, so each sub-batch is internally uniform without needing a full-schema or fixed-omission shape): technically possible but the most invasive of the three — it changes `insertChunked()`'s contract from "one batch per table" to "N batches per table, grouped dynamically," adds a grouping/partitioning step for every fixture generation run, and reduces batch sizes below the current `array_chunk(..., 500)` sizing (more, smaller batches), which cuts against the "efficient bulk fixture generation" criterion for the 5,000-task case. **Rejected** as disproportionate complexity for a test helper whose sole purpose is throwaway fixture rows.

**Overall Option C verdict: rejected**, but specifically because C2/C3 are demonstrably inferior on implementation surface, drift risk, and generality — not because omission is inherently incompatible with batch insert (C2 shows it isn't, given today's factory behavior). Option A remains preferred because it achieves the same portability fix with a smaller, body-only diff and no new structural assumptions about factory behavior.

### Option D — Explicit deterministic fixture values (drop introspection, hardcode the small set of load-bearing defaults)

Since the inventory above found the load-bearing set is genuinely small (5 task columns, 7 project columns — 12 total), hardcode those exact values (`'risk_level' => 'low', 'complexity' => 'moderate', 'effort_points' => 1, 'time_spent' => 0, 'is_billable' => true`, etc.) directly in `modelToInsertRow()` or the factory calls, and delete `tableInsertDefaults()` entirely.

- **Schema drift risk:** real. If a future migration changes `risk_level`'s default from `'low'` to something else, or adds a new `NOT NULL`-with-default column to `projects`/`tasks`, this hardcoded list silently goes stale and the test starts failing on the *next* schema change instead of adapting automatically — exactly the kind of coupling `tableInsertDefaults()` was written to avoid (per Gate 1 Section B's "no driver branching anywhere, no SQLite-only doc comment" finding: this helper was written to track the schema, not fight it).
- **Duplication:** the seven-and-five-value lists duplicate information the schema/migrations already declare as the source of truth.
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

1. **No raw, hand-written SQLite-specific schema-introspection SQL (`PRAGMA table_info` or equivalent) remains in `PerformanceMonitoringTest.php` or any other GAP-043-owned helper code.** This is explicitly scoped to code this test file authors directly — it does **not** mean "Laravel/the framework itself may never use PRAGMA." Option A's `Schema::getColumns()` legitimately dispatches to `pragma_table_xinfo`-based introspection internally on SQLite connections (verified in the LOCAL probe above and in `SQLiteProcessor`); that framework-internal driver dispatch is exactly the point of choosing a portable API and is explicitly allowed. (v1 of this design incorrectly implied the opposite — corrected per Owner review.)
2. A genuine LIVE MySQL run of `PerformanceMonitoringTest` progresses past the former PRAGMA failure point for all 6 tests that failed on it directly at Gate 1 (`test_page_performance_budgets`, `test_database_query_performance`, `test_memory_usage_performance`, `test_concurrent_request_performance`, `test_large_dataset_performance`, `test_cache_performance`) — i.e. none of the 6 fail with `SQLSTATE[42000]` referencing `PRAGMA` any longer.
3. The 7th caller (`test_api_performance_budgets`) is evaluated separately: if GAP-044 is still unresolved at the time GAP-043's fix lands, this test remaining red on its (GAP-044-owned) SAVEPOINT symptom does **not** block GAP-043's own GREEN claim — it is explicitly out of GAP-043's attribution. If GAP-044 has been fixed by then and this test still fails on a PRAGMA-shaped error, that *would* count against GAP-043.
4. Any newly exposed downstream failure (e.g. an assertion that turns out to implicitly depend on a specific default value once real MySQL defaults are read instead of SQLite's) is classified and reported, not silently patched around, per the same "classify, don't absorb" discipline Gate 1 used for the dormant PRAGMA findings.
5. **SQLite compatibility is verified to remain valid via an explicit, focused command (corrected from v1)** — `phpunit.xml` excludes the `performance` group by default (`<exclude><group>performance</group>...</exclude>`, `phpunit.xml:19-23`), confirmed locally: running `./vendor/bin/phpunit tests/Performance/PerformanceMonitoringTest.php` with no group override reports **"No tests executed!"** — v1's claim that "existing SQLite-backed CI jobs" naturally re-run these 7 tests was incorrect; no such job currently exists (this is exactly the kind of truthful-test-selection question GAP-041 exists to police, and this design does not claim otherwise). The correct focused SQLite verification command for Gate 3, using the same `--group` override mechanism the `performance-tests` CI job's `php artisan test "<file>"` invocation relies on:
   ```
   DB_CONNECTION=sqlite ./vendor/bin/phpunit tests/Performance/PerformanceMonitoringTest.php --group=performance
   ```
   (or the `php artisan test tests/Performance/PerformanceMonitoringTest.php --group=performance` equivalent). **Baseline result at this exact Gate-2 head (`a1cde2aa`), run LOCAL against genuine migrated SQLite, unmodified `tableInsertDefaults()` still using `PRAGMA table_info` today:** `OK, but there were issues! Tests: 10, Assertions: 45` — all 10 methods pass today on SQLite via this exact command. This is the SQLite regression baseline Gate 3's post-fix run must continue to match — this design does not weaken or remove SQLite support, only adds correct MySQL support alongside it. SQLite evidence for Gate 3 may be exact-head **LOCAL** (per Owner instruction — no truthful LIVE SQLite lane for this exact population currently exists, and this design does not add or modify workflows to manufacture one); genuine MySQL LIVE evidence remains mandatory and non-waivable.
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
