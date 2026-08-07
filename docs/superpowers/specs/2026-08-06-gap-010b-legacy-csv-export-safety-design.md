---
work_id: GAP-010b
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-010b/02-design.md
---

# GAP-010b — Legacy CSV Export Safety: Gate 2 Design (re-presented after owner Gate 2 review round 1)

**Status:** Gate 2 design, awaiting owner decision. No implementation plan exists yet; no production code has been changed. This revision closes all findings from **Owner Gate 2 review round 1 (2026-08-07): CHANGES REQUESTED** — see §14 for the review record and how each finding was closed.

**Scope:** `app/Http/Controllers/Api/ExportController.php` (`exportTasks()`, `exportProjects()`, `generateCsv()`, `generateProjectsCsv()`) and the two routes `POST /tasks/bulk/export`, `POST /projects/bulk/export` registered at `routes/api.php:1006-1009`. **The missing `Illuminate\Http\Request` import repair is now IN SCOPE for GAP-010b's implementation (owner decision, §1.3/§14) — but this remains a design document; the import is not touched by this Gate 2 packet itself.**

**Out of scope:** Excel (`generateExcel`/`generateProjectsExcel`) and JSON (`generateJson`/`generateProjectsJson`) export paths (§1.8, adjacent findings only); RBAC redesign; tenant isolation (GAP-034, hard blocker — §10); any other export path (`Api\BulkOperationsController`, §1.8); GAP-010c or any other gap.

---

## 1. Verified baseline

All items below were verified by direct inspection and, where noted, live execution against a booted Laravel application on the current `main`. Re-verified again for this revision (2026-08-07) — `ExportController.php`, `routes/api.php`, `Task.php`, `AnalyticsController.php`, `Document.php` all read fresh, not stale from the prior round.

### 1.1 Route registration

```
POST api/tasks/bulk/export ..... Api\ExportController@exportTasks
POST api/projects/bulk/export .. Api\ExportController@exportProjects
```

```php
Route::middleware(['auth:sanctum', 'tenant.isolation', 'rbac'])->group(function () {
    Route::post('/tasks/bulk/export', [ExportController::class, 'exportTasks']);
    Route::post('/projects/bulk/export', [ExportController::class, 'exportProjects']);
});
```

(`routes/api.php:1006-1009`, outside the `v1` prefix group.)

### 1.2 Middleware

- `auth:sanctum` — standard token authentication.
- `tenant.isolation` (`TenantIsolationMiddleware`) — validates a supplied `X-Tenant-ID` header agrees with the user's own tenant. Does **not** apply any query-level scope to `Task`/`Project` — see GAP-034 (§10).
- `rbac` (`RoleBasedAccessControlMiddleware`), called **without** a permission argument — allows through any user holding one of 13 broad roles via `handleGeneralAccess()`. No export-specific permission. Out of scope for GAP-010b, unchanged, not redesigned here.

### 1.3 Runtime reachability — CONFIRMED BROKEN today — OWNER DECISION: import fix IS in GAP-010b's implementation scope

`ExportController.php`'s imports omit `Illuminate\Http\Request`. Both `exportTasks(Request $request)` and `exportProjects(Request $request)` therefore resolve `Request` as `App\Http\Controllers\Api\Request` — nonexistent. Live-reproduced: `app()->make("App\Http\Controllers\Api\Request")` throws `Illuminate\Contracts\Container\BindingResolutionException: Target class [App\Http\Controllers\Api\Request] does not exist.` — fires during Laravel's controller dependency resolution, before the method body (and its own `try/catch`) ever runs.

**Owner decision (Gate 2 review round 1, 2026-08-07): adding `use Illuminate\Http\Request;` IS part of GAP-010b's implementation scope.** This is a Gate 2 design-scope decision, not deferred to Gate 3. Rationale (owner's): it is the same code path GAP-010b is designed against, and no part of this design can be verified end-to-end without it.

**This decision does NOT authorize merging or deploying that fix ahead of GAP-034.** Adding the import to GAP-010b's implementation branch makes the two export routes *callable* — and callable, on this codebase, means reachable by any tenant, because GAP-034 (query-level tenant filtering) is not yet implemented. Therefore: **GAP-010b's implementation, once it includes this import fix, must not be merged/deployed in a state where the two export routes become reachable in production until GAP-034 has ALSO been implemented and verified** (§10, restated as a hard release-gating criterion in §11 item 12). The import fix is *implementation scope*, not *release scope* — those are two different gates for two different questions, and this design keeps them separate on purpose.

No existing automated test covers this — the only reference to `ExportController.php` in `tests/` is a static architecture-allowlist check (`tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php:26`).

### 1.4 Task CSV behavior — `generateCsv()` (`ExportController.php:137-177`) and the controller call site (`ExportController.php:16-79`)

Builds a full in-memory array (`$csvData`), then a full in-memory string (`$csvContent`), escaping only double quotes via manual `str_replace('"', '""', $field)`. No formula-character handling.

**Newly verified this round — relation usage audit:**

```php
$query = Task::with(['project', 'assignments']);
...
$task->project->name ?? 'N/A',
$task->assignee_id ? 'User ' . $task->assignee_id : 'Unassigned',
```

`assignments` is eager-loaded but **never referenced anywhere in the CSV row-building code.** The `Assignee` column is built entirely from `$task->assignee_id` — a plain column on the `tasks` table itself (`Task.php:72`, in `$fillable`, not derived from the `assignments` relation). **`assignments` is confirmed dead weight for this export and must be dropped from eager-loading in the implementation (§5.2).** `project` (a `BelongsTo`) IS used (`$task->project->name`) and must remain eager-loaded, but a `BelongsTo` is a single bounded row per task — not a growing collection — so eager-loading it per-chunk carries no unbounded-memory risk of its own.

