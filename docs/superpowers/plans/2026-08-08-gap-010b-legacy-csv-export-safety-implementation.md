---
work_id: GAP-010b
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-010b/02-design.md
---

# GAP-010b Legacy CSV Export Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development while implementing each task and superpowers:verification-before-completion before every completion claim.

**Goal:** Make the two legacy CSV exports formula-safe, standards-parsable, bounded-memory, row-count accurate, and atomically published while preserving their synchronous API contract and leaving JSON/Excel behavior unchanged.

**Architecture:** Validate `format` before query execution, retain one common caller-filter builder per resource, and branch into writer-specific execution. CSV receives an unexecuted builder and streams bounded `chunkById()` rows into a disk-backed temporary stream using explicit `fputcsv()` parameters; Task CSV loads only `project` per chunk, while Project CSV uses database `withCount()` aggregates and never hydrates `tasks`. JSON/Excel keep their existing collection/eager-relation flow. A small set of private controller helpers owns typed row construction, textual formula neutralization, tags serialization, and temporary publication; no new production service or dependency is introduced.

**Tech Stack:** PHP 8.2, Laravel/Eloquent, PHPUnit/Laravel feature tests, `Storage::fake()`, native `tmpfile()`/`fputcsv()`/`fgetcsv()`.

## Authority and Scope

- Gate 1 and Gate 2 are approved. Implementation remains unauthorized until a later explicit Owner decision.
- Future runtime scope is limited to `app/Http/Controllers/Api/ExportController.php` and a focused regression test at `tests/Feature/Api/LegacyCsvExportSafetyTest.php`.
- `use Illuminate\Http\Request;` is explicitly owned by GAP-010b.
- CSV-only changes must not alter JSON/Excel query shape, payload, filename behavior, success/failure behavior, or writer implementation.
- Tenant predicates, tenant-safe relations/aggregates, reference allowsets, and safe writer projections belong to GAP-034, not this plan.
- No route, middleware, RBAC, model, migration, package, Excel library, JSON writer, background job, timeout policy, or adjacent raw-error fix is in scope.
- No implementation branch may be merged/released or proposed for Gate 3 before GAP-034 is stacked and combined verification is green.

## File Map

- Modify `app/Http/Controllers/Api/ExportController.php`: Request import, early format dispatch, CSV builder execution, typed rows, native CSV encoding, bounded chunking, written-row count, and atomic publication.
- Create `tests/Feature/Api/LegacyCsvExportSafetyTest.php`: characterization, formula/type/tag/parser matrices, bounded-query/memory seams, count/atomicity, non-CSV compatibility, and release-composition guard.

No other future implementation file is expected. Discovery of a required third file is a scope stop for Owner review.

## Pre-Implementation Compatibility Stop

The approved design currently states both:

1. invoke `fputcsv($stream, $row, ',', '"', '', "\n")` rather than manual CSV concatenation; and
2. preserve the current header row byte-for-byte.

The current writer quotes every header field, while native `fputcsv()` does not quote simple fields that require no enclosure. These requirements cannot both be satisfied by passing the header array directly to the pinned native call. Task 1 must capture the exact baseline and demonstrate the mismatch with a red characterization. Before production editing, return this evidence to Owner for one explicit resolution: parsed header value/order compatibility through `fputcsv()`, or a separately authorized exact-header emission exception. Do not silently relax “byte-for-byte,” manually concatenate the header without approval, or replace `fputcsv()`.

## GAP-010b / GAP-034 Composition Contract

```text
current approved design base
→ GAP-010b implementation
→ GAP-034 implementation
→ combined verification
→ Owner Gate 3
```

The future GAP-010b implementation branch starts at approved Gate 2 head `9fb8c7b20595d4984a376a00d998dde58136b1d8`. GAP-034 must then stack on the exact Owner-reviewed GAP-010b implementation head. No concurrent branches may independently implement shared `ExportController.php` hunks.

Shared controller ownership:

- GAP-010b owns the `Request` import, early CSV/non-CSV format seam, CSV `chunkById()` execution, Task CSV removal of `assignments`, Project CSV removal of child hydration, Project CSV aggregate mechanics, typed row mapping, formula/tag processing, explicit `fputcsv()`, temp/publish cleanup, and actual written-row counts.
- GAP-034 owns trusted tenant resolution, Task structural eligibility, tenant predicates on base queries/relations, tenant predicates inside GAP-010b aggregate closures, reference validation, and safe logical writer projections.
- The integration seam is an unexecuted, already caller-filtered query builder plus a row-source/writer boundary. GAP-034 narrows that builder and supplies validated scalar rows; it must not replace GAP-010b's streaming/publishing mechanics.
- Project CSV's future combined shape is: tenant-secure Project base → caller narrowing → tenant-constrained `withCount()` → `chunkById()` → safe tabular projection → native CSV writer. It never calls `with('tasks')`.
- Task CSV's future combined shape is: tenant-secure eligible Task base → caller/filter narrowing → only CSV-required, tenant-constrained `project` relation → `chunkById()` → safe Task projection → native CSV writer. It never loads `assignments`.

