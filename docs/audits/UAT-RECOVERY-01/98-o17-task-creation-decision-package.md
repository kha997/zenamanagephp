# O-17 — Task creation authority: Owner decision package

**Status: READY_FOR_O17_OWNER_DECISION.** Investigation only. No code, test, permission, seed or migration change was made. Nothing here is committed or pushed.

> **Status update (2026-11-24, appended; the package below is unchanged):** the Owner approved Option B with CB-3/CB-4 ("APPROVED_FOR_BOUNDED_LOCAL_IMPLEMENTATION"). The local implementation, the mapping of O-17a–h to the Owner's rules and the new sub-decisions O-17i/j/k are in `99-o17-implementation-report.md`. **Erratum to §2 I1/I2:** `assignee_id` is not dropped; the model stores it in `tasks.assigned_to`, so a foreign-tenant id could be written there before the patch (99 §1).

- Base: PR #422 head `004a5774d` (Draft, auto-merge off).
- O-17 origin: `50-recovery-04c-security-lifecycle-report.md` §E, where it was first labelled "O-16". It was renumbered to O-17 in the register erratum.

## 0. Gate 0 — PR and CI
| Item | Value |
|---|---|
| PR #422 head | `004a5774d31977a4ea55b4f25621a02269c47bac` (matches expected) |
| State | Draft, auto-merge disabled, Auto-fix not enabled |
| CI on `004a5774d` at the time of writing | 32 pass, 1 pending (`browser-tests`), **0 failing** |
| New failures | none |

This is not a final CI result: `browser-tests` was still running when this package was written. No code was changed to affect CI.

## 1. Gate 1 — Task-creation surface
The full route inventory, with the middleware taken from `route:list`, is in `evidence/o17-creation-route-inventory.tsv`.

**Direct creation routes:**
| # | Route | Gate | Controller check | Project validation | Membership / PM check |
|---|---|---|---|---|---|
| 1 | `POST api/zena/tasks` | `rbac:task.create` | `hasPermission('task.create')` (S4c) | tenant-scoped `exists` (S4c CB-2) | **none** |
| 2 | `POST api/tasks` | parameterless `rbac` (legacy role list) | `hasPermission('task.create')` (S4c) | tenant-scoped | **none** |
| 3 | `POST api/v1/tasks` (CoreProject) | `rbac:task.create` | **none.** `StoreTaskRequest::authorize()` returns `true` | tenant-scoped (S4c) | **none** |
| 4 | `POST app/tasks`, `POST tasks` (web) | `rbac:task.create` | delegates to #1's action | tenant-scoped | **none** |
| 5 | `POST operator/schedule/tasks` (web) | `rbac:task.create` | delegates to #1's action | tenant-scoped | **none** |
| 6 | `POST api/zena/inspections/{i}/ncrs/{n}/capa-tasks`, `POST operator/inspections/.../capa-tasks` | `rbac:inspection.edit` + `rbac:task.create` | delegates to #1's action, with the NCR tied to the same tenant and project | tenant-scoped | **none** |

**Bulk routes:**
| # | Route | Gate | Controller check | Project validation | Membership / PM check |
|---|---|---|---|---|---|
| 7 | `POST api/auth/bulk/tasks/create`, `POST api/auth/bulk/import/tasks` | parameterless `rbac` + `rbac:auth.bulk.manage` | none | **`project_id` is not validated at all; `tenant_id` is taken from the request** (see §2, CB-3) | none |

**Template and change-request routes (indirect creation):**
| # | Route | Gate | Creates | Project validation | Membership / PM check |
|---|---|---|---|---|---|
| 8 | `POST api/zena/projects/{id}/apply-template`, `POST app/projects/{project}/work-templates/apply`, `POST api/zena/components/{id}/apply-template` | `rbac:template.apply` | tasks + work instance + `task_assignments` | project looked up in the caller's tenant | **none; no `task.create` required** |
| 9 | `POST api/zena/change-requests/{id}/apply` | `rbac:change-request.apply` (seeded: System Admin only) | one "CR delta" task in the change request's own project | inherited from the change request | none. This is derived creation, not free creation |

