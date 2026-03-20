# 1) EXECUTIVE VERDICT
- changed
- `/api/v1/tasks*` hiện là một mounted-and-reconciled compatibility CRUD surface ở runtime, nhưng broader task-related `/api/v1/*` namespace vẫn là split-owner.
- Mounted CRUD compatibility owner của `/api/v1/tasks*` là `Src\CoreProject\Controllers\TaskController`, nhưng các mounted task-related routes khác lại tách sang `Src\CoreProject\Controllers\TaskAssignmentController`, `App\Http\Controllers\Api\TaskAssignmentController`, và `Src\WorkTemplate\Controllers\ProjectTaskController`.
- Work-template projection family đã shrink từ 24 declarations xuống còn 6 mounted routes; kept routes hiện phải được mô tả theo current runtime behavior đã verify, còn broader debt bên dưới chỉ nên gọi là `UNKNOWN` nếu chưa có evidence trực tiếp.
- Sau 4 slice đã implemented, `/api/v1/task-assignments*` phải được gọi là mounted-and-reconciled flat compatibility surface, `/api/v1/dashboard/*assignments*` là mounted-and-reconciled dashboard compatibility surface, và `/api/v1/tasks*` CRUD là mounted-and-reconciled compatibility CRUD surface; phần chưa làm còn lại tập trung ở work-template projection family.
- Round audit này chỉ chốt evidence bằng docs/tests; không đổi runtime, không remount route, không sửa business logic.

# 2) EXACT FINDINGS

## mounted v1 task owner inventory

### canonical task CRUD overlap

| method | uri | controller@method | middleware | request class | response intent from source | required route params | params match signature | classification |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/tasks` | `Src\CoreProject\Controllers\TaskController@index` | `auth:api`, `tenant.isolation`, `rbac:task.view` | `Illuminate\Http\Request` | `JSendResponse::success` with `data.tasks` + `data.pagination` | none | yes, mounted compatibility contract is reconciled and verified | `mounted-and-reconciled-compatibility-crud` |
| POST | `/api/v1/tasks` | `Src\CoreProject\Controllers\TaskController@store` | `auth:api`, `tenant.isolation`, `rbac:task.create` | `Src\CoreProject\Requests\StoreTaskRequest` | `JSendResponse::success` with `data.task`, `message`, HTTP `201` | none | yes, request resolves `project_id` from body or route | `owned-compatibility` |
| GET | `/api/v1/tasks/{task}` | `Src\CoreProject\Controllers\TaskController@show` | `auth:api`, `tenant.isolation`, `rbac:task.view` | none | `JSendResponse::success` with `data.task` | `task` | yes, `{task}` is the mounted source of truth | `mounted-and-reconciled-compatibility-crud` |
| PUT | `/api/v1/tasks/{task}` | `Src\CoreProject\Controllers\TaskController@update` | `auth:api`, `tenant.isolation`, `rbac:task.edit` | `Src\CoreProject\Requests\UpdateTaskRequest` | `JSendResponse::success` with `data.task`, `message` | `task` | yes, mounted compatibility update contract is verified | `mounted-and-reconciled-compatibility-crud` |
| PATCH | `/api/v1/tasks/{task}` | `Src\CoreProject\Controllers\TaskController@update` | `auth:api`, `tenant.isolation`, `rbac:task.edit` | `Src\CoreProject\Requests\UpdateTaskRequest` | `JSendResponse::success` with `data.task`, `message` | `task` | yes, mounted compatibility update contract is verified | `mounted-and-reconciled-compatibility-crud` |
| DELETE | `/api/v1/tasks/{task}` | `Src\CoreProject\Controllers\TaskController@destroy` | `auth:api`, `tenant.isolation`, `rbac:task.delete` | none | `JSendResponse::success` with `message` | `task` | yes, delete fallback is verified on mounted contract | `mounted-and-reconciled-compatibility-crud` |

### assignment-related compatibility operations

| method | uri | controller@method | middleware | request class | response intent from source | required route params | params match signature | classification |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/task-assignments` | `Src\CoreProject\Controllers\TaskAssignmentController@index` | `auth:api`, `tenant.isolation`, `rbac:task.assign` | `Illuminate\Http\Request` | `JSendResponse::success` with `data.assignments` | none | yes | `mounted-and-reconciled-flat-compatibility` |
| POST | `/api/v1/task-assignments` | `Src\CoreProject\Controllers\TaskAssignmentController@store` | `auth:api`, `tenant.isolation`, `rbac:task.assign` | `Src\CoreProject\Requests\StoreTaskAssignmentRequest` | `JSendResponse::success` with `data.assignment`, HTTP `201` | none | yes | `mounted-and-reconciled-flat-compatibility` |
| GET | `/api/v1/task-assignments/{taskAssignment}` | `Src\CoreProject\Controllers\TaskAssignmentController@show` | `auth:api`, `tenant.isolation`, `rbac:task.view` | none | `JSendResponse::success` with `data.assignment` | `taskAssignment` | yes | `mounted-and-reconciled-flat-compatibility` |
| PUT | `/api/v1/task-assignments/{taskAssignment}` | `Src\CoreProject\Controllers\TaskAssignmentController@update` | `auth:api`, `tenant.isolation`, `rbac:task.assign` | `Src\CoreProject\Requests\UpdateTaskAssignmentRequest` | `JSendResponse::success` with `data.assignment`, `message` | `taskAssignment` | yes | `mounted-and-reconciled-flat-compatibility` |
| PATCH | `/api/v1/task-assignments/{taskAssignment}` | `Src\CoreProject\Controllers\TaskAssignmentController@update` | `auth:api`, `tenant.isolation`, `rbac:task.assign` | `Src\CoreProject\Requests\UpdateTaskAssignmentRequest` | `JSendResponse::success` with `data.assignment`, `message` | `taskAssignment` | yes | `mounted-and-reconciled-flat-compatibility` |
| DELETE | `/api/v1/task-assignments/{taskAssignment}` | `Src\CoreProject\Controllers\TaskAssignmentController@destroy` | `auth:api`, `tenant.isolation`, `rbac:task.assign` | none | `JSendResponse::success` with `message` | `taskAssignment` | yes | `mounted-and-reconciled-flat-compatibility` |