---

### Task 1: Characterize the Existing Contract and Resolve the Header Conflict

**Files:**
- Create: `tests/Feature/Api/LegacyCsvExportSafetyTest.php`
- Read only: `app/Http/Controllers/Api/ExportController.php`

**Interfaces:**
- Consumes: current controller output and approved Gate 2 compatibility contract.
- Produces: executable baseline evidence; no production change.

- [ ] **Step 1: Build the focused endpoint harness**

Use `RefreshDatabase`, the repository RBAC fixtures, and `Storage::fake(config('filesystems.default'))`. Add helpers that authenticate through the real middleware, post CSV requests, resolve `data.filename`, read `exports/{filename}`, and parse rows with explicit native parameters:

```php
private function parseCsv(string $payload): array
{
    $stream = fopen('php://temp', 'w+');
    fwrite($stream, $payload);
    rewind($stream);

    $rows = [];
    while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
        $rows[] = $row;
    }

    fclose($stream);
    return $rows;
}
```

- [ ] **Step 2: Write red runtime reachability and exact contract tests**

Cover the missing `Illuminate\Http\Request` import, HTTP method/path, request fields, response envelope, filename pattern, synchronous file availability, headers/column order, LF/no-BOM, date/numeric/null logical values, and exact Task/Project ULID strings. Record the current quoted header bytes separately from parsed header values.

- [ ] **Step 3: Demonstrate the native-header incompatibility**

In a unit-level characterization, write each approved header array through the exact pinned `fputcsv()` call and assert parsed values/order match while raw bytes differ from the current quote-every-field header. Expected: the compatibility assertion requiring both raw equality and native generation is RED.

- [ ] **Step 4: STOP for the pre-implementation Owner decision**

Report both byte strings and the passing parsed-value comparison. Do not edit production code until Owner resolves the conflict identified above. After explicit resolution, update only the affected test expectation within the already authorized future test file and continue.

- [ ] **Step 5: Commit baseline tests after resolution**

```bash
git add tests/Feature/Api/LegacyCsvExportSafetyTest.php
git commit -m "test(exports): characterize legacy CSV contracts"
```

### Task 2: Establish Format-Aware Dispatch and CSV Query Sources

**Files:**
- Modify: `app/Http/Controllers/Api/ExportController.php:1-132`
- Test: `tests/Feature/Api/LegacyCsvExportSafetyTest.php`

**Interfaces:**
- Consumes: validated request fields and common caller filters.
- Produces: an unexecuted builder for CSV or the unchanged eager-loaded collection for JSON/Excel.

- [ ] **Step 1: Add failing query-shape tests before the import repair**

Instrument database queries/model events at the controller-to-writer boundary and require:

- Task CSV uses `chunkById()`, loads `project` only within each chunk, and executes no `assignments` relation query.
- Project CSV uses `withCount()` aliases and executes no Task hydration/relation query, including one Project with a very large Task population.
- Task JSON/Excel still receive the current `project` + `assignments` collection shape.
- Project JSON/Excel still receive the current `tasks` collection shape; GAP-010b does not impose its CSV aggregate-only builder on them.

Expected: RED because the current controller calls `get()` before format dispatch and both CSV paths hydrate unnecessary/unbounded relations.

- [ ] **Step 2: Add the authorized Request import and move dispatch before execution**

Add `use Illuminate\Http\Request;`. In each endpoint, validate/read `format`, construct one caller-filtered base builder, and dispatch:

```text
csv   → pass the unexecuted builder to the CSV pipeline
excel → apply the existing Excel relation shape, call get(), use existing writer
json  → apply the existing JSON relation shape, call get(), use existing writer
```

Do not add tenant logic. Keep IDs and Task filters semantically identical. Preserve current non-CSV methods without redesign.

- [ ] **Step 3: Implement Task CSV source semantics**

Clone/extend only the CSV builder with `with('project')` and `chunkById($chunkSize, ...)`; do not load `assignments`. Keep `$task->assignee_id` as the existing plain-column source. Define a fixed, test-visible chunk size constant/private method rather than an environment-dependent value.

- [ ] **Step 4: Implement Project CSV source semantics**

Clone/extend only the CSV builder with:

```php
->withCount([
    'tasks',
    'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
])
->chunkById(...)
```

