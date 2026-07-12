# Project Model Consolidation — Discovery Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Finish consolidating the 6 non-canonical, non-compatibility-runtime controllers still using `Src\CoreProject\Models\LegacyProjectAdapter` onto `App\Models\Project` directly, and build the discovery inventory + forward-guard test that a future slice needs before it can safely touch anything in the still-mounted `/api/v1/*` `Src\CoreProject` compatibility runtime.

**Architecture:** Two independent, low-risk deliverables. (1) A mechanical `use`-import swap in 6 already-`App`-namespaced files, verified by a new architecture test mirroring the existing `ModuleOwnershipSourceInvariantTest` pattern. (2) A documentation + test slice: a reproducible reference-dump script (following the existing `scripts/ssot/dump_routes.sh` convention), a written inventory document recording exactly which files reference each of the 4 Project-related classes and what's already known about their reachability, and an allowlist-based architecture test that fails if any *new* file starts importing `Src\CoreProject\Models\Project`.

**Tech Stack:** Laravel 12, PHPUnit, bash (matching the existing `scripts/ssot/*.sh` convention).

## Global Constraints

- **`App\Models\Project` is the canonical model owner for Projects**, per `docs/architecture/module-ownership-ssot.md` — do not contradict or re-litigate this.
- **`/api/v1/projects` → `Src\CoreProject\Controllers\ProjectController` is a live, tested, intentional compatibility runtime** — do not modify any file under `src/CoreProject/Controllers`, `src/CoreProject/Services`, or `src/CoreProject/Listeners` in this plan. Confirmed live via `routes/api.php:1039`'s `require base_path('src/CoreProject/routes/api.php')`.
- **No new behavior in `App\Models\ZenaProject`, `Src\CoreProject\Models\Project`, or `Src\CoreProject\Models\LegacyProjectAdapter`** — this plan only changes which class *other* files import, never the 4 classes' own bodies.
- **No new `Zena*`-prefixed alias classes.**
- **No database migrations** — this is a class-reference and documentation slice.
- **File deletion is unavailable this session** — `LegacyProjectAdapter.php` becomes unreferenced by Task 1 but stays on disk.
- **`declare(strict_types=1)`** at the top of every new/modified PHP file.

---

### Task 1: Phase A — swap `LegacyProjectAdapter` for `App\Models\Project` in 6 controllers

**Files:**
- Modify: `app/Http/Controllers/Web/ProjectBulkController.php`
- Modify: `app/Http/Controllers/Web/DocumentManagementController.php`
- Modify: `app/Http/Controllers/Api/ProjectTemplateController.php`
- Modify: `app/Http/Controllers/Api/ProjectAnalyticsController.php`
- Modify: `app/Http/Controllers/Api/ProjectManagerController.php`
- Modify: `app/Http/Controllers/Api/ProjectMilestoneController.php`
- Test: `tests/Feature/Architecture/LegacyProjectAdapterRemovalTest.php` (new)

**Interfaces:**
- Consumes: nothing from other tasks.
- Produces: nothing later tasks depend on — Task 1 is independent of Tasks 2-3.

- [ ] **Step 1: Write the failing architecture test**

