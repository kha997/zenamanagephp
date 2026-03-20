# 1) EXECUTIVE VERDICT
- changed
- `/api/zena/tasks*` và `/api/v1/tasks*` chưa parity ở mức contract runtime.
- Canonical `zena` surface là owner rõ ràng cho CRUD, status, và dependency endpoints với middleware stack riêng và envelope riêng.
- Compatibility `v1/tasks` hiện là một mounted-and-reconciled compatibility CRUD surface; parity vẫn khác canonical ở owner/middleware/envelope shape, nhưng CRUD mounted contract đã được verify green trong suite mục tiêu.
- Sau 4 slice đã hoàn tất, source-only dead declarations của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics` đã bị xóa khỏi active source, `/api/v1/task-assignments*` là mounted-and-reconciled flat compatibility surface, `/api/v1/dashboard/*assignments*` là mounted-and-reconciled dashboard compatibility surface đã có feature-level verification, và `/api/v1/tasks*` CRUD đã được reconcile theo mounted runtime contract.
- Không có runtime change trong round audit này; trạng thái mới được phản ánh từ route/source hiện tại và tests kiến trúc đang pass.

# 2) EXACT FINDINGS

## Canonical `/api/zena/tasks` contract

Evidence chính:
- `routes/api_zena.php`
- `app/Http/Controllers/Api/TaskController.php`
- `app/Http/Controllers/Api/Concerns/ZenaContractResponseTrait.php`
- `app/Models/Task.php`
- `tests/Feature/Api/TaskApiTest.php`
- `tests/Feature/Api/TaskDependenciesTest.php`
- `tests/Feature/Buttons/ButtonCRUDTest.php`

Route inventory:
- `GET /api/zena/tasks` -> `App\Http\Controllers\Api\TaskController@index`
- `POST /api/zena/tasks` -> `App\Http\Controllers\Api\TaskController@store`
- `GET /api/zena/tasks/{id}` -> `App\Http\Controllers\Api\TaskController@show`
- `PUT /api/zena/tasks/{id}` -> `App\Http\Controllers\Api\TaskController@update`
- `DELETE /api/zena/tasks/{id}` -> `App\Http\Controllers\Api\TaskController@destroy`
- `PATCH /api/zena/tasks/{id}/status` -> `App\Http\Controllers\Api\TaskController@updateStatus`
- `GET /api/zena/tasks/{id}/dependencies` -> `App\Http\Controllers\Api\TaskController@getDependencies`
- `POST /api/zena/tasks/{id}/dependencies` -> `App\Http\Controllers\Api\TaskController@addDependency`
- `DELETE /api/zena/tasks/{id}/dependencies/{dependencyId}` -> `App\Http\Controllers\Api\TaskController@removeDependency`

Middleware/RBAC:
- Shared stack from route-list: `auth:sanctum`, `tenant.isolation`, `input.sanitization`, `error.envelope`
- RBAC:
  - list/show: `task.view`
  - create: `task.create`
  - update: `task.update`
  - delete: `task.delete`
  - status: `task.update-status`
  - dependency list/add/remove: `task.dependencies.view|add|remove`

Tenant isolation assumptions:
- `show`, `update`, `destroy`, `updateStatus`, `getDependencies`, `addDependency`, `removeDependency` explicitly scope by resolved tenant id.
- `index` scopes by authenticated user tenant.
- `store` writes `tenant_id` from authenticated user.

Request fields / validation shape:
- `index` filters: `project_id`, `status`, `priority`, `assignee_id`, `watcher_id`, `overdue`, `due_soon`, `due_soon_days`, `search`, `per_page`
- `store` validates:
  - required: `project_id`, `name`
  - optional: `title`, `description`, `status`, `priority`, `assignee_id`, `start_date`, `end_date`, `estimated_hours`, `actual_hours`, `spent_hours`, `parent_id`, `order`, `dependencies[]`, `watchers[]`, `tags[]`, `is_hidden`, `visibility`, `client_approved`
  - status source-of-truth: `App\Models\Task::VALID_STATUSES`
  - priority source-of-truth: `App\Models\Task::VALID_PRIORITIES`
- `update` validates only a narrower subset:
  - `name`, `description`, `status`, `priority`, `start_date`, `end_date`, `estimated_hours`, `actual_hours`, `dependencies[]`
  - accepted status values are hard-coded to `todo,in_progress,done,pending`
  - accepted priority values are hard-coded to `low,medium,high,urgent`
- `updateStatus` validates `status` as `todo,in_progress,done,pending`
- `addDependency` and `removeDependency` validate `dependency_id`

Response envelope / pagination / status codes:
- Success envelope usually: `success`, `status`, `status_text`, `data`, optional `message`
- Paginated list envelope:
  - `data` is item array
  - `meta.pagination.page|per_page|total|last_page`
- Observed success status codes from controller:
  - index/show/update/status/dependency ops/delete: `200`
  - create: `201`
- Error shapes drift inside canonical surface:
  - some unauthenticated paths return bare `{"error":"Unauthorized"}` or `{"error":"Unauthorized"}`-style payloads
  - validation in `store` and dependency mutations returns `success: false` payloads with `errors`
  - `show/update/destroy/updateStatus` use `errorResponse()`-style `status: error`
- Canonical surface is therefore owner, but not internally envelope-perfect.

## Compatibility `/api/v1/tasks` contract

Evidence chính:
- `Src/CoreProject/routes/api.php`
- `Src/CoreProject/Controllers/TaskController.php`
- `Src/CoreProject/Requests/StoreTaskRequest.php`
- `Src/CoreProject/Requests/UpdateTaskRequest.php`
- `Src/CoreProject/Resources/TaskResource.php`
- `Src/Foundation/Utils/JSendResponse.php`
- `Src/Shared/Requests/BaseApiRequest.php`
- `tests/Feature/TaskTest.php`

Mounted CRUD inventory:
- `GET /api/v1/tasks` -> `Src\CoreProject\Controllers\TaskController@index`
- `POST /api/v1/tasks` -> `Src\CoreProject\Controllers\TaskController@store`
- `GET /api/v1/tasks/{task}` -> `Src\CoreProject\Controllers\TaskController@show`
- `PUT /api/v1/tasks/{task}` -> `Src\CoreProject\Controllers\TaskController@update`
- `PATCH /api/v1/tasks/{task}` -> `Src\CoreProject\Controllers\TaskController@update`
- `DELETE /api/v1/tasks/{task}` -> `Src\CoreProject\Controllers\TaskController@destroy`

Middleware/RBAC:
- Shared stack from route-list: `auth:api`, `tenant.isolation`
- No `input.sanitization`
- No `error.envelope`
- RBAC:
  - list/show: `task.view`
  - create: `task.create`
  - update: `task.edit`
  - delete: `task.delete`

Tenant isolation assumptions:
- Enforced primarily by middleware.
- Feature evidence now covers tenant-boundary rejection for foreign tasks on the mounted compatibility show path.
- Any broader tenant guarantee outside the audited CRUD family still requires route-specific evidence.

Mounted CRUD reconciliation evidence:
- `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php` passed with 7 tests on the mounted CRUD family.
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php` passed with 4 tests across the architecture invariants that now describe the CRUD family as mounted and reconciled in source.
- Root causes handled in the completed CRUD slice:
  - controller signature mismatch
  - `UpdateTaskRequest` constant mismatch
  - `TaskService` id/ulid mismatch on update
  - DateTime normalization issue
  - sqlite json contains delete fallback

Request fields / validation shape where evidence exists:
- `store` is evidence-backed through `StoreTaskRequest`
  - required: `project_id`, `name`
  - optional: `component_id`, `phase_id`, `description`, `start_date`, `end_date`, `status`, `priority`, `dependencies[]`, `conditional_tag`, `is_hidden`, `estimated_hours`, `actual_hours`, `progress_percent`, `tags[]`, `visibility`, `client_approved`, `assignments[*]`
  - defaults injected before validation:
    - `status = pending`
    - `priority = medium`
    - `visibility = internal`
    - `client_approved = false`
    - numeric hours/progress default to zero
- `update` is evidence-backed through `UpdateTaskRequest`
  - optional: `component_id`, `phase_id`, `name`, `description`, `start_date`, `end_date`, `status`, `priority`, `dependencies[]`, `conditional_tag`, `is_hidden`, `estimated_hours`, `actual_hours`, `progress_percent`, `tags[]`, `visibility`, `client_approved`
  - extra validator hooks enforce dependency cycle, date logic, status transition, progress consistency
- Runtime status codes and payloads for list/show/update/delete on mounted `/api/v1/tasks*` routes are now evidence-backed by `V1TasksCompatibilityCrudTest` for the mounted compatibility surface.

Response envelope / pagination where evidence exists:
- Source helper is JSend-like:
  - success: `status: success`, optional `data`, optional `message`
  - validation fail from `BaseApiRequest`: `status: fail`, `message`, `data.validation_errors`
  - errors: `status: error`, `message`, optional `data`
- `index` source intends to return:
  - `data.tasks`
  - `data.pagination.current_page|last_page|per_page|total`
- `store` source intends to return:
  - `status: success`
  - `data.task`
  - `message`
  - HTTP `201`

## Related dependency / status / assignment / watcher endpoints

Canonical owner:
- `/api/zena/tasks/{id}/status`
- `/api/zena/tasks/{id}/dependencies`

Compatibility-related, but not owned by `Src\CoreProject\Controllers\TaskController` CRUD routes:
- Historical `/api/v1/tasks/{task}/*` status/dependency/watcher/assign helper declarations are no longer active source claims and are not mounted in the current runtime route collection.
- Task assignment CRUD remains additionally mounted at `/api/v1/task-assignments*` via `Src\CoreProject\Controllers\TaskAssignmentController`.
- Result: compatibility task surface is still split by owner families, but the evidence-backed split now centers on a reconciled `/api/v1/tasks*` CRUD surface, flat `/api/v1/task-assignments*`, mounted-and-reconciled dashboard assignment routes, and work-template projection routes.

## Parity matrix

| Operation | `/api/zena/tasks*` | `/api/v1/tasks*` | Classification |
| --- | --- | --- | --- |
| list/index | mounted, tenant-aware, paginated `data + meta.pagination` | mounted-and-reconciled compatibility list with JSend `data.tasks + data.pagination` | `compatible-but-different` |
| create | mounted, `201`, canonical app controller, wider task/watcher fields | mounted, `201` source intent, src request/resource/JSend | `compatible-but-different` |
| show | mounted, tenant-scoped by task id | mounted-and-reconciled compatibility show using `{task}` route param as source of truth | `compatible-but-different` |
| update | `PUT` only, canonical app controller, narrower field set than create | mounted-and-reconciled compatibility `PUT` + `PATCH` update contract with richer rules than canonical | `compatible-but-different` |
| delete | mounted, `200`, empty-object-ish zena success payload | mounted-and-reconciled compatibility delete path with sqlite fallback triage covered by tests | `compatible-but-different` |
| status update | first-class canonical route | no status route on `Src\CoreProject` CRUD owner; status mounted separately under app controller in `routes/api.php` | `contract-drift` |
| dependency endpoints | first-class canonical routes | no dependency route on `Src\CoreProject` CRUD owner; dependency routes mounted separately under app controller in `routes/api.php` | `contract-drift` |
| assignee / watcher / related assignment ops | no first-class `/api/zena/tasks/{id}/assign` or watcher routes in reviewed canonical routes | dead `/api/v1/tasks/{task}/*` declarations removed from active source; surviving assignment compatibility surface is the flat `/api/v1/task-assignments*` family | `contract-drift` |

## Drift load-bearing nhất

1. Canonical and compatibility success envelopes are different even after CRUD reconciliation:
   - canonical list: `data + meta.pagination`
   - compatibility list: `data.tasks + data.pagination`
2. Canonical middleware stack includes `input.sanitization` and `error.envelope`; compatibility CRUD stack does not.
3. RBAC codes drift:
   - canonical update: `task.update`
   - compatibility update: `task.edit`
   - canonical status/dependency permissions are more granular.
4. Task-related compatibility endpoints remain split-owner with narrower evidence-backed scope:
   - CRUD owner: `Src\CoreProject\Controllers\TaskController`
   - flat assignment compatibility owner: `Src\CoreProject\Controllers\TaskAssignmentController`
   - dashboard assignment family as mounted-and-reconciled dashboard compatibility surface: `App\Http\Controllers\Api\TaskAssignmentController`
   - work-template projection family: `Src\WorkTemplate\Controllers\ProjectTaskController`

## Projection boundary với `/api/v1/work-template/projects/{projectId}/tasks`

- This surface is adjacent projection runtime, not competing canonical owner.
- It is project-scoped and conceptually template/projection-specific.
- Its runtime project-scoped surface has now been shrunk to the declarations with current method evidence:
  - `GET /api/v1/work-template/projects/{projectId}/tasks`
  - `GET /api/v1/work-template/projects/{projectId}/tasks/conditional`
  - `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress`
  - `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional`
- Current verify evidence for the kept projection routes:
  - `php artisan test tests/Feature/ProjectTaskControllerTest.php`
  - `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
  - `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
  - `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- Current behavior for the 6 mounted projection routes:
  - `GET /api/v1/work-template/projects/{projectId}/tasks` -> `200`
  - `GET /api/v1/work-template/projects/{projectId}/tasks/conditional` -> pass
  - `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}` -> mounted
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}` -> `200`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress` -> `422` invalid payload / `200` valid existing / `404` missing task
  - `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional` -> pass
- Removed declarations are no longer part of the runtime surface; any broader underlying implementation debt on kept routes is `UNKNOWN` unless backed by new evidence.
- Any future convergence between canonical tasks and this projection must be a `Change Proposal`.

## UNKNOWN

- Whether any external clients rely on the split-owner compatibility sub-operations as a single contract family is `UNKNOWN`.
- Whether there is any still-relevant underlying class/request/helper mismatch that materially changes the kept 6-route runtime behavior is `UNKNOWN`.

# 3) EXACT FILES TOUCHED
- `docs/audits/2026-03-19-tasks-contract-parity-audit.md`
- `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`

# 4) TESTS / VERIFICATION
- `php artisan route:list --path=api/zena/tasks`
- `php artisan route:list --path=api/v1/tasks`
- `php artisan route:list --path=api/v1/task-assignments`
- `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- `php artisan route:list --path=api/v1/work-template/projects/ --json`
- `php artisan test tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php`
- `php artisan test tests/Feature/ProjectTaskControllerTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- dashboard route inventory confirms 6 mounted routes/actions under `/api/v1/dashboard/*assignments*`
- Pass/fail recorded in final turn output
- Any broader suite not explicitly run: `UNKNOWN`

# 5) RISK ASSESSMENT
- Runtime: medium primarily because work-template projection remains an adjacent compatibility surface with only the current 6-route behavior positively evidenced; anything deeper than that is `UNKNOWN` until separately verified.
- Compatibility: medium because clients may perceive one `/api/v1/tasks*` family, but runtime ownership is still split across different controllers/envelopes/guards outside the reconciled CRUD family.
- Contributor confusion: high because docs can truthfully say `/api/v1/tasks` CRUD is reconciled compatibility runtime while the broader task-related sub-operations are still not single-owner.
- Maintenance: medium because duplicate route blocks across compatibility families still create update drift risk.

# 6) SSOT UPDATE
- After this round, Tasks parity should be understood as: `/api/zena/tasks*` is the forward business owner; `/api/v1/tasks*` is a mounted-and-reconciled compatibility CRUD surface with intentionally different compatibility contract choices; source-only `/api/v1/tasks/{task}/*` and `/api/v1/tasks/statistics` declarations are no longer active source claims; `/api/v1/task-assignments*` is a mounted-and-reconciled flat compatibility surface; `/api/v1/dashboard/*assignments*` is a mounted-and-reconciled dashboard compatibility surface verified by route inventory plus feature tests; `/api/v1/work-template/projects/{projectId}/tasks*` remains adjacent projection runtime with 24 declarations shrunk down to 6 mounted routes whose current behavior is now evidence-backed, not a second canonical tasks owner.

# 7) NEXT ROUND
- Implemented slices reflected by current evidence:
  - xóa source-only dead declarations của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics`
  - reconcile mounted flat contract cho `/api/v1/task-assignments*`
  - reconcile dashboard assignment family `/api/v1/dashboard/*assignments*`
  - reconcile `/api/v1/tasks*` CRUD signatures + update success-path + delete runtime triage
- Verified by:
  - `php artisan route:list --path=api/v1/tasks`
  - `php artisan route:list --path=api/v1/task-assignments`
  - `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`
  - `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
  - `php artisan test tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php`
  - dashboard route inventory confirms 6 mounted routes/actions under `/api/v1/dashboard/*assignments*`
- Remaining open mismatches:
  - broader implementation debt under kept work-template projection routes: `UNKNOWN`
  - any still-unknown broader JSON/dependency consistency debt outside this CRUD family: `UNKNOWN`
- Next recommended slice: `WORK-TEMPLATE PROJECTION EVIDENCE-ONLY DOC SSOT MAINTENANCE`

commit-ready summary: add SSOT tasks parity audit doc and invariant tests that lock current route-owner, middleware, envelope, and projection wiring findings without changing runtime behavior
