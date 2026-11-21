# UAT RECOVERY 01 — Identity, RBAC & Project Isolation — Owner Decision Package

Status: **READY_FOR_OWNER_GATE** (no runtime behaviour changed; no commit/push; Work ID/GAP number to be assigned by the register — not self-registered here).
Source UAT: `docs/uat/ZENA-UAT-DESIGN-2026-001/` (reported evidence; each claim below re-verified against code + tests).

## 1. Repository snapshot (Gate 0)

| Item | Value |
|---|---|
| Branch / HEAD | `main` / `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d` (unchanged since the UAT run) |
| Worktree | only untracked: `docs/uat/`, `docs/audits/UAT-RECOVERY-01/`, `tests/Feature/Recovery/`. No tracked file modified. |
| Test infra | PHPUnit per-run temp sqlite (`tests/bootstrap.php`), real `DatabaseSeeder`. Local `.env` has no `APP_KEY` → web tests need `APP_KEY` exported (CI writes one). Not a repo defect. |

**UAT artifact audit**
- *Runner isolation*: `lib.php` refuses to run unless `DB_CONNECTION=sqlite`, `APP_ENV=testing`, path outside `database/` and containing `uat`. Dev DB (`database/database.sqlite`) and `.env` were never opened. **Weakness**: the `uat` substring test is weak (any path containing "uat" passes) — a prod-like sqlite path with that substring would be accepted. Recommend exact allow-list path if the runner is kept.
- *Idempotency/cleanup*: **not idempotent** — gate scripts append to `ledger.jsonl`, `gate1` fails on re-run (duplicate emails), several scripts `forceDelete` rows by project/name (safe only because the DB is isolated), `run.sh` default path is a session scratchpad. Not a reusable regression suite; superseded by `tests/Feature/Recovery`.
- *Side effects outside the isolated DB*: appended to `storage/logs/laravel.log` (git-ignored). Uploaded files went to scratch.
- *Secrets/PII*: none found. Emails are `*@example.test`, names synthetic, the test password was replaced by `UAT_TEST_PASSWORD` env. `run.sh` embeds a deterministic throw-away `APP_KEY` (not a real secret; do not reuse). IDs only; no tokens/cookies. The scratch sqlite (hashed fake passwords) is not in the repo.
- *Ledger fidelity*: 146 rows (73/31/2/40). Several early rows were reclassified or removed during the UAT (documented there); the 31 FAIL rows collapse to the defect classes below. Some UAT "PASS" rows were vacuous (denied by the wrong layer) — e.g. `G1b.escalate.*`.

## 2. Verified defect inventory (Gate 1)

Legend: VERIFIED = reproduced by a RED test on the real seeder; CHAR = behaviour reproduced but **not** a violation of an existing canonical policy → Owner decision.