Create `tests/Feature/Architecture/LegacyProjectAdapterRemovalTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Confirms the 6 non-canonical, non-v1-compatibility-runtime controllers
 * that historically used Src\CoreProject\Models\LegacyProjectAdapter now
 * reference App\Models\Project directly. LegacyProjectAdapter is an empty
 * subclass of App\Models\Project (zero behavior change), and none of
 * these 6 files are part of the /api/v1/* Src\CoreProject compatibility
 * runtime (see docs/architecture/module-ownership-ssot.md) — safe to
 * consolidate, unlike anything under src/CoreProject/Controllers.
 */
class LegacyProjectAdapterRemovalTest extends TestCase
{
    private const MIGRATED_FILES = [
        'app/Http/Controllers/Web/ProjectBulkController.php',
        'app/Http/Controllers/Web/DocumentManagementController.php',
        'app/Http/Controllers/Api/ProjectTemplateController.php',
        'app/Http/Controllers/Api/ProjectAnalyticsController.php',
        'app/Http/Controllers/Api/ProjectManagerController.php',
        'app/Http/Controllers/Api/ProjectMilestoneController.php',
    ];

    public function test_migrated_controllers_no_longer_reference_legacy_project_adapter(): void
    {
        foreach (self::MIGRATED_FILES as $relativePath) {
            $source = file_get_contents(base_path($relativePath));

            $this->assertIsString($source, "Unable to read {$relativePath}");
            $this->assertStringNotContainsString(
                'LegacyProjectAdapter',
                $source,
                "{$relativePath} should no longer reference Src\\CoreProject\\Models\\LegacyProjectAdapter."
            );
            $this->assertStringContainsString(
                'use App\\Models\\Project;',
                $source,
                "{$relativePath} should import App\\Models\\Project directly."
            );
        }
    }

    public function test_legacy_project_adapter_class_itself_is_untouched(): void
    {
        $source = file_get_contents(base_path('src/CoreProject/Models/LegacyProjectAdapter.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('class LegacyProjectAdapter extends BaseProject', $source);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Architecture/LegacyProjectAdapterRemovalTest.php`
Expected: FAIL — `test_migrated_controllers_no_longer_reference_legacy_project_adapter` fails because all 6 files still contain `LegacyProjectAdapter`.

- [ ] **Step 3: Swap the import in each of the 6 files**

Every one of the 6 files has the exact same single-line import to change. In each file, replace:

```php
use Src\CoreProject\Models\LegacyProjectAdapter as Project;
```

with:

```php
use App\Models\Project;
```

Apply this to all 6 files listed in the Files section above. No other line in any of these 6 files needs to change — `LegacyProjectAdapter` is an empty subclass of `App\Models\Project`, so every method call, property access, and relation the rest of each file makes against `Project` behaves identically either way.

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Architecture/LegacyProjectAdapterRemovalTest.php`
Expected: PASS (2/2)

- [ ] **Step 5: Run the one pre-existing test file that covers a migrated controller**

Run: `php artisan test tests/Unit/Controllers/Api/ProjectManagerControllerTest.php`
Expected: PASS — unchanged, confirming the import swap is behavior-preserving for `ProjectManagerController`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/ProjectBulkController.php app/Http/Controllers/Web/DocumentManagementController.php app/Http/Controllers/Api/ProjectTemplateController.php app/Http/Controllers/Api/ProjectAnalyticsController.php app/Http/Controllers/Api/ProjectManagerController.php app/Http/Controllers/Api/ProjectMilestoneController.php tests/Feature/Architecture/LegacyProjectAdapterRemovalTest.php
git commit -m "refactor(projects): consolidate 6 controllers off LegacyProjectAdapter onto App\Models\Project"
```

---

### Task 2: Discovery inventory — reference-dump script + written document

**Files:**
- Create: `scripts/architecture/dump-project-model-references.sh`
- Create: `docs/architecture/project-model-reference-inventory.md`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `docs/architecture/project-model-reference-inventory.md`, which Task 3's allowlist test cites as its source of truth (informally — Task 3's allowlist is a literal array, not a generated one, but must match this document's file list exactly).

- [ ] **Step 1: Write the reference-dump script**

Create `scripts/architecture/dump-project-model-references.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p storage/app/architecture

{
    echo "# Project-related class references (generated $(date -u +%Y-%m-%dT%H:%M:%SZ))"
    echo ""
    echo "## App\\Models\\Project"
    grep -rl 'App\\Models\\Project\b' app/ src/ --include="*.php" | sort
    echo ""
    echo "## App\\Models\\ZenaProject"
    grep -rl '\bZenaProject\b' app/ src/ database/ tests/ --include="*.php" | sort
    echo ""
    echo "## Src\\CoreProject\\Models\\Project"
    grep -rl 'Src\\CoreProject\\Models\\Project\b' app/ src/ --include="*.php" | sort
    echo ""
    echo "## Src\\CoreProject\\Models\\LegacyProjectAdapter"
    grep -rl 'LegacyProjectAdapter' app/ src/ --include="*.php" | sort
} > storage/app/architecture/project-model-references.md

echo "Project model reference dump written to storage/app/architecture/project-model-references.md"
```

