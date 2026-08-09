---
work_id: GAP-010b
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-010b/02-design.md
---

# GAP-010b Legacy CSV Export Safety Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:test-driven-development while implementing each task and superpowers:verification-before-completion before every completion claim.

**Goal:** Make the two legacy CSV exports formula-safe, standards-parsable, bounded-memory, row-count accurate, and atomically published while preserving their synchronous API contract; preserve JSON behavior, leave Task Excel unrepaired, and reuse the bounded Project tabular source for the existing Project Excel CSV-style delegation.

**Architecture:** Validate `format` before query execution, retain one common caller-filter builder per resource, and branch into writer-specific execution. CSV receives an unexecuted builder and streams bounded `chunkById()` rows into a disk-backed temporary stream using explicit `fputcsv()` parameters; Task CSV loads only `project` per chunk, while Project CSV and the existing Project Excel CSV-style delegation share database `withCount()` aggregates, bounded processing, and never hydrate `tasks`. Task JSON and Project JSON keep their current collection/relation paths; Task Excel keeps its current incomplete path/writer and is not repaired. A small set of private controller helpers owns typed row construction, textual formula neutralization, tags serialization, and temporary publication; no new production service or dependency is introduced.

**Tech Stack:** PHP 8.2, Laravel/Eloquent, PHPUnit/Laravel feature tests, `Storage::fake()`, native `tmpfile()`/`fputcsv()`/`fgetcsv()`.

## Authority and Scope

- Gate 1 and Gate 2 are approved. Implementation remains unauthorized until a later explicit Owner decision.
- Future runtime scope is limited to `app/Http/Controllers/Api/ExportController.php` and a focused regression test at `tests/Feature/Api/LegacyCsvExportSafetyTest.php`.
- `use Illuminate\Http\Request;` is explicitly owned by GAP-010b.
- Task CSV-only removal of `assignments` must not alter Task JSON/Excel relation shape. Project JSON keeps its loaded `tasks` relation. Project Excel keeps its externally observable filename/download/fidelity behavior but reuses the bounded Project tabular source because its current writer delegates to the CSV-style generator.
- Tenant predicates, tenant-safe relations/aggregates, reference allowsets, and safe writer projections belong to GAP-034, not this plan.
- No route, middleware, RBAC, model, migration, package, Excel library, JSON writer, background job, timeout policy, or adjacent raw-error fix is in scope.
- No implementation branch may be merged/released or proposed for Gate 3 before GAP-034 is stacked and combined verification is green.

## File Map

- Modify `app/Http/Controllers/Api/ExportController.php`: Request import, early format dispatch, CSV builder execution, typed rows, native CSV encoding, bounded chunking, written-row count, and atomic publication.
- Create `tests/Feature/Api/LegacyCsvExportSafetyTest.php`: characterization, formula/type/tag/parser matrices, bounded-query/memory seams, count/atomicity, non-CSV compatibility, and release-composition guard.

No other future implementation file is expected. Discovery of a required third file is a scope stop for Owner review.

## Owner Header Resolution

Owner resolved the physical-header ambiguity during joint plan review. Compatibility requires exact header labels, exact order, exact parsed cells, valid CSV semantics, LF, no BOM, and the explicit native invocation `fputcsv($stream, $header, ',', '"', '', "\n")`. It does not require the legacy quote-every-field header bytes. Record the old bytes only as historical characterization; do not assert raw equality, manually concatenate/force quotes, special-case the header writer, or stop again for this resolved issue.

## GAP-010b / GAP-034 Composition Contract

```text
current approved design base
→ GAP-010b implementation
→ GAP-034 implementation
→ combined verification
→ Owner Gate 3
```

The corrected GAP-010b plan head is `P10`. Create `impl/GAP-010b-legacy-csv-export-safety` from exact `P10`, with its Draft implementation PR based on `plan/GAP-010b-legacy-csv-export-safety`. Its completed head is `H10`; stop there for Owner review and do not begin GAP-034.