### mounted task-related routes under another family contributors can confuse with the same surface

| method | uri | controller@method | middleware | request class | response intent from source | required route params | params match signature | classification |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/dashboard/tasks/{taskId}/assignments` | `App\Http\Controllers\Api\TaskAssignmentController@getTaskAssignments` | `auth:sanctum`, `tenant.isolation`, `rbac` | none | `successResponse` with task-scoped assignments | `taskId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |
| POST | `/api/v1/dashboard/tasks/{taskId}/assignments` | `App\Http\Controllers\Api\TaskAssignmentController@store` | `auth:sanctum`, `tenant.isolation`, `rbac` | `Illuminate\Http\Request` | `successResponse` with created assignment, HTTP `201` | `taskId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |
| PUT | `/api/v1/dashboard/assignments/{assignmentId}` | `App\Http\Controllers\Api\TaskAssignmentController@update` | `auth:sanctum`, `tenant.isolation`, `rbac` | `Illuminate\Http\Request` | `successResponse` with updated assignment | `assignmentId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |
| DELETE | `/api/v1/dashboard/assignments/{assignmentId}` | `App\Http\Controllers\Api\TaskAssignmentController@destroy` | `auth:sanctum`, `tenant.isolation`, `rbac` | none | `successResponse` with delete message | `assignmentId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |
| GET | `/api/v1/dashboard/users/{userId}/assignments` | `App\Http\Controllers\Api\TaskAssignmentController@getUserAssignments` | `auth:sanctum`, `tenant.isolation`, `rbac` | none | `successResponse` with assignments list | `userId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |
| GET | `/api/v1/dashboard/users/{userId}/assignments/stats` | `App\Http\Controllers\Api\TaskAssignmentController@getUserStats` | `auth:sanctum`, `tenant.isolation`, `rbac` | none | `successResponse` with minimal stats payload | `userId` | yes | `split-owner-mounted-and-reconciled-dashboard-compatibility` |

### work-template projection operations

