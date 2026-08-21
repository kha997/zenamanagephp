---
work_id: GAP-043
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "Approve, reject, or request changes to the Gate-2 design for fixing PerformanceMonitoringTest::tableInsertDefaults()'s SQLite-only PRAGMA call: recommended approach is Option A (replace with Laravel's framework-native Schema::getColumns(), verified present in installed Laravel 12.63.0, no driver branching needed)."
references:
  spec: docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md
  plan: null
  branch: docs/GAP-043-gate2-design
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T21:30:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T21:30:00+07:00"
  updated_at: "2026-08-21T21:30:00+07:00"
generated_by: agent
---

## GATE 2: AWAITING OWNER DECISION

## Owner Summary

`PerformanceMonitoringTest::tableInsertDefaults()` uses SQLite-only `PRAGMA table_info` syntax to reconstruct DB column defaults before raw bulk-inserting fixture rows (GAP-043, Gate 1 approved in PR #279). This Gate 2 investigated whether the helper is actually needed (it is — several `NOT NULL` columns on `projects`/`tasks` have DB-level defaults the factories don't set, so without it those columns would insert as `NULL` and violate the constraint), verified Laravel 12.63.0 (the version actually installed, per `composer.lock`) ships a portable `Schema::getColumns($table)` API that returns the same normalized shape on both SQLite and MySQL, and compared four design options.

**Recommendation: Option A** — swap the `PRAGMA table_info` loop for `Schema::getColumns($table)`, keep the existing default-extraction/quote-stripping logic. This eliminates the SQLite-only syntax with no driver branching, no change to the batch-insert row-uniformity design (which the raw `insert()` bulk path genuinely requires — confirmed by reading `Illuminate\Database\Query\Builder::insert()`), and no ongoing coupling to today's specific default values (unlike hardcoding them).

## Vấn đề vận hành (Operational problem)

Gate 1 established the defect and blast radius; Gate 1 explicitly did not select a fix. This Gate 2 answers "what should the fix be" via: (1) reconstructing why the helper exists and whether it's load-bearing, (2) a full column/default inventory for `projects` and `tasks` cross-referenced against actual migrations and the factories the test uses, (3) verifying the exact installed-framework API surface rather than assuming one, and (4) evaluating 4 design families (framework-native API / driver-specific branching / omit-and-let-DB-default / hardcode-the-small-set) against the batch-insert uniform-row-shape constraint that turned out to be the real limiting factor.

## Người dùng bị ảnh hưởng (Affected parties)

Same as Gate 1: engineering team (currently blocked from a real MySQL-passing `PerformanceMonitoringTest` run), GAP-041 (blocked_technical, needs GAP-043/044/045 resolved before its own Gate 3), Owner/stakeholders receiving red CI on `performance-tests`.

## Bằng chứng (Evidence)

Full technical evidence, column inventory, framework-API verification (with exact `vendor/laravel/framework` file/line citations), and 4-option comparison are in `docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md`. No LOCAL probe execution was performed for this Gate 2 — verification was via reading installed framework source directly (authoritative for API existence/shape) plus static reading of migrations and factories; this was judged sufficient at design stage, with LIVE MySQL verification deferred to Gate 3 per the acceptance contract in the design doc.

## Tác động nếu không xử lý (Impact if unaddressed)

Unchanged from Gate 1: `performance-tests` (monitoring leg) stays permanently red for 6 of 10 `PerformanceMonitoringTest` methods even after GAP-044 is separately fixed, and GAP-041 stays `blocked_technical`.

## Phạm vi đề xuất (Proposed scope)

**Future implementation surface (not yet authorized — Gate 3 implementation requires separate authorization after this Gate 2 is approved):** `tests/Performance/PerformanceMonitoringTest.php`, specifically the body of the private `tableInsertDefaults()` method only. No other file. No application code, migration, schema, model, RBAC/tenant, or CRM/Project/Service-Line/OPPM/Finance/Treasury domain logic is implicated — Design Dependency Preflight is not triggered.

**Attribution-safe Gate-3 acceptance contract** (full text in the design doc): GAP-043's GREEN claim is scoped to the 6 tests that fail directly on the PRAGMA call today, not the whole test class — `test_api_performance_budgets` (masked by GAP-044) is explicitly carved out and evaluated separately so GAP-044's unresolved state cannot block or falsely credit GAP-043's closure.

## Loại trừ rõ ràng (Explicit exclusions)

GAP-041 (already fixed, not reopened), GAP-042 (RBAC, unrelated), GAP-044 (SAVEPOINT — not investigated, only the masking interaction is accounted for in the acceptance contract), GAP-045 (confirmed structurally unrelated at Gate 1). The 11 dormant unguarded-`PRAGMA` occurrences in other Feature/Integration test files (cataloged at Gate 1) remain out of scope — not fixed, not refactored, no new gap auto-registered.

## Quyết định cần Owner (Decision requested)

Approve Option A as the Gate-2 design, or reject/request changes. This Gate 2 packet does **not** authorize implementation, test-code changes, Gate 3, or merge/release — those require a separate Owner decision after this design is approved.