Only after Owner accepts exact `H10`, create `integration/GAP-010b-gap034-export` from `H10` and normal non-force merge the exact corrected GAP-034 plan head `P34`. This preserves both work-item histories. Expected conflict count is zero because `P34` is documentation history; any conflict is a hard stop and must not be resolved silently. Validate the resulting `IBASE`.

Only after later Owner authorization, create `impl/GAP-034-export-tenant-isolation` from exact `IBASE`, with its implementation PR based on `integration/GAP-010b-gap034-export`. No concurrent branches may independently implement shared `ExportController.php` hunks.

Shared controller ownership:

- GAP-010b owns the `Request` import, early format seam, CSV `chunkById()` execution, Task CSV-only removal of `assignments`, Project CSV/Project Excel removal of child hydration, bounded Project tabular aggregate mechanics, typed row mapping, formula/tag processing, explicit `fputcsv()`, temp/publish cleanup, and actual written-row counts.
- GAP-034 owns trusted tenant resolution, Task structural eligibility, tenant predicates on base queries/relations, tenant predicates inside GAP-010b aggregate closures, reference validation, and safe logical writer projections.
- The integration seam is an unexecuted, already caller-filtered query builder plus a row-source/writer boundary. GAP-034 narrows that builder and supplies validated scalar rows; it must not replace GAP-010b's streaming/publishing mechanics.
- Project CSV and Project Excel's future combined tabular shape is: tenant-secure Project base → caller narrowing → tenant-constrained `withCount()` → bounded/chunked tabular processing → safe tabular projection → existing CSV-style writer semantics. Neither calls `with('tasks')`; Project Excel receives no library/fidelity/filename/MIME redesign.
- Task CSV's future combined shape is: tenant-secure eligible Task base → caller/filter narrowing → only CSV-required, tenant-constrained `project` relation → `chunkById()` → safe Task projection → native CSV writer. It never loads `assignments`.

---

## Review History

- Owner joint plan review: APPROVED WITH NON-DISCRETIONARY ALIGNMENT CORRECTIONS.
- Alignment recorded: native parsed-header contract resolved without an Owner stop; Project Excel reuses the bounded tabular source; Task CSV-only `assignments` removal; H10 → P34 merge → IBASE lineage.

---

### Task 1: Characterize the Existing Contract and Pin the Resolved Header Semantics

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

- [ ] **Step 3: Characterize physical-header change without making it a blocker**

Record that the old quote-every-field bytes differ from native output, then assert only the approved contract: parsing the header written through the pinned call yields the literal approved header array in exact label/order, with LF and no BOM. Expected RED reason against current production: the current writer does not use the pinned native call.

- [ ] **Step 4: Commit baseline tests**

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
- Produces: an unexecuted builder for CSV/Project Excel tabular execution or the format-compatible Task JSON/Excel and Project JSON paths.

- [ ] **Step 1: Add failing query-shape tests before the import repair**

Instrument database queries/model events at the controller-to-writer boundary and require:

- Task CSV uses `chunkById()`, loads `project` only within each chunk, and executes no `assignments` relation query.
- Project CSV uses `withCount()` aliases and executes no Task hydration/relation query, including one Project with a very large Task population.
- Task JSON and Task Excel still receive the current `project` + `assignments` collection shape; Task Excel remains incomplete and is not repaired.
- Project JSON still receives the current loaded `tasks` collection shape.
- Project Excel receives the same bounded aggregate-only Project tabular source as Project CSV, with no `tasks` hydration, while retaining its legacy externally observable filename/download/fidelity behavior.

Expected: RED because the current controller calls `get()` before format dispatch and both CSV paths hydrate unnecessary/unbounded relations.

- [ ] **Step 2: Add the authorized Request import and move dispatch before execution**

Add `use Illuminate\Http\Request;`. In each endpoint, validate/read `format`, construct one caller-filtered base builder, and dispatch:

```text
Task csv    → pass the unexecuted builder to the bounded Task CSV pipeline
Task excel  → preserve current relation shape and incomplete writer; do not repair
Task json   → preserve current relation shape and JSON writer
Project csv → pass the unexecuted builder to bounded Project tabular pipeline
Project excel → reuse bounded Project tabular source and existing CSV-style delegation
Project json → preserve current loaded-tasks relation and JSON writer
```

Do not add tenant logic. Keep IDs and Task filters semantically identical. Preserve current non-CSV methods without redesign.

- [ ] **Step 3: Implement Task CSV source semantics**

Clone/extend only the CSV builder with `with('project')` and `chunkById($chunkSize, ...)`; do not load `assignments`. Keep `$task->assignee_id` as the existing plain-column source. Define a fixed, test-visible chunk size constant/private method rather than an environment-dependent value.

- [ ] **Step 4: Implement Project CSV/Excel bounded tabular source semantics**

Clone/extend only the CSV builder with:

```php
->withCount([
    'tasks',
    'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
])
->chunkById(...)
```

For both Project CSV and Project Excel, never call `with('tasks')`, access `$project->tasks`, or instantiate Task models to compute counts. Reuse the bounded row-source mechanics while leaving existing Project Excel filename, download URL extension, XLSX fidelity, and MIME behavior unchanged. Leave closure seams where GAP-034 will add tenant predicates without changing the writer.

- [ ] **Step 5: Run focused dispatch/query tests**

```bash
php artisan test tests/Feature/Api/LegacyCsvExportSafetyTest.php --filter='request_import|format_dispatch|task_csv_query|project_csv_query|non_csv_compatibility'
```

Expected: PASS, including relation-not-loaded and bounded query-count assertions for Project CSV and Project Excel, plus unchanged Project JSON and Task JSON/Excel characterization.

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
- Produces: implementation evidence and exact `H10` for Owner review; no integration branch and no Gate 3 packet.

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

Record exact `H10` and keep the GAP-010b implementation PR Draft and explicitly blocked from Gate 3/merge/release. Stop for Owner review. Do not create the integration branch. After a later acceptance of `H10`, the separately authorized integration step creates `integration/GAP-010b-gap034-export`, normal-merges exact `P34`, validates `IBASE`, and only then may a later-authorized GAP-034 implementation branch start. A GAP-010b-only green suite is not release evidence.

- [ ] **Step 5: Report without advancing authority**

Report exact branch/head/base, changed files, diff stat, test counts, PHPStan, CI, Draft/Ready, mergeability, header-compatibility resolution, GAP-034 dependency, unresolved findings, and next action not performed. Do not create Gate 3, mark Ready, merge, release, or begin GAP-034 implementation without the applicable explicit Owner directive.

## Self-Review Result

- Scope coverage: Request import, format-aware dispatch, Task/Project bounded CSV sources, type-aware formula handling, tags serialization, explicit native CSV parameters, API compatibility, actual row count, atomic publication, and release dependency all map to Tasks 1–5.
- Format isolation: CSV receives builders/chunks; Project Excel shares the bounded Project tabular source without fidelity repair; JSON retains current relation behavior; Task Excel retains its incomplete path and `assignments` is removed only from Task CSV.
- Memory contract: Task CSV loads only bounded `project` relations; Project CSV uses aggregate scalars and never hydrates `tasks`; encoding uses one disk-backed temporary stream.
- Composition: GAP-010b produces `H10` for Owner review; only after acceptance does an integration branch normal-merge exact `P34` to form `IBASE`; a later-authorized GAP-034 branch starts from `IBASE` without concurrent controller writers.
- Header resolution: native `fputcsv()` writes the header; parsed labels/order, LF, and no BOM are exact; legacy raw quote-every-field bytes are not preserved and are not a blocker.
- Authority boundary: this plan does not authorize implementation, Gate 3, Ready state, merge, or release.
