# Module Ownership SSOT

Last updated: 2026-07-14
Status: canonical runtime ownership map for active business modules

## Purpose

This document fixes runtime ownership ambiguity for active business APIs.

Use it to decide:

- which route family is canonical for each business module
- which controller namespace owns forward changes
- which model namespace is canonical versus compatibility-only
- which aliases must be frozen instead of expanded

## Boundary

This SSOT covers active business modules only:

- Projects
- Tasks
- Documents
- Change Requests
- Notifications
- Notification Rules
- Contracts
- Payments
- RFIs
- Submittals
- Inspections / QC
- Work Templates
- Work Instances
- Deliverable Templates / Deliverables

Out of scope:

- `/_debug/*`
- universal-frame
- smart-tools
- dashboard/demo narrative surfaces
- `routes/legacy/*` as runtime owners

## Ownership Matrix

| Module | Canonical route family | Canonical controller owner | Canonical model owner | Compatibility aliases still mounted | Evidence | Decision | Risk |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Projects | `/api/zena/projects` | `App\Http\Controllers\Api\ProjectController` | `App\Models\Project` | `/api/v1/projects` -> `Src\CoreProject\Controllers\ProjectController`; canonical app controller injects `App\Services\ProjectService` directly | `routes/api_zena.php:215-222`; `app/Http/Controllers/Api/ProjectController.php`; `app/Services/ProjectService.php`; `routes/api.php:268-281`; `Src/CoreProject/routes/api.php:15-20` | `converge` | Canonical route/controller/model/service owner is explicit in `App/*`; `/api/v1/projects` remains the only mounted compatibility runtime; `LegacyProjectServiceAdapter` is removed and must not be reintroduced |
| Tasks | `/api/zena/tasks` | `App\Http\Controllers\Api\TaskController` | `App\Models\Task` | `/api/v1/tasks` -> `Src\CoreProject\Controllers\TaskController`; `/api/v1/work-template/projects/*/tasks` -> `Src\WorkTemplate\Controllers\ProjectTaskController` projection routes | `routes/api_zena.php:250-260`; `app/Http/Controllers/Api/TaskController.php`; `routes/api.php:590-605`; `Src/CoreProject/routes/api.php:93-98`; `Src/WorkTemplate/routes/api.php:67-121` | `converge` | Forward task ownership is app/zena; `/api/v1/tasks` stays mounted as compatibility runtime and work-template task routes stay frozen as adjacent projections, not competing canonical ownership |
| Documents | `/api/zena/documents` | `App\Http\Controllers\Api\SimpleDocumentController` | `App\Models\Document` | `/api/v1/documents` still mounted on the same app controller; canonical app controller no longer imports `Src\DocumentManagement\Models\LegacyDocumentAdapter`; `Src\DocumentManagement\Controllers\DocumentController` exists in code but is not the active mounted owner | `routes/api_zena.php:341-348`; `routes/api.php:496-505`; `Src/DocumentManagement/routes/api.php`; `app/Http/Controllers/Api/SimpleDocumentController.php`; `Src/DocumentManagement/Models/LegacyDocumentAdapter.php`; `tests/Feature/Zena/ZenaApiContractPhase2InvariantTest.php`; `tests/Feature/Api/DocumentManagementTest.php`; `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php` | `converge` | Forward route/controller/model owner is now explicit in `App/*`; v1 stays mounted as compatibility/business surface and dead src controller code remains frozen debt |
| Change Requests | `/api/zena/change-requests` | `App\Http\Controllers\Api\ChangeRequestController` | `App\Models\ChangeRequest` | `/api/v1/change-requests` -> `Src\ChangeRequest\Controllers\ChangeRequestController`; `Src\ChangeRequest\Models\ChangeRequest`; route/controller split is explicitly frozen because parity evidence is insufficient to remount v1 safely in this round | `routes/api_zena.php:289-299`; `app/Http/Controllers/Api/ChangeRequestController.php`; `routes/api.php:650-661`; `Src/ChangeRequest/routes/api.php`; `Src/ChangeRequest/Controllers/ChangeRequestController.php`; `tests/Feature/Api/ChangeRequestApiTest.php`; `tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`; `tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php` | `converge` | Canonical zena stack is clear, but `/api/v1` remains a real compatibility stack with its own controller/model semantics and RBAC naming drift; keep frozen until contract parity is proven |
| Notifications | `/api/zena/notifications` | `App\Http\Controllers\Api\NotificationController` | `App\Models\Notification` | `/api/v1/notifications` -> `Src\Notification\Controllers\NotificationController`; `Src\Notification\Models\Notification`; `/api/zena/auth/notifications` is an inbox projection via auth controller, not separate module ownership | `routes/api_zena.php:355-363`; `app/Http/Controllers/Api/NotificationController.php`; `Src/Notification/routes/api.php`; `Src/Notification/Controllers/NotificationController.php`; `tests/Feature/Api/NotificationApiTest.php` | `converge` | Inbox ownership is split across zena app controller, v1 src controller, and auth-projection endpoints |
| Notification Rules | `/api/v1/notification-rules` | `Src\Notification\Controllers\NotificationRuleController` | `Src\Notification\Models\NotificationRule` | `App\Models\NotificationRule` exists and notification settings are mounted at `/api/v1/settings/notifications`; no `/api/zena/notification-rules` family exists today | `Src/Notification/routes/api.php`; `Src/Notification/Controllers/NotificationRuleController.php`; `routes/api.php:804-823`; `app/Models/NotificationRule.php`; `tests/Feature/Api/NotificationApiTest.php`; `tests/Feature/Api/SettingsNotificationsApiTest.php` | `alias-and-freeze` | Active runtime is still v1/src-owned, so forward changes should not invent a second owner until an explicit convergence round moves rules into zena |
| Contracts | `/api/v1/projects/{project}/contracts` | `App\Http\Controllers\Api\ContractController` | `App\Models\Contract` | `Src\Compensation\Models\Contract` remains active through `/api/v1/compensation/*` flows but is not the canonical contract CRUD owner | `routes/api.php:826-842`; `app/Http/Controllers/Api/ContractController.php`; `app/Models/Contract.php`; `Src/Compensation/Controllers/CompensationController.php`; `Src/Compensation/Models/Contract.php`; `tests/Feature/Api/ContractApiTest.php` | `keep` | Canonical owner is clear but still lives in `/api/v1`, so contributors may wrongly try to re-home it prematurely |
| Payments | `/api/v1/contracts/{contract}/payments` | `App\Http\Controllers\Api\ContractPaymentController` | `App\Models\ContractPayment` | None mounted outside the v1 app controller family; compensation routes remain adjacent but not duplicate payment CRUD | `routes/api.php:844-857`; `app/Http/Controllers/Api/ContractPaymentController.php`; `app/Models/ContractPayment.php`; `tests/Feature/Api/ContractApiTest.php` | `keep` | Still v1-only, so contributors can misread it as legacy even though it is the only real payment runtime |
| RFIs | `/api/zena/rfis` | `App\Http\Controllers\Api\RfiController` | `App\Models\Rfi` | Role dashboards project RFIs but do not own them | `routes/api_zena.php:263-273`; `app/Http/Controllers/Api/RfiController.php`; `app/Models/Rfi.php` | `keep` | Product-grade runtime with clear ownership |
| Submittals | `/api/zena/submittals` | `App\Http\Controllers\Api\SubmittalController` | `App\Models\Submittal` | Designer dashboard projects submittal state; historical table provenance from `zena_submittals` remains unresolved | `routes/api_zena.php:276-286`; `app/Http/Controllers/Api/SubmittalController.php`; `app/Models/Submittal.php`; `tests/Feature/Api/SubmittalApiTest.php`; `database/migrations/2025_09_14_110000_create_zena_system_tables.php`; `database/migrations/2026_02_05_000000_add_tenant_id_to_zena_submittals_table.php` | `keep` | Module is product-grade at route/controller/model/test level, but physical table provenance is still debt |
| Inspections / QC | `/api/zena/inspections` | `App\Http\Controllers\Api\InspectionController` | `App\Models\QcInspection` with `App\Models\QcPlan` and `App\Models\Ncr` adjacent | Role dashboard inspection views are projections only; no parallel src controller family was found for inspections | `routes/api_zena.php:302-311`; `app/Http/Controllers/Api/InspectionController.php`; `app/Models/QcInspection.php`; `database/migrations/2025_09_20_141930_create_qc_plans_table.php`; `database/migrations/2025_09_20_142005_create_qc_inspections_table.php`; `tests/Feature/InspectionNcrWorkflowTest.php` | `keep` | Product-grade enough to keep, but it is still QMS-lite attached to project/QC-plan lineage rather than a standalone quality suite |
| Work Templates | `/api/zena/work-templates` | `App\Http\Controllers\Api\WorkTemplateController` | `App\Models\WorkTemplate` | `/api/v1/work-templates` -> `Src\CoreProject\Controllers\WorkTemplateController`; `/api/v1/work-template/templates` -> `Src\WorkTemplate\Controllers\TemplateController` | `routes/api_zena.php:229-236`; `app/Http/Controllers/Api/WorkTemplateController.php`; `app/Models/WorkTemplate.php`; `routes/api.php:617-623`; `Src/CoreProject/routes/api.php:101-106`; `Src/WorkTemplate/routes/api.php` | `converge` | Backbone is strongest in zena/app, but legacy v1 families still expose two different template stacks |
| Work Instances | `/api/zena/work-instances` | `App\Http\Controllers\Api\WorkInstanceController` | `App\Models\WorkInstance` | `/api/zena/projects/{project}/work-instances` is a project-scoped alias owned by the same controller; no v1 work-instance family found | `routes/api_zena.php:218`; `routes/api_zena.php:366-376`; `app/Http/Controllers/Api/WorkInstanceController.php`; `app/Models/WorkInstance.php`; `tests/Feature/Api/WorkInstanceEndToEndSmokeFlowTest.php` | `keep` | Ownership is clear, but it depends on WorkTemplate convergence staying disciplined |
| Deliverable Templates / Deliverables | `/api/zena/deliverable-templates` and `/api/zena/work-instances/{id}/export*` | `App\Http\Controllers\Api\DeliverableTemplateController` and `App\Http\Controllers\Api\WorkInstanceController` | `App\Models\DeliverableTemplate` plus `App\Models\DeliverableTemplateVersion` | None mounted outside zena for deliverable-template CRUD; deliverable export is intentionally coupled to work-instance runtime | `routes/api_zena.php:239-247`; `routes/api_zena.php:375-376`; `app/Http/Controllers/Api/DeliverableTemplateController.php`; `app/Http/Controllers/Api/WorkInstanceController.php`; `app/Models/DeliverableTemplate.php`; `tests/Feature/Api/DeliverableTemplateMvpApiTest.php`; `tests/Feature/Api/WorkInstanceDeliverableExportApiTest.php` | `keep` | This is one of the cleanest current owners and should not be diluted by new v1 aliases |

