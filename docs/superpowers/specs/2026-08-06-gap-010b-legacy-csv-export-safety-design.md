---
work_id: GAP-010b
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-010b/02-design.md
---

# GAP-010b — Legacy CSV Export Safety: Gate 2 Design

**Status:** Gate 2 design, awaiting owner decision. No implementation plan exists yet; no production code has been changed.

**Scope:** `app/Http/Controllers/Api/ExportController.php` (`exportTasks()`, `exportProjects()`, `generateCsv()`, `generateProjectsCsv()`) and the two routes `POST /tasks/bulk/export`, `POST /projects/bulk/export` registered at `routes/api.php:1006-1009`.

**Out of scope:** Excel (`generateExcel`/`generateProjectsExcel`) and JSON (`generateJson`/`generateProjectsJson`) export paths (§1.8, adjacent findings only); RBAC redesign; tenant isolation (GAP-034, hard blocker — §6); the missing `Request` import repair itself (§1.3 — documented, not fixed here); any other export path (`Api\BulkOperationsController`, §1.8); GAP-010c or any other gap.

---

## 1. Verified baseline

All items below were verified by direct inspection and, where noted, live execution against a booted Laravel application on the current `main` (post OWN-2026-005 merge, `ExportController.php` and `routes/api.php` re-checked byte-identical to the original Gate 2 investigation — not stale).

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

(`routes/api.php:1006-1009`, outside the `v1` prefix group — these two routes live at `api/tasks/bulk/export`, not `api/v1/tasks/bulk/export`.)

### 1.2 Middleware

- `auth:sanctum` — standard token authentication.
- `tenant.isolation` (`TenantIsolationMiddleware`) — validates a supplied `X-Tenant-ID` header agrees with the user's own tenant. Does **not** apply any query-level scope to `Task`/`Project` — see GAP-034.
- `rbac` (`RoleBasedAccessControlMiddleware`), called **without** a permission argument — allows through any user holding one of 13 broad roles via `handleGeneralAccess()`. No export-specific permission. Out of scope for GAP-010b (§6.2 of the original investigation; unchanged, not redesigned here).

### 1.3 Runtime reachability — CONFIRMED BROKEN today (documented, not fixed at Gate 2)

`ExportController.php`'s imports omit `Illuminate\Http\Request`. Both `exportTasks(Request $request)` and `exportProjects(Request $request)` therefore resolve `Request` as `App\Http\Controllers\Api\Request` — nonexistent. Live-reproduced: `app()->make("App\Http\Controllers\Api\Request")` throws `Illuminate\Contracts\Container\BindingResolutionException: Target class [App\Http\Controllers\Api\Request] does not exist.` — this fires during Laravel's controller dependency resolution, before the method body (and its own `try/catch`) ever runs.

**This defect is NOT being fixed as part of this Gate 2 design or any implementation derived from it without explicit owner authorization at Gate 3.** It is documented here because: (a) it belongs to the same export code path GAP-010b is designed against, and (b) no part of this design can be verified end-to-end by actually calling the routes until it is fixed by *some* authorized change — but which work item performs that fix, and when, is an owner decision at Gate 3, not a decision this Gate 2 packet makes or acts on.

No existing automated test covers this — the only reference to `ExportController.php` in `tests/` is a static architecture-allowlist check (`tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php:26`).

### 1.4 Task CSV behavior — `generateCsv()` (`ExportController.php:137-177`)

Builds a full in-memory array (`$csvData`), then a full in-memory string (`$csvContent`), escaping only double quotes via manual `str_replace('"', '""', $field)` wrapped in `"..."`. No formula-character handling. No CSV-standard newline/BOM handling beyond simple quoting.

### 1.5 Project CSV behavior — `generateProjectsCsv()` (`ExportController.php:182-226`)

Structurally identical to §1.4, different column set, plus a `tasks->count()`/`tasks->where('status','completed')->count()` aggregate computed after `Project::with(['tasks'])->get()` has already loaded every task of every matched project into memory.

### 1.6 Current API response contract (MUST PRESERVE — see §5)

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

Synchronous: the HTTP request does not return until the file is fully generated and stored. `download_url` is available immediately in the same response. No polling/job-status contract exists today.

On failure: `{"success": false, "message": "Export failed: <raw exception>"}`, HTTP 500 — leaks the raw PHP exception message, an adjacent finding (§1.8), not in GAP-010b's stated scope unless the owner adds it.

