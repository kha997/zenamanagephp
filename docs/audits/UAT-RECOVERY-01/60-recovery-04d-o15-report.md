# UAT Recovery 04d — O-15 "A-minimal" (project manager appointment)

Status: **LOCAL_PATCH_VERIFIED** (incl. O-16 grant below) (SQLite/PHPUnit only). No commit, push, deploy or data change. MySQL NOT_VERIFIED. Production remains NO_GO.
Owner approval: "Duyệt A-minimal" (design in 50-recovery-04c §G/§L).

## Rule implemented
- New permission `project.assign_manager` was first seeded as a definition only (granted to no role); **superseded by O-16 below: now granted to the canonical System Admin role only.**
- Changing `projects.pm_id` (appoint / replace / clear) requires `project.update` AND (being the current PM, or holding `project.assign_manager`). Tenant-bound; no System Admin bypass; PM-less project can only get a PM from a `project.assign_manager` holder.
- Project creation: the creator may still name the first PM (same-tenant user only).
- Every change writes audit event `project.manager.changed` (before/after pm_id, actor, source) in the same transaction (API update, create, bulk update).
- Web edit form shows the PM selector only to actors allowed to change it.
- `projects:pm-less-report [--tenant=]` is read-only; no migration, no auto-backfill.

## Files
`app/Support/ProjectPmAssignment.php`, `database/seeders/ZenaPermissionsSeeder.php`, `Api/ProjectController`, `Web/ProjectController`, `ProjectService`, `BulkOperationsService`, `resources/views/projects/edit.blade.php`, `app/Console/Commands/ProjectsPmLessReport.php`.
Bug found by the new tests and fixed: web `edit()` passes `Src\CoreProject\Models\Project`; helper now typed against Eloquent `Model`.

## Tests
- New: `tests/Feature/Recovery/UatRecovery04dPmAssignmentTest.php` — 11/11 pass (evidence `s4d-AFTER.txt`).
- Four tests that pinned the old claim path were deliberately updated to assert denial (no assertion weakened): the 3 O-15 RED tests in `UatRecovery04cO15GateTest` and `test_O15_PROPOSED_…` in `UatRecovery04cSurfaceAndPmTest`; they are now GREEN.
- Fixture grants of `project.assign_manager` exist only inside the S4d test helper `assigner()`.

## Regression vs R2 baseline (by test identity)
| Suite | Baseline | Final |
|---|---|---|
| Feature (Backup x4 excluded) | 1846 tests, 22 F + 1 E | 1857 tests, 18 F + 1 E |
| tests/Feature/Api | 798 OK | 798 OK |

NEW_REGRESSION = **0**. FIXED = 4 (the former intentional O-15 RED). Remaining 19 = 4 Recovery-01 RED + 15 pre-existing (DashboardApiTest 7, GAP059 7, BadgeApiRetired 1), unchanged. Evidence: `s4d-final-full-feature.txt`, `s4d-final-api.txt`.
Backup classes (BackupCommandTest, BackupContentsSafetyTest, BackupRetentionTest, BackupStorageLocationTest): **NOT_EXECUTED** (real `storage/backups`, low disk).

## Remaining Owner decisions
1. ~~Which role(s) receive `project.assign_manager`~~ — resolved by O-16 (System Admin only).
2. Adoption procedure for existing PM-less projects (use the report command; no auto-backfill).
3. O-12 lifecycle, O-14, O-17 (task creation not limited to owning PM; formerly mislabelled O-16 in the 50 report — see OWNER-DECISION-REGISTER.md), 12 permission aliases, 10 Member grants, parameterless `rbac` gate (O-8).
4. Backup test fixture isolation (`useStoragePath($tmp)`), MySQL verification.

## Decision
LOCAL_PATCH_VERIFIED for S4d. Not production-ready.


---

# O-16 — Owner approval and implementation (OPTION 1: System Admin only)

Owner decision (recorded verbatim in intent): grant `project.assign_manager` exclusively to the canonical System Admin role. (ID collision resolved in `OWNER-DECISION-REGISTER.md`: the grant keeps **O-16**; the task-creation issue previously called O-16 in the 50 report is now **O-17**, still open.)

## Exact permission grant
- `database/seeders/ZenaPermissionsSeeder.php`: `project.assign_manager` moved from `CATALOG_ONLY_PERMISSIONS` to `SYSTEM_ADMIN_ONLY_PERMISSIONS`. It is therefore attached by `grantSystemAdminOnlyPermissions()` (`syncWithoutDetaching`) to the role with `name='System Admin' AND scope='system' AND tenant_id IS NULL` only, and by PermissionSeeder's existing admin "sync all" (same role). Nothing else changed in the seeders (PM 42 / Member 15 code lists untouched).
- Verified identity (test): exactly 1 role row holds the permission → `System Admin`, scope `system`, `tenant_id NULL`; exactly one `System Admin` role exists; Project Manager / Project Member / legacy `users.role='admin'` hold nothing.

