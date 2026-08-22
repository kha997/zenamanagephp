---
work_id: GAP-044
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_more_info_or_decline_or_defer
references:
  spec: docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md
  plan: null
  branch: docs/GAP-044-gate1-investigation
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-22T00:00:00+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-22T00:00:00+07:00"
  updated_at: "2026-08-22T00:00:00+07:00"
generated_by: agent
---

## Owner Summary

`SQLSTATE[42000]: Syntax error or access violation: 1305 SAVEPOINT trans2
does not exist` — observed LIVE on real MySQL in both
`tests/Performance/PerformanceMonitoringTest.php` (`test_api_performance_budgets`)
and `tests/Performance/DashboardPerformanceTest.php`
(`it_can_load_dashboard_with_large_dataset_quickly`) — has a **confirmed
root cause**, established via live server-observed probe evidence on genuine
MySQL 8.0, not inference: three methods in `tests/TestCase.php` —
`ensureInteractionLogsTable()`, `ensureProjectPhasesTable()`,
`ensureProjectTasksTable()` — create three tables that have **no migration
anywhere in the repository**, guarded only by an existence check, with
**no driver guard and no isolated-connection routing**. Their `Schema::create()`
DDL runs directly on `RefreshDatabase`'s already-open, transacted MySQL
connection on every cold-start test, and MySQL's implicit-commit-on-DDL
behavior silently kills that transaction. When the first subsequent
nested-transaction consumer in the same test — here, Eloquent's built-in
`firstOrCreate()`/`createOrFirst()` savepoint-safety wrapper, reached via
`tests/Traits/TenantUserFactoryTrait.php`'s `ensurePermissionAttached()` —
later tries to roll back its savepoint, MySQL reports it as already gone,
surfacing exactly this error.

This is the **same defect class GAP-040 already fixed** for a sibling method
in the same file (`ensureSqliteZenaRbacTables()`, isolated onto a separate
`zena_ddl_bootstrap` connection) — but GAP-040's fix was scoped to that one
method only; these three siblings were not discovered or touched by that
work.

## Vấn đề vận hành

`tests/TestCase.php::setUp()` calls, unconditionally for every test class
extending `Tests\TestCase`: `ensureInteractionLogsTable()`,
`ensureProjectPhasesTable()`, `ensureProjectTasksTable()`. Each is guarded
only by `Schema::hasTable(...)`; none has a driver guard; none routes its
DDL through an isolated connection the way GAP-040's fixed
`ensureSqliteZenaRbacTables()` now does. `interaction_logs`, `project_phases`,
and `project_tasks` have zero migrations in `database/migrations/` — they
are always absent on a fresh MySQL database, so their `Schema::create()`
calls genuinely execute, on the live transacted connection, on the first
`RefreshDatabase` test of every fresh MySQL process.

A disposable, never-merged evidence harness (branch
`investigate/GAP-044-disposable-evidence-harness`, deleted immediately after
evidence capture, GitHub Actions run `32557247386`) instrumented these
boundaries with `DB::transactionLevel()`/`PDO::inTransaction()`/`CONNECTION_ID()`
probes and reproduced the exact mechanism live: `PDO::inTransaction()` flips
from `true` to `false` at precisely the `ensureInteractionLogsTable()` call,
on the same connection ID, for the first test of every process, with
Laravel's PHP-side transaction-level counter never learning about the loss.
When `Permission::firstOrCreate()` (called from test fixture setup, before
the test body runs) later needs to create a new row and Eloquent's built-in
race-safety wrapper (`withSavepointIfNeeded()`) opens a savepoint on that
already-dead transaction, the savepoint is discarded before it can be rolled
back, producing exactly `SAVEPOINT trans2 does not exist`.

Full mechanism, live evidence, exact framework-source trace, blast-radius
matrix, ranked hypotheses, and open items are recorded in
`docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md`.

## Người dùng bị ảnh hưởng

Engineering team relying on GAP-040's release as having fully closed
MySQL transaction-isolation defeat across its 5 approved real-MySQL
surfaces — that closure does not actually hold, because three sibling
methods to the one GAP-040 fixed retain the identical unguarded-DDL pattern.
GAP-041 (currently `blocked_technical`) remains blocked pending GAP-044 (and
GAP-045) resolution before it can present a fully-green LIVE Gate 3.
Any test author relying on `Tests\TestCase`'s `RefreshDatabase` guarantee on
real MySQL inherits the same silent exposure on the first test of a fresh
process.

## Bằng chứng

