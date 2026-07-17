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

## `App\Models\ZenaProject` (frozen thin alias — 6 files, updated 2026-07-14)

Empty subclass of `App\Models\Project`, kept per SSOT policy only because
tests/factories reference it. Do not add behavior; do not delete without
first confirming none of these 6 still need it:

- `app/Models/ZenaProject.php` (the class itself)
- `database/factories/DocumentFactory.php` (imports ZenaProject)
- `database/factories/ZenaProjectFactory.php`
- `database/migrations/2025_09_15_144442_unify_projects_table_schema.php` (historical migration — must not be edited)
- `tests/Feature/Api/SubmittalApiTest.php`
- `tests/Feature/Api/SubmittalShowApiTest.php`

Original list had 14 files; 8 were removed during batches 4-5 cleanup
(`ZenaRbacSeeder.php`, 6 test files, `ZenaSubmittalFactory.php` no longer
imports ZenaProject). None are production request-handling code.

## `Src\CoreProject\Models\LegacyProjectAdapter` (7 files — resolved by Task 1 of this plan)

- `src/CoreProject/Models/LegacyProjectAdapter.php` (the class itself — removed, was fully unreferenced dead code)
- `app/Http/Controllers/Api/ProjectAnalyticsController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectManagerController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectMilestoneController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Api/ProjectTemplateController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Web/DocumentManagementController.php` — migrated to `App\Models\Project` (Task 1)
- `app/Http/Controllers/Web/ProjectBulkController.php` — migrated to `App\Models\Project` (Task 1)

## Dead file removal (executed 2026-07-13, commit `272ba5fe`)

11 files previously listed as DEAD in the `Src\CoreProject\Models\Project`
traced batches below have been removed. See
`docs/change-proposals/2026-07-13-dead-project-model-files-removal.md`
(status: EXECUTED) for the full list and evidence.

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

### Traced batch 1 (2026-07-13) — 8 LIVE, 2 DEAD

- `app/Console/Commands/CleanMockData.php` — **LIVE** — Artisan command, auto-registered by Laravel console kernel. Imports `Src\CoreProject\Models\Project` at line 6.
- `app/Http/Controllers/Api/AnalyticsController.php` — **LIVE** — Routed at `routes/api.php:1052-1054` (`/analytics/tasks`, `/analytics/projects`, `/analytics/dashboard`). Imports `Src\CoreProject\Models\Project` at line 8.
- `app/Http/Controllers/Api/ExportController.php` — **LIVE** — Routed at `routes/api.php:1020-1021` (`/tasks/bulk/export`, `/projects/bulk/export`). Imports `Src\CoreProject\Models\Project` at line 8.
- `app/Http/Controllers/TaskController.php` — **LIVE** — Routed at `routes/api.php:126` (`/tasks`), `:462` (in api group), `:590` (`apiResource('tasks')`). Uses `Src\CoreProject\Models\Project` inline at line 348.
- `app/Http/Controllers/Web/DocumentController.php` — **LIVE** — Routed at `routes/web.php:406-409, 550` (`/documents/*`). Imports `Src\CoreProject\Models\Project` at line 13.
- `app/Http/Controllers/Web/ProjectController.php` — **LIVE** — Routed at `routes/web.php:362-372` (`/projects/*`). Imports `Src\CoreProject\Models\Project` at line 14.
- `app/Http/Controllers/Web/TaskController.php` — **LIVE** — Routed at `routes/web.php:385-403` (`/tasks/*`). Imports `Src\CoreProject\Models\Project` at line 13.
- `app/Http/Controllers/WorkTemplateController.php` — **LIVE** — Routed via `routes/api_zena.php:222-247, 513-514` (loaded at `routes/api.php:1016`). Uses `Src\CoreProject\Models\Project` inline at line 251.
- `app/Http/Middleware/ProjectAccessMiddleware.php` — **DEAD** — Middleware alias registration commented out in `CoreProjectServiceProvider::registerMiddleware()` (line 68-77). Not used in any route definition. No consumers found.
- `app/Http/Middleware/ProjectOwnershipMiddleware.php` — **DEAD** — Same as above: registration commented out, no route usage, no consumers.

### Traced batch 2 (2026-07-13) — 5 LIVE, 5 DEAD