**Reachable but non-functional (latent):**
| # | Route | Gate | Why nothing is created | Latent risk |
|---|---|---|---|---|
| 10 | `POST api/work-templates/{id}/apply` (CoreProject) | parameterless `rbac` only | `work_templates` has no `is_active` / `template_data` columns, so the template is always "inactive" and the request returns 422 | `project_id` and `default_assignee_id` use **global** `exists`. The probe passed validation with a **tenant-B project**. If the schema is ever aligned, this becomes a cross-tenant creation path |
| 11 | `POST api/templates/{id}/apply` (Src WorkTemplate) | parameterless `rbac` | writes `project_tasks`, which has no migration | project is tenant-scoped |
| 12 | `POST api/templates/apply` (`ProjectTemplateController`) | parameterless `rbac` | `project_id` must be `integer`, but project IDs are ULIDs; the probe got a 500 | — |

**Not task creation:**
- `api/integrations/third-party/asana/create-task` writes to an external system.
- `final-integration/launch-preparation-tasks` is not the `tasks` table.

**Unreachable** (no route; confirmed in 50 §E): `Web\TaskBulkController`, `Api\App\TaskController`, the root `TaskController`, and `TaskPolicy::create()`. `TaskPolicy::create()` contains legacy-role logic, but **nothing calls it**.

**Assignment validation at creation:**
- #1: `assignee_id`, `watchers.*`, `parent_id` and `dependencies.*` use **global** `exists:users|tasks,id`.
- #3: `assignments.*.user_id`, `component_id` and `phase_id` use **global** `exists`.

**Legacy RBAC dependencies:**
- The parameterless gate admits any **legacy** `users.role` in a fixed list (O-2/O-8).
- `RoleBasedAccessControlMiddleware::checkAccess()` returns `true` for any user whose DB role is named `super_admin`, before any permission check. `production:rbac` creates a role with that name.

**Existing project-relationship data:**
- **`projects.pm_id`** marks the owning PM. Since O-15/O-16 it can only be changed via `ProjectPmAssignment`, with no self-appointment.
- **`project_user_roles`** (`project_id`, `user_id`, `role_id`, `deleted_at`) is the project membership table already used by an Owner-approved rule: `TreasuryPolicy`, GAP-063 Gate 2, which requires same tenant, the permission, and either membership or `treasury.all_projects`.
  - It is also read by work-template assignee rules, time entries and dashboards.
  - Its only writer is `POST api/v1/rbac/assignments/projects/{project}/users/{user}/roles` (`rbac:role.assign`, so System Admin), which requires a tenant **project-scope** role.
  - `DatabaseSeeder` seeds **no** project-scope role, and **no UI** writes this table.
- **`project_team_members`** is a second membership table, read only by `DocumentApproverAssignmentService`, with no writer.

## 2. Gate 2 — Reproduction (isolated SQLite, current behaviour)
**Probe setup:**
- Probe: `evidence/o17-creation-probe-test-source.php.txt`. Raw output: `evidence/o17-creation-probe.json`.
- In-memory test database with `DatabaseSeeder` roles.
- Probe-only fixtures in that throwaway database:
  - a "Probe Creator" role holding only `task.create`;
  - a "Probe Template Applier" role holding `template.apply` + `template.view`;
  - one project-scope membership row.

**Seeded holders:**
| Permission | Seeded holders |
|---|---|
| `task.create` | System Admin, Project Manager |
| `template.apply` | System Admin, Project Manager |
| `auth.bulk.manage` | System Admin only |
| `change-request.apply` | System Admin only |
| `task.create` for Project Member | **not held** |