**Newly verified this round — `tags` cast:**

```php
// src/CoreProject/Models/Task.php:83
'tags' => 'array',
```

`$task->tags` is Eloquent-cast to a PHP array, not a scalar. The current code (`ExportController.php:162`, `$task->tags` placed directly into the row array then into `str_replace('"', '""', $field)`) passes an array into a string-only function — PHP would coerce it to the literal string `"Array"` with an `E_WARNING`, silently corrupting every tags cell. **This is a real, previously-undocumented data-corruption defect in the current code, confirmed this round. §4 defines the serialization design that closes it.**

### 1.5 Project CSV behavior — `generateProjectsCsv()` (`ExportController.php:182-226`) and the controller call site (`ExportController.php:84-132`)

```php
$query = Project::with(['tasks']);
...
$totalTasks = $project->tasks->count();
$completedTasks = $project->tasks->where('status', 'completed')->count();
```

Structurally identical amplification pattern to §1.4, with an additional, more severe amplifier: `Project::with(['tasks'])->get()` loads **every task row of every matched project** into memory just to compute two integer counts per project. A single project with a very large number of tasks materializes that entire task collection in RAM regardless of how projects themselves are chunked — chunking the *project* query alone does not bound this. §5.1 replaces this with a database-side aggregate that never hydrates a single `Task` model for this purpose.

### 1.6 Current API response contract (MUST PRESERVE — see §7)

```json
{
  "success": true,
  "message": "Export completed successfully",
  "data": {
    "filename": "tasks_export_2026-08-06_12-00-00.csv",
    "download_url": "<Storage::url($filePath)>",
    "total_tasks": 42
  }
}
```

Synchronous: the HTTP request does not return until the file is fully generated and stored. `download_url` is available immediately in the same response. `total_tasks`/`total_projects` today comes from `$tasks->count()`/`$projects->count()` on the **full, already-materialized** collection (§8 replaces this with a count of rows actually written).

On failure: `{"success": false, "message": "Export failed: <raw exception>"}`, HTTP 500 — leaks the raw PHP exception message, an adjacent finding (§1.8), not in GAP-010b's stated scope unless the owner adds it.

### 1.7 Current memory behavior — full amplification chain, both paths

**Task path:** `Task::with(['project','assignments'])->get()` (full result set + two eager-loaded relations, one of them unused, §1.4) → `$csvData` (full array) → `$csvContent` (full string) → `Storage::put()` (single write).

**Project path:** `Project::with(['tasks'])->get()` (full result set + full child task collections per project, §1.5) → `$csvData` → `$csvContent` → `Storage::put()`.

**A design that only replaces the final write with streaming is not a complete fix for either path.** §5 addresses every stage of both paths.

### 1.8 Adjacent findings (recorded, NOT in GAP-010b's scope)

Confirmed by inspection, not included in acceptance criteria (§11) unless the owner explicitly asks:
- **Tenant scoping gap** — GAP-034, hard blocker (§10).
- **Generic RBAC** (§1.2) — out of scope per owner's Gate 1 constraint.
- **Raw exception messages returned to clients** (§1.6).
- **`generateExcel()` is dead code** — no return type, empty body, would raise a `TypeError` if reached (`Storage::url(null)`). Independent defect from §1.3.
- **`generateProjectsExcel()` silently returns CSV** with a swapped extension (`ExportController.php:240-243`).
- **A second, parallel export mechanism exists:** `Api\BulkOperationsController` (`GET api/auth/bulk/export/{tasks,projects,users}`) — unrelated, not investigated further.
- **`Api\ExportController` is on the frozen `Src\CoreProject\Models\Project`-reference allowlist** (`tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php:26`) — future implementation touching this controller's imports must account for that test.

---

## 2. Spreadsheet-formula neutralization — type-aware design (settled decision)

Per owner direction, formula-neutralization is **type-aware**: it applies to textual, user-controlled cell content, and must NOT be applied indiscriminately to every cell whose *stringified* form happens to start with a risk character. Numeric, null, and date/time cells retain their own semantics.

### 2.1 Two layers, kept separate (unchanged principle from round 1)

**Layer 1 — CSV structural escaping** (RFC 4180: quoting, comma/quote/newline handling — see §3). **Layer 2 — spreadsheet-formula neutralization** (this section). **These are never conflated: quoting a field (Layer 1) does nothing to stop a spreadsheet application from evaluating `=SUM(A1:A2)` inside that quoted field as a formula. This design does not claim quote-wrapping is any part of a formula-injection mitigation.**

### 2.2 Type-aware application rule (closes owner finding §6 of review round 1)

