# GAP-043 Implementation Plan — PerformanceMonitoringTest MySQL Portability

**Work ID:** GAP-043
**Gate 2 status:** APPROVED (PR #280, design head `05b167d9`, canonical baseline `25cab7f4`)
**Approved design:** Option A — Laravel `Schema::getColumns($table)`
**Approved implementation surface:** `tests/Performance/PerformanceMonitoringTest.php`, body of `private function tableInsertDefaults(string $table): array` only

This plan implements the already-approved Option A. It does not reopen solution selection — that decision was made and Owner-approved at Gate 2.

## Problem recap

`tableInsertDefaults()` reconstructs DB-level column defaults using SQLite-only `PRAGMA table_info({$table})` syntax so that raw bulk-insert fixture rows include `NOT NULL` columns the factories don't set. On genuine MySQL, `PRAGMA` is not valid syntax and the call throws `SQLSTATE[42000]`.

## Current code (RED)

```php
private function tableInsertDefaults(string $table): array
{
    $defaults = [];

    foreach (DB::select("PRAGMA table_info({$table})") as $column) {
        $default = $column->dflt_value;

        if ($default === null || strtoupper((string) $default) === 'NULL') {
            continue;
        }

        $defaults[$column->name] = preg_replace("/^'(.*)'$/", '$1', (string) $default);
    }

    return $defaults;
}
```

## Approved replacement

Swap the PRAGMA loop for `Schema::getColumns($table)` (already imported as `Illuminate\Support\Facades\Schema` in this file). Per Laravel's `Builder::getColumns()` signature, each row is `array{name: string, ..., default: mixed, ...}` — access via array keys (`$column['name']`, `$column['default']`), not object properties. Keep every other semantic identical:

- skip `null` defaults
- skip case-insensitive textual `'NULL'`
- cast the raw default to `string` before the regex
- keep the existing single-quote-stripping regex (reconciles SQLite-quoted vs MySQL-unquoted literal defaults, per Gate-2 Correction A)
- return defaults keyed by column name

```php
private function tableInsertDefaults(string $table): array
{
    $defaults = [];

    foreach (Schema::getColumns($table) as $column) {
        $default = $column['default'];

        if ($default === null || strtoupper((string) $default) === 'NULL') {
            continue;
        }

        $defaults[$column['name']] = preg_replace("/^'(.*)'$/", '$1', (string) $default);
    }

    return $defaults;
}
```

## Explicitly out of scope (per Gate 2)

- No driver branching (`DB::getDriverName()` checks, etc.)
- No `information_schema` queries
- No change to `modelToInsertRow()`, `createTestData()`, `insertChunked()`
- No hardcoded defaults
- No factory or migration changes
- No threshold/selector/assertion changes
- GAP-042 / GAP-044 / GAP-045 / the 11 dormant PRAGMA files: not touched

## Steps

1. **RED (done, pre-edit):** fresh LOCAL MySQL 8.0 run of `tests/Performance/PerformanceMonitoringTest.php --group=performance` against a throwaway Docker `mysql:8.0` container, fully migrated. Confirmed 7 errors, all `SQLSTATE[42000]` on `PRAGMA table_info(projects)`: `test_api_performance_budgets` (GAP-044-owned after this fix), `test_page_performance_budgets`, `test_database_query_performance`, `test_memory_usage_performance`, `test_concurrent_request_performance`, `test_large_dataset_performance`, `test_cache_performance`. The other 3 methods (`test_n_plus_one_query_prevention`, `test_error_handling_performance`, `test_authentication_performance`) don't call `tableInsertDefaults()` and already pass.
2. **Edit:** replace the method body as shown above. No other line in the file changes.
3. **GREEN — SQLite:** run the full file under the default SQLite testing connection; expect all 10 methods to keep passing.
4. **GREEN — MySQL:** run the full file against the same genuine migrated MySQL 8.0 container; expect the 6 GAP-043-owned methods to pass, `test_api_performance_budgets` to progress past PRAGMA and fail (or pass) on the separate GAP-044 SAVEPOINT symptom only — classified, not fixed.
5. **Static scope check:** `git diff` touches only `tests/Performance/PerformanceMonitoringTest.php`; confirm no `PRAGMA table_info` string remains in that file.
6. **Push + Draft PR** against `main`, first PR body line `Work ID: GAP-043`. Remains Draft. No Gate 3 / merge / release / deploy.

## Acceptance contract (carried from Gate 2, unchanged)

GAP-043 GREEN means: (1) no raw SQLite schema-introspection SQL remains in the helper; (2) the 6 directly-PRAGMA-failing methods progress past that point on genuine MySQL; (3) no `SQLSTATE[42000]` PRAGMA failure remains in those 6; (4) SQLite compatibility is retained; (5) no threshold/selector/assertion weakening occurred. `test_api_performance_budgets` is evaluated and reported separately — its post-fix status is GAP-044's, not GAP-043's, to own.