### 1.7 Current memory behavior — full amplification chain (three stages, all must be bounded — see §4)

1. `Task::with(['project', 'assignments'])->get()` / `Project::with(['tasks'])->get()` — loads the entire matched result set (plus eager-loaded relations) into memory. No chunking, no cursor.
2. `$csvData` — a second full in-memory array built from stage 1.
3. `$csvContent` — a third full in-memory string built from stage 2.
4. `Storage::put($filePath, $csvContent)` — single, non-streaming write.

**A design that only replaces stage 4 with streaming is not a complete fix.** Stages 1–3 already hold the full dataset before stage 4 begins. §4 addresses all four stages.

### 1.8 Adjacent findings (recorded, NOT in GAP-010b's scope)

Confirmed by inspection, not included in acceptance criteria (§8) unless the owner explicitly asks:
- **Tenant scoping gap** — split into GAP-034, hard blocker (§6).
- **Generic RBAC** (§1.2) — out of scope per owner's Gate 1 constraint.
- **Raw exception messages returned to clients** (§1.6).
- **`generateExcel()` is dead code** — no return type, empty body, would raise a `TypeError` if reached (`Storage::url(null)`). Independent second defect from §1.3, would surface only if `format=excel` were requested and the import were fixed.
- **`generateProjectsExcel()` silently returns CSV** with a swapped extension (`ExportController.php:240-243`) — functions today for projects, but misleading.
- **A second, parallel export mechanism exists:** `Api\BulkOperationsController` registers `GET api/auth/bulk/export/{tasks,projects,users}` — unrelated, not investigated further.
- **`Api\ExportController` is on the frozen `Src\CoreProject\Models\Project`-reference allowlist** (`tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php:26`) — any future implementation touching this controller's imports must account for that test.

---

## 2. Spreadsheet-formula injection — design decision (not merely a comparison)

Per owner direction, this design commits to a two-layer separation and a specific baseline mitigation, rather than presenting formula-neutralization as an open comparison:

**Layer 1 — CSV structural escaping**, governed entirely by RFC 4180 rules (quoting fields containing commas/quotes/newlines, doubling embedded quotes). This layer's only job is producing syntactically valid CSV; it has no awareness of spreadsheet semantics.

**Layer 2 — spreadsheet-formula neutralization**, applied to each cell's *value* before Layer 1 ever sees it. A value is formula-interpretable if, after any leading whitespace/control characters, its first significant character is `=`, `+`, `-`, `@`, tab (`\t`), carriage return (`\r`), or line feed (`\n`) — the OWASP CSV/Formula Injection set plus the two Excel leading-whitespace variants. **These two layers must never be conflated: wrapping a field in `"..."` (Layer 1) does nothing to stop Excel from evaluating `=SUM(A1:A2)` inside that quoted field as a formula — quoting is a CSV-syntax concern, not a spreadsheet-semantics concern. This design explicitly does NOT claim that quote-wrapping is a complete or partial mitigation for formula injection.**

### 2.1 Baseline mitigation (settled design decision)

Prepend a single leading apostrophe (`'`) to any value matching the Layer-2 rule above, leaving the rest of the value byte-for-byte unchanged. This is the OWASP-recommended mitigation and is the same mechanism Excel/Sheets/LibreOffice use internally for "force text." Rejected alternatives (both discussed and rejected in the prior design round, unchanged): stripping the leading character (destructive — changes visible meaning, e.g. `-5 days behind` → `5 days behind`); wrapping the whole field in `="..."` (itself a formula construct, changes visible file content, still flagged by some scanners).

### 2.2 Data-integrity and compatibility consequences

