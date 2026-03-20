# 1) EXECUTIVE VERDICT
- changed
- Drift chính của `/api/v1/*` task surface hiện không nằm ở narrative docs mà nằm ở chênh lệch giữa route source và mounted runtime.
- Sau 4 slice đã hoàn tất, `source-defined-but-unmounted` của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics` đã được reconcile bằng cách xóa khỏi active source, `/api/v1/task-assignments*` đã chuyển sang mounted-and-reconciled flat compatibility surface, `/api/v1/dashboard/*assignments*` đã chuyển sang mounted-and-reconciled dashboard compatibility surface, và `/api/v1/tasks*` CRUD đã chuyển sang mounted-and-reconciled compatibility CRUD surface.
- Các drift load-bearing còn lại tập trung ở split-owner compatibility boundary và bất kỳ JSON/dependency consistency debt rộng hơn nào còn nằm ngoài CRUD family này.
- `/api/v1/work-template/projects/{projectId}/tasks*` vẫn phải được hiểu là adjacent projection; narrative hiện tại phải phản ánh đúng là projection surface đã shrink còn 6 mounted routes và kept routes đã reconcile theo current runtime behavior, không phải kept-route runtime drift.
- Round này không đổi runtime; chỉ thêm audit SSOT và invariant tests. Nếu sửa runtime wiring về sau, cần đúng một `Change Proposal`.

# 2) EXACT FINDINGS

## source vs mounted matrix

### family: `/api/v1/tasks*`

| source-defined route | mounted runtime route | action | declared route params | controller method params | request class | method exists | classification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1/tasks` | yes | `Src\CoreProject\Controllers\TaskController@index` | none | mounted compatibility signature | `Illuminate\Http\Request` | yes | `mounted-and-reconciled-compatibility-crud` |
| `POST /api/v1/tasks` | yes | `Src\CoreProject\Controllers\TaskController@store` | none | `request` | `Src\CoreProject\Requests\StoreTaskRequest` | yes | `mounted-and-reconciled-compatibility-crud` |
| `GET /api/v1/tasks/{task}` | yes | `Src\CoreProject\Controllers\TaskController@show` | `task` | mounted compatibility signature | none | yes | `mounted-and-reconciled-compatibility-crud` |
| `PUT /api/v1/tasks/{task}` | yes | `Src\CoreProject\Controllers\TaskController@update` | `task` | mounted compatibility signature | `Src\CoreProject\Requests\UpdateTaskRequest` | yes | `mounted-and-reconciled-compatibility-crud` |
| `PATCH /api/v1/tasks/{task}` | yes | `Src\CoreProject\Controllers\TaskController@update` | `task` | mounted compatibility signature | `Src\CoreProject\Requests\UpdateTaskRequest` | yes | `mounted-and-reconciled-compatibility-crud` |
| `DELETE /api/v1/tasks/{task}` | yes | `Src\CoreProject\Controllers\TaskController@destroy` | `task` | mounted compatibility signature | none | yes | `mounted-and-reconciled-compatibility-crud` |
| `PATCH /api/v1/tasks/{task}/status` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `POST /api/v1/tasks/{task}/assign` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `POST /api/v1/tasks/{task}/assign-team` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `POST /api/v1/tasks/{task}/dependencies` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `GET /api/v1/tasks/{task}/dependencies` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `POST /api/v1/tasks/{task}/dependencies/{dependencyId}` | no | removed from active source; no mounted runtime route | `task, dependencyId` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `DELETE /api/v1/tasks/{task}/dependencies/{dependencyId}` | no | removed from active source; no mounted runtime route | `task, dependencyId` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `GET /api/v1/tasks/{task}/watchers` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `POST /api/v1/tasks/{task}/watchers` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `DELETE /api/v1/tasks/{task}/watchers` | no | removed from active source; no mounted runtime route | `task` | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |
| `GET /api/v1/tasks/statistics` | no | removed from active source; no mounted runtime route | none | `UNKNOWN` | `UNKNOWN` | `UNKNOWN` | `implemented-removal` |

### family: `/api/v1/task-assignments*`

| source-defined route | mounted runtime route | action | declared route params | controller method params | request class | method exists | classification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1/task-assignments` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@index` | none | `request` | `Illuminate\Http\Request` | yes | `mounted-and-reconciled-flat-compatibility` |
| `POST /api/v1/task-assignments` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@store` | none | `request` | `Src\CoreProject\Requests\StoreTaskAssignmentRequest` | yes | `mounted-and-reconciled-flat-compatibility` |
| `GET /api/v1/task-assignments/{taskAssignment}` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@show` | `taskAssignment` | `taskAssignment` | none | yes | `mounted-and-reconciled-flat-compatibility` |
| `PUT /api/v1/task-assignments/{taskAssignment}` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@update` | `taskAssignment` | `request, taskAssignment` | `Src\CoreProject\Requests\UpdateTaskAssignmentRequest` | yes | `mounted-and-reconciled-flat-compatibility` |
| `PATCH /api/v1/task-assignments/{taskAssignment}` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@update` | `taskAssignment` | `request, taskAssignment` | `Src\CoreProject\Requests\UpdateTaskAssignmentRequest` | yes | `mounted-and-reconciled-flat-compatibility` |
| `DELETE /api/v1/task-assignments/{taskAssignment}` | yes | `Src\CoreProject\Controllers\TaskAssignmentController@destroy` | `taskAssignment` | `taskAssignment` | none | yes | `mounted-and-reconciled-flat-compatibility` |

### family: `/api/v1/dashboard/*assignments*`

These routes are source-defined in `routes/api.php` and mounted in runtime under the `dashboard` subgroup.

| source-defined route | mounted runtime route | action | declared route params | controller method params | request class | method exists | classification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1/dashboard/tasks/{taskId}/assignments` | yes | `App\Http\Controllers\Api\TaskAssignmentController@getTaskAssignments` | `taskId` | `taskId` | none | yes | `mounted-and-reconciled-dashboard-compatibility` |
| `POST /api/v1/dashboard/tasks/{taskId}/assignments` | yes | `App\Http\Controllers\Api\TaskAssignmentController@store` | `taskId` | `request, taskId` | `Illuminate\Http\Request` | yes | `mounted-and-reconciled-dashboard-compatibility` |
| `PUT /api/v1/dashboard/assignments/{assignmentId}` | yes | `App\Http\Controllers\Api\TaskAssignmentController@update` | `assignmentId` | `request, assignmentId` | `Illuminate\Http\Request` | yes | `mounted-and-reconciled-dashboard-compatibility` |
| `DELETE /api/v1/dashboard/assignments/{assignmentId}` | yes | `App\Http\Controllers\Api\TaskAssignmentController@destroy` | `assignmentId` | `assignmentId` | none | yes | `mounted-and-reconciled-dashboard-compatibility` |
| `GET /api/v1/dashboard/users/{userId}/assignments` | yes | `App\Http\Controllers\Api\TaskAssignmentController@getUserAssignments` | `userId` | `userId` | none | yes | `mounted-and-reconciled-dashboard-compatibility` |
| `GET /api/v1/dashboard/users/{userId}/assignments/stats` | yes | `App\Http\Controllers\Api\TaskAssignmentController@getUserStats` | `userId` | `userId` | none | yes | `mounted-and-reconciled-dashboard-compatibility` |

### family: `/api/v1/work-template/projects/{projectId}/tasks*`

This remains adjacent projection by policy and source ownership.

| source-defined route | mounted runtime route | action | declared route params | controller method params | request class | method exists | classification |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GET /api/v1/work-template/projects/{projectId}/tasks` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@index` | `projectId` | `request, projectId` | `Illuminate\Http\Request` | yes | `adjacent-projection-reconciled-runtime-200` |
| `POST /api/v1/work-template/projects/{projectId}/tasks` | no | declaration removed from runtime surface in this slice | `projectId` | `UNKNOWN` | `UNKNOWN` | no | `implemented-removal` |
| `GET /api/v1/work-template/projects/{projectId}/tasks/conditional` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@conditionalTasks` | `projectId` | `projectId` | none | yes | `adjacent-projection-feature-pass` |
| `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@show` | `projectId, taskId` | `projectId, taskId` | none | yes | `adjacent-projection-mounted` |
| `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@update` | `projectId, taskId` | `request, projectId, taskId` | `Src\WorkTemplate\Requests\UpdateTaskRequest` | yes | `adjacent-projection-reconciled-runtime-200` |
| `DELETE /api/v1/work-template/projects/{projectId}/tasks/{taskId}` | no | declaration removed from runtime surface in this slice | `projectId, taskId` | `UNKNOWN` | `UNKNOWN` | no | `implemented-removal` |
| `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@updateProgress` | `projectId, taskId` | `request, projectId, taskId` | `Illuminate\Http\Request` | yes | `adjacent-projection-reconciled-runtime-422-200-404` |
| `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/status` | no | declaration removed from runtime surface in this slice | `projectId, taskId` | `UNKNOWN` | `UNKNOWN` | no | `implemented-removal` |
| `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional` | yes | `Src\WorkTemplate\Controllers\ProjectTaskController@toggleConditional` | `projectId, taskId` | `request, projectId, taskId` | `Src\WorkTemplate\Requests\ToggleConditionalRequest` | yes | `adjacent-projection-feature-pass` |
| `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-update` | no | declaration removed from runtime surface in this slice | `projectId` | `UNKNOWN` | `UNKNOWN` | no | `implemented-removal` |
| `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional` | no | declaration removed from runtime surface in this slice | `projectId` | `UNKNOWN` | `UNKNOWN` | no | `implemented-removal` |

Other work-template task-adjacent projection declarations removed from the runtime surface in this slice:
- `/api/v1/work-template/projects/{projectId}/conditional-tags`
- `/api/v1/work-template/projects/{projectId}/conditional-tags/statistics`
- `/api/v1/work-template/projects/{projectId}/conditional-tags/{tag}/toggle`
- `/api/v1/work-template/projects/{projectId}/conditional-tags/bulk-toggle`
- `/api/v1/work-template/projects/{projectId}/phases`
- `/api/v1/work-template/projects/{projectId}/phases/{phaseId}/tasks`
- `/api/v1/work-template/projects/{projectId}/phases/{phaseId}/reorder`
- `/api/v1/work-template/projects/{projectId}/reports/progress`
- `/api/v1/work-template/projects/{projectId}/reports/tasks-summary`
- `/api/v1/work-template/projects/{projectId}/reports/conditional-usage`
- `/api/v1/work-template/projects/{projectId}/template-sync/diff`
- `/api/v1/work-template/projects/{projectId}/template-sync/partial`
- `/api/v1/work-template/projects/{projectId}/template-sync/apply-diff`

Outside the exact `projects/{projectId}` family, `GET /api/v1/work-template/search/tasks` remains a separate mounted signature-mismatch and was not changed in this slice.

## mounted routes đúng owner

- `POST /api/v1/tasks` -> `Src\CoreProject\Controllers\TaskController@store`
- mounted-and-reconciled compatibility CRUD surface:
  - `GET /api/v1/tasks`
  - `POST /api/v1/tasks`
  - `GET /api/v1/tasks/{task}`
  - `PUT /api/v1/tasks/{task}`
  - `PATCH /api/v1/tasks/{task}`
  - `DELETE /api/v1/tasks/{task}`
- mounted-and-reconciled dashboard compatibility surface:
  - `GET /api/v1/dashboard/tasks/{taskId}/assignments`
  - `POST /api/v1/dashboard/tasks/{taskId}/assignments`
  - `PUT /api/v1/dashboard/assignments/{assignmentId}`
  - `DELETE /api/v1/dashboard/assignments/{assignmentId}`
  - `GET /api/v1/dashboard/users/{userId}/assignments`
  - `GET /api/v1/dashboard/users/{userId}/assignments/stats`
- Adjacent projection routes with matching method ownership:
  - `GET /api/v1/work-template/projects/{projectId}/tasks`
  - `GET /api/v1/work-template/projects/{projectId}/tasks/conditional`
  - `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress`
  - `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional`
  - current behavior:
    - `GET /tasks` -> `200`
    - `GET /tasks/conditional` -> pass
    - `GET /tasks/{taskId}` -> mounted
    - `PUT /tasks/{taskId}` -> `200`
    - `PUT /tasks/{taskId}/progress` -> `422` invalid payload / `200` valid existing / `404` missing task
    - `POST /tasks/{taskId}/toggle-conditional` -> pass

## implemented removal from active source

Evidence-backed from current `routes/api.php` source plus route collection:
- historical `/api/v1/tasks/{task}/status`
- historical `/api/v1/tasks/{task}/assign`
- historical `/api/v1/tasks/{task}/assign-team`
- historical `/api/v1/tasks/{task}/dependencies`
- historical `/api/v1/tasks/{task}/dependencies/{dependencyId}`
- historical `/api/v1/tasks/{task}/watchers`
- historical `/api/v1/tasks/statistics`
- all of the above are absent from active source and absent from `php artisan route:list`

## mounted kept-route behavior

- `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}` is kept and currently reconciled to runtime behavior `200` by feature evidence.

## removed khỏi runtime surface vì missing-method declarations

- Work-template declarations removed khỏi runtime route collection trong slice này:
  - `POST /api/v1/work-template/projects/{projectId}/tasks`
  - `DELETE /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/status`
  - `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-update`
  - `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional`
  - all conditional-tag, phase, report, and template-sync routes listed above

## projection boundary

- `/api/v1/work-template/projects/{projectId}/tasks*` is still adjacent projection.
- Evidence:
  - route prefix comes from `Src/WorkTemplate/routes/api.php`
  - mounted action owner is `Src\WorkTemplate\Controllers\ProjectTaskController`
  - domain docs and SSOT already freeze it as projection, not canonical task owner
- Current drift inside this boundary is runtime wiring debt, not ownership convergence evidence.

## top load-bearing drifts

1. Historical source-only task subroutes under `/api/v1/tasks/{task}/*` and `/api/v1/tasks/statistics` have now been removed from active source; keep this cleanup locked because reintroducing them would recreate contributor confusion.
2. `/api/v1/task-assignments*` is now a mounted-and-reconciled flat compatibility surface; keep that flat contract locked.
3. `/api/v1/dashboard/*assignments*` is now a mounted-and-reconciled dashboard compatibility surface verified by route inventory plus feature tests; keep that exact 6-route surface locked.
4. Work-template projection surface has been shrunk to the evidence-backed 6-route project-scoped runtime set; kept routes now document current runtime behavior rather than a mounted-missing-method narrative.
5. `/api/v1/tasks*` CRUD is now a mounted-and-reconciled compatibility CRUD family with evidence-backed success-path and delete behavior; keep the regression gate locked.
6. Any broader JSON/dependency consistency debt should now be treated as outside this CRUD family unless new evidence says otherwise.

## mọi `UNKNOWN`

- Whether any external clients still assume the unmounted `/api/v1/tasks/{task}/*` subroutes exist is `UNKNOWN`.
- Whether any broader underlying implementation debt beneath the kept 6-route projection surface materially affects runtime beyond the verified behavior is `UNKNOWN`.

## Change Proposal

- `Change Proposal`: `TASKS V1 ROUTE-SOURCE RUNTIME RECONCILIATION`
- Scope:
  - keep dead `/api/v1/tasks/{task}/*` and `/api/v1/tasks/statistics` declarations removed from active source
  - keep `/api/v1/task-assignments*` on its flat compatibility contract
  - keep `/api/v1/dashboard/*assignments*` on its mounted-and-reconciled dashboard compatibility surface
  - keep `/api/v1/tasks*` on its mounted-and-reconciled compatibility CRUD surface
  - reconcile work-template route declarations with actual `ProjectTaskController` implementation surface
- Implemented for the dead declaration cleanup slice, the flat `/api/v1/task-assignments*` reconcile slice, the dashboard assignment reconcile slice, the `/api/v1/tasks*` CRUD reconcile slice, and the work-template broken declaration shrink slice.

# 3) EXACT FILES TOUCHED
- `docs/audits/2026-03-19-tasks-v1-mounted-source-drift-triage.md`
- `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`

# 4) TESTS / VERIFICATION
- `php artisan route:list --json | jq -r '.[] | select(.uri==\"api/v1/tasks/statistics\" or .uri==\"api/v1/tasks/{task}/status\" or .uri==\"api/v1/tasks/{task}/assign\" or .uri==\"api/v1/tasks/{task}/assign-team\" or .uri==\"api/v1/tasks/{task}/dependencies\" or .uri==\"api/v1/tasks/{task}/dependencies/{dependencyId}\" or .uri==\"api/v1/tasks/{task}/watchers\" or .uri==\"api/v1/dashboard/tasks/{taskId}/assignments\" or .uri==\"api/v1/dashboard/users/{userId}/assignments/stats\" or .uri==\"api/v1/work-template/search/tasks\") | [.method,.uri,.action] | @tsv'`
- `php -r 'require __DIR__.\"/vendor/autoload.php\"; ... method_exists matrix ...'`
- `php artisan route:list --path=api/v1/tasks`
- `php artisan route:list --path=api/v1/task-assignments`
- `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- `php artisan test tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php`
- `php artisan test tests/Feature/ProjectTaskControllerTest.php`
- `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
- `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- pass/fail: see final turn output
- suite chưa chạy: `UNKNOWN`

# 5) RISK ASSESSMENT
- runtime
  - medium because work-template projection surface is now reduced to an evidence-backed 6-route set; any deeper mismatch beyond the verified behavior is `UNKNOWN`.
- compatibility
  - medium because the flat `/api/v1/task-assignments*` surface, dashboard compatibility surface, and `/api/v1/tasks*` CRUD surface are reconciled; work-template projection family no longer over-declares removed routes, but broader debt outside the verified behavior is still `UNKNOWN`.
- contributor confusion
  - high because the dead task declarations are gone, but contributors still have to separate compatibility CRUD, dashboard compatibility routes, and work-template projection behavior.
- maintenance
  - medium because duplicate route blocks still make future compatibility updates easy to drift.

# 6) SSOT UPDATE
- Sau round này, drift của task-related `/api/v1/*` phải được hiểu là: dead source-only task declarations đã bị xóa khỏi active source; `/api/v1/task-assignments*` là mounted-and-reconciled flat compatibility surface; `/api/v1/dashboard/*assignments*` là mounted-and-reconciled dashboard compatibility surface; `/api/v1/tasks*` là mounted-and-reconciled compatibility CRUD surface đã có evidence pass; work-template projection đã shrink từ 24 declarations xuống còn 6 mounted routes và kept routes đã reconcile theo current runtime behavior. Runtime truth phải ưu tiên route collection.

# 7) NEXT ROUND
- Remaining open mismatches:
  - broader implementation debt under kept work-template projection routes: `UNKNOWN`
  - any still-unknown broader JSON/dependency consistency debt outside this CRUD family: `UNKNOWN`
- Next recommended slice: `WORK-TEMPLATE PROJECTION EVIDENCE-ONLY DOC SSOT MAINTENANCE`

commit-ready summary: add a mounted-vs-source drift triage audit and invariant tests for `/api/v1/*` task routes, locking unmounted source routes, mounted signature mismatches, missing methods, and the work-template projection boundary without changing runtime behavior