| Logical data type (source: the model attribute's actual type/cast, not its stringified CSV form) | Formula-neutralization applied? |
|---|---|
| Textual / user-controlled string (task/project name, description, tags-as-text — §4) | **Yes.** |
| Numeric model attribute genuinely typed/cast as numeric (`id`, `progress_percent` (`float` cast), `budget_total`/`budget_planned`/`budget_actual`, `estimated_hours`/`actual_hours` (`float` cast), `Total Tasks`/`Completed Tasks` aggregates) | **No.** A negative number like `progress_percent = -5` is written as the numeric value `-5`, not neutralized — it is not textual, user-controlled content; it is a database-typed numeric field. Numeric semantics (including negative sign) are preserved exactly as stored. |
| Null | **No neutralization; empty CSV cell**, per §2.4 below — never neutralize a null. |
| Date/time (`start_date`, `end_date`, `created_at`) | **No neutralization** of the serialized date string produced by the pinned date-format regression test (§8) — a correctly-formatted date does not start with a risk character in this system's date format, verified by the test, not assumed. |
| ID fields that are formatted as text but are not free-text (none currently identified in the exported columns beyond the numeric `id` above) | N/A — no such column exists in the current export column set. |

**The rule that decides whether to neutralize is based on the column's *known, fixed* data type in the implementation** (each exported column has a known type at code-write time — the implementation is not inferring type from the runtime string), not on inspecting the stringified value for a leading `-`. This is what makes the type-aware distinction possible and testable (§8).

### 2.3 Risk-character rule for textual columns (unchanged core rule, refined)

A **textual** cell value is formula-interpretable if, after any leading whitespace or control characters, its first significant character is `=`, `+`, `-`, `@`, tab (`\t`), carriage return (`\r`), or line feed (`\n`). **Leading whitespace/control characters before the marker do not exempt the value — a tab, CR, or LF preceding `=`/`+`/`-`/`@` is still treated as dangerous and must be neutralized** (this closes the "leading plain space" ambiguity left open in round 1 for the tab/CR/LF cases specifically; the plain-leading-space-only case, e.g. `"  hello"` with no marker after it, is not itself a risk and is NOT neutralized — only whitespace/control immediately preceding one of the four marker characters triggers neutralization).

### 2.4 Baseline mitigation (unchanged, reaffirmed baseline)

Prepend a single leading apostrophe (`'`) to any **textual** value matching §2.3's rule, leaving the rest of the value byte-for-byte unchanged — **the original marker character (`=`, `+`, `-`, `@`, or the leading whitespace/control) is never stripped, never truncated.** OWASP-recommended; same mechanism Excel/Sheets/LibreOffice use internally for "force text." Rejected alternatives (unchanged from round 1): stripping the leading character (destructive); wrapping the whole field in `="..."` (itself a formula construct). **Quote-wrapping (Layer 1, §3) is never used as a substitute security mitigation for this layer — restated explicitly per owner instruction.**

### 2.5 Data-integrity and compatibility consequences

| Property | Consequence |
|---|---|
| Neutralizes spreadsheet interpretation (textual cells) | Yes — verified against Excel/Sheets/LibreOffice's documented `'`-prefix behavior. |
| Preserves original logical data | Yes — original bytes/value untouched after the mark; numeric/date/null cells untouched entirely (§2.2). |
| What a human sees in a spreadsheet app | Textual risk cells render as text, **without** a visible leading apostrophe. |
| **Legitimate `+`-prefixed textual data (e.g. phone number `+84901234567`, if such a column is ever textual)** | Gets the `'` prefix. Opened in a spreadsheet app, **every digit including the `+` displays correctly and completely** — the mitigation does not truncate, does not drop the `+`. Required regression test: §8. |
| Numeric negative values (`progress_percent = -5`) | **Not neutralized** (§2.2) — retains numeric semantics, sorts/computes correctly in the spreadsheet, exactly as today. This is the specific correction from round 1's over-broad rule. |
| Formulas stored intentionally as business data | Still neutralized if in a textual column — this design does not attempt to distinguish intentional-formula-as-text from accidental/malicious; disclosed trade-off. |

### 2.6 Required regression coverage (see §12 for the full matrix)

`=1+1`, `+123456789`, `-10` (as **textual** content, e.g. inside a description field — must be neutralized), `@SUM(A1:A2)`, leading tab before `=`, leading CR/LF before `=`, ordinary text, embedded comma, embedded quote, multiline content, Vietnamese Unicode, empty/null fields, legitimate `+`-prefixed textual value (proving full content preserved), **and — separately — a genuinely numeric negative field value (e.g. `progress_percent = -5`) proving it is NOT neutralized and retains numeric semantics** (§2.2's correction, explicitly tested).

---

## 3. Tags serialization — settled decision (closes owner finding §4 of review round 1)

### 3.1 Canonical precedent found in this codebase

Two existing places already define a tags-to-text representation for CSV/plain-text display purposes:

```php
// app/Models/Document.php:349, src/DocumentManagement/Models/Document.php:269
public function getTagsAsString(): string
{
    return $this->tags ? implode(', ', $this->tags) : '';
}
```

**Decision: adopt this exact existing convention — `implode(', ', $tags)`, empty string for null/empty — for GAP-010b's `tags` column, per owner's explicit instruction to prioritize compatibility with an existing canonical representation over inventing a new one.**

### 3.2 Serialization contract

| Input | Output (logical string value, before Layer 1/2 processing) |
|---|---|
| `null` | `''` (empty string → empty CSV cell). |
| `[]` (empty array) | `''` — matches the `Document::getTagsAsString()` convention exactly (`$this->tags ? ... : ''`, and an empty array is falsy in PHP, so this already produces `''` under the existing precedent too). |
| `['urgent']` (one tag) | `urgent` |
| `['urgent', 'phase-2']` (multiple tags) | `urgent, phase-2` |
| Tags containing Unicode (`['gấp', 'ưu tiên']`) | `gấp, ưu tiên` — UTF-8 preserved. |
| A tag containing a comma (`['a,b', 'c']`) | `a,b, c` — **disclosed, accepted lossy edge case**: joining with `, ` cannot distinguish "one tag containing a literal comma" from "two tags" once serialized to text. This is the exact same trade-off already accepted by the existing `Document::getTagsAsString()` precedent — GAP-010b adopts it unchanged rather than inventing a different (e.g. JSON) representation that would diverge from the system's established convention. |
| A tag containing a double quote (`['5" pipe']`) | `5" pipe` — the quote is structural CSV content, handled entirely by Layer 1 (`fputcsv()`, §5.3), not by this serialization step. |
| A tag that is itself formula-like (`['=SUM(A1:A2)']`) | Serializes to `=SUM(A1:A2)` as the logical string, which is then evaluated as **textual** content by §2 and neutralized (`'=SUM(A1:A2)`) — the tags column is textual/user-controlled per §2.2's table, so Layer 2 always applies to the *serialized* string, never to the raw PHP array. |

### 3.3 Processing order (explicit, closes ambiguity)

1. Determine the logical string value: `implode(', ', $tags) ?: ''` (§3.2).
2. Apply Layer 2 formula-neutralization (§2) to that string, since the tags column is textual.
3. Apply Layer 1 CSV structural escaping (`fputcsv()`, §5.3) to the (possibly `'`-prefixed) string.

**The current code's implicit array-to-string coercion (§1.4, PHP casting an array to the literal string `"Array"`) is a confirmed defect this design closes — not a hypothetical.** `str_replace()`/`fputcsv()` must never receive an unserialized PHP array; §3.2's `implode()` step happens first, always.

### 3.4 Required regression coverage

`null`, `[]`, one tag, multiple tags, Unicode tags, a tag containing a comma (proving the disclosed lossy behavior matches the existing `Document` precedent, not a regression), a tag containing a quote (proving Layer 1 escaping still applies correctly to the serialized string), a formula-like tag string (proving Layer 2 still applies to the serialized value, per §3.2's last row).

---

## 4. Standards-compliant CSV generation (Layer 1) — settled decisions (closes owner finding §7 of review round 1)

**Decision: use PHP's `fputcsv()` against an open stream** instead of manual string concatenation, replacing the current hand-written `str_replace('"', '""', $field)`. `fputcsv()` guarantees RFC-4180-correct quoting/escaping without per-field hand-written logic, and composes directly with the streaming design (§5).

**Layer 2 (§2) is applied to each textual cell value BEFORE it is handed to `fputcsv()`.**

### 4.1 EOL — settled: keep `\n` (LF), do not switch to CRLF

**Decision: preserve the current `\n` row terminator.** Do not switch to `\r\n` "for stricter RFC-4180 compliance" — that would be a compatibility change with no requirement driving it, and the owner's explicit instruction is to minimize compatibility change for its own sake. `fputcsv()`'s `$eol` parameter (PHP 8.1+) will be set explicitly to `"\n"` in the implementation rather than left at the function's own default, so this is a pinned, verified choice, not an accident of the library's default.

### 4.2 BOM — settled: do NOT add a BOM

**Decision: no BOM is added.** The current contract has no BOM; adding one is an unnecessary compatibility change not required by GAP-010b's stated objective (formula-injection + memory safety), and risks breaking any existing programmatic consumer that parses these files expecting no BOM. If a future, separate work item identifies a real Excel-non-ASCII-rendering problem, that is its own decision with its own owner review — not folded into GAP-010b.

### 4.3 CSV quoting representation — compatibility contract is logical values, not byte-for-byte

**Decision:** the compatibility contract this design commits to preserving is **column order + logical cell values + valid CSV semantics** (§4.4) — **not** byte-for-byte equality with the current implementation's behavior of always wrapping every field in `"..."` regardless of whether quoting is structurally required. `fputcsv()` only quotes a field when RFC 4180 requires it (the field contains a comma, quote, or newline) — this is a valid, semantically-equivalent CSV representation, but is not byte-identical to the current "quote everything" output.

**Verification method: tests parse the generated output with a standards-compliant CSV parser and compare logical (parsed) values, never raw-string equality** — except for the specific header-row/column-order requirements in §4.4, which ARE checked for exact match since column order and header text are part of the explicit compatibility contract.

**If, before implementation begins, evidence emerges that a real existing client depends on byte-for-byte output or on every field always being quoted (as opposed to depending on correct CSV semantics), implementation must stop and this must be escalated back to the owner as a new compatibility finding — not silently resolved either way by the implementer.**

### 4.4 Column order and headers — settled: preserve exactly

The exact current header row and column order for both exports (`ExportController.php:142-146` for tasks: `ID, Name, Description, Status, Priority, Project, Assignee, Start Date, End Date, Progress %, Estimated Hours, Actual Hours, Tags, Created At`; `ExportController.php:187-191` for projects: `ID, Code, Name, Description, Status, Priority, Progress %, Budget Total, Budget Planned, Budget Actual, Start Date, End Date, Total Tasks, Completed Tasks, Created At`) **must be preserved exactly, in the same order, with the same header text.** This is a MUST-preserve compatibility item (§7), verified by exact string comparison of the header row specifically (the one place byte-for-byte comparison is the correct test).

### 4.5 Other CSV requirements (unchanged from round 1, now settled rather than flagged)

| Requirement | Decision |
|---|---|
| Commas / quotes / embedded newlines | `fputcsv()` — engine-guaranteed RFC-4180 correctness. |
| UTF-8 | Verified via the Vietnamese-Unicode test (§12), not assumed. |
| Dates, numeric precision | Passed through unchanged (§2.2); pinned by a regression test (§12) so a later Carbon/format change doesn't silently alter exports. |
| Tags | See §3 — fully settled, no longer an open question. |

---

## 5. End-to-end bounded-memory design — closes owner findings §2 and §3 of review round 1

**Decision: bound memory at every stage — database query → model hydration → transformation → CSV encoding → output — for BOTH the task and project export paths, using database-side aggregation to eliminate the project/task amplifier entirely, and chunked, minimal-relation queries for the task path.**

### 5.1 Project export — settled: database aggregate, NEVER hydrate the `tasks` relation

**Rejected (round 1's own draft):** `Project::with(['tasks'])` chunked by project, still eager-loading the full `tasks` relation per chunk. **Rejected because:** chunking the number of *projects* per chunk does not bound the number of *tasks* within a single project — one project with an unusually large number of tasks still hydrates that entire child collection into memory regardless of the project-chunk size, which breaks the bounded-memory invariant.

**Settled design: use a database-side `withCount()` aggregate, so each `Project` model carries only two additional scalar integers — never a task collection.** Proven precedent already exists in this codebase:

```php
// app/Http/Controllers/Api/AnalyticsController.php:152-154 (existing, unrelated feature, confirmed working pattern)
$projectTaskStats = Project::withCount(['tasks', 'tasks as completed_tasks_count' => function($query) {
    $query->where('status', 'completed');
}])->get();
```

GAP-010b's project export design adopts the same `withCount()` pattern, combined with `chunkById()` for the projects themselves:

```php
Project::withCount([
        'tasks',
        'tasks as completed_tasks_count' => fn ($query) => $query->where('status', 'completed'),
    ])
    ->chunkById($chunkSize, function ($projects) {
        foreach ($projects as $project) {
            // $project->tasks_count and $project->completed_tasks_count
            // are plain integer attributes here — the `tasks` relation
            // itself is never touched, never hydrated, never lazy-loaded.
        }
    });
```

This produces exactly the two values the CSV needs (`Total Tasks`, `Completed Tasks`) as single SQL-computed integers per project row, via `COUNT(...)` subqueries executed by the database — **zero `Task` models are ever instantiated for this purpose, regardless of how many tasks any individual project has.** No N+1 (the counts are computed in the same query as the project chunk, not per-project follow-up queries). No full child collection, at any project size.

### 5.2 Task export — settled: drop the unused `assignments` eager-load, keep `project`

Per §1.4's confirmed finding: `assignments` is eager-loaded but never used in the CSV output. Settled design:

```php
Task::with(['project'])  // assignments DROPPED — confirmed unused, §1.4
    ->chunkById($chunkSize, function ($tasks) {
        foreach ($tasks as $task) {
            // $task->project is available (BelongsTo, single bounded row per
            // task, eager-loaded within this chunk only — not the whole result set)
            // $task->assignee_id is a plain column, already present, no relation needed
        }
    });
```

Eliminating `assignments` removes an entire unnecessary eager-load (and its underlying query) from every chunk, with zero loss of CSV output (the column never read from it). `project` remains eager-loaded per-chunk (not for the whole result set upfront) — since it's a `BelongsTo`, this is one bounded row per task, not a growing collection, so it does not reintroduce the class of problem `tasks` did for the project path (§5.1).

### 5.3 Stage-by-stage summary (both paths)

| Stage | Task path | Project path |
|---|---|---|
| Database iteration | `chunkById()` | `chunkById()` |
| Relationship/aggregate loading | `with(['project'])` per chunk (assignments dropped, §5.2) | `withCount([...])` — no relation hydration at all (§5.1) |
| CSV row generation | Transformed and written immediately per row, no `$csvData` array | Same |
| CSV encoding + write | `fputcsv()` direct to an open stream (§4), no `$csvContent` string | Same |

**`chunkById()` chosen over `cursor()`** (unchanged rationale from round 1): `cursor()` holds a single DB connection/statement open for the entire export duration; `chunkById()`'s per-chunk queries are individually bounded. Trade-off (disclosed): requires a stable, indexed primary-key ordering and can skip/duplicate rows under concurrent mid-export writes — acceptable for an export snapshot, documented for the implementation plan.

### 5.4 Measurable acceptance criteria

- Peak memory during export must not scale linearly with row count, **and must not scale with the task-count of any individual project** — demonstrated by an actual test with one project containing a very large number of tasks (§12), not asserted from code review.
- At no point does either pipeline hold a complete in-memory array of all rows **and** a complete in-memory CSV string simultaneously.
- At no point does the project export path instantiate `Task` models for the purpose of computing `Total Tasks`/`Completed Tasks`.
- Concrete row-count/byte-size thresholds, maximum export size, timeout handling, and concurrent-export limits remain implementation-plan-level decisions (Gate 3+), not fixed by this Gate 2 design.

---

## 6. Response row count without full-collection materialization — closes owner finding §8 of review round 1

**Current defect:** `$tasks->count()` / `$projects->count()` (`ExportController.php:67`, `:121`) are called on the full, already-materialized collection — a pattern that no longer exists once §5's chunked design removes the full collection entirely.

**Settled design: maintain an `$exportedRowCount` integer, incremented exactly once per row actually written to the CSV output (i.e., once per successful `fputcsv()` call for a data row, not counting the header row), and return that value as `total_tasks`/`total_projects` in the response (§1.6).** This guarantees the reported total always equals the actual number of data rows present in the generated file — including in a future partial-failure scenario where fewer rows were written than initially matched by the query (though §7's atomic-file invariant means a partial file is never exposed as a successful response in the first place — the count and the invariant are complementary, not redundant).

### 6.1 Required regression coverage

A test asserting `response.data.total_tasks` (or `total_projects`) exactly equals the number of data rows present in the CSV file at `download_url`, parsed with a standards-compliant CSV parser (consistent with §4.3's logical-comparison method) and counted excluding the header row.

---

## 7. Atomic / partial-file behavior — closes owner finding §9 of review round 1

**Settled invariant (implementation detail — specific PHP calls are a Gate 3/implementation-plan decision, not fixed here):**

1. CSV generation writes to a temporary stream/file location, never directly to the final, client-visible storage path/filename.
2. The temporary artifact is published/moved/uploaded to the final storage path **only after** the complete CSV generation (all chunks, all rows) has finished successfully.
3. If query execution, transformation, writing, or the final publish/upload step fails at any point:
   - the partial temporary artifact is deleted;
   - the endpoint returns an explicit error response (§1.6's existing error envelope shape, preserved);
   - **no `success: true` / `download_url` response is ever returned for an incomplete file**;
   - no file exists at the final, client-visible filename in a partial/truncated state — a client can never observe a "download_url" that resolves to a truncated CSV.

### 7.1 Required regression coverage

A forced mid-export failure (e.g., an injected exception partway through chunk processing) must result in: no retrievable file at the would-be final path, an explicit error response, and no misleading success response — this exact scenario is required in the test matrix (§12).

---

## 8. Type-aware formula/numeric test proof — cross-reference

(See §2.6 and §12 for the full regression list; this section exists only to make explicit, per owner instruction, that the type-aware distinction in §2.2 is testable and tested, not merely asserted.)

---

## 9. API compatibility — MUST-PRESERVE contract (Gate 2 decisions, not deferred to Gate 3)

| Aspect | Current (verified §1.1/§1.6) | Design requirement |
|---|---|---|
| HTTP methods | `POST` | **MUST preserve.** |
| Endpoint paths | `/tasks/bulk/export`, `/projects/bulk/export` | **MUST preserve.** |
| Request fields | `task_ids`/`project_ids` (array), `format` (csv/excel/json), `filters` (tasks only) | **MUST preserve** for the `csv` path. |
| Authorization behavior | `auth:sanctum` + generic `rbac` (13 broad roles) | **MUST preserve exactly** — no broadening, no narrowing. |
| Tenant isolation | Currently unscoped at query level | Not GAP-010b's concern — GAP-034, hard blocker (§10). |
| JSON response shape | `{success, message, data: {filename, download_url, total_X}}` | **MUST preserve**; `total_X` now sourced per §6. |
| Filename pattern | `{resource}_export_{Y-m-d_H-i-s}.{format}` | **MUST preserve.** |
| Column order / headers | See §4.4 | **MUST preserve exactly** (the one byte-for-byte requirement). |
| CSV quoting representation | Current: quotes every field | **Logical-value compatibility, not byte-for-byte** — see §4.3. This is a settled Gate 2 decision, not an open question carried to Gate 3. |
| EOL | `\n` | **Preserved, settled (§4.1).** |
| BOM | None | **Preserved (none added), settled (§4.2).** |
| `download_url` availability | Synchronous, same response | **Kept synchronous by design decision (§9.1)** — this design does not introduce a background-job/polling architecture. |
| Error status conventions | 500, raw exception message | Preserve envelope shape; raw-message leak is an adjacent finding (§1.8), not required to fix. |

### 9.1 Synchronous completion — settled decision (not an open question carried to Gate 3)

Because chunked/streamed processing (§5) still runs to completion **within the same HTTP request** (chunk-and-stream-to-a-temp-file-then-publish, §7 — not move-to-a-background-job), the synchronous response contract (§1.6) is preserved by design. **This design commits to keeping the request synchronous** — introducing a job-ID/polling contract would itself be a breaking API change requiring its own separate owner decision, and nothing in GAP-010b's stated objective requires that trade-off. This is now a settled Gate 2 decision (round 1 had flagged this as the "single largest open risk"; it is closed here in favor of the compatibility-preserving choice).

The one disclosed caveat, unchanged from round 1: if a production export is large enough that chunked processing still exceeds PHP/webserver request timeout limits even with bounded memory, that is a timeout problem, not a memory problem, and would need its own separate owner-level decision (e.g., a maximum export size limit) at implementation-plan time — not solved by, or silently assumed away by, this Gate 2 design.

---

## 10. Tenant isolation — GAP-034 is a HARD BLOCKER, unchanged and reinforced

**GAP-034 (`docs/owner-decisions/GAP-034/01-request.md`, PR #246, Gate 1 awaiting owner) is a hard blocker for restoring or enabling `POST /tasks/bulk/export` and `POST /projects/bulk/export`.** This remains completely unchanged from round 1: this design does not include any tenant-filtering logic, does not add a tenant `WHERE` clause, and does not treat tenant isolation as an acceptance criterion of GAP-010b (§11 excludes it explicitly).

**Reinforced per owner's §1.3 decision this round:** because the `Request` import fix is now explicitly in GAP-010b's implementation scope (§1.3), the two export routes WILL become reachable the moment GAP-010b's implementation branch includes that fix — **this makes the GAP-034 dependency more, not less, load-bearing.** The implementation plan for GAP-010b (a future artifact, not this Gate 2 packet) **must include an explicit release-dependency check** — a concrete, verifiable gate (e.g., a deployment/release-process check, or a feature-flag/guard the implementation plan defines) preventing the fixed, reachable routes from being merged/deployed to production ahead of GAP-034's own verified completion. This design does not specify the exact mechanism of that guard (that is implementation-plan-level detail), only that one is required and non-negotiable.

- GAP-034 has its own Gate 1/2/3, its own acceptance criteria, its own tests, its own evidence, and its own owner decision — entirely separate from GAP-010b's.
- This design does not decide *how* GAP-034 is enforced (that is GAP-034's own Gate 2), only that GAP-010b's own release cannot restore route functionality ahead of it.
- No tenant-isolation test is included in GAP-010b's test matrix (§12.5) — only a dependency guard/assertion.

---

## 11. Acceptance criteria (Gate 2 minimum, for a future implementation plan to satisfy)

1. Formula-interpretable **textual** values (§2) are neutralized per the two-layer, type-aware design (§2/§4); numeric, null, and date/time values are never neutralized (§2.2).
2. Legitimate textual values — explicitly including `+`-prefixed data — remain fully visible and semantically recoverable when opened in a spreadsheet application (§2.5/§12.1).
3. Both task and project CSV paths are covered identically for the formula/CSV concerns; their bounded-memory designs differ appropriately per §5 (task: drop unused relation; project: database aggregate, never hydrate `tasks`).
4. Memory usage remains bounded across every stage of both pipelines (§5), including the specific case of a single project with a very large number of tasks (§5.4) — demonstrated by actual tests, not code review.
5. No complete dataset array and complete CSV string coexist in memory at any point, for either path.
6. The project export path never instantiates `Task` models to compute `Total Tasks`/`Completed Tasks` (§5.1/§5.4).
7. `tags` is serialized deterministically per §3 before any escaping step; the current implicit-array-to-string defect (§1.4) is closed.
8. Current *intended* RBAC/authorization behavior is unchanged; the tenant-scoping question is entirely GAP-034's (§10).
9. Existing API contract preserved per §9: synchronous request/response, same JSON shape, same endpoint/method/fields, exact column order/headers, `\n` EOL, no BOM — no background-job migration.
10. Standards-compliant CSV (§4): quotes, delimiters, multiline, UTF-8 all valid; compatibility verified by logical-value comparison (§4.3), not byte-for-byte, except the header row (§4.4).
11. `total_tasks`/`total_projects` in the response exactly equals the number of data rows actually written (§6).
12. Atomic file behavior: no partial/truncated file is ever exposed via a success response (§7).
13. **The two affected export routes are not restored to working order in production (the `Request` import fix, §1.3, is implemented but not released) unless GAP-034 has ALSO been implemented and verified** (§10) — a hard release-gating criterion, enforced by an explicit dependency guard the implementation plan must define.
14. No adjacent finding (§1.8) is silently folded into GAP-010b's implementation scope without a separate, explicit owner decision.
15. GAP-010b's implementation can be reverted without a database rollback, independently of GAP-034 (§13).
16. GAP-010c and all other gaps remain out of scope.

---

## 12. Testing strategy (test matrix defined at Gate 2, for a future implementation plan to execute)

All of the following are **new** tests — no existing automated coverage of `ExportController` exists today beyond the static architecture-allowlist check (§1.3).

### 12.1 Formula-injection / type-awareness / data-integrity matrix

| Input | Required assertion |
|---|---|
| `=1+1` (textual field) | Neutralized. |
| `+123456789` (textual field) | Neutralized. |
| `-10` (textual field, e.g. inside a description) | Neutralized. |
| `@SUM(A1:A2)` (textual field) | Neutralized. |
| Leading tab + `=1+1` | Neutralized. |
| Leading CR/LF + `=1+1` | Neutralized. |
| Ordinary text | Unchanged. |
| Embedded comma | Correctly quoted, one cell (parsed, not raw-string compared — §4.3). |
| Embedded quote | Correctly doubled-quote-escaped. |
| Multiline content | Correctly quoted, newline preserved inside quotes, still one cell. |
| Vietnamese Unicode | Correctly encoded, no mojibake. |
| Empty/null textual fields | Empty cell, no crash, no neutralization. |
| **Legitimate `+`-prefixed textual value (e.g. phone number `+84901234567`)** | Neutralized (prefixed with `'`) BUT every digit including `+` fully visible when opened in a spreadsheet application. |
| **Genuinely numeric negative field (`progress_percent = -5`)** | **NOT neutralized** — written as the numeric value `-5`, retains numeric semantics (§2.2's type-aware correction). |
| Null model attribute | Empty cell, never neutralized. |
| Date/time field | Serialized per the pinned date-format test, not neutralized. |

### 12.2 Tags serialization matrix (§3)

`null`, `[]`, one tag, multiple tags, Unicode tags, a tag containing a comma, a tag containing a quote, a formula-like tag string — all per §3.4.

### 12.3 Bounded-memory matrix

- Large task export — peak memory does not scale linearly with row count.
- Large project export — peak memory does not scale linearly with row count.
- **A single project with a very large number of tasks — export completes without hydrating the `tasks` relation, and `Total Tasks`/`Completed Tasks` are still correct** (§5.1/§5.4 — the specific scenario owner required).
- Task export confirmed to not eager-load `assignments` (§5.2) — e.g. asserting no query against the assignments table occurs during a task export.
- Explicit regression proving no complete in-memory array of all rows **and** complete in-memory CSV string coexist, for either path.

### 12.4 Compatibility and reliability

- Existing JSON response contract unchanged (§9).
- Exact existing column order and header text preserved (§4.4) — byte-for-byte on the header row specifically.
- `\n` EOL preserved (§4.1); no BOM present (§4.2).
- `total_tasks`/`total_projects` equals actual parsed CSV data-row count (§6.1).
- Stored file availability — `download_url` resolves to a retrievable, complete file.
- Existing authorization behavior unchanged (no broadening, no narrowing).
- Forced mid-export failure: no retrievable partial file, explicit error response, no misleading success (§7.1).

### 12.5 Runtime-defect and dependency regression

- A dedicated test asserting the current missing `Illuminate\Http\Request` import's effect on route execution, written **first** against the unfixed code to document today's broken state as a citable regression test, then expected to flip once the import is added as part of GAP-010b's implementation (§1.3).
- A dependency assertion/guard verifying GAP-010b's release process does not make the export routes reachable in production ahead of GAP-034's verified completion (§10) — a guard/assertion, not a tenant-isolation test.

### 12.6 Explicitly excluded from GAP-010b's test scope

Tenant-isolation tests (cross-tenant data leakage, tenant-scoped query correctness) belong entirely to GAP-034 (§10), not to GAP-010b's implementation.

---

## 13. Rollback boundary

- **No database migration.** All of §2–§9's corrections are query-level and file-generation-level changes, not schema changes.
- **GAP-010b rolls back independently** of GAP-034: reverting GAP-010b's implementation PR restores the prior (broken-but-inert, §1.3) code state with no data or structural cleanup required.
- **Rollback must never weaken or bypass GAP-034.** Reverting GAP-010b's implementation does not touch GAP-034's own code, and GAP-034's hard-blocker status (§10) is unaffected by any GAP-010b rollback in either direction.
- Partial-write cleanup (§7) is an operational cleanup concern, addressed by the atomic-file invariant itself, not a rollback mechanism.

---

## 14. Owner Gate 2 review record

**Owner Gate 2 review round 1 (2026-08-07): CHANGES REQUESTED.**

Required changes and how each was closed in this revision:

1. **Bounded-memory relation between project and tasks** — round 1's `Project::with(['tasks'])` chunked design did not actually bound memory for a project with many tasks. **Closed:** §5.1 — `withCount()` database aggregate, `Task` models never instantiated for this purpose, using the exact existing `AnalyticsController.php:152-154` precedent.
2. **`tags` array serialization** — round 1 deferred this to "verify actual cast type before implementation." **Closed:** §3 — cast confirmed as `array` (`Task.php:83`), existing `Document::getTagsAsString()` precedent adopted (`implode(', ', $tags)`), full serialization contract and processing order defined, current implicit-array-to-string defect documented as a real, closed bug.
3. **Missing `Request` import scope** — round 1 deferred this decision to Gate 3. **Closed:** §1.3 — owner decided at Gate 2: the import fix is in GAP-010b's implementation scope; merge/release remains blocked by GAP-034 regardless (§10), keeping implementation scope and release scope as two separate decisions.
4. **Type-aware formula handling** — round 1's rule neutralized any textual OR numeric value starting with `-`, `+`, etc., including genuinely numeric fields. **Closed:** §2.2 — neutralization now applies only to textual/user-controlled columns, keyed off each column's known, fixed data type, never off the stringified runtime value; numeric/null/date columns are explicitly exempted with a dedicated regression test (§12.1).
5. **CSV compatibility decisions** — round 1 left EOL and BOM as open implementation-time questions. **Closed:** §4.1 (EOL: keep `\n`, no switch to CRLF) and §4.2 (BOM: none added) are now settled Gate 2 decisions; §4.3 defines the compatibility contract as logical-value equivalence (parsed, not byte-for-byte) except for the header row (§4.4, byte-for-byte).
6. **Response count + partial-file invariants** — not addressed in round 1. **Closed:** §6 (`$exportedRowCount`, incremented per row actually written) and §7 (temp-file-then-publish, delete-on-failure, never expose a partial file as success).

Also closed per owner instruction: dropped the unused `assignments` eager-load from the task path (§5.2, §1.4 finding); removed "chờ owner chọn ở Gate 3" language throughout — every decision this section lists is now a settled Gate 2 design decision, not deferred.

**Re-presented at:** `gate_status: awaiting_owner`, `owner_decision.value: none`, `decision_requested: approve_or_changes_or_decline` (see `docs/owner-decisions/GAP-010b/02-design.md`) — not self-marked approved.

---

## 15. Independent review

An independent review of this Gate 2 design revision is required before it is presented as final to the owner. The reviewer must specifically evaluate:

- Whether §5.1's `withCount()` design genuinely eliminates the per-project task-hydration risk for a project with a very large number of tasks, and whether the cited `AnalyticsController.php` precedent is accurately represented.
- Whether §5.2's removal of the `assignments` eager-load is justified by an accurate reading of the CSV column-building code (i.e., confirm `assignee_id` truly is a plain column, not silently relation-derived elsewhere).
- Whether §2.2's type-aware formula-neutralization table is complete and correctly maps every currently-exported column to textual/numeric/null/date, with no column left ambiguous.
- Whether §3's tags serialization decision is a faithful, unmodified adoption of the existing `Document::getTagsAsString()` precedent, and whether the disclosed lossy-comma edge case is accurately characterized as "unchanged from existing precedent" rather than a new risk.
- Whether §4's CSV compatibility decisions (EOL, BOM, logical-value contract) are each justified by an actual requirement rather than arbitrary preference, and whether the header-row byte-for-byte exception is correctly scoped.
- Whether §1.3/§10's separation of "implementation scope" (Request import, now in-scope) from "release scope" (still blocked by GAP-034) is applied consistently everywhere in the document, with no place where the two are implicitly conflated.
- Whether §6/§7 (row count, atomic file) are complete and internally consistent with §5's chunked design.
- Whether this document accidentally pre-selects implementation code (as opposed to a design decision, which Gate 2 is permitted to make) anywhere that would exceed Gate 2's authorization.
- Whether a non-technical owner reading only `docs/owner-decisions/GAP-010b/02-design.md` has enough information to make the Gate 2 decision without needing this full document.

*(Independent review to be dispatched and its findings recorded in the final report before this design is considered ready for owner Gate 2 review.)*