## Compatibility Policy

### Keep mounted compatibility surfaces

- `/api/v1/projects`, `/api/v1/tasks`, `/api/v1/work-templates`
  - Reason: active compatibility/platform surface still consumed through `Src\CoreProject`.
- `/api/v1/change-requests`
  - Reason: active mounted legacy/business contract; cannot be removed blind.
- `/api/v1/notifications` and `/api/v1/notification-rules`
  - Reason: active runtime and tests still target `Src\Notification`.
- `/api/v1/projects/{project}/contracts` and `/api/v1/contracts/{contract}/payments`
  - Reason: these are the only active contract/payment runtimes today.
- `/api/v1/documents`
  - Reason: compatibility/business surface still routed to the hardened app document controller.

### Freeze thin model aliases (updated 2026-07-14)

The following aliases have been deleted (class + factory removed; migration references preserved):

- ~~`App\Models\ZenaTask`~~ — deleted (2026-07-14)
- ~~`App\Models\ZenaNotification`~~ — deleted (2026-07-14)
- ~~`App\Models\ZenaRfi`~~ — deleted (2026-07-14)
- ~~`App\Models\ZenaSubmittal`~~ — deleted (2026-07-14)
- ~~`App\Models\ZenaProject`~~ — deleted (2026-07-14; historical migration `2025_09_15_144442` preserved)
- ~~`App\Models\ZenaChangeRequest`~~ — deleted (2026-07-14)