| ID | Claim | Verdict | Route / middleware | Code | Evidence (test) |
|---|---|---|---|---|---|
| D1 | `role.assign` absent from seed → even System Admin cannot assign roles | **VERIFIED** | `POST api/v1/rbac/assignments/users/{u}/roles`, `rbac:role.assign` (7 routes, `src/RBAC/routes/api.php:69-87`) | `RoleBasedAccessControlMiddleware::isPermission()` returns false when no `permissions` row exists → **fail-closed for everyone** (`app/Http/Middleware/RoleBasedAccessControlMiddleware.php` `checkAccess`); only `hasRole('super_admin')` bypasses and no seeded role is `super_admin` | `test_D1_seed_contains_role_assign_permission` RED; `test_D1_system_admin_can_assign…` RED (403); `CONTROL_D1` GREEN (adding only the row fixes the 403) |
| **D1b** (new) | Even with `role.assign`, an API-assigned role does not take effect | **VERIFIED (new)** | same | `RBACManager::assignSystemRole` writes `system_user_roles` (`UserRoleSystem`); `User::hasPermission()`/`roles()` read `user_roles` (`app/Models/User.php:93-98,194`) | `test_D1b_role_assigned_through_api_takes_effect…` RED |
| D2 | Authorization inconsistent: `users.role` vs `DocumentPolicy` vs DB roles | **VERIFIED** | generic `rbac` (no arg) on the whole `Route::middleware(['auth:sanctum','tenant.isolation','rbac'])` group; `POST app/documents/{id}/submit` | `handleGeneralAccess` allows only legacy values {`project_manager`,`team_member`,…} or DB role *names* equal to them; `DocumentPolicy::update` allows {`super_admin`,`admin`,`pm`,`designer`}; seeded DB roles are `System Admin/Project Manager/Project Member`. No single `users.role` value satisfies both gates. `UserManagementService::createUser` sets no role. | `test_D2_user_with_only_seeded_project_manager_role_passes_api_gate` RED (403); `test_D2_seeded_project_manager_can_submit…` RED; `test_D2_no_seeded_user_can_create_users…` RED; `CONTROL_D2` GREEN (legacy values are the only blocker) |
| D3 | Business permissions missing/inconsistent with seed | **VERIFIED** | 223 distinct `rbac:<perm>` strings in routes | **34** are absent from the seed (list: `evidence/missing-route-permissions.txt`) incl. `role.*`, `permission.*`, `user.view`, `project.edit`, `task.edit`, `change_request.submit/view`, `baseline.*`, `audit.view`. Naming drift: Member has `task.read`, routes need `task.view`; PM has `change-request.*` (hyphen) *and* `change_request.*` (underscore); `project.read`/`user.manage` rows have `name=NULL` (`PermissionSeeder` omits it for 2 entries). `rfi.*` exist only on System Admin. | `test_D3_every_rbac_route_permission_exists…` RED (34 missing); `test_D3_project_member_can_open_my_work` RED (302) |
| ISO | Same-tenant non-member can read project/task/doc data | **CHAR — not a violation of existing policy** | `GET app/projects/{id}`, `app/tasks` (auth+tenant only) | `ProjectPolicy::view` = same tenant + `project.view` — **no membership term**; controllers never call `authorize`; `project_team_members` has **no writer anywhere** and is read only by `DocumentApproverAssignmentService` and `ProjectAnalyticsController`. No canonical document defines member-restricted visibility. | `test_CHAR_same_tenant_non_member_can_read…` GREEN (documents current behaviour) |
| OWN | Project Member can change another member's task status | **VERIFIED**, policy-conflict | `PUT app/tasks/{id}` (`rbac:task.update`, held by every Member) | `Web\TaskController::update` → `Api\TaskController::update` filters by tenant only; `TaskPolicy::update` (admin / PM of project / assignee) exists but **neither controller calls `authorize`** (0 `authorize` calls). `TaskPolicy` itself still uses legacy roles (`project_manager`) so wiring it unchanged would not work for seeded roles. | `test_TASK_OWNERSHIP_non_assignee…` RED (status flipped to `in_progress`) |
| — | Cross-tenant isolation | **PASS (guard)** | task/project/doc/assignment endpoints | tenant-filtered lookups return 404/403 and nothing mutates | `test_GUARD_cross_tenant…` GREEN |
| — | Unauthorized document approve | **PASS (guard)** | `POST app/documents/{id}/approve` `rbac:document.approve` | Member lacks permission; no state/event change | `test_GUARD_project_member_cannot_approve…` GREEN |
| — | PM cannot create RFI | **CHAR** (Owner decision) | `operator/rfis` `rbac:rfi.create` | `rfi.*` granted to System Admin only by seeds | `test_CHAR_project_manager_cannot_create_rfi…` GREEN |

**Not a defect / corrections to the UAT report:** (a) "System Admin bypass" does not exist — `isSuperAdmin()` is the role name `super_admin`, which is never seeded; System Admin is just a role with all seeded permissions, hence D1. (b) The UAT's membership finding (D8) is a **policy gap**, not a broken rule. (c) UAT D4–D7 (task API tenant-less rows, project-create field loss, etc.) are out of scope for this round.

## 3. Root cause analysis