Full evidence in `docs/audits/2026-08-22-gap-044-savepoint-trans2-root-cause-evidence.md`:
LIVE reproduction (GitHub Actions run `32557247386`, jobs `96993284455` and
`96993284478`, genuine MySQL 8.0 service container, truthful `--group=performance
--fail-on-empty-test-suite` selector per GAP-041's already-approved overlay
mechanism); server-observed transaction-state probe log (262 combined probe
lines); exact Laravel-framework-source trace confirming the failing
operation, its origin, and its cause; migration-inventory confirming the
three implicated tables have no migration; GAP-040 gate-record cross-check
confirming its approved scope never covered these three methods; blast-radius
grep across all of GAP-040's 5 approved surfaces and 102 repo-wide
`FixtureFactory`/`TenantUserFactoryTrait` consumers.

## Tác động nếu không xử lý

`PerformanceMonitoringTest`/`DashboardPerformanceTest` continue failing
their first-declared test on real MySQL indefinitely. GAP-041 remains
blocked, unable to present a genuinely all-green LIVE Gate 3. Any other
`RefreshDatabase`-using test on real MySQL — including all 5 of GAP-040's
own approved surfaces — remains silently exposed to the same
implicit-commit-transaction-defeat mechanism GAP-040 believed it had fully
eliminated, on the first test of every fresh process, with visible failure
depending only on whether that specific test happens to exercise a
nested-transaction-consuming call (such as `firstOrCreate()`) afterward.

## Phạm vi đề xuất

Gate 1 confirms only: (1) the root-cause mechanism is established via live,
server-observed MySQL evidence, not inference — `tests/TestCase.php`'s three
unguarded sibling methods (`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`)
implicit-commit `RefreshDatabase`'s transaction on the first cold-start test,
exactly mirroring GAP-040's already-diagnosed-and-fixed defect pattern for a
different method in the same file; (2) the defect is genuinely test-only —
no application/schema/RBAC/tenant/business semantics are implicated; (3) a
Gate 2 design decision is needed on the specific remediation mechanism (e.g.
extending GAP-040's isolated-`zena_ddl_bootstrap`-connection pattern to
these three siblings, or an equivalent complete solution) and on what
regression evidence would prove the invariant restored across whichever
surfaces Gate 2 scopes; (4) one open item — the exact originating exception
inside `Permission::create()`'s/`Role::create()`'s wrapped closure — remains
unresolved and is flagged as a candidate Gate 2 (or Gate 1 follow-up)
investigation item, not guessed at here. Gate 1 does not select a technical
mechanism.

## Loại trừ rõ ràng

Does not reopen or modify GAP-040's already-released decision record
(`docs/owner-decisions/GAP-040/*`) — §H of the evidence document reports new
evidence for Owner consideration only. Does not touch GAP-041 (only reused
its already-Owner-approved selector-overlay mechanism, unmerged, purely for
evidence-gathering — identical precedent to GAP-043's own disposable
harness), GAP-042 (RBAC production-fidelity, unrelated), or GAP-045 (latency
budget — the one `DashboardPerformanceTest` failure attributable to GAP-045
is confirmed structurally distinct from this SAVEPOINT mechanism and is not
investigated here). No test, application, migration, or workflow code is
changed by this Gate 1 submission — this document and its companion
evidence file are the only contents of this PR (plus the disposable
evidence-harness branch, already deleted, never merged, never presented as
implementation). No business-domain semantics, tenant behavior, or
production schema are proposed to change; per the governance classification
in the evidence document, this does not require the Design Dependency
Preflight unless Gate 2 investigation later shows otherwise, in which case
work stops and that preflight runs before any design/plan/code.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt để xác nhận phạm vi/bằng chứng Gate 1
— root cause đã được xác lập bằng bằng chứng LIVE trên MySQL thật (không chỉ
suy luận), là cùng một loại lỗi GAP-040 đã sửa cho một method khác trong
cùng file, nhưng chưa được sửa cho 3 method anh em của nó. Không chọn cơ chế
kỹ thuật cụ thể ở Gate 1.

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code change, workflow file change,
or specific technical remediation mechanism at this step — only to confirm
the root-cause evidence and problem scope are sound and worth a Gate 2
design (if authorized). Owner is not being asked to reopen or modify
GAP-040's already-released decision. Owner is not deciding on GAP-041,
GAP-042, or GAP-045 in this packet — all three are explicitly out of scope,
with their own separate governance lifecycles. No production code, no Gate
2, no Gate 3, no merge is authorized by this document.
