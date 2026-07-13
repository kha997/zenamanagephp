# Dead File Removal Proposal — `Src\CoreProject\Models\Project` References

**Date:** 2026-07-13
**Branch:** `worktree-zena-project-model-consolidation` (PR #163)
**Status:** Awaiting user approval — NO files deleted yet.

---

## Summary

10 files in `app/` and `src/` import `Src\CoreProject\Models\Project` but have no live consumers. Each was re-verified independently on 2026-07-13 using the 5-step methodology from `docs/architecture/project-model-reference-inventory.md`.

**All 10 files confirmed DEAD.** No corrections needed to the inventory.

---

## Files for Removal

### Group 1: Middleware (alias registration commented out)

| # | File | Evidence | Risk |
|---|------|----------|------|
| 1 | `app/Http/Middleware/ProjectAccessMiddleware.php` | Alias `project.access` registration commented out in `CoreProjectServiceProvider:68-77`. Not in `app/Http/Kernel.php`. No route uses this middleware. Only reference: `ProjectModelReferenceAllowlistTest` (inventory listing). | Low — never registered |
| 2 | `app/Http/Middleware/ProjectOwnershipMiddleware.php` | Alias `project.ownership` registration commented out in `CoreProjectServiceProvider:68-77`. Not in `app/Http/Kernel.php`. No route uses this middleware. | Low — never registered |
| 3 | `src/CoreProject/Middleware/ProjectAccessMiddleware.php` | Alias registration commented out in `CoreProjectServiceProvider:68-77`. No route definition uses `project.access`. | Low — never registered |
| 4 | `src/CoreProject/Middleware/ProjectOwnershipMiddleware.php` | Alias registration commented out in `CoreProjectServiceProvider:68-77`. No route definition uses `project.ownership`. | Low — never registered |
| 5 | `src/CoreProject/Middleware/ProjectStatusMiddleware.php` | Alias registration commented out in `CoreProjectServiceProvider:68-77`. No route definition uses `project.status`. | Low — never registered |

### Group 2: Legacy `app/` Services (superseded by `src/` equivalents)

| # | File | Evidence | Risk |
|---|------|----------|------|
| 6 | `app/Services/BaselineService.php` | Only consumed by `app/Http/Controllers/BaselineController.php` (line 22, constructor DI). That controller is NOT routed — no `Route::` entry found, not in `routes/api.php` or `app/routes/api_zena.php`. The routed `Src\CoreProject\Controllers\BaselineController` uses `Src\CoreProject\Services\BaselineService`. | Low — unrouted consumer |
| 7 | `app/Services/CompensationService.php` | Only consumed by `app/Http/Controllers/CompensationController.php` (line 30, constructor DI). That controller is NOT routed. The routed `Src\Compensation\Controllers\CompensationController` uses `Src\Compensation\Services\CompensationService`. | Low — unrouted consumer |
| 8 | `app/Services/InteractionLogService.php` | Only consumed by `app/Http/Controllers/InteractionLogController.php` (line 18, constructor DI). That controller is NOT routed. The routed `Src\InteractionLogs\Controllers\InteractionLogController` uses `Src\InteractionLogs\Services\InteractionLogService`. | Low — unrouted consumer |
| 9 | `app/Services/TemplateService.php` | Only consumed by `app/Http/Controllers/TemplateController.php` (line 22, constructor DI). That controller is NOT routed. The routed `Src\WorkTemplate\Controllers\TemplateController` uses `Src\WorkTemplate\Services\TemplateService`. | Low — unrouted consumer |

### Group 3: Orphaned Request/Job

| # | File | Evidence | Risk |
|---|------|----------|------|
| 10 | `app/Http/Requests/UpdateProjectRequest.php` | No controller type-hints this Form Request. `Web\ProjectController` and `Api\ProjectController` use their own `src/CoreProject/Requests/*` equivalents. Zero references outside its own file. | Low — never type-hinted |
| 11 | `src/CoreProject/Jobs/RecalculateProjectRollupJob.php` | Dispatched only by `UpdateProjectProgressListener` (line 26). That listener is fully commented out in `CoreProjectServiceProvider` (lines 16-20 of `EventServiceProvider`). Not registered in any event provider. | Low — dispatch source is dead |

---

## Proposed Actions

For each file, the removal is a single `git rm`:

```bash
# Group 1: Middleware
git rm app/Http/Middleware/ProjectAccessMiddleware.php
git rm app/Http/Middleware/ProjectOwnershipMiddleware.php
git rm src/CoreProject/Middleware/ProjectAccessMiddleware.php
git rm src/CoreProject/Middleware/ProjectOwnershipMiddleware.php
git rm src/CoreProject/Middleware/ProjectStatusMiddleware.php

# Group 2: Legacy services
git rm app/Services/BaselineService.php
git rm app/Services/CompensationService.php
git rm app/Services/InteractionLogService.php
git rm app/Services/TemplateService.php

# Group 3: Orphaned request/job
git rm app/Http/Requests/UpdateProjectRequest.php
git rm src/CoreProject/Jobs/RecalculateProjectRollupJob.php
```

**After removal:** update `tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php` to remove the deleted file paths from the allowlist array (lines 32-33, 57-58).

---

## Post-Removal Verification

```bash
php artisan test tests/Feature/Architecture/   # must pass
php artisan test --testsuite=Feature            # baseline ~870 passed
php artisan route:list                          # verify no broken routes
```

---

## Risk Assessment

**Overall risk: LOW**

- 3 middleware files: alias registration was commented out — never loaded, never used
- 4 legacy services: consumed only by unrouted `app/Http/Controllers/*` counterparts that are themselves superseded by `src/` controllers
- 1 form request: never type-hinted by any controller
- 1 job: dispatched only by a fully commented-out listener

**Potential risk:** If any `app/Http/Controllers/*` file (BaselineController, CompensationController, InteractionLogController, TemplateController) is somehow routed via a mechanism not visible in `routes/api.php` or `app/routes/api_zena.php`, removing the service would cause a runtime error. However, grep of all route files confirms no such routing exists.

---

**DECISION (2026-07-13): User APPROVED removal of all 11 files.** Execute in 2 batches per `docs/superpowers/plans/2026-07-13-opencode-handoff-3.md` Task K1: batch 1 = the 7 `app/` files, full suite green, then batch 2 = the 4 `src/CoreProject/{Middleware,Jobs}` files, full suite green again. Any red test → revert that batch immediately.
