# O-19 reconciliation and PR #422 merge-blocker plan (2026-11-24, head `b4440a3d2`)

This is read-only analysis. No code, test or permission changes were made, and the file is not committed.

## Part 1 — O-19: the five retired assertions, checked against current GAP-098, GAP-079 and GAP-030 behaviour

**Fact base.** The seed snapshots are in `evidence/o18-seed-reproduction.txt`.
- `git diff cb958ad48..HEAD -- database/seeders` touches **one file**, `ZenaPermissionsSeeder.php`, and every change is additive (+75 lines):
  - the `project.assign_manager` definition and its grant to System Admin only (O-16);
  - `repairNullPermissionNames`.
- No other role's grants differ from `main`.

| # | Retired assertion | Why obsolete | Original security intent | Current coverage | Gap |
|---|---|---|---|---|---|
| 1 | `test_S1_catalog_is_complete_except_the_recorded_owner_decision_aliases` | The alias list was dropped. GAP-098 creates the codes | Every route `rbac:` code exists in the seed | `UatRecovery01…::test_D3_every_rbac_route_permission_exists_in_canonical_seed`, released from quarantine and green in CI. It matches on `name` and handles both middleware spellings. Upstream `RouteRbacCodesSeededTest` matches on `code`, `rbac:` alias, first code | **Covered** |
| 2 | `test_S1_owner_decision_aliases_were_not_created_as_duplicates` | No alias constant exists any more | Do not invent duplicate or synonym codes | Nothing asserts code or name uniqueness. A unique index on `permissions.code` was **not verified** in this pass | **G-1 (minor):** add an assertion that codes and names are unique, or confirm the DB constraint |
| 3 | `test_S1_catalog_only_permissions_exist_but_are_granted_to_no_role` | Falsified: GAP-098 grants these codes, under its own Owner gate | Administrative codes (`role.*`, `permission.*`, `role.assign`, `audit.view`) must not reach ordinary roles | Seed snapshot: neither PM nor Member holds any of them. `V1PermissionGapTest` covers only "Member cannot POST /api/v1/rbac/roles" | **G-2:** no negative pin for PM and Member on all admin codes |
| 4 | `test_S1_project_manager_and_member_gained_no_new_grants` (42/15) | Falsified: the counts are now PM 60 and Member 20 or 31, from GAP-098, GAP-030, GAP-015 and Treasury | Detect privilege drift in the PM and Member roles | **None.** This gap is exactly what let O-18 go unnoticed | **G-3:** covered by O-18 criteria R-1 and R-2 (`95-o18-decision-package.md` §8) |
| 5 | second assertion of `test_CHAR_project_manager_cannot_create_rfi_under_current_seed` (PM holds no `rfi.%`) | Falsified: GAP-030 deliberately grants PM `rfi.escalate`, `rfi.cancel` and `rfi.resolve-escalation`, and Member `rfi.resolve-escalation` | PM cannot create RFIs | The first assertion, that the RFI row count is unchanged, is **retained**. `rfi.create` is still not granted to PM. `RfiApiTest` covers resolve-escalation limits: an unrelated user is forbidden, and escalate alone does not resolve | **Covered** |

**Conclusions:**
- **No obsolete assertion should be restored.** Each one contradicts merged, Owner-governed behaviour.
- **No hidden authorization regression.** The PR's seeder delta is additive and limited to System Admin.
- **Missing replacement coverage:** G-1 (minor), G-2, and G-3, which is folded into O-18.
- **Items to note in review.** These are pre-existing and not caused by this PR:
  - PM holds `user.manage_roles` and `settings.security.update` (PermissionSeeder) and `user.view` (GAP-098);
  - Member holds `treasury.view`, `compensation.view` and `baseline.view` (Treasury S1 and GAP-098).
  - These are upstream decisions and are listed only so that O-19 is reviewed with the full role picture.

## Part 2 — prioritized merge-blocker plan

**P0. Security decisions that must exist before merge**
1. **O-18, the Member grant model.** The decision package is in `95-o18-decision-package.md`.
   - The PR does not change O-18 (zero delta), but the Owner classed it as a pre-merge obligation.
   - Required: the Owner chooses D, 1, 2 or 3. The implementation then goes in a **separate governed PR** (seeders, a convergence command, and tests R-1..R-8).
   - The Owner also decides whether PR #422 may merge before that PR or must wait for it.
2. **O-17, task-creation authority.** Today any holder of `task.create` or `template.apply` can create tasks in any project of the tenant.
   - Needed: either a decision ("owning PM, or an explicit permission, scoped by project") with its own slice, or an explicit acceptance as a risk.
3. **`TaskPolicy::view()`, a latent defect.**
   - It authorizes using legacy role names (`super_admin`, `admin`, `project_manager`, `client`, `designer`, and so on). It does not use canonical permissions, and it does not match the S4 contract.
   - Its watchers check mixes the cast `watchers` column with the `watchers()` relation.
   - A grep found no direct `authorize('view', $task)` caller, but that does not prove the method is unreachable.
   - Needed: an Owner decision to (a) align it with the S4 contract in a follow-up slice, or (b) record it as dead and remove it under its own Work ID. It stays unchanged in #422, as instructed.

**P1. Governance**

4. **Work ID.** The Owner allocates a canonical ID (`GAP-nnn` or `OWN-yyyy-nnn`). The PR body's first line must then carry it, and the Owner decides whether `docs/audits/UAT-RECOVERY-01/` becomes governed packets under `docs/owner-decisions/`, with frontmatter and `owner-governance-lint` passing.
5. **The three quarantined tests.**
   - `test_D3_project_member_can_open_my_work` is resolved by O-18 option D or 1; option 2 needs the test amended by Owner decision.
   - The two `test_D2_*` tests depend on O-2 and O-8, the meaning of the parameterless `rbac` gate and the `users.role` aliasing. Each needs a fix-or-amend decision. The guard test keeps the set exact until then.
6. **O-19.** The Owner accepts this reconciliation, and G-2 (with G-1 optionally) is added as tests. Adding new tests is a test-coverage change, so it needs approval.

**P2. Verification**

7. **Recovery parity on MySQL.**
   - CI ran the Recovery suite on SQLite only.
   - The real-MySQL jobs that passed (Zena RBAC/Tenant invariants with mysql-parity, RFI escalation, Document workflow, Treasury, Month close, GAP-048) do not include `tests/Feature/Recovery`.
   - Proposal: one manual run of `tests/Feature/Recovery` with `phpunit.mysql.xml` against a disposable MySQL. Either a local container, or a one-off `workflow_dispatch` that does not deploy. Adding tests to `mysql-parity` is a test-selection change and needs the Owner's approval.
8. **Backup tests.** All 4 classes passed in disposable CI. Running them locally still writes to real `storage/backups` (no `useStoragePath($tmp)` isolation). **This is not a merge blocker for #422**; it should be a separate hygiene item.
9. **The 14 pre-existing failures** (Dashboard 7, EvidenceFreshness 5, ZenaInvariantsDiscovery 2). They are identical on `main` and **do not block** this PR.

**P3. After merge** (each needs separate approval)

10. Read-only inventory of the production and staging grant sets (O-18 §7).
11. `projects:pm-less-report`, dry run on a copy.
12. `rbac:backfill-user-roles`, dry run on a copy.
13. No deploy or backfill without its own Owner gate.

**Suggested order:**
- (1) O-18 decision and (4) Work ID, in parallel.
- Then (2), (3), (5) and (6) decisions.
- Then the O-18 implementation PR, with (7) MySQL Recovery run alongside.
- Re-run CI on #422, then the merge gate.
