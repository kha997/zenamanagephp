# UAT-RECOVERY-01 — Owner Decision Register (canonical)

Single index of Owner decision IDs for the UAT-RECOVERY-01 program (S1–S4d). Entries cite the report that holds the evidence. Reports and evidence files are **never renumbered or overwritten**; corrections are made here as errata. Decisions are recorded as given by the Owner in chat; nothing here is agent-approved.

| ID | Subject | Status | Evidence |
|---|---|---|---|
| O-1 | Canonical role pivot (`user_roles`; ledger projection) | Applied; deviation acknowledged ("Xác nhận") | 10-recovery-02 report |
| O-2 | Legacy `users.role` / `admin`⇄System Admin aliasing | Open (parameterless-gate work deferred) | 00 §11, 20 report |
| O-3 | Project data visibility (tenant-visible reads kept) | Applied as proposed (no change) | 00 §11 |
| O-4 | Task update/complete authority | Applied via S4 (refined by O-10) | 30 report |
| O-5 | Permission grants for the 34 + related | Largely overtaken by merged upstream GAP-098 (see O-19); remaining alias drift + Member grants stay OWNER_DECISION_REQUIRED | 10 report |
| O-6 | Design-discipline roles | Deferred | 00 §11 |
| O-7 | System Admin exception (no bypass; only `role.assign`) | Applied | 10 report |
| O-8 | Parameterless `rbac` gate meaning | Unchanged; per-route-group mapping only (O-8′) | 20 report |
| O-9 | Amend pinned route assertions for S3b | Approved + applied | 20 report §N |
| O-10 | "Full task authority" derivation (no inference from `task.delete`) | Approved + applied | 40 report |
| O-11 | `pm_id` persistence (bounded) | Approved + applied | 40 report |
| O-12 | Task lifecycle / completion rules | Deferred (design only) | 40, 50 reports |
| O-13 | Same boundary on every task mutation | Approved + applied | 40 report |
| O-14 | Administrative "manage any task" capability; fixture option A (S4) | Fixture option A approved; admin capability still open | 30, 40 reports |
| O-15 | PM appointment: independent authority (A-minimal) | Approved ("Duyệt A-minimal") + applied (S4d) | 50 §G, 60 report |
| **O-16** | **Recipients of `project.assign_manager`: System Admin only (Option 1)** | **Approved + applied** | 60 report (O-16 section) |
| **O-16a** | **Self-appointment: System Admin (and any actor) may NOT appoint itself as PM; may appoint another eligible same-tenant user** | **Approved (Owner final decision) — enforced in `ProjectPmAssignment::allows()`; tests unchanged** | 60 report, `UatRecovery04dPmAssignmentTest` |
| **O-17** | **Task creation authority (was: any `task.create`/`template.apply` holder in any tenant project)** | **DECIDED 2026-11-24 — Option B ("APPROVED_FOR_BOUNDED_LOCAL_IMPLEMENTATION"); local patch O17_LOCAL_PATCH_VERIFIED; commit and push authorized under the pre-push gate (O-17i decided there); open: O-17h, O-17j, O-17k** | 50 report §E; `98-o17-task-creation-decision-package.md`; `99-o17-implementation-report.md` |
| **CB-3** | **Bulk task creation trusted request `tenant_id` and an unvalidated `project_id` (cross-tenant write, no audit)** | **Fix approved with O-17 (O-17e); fixed in the local patch, uncommitted** | 98 §2; 99 §4 |
| **CB-4** | **Role named `super_admin` passed every `rbac:` gate with zero permissions; creation routes without in-controller checks created tasks** | **Fixed for every task-creation entry point (local patch, uncommitted); middleware itself unchanged (O-17h open)** | 98 §2; 99 §4 |
| **O-18** | **Seeder-order non-determinism: `DatabaseSeeder` grants Project Member 11 `*.view` codes only from the 2nd run on (15 → 26); first-seed (fresh install) state differs from twice-seeded environments** | **DECIDED 2026-11-24 — Option D (26-code deterministic Member set) + Project Manager included; IMPLEMENTED in commit `103bc5c19` (PR #422, Draft), CI 35/35 green** (`97-o18-implementation-report.md`); still open: existing environments not converged | 70 integration-precheck report §A; `80-o18-permission-impact-matrix.md`; `95-o18-decision-package.md` (corrected seed counts on this base: 20 → 31) |
| **O-19** | **S1 catalog superseded by upstream GAP-098/GAP-079/GAP-030 during integration:** S1's catalog-only set and `role.assign` grant were NOT carried into the PR (zero permission delta vs `main`); 5 falsified S1/CHAR assertions retired | **Technical reconciliation recorded for Owner review** (no grant/revoke) | `85-retired-assertions.md`, PR description |

## Erratum — O-16 identifier collision
- The 50-recovery-04c report (§D, §M) used **O-16** for the task-creation authorization question (created by any `task.create`/`template.apply` holder in any project of the tenant). Later, the Owner issued decision **"O-16" = grant `project.assign_manager` to System Admin only**, implemented in S4d (code comments, tests, 60 report).
- Resolution (no renumbering of established evidence): the **grant decision keeps O-16** (it is the identifier the Owner issued and that code/tests/report already cite). The **task-creation issue is re-identified as O-17**. Wherever the 50 report says "O-16" about task creation, read **O-17**. The 50 report is intentionally left byte-identical.
- O-17 remains open; no runtime change exists for it. `task.create` enforcement and tenant-scoped project binding (S4c CB-1/CB-2) are unaffected.
- *(Superseded 2026-11-24: O-17 decided — see "Decision record — O-17" below.)*

## Decision record — O-16a (2026-11-21)
Owner: "KEEP SYSTEM ADMIN SELF-APPOINTMENT PROHIBITED." (1) System Admin may appoint another eligible user as PM within the authorised tenant; (2) System Admin must not appoint itself; (3) existing O-15/O-16 security tests are preserved unmodified; (4) no escalation through `users.role`, `project.update` or an administrator bypass.
Implementation status: already in the working tree (self-appointment guard in `app/Support/ProjectPmAssignment.php::allows()`); verified by `UatRecovery04dPmAssignmentTest::test_O16_a_holder_cannot_self_appoint_as_pm_but_can_appoint_someone_else` plus the unchanged 04b/04c tests (`test_pm_authority_cannot_be_taken_by_editing_pm_id`, `test_O15_system_admin_cannot_reacquire_task_authority_by_claiming_unmanaged_projects`, `test_a_system_admin_cannot_self_claim_an_unassigned_project`).
## Decision record — O-17 (2026-11-24)
Owner, in chat: "DECISION: APPROVED_FOR_BOUNDED_LOCAL_IMPLEMENTATION" for O-17 Option B together with CB-3/CB-4. Contract: task creation requires (1) the same tenant as the project, (2) canonical `task.create` (template application: also canonical `template.apply`) through real RBAC grants, and (3) the project's current PM (`pm_id`) or an active `project_user_roles` participant. "No implicit System Admin or super_admin bypass." "No authority derived only from users.role." O-17g: keep the System Admin membership API; PM-managed participation deferred to S5. Limits: no commit/push/merge/deploy, no grants/revocations, no migration, no middleware rewrite, no O-18 or split_percent change.

Implementation status: local patch, uncommitted, verified on SQLite and isolated MariaDB 10.4 (`99-o17-implementation-report.md`); MySQL 8 not verified. Sub-decisions still requiring the Owner: O-17h (middleware `super_admin` short-circuit for non-creation routes), O-17i (relationship rule for change-request apply), O-17j (`task.assign` for an assignee named at creation), O-17k (project creators must name themselves PM or be added as participants), and the O-17d template-assignee deviation (99 §9).

**Pre-push gate (2026-11-24).** Owner, in chat: "O17_APPROVED_FOR_CONDITIONAL_COMMIT_AND_PUSH".
- **O-17i decided.** Change-request apply creates a task, so the actor must hold `change-request.apply` and `task.create`, belong to the project's tenant, and be the project's PM or an active participant.
- **RED.** The gate's case (a non-participant holding `change-request.apply`, on another project in the same tenant) was allowed by the pre-gate patch.
- **Fix.** The full contract is now enforced through `TaskCreationAuthority`, with no new exception.
- **Template-assignee 422 confirmed.** The Owner listed "Template assignee outside project: 422" as approved behaviour.
- **Still not approved for this commit:** O-17h, O-17j and O-17k.
- See 99 §11.

## Decision record — O-18 (2026-11-24)
Owner, in chat: "Option D (26 codes)". The decision is the one proposed in `95-o18-decision-package.md` §5.

**The approved Project Member set** has 26 codes and must be the same on every seed run:
- The 20 codes a first seed grants today.
- Plus `task.view`, `site_diary.view`, `design-item.view`, `material.read`, `material-receipt.view` and `material.view`.

**Withheld from Project Member:**
- `crm.view`, `contract.view`, `boq.view` and `vendor.view`, which are commercially sensitive.
- `event-record.view`, until the activity feed is filtered by module permission.

**Status (as first recorded, superseded below):**
- Decision recorded only. No seeder, permission, grant/revoke, seed-order or test change had been made.
- Implementation was to meet implementation criteria I-1..I-5 and regression criteria R-1..R-8 in `95-o18-decision-package.md` §8, under its own Owner authorization.

**Status update (2026-11-24):**
- The Owner authorized local implementation ("Implement O-18 Option D locally") and then "Commit and push".
- Implemented in commit `103bc5c19` on `integration/uat-recovery-s1-s4d` (PR #422, still Draft). Seed order is unchanged; the six codes are granted by `ZenaProjectManagerRolePermissionSeeder`.
- CI on `103bc5c19`: 35 of 35 checks pass.
- Not merged, not deployed. No staging or production permissions were read or changed.
- PR #422 stays MERGE_NO_GO for the open items below and the other PR merge blockers.

**P-1 decided (2026-11-24).** Owner: "YES — INCLUDE PROJECT MANAGER". Project Manager gets the same six codes and keeps its other grants. The patch implementing this was verified locally the same day and committed as `103bc5c19`; see `97-o18-implementation-report.md`.

Following the Owner's instruction, re-seeding is now additive: grants an administrator added by hand are never deleted.

**Still open within O-18:**
- The convergence approach for existing environments (§7). Re-running the seeder or `production:rbac` does **not** remove the five withheld codes from an environment that already holds them.
  - Production is likely in that state, because `production:rbac` granted them to Members on its first run before this patch.
- Read-only access to the production grant inventory.
- Whether S-1 and S-2 get their own Work IDs.

**Effect on existing environments:**
- Environments seeded more than once currently hold 31 codes. They keep them until an approved convergence step revokes the 5 withheld codes, with a dry run and audit.
- Nothing is revoked automatically.

## Integration note (PR)
Numbered reports 00–70 were produced on local base `a0381f5e3`. The PR was re-based onto `main` `cb958ad48` (83 newer commits) and re-verified there; see `90-integration-verification.md`. Raw UAT drivers (`docs/uat/`) are intentionally not part of the PR; the original files remain in the source worktree.

## Location note
This register and the reports live under `docs/audits/UAT-RECOVERY-01/` on purpose: files under `docs/owner-decisions/**` are governed Gate packets (YAML frontmatter + a canonical `GAP-nnn`/`OWN-yyyy-nnn` Work ID, enforced by `owner-governance-lint`). Allocating a Work ID and converting these into governed packets is an Owner governance act and is **not** done in this Draft PR (see PR merge blockers).
