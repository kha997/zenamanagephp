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
  pr: "https://github.com/kha997/zenamanagephp/pull/283"
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

## Owner Gate 1 review round 1 (2026-08-22): CHANGES REQUIRED — not an approval

Owner reviewed PR #283 at head `98a2706d2de9df71b64ee292261fc8b716cb5eca` and
returned **DECISION: CHANGES REQUIRED** (not an approval; `gate_status`
remained `awaiting_owner` throughout). The primary transaction-desynchronization
finding was accepted as factually supported (genuine MySQL evidence truthful;
`ensureInteractionLogsTable()` directly observed implicit-committing the
`RefreshDatabase` transaction; `PDO::inTransaction()` flips `true`→`false`;
Laravel `transactionLevel()` remains `1`; physical `CONNECTION_ID` unchanged;
the `SAVEPOINT trans2` rollback failure is real). Two material evidence gaps
were identified and required resolution before resubmission:

1. **GAP-040 acceptance-proof reconciliation** — the three implicated sibling
   helpers already existed at the GAP-040 baseline; a concrete second
   false-green hypothesis in GAP-040's own v2 rollback proof needed to be
   tested via a disposable, genuine-MySQL discriminating harness, not
   inferred from code alone. **Resolved this revision** — see evidence
   document §H1: a disposable writer/verifier harness confirmed the
   hypothesis. GAP-040's cold-start rollback proof is now assessed as
   **false-green** for the same reason GAP-044 itself exists. GAP-040's
   historical decision record is preserved unedited; no GAP-040 file is
   touched by this PR; this Gate 1 does not claim GAP-040's technical
   acceptance contract remains satisfied.
2. **Capture the masked original exception** — attempted this revision via
   disposable instrumentation (two independent approaches, including a
   faithful call to the real, unmodified `TenantUserFactoryTrait`). **Not
   reproduced** — reported honestly as unresolved in evidence document §I1,
   per Owner instruction not to assume it is expected Eloquent
   `createOrFirst()` race handling.

This document and the companion evidence document have been amended
accordingly. `gate_status` remains `awaiting_owner`; no Owner approval is
recorded by this revision.

## Owner Gate 1 review round 2 (2026-08-22): CHANGES REQUIRED — item A accepted, item B now resolved — not an approval

Owner reviewed PR #283 at head `c46223a7d9e23e038b3c672a553003107c75c470`
and returned **DECISION: CHANGES REQUIRED** (correction round 2; not an
approval; `gate_status` remained `awaiting_owner` throughout). **Item A
(GAP-040 false-green, §H1) was accepted outright** — Owner directed not to
redo it or touch any GAP-040 file, which this revision honors. The only
remaining Gate-1 blocker was **item B: identify the original masked
`Throwable`**, this time by faithfully replicating the *authoritative*
failing run (`32557247386`, job `96993284455` — genuine MySQL 8.0,
`migrate` **and** `db:seed --env=testing --force`, truthful
`--group=performance --fail-on-empty-test-suite` selector) rather than the
simplified/substitute environment (`routes-guardrails.yml`, which lacks
`db:seed`) the first two attempts used.