## One additional safeguard (please confirm)
`ProjectPmAssignment::allows()` now refuses **self-appointment**: an actor may not set themselves as PM of a project they do not already manage (O-15 "no self-claim" preserved; O-16 rule 3). Without it a System Admin could appoint itself and regain task authority (TaskPolicy derives it from `pm_id`), which would silently void 3 existing tests (04b `test_pm_authority_cannot_be_taken_by_editing_pm_id`, 04c `test_O15_system_admin_cannot_reacquire_task_authority…`, 04c O15 gate admin self-claim). Those tests are **unchanged and still pass**. Consequence: the System Admin appoints *another* same-tenant user; it cannot make itself PM. If the Owner wants admin self-appointment allowed, that is a one-line change plus a deliberate test change.

## RED / GREEN
- RED (before the grant, `evidence/s4d-O16-RED.txt`): new tests for exact-role grant, idempotency and System Admin appointment failed (3 ✘; the denial guard stayed ✔).
- GREEN (`evidence/s4d-O16-GREEN.txt`): `UatRecovery04dPmAssignmentTest` 16/16 (78 assertions). Covers: exact role identity; System Admin appoints PM on PM-less project (API and bulk) with audit row (actor, tenant, project, old/new pm_id, timestamp); self-appointment denied; cross-tenant (project, appointee, foreign admin) denied; PM and Member denied; permission alone without `project.update` denied; idempotent seeding.
- Existing 04d test that treated System Admin as a non-holder was corrected (System Admin removed from the non-holder list; bulk denial now uses an explicit fixture holding only `auth.bulk.manage`+`project.update`). No assertion weakened.

## Regression (by test identity)
| Suite | R2 baseline | After S4d | After O-16 |
|---|---|---|---|
| Feature (Backup x4 excluded) | 1846 tests, 22F+1E | 1857, 18F+1E | 1862, 18F+1E |
| tests/Feature/Api | 798 OK | 798 OK | 798 OK |
NEW_REGRESSION = **0**. Remaining 19 = 4 Recovery-01 RED + 15 pre-existing (DashboardApiTest 7, GAP059 7, BadgeApiRetired 1). Evidence: `s4d-o16-full-feature.txt`, `s4d-o16-api.txt`, `s4d-O16-recovery.txt`. Backup classes NOT_EXECUTED (real `storage/backups`).

## Security assessment
- Authority = `project.update` ∧ tenant match ∧ (current PM ∨ `project.assign_manager`); no `users.role` read, no super-admin bypass, no self-appointment. PM/Member cannot use it. Cross-tenant denied in both directions. Change + audit remain atomic (API update in a transaction; audit also on create and bulk).
- Residual: a System Admin is still a single point of authority for PM-less projects; there is no break-glass if the only admin is unavailable (adopt via a second admin).
- Finding (pre-existing, NOT changed, out of scope): re-running `DatabaseSeeder` grows Project Member grants 15 → 26 (adds `task.view`, `contract.view`, `crm.view`, … from other seeders). Unrelated to `project.assign_manager`; needs its own Owner decision (relates to the "10 Member grants" item).

## Remaining blockers
1. Confirm self-appointment ban (above). 2. Procedure to adopt existing PM-less projects (use `projects:pm-less-report`, then admin appoints; no auto-backfill). 3. O-12 lifecycle, O-14, task-creation-by-owning-PM item, 12 aliases, Member grants + seeder re-run growth, O-8 parameterless gate. 4. Recovery-01 RED (4) and 15 pre-existing failures. 5. Backup test fixture isolation; MySQL NOT_VERIFIED; production NO_GO.

## Updated S4d decision
**LOCAL_PATCH_VERIFIED** (O-16 applied). Not production-ready.

## Recommended smallest controlled Integration Checkpoint (S1–S4d)
Do not merge on the current dirty tree. (1) Branch from `main` (`a0381f5e3`) in a **fresh worktree**; commit S1–S4d as one logical, reviewable series in this order: S1/S2 (seeder, pivot, backfill command) → S3/S3b (DocumentPolicy, routes) → S4/S4b/S4c (task policy/surfaces) → S4d (PM assignment + O-16) plus the `tests/Feature/Recovery` suite and evidence docs; exclude `docs/uat/` raw run output unless the Owner wants it. (2) Open a **Draft PR** under governance (Gate 1/2 packets per repo rules) rather than merging. (3) On that branch run CI on MySQL (closes "MySQL NOT_VERIFIED") and the Backup classes against an isolated `useStoragePath($tmp)` fixture. (4) Dry-run `rbac:backfill-user-roles` and `projects:pm-less-report` against a **copy** of production data (read-only) to size the PM-less project backlog before any production step. Staging/production backfill and deploy stay blocked until the Owner approves each.


## O-16a — Owner final decision: self-appointment stays prohibited (2026-11-21)
Confirmed by the Owner: System Admin may appoint another eligible same-tenant user as PM but must not appoint itself; no escalation via `users.role`, `project.update` or an admin bypass; existing O-15/O-16 tests preserved unmodified. This matches the behaviour already implemented and verified above (the one-line guard in `ProjectPmAssignment::allows()`); **no code or test change was needed for this decision**. Recorded in `OWNER-DECISION-REGISTER.md` (O-16a). "Remaining blockers" item 1 ("Confirm self-appointment ban") is therefore **resolved**.
