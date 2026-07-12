# Project-Related Class Reference Inventory

Last generated: 2026-07-12 (regenerate with `scripts/architecture/dump-project-model-references.sh`)

This inventory exists to satisfy `docs/architecture/module-ownership-ssot.md`'s
compatibility policy: before any future slice may migrate files off
`Src\CoreProject\Models\Project` or touch the `/api/v1/*` compatibility
runtime, it needs this reference count and (for files already investigated)
a real reachability verdict — not a guess. This document does not itself
authorize any migration; see the "Not yet traced" note on every file below
that lacks a reachability verdict.

## `App\Models\Project` (canonical — 70 files)

The canonical model per `docs/architecture/module-ownership-ssot.md`. No
action needed on any of these — they are already using the correct class.
Full current list: see `storage/app/architecture/project-model-references.md`
(regenerate via the dump script; not reproduced here since it changes as
the canonical model's adoption grows, unlike the frozen classes below).

## `App\Models\ZenaProject` (frozen thin alias — 13 files)

Empty subclass of `App\Models\Project`, kept per SSOT policy only because
tests/factories reference it. Do not add behavior; do not delete without
first confirming none of these 13 still need it:

- `app/Models/ZenaProject.php` (the class itself)
- `database/factories/ZenaProjectFactory.php`
- `database/factories/ZenaSubmittalFactory.php`
- `database/migrations/2025_09_15_144442_unify_projects_table_schema.php`
- `database/seeders/ZenaRbacSeeder.php`
- `tests/Feature/Api/ChangeRequestApiTest.php`
- `tests/Feature/Api/IntegrationTest.php`
- `tests/Feature/Api/PerformanceTest.php`
- `tests/Feature/Api/RfiApiTest.php`
- `tests/Feature/Api/SecurityTest.php`
- `tests/Feature/Api/SubmittalApiTest.php`
- `tests/Feature/Api/SubmittalShowApiTest.php`
- `tests/Feature/Api/TaskApiTest.php`
- `tests/Feature/Api/TaskDependenciesTest.php`

All 13 references are in tests/factories/seeders/a historical migration —
none are production request-handling code. No reachability tracing needed
beyond this; this list is exhaustive as of 2026-07-12.

## `Src\CoreProject\Models\LegacyProjectAdapter` (7 files — resolved by Task 1 of this plan)

- `src/CoreProject/Models/LegacyProjectAdapter.php` (the class itself — stays, now fully unreferenced)
- `app/Http/Controllers/Api/ProjectAnalyticsController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectManagerController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectMilestoneController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectTemplateController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Web/DocumentManagementController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Web/ProjectBulkController.php` — migrated to `App\Models\Project` (Task 1)

## `Src\CoreProject\Models\Project` (compatibility/debt — 54 files, NOT to be migrated without a future slice)

Reachability verdicts below are limited to files investigated during the
2026-07-12 brainstorm/spec/plan cycle. **Every file marked "Not yet traced"
must be traced (routes, `require base_path(...)` mounts, event-provider
registrations in `config/app.php`, constructor DI, method-level `app()`
resolution) before a future slice may touch it** — do not assume dead code
from a grep alone; this session found that mistake once already (see the
design spec's revision note).

### Traced, confirmed LIVE (part of the tested `/api/v1/*` compatibility runtime — do not touch without a dedicated, reviewed slice)

- `src/CoreProject/Controllers/ProjectController.php` — routed via `require base_path('src/CoreProject/routes/api.php')` in `routes/api.php:1039`; operates on `Project::` directly (`with()`, `create()`, `createFromTemplate()`, `findOrFail()`)
- `src/CoreProject/Controllers/TaskController.php` — same route mount; constructor-injects `TaskService` and `ConditionalTagService`
- `src/CoreProject/Controllers/WorkTemplateController.php` — same route mount, plus a second route family in `routes/api.php` directly; `app()`-resolves `WorkTemplateApplicationService` and `ConditionalTagService` inline
- `src/CoreProject/Services/WorkTemplateApplicationService.php` — constructor-injected into the live `WorkTemplateController`
- `src/CoreProject/Services/ConditionalTagService.php` — used directly by the live `WorkTemplateController`, and constructor-injected into `WorkTemplateApplicationService`
- `src/CoreProject/Services/TaskService.php` — constructor-injected into `WorkTemplateApplicationService` (same-namespace type-hint)
- `src/CoreProject/Services/BaselineService.php` — constructor-injected into `BaselineController`, itself routed both via `src/CoreProject/routes/api.php` and directly in `routes/api.php`
- `src/CoreProject/Services/ComponentService.php` — instantiated inline (`new ComponentService()`) inside the live `ComponentController`
- `src/CoreProject/Listeners/ProjectCalculationListener.php` — registered in `src/Foundation/Providers/EventBusServiceProvider.php` (listed in `config/app.php`), maps `Src\CoreProject\Events\ComponentProgressUpdated`/`ComponentCostUpdated` → this listener; both events are dispatched by the live `App\Models\Component`

### Traced, confirmed DEAD as of 2026-07-12 (still not touched in this slice — future slice must re-verify before acting, since reachability can change)

- `src/CoreProject/Services/ProjectService.php` — zero consumers found anywhere; `Src\CoreProject\Controllers\ProjectController` (confirmed live above) operates on `Project::` directly and does not use this service
- `src/CoreProject/Listeners/ConditionalTagListener.php` — not registered in any provider
- `src/CoreProject/Listeners/ProgressCalculationListener.php` — registered only in `src/Foundation/Providers/EventServiceProvider.php`, which is itself absent from `config/app.php`'s providers array
- `src/CoreProject/Listeners/ProjectProgressListener.php` — not registered anywhere (do not confuse with the separately-registered `UpdateProjectProgressListener`)
- `src/CoreProject/Listeners/NotificationListener.php` — not registered anywhere

### Not yet traced (39 files — no reachability verdict, do not assume either way)

- `app/Console/Commands/CleanMockData.php`
- `app/Http/Controllers/Api/AnalyticsController.php`
- `app/Http/Controllers/Api/ExportController.php`
- `app/Http/Controllers/TaskController.php`
- `app/Http/Controllers/Web/DocumentController.php`
- `app/Http/Controllers/Web/ProjectController.php`
- `app/Http/Controllers/Web/TaskController.php`
- `app/Http/Controllers/WorkTemplateController.php`
- `app/Http/Middleware/ProjectAccessMiddleware.php`
- `app/Http/Middleware/ProjectOwnershipMiddleware.php`
- `app/Http/Requests/UpdateProjectRequest.php`
- `app/Models/InteractionLog.php`
- `app/Models/NotificationRule.php`
- `app/Models/UserRoleProject.php`
- `app/Services/BaselineService.php`
- `app/Services/CompensationService.php`
- `app/Services/ConditionalTagService.php`
- `app/Services/InteractionLogService.php`
- `app/Services/TemplateService.php`
- `src/ChangeRequest/Listeners/ChangeRequestEventListener.php`
- `src/ChangeRequest/Models/ChangeRequest.php`
- `src/Compensation/Models/Contract.php`
- `src/Compensation/Services/CompensationService.php`
- `src/CoreProject/Events/ProjectCreated.php`
- `src/CoreProject/Jobs/RecalculateProjectRollupJob.php`
- `src/CoreProject/Middleware/ProjectAccessMiddleware.php`
- `src/CoreProject/Middleware/ProjectOwnershipMiddleware.php`
- `src/CoreProject/Middleware/ProjectStatusMiddleware.php`
- `src/CoreProject/Requests/StoreProjectRequest.php`
- `src/CoreProject/Requests/UpdateProjectRequest.php`
- `src/DocumentManagement/Models/Document.php`
- `src/InteractionLogs/Models/InteractionLog.php`
- `src/InteractionLogs/Services/InteractionLogService.php`
- `src/Notification/Models/NotificationRule.php`
- `src/RBAC/Models/UserRoleProject.php`
- `src/WorkTemplate/Events/TaskConditionalToggled.php`
- `src/WorkTemplate/Events/TemplateApplied.php`
- `src/WorkTemplate/Models/ProjectTask.php`
- `src/WorkTemplate/Requests/ApplyTemplateRequest.php`
- `src/WorkTemplate/Services/TemplateService.php`

## Methodology note for whoever traces the remaining 39 files

A file is only safe to call "dead" after checking ALL of:
1. `Route::` call sites in `routes/*.php` for the file's controller (if it is one).
2. `require base_path(...)` lines in `routes/api.php` mounting a module-local route file (e.g. `src/CoreProject/routes/api.php`, `src/WorkTemplate/routes/api.php`) — grep `require base_path` in `routes/api.php` for the full current list of these mounts.
3. `config/app.php`'s `providers` array, for whether any provider registering the file (if it's an event listener) is actually loaded — a provider registering a listener means nothing if the provider itself isn't in this array.
4. Constructor dependency injection from an already-confirmed-live class.
5. Inline `app(SomeClass::class)` or `new SomeClass()` resolution inside an already-confirmed-live class's method body (constructor DI alone won't catch this).

This session's first pass at this exact task (documented in the design spec's revision note) got step 2 wrong and concluded several genuinely-live files were dead — do not skip any of the 5 checks above.
