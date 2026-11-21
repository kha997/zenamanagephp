# UAT RECOVERY 04 — S4 report: task ownership & lifecycle authorization

**DECISION: LOCAL_PATCH_VERIFIED (after Owner decision O-14 option A).** The S4 patch is applied in the working tree (`TaskPolicy`, `Api\TaskController`, `Web\TaskController`) together with 5 fixture amendments (§G). Not a staging/production approval. No commit/push.

## A. Repository snapshot & baseline verification
`main` @ `a0381f5e3d5abaa37dc2f21f7099a09e6234ab2d`. Tracked modifications at start of S4 (S1/S2/S3/S3b/O-9): `PermissionSeeder`, `ZenaPermissionsSeeder`, `RBACManager`, `DocumentPolicy`, `routes/api.php`, `RouteNameCollisionInvariantTest`, `ProjectApiTest`. Untracked UAT/recovery artifacts preserved.
**O-9 diff check (reported vs actual):** my earlier wording "4 assertions" meant 4 *test methods*. The actual diff is: `RouteNameCollisionInvariantTest` — 3 expected-middleware entries changed (`projects.store/update/destroy` gained `rbac:project.create/update/delete`, +comments) in 1 test method; `ProjectApiTest` — 3 test methods × 2 `assertJsonPath` lines (error code and message) = 6 assertion lines changed. Total **9 expectation changes in 4 test methods across 2 files**, nothing else in tests was touched (`git diff --stat`: 12 + 15 lines). Latest verified baseline (S3b final): Api 798 tests / 0 failures; Feature 1811 tests / 1 error + 19 failures / 21 skipped (5 Recovery-01 RED + 15 known pre-existing).

## B. Task authorization architecture (before)
Mutation entry points (from `route:list`, 48 mutating task-related routes inspected):
| Route | Gate | Controller | Object-level check before S4 |
|---|---|---|---|
| `PUT app/tasks/{task}` | `rbac:task.update` (Member holds) | `Web\TaskController@update` → `Api\TaskController@update` | none (tenant filter only) |
| `POST app/tasks/{task}/block`, `/unblock` | `rbac:task.update` | `Web\TaskController` | none |
| `PUT/DELETE api/zena/tasks/{id}`, `PATCH …/status` | `task.update` / `task.delete` / `task.update-status` | `Api\TaskController` | none |
| `POST operator/schedule/tasks/{id}` (+DELETE) | `task.update` / `task.delete` | `SchedulePageController` → `Api\TaskController` | none |
| `PUT/DELETE api/tasks/{task}` | parameterless gate only | `Api\TaskController` | none |
| `PUT/PATCH/DELETE api/v1/tasks/{task}`, work-template task routes | `task.edit` / `task.delete` (**`task.edit` not in seed ⇒ unreachable**) | `Src\CoreProject…` | none (out of S4 scope; see §I) |
| `api/tasks/bulk/status-change` | `task.update` | closure | stub — mutates nothing |
| Assignment endpoints (`api/dashboard/tasks/{id}/assignments`, `api/task-assignments`, `api/v1/task-assignments`) | generic gate / `task.assign` | `TaskAssignmentController` | none (out of S4 scope; §I) |
`Api\TaskController` is the single choke point for 9 of the routes above (web update, `api/tasks`, `api/zena`, schedule). Existing `TaskPolicy` was **never invoked and could not be**: the file lacked `use App\Models\User/Task`, so any call raised a `TypeError` (policy argument typed against `App\Policies\User`). Its rules also read legacy role names and ignored tenant and `task_assignments`.
Lifecycle: no transition table and no completion prerequisite exists (`Task` has only validation lists: `pending/in_progress/completed/on_hold/cancelled` plus aliases `todo/done`); the web form cannot set `assignee_id`, assignments live in `task_assignments` (UAT D5).

## C. Confirmed defects (RED before the patch, `evidence/s4-BEFORE.txt`)
1. Any holder of `task.update` (every Project Member) changes/completes any tenant task on all four mutating endpoint families (web update, block, api/zena update, schedule).
2. Observers and PMs of other projects mutate any task.
3. A Member carrying a legacy string (`admin`, `pm`, `project_manager`, `super_admin`, `designer`) is not elevated but also not restricted — the legacy-gated `api/tasks/{id}` PUT/DELETE let a `team_member` Member rename/complete/delete any task.
4. `TaskPolicy` dead (TypeError).