Retained aliases (active seeder/test consumers):

- `App\Models\ZenaPermission` — refs: self, `ZenaPermissionFactory`, 4 seeders, 4 tests
- `App\Models\ZenaRole` — refs: self, `ZenaRoleFactory`, historical migration, seeder, 1 test

Policy:

- do not create more `Zena*` aliases
- retained aliases must not gain new behavior

### Convergence debt, not bounded contexts to expand

- `Src\CoreProject`
- `Src\ChangeRequest`
- `Src\Notification`
- `Src\DocumentManagement` adapters

Policy:

- treat these as compatibility/debt mounting layers unless a module has no app-owned runtime yet
- do not start new business modules in `Src/*`
- do not create a second controller/model owner when zena/app ownership already exists

## Non-Canonical Surfaces Not To Expand

Do not treat these as product ownership surfaces:

- `/_debug/*`
- universal-frame
- smart-tools
- `routes/legacy/api_v1.php`
- unmounted/stale demo or public artifacts

## Invitation — Documented TenantScope Exclusion

`App\Models\Invitation` is intentionally **NOT** decorated with `TenantScope`:

- **Cross-tenant lookup**: Invitation tokens are consumed by users who may not yet belong to the inviting tenant. The accept flow resolves invitations via `TeamInvitationService::resolveByToken($tenantId, ...)` which manually scopes by tenant (`->where('tenant_id', $tenantId)` at `app/Services/TeamInvitationService.php:23-24`).
- **Token-based access**: `Invitation::findByToken()` is used in decline routes (`routes/web.php:480`) without tenant middleware — a global scope would break these unauthenticated flows.
- **Artisan commands**: `BackfillInvitationTokenHash` queries all invitations across tenants.
- **Test coverage**: `tests/Feature/Invitation/InvitationCrossTenantTest.php` proves cross-tenant accept returns 404 and same-tenant accept succeeds. A static guard test asserts Invitation does NOT use the trait.

## Unknowns

- The current physical creation path for the `submittals` table remains `UNKNOWN`; runtime evidence proves active use of `submittals`, but migration provenance still points back to `zena_submittals` history.
- Notification rules do not yet have a `/api/zena/*` owner, so their forward convergence target is architectural intent, not current runtime fact.

## How Future Contributors Should Choose Where New Code Goes

1. If the module already has `/api/zena/*` ownership, add forward business API changes under `app/Http/Controllers/Api` and canonical `app/Models`.
2. If the module is currently v1-only but still business-active, treat `/api/v1/*` as compatibility/platform runtime and avoid adding a second parallel owner unless a convergence round explicitly creates it.
3. Do not expand `Src/*` for modules already owned by zena/app.
4. Do not add behavior to `Zena*` alias models.
5. If route/controller/model evidence conflicts, runtime route truth wins and missing proof stays `UNKNOWN`.