1. **Three authorization generations coexist.** Older policies/controllers (`TaskPolicy`, `DocumentPolicy`, `RfiPolicy`, `ChangeRequestPolicy`, `ComponentPolicy`, `NcrPolicy`, `QcPlan/QcInspection`, `UserPolicy`, `TemplatePolicy`, `InvitationPolicy`, `SidebarConfigPolicy`, `NotificationPolicy`) call `hasRole([...])` with a legacy vocabulary (`super_admin` 110 uses, `admin` 98, `pm` 63, `designer` 35, `engineer` 24, `project_manager` 22 — 264 call sites). Newer ones (Treasury, Quote, Lead, Boq, Material*, Submittal, SiteDiary, Project, Team, Vendor…) call `hasPermission`. Routes use `rbac:<code>` which resolves DB permissions. The DB seed knows only 3 roles with different names.
2. **Seed drift.** Routes/policies were written against permission codes that the seeders never created (34), with two naming conventions. The only test that "covers" RBAC (`GAP042RbacProductionFidelityTest`, `RbacApiTest`, …) calls `grantFullRbacGateAccess`/`createTenantUserWithRbac` which `firstOrCreate` the missing permissions — **fixtures mask the seed gap**, so CI is green while a fresh install is unusable.
3. **Two role pivots.** Read side = `user_roles`; RBAC-API write side = `system_user_roles`.
4. **Fail-closed gate + no bypass** converts every seed gap into a hard 403 for all users.
5. **Authorization lives only in route middleware for Task/RFI/CR/Document-API** (0 `authorize`/`hasPermission` in those controllers); object-level rules (ownership, project role) are therefore not enforced even where policies exist.
6. **Project membership** is a half-built concept (table + one reader, no writer, no policy).

## 4. Current authorization architecture

```
request ─► auth (web session | sanctum) ─► TenantIsolationMiddleware ─► route middleware
                                                                          │
        ┌─────────────────────────────────────────────────────────────────┤
        │ A) rbac (no arg)  handleGeneralAccess: users.role ∈ {project_manager,team_member,…}
        │                   OR DB role NAME ∈ same list          (legacy vocabulary)
        │ B) rbac:<code>    isPermission(code exists?) ─no→ DENY (fail-closed)
        │                   └yes→ user.hasPermission(code) via user_roles ─► permissions.name
        │                        (only role 'super_admin' bypasses — never seeded)
        └─ project param hook: checkProjectAccess() = tenant check only; its
           $projectSpecificPermissions list uses names no route uses (dead)
                                      ▼
controller ──(Task/RFI/CR/Doc-API: no authorize)──► model (tenant global scope on most models)
controller ──(Web Document*, Submittal, SimpleDocument: authorize())──► Policy
                       Policy gen-1: hasRole(legacy names) | gen-2: hasPermission(code)
Data: users.role (legacy string) | user_roles (read) | system_user_roles (RBAC API write) |
      custom_user_roles (GAP-042) | project_user_roles | project_team_members (no writer)
```

## 5. Proposed canonical architecture (recommendation only)

Principle: **one decision, three ordered questions, each answered in exactly one place.**
1. **Tenant boundary** — unchanged (`TenantIsolationMiddleware` + model scopes).
2. **Capability** — "may this user do `<resource>.<action>`?" = `permissions.name` reached through **one** role pivot (`user_roles`). Route middleware `rbac:<code>` stays the coarse gate; the seed becomes the **single source of truth** and is guarded by a seed-coverage test (the D3 test).
3. **Object scope** — "…on *this* project/task/document?" = a policy method that first calls `hasPermission(code)` then applies one of a closed set of predicates: project-PM (`projects.pm_id`), task assignee (`tasks.assignee_id`/`task_assignments`), document creator/designated approver, project member (only after Owner approves membership semantics). Policies never read `users.role` or role names.
4. **Legacy `users.role`** — frozen compat: one alias map inside `HasRoles::hasRole` (legacy vocabulary → seeded role names) so the 264 existing call sites keep working without a rewrite; no *new* code may read `users.role`. Retire later.
5. **Intentional administrative exceptions** (explicit, not implicit): System Admin = all permissions by seed sync, **tenant-bound**, no cross-tenant power, no object-scope bypass beyond what the matrix states; no `super_admin` role is introduced.

Job titles ≠ roles: Lead Architect / Architect / Structural / MEP / Quality Reviewer / Director are **titles**. Access roles stay `System Admin`, `Project Manager`, `Project Member`; differences between titles come from *object scope* (assignee, designated approver) and from explicitly granted permissions, not new roles. No new role in the first patch.

## 6. Permission matrix (proposed; **nothing here is applied**)