## D. Authorization matrix implemented (S4 contract; nothing granted)
| Operation | Route gate (unchanged) | Object rule (new) | Evidence |
|---|---|---|---|
| Update fields / status / complete / reopen, block, unblock | `task.update` (or generic gate on `api/tasks`) | tenant match AND (assignee **or** project PM holding `task.update` **or** holder of both `task.update`+`task.delete`) | S4 tests |
| Delete | `task.delete` / generic | tenant match AND (project PM holding `task.update` **or** holder of `task.update`+`task.delete`); assignee alone: no | `clean_403` guard |
| Assignee definition | — | `tasks.assignee_id` **or** `task_assignments(role=assignee)`; `reviewer`/`observer` rows do not count | tests (A, A2, observer) |
| Legacy `users.role` | — | never read | legacy-string test |
| Cross-tenant / System Admin | — | tenant-bound, no bypass | guard test |
Permission derivation for "full task authority" (**O-10, to confirm**): no existing permission distinguishes System Admin from Member for `task.update`; the rule "whoever may already delete ANY tenant task may update it" reuses seeded permissions (today only System Admin holds `task.delete`) and grants nothing new.

## E. Exact implementation diff (S4 only)
- `app/Policies/TaskPolicy.php`: add `use App\Models\Task; use App\Models\User;` (makes the policy invocable); rewrite `update()` and `delete()` + 4 private helpers (`sameTenant`, `isAssignee`, `isProjectManagerWithTaskUpdate`, `hasFullTaskAuthority`). `view/create/assign/comment` untouched (still legacy, still unused).
- `app/Http/Controllers/Api/TaskController.php`: `update()`, `updateStatus()` call `$user->can('update', $task)`, `destroy()` calls `can('delete', $task)`; deny → `403 Forbidden` JSON, before validation/mutation.
- `app/Http/Controllers/Web/TaskController.php`: `block()`/`unblock()` call `$this->authorize('update', $task)`.
- Tests: `tests/Feature/Recovery/UatRecovery04TaskOwnershipTest.php` (11 tests).
No route, seed, grant, migration, UI or other-module change.

## F. RED → GREEN
`evidence/s4-BEFORE.txt`: 4 RED defect tests (unassigned member, observer/other PM, legacy strings, legacy gate endpoint) + 1 owner-decision test (other-project PM currently manages); guards GREEN (assignee, PM/admin, cross-tenant, role-less). `evidence/s4-AFTER.txt`: 11/11 PASS (59→66 assertions after adding the clean-403 guard). A first implementation attempt passed the RED tests only because `TaskPolicy` raised a `TypeError` (500, "no mutation"); the positive guards then failed, exposing it — hence the added `…denials_are_clean_403_and_never_server_errors` test. Mutation checks: `update()⇒true` fails 6 tests; removing the `task_assignments` assignee path fails the assignee guard.

## G. Regression comparison with the S3b baseline (patch applied; `evidence/s4-full-feature.txt`, `s4-api.txt`)
| | S3b baseline | S4 patch applied |
|---|---|---|
| Feature | 1811 tests, 1 error + 19 failures, 21 skipped | 1822 tests (+11 new), 1 error + 23 failures, 21 skipped |
| Api | 798 tests, 0 failures | 798 tests, **3 failures** |
Diff of failing sets: **fixed (same scenario now GREEN): 1** — `UatRecovery01…::test_TASK_OWNERSHIP_non_assignee_member_cannot_change_task_status`. **NEW failures: 8**, classified:
- `Tests\Feature\Console\BackupRetentionTest::test_a_full_run_is_named_full` — flaky, **PASS when run alone** (file-system/time dependent); not attributable.
- `TaskTest::test_update_task`, `TaskTest::test_delete_task` — fixture user = legacy `admin` + `task.delete/edit` (no `task.update`); **turn GREEN after I derived "full authority" from `task.delete` alone** (final form of the patch).
- **5 CONTRACT CONFLICTS (remain RED with the patch):** `Zena\BlockerTest::test_block_requires_note_and_sets_fields`, `::test_unblock_clears_fields` (fixture: role name `admin` + `task.update`, unassigned task, PM-less project); `Api\TaskApiTest::test_can_update_task_status`, `Api\IntegrationTest::test_task_dependencies_integration`, `Api\TaskDependenciesTest::test_task_status_update_with_dependencies` (fixture `apiActingAsTenantAdmin`: legacy-default permissions + `project.*`, no assignment, no PM, no `task.delete`). Each performs exactly the case the proposed contract forbids (a user holding only `task.update` mutating an unassigned task of a PM-less project). They were green because they encode the old open behaviour; amending them is outside this slice.
After reverting the patch: those 5 + `TaskTest` pass again (TaskTest 9/9, BlockerTest 4/4, TaskApiTest 6/6, IntegrationTest 8/8, TaskDependenciesTest 10/10); final tree = S3b tree (+ untracked S4 test file; its 6 expected-RED tests are classified BLOCKED, see header comment). Known baseline failures unchanged (5→4 Recovery-01 RED when patched; 15 pre-existing unrelated).