- [ ] **Step 2: Run the script and verify it produces output**

Run: `chmod +x scripts/architecture/dump-project-model-references.sh && ./scripts/architecture/dump-project-model-references.sh`
Expected: `Project model reference dump written to storage/app/architecture/project-model-references.md`, and that file contains 4 headed sections with file-path lists under each.

- [ ] **Step 3: Write the inventory document**

Create `docs/architecture/project-model-reference-inventory.md`. This is the discovery deliverable required by the project-model-consolidation design spec (`docs/superpowers/specs/2026-07-12-project-model-consolidation-design.md`, §3.2) — it records the full current reference list for each of the 4 classes, plus the reachability facts already established this session for the subset of files actually traced, and marks everything else explicitly unresolved for a future slice:

```markdown
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
```

- [ ] **Step 4: Commit**

```bash
git add scripts/architecture/dump-project-model-references.sh docs/architecture/project-model-reference-inventory.md
git commit -m "docs(architecture): add Project model reference-dump script and discovery inventory"
```

---

### Task 3: Forward-guard architecture test

**Files:**
- Test: `tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php` (new)

**Interfaces:**
- Consumes: the file list documented in Task 2's inventory (informally — this task's allowlist array must match the "Traced, confirmed LIVE" + "Traced, confirmed DEAD" + "Not yet traced" file lists from Task 2 combined, since together they are the complete current 54-file set).
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Forward-guard for docs/architecture/module-ownership-ssot.md's compatibility
 * policy: Src\CoreProject\Models\Project is accepted, frozen debt behind the
 * live /api/v1/* compatibility runtime — this test does not shrink that debt,
 * it only fails loudly the moment a NEW file (outside this fixed allowlist)
 * starts importing it, so a future app-owned module doesn't silently grow the
 * legacy surface this codebase is trying to converge away from.
 *
 * The allowlist below is the exact 2026-07-12 file list from
 * docs/architecture/project-model-reference-inventory.md. When a future
 * consolidation slice removes a reference, remove it from this list too —
 * do not add to this list without updating that inventory document first.
 */
