---
work_id: GAP-043
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md
  plan: null
  branch: docs/GAP-043-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/280"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-21T22:45:00+07:00"
  owner_response_reference: "Owner Gate-2 APPROVAL (2026-08-21), PR #280, reviewed design head aa551240cb02ea8b06d11b39bfef0e91d48cf657, canonical baseline 25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac: APPROVE GAP-043 Gate 2 v2 — Option A (Laravel Schema::getColumns($table)) approved as the technical design; approved implementation boundary strictly tests/Performance/PerformanceMonitoringTest.php private tableInsertDefaults(string $table): array, no other surface without returning to Owner; binding design semantics reaffirmed (12-pair load-bearing inventory: projects budget_total/estimated_hours/actual_hours/risk_level/is_template/completion_percentage/actual_cost, tasks risk_level/complexity/effort_points/time_spent/is_billable; Schema::getColumns() portable at the Laravel API boundary; SQLite/MySQL raw default representations not required to be byte-identical; framework processors normalize structure only; existing helper quote-stripping logic reconciles the SQLite-quoted-vs-MySQL-unquoted literal difference; no driver-specific schema SQL to be introduced; keep current bulk-insert/full-row-shape semantics; GAP-044/045/042 and dormant PRAGMA findings not absorbed); Gate 2 approval authorizes implementation only within the approved scope and does NOT authorize ready/merge/release/deploy, which remain blocked pending Gate 3 Owner approval. Owner also authorized two non-substantive epistemic wording corrections applied in this same commit: Correction A (framework processors normalize metadata structure only, not literal default representation — reconciling SQLite-quoted-vs-MySQL-unquoted literals is tableInsertDefaults()'s existing quote-stripping logic, not the processors) and Correction B (generation == null proves only that a column is not a generated column, not that its default is a literal rather than a SQL expression — the literal-vs-expression conclusion for the 12 load-bearing defaults rests on the LOCAL probe's raw values plus the migrations' plain ->default($scalar) declarations). No option/recommendation/inventory/acceptance-contract change was authorized or made beyond these two wording corrections."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-21T21:30:00+07:00"
  updated_at: "2026-08-21T22:45:00+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

