---
work_id: GAP-040
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md
  plan: null
  branch: docs/GAP-040-gate1-prep
  pr: "https://github.com/kha997/zenamanagephp/pull/269"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-20T14:00:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-20: 'GAP-040 — GATE 1 OWNER DECISION: APPROVE... PR: #269, reviewed head 6f04ef723964101a660f0996a1d7438dc2b856a0, baseline 87a4307fdcf8117d8cac4b11c2cb27cb637ada5a... APPROVED. I accept that GAP-040 is a real test-infrastructure integrity problem worth proceeding to Gate 2 design. The approved problem statement is: Tests\\TestCase::ensureSqliteZenaRbacTables() performs unconditional DDL on real-MySQL test paths. For test classes where RefreshDatabase has already opened a transaction, MySQL implicit-commit behavior can defeat the transaction isolation that CI is relying upon, weakening confidence in tenant/data-integrity and other MySQL-parity evidence. I also accept the corrected v2 affected-surface analysis as the Gate 1 basis. End-to-end test-state leakage has not been empirically reproduced and must continue to be described as such; do not rewrite that limitation as demonstrated production/runtime failure.' Owner also directed: Gate 2 design authorized, implementation not authorized; the zero-test performance-tests/performance-budget/performance-heavy group-name mismatch is out of GAP-040 scope and must be tracked as a separate gap if confirmed, not silently fixed here; Design Dependency Preflight not currently required but must trigger if a proposed fix touches production Treasury schema/tenant semantics/business behavior; Gate 2 submission may need a separate Draft PR from canonical baseline per the GAP-039 precedent if --enforce-gate-ordering requires it, cross-referencing PR #269."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-20T00:00:00+07:00"
  updated_at: "2026-08-20T14:00:00+07:00"
generated_by: agent
---