Never call `with('tasks')`, access `$project->tasks`, or instantiate Task models to compute counts. Leave closure seams where GAP-034 will add tenant predicates without changing the writer.

- [ ] **Step 5: Run focused dispatch/query tests**

```bash
php artisan test tests/Feature/Api/LegacyCsvExportSafetyTest.php --filter='request_import|format_dispatch|task_csv_query|project_csv_query|non_csv_compatibility'
```

Expected: PASS, including relation-not-loaded and bounded query-count assertions.

- [ ] **Step 6: Commit the execution seam**

```bash
git add app/Http/Controllers/Api/ExportController.php tests/Feature/Api/LegacyCsvExportSafetyTest.php
git commit -m "refactor(exports): isolate bounded CSV query paths"
```

### Task 3: Implement Typed Rows, Formula Safety, and Native CSV Encoding

**Files:**
- Modify: `app/Http/Controllers/Api/ExportController.php:137-226`
- Test: `tests/Feature/Api/LegacyCsvExportSafetyTest.php`

**Interfaces:**
- Consumes: one bounded Task/Project chunk.
- Produces: ordered logical scalar rows written immediately with native CSV semantics.

- [ ] **Step 1: Write the full red formula/type/parser matrix**

Run the approved matrix against both Task and Project textual columns: `=`, `+`, textual `-`, `@`, marker after leading spaces/tabs/CR/LF, ordinary text, comma, quote, multiline, Vietnamese Unicode, empty/null, literal backslash, and backslash-before-quote. Separately prove numeric negative values, dates, nulls, and ULIDs are not formula-neutralized or numerically coerced.

Add Task tags cases: null, empty, one, multiple, Unicode, comma, quote, and formula-like tag. Assert the canonical logical join is `implode(', ', $tags)` and never `Array`.

- [ ] **Step 2: Implement explicit typed row maps**

Create private `taskCsvRow()` and `projectCsvRow()` helpers returning arrays in exactly the approved column order. Each field is classified at code-write time as textual, numeric, null, date/time, or ULID. Do not infer type from its string form and do not apply textual neutralization to an entire row generically.

- [ ] **Step 3: Implement textual neutralization only**

Create a private helper that prefixes one apostrophe when a textual value begins, after leading whitespace/control characters, with `=`, `+`, `-`, or `@`; leading tab/CR/LF are also risk triggers. Preserve all original bytes after the prefix. Empty/null handling remains separate.

- [ ] **Step 4: Serialize tags before neutralization**

Normalize null/empty tags to `''`, otherwise `implode(', ', $tags)`. Then apply textual neutralization to the joined string. Never pass an array to `fputcsv()`.

- [ ] **Step 5: Write every row through the pinned native call**

Use the Owner-resolved Task 1 header behavior, then write each data row exactly as:

```php
$written = fputcsv($stream, $row, ',', '"', '', "\n");
if ($written === false) {
    throw new RuntimeException('Unable to write CSV row.');
}
```

No BOM, no manual field quoting, no full `$csvData`, and no full `$csvContent`.

- [ ] **Step 6: Run formula/tags/parser tests**

```bash
php artisan test tests/Feature/Api/LegacyCsvExportSafetyTest.php --filter='formula|numeric|ulid|tags|parser|unicode|eol|bom|header'
```

Expected: PASS for both resource row maps and every approved type/round-trip case.

- [ ] **Step 7: Commit row safety**

```bash
git add app/Http/Controllers/Api/ExportController.php tests/Feature/Api/LegacyCsvExportSafetyTest.php
git commit -m "fix(exports): encode formula-safe CSV rows"
```

### Task 4: Implement Atomic Publication and Actual Written-Row Counts

**Files:**
- Modify: `app/Http/Controllers/Api/ExportController.php`
- Test: `tests/Feature/Api/LegacyCsvExportSafetyTest.php`

**Interfaces:**
- Consumes: a completed disk-backed temporary CSV stream and successful row-write count.
- Produces: final storage path only after complete publication, plus exact data-row count.

- [ ] **Step 1: Write red success/count/failure tests**

Assert `total_tasks`/`total_projects` equals parsed data rows excluding the header. Inject a deterministic exception after at least one data row and assert HTTP 500 with the existing error envelope, no final file, no retrievable temp artifact, and no success/download URL. Add publish-failure coverage with the same cleanup invariant.

- [ ] **Step 2: Generate into a disk-backed temporary stream**

Use `tmpfile()` (not `php://memory` and not a growing string), validate the returned resource, stream header/data rows, and increment `$exportedRowCount` only after each successful data-row `fputcsv()` call. Rewind only after all chunks finish.