Tenant boundary = always required. "—" = not required. "O-n" = Owner decision below.

| # | Operation | Required permission | Project membership | Ownership | Designated actor | Current implementation (evidence) | Proposed | Owner decision |
|---|---|---|---|---|---|---|---|---|
| 1 | Create/update project | `project.create` / `project.update` (+`project.edit` alias seeded) | — / PM-of-project or admin (update) | — | — | Web `POST app/projects` → Api `hasPermission('project.create')`; update via FormRequest; PM role has create/update; D6 field loss out of scope | keep; add object scope on update (pm_id or admin) | O-5 |
| 2 | View project | `project.view` (seed currently gives Member `project.read` only) | tenant-visible today; member-restricted only if O-3=B | — | — | `ProjectPolicy::view` tenant+`project.view`; not invoked by controllers; reads reachable by any tenant user with a role (`test_CHAR…`) | seed `project.view` for Member/PM (alias of read); keep tenant-visible | **O-3**, O-5 |
| 3 | Assign project members | `project.assign_member` (new) | PM-of-project/admin | — | — | no UI/API writes `project_team_members`; RBAC project-role route needs unseeded `role.assign` and writes a third table | add writer later (separate slice); not in first patch | **O-3** |
| 4 | Create task | `task.create` | member (if O-3=B) / PM | — | — | web+API gated by `task.create`; PM only | unchanged | — |
| 5 | Assign task | `task.assign` | PM-of-project/admin | — | — | `/api/dashboard/tasks/{id}/assignments` generic gate (legacy role); `v1/task-assignments` `rbac:task.assign` (ULID bug, out of scope) | unchanged in first patch | O-4 |
| 6 | Update assigned task | `task.update` | — | assignee **or** PM-of-project **or** admin | — | any Member with `task.update` can edit any tenant task (RED) | `authorize('update',$task)` in `Web/Api TaskController::update/updateStatus/block/unblock`; `TaskPolicy` re-expressed with `hasPermission` + assignee/pm_id | **O-4** |
| 7 | Complete task | `task.update` | — | assignee may set `completed` only if O-4 allows; else PM/admin | — | no guard (UAT D7, out of scope) | same policy as #6 (+ future completion rules) | **O-4** |
| 8 | Upload drawing/document | `document.create` | member (if O-3=B) | creator recorded | — | Member+PM hold it; `DocumentController@store` no authorize | unchanged | — |
| 9 | Review document | none seeded (no review entity) | — | — | — | NOT_IMPLEMENTED | out of scope | O-6 |
| 10 | Approve document | `document.approve` | — | not the submitter (separation of duties, if Owner wants) | designated approver if assigned (GAP-033 intent; not enforced today) | `DocumentPolicy::approve` = `hasPermission` ✔; designation unenforced | keep; enforcement is a separate slice | O-6 |
| 11 | Publish document | `document.update` | — | — | — | `DocumentPolicy::update` legacy roles → PM 403 (RED) | express `update` via `hasPermission('document.update')` | O-2 |
| 12 | Create/respond to RFI | `rfi.create`/`rfi.respond`/`rfi.close` | member | creator may update own | assignee responds | seeds: System Admin only; `RfiPolicy` legacy roles; controllers no authorize | grant per Owner; policies → `hasPermission` | **O-5** |
| 13 | Submit/approve CR | `change-request.submit` / `.approve` | member | creator submits own | approver ≠ creator (optional) | PM lacks `submit`; two naming variants; `ChangeRequestPolicy` legacy roles | seed `change-request.submit`; collapse to hyphen form via alias, no rename | **O-5** |
| 14 | View project dashboard | `dashboard.view`/`task.view` | tenant-visible aggregate | — | — | `/app/dashboard` open to any authenticated; `/app/my-work` needs `task.view` (Member lacks → RED) | seed `task.view` for Member (read-only) | O-5 |
| 15 | Manage users and roles | `user.create` / `role.assign` / `user.manage_roles` | — | cannot assign a role ⊃ own permissions | — | System Admin cannot (D1/D1b); PM holds `user.manage_roles` (unused) | seed `role.assign` **System Admin only**; single pivot; assigner restricted to System Admin; PM does **not** receive it | **O-1**, O-7 |