Results: "t" means rows added to `tasks`. Each creation scenario was run on all five creation endpoints (#1–#5).
| Case | Actor and target | Current result | Desired (recommended policy, §4) |
|---|---|---|---|
| A | PM1 → own project P1 | **201/302, t+1 on all 5** | allow |
| B | PM1 → PM2's project | **201/302, t+1 on all 5** | **deny** |
| B2 | PM1 → project with `pm_id` NULL | **201/302, t+1 on all 5** | deny (O-17b) |
| C | `task.create` holder, participant of P1 → P1 | **t+1 on all 5** | allow |
| D | same holder → P2, where they are not a participant | **t+1 on all 5** | **deny** |
| E | `task.create` holder with no membership → P1 | **t+1 on all 5** | **deny** |
| F | System Admin, neither PM nor participant → P1 | **t+1 on all 5.** This comes from its `task.create` grant, not from a bypass | **deny**, unless an explicit override is granted (O-17c) |
| F2 | user whose only role is `super_admin`, with **zero permissions** | 403 on #1, #2 and the web routes; **201, t+1 on `api/v1/tasks`** | deny |
| C0 | seeded Project Member (no `task.create`) | 403 / redirect, t+0 on all 5 | deny |
| G | PM1 → tenant-B project | 422, t+0 on all 5 | deny (unchanged) |
| H1 | template applier **without `task.create`** → P1 | **201: t+1 task, +1 assignment, +1 work instance** | deny (`template.apply` does not imply creation authority) |
| H2 | same applier → PM2's project | **201: t+1, +1 assignment** | deny |
| H3b | PM1 applies a template to PM2's project | **201: t+1, +1 assignment (assigned to PM2)** | deny |
| H4 | `task.create` without `template.apply` → apply | 403, t+0 | deny (unchanged) |
| H5 | CoreProject apply (#10), tenant-B project, no permissions | 422 "template inactive", after passing project validation | deny before validation |
| I1/I2 | `assignee_id` = tenant-B user / non-participant | 201; `assignee_id` **silently dropped** (UAT D5) | reject an ineligible assignee (O-17d) |
| I3 | `watchers` = tenant-B user | **500**, rolled back, t+0 | 422 |
| I4 | v1 `assignments[].user_id` = tenant-B user | **500**, rolled back. Caused by the separate `split_percent` defect, which is out of scope and was not investigated | 422, tenant/participant-checked |
| I5b/I6 | `parent_id` / `dependencies` = tenant-B task | 201; neither link persisted | 422 |
| **K1** | **System Admin, bulk create with `tenant_id` and project of tenant B** | **200; task written INTO TENANT B** (row `tenant_id` = B); **no audit row** | **deny (CB-3)** |
| K2 | System Admin, bulk create into PM2's project | 200, t+1 | deny |
| K1/K2 | PM1 (no `auth.bulk.manage`) | 403, t+0 | deny |
| **F3** | **`super_admin` role with zero permissions, bulk create into tenant B** | **200; task written into tenant B** | **deny (CB-4)** |

**J — zero side effects on denial:** every denied request above (C0, G, F2 except v1, H4, H5–H7, K for PM1) left 0 tasks, 0 assignments, 0 audit rows and 0 work instances.

**Audit gap.** Successful creation through #1/#4/#5 writes one `audit_logs` row; `api/v1/tasks` and bulk creation write none.

### New security findings (tenant boundary, outside the O-17 policy question)
- **CB-3 (Critical where more than one tenant exists).**
  - `BulkOperationsController::bulkCreateTasks` / `importTasks` accept `tenant_id` from the request, do not validate `project_id`, and `BulkOperationsService::createTask` calls `Task::create($data)` with the request rows.
  - Any `auth.bulk.manage` holder can write tasks into another tenant's project, without audit.
  - Fixing this enforces the existing tenant boundary, as with S4c CB-2; it is not a new business rule.
- **CB-4 (High).**
  - The middleware `isSuperAdmin()` short-circuit lets a `super_admin`-named role pass every `rbac:<permission>` gate with **no permissions**.
  - Creation routes without an in-controller check (#3 `api/v1/tasks`, #7 bulk) therefore create.
  - `production:rbac` creates the `super_admin` role ("Owner / quản trị — toàn quyền"). Whether production users hold it is **not verified**, because production was not accessed.
- **Global `exists` on assignee, watcher, parent, dependency and v1 assignment references** (#1, #3, #10).
  - This lets anyone probe whether IDs exist in other tenants.
  - It is harmless today only because those fields are dropped or crash. Severity Low; it becomes latent High if they are fixed naively.

## 3. Gate 3 — Policy options
| | **A. Owning PM only** | **B. Owning PM + explicit participants (recommended)** | **C. Any `task.create` holder, any tenant project (current)** |
|---|---|---|---|
| Security | Strongest; one actor per project | Strong: needs the permission **and** a recorded relationship; same model as Treasury GAP-063 | Weak: every holder can write into every project of the tenant (cases B, D, E, F) |
| ZENA workflow (architecture practice: PM, lead architect, structure/MEP leads, site engineer) | Unrealistic: the PM becomes a data-entry bottleneck; discipline leads cannot plan their own work | Fits: the PM plans; named discipline and site leads create work in their own projects only | Works, but with no boundary between projects |
| Delegation | None (only by changing `pm_id`, which O-15 restricts) | Explicit, per project, revocable (`deleted_at`) | Implicit and tenant-wide |
| Backward compatibility | Breaks every non-PM creator, including System Admin | Breaks creators without a relationship (cases B, D, E, F). Seeded users are unaffected **only if** each PM is the `pm_id` of the projects they work in | Fully compatible |
| Existing permissions | `task.create` alone is no longer sufficient | `task.create` stays canonical; a relationship check is added | unchanged |
| API / UI | 403 for others; create forms must list only own projects | 403 for others; create forms and the schedule page must list only creatable projects; participants need a management surface (O-17g) | none |
| Regression coverage | small matrix | full matrix in §6 | nothing new |

**Recommendation: Option B.** On every entry point:
- the canonical `task.create` permission;
- the same tenant;
- the actor is the project's owning PM or an active participant.

`template.apply` requires the same creation authority. There is no hidden System Admin bypass, and `super_admin` gets no creation bypass. This is a recommendation; it is not approved.

## 4. Authorization matrix (Option B, proposed)
An actor may create a task in project P only if **all** of these hold:
1. The actor and P are in the same tenant.
2. The actor holds `task.create`. For template apply, the actor also holds `template.apply`.
3. One of:
   - `P.pm_id = actor.id`;
   - the actor has an active `project_user_roles` row for P (`deleted_at` NULL);
   - the actor holds an explicit override permission, if the Owner creates one (O-17c).

| Actor | `task.create` | Relationship to P | Direct create | Template apply | Bulk |
|---|---|---|---|---|---|
| Owning PM (seeded PM) | yes | `pm_id` | **allow** | allow (+`template.apply`) | deny (no `auth.bulk.manage`) |
| PM of another project | yes | none | **deny** | **deny** | deny |
| Participant with `task.create` | yes | participant | **allow** | allow only with `template.apply` | deny |
| Participant without `task.create` (today's seeded Project Member) | no | participant | deny | deny | deny |
| `task.create` holder, no relationship | yes | none | **deny** | **deny** | deny |
| System Admin, not PM or participant | yes (all codes) | none | **deny**, unless O-17c override | **deny** | **deny** for another tenant (CB-3); within tenant, same predicate per row |
| `super_admin`-named role, no permissions | no | any | **deny on every path** (CB-4) | deny | deny |
| Any actor | — | other tenant | deny, 422 (unchanged) | deny | **deny** (CB-3) |
| Project with `pm_id` NULL | — | — | participants only; else nobody (O-17b) | same | same |
| Change-request apply (#9) | n/a | derived from an approved change request | unchanged: system-derived, keeps `change-request.apply` | — | — |

**Assignment at creation** (O-17d, proposed):
- `assignee_id`, `watchers`, `assignments[].user_id` and template-resolved assignees must be in the same tenant **and** be the PM or a participant of P.
- Otherwise:
  - direct create returns 422;
  - template apply leaves the step unassigned and adds a warning.
- `parent_id` and `dependencies` must be tasks of P.

## 5. Gate 4 — Smallest coherent implementation (design only, not started)
1. **A single decision point.**
   - Add `App\Support\TaskCreationAuthority::allows(User $actor, Project $project): bool` (and `denialReason()`), modelled on `ProjectPmAssignment`.
   - It implements §4 points 1–3, reads `projects.pm_id` and `project_user_roles`, and has no role-name logic and no `isSuperAdmin()`.
2. **Owning PM:** `projects.pm_id`. **Eligible participant:** an active `project_user_roles` row, where the user is in the project's tenant. This is the canonical table, following the Treasury precedent; `project_team_members` is not used.
3. **Projects without a PM:** only participants may create. No auto-claim, consistent with O-15.
4. **Assignment safety:** one validator, `TaskCreationAuthority::eligibleAssignee(P, userId)`, used by #1, #3, #7 and the template assignee resolver.
5. **Entry points:** call `allows()` inside the action, after the tenant-scoped project lookup, on #1 (covers #2, #4, #5 and #6), #3, #7 (per row; ignore request `tenant_id`; tenant-scoped `project_id`; this is the CB-3 fix) and #8. This makes the decision independent of middleware, so CB-4 is closed for creation without touching the global `isSuperAdmin()` short-circuit. That short-circuit is a separate Owner item (O-17h).
   - For #10: add a tenant-scoped `project_id` and `default_assignee_id`, plus `allows()`. Alternatively, record it as dead code for later removal.
   - #11 and #12 are non-functional; record them only.
6. **Validation:** tenant-scope `assignee_id`, `watchers.*`, `parent_id`, `dependencies.*`, `component_id`, `phase_id` and `assignments.*.user_id`.
7. **Audit and failure behaviour:**
   - A denial creates nothing: authorize before `DB::beginTransaction()`, and write no audit row.
   - A success writes one `audit_logs` row on every path, including v1 and bulk.
   - A denial is logged at warning level with actor, project and reason, without request bodies.
8. **API compatibility:**
   - **Unauthorised:** 403 with the existing error envelope (`E403.AUTHORIZATION`) on JSON; a redirect with the existing "Bạn không có quyền" flash on web.
   - **Unchanged:** cross-tenant stays 422, and success shapes do not change.
   - **Bulk:** per-row denials go into `errors[]` with `failed` incremented. The whole request returns 403 when no row is permitted.
9. **Frontend:** `tasks.create`, the schedule page and the template-apply picker list only projects where `allows()` is true. No new screens in this slice.
10. **RED/GREEN tests:** §6. They must be RED on the unpatched code.
11. **SQLite/MySQL:** only `EXISTS` subqueries on indexed columns (`project_user_roles(project_id, user_id)`). No schema change, no migration. Run on SQLite and on MySQL 8 in CI.
12. **Interaction with other decisions:**
    - O-15/O-16: authority derives from `pm_id`, which only `ProjectPmAssignment` can change, so creation authority cannot be self-granted.
    - O-18: Project Member still lacks `task.create`. Option B makes granting it later safe (scoped to participation), but that grant is a separate decision (O-17f).
    - TaskPolicy update/delete/assign stay unchanged.
    - The `split_percent` defect stays out of scope. I4 will keep failing on v1 assignments until its own Work ID.

**Out of scope:**
- the parameterless gate (O-8);
- the global `isSuperAdmin()` behaviour for non-creation routes (O-17h);
- a participant-management UI (O-17g);
- dead-code removal;
- `split_percent`;
- any grant or seed change.

## 6. Regression acceptance criteria
Each criterion must be RED before the patch and GREEN after it. Every denial must assert 0 rows in `tasks`, `task_assignments`, `audit_logs` and `work_instances`.
- **T-1:** cases A, C allowed and B, B2, D, E, F denied, on every direct endpoint (#1–#6).
- **T-2:** `template.apply` without `task.create` is denied; PM → another PM's project is denied; the owning PM is allowed (H1–H3b).
- **T-3:** bulk ignores request `tenant_id`; a foreign or unknown `project_id` is denied; per-row denial is reported; each success is audited (K1, K2).
- **T-4:** a zero-permission `super_admin` role is denied on every creation path (F2, F3).
- **T-5:** an ineligible `assignee_id`, `watchers`, `assignments[].user_id`, `parent_id` or `dependencies` gives 422 (I1–I6); template assignees outside P are left unassigned with a warning.
- **T-6:** cross-tenant cases stay 422 or 404 (G) and existing S4c tests stay unchanged.
- **T-7:** a participant row with `deleted_at` set gives no authority, and a participant of another tenant gives none either.
- **T-8:** the create forms list only creatable projects.
- **T-9:** O-15/O-16/O-18 suites and the Recovery suite stay green; no permission row changes (`role_permissions` count unchanged).

## 7. Remaining Owner decisions
| ID | Question | Recommendation |
|---|---|---|
| **O-17** | Which policy: A, B or C? | **B** |
| O-17a | Is the canonical participant `project_user_roles` (active row)? | yes (Treasury precedent) |
| O-17b | In a project with `pm_id` NULL, may participants create? | participants only; nobody else |
| O-17c | Should there be an explicit administrative override such as `task.create.any_project`? Note: `PermissionSeeder` gives System Admin **every** code via `sync(all)`, so creating one grants it to System Admin automatically. | no override for now; if one is wanted, decide its recipients explicitly as with O-16 |
| O-17d | Must assignees and watchers at creation be the PM or participants of the project? | yes |
| O-17e | Fix CB-3 (bulk cross-tenant write) and CB-4 (`super_admin` creation bypass) in the O-17 slice, or ahead of it as a tenant-boundary fix? | fix ahead of or with O-17; both are boundary enforcement, not new rules |
| O-17f | Should any seeded role besides PM receive `task.create` (for example Project Member or `site_engineer`) once B is enforced? | separate decision; no grant in this slice |
| O-17g | Who manages participants: System Admin through the existing API, or the owning PM through a new capability? Is a UI needed? | System Admin through the existing API for now; PM-managed participants as a follow-up |
| O-17h | Should the global middleware `isSuperAdmin()` short-circuit be removed or restricted for non-creation routes? | its own Work ID |
| — | Is read-only production verification of who holds `super_admin` and `auth.bulk.manage`, and of whether more than one tenant exists, approved? | yes, read-only |

## 8. Final result
**READY_FOR_O17_OWNER_DECISION.**
- The evidence is complete for the policy choice.
- CB-3 and CB-4 are security defects that exist under every option. They are recorded here for decision O-17e and are **not** fixed.
- PR #422 stays Draft and MERGE_NO_GO.