| Property | Consequence |
|---|---|
| Neutralizes spreadsheet interpretation | Yes — verified against Excel/Sheets/LibreOffice's documented `'`-prefix behavior. |
| Preserves original logical data | Yes, original bytes untouched after the mark. |
| Raw CSV file bytes | Contain a leading `'` for any value that started with a risk character — standard, expected. |
| What a human sees in a spreadsheet app | Renders as text, **without** a visible leading apostrophe — matches the original value visually. |
| Re-import fidelity | Spreadsheet-aware re-import (Excel/Sheets): original value restored. Naive/raw CSV parser: the `'` is preserved literally — an explicit compatibility trade-off, disclosed, not hidden. |
| **Legitimate `+`-prefixed data (e.g. phone number `+84901234567`)** | Gets the `'` prefix (starts with a risk character per §2 rule). Opened in a spreadsheet app, the phone number displays correctly and completely — **the mitigation does not truncate, does not drop the `+`, does not lose any visible digit.** This is the specific property the owner required proof of; see §7 test matrix item "legitimate `+`-prefixed data" for the required regression test proving no content is lost. |
| Apostrophe-led values (user literally typed `'twas...`) | Not a risk character — untouched, no double-prefixing. |
| Formulas stored intentionally as business data | Still neutralized — this design does not attempt to distinguish intentional-formula-as-text from accidental/malicious; disclosed as a trade-off, not hidden. |
| Empty/null | No prefix; empty CSV field. |
| Numeric (e.g. negative `progress_percent`) | A value like `-5` starts with `-` and is neutralized (becomes text-typed in the opened spreadsheet) — a real, disclosed behavior change from today, requiring explicit owner acceptance at Gate 3, not assumed here. |
| Date values | No expected impact (Laravel's default date rendering doesn't start with a risk character) — must still be covered by the test matrix (§7), not assumed. |

### 2.3 Required regression coverage (see §7 for the full matrix)

`=1+1`, `+123456789`, `-10`, `@SUM(A1:A2)`, leading tab, leading CR/LF before a marker, leading plain space before `=` (explicitly flagged as an open question — §2 does not currently commit to treating plain leading space alone as risk-triggering; the test matrix must pin this down against real Excel/Sheets behavior before implementation), ordinary text, embedded comma, embedded quote, multiline content, Vietnamese Unicode, empty/null fields, and — specifically required by the owner — a **legitimate `+`-prefixed value proving the mitigation does not destroy visible content**.

---

## 3. Standards-compliant CSV generation (Layer 1) — design decision