## 7. Red regression evidence (Gate 4)

File: `tests/Feature/Recovery/UatRecovery01IdentityRbacTest.php` (real `DatabaseSeeder`; actors get only an **already-seeded** role; CSRF not bypassed; denied writes assert no DB change). Output: `evidence/red-run-1.txt`.

| Class | Tests | Result today |
|---|---|---|
| RED (defect reproduced) | D1 seed row; D1 admin assign; D1b role takes effect; D2 PM API gate; D2 PM submit doc; D2 no seeded user can create users; D3 seed coverage (34 missing); D3 Member my-work; TASK OWNERSHIP | **9 FAIL (expected)** |
| CONTROL (single-variable isolation) | D1 (add only `role.assign`), D2 (only legacy `users.role`) | 2 PASS |
| CHAR / GUARD (current behaviour, must stay) | same-tenant non-member read; cross-tenant read+write denied no-mutation; Member cannot approve; PM cannot create RFI | 4 PASS |

Totals: **15 tests, 45 assertions — 6 PASS, 9 RED, 0 BLOCKED.** Neighbouring suites unchanged: RfiWorkflowTest 10/10, GAP042RbacProductionFidelityTest 18/18, tests/Feature/Documents 29 (2 skipped, pre-existing). Existing-green ≠ GO.
Limits: sqlite only (MySQL parity unverified); HTTP-level tests, no browser; D3's route scan reflects routes registered in the test app.

## 8. Minimum safe remediation plan (Gate 5)

Order = dependency order; each slice is independently releasable and reversible. **No UI work, no document versioning, no new project features.**

**S1 — Seed completeness & idempotency (A, B).** Scope: add the 34 missing permission rows (code=name, module, action) + set `name` on `project.read`/`user.manage`; seeder uses `updateOrCreate(['code'=>…])`; System Admin keeps all-permission sync; **no grants to PM/Member beyond the Owner-approved list (O-5)**. Files: `database/seeders/PermissionSeeder.php`, `ZenaPermissionsSeeder.php`, `ZenaAdminRolePermissionSeeder.php`, optional new `PermissionBackfillSeeder` for existing environments. Security: raises System Admin reach only; `role.assign` System-Admin-only. Migration: none (data seed; run in prod via the same idempotent seeder, additive). Tests: `D1_seed…`, `D3_every_rbac_route…`, seeder-idempotency (run twice, row counts equal) + MySQL job. Acceptance: those tests GREEN; no other test changes. Dependencies: O-5. Complexity: **S**.

**S2 — Single role pivot (A, G, H).** Scope: make the write path of `RBACManager::assignSystemRole/revokeRole` target `user_roles` (the pivot all reads use) — or, if O-1 chooses otherwise, make `roles()` union both. Backfill: copy `system_user_roles` rows missing from `user_roles` (idempotent `INSERT … SELECT … WHERE NOT EXISTS`; run first as read-only count). Files: `src/RBAC/Services/RBACManager.php`, `app/Models/User.php`/`HasRoles`, one backfill migration. Security: **escalation guard** — only System Admin may assign; target must be same tenant (already enforced), role must be global (already). Migration: data-only, rollback = delete rows tagged by a `backfill` marker/timestamp window (record count before). Tests: `D1b…`, `D1_admin_assign…`, tenant guard, "assigner without `role.assign` gets 403 and no row". Acceptance: D1b/D1 GREEN. Dependencies: S1, O-1. Complexity: **M**.

**S3 — Legacy compatibility shim (C, G).** Scope: one alias map in `HasRoles::hasRole/hasAnyRole` (e.g. `pm`/`project_manager` ⇄ "Project Manager"; `designer`/`team_member` ⇄ "Project Member"; `admin` ⇄ "System Admin" **only if O-2 approves**) and make `handleGeneralAccess` accept any user holding a seeded DB role. Do **not** edit the 264 call sites. Security: the mapping is a permission expansion for every `hasRole(['admin'])` policy — hence O-2; tenant boundary unaffected. Tests: `D2_*` (3 tests) GREEN; add negative tests (Member must still be denied admin-only policies; legacy-role users unchanged). Dependencies: S1, O-2. Complexity: **M** (risk-dense, small diff).

