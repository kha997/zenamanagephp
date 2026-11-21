# UAT RECOVERY 04b — task mutation surface closure + project `pm_id` persistence (D6, pm_id only)

**FINAL DECISION: LOCAL_PATCH_VERIFIED** (local, SQLite). Not a staging/production approval. No commit/push/deploy. MYSQL_NOT_VERIFIED.
Owner decisions applied: **O-10** (no inference from `task.delete`), **O-11** (pm_id only, bounded), **O-13** (same boundary on every task mutation); **O-12** deferred (nothing invented).

## A. Repository snapshot
`main` @ `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d`, dirty tree = S1…S4 + S4b (29 tracked files, +452/−77) + untracked recovery artifacts (preserved). S4 baseline reconfirmed before editing: Api 798 tests/0 failures; Feature 1822 tests, 1 error + 18 failures, 21 skipped (4 Recovery-01 RED + 15 pre-existing + 0 new).

## B. Mutation surface inventory (route table + code search)
| Surface | Gate before | Object check before S4b | After S4b |
|---|---|---|---|
| `PUT app/tasks/{id}`, block/unblock, `api/zena` update/status, schedule update, `api/tasks` PUT | task.update / generic | S4: assignee/PM/task.delete-holder | assignee or owning PM (O-10) |
| `DELETE …/tasks/{id}` (api/zena, api/tasks, schedule, **v1**) | task.delete / generic | S4 (api, web) / **none on v1** | PM-of-project or task.delete holder; v1 now checked |
| `api/zena/tasks/{id}/dependencies` add/remove | task.dependencies.* | **none** | `update` |
| `api/zena/tasks/{id}/escalate-overdue` | task.escalate-overdue | none | unchanged — creates a notification/event record only, does not mutate the task |
| `Api\TaskController::assignUser` | (unrouted) | none | `assign` |
| `api/dashboard/**/assignments`, `api/task-assignments` (app controller) | generic | **none**; tenant-less `Task::find`; cross-tenant assignee possible | `assign` + assignee must be in task tenant |
| `api/v1/task-assignments` (CoreProject) | task.assign | **none**; `integer` ID validation rejected every ULID | `assign` on the canonical task, tenant-checked assignee, ULID validation, 404 for missing task |
| `api/v1/tasks` PUT/PATCH/DELETE (CoreProject) | `task.edit` (unseeded alias, unreachable) / `task.delete` | **none** | `update` / `delete` on the canonical `App\Models\Task` row |
| `api/auth/bulk/tasks/update-status` (`auth.bulk.manage`, System Admin only) | permission only | **none** (no tenant/ownership per task) | per-task `update`; denied tasks reported as failed |
| `api/auth/bulk/projects/update` | permission only | none (`pm_id` mass-assignable) | `pm_id` changes obey `ProjectPmAssignment` |
| `api/v1/work-template/projects/*/tasks/*` (`task.edit`) | unseeded alias | none | **table `project_tasks` has no migration** (only a test shim) ⇒ dead code, different entity; documented, not changed |
| Compensation sync/edit (`compensation.*`) | catalog-only, granted to nobody | none | unreachable today; latent |
| `api/tasks/bulk/status-change` | task.update | stub — mutates nothing | n/a |

## C. Authorization contract implemented (operation-specific, tenant-bound; `users.role` never read; no bypass)
- **update** (fields, status, completion, block/unblock, dependencies): assignee (`tasks.assignee_id` or `task_assignments.role=assignee`) **or** the owning project's PM (`projects.pm_id`) holding `task.update`.
- **assign / reassign / unassign**: owning project's PM holding `task.assign` only.
- **delete**: owning PM holding `task.update`, **or** holder of `task.delete` (independent authority, preserved).
- **O-10 consequence (documented removal of authority):** a System Admin (holder of `task.delete`, `task.assign`, `task.update-status`, …) no longer updates/assigns tasks of projects where it is neither PM nor assignee. It still deletes. To act it must be made the project PM or assignee. A distinct administrative permission is an unresolved-alias Owner decision (O-14, below) and was not invented.
- CoreProject models have no registered policy (Gate would deny everyone); decisions are taken on the canonical `App\Models\Task` row.
- Project PM change: `ProjectPmAssignment` — same tenant, actor holds `project.update`, project has no PM **or** actor is the current PM, new PM belongs to the project's tenant; unchanged value always allowed; no admin override.

