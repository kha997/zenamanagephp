# Domain Ownership (Phase 2 Baseline)

This file defines canonical ownership between `src/*` (domain) and `app/*` (adapters) to reduce drift.

## Global Rules

- `src/*` remains canonical for compatibility-domain modules that have not yet converged to an app-owned `/api/zena/*` business runtime.
- `app/*` remains adapter/integration by default, but it is also the canonical business owner for modules explicitly converged in the runtime ownership SSOT.
- `app/*` controllers must call the canonical owner for the module in the runtime ownership SSOT; do not route converged app-owned modules back through legacy `src/*` adapters.
- Cross-module writes must flow through the owning module service, not direct model writes from another module.
- SSOT invariants remain mandatory: tenancy deny/hide, reject-first sanitization, RBAC, named routes, strict baseline rules.

## Ownership Map

### CoreProject

- Canonical app-owned Projects runtime:
  - Route family: `/api/zena/projects`
  - Controller/service/model owner: `app/Http/Controllers/Api/ProjectController.php`, `app/Services/ProjectService.php`, `app/Models/Project.php`
- Canonical app-owned Tasks runtime:
  - Route family: `/api/zena/tasks`
  - Controller/model owner: `app/Http/Controllers/Api/TaskController.php`, `app/Models/Task.php`
  - Current dependency helper in canonical stack: `app/Services/TaskDependencyService.php` via `app/Models/Task.php`
- Canonical `src/*` compatibility runtime still mounted:
  - Route family: `/api/v1/projects`
  - Controller owner: `Src/CoreProject/Controllers/ProjectController.php`
  - Route family: `/api/v1/tasks`
  - Controller owner: `Src/CoreProject/Controllers/TaskController.php`
- Compatibility runtime still mounted: `/api/v1/tasks` in `Src/CoreProject/Controllers/TaskController.php`
- Canonical `src/*` adjacent projection runtime still mounted:
  - Route family: `/api/v1/work-template/projects/{projectId}/tasks`
  - Controller/service/model owner: `Src/WorkTemplate/Controllers/ProjectTaskController.php`, `Src/WorkTemplate/Services/ProjectTaskService.php`, `Src/WorkTemplate/Models/ProjectTask.php`
- Projection runtime still mounted: `/api/v1/work-template/projects/{projectId}/tasks` in `Src/WorkTemplate/Controllers/ProjectTaskController.php`
- Canonical `src/*` for remaining CoreProject compatibility/domain surfaces:
  - `src/CoreProject/Models/Component.php`
  - `src/CoreProject/Services/ComponentService.php`
- Allowed `app/*` adapters and owners:
  - `app/Http/Controllers/**/Project*Controller.php`
  - `app/Http/Controllers/**/Task*Controller.php`
  - `app/Http/Controllers/**/Component*Controller.php`
  - `app/Repositories/ProjectRepository.php`
  - `app/Repositories/TaskRepository.php`
- Forbidden cross-calls:
  - Document/Notification/Support modules must not directly mutate `Project/Task/Component` models.
  - Do not reintroduce `LegacyProjectServiceAdapter`; Projects canonical `/api/zena/*` flow is app-owned.
  - Do not remount `/api/v1/projects`; keep it as compatibility-owned by `Src/CoreProject`.
  - Do not route canonical `/api/zena/tasks` changes back through `Src/CoreProject/Services/TaskService.php`.
  - Do not expand `/api/v1/work-template/projects/{projectId}/tasks` into a second general-purpose task owner; keep it projection-scoped to work-template flows.

### DocumentManagement

- Canonical `src/*`:
  - `src/DocumentManagement/Models/Document.php`
  - `src/DocumentManagement/Models/DocumentVersion.php`
  - `src/DocumentManagement/Services/DocumentService.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/Document*Controller.php`
  - `app/Repositories/DocumentRepository.php`
- Forbidden cross-calls:
  - CoreProject/ChangeRequest modules must not directly mutate document/version records.
  - `app/*` controllers must not prefer `App\Models\Document` or `App\Services\DocumentService` when `src/DocumentManagement/Services/DocumentService.php` exists.

### ChangeRequest

- Canonical `src/*`:
  - `src/ChangeRequest/Models/ChangeRequest.php`
  - `src/ChangeRequest/Models/CrLink.php`
  - `src/ChangeRequest/Services/ChangeRequestService.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/ChangeRequest*Controller.php`
  - `app/Repositories/ChangeRequestRepository.php`
- Forbidden cross-calls:
  - No direct write from Project/Document controllers to change-request tables.
  - Route/request validation logic must stay adapter-side; decision logic stays in `src/ChangeRequest`.

### RFI / Submittal / Inspection

- Canonical `src/*` (current baseline):
  - `src/CoreProject/Services/TaskService.php`
  - `src/CoreProject/Services/ComponentService.php`
  - `src/CoreProject/Models/Task.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/Api/RfiController.php`
  - `app/Http/Controllers/Api/SubmittalController.php`
  - `app/Http/Controllers/Api/InspectionController.php`
  - legacy models (`app/Models/Rfi.php`, `app/Models/ZenaSubmittal.php`, `app/Models/QcInspection.php`) are transitional.
- Forbidden cross-calls:
  - No direct writes from RFI/Submittal/Inspection controllers into unrelated domain models.
  - New business rules for this area must be introduced in `src/*` first (not in `app/Models/*`).

### Notification / Realtime

- Canonical `src/*`:
  - `src/Notification/Models/Notification.php`
  - `src/Notification/Models/NotificationRule.php`
  - `src/Notification/Services/NotificationService.php`
  - `src/Notification/Services/NotificationRuleService.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/Notification*Controller.php`
  - realtime transport adapters in `app/Http/Controllers/Api/*WebSocket*Controller.php`
- Forbidden cross-calls:
  - Realtime transport code must not own notification decision logic.
  - Other modules must not bypass Notification services for dispatch/rule writes.

### Support

- Canonical `src/*` (current baseline):
  - `src/InteractionLogs/Models/InteractionLog.php`
  - `src/InteractionLogs/Services/InteractionLogService.php`
  - `src/InteractionLogs/Services/InteractionLogQueryService.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/Support*Controller.php`
  - `app/Http/Controllers/**/InteractionLog*Controller.php`
- Forbidden cross-calls:
  - Support-facing controllers must not embed business rules for unrelated modules.
  - Cross-domain status/approval changes must go through owning module services.

### Dashboard / Metrics / Logs

- Canonical `src/*`:
  - `src/InteractionLogs/Services/InteractionLogQueryService.php`
  - `src/Foundation/Listeners/EventLogListener.php`
  - `src/CoreProject/Listeners/ProjectProgressListener.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/Dashboard*Controller.php`
  - `app/Http/Controllers/**/Analytics*Controller.php`
  - `app/Services/*Metrics*Service.php` as read-model adapters
- Forbidden cross-calls:
  - Dashboard/metrics code is read-side; it must not become write-side business authority.
  - Logging pipelines must not bypass tenancy/RBAC constraints from owning modules.

## Guardrails (Phase 2 Initial)

- Lint command: `composer lint:domain-ownership`
- Current enforcement scope:
  - `app/Http/Controllers/**/*Project*Controller.php`
  - `app/Http/Controllers/**/*Document*Controller.php`
- Rule:
  - If canonical service exists in `src/*`, block new direct imports of non-canonical `App\Models/*` and `App\Services/*` for Project/Document flows.
- Existing debt is tracked via an explicit temporary allowlist in `scripts/ci/lint-domain-ownership.php` so CI blocks new drift without forcing immediate wide refactor.