**S4 — Task ownership (E).** Scope: `$this->authorize('update', $task)` in `Web/Api TaskController::update/updateStatus/block/unblock`; rewrite `TaskPolicy::update/assign/delete` to `hasPermission` + (admin | `projects.pm_id` | `assignee_id`/active `task_assignments`) per O-4. Files: `app/Policies/TaskPolicy.php`, two controllers. Security: tightens; may break workflows where non-assignees currently edit — needs the O-4 answer and a release note. Tests: `TASK_OWNERSHIP` GREEN; positive: assignee, PM-of-project, admin; negative: other Member, other-tenant PM; no-mutation assertions; existing `TaskTest`/`TaskEditTest` re-run. Dependencies: S1–S3, O-4. Complexity: **M**.

**S5 — Project membership boundary (D).** *Deferred until O-3 is decided.* If B: add membership writer (service + minimal endpoint), backfill from `projects.pm_id` + task assignees, then scope reads in `ProjectPolicy`/list queries. Separate Work ID; not part of the first patch.

**F (document/RFI/CR permissions):** after S1–S3 re-express `DocumentPolicy::update`, `RfiPolicy`, `ChangeRequestPolicy` via `hasPermission` one policy per PR; each with its existing tests + a seeded-role test. **I (rollback):** every slice is a single revert; S1 rows are additive and removable by code; S2 backfill tagged and reversible; keep a pre-change `SELECT COUNT(*)` of `user_roles`, `system_user_roles`, `role_permissions`. **J (SQLite vs MySQL):** unique `roles.name` is case-insensitive on MySQL (seed lookups by exact name); `permissions.name` NULLs allowed; ULID string keys both; backfill SQL must avoid sqlite-only syntax; run S1/S2 tests on the MySQL CI job (GAP-040 harness) before release.

## 9. Risk & compatibility

- Existing users: any user with `users.role='admin'` keeps current reach; S3 only adds DB-role equivalence. Users with only DB roles currently locked out (generic gate) gain access — that is the intended repair but is an expansion → O-2/O-8.
- Seeder drift is permanent unless the D3 test stays in CI (fixture-based suites cannot catch it) — recommended standing guard.
- S4 is the only slice that can remove access that exists today.
- Cross-tenant guard tests must remain GREEN at every step.

## 10. Adversarial review (Gate 6)

| Risk | Finding | Mitigation |
|---|---|---|
| Privilege escalation via `role.assign` | Any holder can assign `System Admin` to anyone in-tenant; `assignSystemRole` checks tenant+scope but not assigner privilege | `role.assign` System Admin only; add "cannot assign role whose permissions ⊄ assigner's"; test it |
| Alias map (S3) | `admin ⇄ System Admin` silently expands ~98 `hasRole(['admin'])` checks (SidebarConfig, UserPolicy, Invitation…) | Owner approval O-2; enumerate impacted policies in the PR; negative tests |
| Seeding fixes mask re-drift | New permissions could again be invented by fixtures | keep D3 seed-coverage test; ban `firstOrCreate` permissions in RBAC tests (SSOT lint already blocks raw `Permission::create`) |
| False-positive RED | RED tests could fail for env reasons | CONTROL tests change exactly one variable and pass; APP_KEY-less failure was caught and fixed |
| False-positive GREEN | Guards could pass because a *different* layer denies | guards assert DB state unchanged **and** status; document that `GUARD_approve` is denied by route permission, not policy |
| Project isolation | "Tenant-visible" reads mean any tenant user with a role sees all projects incl. financial fields (budget) | Owner decides O-3; at minimum fields-level review |
| Dual pivots | `custom_user_roles` (GAP-042) and `project_user_roles` exist too; S2 must not widen scope to them | S2 touches only `user_roles`/`system_user_roles`; audit others separately |
| Cross-tenant | role `tenant_id` NULL global roles shared across tenants; target-user tenant check exists | keep test; no tenant-scoped role creation in this patch |
| Unresolved questions | (1) Production data: do `system_user_roles` rows exist? (2) Which `users.role` values exist in prod? (3) Is `custom_user_roles` live? (4) Do MySQL prod `permissions.name` match? — **need read-only prod/staging counts via Owner**; not queried here. |

## 11. Owner decisions required

