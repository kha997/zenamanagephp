# 1) EXECUTIVE VERDICT
- change-proposal
- `/api/v1/*` task-related surface phải được reconcile theo mounted runtime truth, không theo source declaration đơn lẻ.
- `/api/v1/tasks*` giữ là compatibility family; slice xóa source-only dead declarations của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics` đã implemented, và CRUD list/show/update/delete hiện là một mounted-and-reconciled compatibility CRUD surface đã có evidence pass trong suite mục tiêu.
- `/api/v1/task-assignments*` đã được implement như một mounted-and-reconciled flat compatibility surface; `/api/v1/dashboard/*assignments*` cũng đã được implement như một mounted-and-reconciled dashboard compatibility surface với đúng 6 mounted routes và action runtime tương ứng.
- `/api/v1/work-template/projects/{projectId}/tasks*` phải giữ là adjacent projection; runtime surface hiện đã shrink từ 24 declarations xuống còn 6 mounted routes, các kept routes đã được reconcile theo current runtime behavior, và removed declarations đã ra khỏi runtime surface.

# 2) PROPOSED DECISION MATRIX

## `/api/v1/tasks*`

- Route/group: `POST /api/v1/tasks`
  - current status: mounted-and-reconciled compatibility create path.
  - evidence: `Src/CoreProject/routes/api.php:94`; `Src/CoreProject/Controllers/TaskController.php:137`; `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`; `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`.
  - proposed future action: keep as compatibility; thêm explicit compatibility marker trong route/source docs-test expectation để không bị hiểu nhầm là canonical owner.
  - compatibility impact: none expected; path và handler family giữ nguyên.
  - required test gate: route/action invariant vẫn phải giữ `Src\CoreProject\Controllers\TaskController@store`; thêm gate xác nhận route này là compatibility-only, không canonical.

- Route/group: `GET /api/v1/tasks`, `GET /api/v1/tasks/{task}`, `PUT /api/v1/tasks/{task}`, `PATCH /api/v1/tasks/{task}`, `DELETE /api/v1/tasks/{task}`
  - current status: mounted-and-reconciled compatibility CRUD surface. List/show/update/delete hiện đã chạy pass trên mounted URI hiện có và dùng `{task}` route param làm source of truth.
  - evidence: `Src/CoreProject/routes/api.php:93-98`; `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`; `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`.
  - handled root causes: controller signature mismatch; `UpdateTaskRequest` constant mismatch; `TaskService` id/ulid mismatch on update; DateTime normalization issue; sqlite json contains delete fallback.
  - proposed future action: keep exact mounted CRUD contract, keep compatibility ownership under `Src\CoreProject\Controllers\TaskController`, và tiếp tục lock success-path/delete behavior bằng feature + invariant suites hiện có.
  - compatibility impact: positive because current mounted contract giờ đã có runtime-backed CRUD evidence mà không đổi path hay mở runtime slice mới.
  - required test gate: giữ `V1TasksCompatibilityCrudTest` và 3 invariant files như mandatory regression gate cho mounted CRUD surface.

- Route/group: `PATCH /api/v1/tasks/{task}/status`, `POST /api/v1/tasks/{task}/assign`, `POST /api/v1/tasks/{task}/assign-team`, `POST /api/v1/tasks/{task}/dependencies`, `GET /api/v1/tasks/{task}/dependencies`, `POST /api/v1/tasks/{task}/dependencies/{dependencyId}`, `DELETE /api/v1/tasks/{task}/dependencies/{dependencyId}`, `GET /api/v1/tasks/{task}/watchers`, `POST /api/v1/tasks/{task}/watchers`, `DELETE /api/v1/tasks/{task}/watchers`, `GET /api/v1/tasks/statistics`
  - current status: implemented. Source-only dead declarations đã bị xóa khỏi `routes/api.php` và vẫn không mounted trong runtime route collection.
  - evidence: `routes/api.php` không còn block task subroutes/statistics cũ; `php artisan route:list --path=api/v1/tasks` chỉ còn 6 CRUD routes; `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php::test_source_defined_v1_task_subroutes_remain_unmounted_in_runtime_route_collection`; `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php::test_mounted_v1_task_related_owner_map_stays_explicit`.
  - proposed future action: keep removed khỏi active source cho tới khi có proposal khác với evidence runtime mới.
  - compatibility impact: none expected at runtime because routes này đã unmounted trước khi cleanup source.
  - required test gate: giữ invariants khẳng định source block không quay lại và route collection không remount các URI này ngoài một round proposal riêng.

## `/api/v1/task-assignments*`

- Route/group: `GET /api/v1/task-assignments`, `POST /api/v1/task-assignments`, `GET /api/v1/task-assignments/{taskAssignment}`, `PUT /api/v1/task-assignments/{taskAssignment}`, `PATCH /api/v1/task-assignments/{taskAssignment}`, `DELETE /api/v1/task-assignments/{taskAssignment}`
  - current status: implemented và mounted như một mounted-and-reconciled flat compatibility surface.
  - evidence: `Src/CoreProject/routes/api.php:109-114`; `Src/CoreProject/Controllers/TaskAssignmentController.php` hiện dùng flat signatures `index(Request $request)`, `show(string $taskAssignment)`, `update(UpdateTaskAssignmentRequest $request, string $taskAssignment)`, `destroy(string $taskAssignment)`; `php artisan route:list --path=api/v1/task-assignments`; `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php::test_mounted_assignment_surfaces_are_flat_and_signature_reconciled`; `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php::test_reconciled_task_assignment_signatures_match_flat_mounted_routes`.
  - proposed future action: keep flat compatibility routing và không đổi family sang `/projects/{projectId}/tasks/{taskId}/assignments` trong round này.
  - compatibility impact: positive because mounted URI shape giờ khớp controller signatures hiện có mà không đổi path.
  - required test gate: giữ explicit route-signature parity tests cho cả 6 routes và không thêm project-scoped remount trong cùng lane.

## `/api/v1/dashboard/*assignments*`

- Route/group: `GET /api/v1/dashboard/users/{userId}/assignments`
  - current status: implemented như một phần của mounted-and-reconciled dashboard compatibility surface.
  - evidence: `php artisan route:list --json` xác nhận `GET|HEAD api/v1/dashboard/users/{userId}/assignments -> App\Http\Controllers\Api\TaskAssignmentController@getUserAssignments`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_mounted_dashboard_routes_keep_owner_family_and_expected_actions`.
  - proposed future action: keep owner `App\Http\Controllers\Api\TaskAssignmentController`; không dời sang `Src\CoreProject`; giữ explicit split-owner compatibility marker để contributors không nhầm đây là canonical Tasks CRUD owner.
  - compatibility impact: none expected.
  - required test gate: keep route action invariant và method-exists invariant cho `getUserAssignments`.

- Route/group: `GET /api/v1/dashboard/tasks/{taskId}/assignments`, `POST /api/v1/dashboard/tasks/{taskId}/assignments`
  - current status: implemented như mounted-and-reconciled dashboard compatibility surface. `GET` hiện mount vào `getTaskAssignments(string $taskId)` và `POST` mount vào `store(Request $request, string $taskId)`.
  - evidence: `php artisan route:list --json` xác nhận `GET|HEAD api/v1/dashboard/tasks/{taskId}/assignments -> App\Http\Controllers\Api\TaskAssignmentController@getTaskAssignments` và `POST api/v1/dashboard/tasks/{taskId}/assignments -> App\Http\Controllers\Api\TaskAssignmentController@store`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_get_task_assignments_uses_route_task_context`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_store_uses_route_task_id_as_source_of_truth_and_keeps_current_envelope_family`.
  - proposed future action: keep current owner and exact URI contract; không rebind về `index`.
  - compatibility impact: positive because mounted route params giờ đã khớp handler thật và feature tests khóa route-task context.
  - required test gate: giữ feature tests khóa `{taskId}` là source of truth cho list/create.

- Route/group: `PUT /api/v1/dashboard/assignments/{assignmentId}`, `DELETE /api/v1/dashboard/assignments/{assignmentId}`
  - current status: implemented như mounted-and-reconciled dashboard compatibility surface; controller signatures giờ dùng exact param name `{assignmentId}`.
  - evidence: `php artisan route:list --json` xác nhận `PUT` và `DELETE` cho `api/v1/dashboard/assignments/{assignmentId}` map vào `App\Http\Controllers\Api\TaskAssignmentController@update|destroy`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_put_route_uses_exact_assignment_id_param_name`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_delete_route_uses_exact_assignment_id_param_name`.
  - proposed future action: keep current owner and exact path contract.
  - compatibility impact: positive because route param self-documents đúng runtime signature.
  - required test gate: giữ route dispatch tests cho exact `{assignmentId}` contract.

- Route/group: `GET /api/v1/dashboard/users/{userId}/assignments/stats`
  - current status: implemented như mounted-and-reconciled dashboard compatibility surface. `getUserStats(string $userId)` tồn tại và mounted route hiện có evidence chạy pass.
  - evidence: `php artisan route:list --json` xác nhận `GET|HEAD api/v1/dashboard/users/{userId}/assignments/stats -> App\Http\Controllers\Api\TaskAssignmentController@getUserStats`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_stats_returns_minimal_payload_for_user_assignment_family`; `tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php::test_stats_tenant_mismatch_is_rejected_before_enumeration`; `app/Http/Controllers/Api/TaskAssignmentController.php` có `getUserStats(string $userId)` và nhánh filter `tenant_id` theo `current_tenant_id` nếu binding tồn tại.
  - proposed future action: keep current owner and current minimal payload contract; open item duy nhất còn lại ở route này là verify assumption rằng `current_tenant_id` luôn được bind đúng trước khi query filter theo `tenant_id`.
  - compatibility impact: positive because mounted stats route giờ đã có runtime evidence.
  - required test gate: giữ exact payload test và tenant-mismatch rejection test; nếu thay đổi query scoping thì phải có evidence mới trước khi claim stronger tenant guarantees.

## `/api/v1/work-template/projects/{projectId}/tasks*`

- Route/group: `GET /api/v1/work-template/projects/{projectId}/tasks`, `GET /api/v1/work-template/projects/{projectId}/tasks/conditional`, `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}`, `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}`, `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress`, `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional`
  - current status: adjacent projection runtime đã shrink còn đúng 6 mounted routes và current behavior đã có evidence pass cho kept route set hiện tại.
  - evidence: `php artisan test tests/Feature/ProjectTaskControllerTest.php`; `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`; `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`; `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`.
  - current behavior:
    - `GET /tasks` -> `200`
    - `GET /tasks/conditional` -> pass
    - `GET /tasks/{taskId}` -> mounted
    - `PUT /tasks/{taskId}` -> `200`
    - `PUT /tasks/{taskId}/progress` -> `422` invalid payload / `200` valid existing / `404` missing task
    - `POST /tasks/{taskId}/toggle-conditional` -> pass
  - proposed future action: keep exact 6-route projection surface và giữ projection boundary rõ ràng; không reopen removed declarations trong round này.
  - compatibility impact: positive because current runtime behavior giờ đã được verify mà không đổi path hay mở runtime slice mới.
  - required test gate: projection-boundary invariant và `ProjectTaskControllerTest` phải tiếp tục khóa exact 6 mounted routes cùng current behavior ở trên.

- Route/group: `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
  - current status: kept route đã reconcile cho current runtime behavior `200`.
  - evidence: `php artisan test tests/Feature/ProjectTaskControllerTest.php`; `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`; `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`; `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`.
  - proposed future action: keep mounted behavior như runtime truth hiện tại; open implementation debt nào bên dưới kept route chỉ được ghi khi có evidence riêng.
  - compatibility impact: low for this proposal because route behavior đã pass và không có runtime patch trong round docs này.
  - required test gate: giữ `ProjectTaskControllerTest` khóa `200` contract cho mounted update path.

- Route/group: `POST /api/v1/work-template/projects/{projectId}/tasks`, `DELETE /api/v1/work-template/projects/{projectId}/tasks/{taskId}`, `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/status`, `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-update`, `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional`
  - current status: removed from runtime route collection in this slice because controller methods không tồn tại.
  - evidence: `Src/WorkTemplate/routes/api.php:77-90`; `Src/WorkTemplate/Controllers/ProjectTaskController.php`; `php artisan route:list --json`; `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`.
  - proposed future action: keep removed cho tới khi có implementation round riêng bổ sung method thật và evidence runtime mới.
  - compatibility impact: low-to-medium; đây là broken declarations bị co khỏi runtime surface, không phải stable proven contract.
  - required test gate: invariant suite phải assert removed-from-route-collection, không còn trạng thái “mounted-missing-method”.

- Route/group: `GET /api/v1/work-template/projects/{projectId}/phases`, `GET /api/v1/work-template/projects/{projectId}/phases/{phaseId}/tasks`, `PUT /api/v1/work-template/projects/{projectId}/phases/{phaseId}/reorder`, `GET /api/v1/work-template/projects/{projectId}/conditional-tags`, `GET /api/v1/work-template/projects/{projectId}/conditional-tags/statistics`, `POST /api/v1/work-template/projects/{projectId}/conditional-tags/{tag}/toggle`, `POST /api/v1/work-template/projects/{projectId}/conditional-tags/bulk-toggle`, `POST /api/v1/work-template/projects/{projectId}/template-sync/partial`, `GET /api/v1/work-template/projects/{projectId}/template-sync/diff`, `POST /api/v1/work-template/projects/{projectId}/template-sync/apply-diff`, `GET /api/v1/work-template/projects/{projectId}/reports/progress`, `GET /api/v1/work-template/projects/{projectId}/reports/tasks-summary`, `GET /api/v1/work-template/projects/{projectId}/reports/conditional-usage`
  - current status: removed from runtime route collection in this slice because mounted declarations were pointing to non-existent controller methods.
  - evidence: `Src/WorkTemplate/routes/api.php`; `php artisan route:list --path=api/v1/work-template/projects`; `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`; `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`; `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`.
  - proposed future action: keep removed until a separate implementation slice proves real methods and route intent.
  - compatibility impact: low; these were broken declarations, not evidence-backed projection contracts.
  - required test gate: route inventory must stay at the 6 remaining project-scoped projection routes unless another approved change proposal expands it.

- Route/group: toàn bộ `/api/v1/work-template/projects/{projectId}/tasks*`
  - current status: adjacent projection family, không phải competing owner.
  - evidence: `docs/architecture/module-ownership-ssot.md`; `docs/engineering/domain-ownership.md`; `Src/WorkTemplate/routes/api.php`; `docs/audits/2026-03-19-tasks-contract-parity-audit.md`.
  - proposed future action: không được lẫn sang canonical Tasks ownership; mọi repair ở family này chỉ nhằm projection runtime hygiene, không nhằm hội tụ ownership vào `Src\WorkTemplate`.
  - compatibility impact: none if boundary được giữ rõ.
  - required test gate: implementation round phải giữ invariant “do not expand `/api/v1/work-template/projects/{projectId}/tasks` into a second general-purpose task owner”.

# 3) NON-NEGOTIABLE INVARIANTS
- Runtime route truth phải ưu tiên `php artisan route:list --json`; source declaration không tự tạo runtime truth.
- Không remount route family mới trong round reconcile này.
- Không đổi canonical Tasks ownership khỏi `/api/zena/tasks`.
- `/api/v1/tasks*` chỉ được giữ như compatibility surface, không được tái diễn giải thành canonical owner.
- `/api/v1/work-template/projects/{projectId}/tasks*` phải giữ là adjacent projection.
- Slice này chỉ shrink broken declarations; không backfill controller/service/request/helper mới.
- Source-defined nhưng unmounted `/api/v1/tasks/{task}/*` routes không được silently revive.
- Mọi repair của `/api/v1/task-assignments*` và `/api/v1/dashboard/*assignments*` phải khớp exact mounted URI params hiện tại hoặc explicit removal; không được để mismatch mơ hồ.

# 4) CHANGE PROPOSAL FILES
- file proposal mới sẽ tạo
  - `docs/change-proposals/2026-03-19-tasks-v1-route-source-runtime-reconciliation.md`
- file round implementation tương lai có thể phải chạm
  - `routes/api.php`
  - `Src/CoreProject/routes/api.php`
  - `Src/WorkTemplate/routes/api.php`
  - `app/Http/Controllers/Api/TaskAssignmentController.php`
  - `Src/CoreProject/Controllers/TaskController.php`
  - `Src/CoreProject/Controllers/TaskAssignmentController.php`
  - `Src/WorkTemplate/Controllers/ProjectTaskController.php`
  - `tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
  - `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
  - `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
- file tuyệt đối chưa được chạm trong round này
  - tất cả runtime files ở danh sách trên
  - các audit SSOT đã có trong `docs/audits/2026-03-19-*tasks*.md`

# 5) RISK ASSESSMENT
- runtime
  - medium cho work-template projection family sau shrink; current 6-route surface đã reconcile theo runtime behavior hiện tại, còn broader underlying implementation debt chỉ nên xem là `UNKNOWN` nếu chưa có evidence trực tiếp.
- compatibility
  - medium vì compatibility surface hiện trải qua nhiều owner families; riêng `/api/v1/tasks*` CRUD đã được khóa bằng suite mục tiêu.
- contributor confusion
  - high nếu làm mờ boundary giữa `/api/v1/tasks*` CRUD, mounted-and-reconciled dashboard compatibility surface, và work-template projection family sau khi dead declarations đã được dọn.
- maintenance
  - medium vì duplicate route block giữa compatibility families vẫn tạo maintenance risk, dù dashboard surface đã được reconcile.
- migration risk
  - medium; proposal này cố ý ưu tiên rebind/remove tại chỗ thay vì remount path mới để tránh làm nổ client path contracts.

# 6) IMPLEMENTATION GATE
- Có route inventory snapshot mới từ `php artisan route:list --json` cho đúng 4 cụm của proposal.
- Có test matrix chuyển từng drift sang một decision duy nhất: keep, rebind, hoặc remove.
- Có explicit before/after contract note cho mọi route đã bị shrink khỏi runtime surface.
- Có guard xác nhận `/api/zena/tasks` vẫn là canonical owner và work-template family vẫn là projection.
- Không có runtime edit nào ngoài scope các files đã liệt kê.

# 7) NEXT ROUND
- Implemented in this proposal scope:
  - slice 1: xóa source-only dead declarations của `/api/v1/tasks/{task}/*` và `/api/v1/tasks/statistics`
    - verified by `php artisan route:list --path=api/v1/tasks`
    - verified by `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
    - verified by `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
  - slice 2: reconcile mounted flat contract cho `/api/v1/task-assignments*`
    - verified by `php artisan route:list --path=api/v1/task-assignments`
    - verified by `tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
    - verified by `tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
  - slice 3: reconcile dashboard assignment family `/api/v1/dashboard/*assignments*`
    - verified by `php artisan test tests/Feature/Api/V1DashboardTaskAssignmentsCompatibilityTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
    - verified by route inventory with 6 mounted routes and actions:
      - `GET|HEAD /api/v1/dashboard/tasks/{taskId}/assignments` -> `App\Http\Controllers\Api\TaskAssignmentController@getTaskAssignments`
      - `POST /api/v1/dashboard/tasks/{taskId}/assignments` -> `App\Http\Controllers\Api\TaskAssignmentController@store`
      - `PUT /api/v1/dashboard/assignments/{assignmentId}` -> `App\Http\Controllers\Api\TaskAssignmentController@update`
      - `DELETE /api/v1/dashboard/assignments/{assignmentId}` -> `App\Http\Controllers\Api\TaskAssignmentController@destroy`
      - `GET|HEAD /api/v1/dashboard/users/{userId}/assignments` -> `App\Http\Controllers\Api\TaskAssignmentController@getUserAssignments`
      - `GET|HEAD /api/v1/dashboard/users/{userId}/assignments/stats` -> `App\Http\Controllers\Api\TaskAssignmentController@getUserStats`
  - slice 4: reconcile `/api/v1/tasks*` CRUD signatures + update success-path + delete runtime triage
    - verified by `php artisan test tests/Feature/Api/V1TasksCompatibilityCrudTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
    - handled root causes:
      - controller signature mismatch
      - `UpdateTaskRequest` constant mismatch
      - `TaskService` id/ulid mismatch on update
      - DateTime normalization issue
      - sqlite json contains delete fallback
  - slice 5: shrink broken work-template projection declarations khỏi runtime surface
    - verified by `php artisan test tests/Feature/ProjectTaskControllerTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksContractParityAuditInvariantTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksV1SplitOwnerRouteInventoryInvariantTest.php`
    - verified by `php artisan test tests/Feature/Architecture/TasksV1MountedSourceDriftTriageInvariantTest.php`
    - current 6 mounted routes and behavior:
      - `GET /api/v1/work-template/projects/{projectId}/tasks` -> `200`
      - `GET /api/v1/work-template/projects/{projectId}/tasks/conditional` -> pass
      - `GET /api/v1/work-template/projects/{projectId}/tasks/{taskId}` -> mounted
      - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}` -> `200`
      - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/progress` -> `422` invalid payload / `200` valid existing / `404` missing task
      - `POST /api/v1/work-template/projects/{projectId}/tasks/{taskId}/toggle-conditional` -> pass
    - removed declarations:
      - `POST /api/v1/work-template/projects/{projectId}/tasks`
      - `DELETE /api/v1/work-template/projects/{projectId}/tasks/{taskId}`
      - `PUT /api/v1/work-template/projects/{projectId}/tasks/{taskId}/status`
      - `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-update`
      - `POST /api/v1/work-template/projects/{projectId}/tasks/bulk-toggle-conditional`
      - `GET /api/v1/work-template/projects/{projectId}/phases`
      - `GET /api/v1/work-template/projects/{projectId}/phases/{phaseId}/tasks`
      - `PUT /api/v1/work-template/projects/{projectId}/phases/{phaseId}/reorder`
      - `GET /api/v1/work-template/projects/{projectId}/conditional-tags`
      - `GET /api/v1/work-template/projects/{projectId}/conditional-tags/statistics`
      - `POST /api/v1/work-template/projects/{projectId}/conditional-tags/{tag}/toggle`
      - `POST /api/v1/work-template/projects/{projectId}/conditional-tags/bulk-toggle`
      - `POST /api/v1/work-template/projects/{projectId}/template-sync/partial`
      - `GET /api/v1/work-template/projects/{projectId}/template-sync/diff`
      - `POST /api/v1/work-template/projects/{projectId}/template-sync/apply-diff`
      - `GET /api/v1/work-template/projects/{projectId}/reports/progress`
      - `GET /api/v1/work-template/projects/{projectId}/reports/tasks-summary`
      - `GET /api/v1/work-template/projects/{projectId}/reports/conditional-usage`
- Remaining open items:
  - broader implementation debt under kept work-template projection routes: `UNKNOWN`
  - any broader JSON/dependency consistency debt outside this CRUD family: `UNKNOWN`
- Next recommended slice: `WORK-TEMPLATE PROJECTION EVIDENCE-ONLY DOC SSOT MAINTENANCE`

commit-ready summary