Routes below are mounted and project-scoped under `/api/v1/work-template/projects/{projectId}/*`. Per SSOT policy they are adjacent projection routes, not competing canonical task ownership.

Routes with evidence-backed controller methods:

| method | uri | controller@method | request class | required route params | params match signature | classification |
| --- | --- | --- | --- | --- | --- | --- |
| GET | `/api/v1/work-template/projects/{projectId}/tasks` | `Src\WorkTemplate\Controllers\ProjectTaskController@index` | `Illuminate\Http\Request` | `projectId` | yes | `owned-canonical-adjacent` |
| GET | `/api/v1/work-template/projects/{projectId}/tasks/conditional` | `Src\WorkTemplate\Controllers\ProjectTaskController@conditionalTasks` | none | `projectId` | yes | `owned-canonical-adjacent` |
| GET | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}` | `Src\WorkTemplate\Controllers\ProjectTaskController@show` | none | `projectId`, `taskId` | yes | `owned-canonical-adjacent` |
| PUT | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}` | `Src\WorkTemplate\Controllers\ProjectTaskController@update` | `Src\WorkTemplate\Requests\UpdateTaskRequest` | `projectId`, `taskId` | yes; current runtime behavior verified as `200` | `owned-canonical-adjacent-reconciled-runtime` |
| PUT | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress` | `Src\WorkTemplate\Controllers\ProjectTaskController@updateProgress` | `Illuminate\Http\Request` | `projectId`, `taskId` | yes; current runtime behavior verified as `422/200/404` | `owned-canonical-adjacent-reconciled-runtime` |
| POST | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional` | `Src\WorkTemplate\Controllers\ProjectTaskController@toggleConditional` | `Src\WorkTemplate\Requests\ToggleConditionalRequest` | `projectId`, `taskId` | yes; feature evidence pass | `owned-canonical-adjacent-reconciled-runtime` |

Projection declarations removed from runtime surface in this slice:

| method | uri | controller@method | evidence | classification |
| --- | --- | --- | --- | --- |
| POST | `/api/v1/work-template/projects/{projectId}/tasks` | `ProjectTaskController@store` | declaration removed because `store` method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional` | `ProjectTaskController@bulkToggleConditional` | declaration removed because method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/tasks/bulk-update` | `ProjectTaskController@bulkUpdate` | declaration removed because method not found | `implemented-removal` |
| DELETE | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}` | `ProjectTaskController@destroy` | declaration removed because method not found | `implemented-removal` |
| PUT | `/api/v1/work-template/projects/{projectId}/tasks/{taskId}/status` | `ProjectTaskController@updateStatus` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/conditional-tags` | `ProjectTaskController@getConditionalTags` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/conditional-tags/statistics` | `ProjectTaskController@getConditionalTagStats` | declaration removed because method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/conditional-tags/{tag}/toggle` | `ProjectTaskController@toggleConditionalTag` | declaration removed because method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/conditional-tags/bulk-toggle` | `ProjectTaskController@bulkToggleConditionalTags` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/phases` | `ProjectTaskController@getPhases` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/phases/{phaseId}/tasks` | `ProjectTaskController@getPhaseTask` | declaration removed because method not found | `implemented-removal` |
| PUT | `/api/v1/work-template/projects/{projectId}/phases/{phaseId}/reorder` | `ProjectTaskController@reorderPhase` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/reports/progress` | `ProjectTaskController@getProgressReport` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/reports/tasks-summary` | `ProjectTaskController@getTasksSummary` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/reports/conditional-usage` | `ProjectTaskController@getConditionalUsageReport` | declaration removed because method not found | `implemented-removal` |
| GET | `/api/v1/work-template/projects/{projectId}/template-sync/diff` | `ProjectTaskController@getTemplateDiff` | declaration removed because method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/template-sync/partial` | `ProjectTaskController@partialSync` | declaration removed because method not found | `implemented-removal` |
| POST | `/api/v1/work-template/projects/{projectId}/template-sync/apply-diff` | `ProjectTaskController@applyTemplateDiff` | declaration removed because method not found | `implemented-removal` |

Additional adjacent projection route outside the exact `/projects/{projectId}/tasks*` family:

| method | uri | controller@method | evidence | classification |
| --- | --- | --- | --- | --- |
| GET | `/api/v1/work-template/search/tasks` | `ProjectTaskController@searchTasks` | mounted route exists, `searchTasks` method not found | `signature-mismatch` |

## canonical overlap vs compatibility-only map

- Canonical overlap with real mounted runtime on both families:
  - task CRUD list/create/show/update/delete
- Compatibility-only mounted routes:
  - `/api/v1/task-assignments*`
  - `/api/v1/dashboard/tasks/{taskId}/assignments`
  - `/api/v1/dashboard/assignments/{assignmentId}`
  - `/api/v1/dashboard/users/{userId}/assignments*`
- Canonical-only mounted routes in reviewed task surface:
  - `/api/zena/tasks/{id}/status`
  - `/api/zena/tasks/{id}/dependencies`
  - `/api/zena/tasks/{id}/dependencies/{dependencyId}`
- Historical source-only task sub-operations now removed from active source and absent from the runtime route collection:
  - `/api/v1/tasks/{task}/status`
  - `/api/v1/tasks/{task}/assign`
  - `/api/v1/tasks/{task}/assign-team`
  - `/api/v1/tasks/{task}/dependencies`
  - `/api/v1/tasks/{task}/dependencies/{dependencyId}`
  - `/api/v1/tasks/{task}/watchers`
  - `/api/v1/tasks/statistics`

## split-owner map

- `owned-compatibility`
  - `/api/v1/tasks*` CRUD family under `Src\CoreProject\Controllers\TaskController`
- `split-owner`
  - mounted `/api/v1/tasks*` CRUD owner: `Src\CoreProject\Controllers\TaskController`
  - mounted `/api/v1/task-assignments*` owner: `Src\CoreProject\Controllers\TaskAssignmentController` as a mounted-and-reconciled flat compatibility surface
  - mounted `/api/v1/dashboard/*assignments*` owner: `App\Http\Controllers\Api\TaskAssignmentController` as a mounted-and-reconciled dashboard compatibility surface
  - mounted `/api/v1/work-template/projects/{projectId}/tasks*` owner: `Src\WorkTemplate\Controllers\ProjectTaskController` for the remaining 6-route projection surface
- `owned-canonical-adjacent`
  - only the evidence-backed project-scoped projection endpoints under `/api/v1/work-template/projects/{projectId}/tasks*` listed above
  - current behavior:
    - `GET /tasks` -> `200`
    - `GET /tasks/conditional` -> pass
    - `GET /tasks/{taskId}` -> mounted
    - `PUT /tasks/{taskId}` -> `200`
    - `PUT /tasks/{taskId}/progress` -> `422` invalid payload / `200` valid existing / `404` missing task
    - `POST /tasks/{taskId}/toggle-conditional` -> pass

## signature mismatch map

- `/api/v1/task-assignments`
  - implemented reconcile: mounted routes now match flat route params vs `Src\CoreProject\Controllers\TaskAssignmentController` signature
- `/api/v1/work-template/projects/{projectId}/tasks*`
  - missing-method declarations have been removed from the runtime route collection
  - kept routes are documented by current runtime behavior; any broader implementation debt beneath them is `UNKNOWN` unless new evidence is added

## projection boundary với `/api/v1/work-template/projects/{projectId}/tasks`

- This family is `owned-canonical-adjacent`, not a competing canonical task owner.
- Evidence for boundary:
  - prefix is `v1/work-template`
  - controller owner is `Src\WorkTemplate\Controllers\ProjectTaskController`
  - model owner is `Src\WorkTemplate\Models\ProjectTask`
  - policy docs already state this is projection-scoped
- The boundary is preserved after shrink; the route table no longer exposes the removed missing-method declarations.
- Any attempt to converge projection routes into general task runtime requires `Change Proposal`.

## top load-bearing drifts

- `/api/v1/tasks*` CRUD is now locked as a mounted-and-reconciled compatibility CRUD surface by feature and invariant evidence.
- `/api/v1/task-assignments*` is now mounted-and-reconciled to the flat route shape; this slice should stay closed.
- `/api/v1/dashboard/*assignments*` is now mounted-and-reconciled to the dashboard route shape; this slice should stay closed.
- Historical `/api/v1/tasks/{task}/status`, dependency, watcher, assignment helper routes, and `/api/v1/tasks/statistics` are no longer active source claims and are still absent from the runtime route collection.
- `/api/v1/work-template/projects/{projectId}/tasks*` is now a smaller 6-route mounted projection surface; removed declarations are out of runtime surface and kept routes are described by verified current behavior.
- Any broader JSON/dependency consistency debt should be treated as outside the closed CRUD slice unless new evidence ties it back.

## mọi UNKNOWN

- Whether any external client currently depends on the source-only but unmounted `/api/v1/tasks/{task}/*` sub-operation definitions is `UNKNOWN`.
- Whether any broader implementation debt beneath the kept work-template projection routes materially changes runtime beyond the verified behavior is `UNKNOWN`.

# 3) EXACT FILES TOUCHED
- `docs/audits/2026-03-19-tasks-v1-split-owner-route-inventory-audit.md`
- `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`

# 4) TESTS / VERIFICATION
- `php artisan route:list --json | jq -r '.[] | select(.uri | startswith("api/v1/tasks") or startswith("api/v1/task-assignments") or startswith("api/v1/work-template/projects/")) | [.method,.uri,.action] | @tsv'`
- `php artisan route:list --json | jq -r '.[] | select((.action|test("TaskController|TaskAssignmentController")) and (.uri|startswith("api/v1/"))) | [.method,.uri,.action] | @tsv'`
- `php artisan route:list --path=api/v1/tasks`
- `php artisan route:list --path=api/v1/task-assignments`
- `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- `php artisan test tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php`
- `php artisan test tests/Feature/ProjectTaskControllerTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- dashboard route inventory confirms 6 mounted routes/actions under `/api/v1/dashboard/*assignments*`
- pass/fail: see final turn output
- broader suites not run: `UNKNOWN`

# 5) RISK ASSESSMENT
- runtime
  - medium for work-template projection routes because the remaining 6-route surface is evidence-backed; deeper debt beyond that verified behavior is `UNKNOWN`.
- compatibility
  - medium because `/api/v1/task-assignments*`, dashboard assignments, and `/api/v1/tasks*` CRUD are reconciled, while work-template projection is now a smaller evidence-backed 6-route adjacent surface.
- contributor confusion
  - high because task-related compatibility behavior is split across four owners and one projection family under the same `/api/v1/*` namespace.
- maintenance
  - medium because duplicate route blocks still create drift risk across compatibility families.

# 6) SSOT UPDATE
- Sau round này, `/api/v1/tasks*` phải được hiểu là một mounted-and-reconciled compatibility CRUD family do `Src\CoreProject` mount. Task-related compatibility behavior rộng hơn vẫn split-owner sang `Src\CoreProject` task-assignments như một mounted-and-reconciled flat compatibility surface, `App` dashboard assignment routes như một mounted-and-reconciled dashboard compatibility surface, và `Src\WorkTemplate` projection routes; còn canonical forward business owner vẫn là `/api/zena/tasks*`.

# 7) NEXT ROUND
- Implemented slices reflected here:
  - xóa source-only dead declarations của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics`
  - reconcile mounted flat contract cho `/api/v1/task-assignments*`
  - reconcile dashboard assignment family `/api/v1/dashboard/*assignments*`
  - reconcile `/api/v1/tasks*` CRUD signatures + update success-path + delete runtime triage
- Remaining open mismatches:
  - broader implementation debt under kept work-template projection routes: `UNKNOWN`
  - any still-unknown broader JSON/dependency consistency debt outside this CRUD family: `UNKNOWN`
- Next recommended slice: `WORK-TEMPLATE PROJECTION EVIDENCE-ONLY DOC SSOT MAINTENANCE`

commit-ready summary: add a new SSOT audit for mounted `/api/v1/*` task route ownership, signature mismatches, source-vs-mounted drift, and projection boundaries, plus invariant coverage locking the current owner map and broken wiring