class ProjectModelReferenceAllowlistTest extends TestCase
{
    private const ALLOWED_FILES = [
        'app/Console/Commands/CleanMockData.php',
        'app/Http/Controllers/Api/AnalyticsController.php',
        'app/Http/Controllers/Api/ExportController.php',
        'app/Http/Controllers/TaskController.php',
        'app/Http/Controllers/Web/DocumentController.php',
        'app/Http/Controllers/Web/ProjectController.php',
        'app/Http/Controllers/Web/TaskController.php',
        'app/Http/Controllers/WorkTemplateController.php',
        'app/Http/Middleware/ProjectAccessMiddleware.php',
        'app/Http/Middleware/ProjectOwnershipMiddleware.php',
        'app/Http/Requests/UpdateProjectRequest.php',
        'app/Models/InteractionLog.php',
        'app/Models/NotificationRule.php',
        'app/Models/UserRoleProject.php',
        'app/Services/BaselineService.php',
        'app/Services/CompensationService.php',
        'app/Services/ConditionalTagService.php',
        'app/Services/InteractionLogService.php',
        'app/Services/TemplateService.php',
        'src/ChangeRequest/Listeners/ChangeRequestEventListener.php',
        'src/ChangeRequest/Models/ChangeRequest.php',
        'src/Compensation/Models/Contract.php',
        'src/Compensation/Services/CompensationService.php',
        'src/CoreProject/Controllers/ProjectController.php',
        'src/CoreProject/Controllers/TaskController.php',
        'src/CoreProject/Controllers/WorkTemplateController.php',
        'src/CoreProject/Events/ProjectCreated.php',
        'src/CoreProject/Jobs/RecalculateProjectRollupJob.php',
        'src/CoreProject/Listeners/ConditionalTagListener.php',
        'src/CoreProject/Listeners/NotificationListener.php',
        'src/CoreProject/Listeners/ProgressCalculationListener.php',
        'src/CoreProject/Listeners/ProjectCalculationListener.php',
        'src/CoreProject/Listeners/ProjectProgressListener.php',
        'src/CoreProject/Middleware/ProjectAccessMiddleware.php',
        'src/CoreProject/Middleware/ProjectOwnershipMiddleware.php',
        'src/CoreProject/Middleware/ProjectStatusMiddleware.php',
        'src/CoreProject/Requests/StoreProjectRequest.php',
        'src/CoreProject/Requests/UpdateProjectRequest.php',
        'src/CoreProject/Services/BaselineService.php',
        'src/CoreProject/Services/ComponentService.php',
        'src/CoreProject/Services/ConditionalTagService.php',
        'src/CoreProject/Services/ProjectService.php',
        'src/CoreProject/Services/TaskService.php',
        'src/CoreProject/Services/WorkTemplateApplicationService.php',
        'src/DocumentManagement/Models/Document.php',
        'src/InteractionLogs/Models/InteractionLog.php',
        'src/InteractionLogs/Services/InteractionLogService.php',
        'src/Notification/Models/NotificationRule.php',
        'src/RBAC/Models/UserRoleProject.php',
        'src/WorkTemplate/Events/TaskConditionalToggled.php',
        'src/WorkTemplate/Events/TemplateApplied.php',
        'src/WorkTemplate/Models/ProjectTask.php',
        'src/WorkTemplate/Requests/ApplyTemplateRequest.php',
        'src/WorkTemplate/Services/TemplateService.php',
    ];

    public function test_no_file_outside_the_allowlist_imports_src_coreproject_models_project(): void
    {
        $finder = (new Finder())
            ->files()
            ->name('*.php')
            ->in([base_path('app'), base_path('src')]);

        $allowedRealPaths = array_map(
            static fn (string $relative): string => base_path($relative),
            self::ALLOWED_FILES
        );

        $unexpected = [];

        foreach ($finder as $file) {
            $realPath = $file->getRealPath();

            if ($realPath === false) {
                continue;
            }

            if (in_array($realPath, $allowedRealPaths, true)) {
                continue;
            }

            $contents = file_get_contents($realPath);

            if ($contents === false) {
                continue;
            }

            if (str_contains($contents, 'Src\\CoreProject\\Models\\Project') || preg_match('/\buse\s+Src\\\\CoreProject\\\\Models\\\\Project\s*;/', $contents)) {
                $unexpected[] = str_replace(base_path() . '/', '', $realPath);
            }
        }

        $this->assertSame(
            [],
            $unexpected,
            "New file(s) import Src\\CoreProject\\Models\\Project outside the allowlist: " . implode(', ', $unexpected)
            . ". If this is intentional, add the file to docs/architecture/project-model-reference-inventory.md first, then to this test's ALLOWED_FILES."
        );
    }

    public function test_allowlist_files_that_still_exist_still_reference_the_class(): void
    {
        foreach (self::ALLOWED_FILES as $relativePath) {
            $fullPath = base_path($relativePath);

            if (!file_exists($fullPath)) {
                continue;
            }

            $contents = file_get_contents($fullPath);
            $this->assertIsString($contents);
            $this->assertStringContainsString(
                'CoreProject\\Models\\Project',
                $contents,
                "{$relativePath} is in the allowlist but no longer references Src\\CoreProject\\Models\\Project — remove it from the allowlist and the inventory doc."
            );
        }
    }
}
```

- [ ] **Step 2: Run the test to verify the first assertion passes and the second is meaningful**

Run: `php artisan test tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php`
Expected: PASS (2/2) — the allowlist exactly matches the current codebase state built in Task 2, so nothing outside it should be found, and nothing inside it should have drifted away from referencing the class.

- [ ] **Step 3: Prove the guard actually works (temporary, reverted)**

Create a scratch file to confirm the test fails when it should:

```bash
mkdir -p app/Services/_scratch_test
cat > app/Services/_scratch_test/ScratchProjectConsumer.php <<'EOF'
<?php declare(strict_types=1);

