# Domain Ownership (Phase 2 Baseline)

This file defines canonical ownership between `src/*` (domain) and `app/*` (adapters) to reduce drift.

## Global Rules

- `src/*` is canonical for domain models, domain services, and domain events.
- `app/*` is adapter/integration only (HTTP, framework glue, policy wiring, repository plumbing, view/API shaping).
- `app/*` controllers must call canonical `src/*` services when those services exist.
- Cross-module writes must flow through the owning module service, not direct model writes from another module.
- SSOT invariants remain mandatory: tenancy deny/hide, reject-first sanitization, RBAC, named routes, strict baseline rules.

## Ownership Map

### CoreProject

- Canonical `src/*`:
  - `src/CoreProject/Models/Project.php`
  - `src/CoreProject/Models/Task.php`
  - `src/CoreProject/Models/Component.php`
  - `src/CoreProject/Services/ProjectService.php`
  - `src/CoreProject/Services/TaskService.php`
  - `src/CoreProject/Services/ComponentService.php`
- Allowed `app/*` adapters:
  - `app/Http/Controllers/**/Project*Controller.php`
  - `app/Http/Controllers/**/Task*Controller.php`
  - `app/Http/Controllers/**/Component*Controller.php`
  - `app/Repositories/ProjectRepository.php`
  - `app/Repositories/TaskRepository.php`
- Forbidden cross-calls:
  - Document/Notification/Support modules must not directly mutate `Project/Task/Component` models.
  - `app/*` controllers must not prefer `App\Models\Project` or `App\Services\ProjectService` when `src/CoreProject/Services/ProjectService.php` is the canonical service.

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