> **v2 — Gate 1 APPROVED.** Owner requested changes on the initial v1 submission (affected-surface inventory built from a stale local checkout instead of exact `origin/main`, and a failing PR-body governance check); v2 corrected both (see commit history and PR #269). Owner subsequently reviewed v2 at head `6f04ef723964101a660f0996a1d7438dc2b856a0` and **approved** Gate 1. This section records that approval; the substantive v2 evidence-derived content below (Owner Summary, affected-surface inventory, evidence references, etc.) is unchanged from the approved submission.

## OWNER GATE 1: APPROVED

Owner phê duyệt GAP-040 Gate 1 lúc `2026-08-20T14:00:00+07:00`, đã review head `6f04ef723964101a660f0996a1d7438dc2b856a0` của PR #269 (baseline `87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`). Owner xác nhận GAP-040 là vấn đề test-infrastructure integrity có thật, đáng để chuyển sang Gate 2 design. Problem statement được duyệt: `Tests\TestCase::ensureSqliteZenaRbacTables()` thực thi DDL vô điều kiện trên các đường test MySQL thật; đối với các test class mà `RefreshDatabase` đã mở transaction, hành vi implicit-commit của MySQL có thể vô hiệu hoá transaction isolation mà CI đang phụ thuộc vào, làm suy yếu độ tin cậy của bằng chứng tenant/data-integrity và MySQL-parity khác. Owner cũng chấp nhận bản phân tích affected-surface v2 đã sửa làm cơ sở Gate 1. Rò rỉ trạng thái test đầu-cuối CHƯA được tái hiện thực nghiệm và phải tiếp tục được mô tả đúng như vậy — không được viết lại thành lỗi runtime/production đã chứng minh.

Quyết định này CHỈ cho phép chuyển sang Gate 2 design. Implementation KHÔNG được cấp phép. Phát hiện riêng về `performance-tests`/`performance-budget`/`performance-heavy` có thể chạy 0 test do lệch tên group KHÔNG thuộc phạm vi GAP-040 — phải được xác minh độc lập và đăng ký dưới Work ID riêng nếu xác nhận đúng, không được âm thầm sửa trong GAP-040. Design Dependency Preflight hiện KHÔNG bắt buộc, nhưng nếu giải pháp đề xuất đụng đến schema Treasury sản xuất, ngữ nghĩa tenant chính tắc, hay hành vi nghiệp vụ, phải DỪNG và chạy preflight tương ứng trước khi thiết kế phần đó. Gói Gate 2 khi hoàn thành phải quay lại `awaiting_owner` để Owner ra quyết định riêng, trước khi bất kỳ implementation nào bắt đầu; nếu `--enforce-gate-ordering` không cho phép gói Gate 2 nằm chung PR với lịch sử Gate 1, tạo Draft PR riêng từ baseline chính tắc, tham chiếu rõ PR #269, theo đúng tiền lệ GAP-039.

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` runs 8 DDL statements (4 `DROP TABLE IF EXISTS` + 4 `CREATE TABLE`) unconditionally in every single test's `setUp()`, on every database driver — no driver guard (unlike its sibling `ensureSqliteDocumentsBackupTable()`) and no existence guard (unlike its sibling `ensureSqliteSubmittalsTable()`). Because `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` renames these tables away during every fresh migration, the `CREATE TABLE` calls are not a rare fallback — they genuinely execute every test, always.

For test classes that use Laravel's `RefreshDatabase` trait, this DDL runs *after* `RefreshDatabase` has already opened a database transaction meant to isolate that test's writes. On MySQL, `CREATE TABLE`/`DROP TABLE` are documented to cause an implicit `COMMIT`. If that holds here, the isolating transaction is silently committed away mid-`setUp()`, and the rollback registered for teardown has nothing left to undo — the test's writes are not cleaned up, and can leak into the next test sharing that MySQL connection.

## Vấn đề vận hành (corrected inventory)

Rebuilt directly from exact `origin/main @ 87a4307fdcf8117d8cac4b11c2cb27cb637ada5a` (the v1 submission mistakenly used a stale local checkout that predated several post-GAP-039 workflow changes; see evidence §5 for the specific corrections). On this baseline, at minimum **5 distinct real-MySQL, `RefreshDatabase`-using CI surfaces are directly exposed** to the specific transaction-isolation-defeat mechanism:

1. `routes-guardrails.yml`'s MySQL-parity step (`--group=mysql-parity`) — `TenantIsolationProjectsTest`, `DatabaseConstraintsTest`.
2. `automated-testing.yml`'s `zena-invariants-mysql` job — 12 of 17 `--group=zena-invariants` test classes.
3. `automated-testing.yml`'s `treasury-check-constraints-mysql` job — 16 of 19 Treasury schema/model test files (the largest single surface found), plus its gating step-2 test.
4. `a11y-perf-testing.yml`'s `e2e-tests` job — `CriticalUserFlowsE2ETest`, `DashboardE2ETest`.
5. `ci-cd.yml`'s GAP-032-MySQL-proof step in the `test` job — `DocumentStatusMigrationTest` — this is part of the **primary PR-gating pipeline**.

A further 3 real-MySQL surfaces (the two concurrency scripts, plus one Treasury-script step) run the same unguarded DDL but have no open `RefreshDatabase` transaction to defeat — related schema churn, a lower-severity adjacent concern, not the primary defect. 3 additional real-MySQL, fail-closed-preflight surfaces (`performance-tests`, `performance-budget`, `performance-heavy`) currently execute zero tests due to an unrelated group-naming mismatch between their CI command and the actual test annotations — not exposed today, but not meaningfully validated either; flagged for Gate 2 scoping as a distinct, adjacent defect, not claimed as GAP-040 exposure.

CI is currently green — no test currently depends on execution order in a way that has surfaced this as a visible failure. This remains a structural defect with a well-documented (MySQL implicit-commit-on-DDL) but not-live-reproduced-in-this-environment runtime consequence (a local MariaDB reproduction attempt failed on an unrelated filesystem permission error; no elevated system changes were made or are required to establish this finding). This is not asserted as an empirically observed data-leakage incident.

## Người dùng bị ảnh hưởng

Engineering team relying on green CI across 5 distinct real-MySQL surfaces — including the primary PR-gating pipeline (`ci-cd.yml`) — as evidence that `RefreshDatabase`-isolated tests are validated against isolated, production-representative MySQL state per test. That isolation guarantee does not actually hold as currently implemented on any of those 5 surfaces. Anyone extending any of them with a new `RefreshDatabase`-using test inherits the same silent isolation gap. No end-user-facing production behavior is affected; this is testing-infrastructure integrity only.

## Bằng chứng

Full evidence — code trace, framework-source trace, PHPUnit group-filter-precedence trace, live reproduction of the `tests/bootstrap.php` env-override mechanism, and the corrected affected-surface matrix — is recorded in `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md` (v2). No application or test code was modified to produce this evidence.

## Tác động nếu không xử lý

The `RefreshDatabase`-using tests across all 5 exposed surfaces — including the primary PR-gating pipeline — continue running without the per-test isolation their use of `RefreshDatabase` implies. State written by one test could leak into the next test sharing the connection, producing order-dependent results or false greens that do not reliably validate the RBAC/tenant/audit/Treasury invariants those tests are named for. The risk grows as more `RefreshDatabase`-using tests are added to any of these groups, or as the same unguarded helper pattern gets copied elsewhere.

## Phạm vi đề xuất

Gate 1 confirms only: (1) the structural defect is real and directly traceable in code (evidence §1-§3); (2) the actually-affected surface, now correctly enumerated against exact `origin/main` (evidence §4), is materially broader than initially reported and touches the primary PR-gating pipeline; (3) a Gate 2 design decision is needed on the specific guard mechanism (driver guard, existence guard, or both, per the sibling-helper patterns already in the file) and on what regression evidence would prove isolation is restored across all 5 exposed surfaces; (4) Gate 2 should separately scope (not necessarily fix in the same change) the adjacent group-naming defect that currently makes 3 real-MySQL jobs run zero tests. Gate 1 does not select a technical mechanism for either — that is Gate 2's decision.

## Loại trừ rõ ràng

Does not reopen or modify GAP-039 (released, PR #268, merged `87a4307f`). No workflow file, `tests/bootstrap.php`, PHPUnit config, test code, or production/application code is changed by this Gate 1 submission — this document and its companion evidence file are the only contents of this PR. No business-domain semantics, tenant behavior, or production schema are proposed to change; per the governance classification below, this does not require the CRM/Project/Finance/OPPM Design Dependency Preflight unless Gate 2 investigation later shows otherwise (in which case work stops and that preflight runs before any design/plan/code).

## Governance classification

GAP-040 is a test infrastructure / database transaction-isolation integrity defect. It does not, on its own, require the Design Dependency Preflight, because no business-domain semantics are being changed — the `zena_*` RBAC tables involved are legacy pre-rename artifacts of a test-only helper, not the live `roles`/`permissions` tables the application actually uses; the Treasury tests exposed (surface #3) are schema/constraint tests, not business-logic changes. If Gate 2 design work reveals that a fix would require changing canonical business semantics, tenant behavior, production schema, or application-domain behavior, work stops and the appropriate preflight runs first. Tenant isolation and data integrity remain mandatory technical constraints that cannot be waived by Owner.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt để tiến hành thiết kế Gate 2, trả lời câu hỏi chất lượng nghiệp vụ sau — có cho phép thiết kế bản vá cho một helper test dùng chung, hiện có thể vô hiệu hoá transaction isolation trên 5 bề mặt CI thật-MySQL dùng `RefreshDatabase` (bao gồm cả pipeline gác cổng PR chính), và do đó làm suy yếu độ tin cậy của bằng chứng CI về tenant/data-integrity? Không chọn cơ chế kỹ thuật cụ thể ở Gate 1.

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code change, workflow file change, or specific technical guard mechanism at this step — only to confirm the problem, now with a corrected and materially broader affected-surface inventory, is real and worth a Gate 2 design. Owner is not deciding on GAP-039 (already released) or any other gap. No production code, no Gate 3, no merge is authorized by this document.