namespace App\Services;

use Src\CoreProject\Models\Project;

class ScratchProjectConsumer
{
    public function find(string $id): ?Project
    {
        return Project::find($id);
    }
}
EOF
```

Run: `php artisan test tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php`
Expected: FAIL — `test_no_file_outside_the_allowlist_imports_src_coreproject_models_project` fails, naming `app/Services/_scratch_test/ScratchProjectConsumer.php`.

Then remove the scratch file and directory:

```bash
rm -rf app/Services/_scratch_test
```

Run: `php artisan test tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php`
Expected: PASS (2/2) again, confirming the scratch file is fully gone and the guard test is back to green against real code only.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Architecture/ProjectModelReferenceAllowlistTest.php
git commit -m "test(architecture): add forward-guard against new Src\CoreProject\Models\Project imports"
```

---

### Task 4: Full verification — the completion gates from the design spec

**Files:** None (verification only).

- [ ] **Step 1: `php artisan route:list` spot-check**

Run: `php artisan route:list | grep -E "projects|api/v1/projects"`
Expected: both an `api/zena/projects*` family (canonical) and an `api/v1/projects*` family (compatibility) are present — confirms Task 1's import swaps didn't touch routing (they shouldn't have; this is the cheap confirmation that they didn't).

- [ ] **Step 2: `composer ssot:lint`**

Run: `composer ssot:lint`
Expected: exits 0 — the existing combined route-dump/orphan-route/domain-ownership-doc CI gate stays green.

- [ ] **Step 3: The 2 existing module-ownership tests**

Run: `php artisan test tests/Feature/Architecture/ModuleOwnershipSourceInvariantTest.php tests/Feature/Architecture/ModuleOwnershipRouteInvariantTest.php`
Expected: PASS — these are the tests that would fail first if the `/api/v1/*` compatibility surface had been disturbed. This plan doesn't touch it, but this is the direct proof.

- [ ] **Step 4: Full test suite**

Run: `php artisan test`
Expected: all tests pass, including the 4 new tests added across Tasks 1 and 3 (2 + 2).

- [ ] **Step 5: Deptrac**

Run: `vendor/bin/deptrac analyse --no-cache`
Expected: 0 violations, 0 errors — Task 1's import swaps move 6 files from one `App\Models\*`-adjacent class to another; Tasks 2-3 add no new production code (docs + tests only), so no new layer-boundary edges are introduced.

- [ ] **Step 6: If any step fails, fix and re-run**

Do not consider this plan complete until all 6 steps are clean.

---

## Post-plan notes for the controller (not a task — read before dispatching)

- Task 1 and Tasks 2-3 are independent of each other and could be dispatched in parallel by task number, but per this project's established subagent-driven-development convention (never dispatch multiple implementation subagents in parallel — risk of conflicting file edits), dispatch them sequentially in the order written.
- Task 3's scratch-file step (Step 3) is unusual — it's the only step in this plan that deliberately creates and deletes a throwaway file to prove a negative-path test works, matching the "prove the guard actually works" principle. Make sure the implementer actually removes `app/Services/_scratch_test/` before committing — a stray scratch directory left in the tree would itself trip Task 4's Deptrac/test-suite gates.
- If a future reviewer asks "why doesn't this plan just migrate all 54 files while we're at it" — the answer is in the design spec's revision note: the original draft of this exact plan tried that, and the reachability trace justifying it was wrong. This plan is deliberately narrow.