**Decision: use a standards-aware CSV writer (PHP's `fputcsv()` against an open stream) instead of manual string concatenation**, replacing the current hand-written `str_replace('"', '""', $field)` + manual `"..."` wrapping. Rationale: `fputcsv()` guarantees RFC-4180-correct quoting/escaping for every column without per-field hand-written logic (which risks a future column addition introducing an escaping bug), and composes directly with the streaming design in §4 (a stream handle is exactly what `fputcsv()` writes to, one row at a time — no intermediate full-string buffer).

**Layer 2 (§2) is applied to each cell value BEFORE it is handed to `fputcsv()`** — `fputcsv()` has no knowledge of spreadsheet-formula risk, only of CSV syntax. The two layers are applied in this fixed order: (1) formula-neutralization prefix if applicable → (2) `fputcsv()` structural escaping.

| Requirement | Current | Design position |
|---|---|---|
| Commas / quotes / embedded newlines | Hand-written, currently correct but fragile | `fputcsv()` — engine-guaranteed. |
| CRLF vs LF row terminators | Hard-coded `\n` | `fputcsv()` on most PHP versions writes `\n` row terminators by default (configurable in PHP 8.1+ via the `$eol` parameter) — **explicit decision needed at implementation time whether to pin `\r\n` for stricter RFC-4180 compliance or keep `\n` for compatibility with whatever currently consumes these files**; this Gate 2 does not resolve it, flags it as an implementation-time decision bounded by §5's compatibility requirement. |
| UTF-8 | No explicit handling | Verify via the Vietnamese-Unicode test (§7), not assumed. |
| BOM | None | Candidate addition for Excel compatibility on non-ASCII content; must not break any existing programmatic consumer expecting no BOM — verify before adding, not assumed safe. |
| Arrays/tags (`$task->tags`) | Passed directly into escaping | Must verify actual cast type before implementation; flagged, not assumed scalar. |
| Dates, numeric precision | Passed through as-is | Preserve exactly; pin with a regression test (§7) so a later Carbon/format change doesn't silently alter exports. |

---

## 4. End-to-end bounded-memory design (all four stages of §1.7) — design decision

**Decision: bound memory at every stage of the pipeline — database query → model hydration → transformation → CSV encoding → output — using a chunked/cursor query paired with a streamed write, never materializing the full task/project collection, the full `$csvData` array, or the full `$csvContent` string.**

### 4.1 Stage-by-stage design

| Stage | Current | Design decision |
|---|---|---|
| Database iteration | `->get()` — full result set | **`chunkById()`** (not `cursor()`): processes N rows per query, re-queries by primary key for the next batch. Chosen over `cursor()` because `cursor()` holds a single DB connection/statement open for the entire export duration, which is a worse failure/timeout profile for a potentially large export; `chunkById()`'s per-chunk queries are individually bounded and the connection is not held open across the whole export. Trade-off (disclosed, not implementation-hidden): `chunkById()` requires a stable, indexed ordering column (primary key) and can skip/duplicate rows if the underlying data is mutated mid-export by a concurrent write — acceptable for an export snapshot use case, but must be documented in the implementation plan, not silently assumed away. |
| Relationship loading (`with(['project','assignments'])` / `with(['tasks'])`) | Eager-loaded for the entire result set upfront | Eager-load **within each chunk**, not the whole set — avoids both the full-set upfront cost AND N+1 queries per row. |
| CSV row generation (`$csvData`) | Full array | **Eliminated.** Each hydrated model in a chunk is transformed directly into a row and written immediately — no intermediate full-array buffer at any point. |
| CSV encoding + write (`$csvContent`, `Storage::put()`) | Full string, single write | **`fputcsv()` writes directly to an open stream** (a local temp file handle, or a stream wrapped around the storage driver if the configured driver supports true streaming writes — must be verified against the actual configured driver before implementation commits to which). The temp file (if used) is uploaded/moved to final storage only after the full write completes; on any failure mid-write, the partial temp file is deleted (§8 rollback). |

### 4.2 Measurable acceptance criteria (see also §8)

- Peak memory during export must not scale linearly with row count beyond a small, fixed per-chunk overhead — demonstrated by an actual large-dataset test (§7), not asserted from code review.
- At no point does the pipeline hold a complete in-memory array of all rows **and** a complete in-memory CSV string simultaneously — this directly closes §1.7's gap.
- Concrete row-count/byte-size thresholds for "large export" test scenarios, maximum export size, timeout handling, and concurrent-export limits remain implementation-plan-level decisions (Gate 3+), not fixed by this Gate 2 design.

---

## 5. API compatibility — MUST-PRESERVE contract, read directly from current code

| Aspect | Current (verified in §1.1/§1.6) | Design requirement |
|---|---|---|
| HTTP methods | `POST` | **MUST preserve.** |
| Endpoint paths | `/tasks/bulk/export`, `/projects/bulk/export` | **MUST preserve.** |
| Request fields | `task_ids`/`project_ids` (array), `format` (csv/excel/json), `filters` (tasks only) | **MUST preserve** for the `csv` path (this design's scope). |
| Authorization behavior | `auth:sanctum` + generic `rbac` (13 broad roles) | **MUST preserve exactly** — no broadening, no narrowing (owner's explicit Gate 1 constraint). |
| Tenant isolation | Currently unscoped at query level (GAP-034) | Not GAP-010b's concern — see §6. |
| JSON response shape | `{success, message, data: {filename, download_url, total_X}}` | **MUST preserve.** |
| Filename pattern | `{resource}_export_{Y-m-d_H-i-s}.{format}` | **MUST preserve**, unless the chunked design (§4) makes synchronous completion genuinely impossible for some export size (see below). |
| `download_url` availability | Synchronous, available in the same response | **This is the single largest compatibility risk in this whole design.** See below. |
| Error status conventions | 500, raw exception message | Preserve envelope shape; raw-message leak is an adjacent finding (§1.8), not required to fix. |

### 5.1 The synchronous-completion question (explicit owner decision point, not resolved here)

Because chunked/streamed processing (§4) still runs to completion **within the same HTTP request** in this design's baseline (chunk-and-stream-to-a-file, not move-to-a-background-job), the synchronous response contract in §1.6 is preserved by default — `download_url` remains available in the same response, no client-visible behavior change, no polling/job-ID mechanism introduced. **This design deliberately chooses to keep the request synchronous rather than move to a background-job architecture**, specifically because introducing a job-ID/polling contract would be a breaking API change requiring its own explicit owner decision, and nothing in GAP-010b's stated objective requires that trade-off.

The one caveat, disclosed rather than hidden: if a production export is large enough that chunked processing still exceeds PHP/webserver request timeout limits even with bounded memory, that is a **timeout problem, not a memory problem**, and would need its own separate owner-level decision (e.g., a maximum export size limit with a clear user-facing error) — not solved by, or silently assumed away by, this design. No such background-job migration is proposed or authorized here.

### 5.2 Does the current client expect synchronous completion?

Assessed, not assumed: the response contract (§1.6) is the *only* contract that has ever existed for these two endpoints — no polling/job-ID/webhook mechanism exists anywhere in `ExportController`. Any existing integration (internal frontend or external API consumer) can only have been built against synchronous completion. This confirms the §5.1 design choice (stay synchronous) is also the compatibility-safe choice, not merely the simpler one.

---

## 6. Tenant isolation — GAP-034 is a HARD BLOCKER, out of GAP-010b's design scope

**GAP-034 (`docs/owner-decisions/GAP-034/01-request.md`, PR #246, Gate 1 awaiting owner) is a hard blocker for restoring or enabling `POST /tasks/bulk/export` and `POST /projects/bulk/export`.** This design does not include any tenant-filtering logic, does not propose adding a tenant `WHERE` clause "while we're in the file anyway," and does not treat tenant isolation as an acceptance criterion of GAP-010b (§8 excludes it explicitly).

This section exists only to state the dependency, not to design the dependency's solution:

- GAP-034 has its own Gate 1/2/3, its own acceptance criteria, its own tests, its own evidence, and its own owner decision — entirely separate from GAP-010b's.
- An implementation of GAP-010b that makes the export routes callable again (by fixing §1.3's import defect, whether inside GAP-010b's scope or a separate authorized change) **must not be deployed/merged in a state where the routes are reachable unless GAP-034 has ALSO been implemented and verified** — otherwise the combination re-opens a cross-tenant data leak worse than either issue alone.
- This design does not decide *how* GAP-034 is enforced (that is GAP-034's own Gate 2), only that GAP-010b's own release cannot restore route functionality ahead of it.

Section §1.2/§1.3 of the prior investigation round (generic RBAC scope, 13 broad roles) remains unchanged and out of scope for the same reason it was excluded before — not redesigned by GAP-010b, not redesigned by GAP-034.

---

## 7. Testing strategy (test matrix defined at Gate 2, for a future implementation plan to execute)

All of the following are **new** tests — no existing automated coverage of `ExportController` exists today beyond the static architecture-allowlist check (§1.3).

### 7.1 Formula-injection / data-integrity matrix (both `generateCsv()` and `generateProjectsCsv()`, or their approved replacements)

| Input | Required assertion |
|---|---|
| `=1+1` | Neutralized (`'`-prefixed in raw bytes), renders as text in spreadsheet apps, not evaluated. |
| `+123456789` | Neutralized. |
| `-10` | Neutralized. |
| `@SUM(A1:A2)` | Neutralized. |
| Leading tab + formula (`\t=1+1`) | Neutralized. |
| Leading CR/LF + formula | Neutralized. |
| Leading plain space before `=` (`  =1+1`) | Open question (§2.3) — test must determine and pin down real Excel/Sheets behavior before implementation, not assume either way. |
| Ordinary text | Unchanged. |
| Embedded comma | Correctly quoted (one cell). |
| Embedded quote | Correctly doubled-quote-escaped. |
| Multiline content | Correctly quoted, newline preserved inside quotes, still one cell. |
| Vietnamese Unicode | Correctly encoded, no mojibake. |
| Empty/null fields | Empty cell, no crash. |
| **Legitimate `+`-prefixed data (phone number, e.g. `+84901234567`)** | **Explicitly required by owner:** neutralized (per §2's rule, since it starts with `+`) BUT every digit of the phone number is still present and visible when the cell is opened in a spreadsheet application — proves the mitigation does not truncate or destroy legitimate content. |

### 7.2 Volume / bounded-memory tests

- Large task export and large project export (row count large enough to make the current triple-allocation pattern fail or approach memory limits in a controlled test environment) — must complete within bounded memory under the chunked design (§4), with peak memory demonstrated NOT to scale linearly with dataset size the way the current implementation does.
- Explicit regression proving the full dataset is never materialized as a complete in-memory array **and** a complete in-memory CSV string simultaneously at any point during export.

### 7.3 Compatibility and reliability

- Existing JSON response contract (§5) unchanged for the synchronous path.
- Stored file availability — `download_url` returned resolves to a retrievable file with the expected row count.
- Existing authorization behavior unchanged (a user who could not export before still cannot after; a user who could still can — no broadening, no narrowing).
- Failure handling when the stream/storage write fails mid-export: no partial file left referenced by any success response; explicit error returned; partial temp artifacts cleaned up.

### 7.4 Runtime-defect regression (§1.3, documented not fixed)

- A dedicated test asserting whether the current missing `Illuminate\Http\Request` import affects route execution, written **first** against the current (unfixed) code to formally document today's broken state as a named, citable regression test. This test's existence and initial (failing/erroring) result is part of Gate 2's required test-matrix definition; whether and when it is later fixed is a separate, explicit owner decision (§1.3), not decided or executed here.

### 7.5 Explicitly excluded from GAP-010b's test scope

Tenant-isolation tests (cross-tenant data leakage, tenant-scoped query correctness) belong entirely to GAP-034, not to GAP-010b's implementation. GAP-010b's implementation only needs a test/guard verifying that its own change does not, by itself, restore route functionality ahead of GAP-034's completion (i.e., a dependency guard, not a tenant-isolation test).

---

## 8. Rollback boundary

- **No database migration.** The tenant-scoping fix (GAP-034) and this design's memory/formula corrections are both query-level and file-generation-level changes, not schema changes.
- **GAP-010b rolls back independently** of GAP-034: reverting GAP-010b's implementation PR restores the prior (broken-but-inert, per §1.3) code state with no data or structural cleanup required.
- **Rollback must never weaken or bypass GAP-034.** Reverting GAP-010b's implementation does not touch GAP-034's own code, and GAP-034's hard-blocker status (§6) is unaffected by any GAP-010b rollback in either direction.
- Partial-write cleanup (temp files from an interrupted streamed export) is addressed as part of the failure-handling requirement in §7.3, not as a rollback concern — it's an operational cleanup, not a code revert.

---

## 9. Acceptance criteria (Gate 2 minimum, for a future implementation plan to satisfy)

1. Formula-interpretable values (§2) are neutralized per the two-layer design (§2/§3), with the CSV structural-escaping layer and the formula-neutralization layer never conflated or claimed to substitute for one another.
2. Legitimate values — explicitly including `+`-prefixed data such as phone numbers — remain fully visible and semantically recoverable when opened in a spreadsheet application (§2.2/§7.1).
3. Both task and project CSV paths are covered identically.
4. Memory usage remains bounded across ALL FOUR stages of the pipeline (§4), not just the final write — demonstrated by an actual large-dataset test.
5. No complete dataset array and complete CSV string coexist in memory at any point (§4.2).
6. Current *intended* RBAC/authorization behavior is unchanged (§5); the tenant-scoping question is entirely GAP-034's, not this criterion's concern.
7. Existing API contract (§5) preserved: synchronous request/response, same JSON shape, same endpoint/method/fields — no background-job migration introduced by this design.
8. Standards-compliant CSV (§3): quotes, delimiters, multiline, UTF-8 all valid.
9. Failure leaves no misleading completed export (§7.3).
10. No adjacent finding (§1.8) is silently folded into GAP-010b's implementation scope without a separate, explicit owner decision.
11. GAP-010b's implementation can be reverted without a database rollback, independently of GAP-034 (§8).
12. **The two affected export routes are not restored to working order (i.e., the §1.3 import defect is not fixed and deployed) unless GAP-034 has ALSO been implemented and verified** (§6) — this is a hard release-gating criterion, not a recommendation.
13. GAP-010c and all other gaps remain out of scope.

---

## 10. Independent review

An independent review of this Gate 2 design is required before it is presented as final to the owner. The reviewer must specifically evaluate:

- Whether §1.3's runtime-reachability finding is accurately characterized as documented-but-not-fixed, with no implementation slipped in.
- Whether the two-layer formula/CSV design (§2/§3) genuinely separates syntax from semantics, and whether the `+`-prefix data-integrity claim (§2.2/§7.1) is actually justified, not merely asserted.
- Whether the memory design (§4) actually addresses all four pipeline stages, not just the final write, and whether the `chunkById()` vs `cursor()` trade-off is fairly presented.
- Whether the API-compatibility section (§5) correctly identifies the synchronous-completion decision as the single largest risk and doesn't quietly assume a background-job migration is out of scope without saying so plainly.
- Whether §6 (GAP-034 hard-blocker) avoids designing GAP-034's own solution and avoids adding any tenant-filtering logic to GAP-010b's own scope.
- Whether adjacent findings (§1.8) are cleanly separated from GAP-010b's proposed scope.
- Whether this document accidentally pre-selects implementation code (as opposed to a design decision, which Gate 2 is permitted to make) anywhere that would exceed Gate 2's authorization.
- Whether a non-technical owner reading only `docs/owner-decisions/GAP-010b/02-design.md` has enough information to make the Gate 2 decision without needing this full document.

*(Independent review to be dispatched and its findings recorded in the final report before this design is considered ready for owner Gate 2 review.)*