Owner approved GAP-043 Gate 2 v2 (PR #280, reviewed head `aa551240cb02ea8b06d11b39bfef0e91d48cf657`, canonical baseline `25cab7f4955ed9a9b5d0c7113c19ca1ea679c3ac`). Approved technical design: **Option A — Laravel `Schema::getColumns($table)`**. Approved implementation boundary: `tests/Performance/PerformanceMonitoringTest.php`, specifically `private tableInsertDefaults(string $table): array` only — no other implementation surface is approved unless new evidence proves this boundary insufficient, in which case implementation must stop and return to Owner.

**Binding design semantics reaffirmed by this approval:**
1. `tableInsertDefaults()` is load-bearing for raw bulk fixtures.
2. Correct load-bearing DB-default inventory — 12 table-column pairs: `projects` (`budget_total`, `estimated_hours`, `actual_hours`, `risk_level`, `is_template`, `completion_percentage`, `actual_cost`); `tasks` (`risk_level`, `complexity`, `effort_points`, `time_spent`, `is_billable`).
3. `Schema::getColumns()` is portable at the Laravel API boundary.
4. SQLite and MySQL raw default representations are NOT required to be byte-identical.
5. Framework processors provide the common structured metadata shape (not literal-value normalization).
6. The existing quote-stripping logic in `tableInsertDefaults()` is responsible for reconciling the SQLite-quoted-vs-MySQL-unquoted literal representation difference.
7. No driver-specific schema SQL is to be introduced.
8. Current bulk-insert/full-row-shape semantics are kept as-is.
9. GAP-044/GAP-045/GAP-042 and the dormant PRAGMA findings are not absorbed into this scope.
10. Gate 2 approval authorizes implementation only within the approved scope.
11. Gate 2 approval does **not** authorize ready/merge/release/deploy — those remain blocked pending Gate 3 Owner approval.

**Owner-authorized non-substantive wording corrections applied in this same commit** (design doc only, no option/recommendation/inventory/acceptance-contract change): Correction A — framework processors normalize metadata *structure*, not the raw literal default representation; the existing `tableInsertDefaults()` quote-stripping logic (not the processors) reconciles the SQLite-quoted-vs-MySQL-unquoted difference. Correction B — `generation == null` proves only that a column is not a *generated* column; it is not evidence about literal-vs-SQL-expression default status. That determination rests on the LOCAL probe's raw values (plain literal text, no expression syntax) and the migrations' plain `->default($scalar)` declarations.

## Owner Summary

`PerformanceMonitoringTest::tableInsertDefaults()` uses SQLite-only `PRAGMA table_info` syntax to reconstruct DB column defaults before raw bulk-inserting fixture rows (GAP-043, Gate 1 approved in PR #279). This Gate 2 investigated whether the helper is actually needed (it is — several `NOT NULL` columns on `projects`/`tasks` have DB-level defaults the factories don't set, so without it those columns would insert as `NULL` and violate the constraint), verified Laravel 12.63.0 (the version actually installed, per `composer.lock`) ships a portable `Schema::getColumns($table)` API that returns the same normalized shape on both SQLite and MySQL, and compared four design options.

**Recommendation: Option A** — swap the `PRAGMA table_info` loop for `Schema::getColumns($table)`, keep the existing default-extraction/quote-stripping logic. This eliminates the SQLite-only syntax with no driver branching, no change to the batch-insert row-uniformity design (which the raw `insert()` bulk path genuinely requires — confirmed by reading `Illuminate\Database\Query\Builder::insert()`), and no ongoing coupling to today's specific default values (unlike hardcoding them).

## Vấn đề vận hành (Operational problem)

Gate 1 established the defect and blast radius; Gate 1 explicitly did not select a fix. This Gate 2 answers "what should the fix be" via: (1) reconstructing why the helper exists and whether it's load-bearing, (2) a full column/default inventory for `projects` and `tasks` cross-referenced against actual migrations and the factories the test uses, (3) verifying the exact installed-framework API surface rather than assuming one, and (4) evaluating 4 design families (framework-native API / driver-specific branching / omit-and-let-DB-default / hardcode-the-small-set) against the batch-insert uniform-row-shape constraint that turned out to be the real limiting factor.

## Người dùng bị ảnh hưởng (Affected parties)

Same as Gate 1: engineering team (currently blocked from a real MySQL-passing `PerformanceMonitoringTest` run), GAP-041 (blocked_technical, needs GAP-043/044/045 resolved before its own Gate 3), Owner/stakeholders receiving red CI on `performance-tests`.

## Bằng chứng (Evidence)

Full technical evidence, corrected 12-pair column inventory (7 `projects` + 5 `tasks`, corrected from v1's incomplete 11), framework-API verification (with exact `vendor/laravel/framework` file/line citations), a LOCAL runtime probe against genuine migrated MySQL 8.0 (throwaway Docker container) and genuine migrated SQLite comparing raw and normalized `Schema::getColumns()` output for both tables, and the corrected 4-option comparison (including the C1/C2/C3 breakdown of Option C) are in `docs/superpowers/specs/2026-08-21-gap-043-performance-test-mysql-portability-design.md`. LIVE MySQL verification remains deferred to Gate 3 per the acceptance contract in the design doc — the LOCAL probe in this v2 does not substitute for it.

## Tác động nếu không xử lý (Impact if unaddressed)

Unchanged from Gate 1: `performance-tests` (monitoring leg) stays permanently red for 6 of 10 `PerformanceMonitoringTest` methods even after GAP-044 is separately fixed, and GAP-041 stays `blocked_technical`.

## Phạm vi đề xuất (Proposed scope)

**Future implementation surface:** `tests/Performance/PerformanceMonitoringTest.php`, specifically the body of the private `tableInsertDefaults()` method only. No other file. No application code, migration, schema, model, RBAC/tenant, or CRM/Project/Service-Line/OPPM/Finance/Treasury domain logic is implicated — Design Dependency Preflight is not triggered.

**Governance semantics:** per this register's canonical lifecycle, Gate 1 approval accepts the problem/evidence; **Gate 2 approval accepts the design AND authorizes implementation within the approved scope**; Gate 3 is the release decision — no ready/merge/release/deploy before Gate-3 approval. This Gate-2 packet is now **approved** — implementation of Option A confined to `tableInsertDefaults()` is authorized. Merge, release, and deployment remain gated on Gate 3, not yet started.

**Attribution-safe Gate-3 acceptance contract** (full text in the design doc): GAP-043's GREEN claim is scoped to the 6 tests that fail directly on the PRAGMA call today, not the whole test class — `test_api_performance_budgets` (masked by GAP-044) is explicitly carved out and evaluated separately so GAP-044's unresolved state cannot block or falsely credit GAP-043's closure.

## Loại trừ rõ ràng (Explicit exclusions)

GAP-041 (already fixed, not reopened), GAP-042 (RBAC, unrelated), GAP-044 (SAVEPOINT — not investigated, only the masking interaction is accounted for in the acceptance contract), GAP-045 (confirmed structurally unrelated at Gate 1). The 11 dormant unguarded-`PRAGMA` occurrences in other Feature/Integration test files (cataloged at Gate 1) remain out of scope — not fixed, not refactored, no new gap auto-registered.

## Quyết định Owner (Owner decision)

**APPROVED** (2026-08-21). Option A (`Schema::getColumns()`) is the approved technical design; implementation is authorized strictly within `tests/Performance/PerformanceMonitoringTest.php`'s `tableInsertDefaults()`. This approval does **not** authorize Gate 3, merge, release, or deployment — those remain separately gated and were not part of this decision.