## H. Security review
- Effective access **narrows only**: Member/other-project PM/observer/legacy-valued users lose the ability to mutate tasks they neither own nor manage; nobody gains access (holders of `task.update`+`task.delete`, assignees, and project PMs already could).
- Cross-tenant: policy checks tenant explicitly (in addition to the controller's tenant-scoped query).
- No `users.role` read; no admin alias; no new permission.
- Alternate endpoints checked: web update, block, unblock, api/zena update/status/destroy, schedule update/destroy, `api/tasks` PUT/DELETE (covered); bulk status-change (stub); `api/v1/tasks` & work-template task routes (unreachable today because `task.edit` is not seeded — **latent bypass if the alias is ever defined without adding the same check**, High-if-activated); assignment endpoints (not guarded, §I).
- No Critical/High finding open for the S4 scope.

## I. Remaining known defects / gaps
Latent: `Src\CoreProject\Controllers\TaskController` and `ProjectTaskController` (`task.edit` routes) have no object check. Assignment/reassignment endpoints (generic gate or `task.assign`) have no ownership/PM check (`TaskPolicy::assign` still legacy). `TaskPolicy::view/create/assign/comment` still use legacy roles. No lifecycle guards (any status→any status; assignee may complete at 0%). UAT D6: project creation drops `pm_id` ⇒ a PM who created a project via UI is not "the project PM" and cannot manage its tasks unless assigned (see O-11). Web task form cannot set an assignee; v1 assignment API requires integer ids.

## J. MySQL
MYSQL_NOT_VERIFIED. New code is one EXISTS query on `task_assignments` and boolean permission checks.

## K. Owner decisions still required
- **O-10** confirm "full task authority = holds `task.update` AND `task.delete`" (alternative: add a dedicated permission, which is a new grant).
- **O-11** project-PM rule depends on `projects.pm_id`, which project creation currently drops (UAT D6): approve fixing D6 or an additional "project creator may manage" rule; until then PMs rely on being assignee/admin.
- **O-12** completion/transition rules (who may complete; prerequisites; reopen) — none exist, none invented.
- **O-13** guard assignment/reassignment and `task.edit` routes in a follow-up.
- Carried over: 12 permission aliases, 10 Member grants, parameterless gate for the other ~300 routes, backfill dry-run, MySQL.

## K2. O-14 — RESOLVED (option A)
Owner approved amending the 5 conflicting fixtures with a legitimate ownership relation. Done as 6 added lines (`'assignee_id' => <acting user>` + comment `S4/O-14`) in 4 files: `tests/Feature/Zena/BlockerTest.php` (setUp task; covers 2 tests), `tests/Feature/Api/TaskApiTest.php` (1 task; `test_can_update_task_status`), `tests/Feature/Api/IntegrationTest.php` (2 tasks; `test_task_dependencies_integration`), `tests/Feature/Api/TaskDependenciesTest.php` (2 tasks; `test_task_status_update_with_dependencies`). No assertion, expectation or permission fixture changed. `git diff --stat`: 6 insertions, 0 deletions.

## L. Next bounded slice (proposal)
S4b: apply the same `TaskPolicy` decisions to `TaskAssignmentController` (assign/reassign/unassign) and to the `Src\CoreProject` task controllers before the `task.edit` alias is ever defined; then D6 (project creation keeps `pm_id`) so the PM rule is usable.

## M. Final decision
**LOCAL_PATCH_VERIFIED.** Conditions: approved S4 behaviour implemented (§D/§E); unauthorized task mutation blocked on web update, block, unblock, api/zena update/status/destroy, schedule update/destroy and `api/tasks` PUT/DELETE; authorized operations (assignee via column or assignment row, project PM, `task.delete` holder) still work; no permission/role grant and no legacy read; **no new relevant regression** in the final full run (below); unresolved business rules listed in §K/§I.
**Final-tree full run** (`evidence/s4b-full-feature.txt`, `s4b-api.txt`): Feature 1822 tests, 97816 assertions, **1 error + 18 failures**, 21 skipped; Api 798 tests, 5171 assertions, **0 failures**. Against the S3b baseline (Feature 1811, 1 error + 19 failures; Api 0): **NEW regressions: 0**; fixed: 1 (`UatRecovery01IdentityRbacTest::test_TASK_OWNERSHIP_non_assignee_member_cannot_change_task_status` — the same scenario now GREEN). Remaining known failures: 4 Recovery-01 RED (D2 PM API gate, D2 create-users, D3 route permissions [12 aliases], D3 my-work) + 15 pre-existing unrelated (`DashboardApiTest` 7, `GAP059 ConfigureSmtp` 7, `BadgeApiRetired` 1; also the earlier flaky `BackupRetention` did not fail this time). MYSQL_NOT_VERIFIED.