| ID | Decision | Recommended | Alternatives | Consequence / security trade-off | Boundary if approved |
|---|---|---|---|---|---|
| **O-1** | Canonical role pivot | `user_roles` (read path of 264+ sites); write path + backfill from `system_user_roles` | `roles()` = union of both | union keeps writes untouched but perpetuates two sources | S2 only; no change to `custom_user_roles`/`project_user_roles` |
| **O-2** | Legacy `users.role` future & `admin ⇄ System Admin` aliasing | alias map in one place; no new reads of `users.role`; `admin`⇄System Admin approved explicitly | no alias (rewrite 264 sites per policy) | alias = fast but expands legacy `admin` checks to DB System Admins | S3 only |
| **O-3** | Project data visibility | keep **tenant-visible** reads now; restrict **mutations** by PM/assignee (O-4); add membership writer + member-restricted reads as a later Work ID | A) tenant-visible permanently; B) member-restricted now | B now would lock users out (no membership writer; empty `project_team_members`); A leaves financial data visible to all staff | none in first patch |
| **O-4** | Task update/complete authority | assignee (own task) + project PM + System Admin; create/assign/delete = PM + admin | any member (status quo); PM-only | stricter = may disrupt current ad-hoc edits | S4 only |
| **O-5** | Exact permission grants for the 34 + `task.view`, `project.view`, `rfi.*`, `change-request.submit` | System Admin: all; Project Manager: `change-request.submit`, `rfi.view/create/respond/close`, `project.view`; Project Member: `task.view`, `project.view`, `rfi.view`, `rfi.create` | grant nothing beyond System Admin | each grant is a business-authority decision; none applied silently | S1 seed lists exactly as approved |
| **O-6** | Design-discipline roles / reviewer | no new roles; titles ≠ roles; reviewer/designated approver handled by object scope in later slice | add `Quality Reviewer`, `Director`, `Client` roles | new roles multiply seeds/tests; defer until membership exists | none now |
| **O-7** | System Admin exception | no bypass; explicit all-permission sync; tenant-bound; only System Admin has `role.assign` | add seeded `super_admin` bypass | bypass removes fail-closed protection | S1/S2 |
| **O-8** | Generic `rbac` (no-arg) gate meaning | "authenticated + holds any seeded DB role (or legacy role)" | keep legacy-only | legacy-only keeps D2 | S3 |

## 12. Next Codex prompt (execute only after O-1, O-5, O-7 approved)

> Implement **UAT-RECOVERY-01 slices S1 and S2 only** on a fresh branch from `main`.
> S1: make `PermissionSeeder`/`ZenaPermissionsSeeder`/`ZenaAdminRolePermissionSeeder` create the 34 permissions listed in `docs/audits/UAT-RECOVERY-01/evidence/missing-route-permissions.txt` (code = name), set `name` on `project.read` and `user.manage`, use `updateOrCreate` keyed by `code`, give System Admin all permissions, and grant to Project Manager / Project Member **only** the permissions listed in the Owner-approved O-5 row (nothing else). `role.assign` must belong to System Admin only.
> S2: change `RBACManager::assignSystemRole` / `revokeRole` so assignments are written to (and removed from) `user_roles`, keeping tenant and global-role guards; add an idempotent data migration copying `system_user_roles` rows missing from `user_roles` (log counts before/after; reversible).
> Do **not** touch policies, controllers, `HasRoles`, the generic `rbac` gate, tasks, documents, RFI, CR, UI, or `users.role`.
> Acceptance: in `tests/Feature/Recovery/UatRecovery01IdentityRbacTest.php` the tests `D1_*`, `D1b_*`, `D3_every_rbac_route_…` turn GREEN with **no edits to their assertions**; the other RED tests remain RED (D2, TASK OWNERSHIP, D3 my-work unless O-5 grants `task.view`); CONTROL/CHAR/GUARD stay GREEN; add a seeder-idempotency test (seed twice → identical counts) and a test that a user without `role.assign` gets 403 and no row; `RfiWorkflowTest`, `GAP042RbacProductionFidelityTest`, `tests/Feature/Documents`, `tests/Feature/Audit` stay green; run on SQLite and the MySQL harness. No commit/push until Owner Gate 3 review.
