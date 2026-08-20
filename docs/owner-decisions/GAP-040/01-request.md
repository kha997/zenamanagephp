---
work_id: GAP-040
gate: 1
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md
  plan: null
  branch: docs/GAP-040-gate1-prep
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-20T00:00:00+07:00"
  updated_at: "2026-08-20T00:00:00+07:00"
generated_by: agent
---

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` runs 8 DDL statements (4 `DROP TABLE IF EXISTS` + 4 `CREATE TABLE`) unconditionally in every single test's `setUp()`, on every database driver — no driver guard (unlike its sibling `ensureSqliteDocumentsBackupTable()`) and no existence guard (unlike its sibling `ensureSqliteSubmittalsTable()`). Because `database/migrations/2025_09_19_174648_rename_zena_tables_to_standard_names.php` renames these tables away during every fresh migration, the `CREATE TABLE` calls are not a rare fallback — they genuinely execute every test, always.

For test classes that use Laravel's `RefreshDatabase` trait, this DDL runs *after* `RefreshDatabase` has already opened a database transaction meant to isolate that test's writes. On MySQL, `CREATE TABLE`/`DROP TABLE` are documented to cause an implicit `COMMIT`. If that holds here, the isolating transaction is silently committed away mid-`setUp()`, and the rollback registered for teardown has nothing left to undo — the test's writes are not cleaned up, and can leak into the next test sharing that MySQL connection.

## Vấn đề vận hành

Verified by direct code trace (not the register's wording copied uncritically — see evidence §4 for a correction to the register's own affected-surface list): only 3 CI script paths on `origin/main` genuinely execute PHPUnit against real MySQL (`scripts/ci/zena-invariants-mysql`, `rfi-escalation-concurrency-mysql`, `document-workflow-concurrency-mysql` — confirmed via `ZENA_INVARIANTS_DB=mysql` export, the only variable that lets a job's `DB_CONNECTION=mysql` survive `tests/bootstrap.php`'s otherwise-unconditional override to SQLite). Of these, only `zena-invariants-mysql` runs `RefreshDatabase`-using tests: 12 of the 17 test classes under `--group=zena-invariants` `use RefreshDatabase` and are therefore exposed to this defect's specific failure mode. The register's GAP-040 row additionally names `routes-guardrails.yml`'s parity step, `performance-tests`, and `e2e-tests` as affected; cross-checked against GAP-039's own definitive Gate-1 CI inventory, all three resolve to SQLite in actual execution (no `ZENA_INVARIANTS_DB=mysql` set anywhere in those jobs) and are **not** affected by this specific mechanism.

CI is currently green — no test currently depends on execution order in a way that has surfaced this defect as a visible failure. This is a structural defect with a well-documented (MySQL implicit-commit-on-DDL) but not-live-reproduced-in-this-environment runtime consequence (a local MariaDB reproduction attempt failed on a filesystem permission error unrelated to the code; see evidence §5), not an observed production incident.

## Người dùng bị ảnh hưởng

Engineering team relying on `zena-invariants-mysql`'s green CI as evidence that its 12 `RefreshDatabase`-using RBAC/audit/seed invariant tests are validated against isolated, production-representative MySQL state per test — that isolation guarantee does not actually hold as currently implemented. Anyone extending that test group with a new `RefreshDatabase`-using test inherits the same silent isolation gap. No end-user-facing production behavior is affected; this is testing-infrastructure integrity only.

## Bằng chứng

Full evidence — code trace, framework-source trace, live reproduction of the `tests/bootstrap.php` env-override mechanism, and the correction to the register's affected-surface claim — is recorded in `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md`. No application or test code was modified to produce this evidence.

## Tác động nếu không xử lý

The 12 `RefreshDatabase`-using tests in `--group=zena-invariants` continue to run under `zena-invariants-mysql` without the per-test isolation their use of `RefreshDatabase` implies. State written by one test could leak into the next test sharing the connection, producing order-dependent results or false greens that do not reliably validate the RBAC/tenant/audit invariants those tests are named for. The risk grows as more `RefreshDatabase`-using tests are added to that group, or as the same unguarded helper pattern gets copied elsewhere.

## Phạm vi đề xuất

Gate 1 confirms only: (1) the structural defect is real and directly traceable in code (§1-§3 of the evidence); (2) the actually-affected surface is narrower than the register's current wording (§4) and should be corrected there at Gate 2 alongside the fix; (3) a Gate 2 design decision is needed on the specific guard mechanism (driver guard like the sibling helper, existence guard like the other sibling, or both) and on what regression evidence would prove isolation is restored. Gate 1 does not select a technical mechanism — that is Gate 2's decision.

## Loại trừ rõ ràng

Does not reopen or modify GAP-039 (released, PR #268, merged `87a4307f`). No workflow file, `tests/bootstrap.php`, PHPUnit config, test code, or production/application code is changed by this Gate 1 submission — this document and its companion evidence file are the only contents of this PR. No business-domain semantics, tenant behavior, or production schema are proposed to change; per the governance classification below, this does not require the CRM/Project/Finance/OPPM Design Dependency Preflight unless Gate 2 investigation later shows otherwise (in which case work stops and that preflight runs before any design/plan/code).

## Governance classification

GAP-040 is a test infrastructure / database transaction-isolation integrity defect. It does not, on its own, require the Design Dependency Preflight, because no business-domain semantics are being changed — the `zena_*` RBAC tables involved are legacy pre-rename artifacts of a test-only helper, not the live `roles`/`permissions` tables the application actually uses. If Gate 2 design work reveals that a fix would require changing canonical business semantics, tenant behavior, production schema, or application-domain behavior, work stops and the appropriate preflight runs first. Tenant isolation and data integrity remain mandatory technical constraints that cannot be waived by Owner.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt để tiến hành thiết kế Gate 2 — thu hẹp `ensureSqliteZenaRbacTables()` để không còn chạy DDL vô điều kiện trên kết nối MySQL thật đang có transaction mở (driver-specific và/hoặc existence-guarded, theo đúng pattern của 2 hàm chị em), kèm regression evidence chứng minh transaction isolation được khôi phục trên `zena-invariants-mysql`. Đồng thời đề xuất Gate 2 sửa lại dòng affected-surface trong `OPERATIONAL_GAP_REGISTER.md`'s GAP-040 cho khớp với bằng chứng đã xác minh ở đây.

## What the owner is NOT being asked to decide

Owner is not being asked to approve any code change, workflow file change, or specific technical guard mechanism at this step — only to confirm the problem as evidenced is real and worth a Gate 2 design. Owner is not deciding on GAP-039 (already released) or any other gap. No production code, no Gate 3, no merge is authorized by this document.