- `app/Http/Requests/UpdateProjectRequest.php` — **DEAD** — No controller type-hints this Form Request; zero references outside its own file.
- `app/Models/InteractionLog.php` — **LIVE** — Used by `App\Services\ComponentService` (line 10) and `App\Services\AuditService` (line 7). Imports `Src\CoreProject\Models\Project` at line 11.
- `app/Models/NotificationRule.php` — **LIVE** — Relationship from `App\Models\User` (line 269: `$this->hasMany(NotificationRule::class)`). Imports `Src\CoreProject\Models\Project` at line 12.
- `app/Models/UserRoleProject.php` — **LIVE** — Used by `DesignerDashboardController`, `PmDashboardController`, `WorkTemplateController` (all routed). Imports `Src\CoreProject\Models\Project` at line 54.
- `app/Services/BaselineService.php` — **DEAD** — Only consumed by unrouted `App\Http\Controllers\BaselineController`; the routed `Src\CoreProject\Controllers\BaselineController` uses `Src\CoreProject\Services\BaselineService` instead.
- `app/Services/CompensationService.php` — **DEAD** — Only consumed by unrouted `App\Http\Controllers\CompensationController`; the routed `Src\Compensation\Controllers\CompensationController` uses `Src\Compensation\Services\CompensationService`.
- `app/Services/ConditionalTagService.php` — **LIVE** — Constructor-injected into routed `App\Http\Controllers\TaskController` (line 26).
- `app/Services/InteractionLogService.php` — **DEAD** — Only consumed by unrouted `App\Http\Controllers\InteractionLogController`; routes use `Src\InteractionLogs\Controllers\InteractionLogController`.
- `app/Services/TemplateService.php` — **DEAD** — Only consumed by unrouted `App\Http\Controllers\TemplateController`; routes use `Src\WorkTemplate\Controllers\TemplateController`.
- `src/ChangeRequest/Listeners/ChangeRequestEventListener.php` — **LIVE** — Registered in `ChangeRequestServiceProvider` (line 50: `$this->app['events']->subscribe(...)`); provider loaded in `config/app.php`. Imports `Src\CoreProject\Models\Project` at line 24.

### Traced batch 3 (2026-07-13) — 7 LIVE, 3 DEAD

- `src/ChangeRequest/Models/ChangeRequest.php` — **LIVE** — Used by `ChangeRequestEventListener`, `ChangeRequestResource`, `ChangeRequestService` — all within routed ChangeRequest module. Imports `Src\CoreProject\Models\Project` at line 12.
- `src/Compensation/Models/Contract.php` — **LIVE** — Used by `TaskCompensation` (belongsTo), `ContractResource`, `CompensationService` — all within routed Compensation module. Imports `Src\CoreProject\Models\Project` at line 10.
- `src/Compensation/Services/CompensationService.php` — **LIVE** — Registered in `CompensationServiceProvider` (loaded in `config/app.php`); consumed by routed `Src\Compensation\Controllers\CompensationController`. Imports `Src\CoreProject\Models\Project` at line 11.
- `src/CoreProject/Events/ProjectCreated.php` — **LIVE** — Dispatched by live `Src\CoreProject\Controllers\ProjectController::store()` (line 134); listened by `EventBusServiceProvider` and `EventServiceProvider` (both loaded in `config/app.php`).
- `src/CoreProject/Jobs/RecalculateProjectRollupJob.php` — **DEAD** — Dispatched only by `UpdateProjectProgressListener`, which is fully commented out in `CoreProject/Providers/EventServiceProvider.php` (lines 16-20). Not registered anywhere.
- `src/CoreProject/Middleware/ProjectAccessMiddleware.php` — **DEAD** — Middleware alias registration commented out in `CoreProjectServiceProvider::registerMiddleware()` (lines 68-77). Not used in any route definition.
- `src/CoreProject/Middleware/ProjectOwnershipMiddleware.php` — **DEAD** — Same as above.
- `src/CoreProject/Middleware/ProjectStatusMiddleware.php` — **DEAD** — Same — registration commented out, not used in any route.
- `src/CoreProject/Requests/StoreProjectRequest.php` — **LIVE** — Used by live `Src\CoreProject\Controllers\ProjectController::store()` (line 115).
- `src/CoreProject/Requests/UpdateProjectRequest.php` — **LIVE** — Used by live `Src\CoreProject\Controllers\ProjectController::update()` (line 188).