- [ ] **Step 3: Publish only after complete generation**

Upload the rewound completed stream to a unique storage-side `.part` path, then move/publish it to `exports/{filename}`. The final path must not exist before generation completes. In `catch`/`finally`, close the OS temp resource and delete any storage temp path; if final publication fails, ensure no partial final artifact remains before returning the existing failure envelope.

- [ ] **Step 4: Return the writer result, not a collection count**

Have each CSV pipeline return a small value object-equivalent array such as `['path' => $filePath, 'count' => $exportedRowCount]`. Use those values for `Storage::url()` and `total_tasks`/`total_projects`. Non-CSV branches continue using their current collection counts.

- [ ] **Step 5: Prove bounded behavior without brittle absolute memory limits**

Use large fixtures plus instrumentation that observes chunk sizes, absence of full-dataset arrays/strings, `relationLoaded('tasks') === false` for Project CSV, and no Task model retrieval for Project aggregates. Compare small/large runs with a reasonable bounded tolerance; do not assert an environment-specific exact byte ceiling.

- [ ] **Step 6: Run reliability and memory tests**

```bash
php artisan test tests/Feature/Api/LegacyCsvExportSafetyTest.php --filter='written_row_count|atomic|partial|publish_failure|bounded|large_project'
```

Expected: PASS with no leftover `.part` artifact.

- [ ] **Step 7: Commit publication reliability**

```bash
git add app/Http/Controllers/Api/ExportController.php tests/Feature/Api/LegacyCsvExportSafetyTest.php
git commit -m "fix(exports): publish bounded CSV exports atomically"
```

### Task 5: Combined Regression and Owner Handoff

**Files:**
- Modify only if an approved GAP-010b case fails: `app/Http/Controllers/Api/ExportController.php`, `tests/Feature/Api/LegacyCsvExportSafetyTest.php`

**Interfaces:**
- Consumes: completed GAP-010b implementation on the exact approved base.
- Produces: implementation evidence and the exact head onto which GAP-034 must stack; no Gate 3 packet.

- [ ] **Step 1: Run the complete focused suite**

```bash
php artisan test tests/Feature/Api/LegacyCsvExportSafetyTest.php
```

Expected: all GAP-010b tests PASS, with no failures/errors/skips.

- [ ] **Step 2: Run route, architecture, and middleware regressions**

```bash
php artisan test tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php tests/Feature/RouteHygieneTest.php tests/Feature/RouteMiddleware/V1LegacyRouteHardeningContractTest.php
```

Expected: PASS; routes/middleware/RBAC remain unchanged.

- [ ] **Step 3: Run static and diff verification**

```bash
./vendor/bin/phpstan analyse app/Http/Controllers/Api/ExportController.php tests/Feature/Api/LegacyCsvExportSafetyTest.php
git diff --check
git diff --name-only origin/plan/GAP-010b-legacy-csv-export-safety...HEAD
```

Expected: PHPStan exits 0, diff check is silent, and only the two planned implementation files appear.

- [ ] **Step 4: Enforce the release dependency**

Record the exact reviewed GAP-010b implementation head as the sole permitted base for GAP-034. Keep the GAP-010b implementation PR Draft and explicitly blocked from Gate 3/merge/release. Stack GAP-034 on that exact head, run both focused suites and shared-controller review, and only then present the combined head for a future Owner Gate 3 decision. A GAP-010b-only green suite is not release evidence.

- [ ] **Step 5: Report without advancing authority**

Report exact branch/head/base, changed files, diff stat, test counts, PHPStan, CI, Draft/Ready, mergeability, header-compatibility resolution, GAP-034 dependency, unresolved findings, and next action not performed. Do not create Gate 3, mark Ready, merge, release, or begin GAP-034 implementation without the applicable explicit Owner directive.

## Self-Review Result

- Scope coverage: Request import, format-aware dispatch, Task/Project bounded CSV sources, type-aware formula handling, tags serialization, explicit native CSV parameters, API compatibility, actual row count, atomic publication, and release dependency all map to Tasks 1–5.
- Format isolation: CSV receives builders/chunks; JSON/Excel retain current eager-loaded collections and existing writers.
- Memory contract: Task CSV loads only bounded `project` relations; Project CSV uses aggregate scalars and never hydrates `tasks`; encoding uses one disk-backed temporary stream.
- Composition: GAP-010b owns mechanics first; GAP-034 stacks on its exact approved implementation head and adds tenant security without concurrent controller writers.
- Known compatibility finding: byte-exact current header quoting conflicts with direct native `fputcsv()` header emission and requires Owner resolution before production implementation.
- Authority boundary: this plan does not authorize implementation, Gate 3, Ready state, merge, or release.