## D. Confirmed bypasses (RED before the patch, `evidence/s4b-BEFORE.txt`, 11 of 17)
1. `task.delete` holder updated/blocked tasks and added dependencies without any relation (O-10).
2. Any legacy-valued user created/reassigned/removed assignments on any task; cross-tenant assignee accepted.
3. CoreProject (`api/v1`) task update/delete and assignment had **no** object check; with the `task.edit` alias defined, an unrelated Member mutated tasks (the 500 returned afterwards is an unrelated serialization bug — the write had already happened).
4. Bulk status endpoint updated any task id regardless of ownership.
5. D6: project creation dropped `pm_id`; update accepted any `pm_id`, including another tenant's user and a takeover by another PM; bulk update likewise.
A first version of the v1 tests passed vacuously because v1 routes authenticate with the `api` guard (401 before any logic); this was caught by probing and the baseline was re-run — the RED/GREEN figures above are from the corrected tests.

## E. Exact code changes (S4b)
`app/Policies/TaskPolicy.php` (update/delete/assign rewritten; `task.delete` inference removed); `app/Http/Controllers/Api/TaskController.php` (+`addDependency`, `removeDependency`, `assignUser` guards); `app/Http/Controllers/Api/TaskAssignmentController.php` (assign guard + tenant-bound assignee, on store/update/destroy); `src/CoreProject/Controllers/TaskAssignmentController.php`, `src/CoreProject/Controllers/TaskController.php` (guards via canonical task row; 404 for missing task on store); `src/CoreProject/Requests/StoreTaskAssignmentRequest.php` (`integer`→`string` for task_id/user_id); `app/Services/BulkOperationsService.php` (per-task update policy; pm_id rule); `app/Support/ProjectPmAssignment.php` (new); `app/Services/ProjectService.php` (persist/validate `pm_id`); `app/Http/Requests/ProjectFormRequest.php` (`pm_id` must exist in the caller's tenant); `app/Http/Controllers/Api/ProjectController.php` (`update` applies the rule). No route, seed, grant, migration or UI change; `client_id`/`priority` untouched.
**Fixture corrections** (original intent preserved — "the acting user is the task's assignee / project PM and holds the canonical permission"; **no assertion changed**): `TaskTest::test_update_task`; `Api\TaskDependenciesTest` (5 tests); `Api\V1TasksCompatibilityCrudTest` (persona gets `task.update`/`task.delete` via a new optional, backward-compatible parameter of `apiActingAsTenantAdmin`); `Api\V1TaskAssignmentsCompatibilityTest` and `…V1DashboardTaskAssignmentsCompatibilityTest` (previously ran **with no authenticated user at all**; now an acting PM with `task.assign`; `pm_id` set through the DB because the `Src` Project model does not mass-assign it); `BulkOperationsTest::test_can_bulk_update_task_status` (acting user is the assignee); `Zena\OperatorPlatformUiTest::test_schedule_page_supports_task_crud` (operator is the project PM); `Zena\BlockerTest`, `Api\TaskApiTest`, `Api\IntegrationTest` (S4 round); the two S4 tests of mine that encoded the superseded `task.delete` inference were rewritten to the O-10 contract.

## F. D6 findings (pm_id only)
Root cause: `ProjectService::createProject` builds the row from a fixed field list that omitted `pm_id` (the web/API layers did pass it). `updateProject` already persisted it but accepted any value (not tenant-checked, any `project.update` holder could take over, bulk path unguarded). Verified: A create with authorised PM persists (API and web); B read-back through the list API; C DB row; D/E creator-PM then creates and manages its project's tasks; F another PM denied; G a Member/other PM/System Admin cannot take over (web, API, bulk); H cross-tenant `pm_id` → 422 and no project created; I update without `pm_id` preserves it; the current PM may hand over; an unmanaged (null-PM) project can be claimed by a `project.update` holder (documented choice — **O-15**).
Note: `GET api/projects/{id}` independently requires legacy `Admin`/`SuperAdmin` or team membership (untouched).

## G. RED → GREEN
`UatRecovery04bTaskSurfaceTest` 17 tests: BEFORE (`s4b-BEFORE.txt`) 11 FAIL; AFTER (`s4b-AFTER.txt`) **17/17 PASS (141 assertions)**. The labelled adversarial alias fixture (group `uat-recovery-04b-alias`, defines `task.edit` for Members/PMs/Admins **inside the test only**) shows an unrelated Member/other PM/System Admin/observer/foreign-tenant admin cannot mutate via `api/v1/tasks` even if the alias existed, while the assignee and owning PM pass authorization. Mutation checks: re-adding `task.delete`⇒update fails 4 tests; `ProjectPmAssignment` always-allow fails the takeover test; `assign()` without the PM requirement fails the assignment test.

## H. Full regression vs the exact S4 baseline (`evidence/s4d-*`)
| | S4 baseline | S4b |
|---|---|---|
| Api | 798 tests, 5171 assertions, 0 failures | 798 tests, 5171 assertions, **0 failures** |
| Feature | 1822 tests, 1 error + 18 failures, 21 skipped | 1839 tests (+17 new), 1 error + 23 failures, 21 skipped |
New relevant regressions: **0**. Remaining failures = 4 Recovery-01 RED (D2 PM API gate, D2 create-users, D3 route permissions [12 aliases], D3 my-work) + 15 pre-existing (`DashboardApiTest` 7, `GAP059 ConfigureSmtp` 7, `BadgeApiRetired` 1) **+ 5 `Console\Backup*` tests that are environmental**: they run a real `backup:run` over `storage/` (644 MB) and gave contradictory results on identical code (BackupContentsSafety: fail in full run, then 1 fail + 1 pass on isolated reruns; BackupRetention: pass alone, failed only inside full runs, and passed in the previous two full runs). Not touched by any S1–S4b change.
Intermediate full runs were used to find fixture conflicts (listed in §E); none was hidden or re-classified without inspection. SKIPPED 21 pre-existing; BLOCKED 0.

## I. Independent security review
- No unauthorized task mutation found through any in-scope endpoint (13 mutation surfaces enumerated, §B); denied requests assert zero change of the task row, its assignment rows and dependency rows; clean 403 (not 500) asserted on the main surfaces.
- No permission or role granted anywhere; assignment grants no RBAC (asserted via role_permission count). `task.delete` implies nothing but delete. No legacy read; no admin bypass; tenant check in the policy itself.
- **Behavioural narrowings to acknowledge (documented, not defects):** System Admin loses cross-project task update/assign/dependency/bulk-status authority unless PM/assignee (O-10); users who relied on `api/tasks*`/schedule/`api/v1/task-assignments` with only a legacy role value are now denied; bulk status now reports `Forbidden` per task.
- Medium (not fixed, out of scope): assignees may still complete tasks at any progress (O-12); `Src\CoreProject` `updateStatus/assignUser/updateDependencies/toggleVisibility/recalculateSchedule` are unrouted but unguarded — if ever routed they must call the same policy; `compensation.*` (unseeded/ungranted) and the `project_tasks` work-template controller have no object checks; `api/projects/{id}` show uses `hasRole(['SuperAdmin','Admin'])` (legacy casing).
- No Critical/High open for the S4b scope.

## J. MySQL
**MYSQL_NOT_VERIFIED** (SQLite only). New logic is boolean permission checks, one EXISTS on `task_assignments`, one tenant-scoped EXISTS on `users`.

## K. Remaining Recovery-01 RED tests (4)
`D3_every_rbac_route_permission_exists_in_canonical_seed` (12 aliases), `D2_user_with_only_seeded_project_manager_role_passes_api_gate`, `D3_project_member_can_open_my_work`, `D2_no_seeded_user_can_create_users_through_canonical_api`.

## L. Owner decisions still required
- **O-12** (deferred, audited): today there is no transition table or completion prerequisite — any status→any status, completion at 0 %, `completed→pending` reopen, assignee may complete; `Task::VALID_STATUSES` = pending/in_progress/completed/on_hold/cancelled plus aliases todo/done accepted by some endpoints. Pinned by `test_OWNER_DECISION_lifecycle_has_no_transition_or_completion_guards_today`. A separate business-rule proposal (who may complete, prerequisites, reopen authority) is needed before any guard is built.
- **O-14** an administrative "manage any task" capability for System Admin: needs a distinct permission (belongs with the unresolved 12 aliases); until then admins act as PM/assignee.
- **O-15** whether a `project.update` holder may claim a project that has no PM (current behaviour: yes).
- Carried over: 12 aliases, 10 Member grants, parameterless gate for the other ~300 routes (O-8), backfill dry-run, MySQL.

## M. Recommended next bounded slice
S4c: (1) guard the unrouted `Src\CoreProject` task actions and `compensation.*` with the same policy before they are ever mounted; (2) replace the 5 environment-dependent `Backup*` tests' repo-wide scan with a disposable fixture (test hygiene); (3) draft the O-12 lifecycle proposal. Optionally S3b-style per-group permission mapping of the remaining parameterless-only routes once O-8 is decided.
