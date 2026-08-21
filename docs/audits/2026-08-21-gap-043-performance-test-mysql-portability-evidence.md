# GAP-043 — Gate 1 Evidence: `PerformanceMonitoringTest` MySQL schema-introspection portability defect

**Date:** 2026-08-21
**Gate:** 1 (investigation only — no fix, no Gate 2, no implementation)
**Canonical baseline:** `origin/main` `25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac`
**Investigation branch:** `docs/GAP-043-gate1-investigation` (worktree `.worktrees/GAP-043-gate1-investigation`), PR [#279](https://github.com/kha997/zenamanagephp/pull/279)
**Registered:** `OPERATIONAL_GAP_REGISTER.md` row `GAP-043` (added by PR #278, commit `25cab7f4`, discovered during GAP-041 LIVE execution)

## Owner Summary

`PerformanceMonitoringTest::tableInsertDefaults()` (`tests/Performance/PerformanceMonitoringTest.php:445-460`) calls `DB::select("PRAGMA table_info({$table})")` — SQLite-only schema-introspection syntax. On real MySQL this is a hard syntax error (`SQLSTATE[42000]`), not a soft degradation. The helper is reached by exactly **7 of the 10** test methods in this class (the 7 that call `createTestData()`); the other 3 build their fixtures directly via model factories and never reach it. Because this test class only ever ran on SQLite until GAP-041's Option D repair made the CI job's test-selection real (previously the job silently selected 0 tests and reported `success`), this MySQL-specific failure was never exposed until GitHub Actions run `32471481216` (2026-08-21), job `96739005481`.

This Gate 1 independently retrieved and revalidated that existing LIVE failure (no new LOCAL execution was performed — see Section D), confirmed the test-code blob executed in that run is byte-identical to current canonical `main`, confirmed the defect's exact call path and blast radius, and confirmed no other SQLite-specific syntax exists anywhere else in this test class. The defect is genuinely test-only (no application/production code, migration, model, or schema is implicated). One masking interaction with the separately-registered GAP-044 (SAVEPOINT) was found and is documented below without being absorbed into this gap.

## A. Exact call path

`tableInsertDefaults(string $table): array` (`tests/Performance/PerformanceMonitoringTest.php:445`) is a `private` method called from exactly one place: `createTestData()` (`tests/Performance/PerformanceMonitoringTest.php:362`), lines 366 and 368:

```php
$projectDefaults = $this->tableInsertDefaults('projects');
...
$taskDefaults = $this->tableInsertDefaults('tasks');
```

`createTestData()` is in turn called by exactly 7 of the class's 10 test methods:

| Test method | Calls `createTestData()`? | Reaches `tableInsertDefaults()`? |
|---|---|---|
| `test_api_performance_budgets` | Yes (line 49) | Yes |
| `test_page_performance_budgets` | Yes (line 73) | Yes |
| `test_database_query_performance` | Yes (line 98) | Yes |
| `test_memory_usage_performance` | Yes (line 128) | Yes |
| `test_concurrent_request_performance` | Yes (line 152) | Yes |
| `test_large_dataset_performance` | Yes (line 183) | Yes |
| `test_n_plus_one_query_prevention` | **No** — builds fixtures directly via `Project::factory()`/`Task::factory()` (lines 207-216) | No |
| `test_cache_performance` | Yes (line 261) | Yes |
| `test_error_handling_performance` | **No** — no fixture creation at all | No |
| `test_authentication_performance` | **No** — no fixture creation at all | No |

**Exact number of tests blocked: 7 of 10.**

### Do all observed PRAGMA failures share the same root cause?

Yes for the 6 that surfaced it directly. The LIVE run (`32471481216`, job `96739005481`, monitoring leg) reported `Tests: 7 failed, 3 passed`. Cross-referencing the JUnit XML embedded in the job log against the table above:

| Test | LIVE result | LIVE failure cause |
|---|---|---|
| `test_api_performance_budgets` | FAILED | `PDOException: SQLSTATE[42000] ... SAVEPOINT trans2 does not exist` (GAP-044, **not** GAP-043 — see masking note below) |
| `test_page_performance_budgets` | FAILED | `QueryException: SQLSTATE[42000] ... near 'PRAGMA table_info(projects)'` (GAP-043) |
| `test_database_query_performance` | FAILED | same PRAGMA syntax error (GAP-043) |
| `test_memory_usage_performance` | FAILED | same PRAGMA syntax error (GAP-043) |
| `test_concurrent_request_performance` | FAILED | same PRAGMA syntax error (GAP-043) |
| `test_large_dataset_performance` | FAILED | same PRAGMA syntax error (GAP-043) |
| `test_n_plus_one_query_prevention` | **PASSED** (5 assertions) | does not reach the helper |
| `test_cache_performance` | FAILED | same PRAGMA syntax error (GAP-043) |
| `test_error_handling_performance` | **PASSED** | does not reach the helper |
| `test_authentication_performance` | **PASSED** | does not reach the helper |

6 of 7 failures are attributable directly to GAP-043. The 7th (`test_api_performance_budgets`) failed one layer earlier, inside Laravel's transaction/`RefreshDatabase` machinery, before `createTestData()`'s body could execute the PRAGMA call — that is GAP-044's SAVEPOINT defect masking what would otherwise also be a GAP-043 PRAGMA failure. This is noted as a masking interaction, not absorbed: GAP-043's true blast radius among the 7 `createTestData()`-calling tests is "6 confirmed directly, 1 masked by a different registered defect and expected — not proven — to also hit the same PRAGMA line once GAP-044 is fixed." Gate 1 does not assert this as proven LIVE fact for the 7th test; it is flagged as an open unknown (see Section F).

The 3 passing tests (`test_n_plus_one_query_prevention`, `test_error_handling_performance`, `test_authentication_performance`) are structurally unaffected — they never call `createTestData()` — and their LIVE pass is consistent with static code reading, not just coincidence.

## B. Intent of `tableInsertDefaults()`

`createTestData()` performs bulk raw-row inserts (via `DB::table($table)->insert($chunk)` in `insertChunked()`) rather than going through Eloquent's `create()`/`save()`, for performance (it inserts up to 5,000 rows per test in `test_large_dataset_performance`). Raw inserts bypass Eloquent's attribute-default handling, so any DB-level column defaults that aren't set explicitly on the in-memory model (e.g. defaults defined at the schema/migration level rather than in the model or factory) would be written as `NULL` unless reconstructed first. `tableInsertDefaults()` exists to read each column's declared default value from the schema and pre-populate `$defaults`, which `modelToInsertRow()` then merges in (`array_replace($nullFill, $defaults, $attributes)`, line 438-441) before the raw insert, so that any column not explicitly set by the factory/test still gets its correct schema-level default instead of `NULL`.

Whether this is actually necessary for these tests to pass their asserted behavior is a **design question that Gate 2 must own**, not Gate 1. Candidate framings (none selected here):
- The tests only assert timing/query-count/memory budgets, never assert on the inserted rows' non-explicit column values — so it is plausible the defaults reconstruction is defensive/exists to avoid `NOT NULL` constraint violations on raw insert, not because any assertion actually depends on the reconstructed values.
- Alternatively, some column (e.g. a `status` or `type` enum-like column) may have a `NOT NULL` schema default that, if written as `NULL` by a raw insert, would throw a DB constraint error unrelated to PRAGMA — in which case the helper is load-bearing for insert success, not just data fidelity.

**Was it written for SQLite-only execution, or intended to be portable?** The surrounding code shows no driver branching anywhere in this file (no `DB::getDriverName()` check, no `if ($isSqlite)` guard) — contrast with `database/migrations/2025_09_20_145756_disable_foreign_keys_for_testing.php`, which explicitly branches `sqlite` → `PRAGMA foreign_keys=OFF` vs `mysql` → `SET FOREIGN_KEY_CHECKS=0`. `tableInsertDefaults()` has no MySQL branch at all — there is no dead/unreachable MySQL-equivalent code sitting unused nearby. Combined with the fact this file carries no comment or docblock restricting it to SQLite, and the CI job matrix (`automated-testing.yml`'s `performance-tests`) has always declared `DB_CONNECTION: mysql` as its intended, sole test backend (see `database/migrations/...` for the project's established sqlite/mysql dual-driver convention elsewhere), the evidence is consistent with this helper having been written and never exercised against its declared target (MySQL) — i.e., portability was very likely the intent (this is the only DB the job has ever declared), and the SQLite-only syntax is an oversight rather than a deliberate SQLite-only design, though Gate 1 cannot read the original author's intent with certainty and defers the final characterization to Gate 2/Owner.

## C. Blast radius (exhaustive repo search)

Searched patterns: `PRAGMA`, `tableInsertDefaults`, and equivalent SQLite-only schema introspection.

### `tableInsertDefaults` — single definition, single call site

```
tests/Performance/PerformanceMonitoringTest.php:366  $this->tableInsertDefaults('projects')
tests/Performance/PerformanceMonitoringTest.php:368  $this->tableInsertDefaults('tasks')
tests/Performance/PerformanceMonitoringTest.php:445  private function tableInsertDefaults(...)
```
No other file references this helper. It is not a shared trait method — it is private to this one class.

### `PRAGMA` — exact tracked-tree count

Command used: `git grep -n "PRAGMA" -- '*.php'` (git-tracked working tree at this branch's head, scoped to executable PHP source — `app/`, `database/migrations/`, `tests/`; this is not a whole-repo/all-file-type count, and it excludes anything untracked or outside `*.php`).

Result: **25 line-occurrences across 20 unique files.** These four categories are exhaustive and non-overlapping (every occurrence appears in exactly one category):

| Category | Files | Lines | Verified how |
|---|---|---|---|
| **GAP-043 defect itself** | 1 (`tests/Performance/PerformanceMonitoringTest.php:449`) | 1 | Unguarded — no driver check anywhere in the file (Section B) |
| **Driver-guarded migration/app introspection** | 8 (`app/Console/Commands/MaintenanceCommand.php`, `app/Http/Controllers/Admin/MaintenanceController.php`, `database/migrations/2025_09_20_071043_add_missing_performance_indexes.php`, `database/migrations/2025_09_20_132400_add_missing_fields_to_components_table.php`, `database/migrations/2025_09_20_145756_disable_foreign_keys_for_testing.php`, `database/migrations/2025_09_20_160000_fix_notifications_table_schema.php`, `database/migrations/2025_09_20_164912_add_missing_columns_to_task_assignments_table.php`, `database/migrations/2025_09_22_013614_add_missing_indexes_for_n1_optimization.php`) | 13 | Each `PRAGMA` call individually confirmed sitting inside an `if ($driver === 'sqlite')` (or equivalent `getDriverName()`) branch with an explicit MySQL-equivalent alternative (`SET FOREIGN_KEY_CHECKS`, or migration-only introspection gated the same way) — read directly, not inferred |
| **Unguarded `PRAGMA foreign_keys=OFF` in Feature/Integration tests** | 10 (`tests/Integration/FinalSystemTest.php`, `tests/Feature/DashboardAnalyticsSimpleTest.php`, `tests/Feature/DashboardAnalyticsTest.php`, `tests/Feature/DocumentVersioningNoFKTest.php`, `tests/Feature/NotificationSystemTest.php`, `tests/Feature/BulkOperationsTest.php`, `tests/Feature/UserManagementSimpleTest.php`, `tests/Feature/UserManagementAuthenticationTest.php`, `tests/Feature/Integration/EventWorkflowTest.php`, `tests/Feature/Integration/InterModuleCommunicationTest.php`) | 10 | No driver check present; flagged as a related latent risk below — **kept as its own category, distinct from the next row** |
| **Unguarded `PRAGMA defer_foreign_keys` in one Feature test** | 1 (`tests/Feature/Api/ExportTenantIsolationTest.php:382`) | 1 | A different PRAGMA statement (`defer_foreign_keys`, not `foreign_keys=OFF`) with a different purpose (deferring FK checks mid-test to simulate a stale reference, not disabling them for setup) — reported as its own row rather than folded into the `foreign_keys=OFF` count above, since conflating the two would misstate what each does |
| **Total** | **20** | **25** | — |

Only the first category (1 file, 1 line) is the GAP-043 defect. The second category (8 files, 13 lines) is confirmed safe by direct reading, not by pattern-matching alone. The third and fourth categories (11 files, 11 lines combined) are a related-but-out-of-scope finding, detailed next.

**Related latent-risk finding (explicitly NOT part of GAP-043's scope, reported per instruction E rather than silently fixed):** 10 Feature/Integration test files call `\DB::statement('PRAGMA foreign_keys=OFF;')` unguarded (no driver check), and a separate 1 file (`ExportTenantIsolationTest.php`) calls the distinct `PRAGMA defer_foreign_keys = ON` statement, also unguarded — 11 files total, kept as two distinct sub-findings rather than one, since they are different statements with different intents. Gate 1 confirmed via `.github/workflows/automated-testing.yml` that none of these 11 files are currently ever executed against MySQL: the only CI jobs that set `DB_CONNECTION: mysql` are `zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql`, `treasury-check-constraints-mysql`, and `performance-tests` (matrix: only `PerformanceMonitoringTest.php` and `DashboardPerformanceTest.php`) — none of which include these 11 files. The project's default `phpunit.xml` sets `DB_CONNECTION=sqlite`, which is what these 11 files run under in every other job. This is therefore a **dormant** portability risk, not a currently-failing one, and is out of scope for GAP-043 (which is specifically the LIVE-confirmed, currently-failing `PerformanceMonitoringTest.php` defect). It is recorded here so it is not silently absorbed or silently fixed, per instruction E. No new gap is registered for it in this document — that determination belongs to the Owner/register maintainer, not to this Gate 1 packet.

### Other SQLite-specific code in `PerformanceMonitoringTest.php` likely to fail immediately after PRAGMA is fixed

Read the full file (`tests/Performance/PerformanceMonitoringTest.php`, 478 lines) end to end looking for further MySQL incompatibilities downstream of `tableInsertDefaults()`, per instruction C's directive not to stop at the first PRAGMA line:

- `createTestData()` (362-428): `DB::transaction()`, `Project::factory()->make()`, `insertChunked()` → `DB::table($table)->insert($chunk)`. All portable Eloquent/query-builder calls, no raw SQL.
- `modelToInsertRow()` (430-443): pure PHP array manipulation, no DB calls.
- `insertChunked()` (462-467): `DB::table($table)->insert($chunk)` — portable.
- `newModelKey()` (469-476): `$model->newUniqueId()` or `Str::uuid()` — portable, no DB calls.
- `Schema::getColumnListing()` (line 365, 367): portable Laravel Schema facade method, works identically across drivers.

**Finding: no other MySQL-incompatible code was found in this file.** The single `PRAGMA table_info` call at line 449 is the only schema-introspection defect. This directly answers instruction C: the blast radius within the file itself is fully contained to one line/one helper, reached by 7 of 10 tests (6 confirmed LIVE, 1 masked by GAP-044).

## D. Evidence (epistemic labels preserved)

| # | Evidence | Type | Source |
|---|---|---|---|
| 1 | LIVE MySQL run failure, monitoring leg: `Tests: 7 failed, 3 passed (9 assertions)`, 6 of 7 failures show `SQLSTATE[42000] ... near 'PRAGMA table_info(projects)'`, 1 of 7 shows `SQLSTATE[42000] ... SAVEPOINT trans2 does not exist` | **LIVE** | GitHub Actions run `32471481216`, job `96739005481` (`Performance Tests (tests/Performance/PerformanceMonitoringTest.php)`), retrieved via `gh run view --job 96739005481 --log` |
| 2 | LIVE MySQL run, dashboard leg: 0 occurrences of `PRAGMA` in the log; 2 failures present are unrelated to GAP-043 (SAVEPOINT / latency-budget) | **LIVE** | GitHub Actions run `32471481216`, job `96739005491` (`Performance Tests (tests/Performance/DashboardPerformanceTest.php)`) |
| 3 | `tableInsertDefaults()` source (lines 445-460), call sites (366, 368), and full file read confirming no other MySQL-incompatible code | **STATIC** | `tests/Performance/PerformanceMonitoringTest.php` at current canonical `main` (`origin/main` `25cab7f4`) |
| 4 | Test-code blob byte-identity across the LIVE run's commit, canonical `main`, and this investigation branch | **STATIC** | `git rev-parse <ref>:tests/Performance/PerformanceMonitoringTest.php` — all three resolve to blob `af50d58f3aebba90879119547c582a16e6d55b76`: `bde3589c` (GAP-041 implementation SHA at time of the LIVE run), `origin/main` (`25cab7f4`), and `HEAD` of this investigation branch (`docs/GAP-043-gate1-investigation`, PR #279) |
| 5 | Tracked-tree `PRAGMA` inventory (25 line-occurrences / 20 unique `*.php` files, classified into 4 non-overlapping categories) and `tableInsertDefaults` inventory (1 definition, 2 call sites, 1 file) | **STATIC** | `git grep -n "PRAGMA" -- '*.php'` and `git grep -rn 'tableInsertDefaults' -- '*.php'` over the tracked working tree at this branch's head |
| 6 | CI job → DB driver mapping (`DB_CONNECTION: mysql` only in 5 named jobs; default `phpunit.xml` sets `sqlite`) | **STATIC** | `.github/workflows/automated-testing.yml`, `phpunit.xml` |
| 7 | Prior GAP-041 provenance record classifying GAP-043/044/045 and confirming no test/app code was touched in that session | **STATIC** (documentary, not independently re-run) | `docs/audits/2026-08-21-gap-041-implementation-blocked-technical-evidence.md`, committed at `1f96afca` on `feature/GAP-041-ci-test-selection-truthfulness` (not yet merged to `main`) |

**No LOCAL reproduction against genuine MySQL was performed in this Gate 1** — the LIVE GitHub Actions evidence (items 1-2) and STATIC source verification (items 3-6) were judged sufficient to establish the defect, call path, and blast radius without needing a redundant local MySQL run. If Gate 2 needs to iterate on a fix, local reproduction becomes appropriate at that stage.

## E. Separation from other gaps

- **GAP-041** (selector/truthfulness): not reopened. This packet only reads GAP-041's existing provenance record; no GAP-041 file, workflow, or test-selection mechanism was touched.
- **GAP-044** (SAVEPOINT): the `test_api_performance_budgets` masking interaction is documented in Section A but not investigated further — its root cause (fixture/transaction machinery in `FixtureFactory`/`TenantUserFactoryTrait`) is explicitly GAP-044's Gate 1 to perform, per the register's own text ("Gate 1 BẮT BUỘC phải đối chiếu lại với công việc transaction-isolation của GAP-040"). This packet stops at noting the interaction exists.
- **GAP-045** (latency budget): not touched — that symptom appears only in `DashboardPerformanceTest.php`, which this packet confirms (item 2 above) has zero PRAGMA occurrences and is therefore structurally unrelated to GAP-043.
- **GAP-042** (RBAC production-fidelity): unrelated domain, not touched, not referenced beyond the register's own cross-listing.
- The dormant unguarded-`PRAGMA` pattern in 11 Feature/Integration test files (10 `foreign_keys=OFF` + 1 `defer_foreign_keys`, Section C) is reported as a classified finding, not investigated further or absorbed into GAP-043's scope, per instruction E's "classify and stop."

## F. What remains unknown

- Whether `test_api_performance_budgets` would hit the GAP-043 PRAGMA error if GAP-044's SAVEPOINT defect were fixed first — plausible by code-path analysis (it also calls `createTestData()`) but **not proven LIVE**, since GAP-044 currently masks it.
- Whether `tableInsertDefaults()`'s default-reconstruction is strictly necessary for any test's pass/fail outcome (vs. purely defensive) — Section B lists two candidate framings; Gate 1 does not adjudicate which is correct, as doing so risks pre-selecting a fix design (e.g. "just delete the helper" vs. "port it to be driver-aware") that belongs to Gate 2.
- The original authorial intent behind writing `tableInsertDefaults()` with SQLite-only syntax and no driver branch (oversight vs. an assumption that this suite would only ever run on SQLite) — inferred from absence of any driver-guard pattern in this file, but not confirmable via git blame/PR-description archaeology within this Gate 1's scope.
- Whether the Owner wants the dormant unguarded-`PRAGMA` pattern in the 11 other Feature/Integration test files (Section C) registered as its own gap, monitored, or left as-is given it is not currently exercised against MySQL by any CI job.

## Scope confirmation

**The problem is genuinely test-only.** No application code, model, migration, schema, RBAC/tenant semantics, or Finance/Treasury/OPPM domain logic is implicated — the defect is fully contained to one private helper method and its two call sites inside `tests/Performance/PerformanceMonitoringTest.php`. No Design Dependency Preflight is triggered by this Gate 1's findings, since nothing here indicates a production-domain change would be required to address it. Should Gate 2 later determine the fix requires touching schema/migrations (e.g. to introduce a portable way of reading column defaults across drivers) rather than only test code, that determination and any resulting preflight belong to Gate 2, not this packet.

**No additional downstream portability risks were found within `PerformanceMonitoringTest.php` itself** beyond the one `PRAGMA table_info` call (Section C). One related-but-out-of-scope dormant risk was found and classified (11 other test files with unguarded PRAGMA statements — 10 `foreign_keys=OFF` plus 1 `defer_foreign_keys`, currently never exercised against MySQL) and is reported without being absorbed or fixed.