**Resolved this revision.** A third, exact-match disposable harness
(`investigate/GAP-044-exact-match-harness`, GitHub Actions run
`32562591732`, deleted after capture) reused the real
`automated-testing.yml` `performance-tests` job unmodified except for two
purely-additive, never-committed diagnostics: a runtime patch to the
*installed* (gitignored) vendor copy of `ManagesTransactions.php` logging
the original `Throwable` immediately before Laravel's own rollback call,
and a disposable query-listener ring buffer in `tests/TestCase.php`. Both
were deleted with the branch; neither changed control flow or altered the
real `SAVEPOINT trans2 does not exist` symptom, which still occurred
identically. **Reproduced on the first attempt, identically on both
Performance test files:** the original exception is
`Illuminate\Database\UniqueConstraintViolationException` (SQLSTATE `23000`,
MySQL error `1062`, duplicate entry `'project.read'` for key
`permissions.permissions_code_unique`) — a genuine, independent seeding/
lookup-key mismatch between `TenantUserFactoryTrait`'s lookup-by-`name` and
a pre-existing seeded `permissions` row whose `code` already equals
`'project.read'`. Classified **B — a test-data/seeding defect**, not
expected Eloquent race handling; full causal chain, evidence, and remaining
narrow uncertainty (why the seeded row's `name` doesn't match its `code`,
given `PermissionSeeder`'s own code sets them equal) recorded in evidence
document §I1. This finding is documented and characterized, not fixed, not
absorbed into GAP-044's implementation scope.

This document and the companion evidence document have been amended
accordingly. `gate_status` remains `awaiting_owner`; no Owner approval is
recorded by this revision.

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
More specifically: **the specific regression evidence GAP-040's own Gate 3
relied on to prove that closure (its writer/verifier cold-start rollback
proof) is now confirmed, by direct experiment, to be false-green** — the
marker-row disappearance it reports is explained by a `migrate:fresh`
schema wipe triggered by this same defect, not by genuine `RefreshDatabase`
rollback (see evidence doc §H1). GAP-041 (currently `blocked_technical`)
remains blocked pending GAP-044 (and GAP-045) resolution before it can
present a fully-green LIVE Gate 3. Any test author relying on
`Tests\TestCase`'s `RefreshDatabase` guarantee on real MySQL inherits the
same silent exposure on the first test of a fresh process.

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
`FixtureFactory`/`TenantUserFactoryTrait` consumers; a second disposable
writer/verifier discriminating harness (GitHub Actions runs `32560499974`/
`32560820613`, throwaway Draft PR #284, closed unmerged) that directly tested
and confirmed the GAP-040 false-green hypothesis; a third, exact-match
disposable harness (GitHub Actions run `32562591732`, deleted after capture)
that faithfully replicated the authoritative failing run's full pipeline
(including its `db:seed` step) and, via runtime-only never-committed vendor
instrumentation, captured the original masked exception —
`UniqueConstraintViolationException`, SQLSTATE 23000/MySQL 1062, duplicate
`code='project.read'` — identically on both Performance test files.

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

Gate 1 confirms: (1) the root-cause mechanism is established via live,
server-observed MySQL evidence, not inference — `tests/TestCase.php`'s three
unguarded sibling methods (`ensureInteractionLogsTable`/`ensureProjectPhasesTable`/`ensureProjectTasksTable`)
implicit-commit `RefreshDatabase`'s transaction on the first cold-start test,
exactly mirroring GAP-040's already-diagnosed-and-fixed defect pattern for a
different method in the same file; (2) the defect is genuinely test-only —
no application/schema/RBAC/tenant/business semantics are implicated; (3) a
second, directly-tested finding: GAP-040's own Gate-3 cold-start rollback
proof is false-green for the same reason, confirmed via a dedicated
disposable writer/verifier harness on genuine MySQL — this is reported as
evidence for Owner consideration, not as a reclassification of GAP-040's
released status, and no GAP-040 file is touched; (4) the originating
exception has now been identified, via a faithful exact-match reproduction
of the authoritative failing run: `UniqueConstraintViolationException`
(SQLSTATE 23000, MySQL 1062) from a genuine `permissions` table
duplicate-`code` collision on `'project.read'`, whose root is a
seeding/lookup-key mismatch (classification B — test-data/seeding defect,
not expected Eloquent race handling) that GAP-044's transaction mechanism
then masks and whose Eloquent's own built-in graceful recovery it defeats;
this finding is documented, not fixed, not absorbed into GAP-044's scope;
(5) a Gate 2 design decision is needed on the specific remediation
mechanism for the transaction-desynchronization root cause (e.g. extending
GAP-040's isolated-`zena_ddl_bootstrap`-connection pattern to these three
siblings, or an equivalent complete solution) and on what regression
evidence would prove the invariant restored across whichever surfaces Gate
2 scopes, this time using a proof design immune to the false-green mode
identified in (3); the seeding/lookup mismatch (4) is a separate, Owner-
scoped decision if pursued, not automatically part of Gate 2's remediation.
Gate 1 does not select a technical mechanism for either.

## Loại trừ rõ ràng

Does not reopen or modify GAP-040's already-released decision record
(`docs/owner-decisions/GAP-040/*`) — §H/§H1 of the evidence document report
new evidence for Owner consideration only; GAP-040's historical decision
record is preserved unedited. Does not touch GAP-041 (only reused its
already-Owner-approved selector-overlay mechanism, unmerged, purely for
evidence-gathering — identical precedent to GAP-043's own disposable
harness), GAP-042 (RBAC production-fidelity, unrelated), or GAP-045 (latency
budget — the one `DashboardPerformanceTest` failure attributable to GAP-045
is confirmed structurally distinct from this SAVEPOINT mechanism and is not
investigated here). No test, application, migration, or workflow code is
changed by this Gate 1 submission — this document and its companion
evidence file are the only contents of this PR (plus three disposable
evidence-harness branches used across this investigation, all already
deleted/closed unmerged, never presented as implementation — including
runtime-only vendor instrumentation that patched only a `composer
install`-generated, gitignored `vendor/` copy in a disposable CI job and
was never committed to any branch). No business-domain semantics, tenant
behavior, or production schema are proposed to change; per the governance
classification in the evidence document, this does not require the Design
Dependency Preflight unless Gate 2 investigation later shows otherwise, in
which case work stops and that preflight runs before any design/plan/code.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt để xác nhận phạm vi/bằng chứng Gate 1
— root cause đã được xác lập bằng bằng chứng LIVE trên MySQL thật (không chỉ
suy luận), là cùng một loại lỗi GAP-040 đã sửa cho một method khác trong
cùng file, nhưng chưa được sửa cho 3 method anh em của nó; đồng thời xác
nhận phát hiện mới (đã kiểm chứng bằng thực nghiệm, không suy luận):
bằng chứng rollback Gate-3 của chính GAP-040 là false-green vì cùng lý do
này — báo cáo để Owner xem xét, không tự ý thay đổi trạng thái đã release
của GAP-040; và exception gốc bị che khuất đã được xác định đầy đủ
(`UniqueConstraintViolationException`, xung đột dữ liệu seed thật trên
`permissions.code='project.read'`) — một lỗi test-data/seeding riêng biệt
(loại B), được ghi nhận và phân loại, không sửa, không mở rộng phạm vi
GAP-044. Không chọn cơ chế kỹ thuật cụ thể ở Gate 1.

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code change, workflow file change,
or specific technical remediation mechanism at this step — only to confirm
the root-cause evidence and problem scope are sound and worth a Gate 2
design (if authorized). Owner is not being asked to reopen or modify
GAP-040's already-released decision. Owner is not deciding on GAP-041,
GAP-042, or GAP-045 in this packet — all three are explicitly out of scope,
with their own separate governance lifecycles. No production code, no Gate
2, no Gate 3, no merge is authorized by this document.
