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
| **O-17** | **Task creation authority limited to the owning PM / any `task.create`/`template.apply` holder in any tenant project** | **OPEN — OWNER_DECISION_REQUIRED** | 50 report §D |
| **O-18** | **Seeder-order non-determinism: `DatabaseSeeder` grants Project Member 11 `*.view` codes only from the 2nd run on (15 → 26); first-seed (fresh install) state differs from twice-seeded environments** | **OPEN — OWNER_DECISION_REQUIRED** (pre-existing at HEAD; not caused by S1–S4d; nothing changed) | 70 integration-precheck report §A; `80-o18-permission-impact-matrix.md` — **unresolved pre-merge security / deployment-readiness obligation**; not declared acceptable; needs a separate Owner Gate |
| **O-19** | **S1 catalog superseded by upstream GAP-098/GAP-079/GAP-030 during integration:** S1's catalog-only set and `role.assign` grant were NOT carried into the PR (zero permission delta vs `main`); 5 falsified S1/CHAR assertions retired | **Technical reconciliation recorded for Owner review** (no grant/revoke) | `85-retired-assertions.md`, PR description |

## Erratum — O-16 identifier collision
- The 50-recovery-04c report (§D, §M) used **O-16** for the task-creation authorization question (created by any `task.create`/`template.apply` holder in any project of the tenant). Later, the Owner issued decision **"O-16" = grant `project.assign_manager` to System Admin only**, implemented in S4d (code comments, tests, 60 report).
- Resolution (no renumbering of established evidence): the **grant decision keeps O-16** (it is the identifier the Owner issued and that code/tests/report already cite). The **task-creation issue is re-identified as O-17**. Wherever the 50 report says "O-16" about task creation, read **O-17**. The 50 report is intentionally left byte-identical.
- O-17 remains open; no runtime change exists for it. `task.create` enforcement and tenant-scoped project binding (S4c CB-1/CB-2) are unaffected.

## Decision record — O-16a (2026-11-21)
Owner: "KEEP SYSTEM ADMIN SELF-APPOINTMENT PROHIBITED." (1) System Admin may appoint another eligible user as PM within the authorised tenant; (2) System Admin must not appoint itself; (3) existing O-15/O-16 security tests are preserved unmodified; (4) no escalation through `users.role`, `project.update` or an administrator bypass.
Implementation status: already in the working tree (self-appointment guard in `app/Support/ProjectPmAssignment.php::allows()`); verified by `UatRecovery04dPmAssignmentTest::test_O16_a_holder_cannot_self_appoint_as_pm_but_can_appoint_someone_else` plus the unchanged 04b/04c tests (`test_pm_authority_cannot_be_taken_by_editing_pm_id`, `test_O15_system_admin_cannot_reacquire_task_authority_by_claiming_unmanaged_projects`, `test_a_system_admin_cannot_self_claim_an_unassigned_project`).


## Integration note (PR)
Numbered reports 00–70 were produced on local base `a0381f5e3`. The PR was re-based onto `main` `cb958ad48` (83 newer commits) and re-verified there; see `90-integration-verification.md`. Raw UAT drivers (`docs/uat/`) are intentionally not part of the PR; the original files remain in the source worktree.

## Location note
This register and the reports live under `docs/audits/UAT-RECOVERY-01/` on purpose: files under `docs/owner-decisions/**` are governed Gate packets (YAML frontmatter + a canonical `GAP-nnn`/`OWN-yyyy-nnn` Work ID, enforced by `owner-governance-lint`). Allocating a Work ID and converting these into governed packets is an Owner governance act and is **not** done in this Draft PR (see PR merge blockers).