### Traced batch 4 (2026-07-13) — 10 LIVE, 0 DEAD

- `src/DocumentManagement/Models/Document.php` — **LIVE** — Used throughout `DocumentManagement` module (routed via `require base_path('src/DocumentManagement/routes/api.php')` at `routes/api.php:1037`). Imports `Src\CoreProject\Models\Project` at line 13.
- `src/InteractionLogs/Models/InteractionLog.php` — **LIVE** — Used by `InteractionLogService`, `InteractionLogController`; routed via `InteractionLogServiceProvider` (loaded in `config/app.php`). Imports `Src\CoreProject\Models\Project` at line 10.
- `src/InteractionLogs/Services/InteractionLogService.php` — **LIVE** — Registered in `InteractionLogServiceProvider` (loaded in `config/app.php`). Imports `Src\CoreProject\Models\Project` at line 8.
- `src/Notification/Models/NotificationRule.php` — **LIVE** — Used by `NotificationRuleService`, `NotificationRuleController`; routed via `NotificationServiceProvider` (loaded in `config/app.php`). Imports `Src\CoreProject\Models\Project` at line 12.
- `src/RBAC/Models/UserRoleProject.php` — **LIVE** — Used by RBAC module; routed via `RBACServiceProvider` (loaded in `config/app.php`). Imports `Src\CoreProject\Models\Project` at line 56.
- `src/WorkTemplate/Events/TaskConditionalToggled.php` — **LIVE** — Dispatched by `ProjectTaskService` (line 80); listened by `WorkTemplateEventListener` (registered in `WorkTemplateServiceProvider`, loaded in `config/app.php`). Imports `Src\CoreProject\Models\Project` at line 8.
- `src/WorkTemplate/Events/TemplateApplied.php` — **LIVE** — Listened by `WorkTemplateEventListener` (same registration as above). Imports `Src\CoreProject\Models\Project` at line 8.
- `src/WorkTemplate/Models/ProjectTask.php` — **LIVE** — Used by `ProjectTaskService`, `ProjectTaskController`, `TemplateService`, `TaskConditionalToggled`, `UpdateTaskRequest` — all within routed WorkTemplate module. Imports `Src\CoreProject\Models\Project` at line 12.
- `src/WorkTemplate/Requests/ApplyTemplateRequest.php` — **LIVE** — Used by `Src\WorkTemplate\Controllers\TemplateController::apply()` (line 215), itself routed via `require base_path('src/WorkTemplate/routes/api.php')` at `routes/api.php:1041`. Imports `Src\CoreProject\Models\Project` at line 7.
- `src/WorkTemplate/Services/TemplateService.php` — **LIVE** — Registered in `WorkTemplateServiceProvider` (line 25); consumed by routed controllers. Imports `Src\CoreProject\Models\Project` at line 10.

### Summary

| Category | Count |
|----------|-------|
| Traced LIVE (part of tested runtime) | 30 |
| Traced DEAD (no reachable consumers) | 10 |
| **Total traced** | **40** |
| Not yet traced | **0** |

## Methodology note for whoever traces the remaining 40 files

A file is only safe to call "dead" after checking ALL of:
1. `Route::` call sites in `routes/*.php` for the file's controller (if it is one).
2. `require base_path(...)` lines in `routes/api.php` mounting a module-local route file (e.g. `src/CoreProject/routes/api.php`, `src/WorkTemplate/routes/api.php`) — grep `require base_path` in `routes/api.php` for the full current list of these mounts.
3. `config/app.php`'s `providers` array, for whether any provider registering the file (if it's an event listener) is actually loaded — a provider registering a listener means nothing if the provider itself isn't in this array.
4. Constructor dependency injection from an already-confirmed-live class.
5. Inline `app(SomeClass::class)` or `new SomeClass()` resolution inside an already-confirmed-live class's method body (constructor DI alone won't catch this).

This session's first pass at this exact task (documented in the design spec's revision note) got step 2 wrong and concluded several genuinely-live files were dead — do not skip any of the 5 checks above.
