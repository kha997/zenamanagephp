# Dead Milestone/ProjectTemplate API Removal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remove `Api\ProjectMilestoneController`, `Api\ProjectTemplateController`, their dead routes, and the no-op-forever duplicate `project_templates` migration — all confirmed to have zero real consumers and pre-existing structural bugs unrelated to permissions. Leave the `ProjectMilestone`/`ProjectTemplate` models and every other place that reads them untouched.

**Architecture:** Pure deletion. No new code. Two controller files removed, two route blocks removed from `routes/api.php`, one migration file removed. A new architecture guard test proves the routes are gone and proves the models + their real (kept) consumers still work.

**Tech Stack:** Laravel 12, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-22-dead-milestone-template-api-removal-design.md`

## Global Constraints

- Do NOT touch: `app/Models/ProjectMilestone.php`, `app/Models/ProjectTemplate.php`, `app/Models/TemplateTask.php`, `app/Http/Controllers/ProjectTemplateController.php` (the non-`Api` namespace one, kept for a separate future audit), `Src\WorkTemplate\Controllers\TemplateController`, `app/Http/Controllers/TemplateController.php`, `app/Http/Controllers/Api/App/TemplateController.php`, `database/migrations/2024_02_15_000001_create_project_templates_table.php`.
- Do NOT touch any code that reads `ProjectMilestone`/`ProjectTemplate` via Eloquent directly: `app/Models/Project.php` relation, `app/Http/Controllers/Api/ProjectAnalyticsController.php`, `app/Http/Controllers/Api/PmDashboardController.php`, `app/Events/ProjectMilestoneCompleted.php`, `app/Services/RealTimeNotificationService.php`.
- Verified zero-consumer facts to rely on (do not re-derive): no Blade/JS file references `projects/milestones` or `project-templates` routes; no test file references `ProjectMilestoneController`, `Api\ProjectTemplateController`, or any `milestones.*`/`project-templates.*` route name; `tests/Feature/SimpleProjectMilestoneTest.php` and `tests/Feature/VerySimpleProjectMilestoneTest.php` only exercise the `ProjectMilestone` model directly (no route/controller involvement) and must still pass unchanged after this plan.
- Exact route names to remove (confirmed via `php artisan route:list`): `milestones.index`, `milestones.store`, `milestones.show`, `milestones.update`, `milestones.destroy`, plus the unnamed `POST api/projects/milestones/reorder`, `POST api/projects/milestones/{milestone}/mark-completed`, `POST api/projects/milestones/{milestone}/mark-cancelled`; `project-templates.index`, `project-templates.store`, `project-templates.show`, `project-templates.update`, `project-templates.destroy`, plus the unnamed `GET api/project-templates/categories`, `POST api/project-templates/{template}/create-project`, `POST api/project-templates/{template}/duplicate`.
- Test invocation: `./vendor/bin/phpunit <path>` directly, never `php artisan test`. CI is the source of truth for PHPStan; if "Code Quality Analysis"/"Security Tests" fail on new findings (deleting files sometimes shifts baseline entry counts for adjacent findings), add surgical entries to `phpstan-baseline.neon` (single quotes inside single-quoted neon strings must be doubled `''`) — or, more likely here, REMOVE now-stale baseline entries that referenced the deleted files (a baseline entry pointing at a deleted file's path is harmless but should be cleaned up if CI's baseline-drift check flags it).

---

### Task 1: Remove the two dead controllers and their routes

**Files:**
- Delete: `app/Http/Controllers/Api/ProjectMilestoneController.php`
- Delete: `app/Http/Controllers/Api/ProjectTemplateController.php`
- Modify: `routes/api.php` (remove two route blocks)
- Test: `tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php` (create)

**Interfaces:**
- Produces: nothing — this is pure removal. Task 2 (migration removal) is independent and can be verified separately.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\ProjectMilestone;
use App\Models\ProjectTemplate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 2026-07-22 audit: Api\ProjectMilestoneController và Api\ProjectTemplateController
 * bị gỡ vì cả hai đều chưa từng hoạt động đúng (route milestone thiếu {project};
 * ProjectTemplateController viết cho schema project_templates chưa từng tồn tại
 * thật trên DB — xem docs/superpowers/specs/2026-07-22-dead-milestone-template-api-removal-design.md)
 * và zero consumer thật (không blade/JS/test nào gọi). Model + mọi nơi đọc model
 * qua Eloquent trực tiếp vẫn giữ nguyên — guard này xác nhận cả hai vế.
 */
class DeadMilestoneTemplateApiRemovedTest extends TestCase
{
    public function test_dead_milestone_api_routes_are_removed(): void
    {
        foreach (['milestones.index', 'milestones.store', 'milestones.show', 'milestones.update', 'milestones.destroy'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route {$routeName} phải bị gỡ (milestone write-API chết).");
        }

        $unnamedUris = [
            'api/projects/milestones/reorder',
            'api/projects/milestones/{milestone}/mark-completed',
            'api/projects/milestones/{milestone}/mark-cancelled',
        ];
        foreach (Route::getRoutes() as $route) {
            $this->assertNotContains($route->uri(), $unnamedUris, "URI {$route->uri()} phải bị gỡ (milestone write-API chết).");
        }
    }

    public function test_dead_project_template_api_routes_are_removed(): void
    {
        foreach (['project-templates.index', 'project-templates.store', 'project-templates.show', 'project-templates.update', 'project-templates.destroy'] as $routeName) {
            $this->assertFalse(Route::has($routeName), "Route {$routeName} phải bị gỡ (Api\\ProjectTemplateController chết).");
        }

        $unnamedUris = [
            'api/project-templates/categories',
            'api/project-templates/{template}/create-project',
            'api/project-templates/{template}/duplicate',
        ];
        foreach (Route::getRoutes() as $route) {
            $this->assertNotContains($route->uri(), $unnamedUris, "URI {$route->uri()} phải bị gỡ (Api\\ProjectTemplateController chết).");
        }
    }

    public function test_kept_models_still_instantiate_and_query(): void
    {
        // Xoá controller không được đụng tới model — cả hai vẫn query bình thường.
        $this->assertIsIterable(ProjectMilestone::query()->limit(1)->get());
        $this->assertIsIterable(ProjectTemplate::query()->limit(1)->get());
    }

    public function test_kept_non_api_project_template_controller_route_untouched(): void
    {
        // App\Http\Controllers\ProjectTemplateController (khác namespace, giữ lại
        // để audit riêng) mount ở /api/templates — path khác hẳn /api/project-templates
        // vừa gỡ, phải không bị ảnh hưởng.
        $this->assertTrue(Route::has('templates.show'), 'Route templates.show (Src\\WorkTemplate legacy, giữ nguyên) không được bị gỡ nhầm.');
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php`
Expected: FAIL on `test_dead_milestone_api_routes_are_removed` and `test_dead_project_template_api_routes_are_removed` (routes still exist). The other two tests pass already (nothing to remove yet).

- [ ] **Step 3: Delete the two controller files**

```bash
git rm app/Http/Controllers/Api/ProjectMilestoneController.php
git rm app/Http/Controllers/Api/ProjectTemplateController.php
```

- [ ] **Step 4: Remove the route blocks from routes/api.php**

In `routes/api.php`, inside the `Route::prefix('projects')->group(function () { ... })` block, remove exactly this sub-block (currently sits right after the `dropdown` route):

```php
            // Project Milestones Routes
            Route::apiResource('milestones', \App\Http\Controllers\Api\ProjectMilestoneController::class);
            Route::prefix('milestones')->group(function () {
                Route::post('{milestone}/mark-completed', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'markCompleted']);
                Route::post('{milestone}/mark-cancelled', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'markCancelled']);
                Route::post('reorder', [\App\Http\Controllers\Api\ProjectMilestoneController::class, 'reorder']);
            });
```

so the `projects` group's closing goes directly from the `dropdown` route to the group's closing `});`.

Immediately after that same group's closing `});`, remove this block (the "Project Templates Routes" section):

```php
        // Project Templates Routes
        Route::apiResource('project-templates', \App\Http\Controllers\Api\ProjectTemplateController::class);
        Route::prefix('project-templates')->group(function () {
            Route::post('{template}/create-project', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'createProject']);
            Route::post('{template}/duplicate', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'duplicate']);
            Route::get('categories', [\App\Http\Controllers\Api\ProjectTemplateController::class, 'categories']);
        });
```

Double-check with `grep -n "ProjectMilestoneController\|Api\\\\ProjectTemplateController" routes/api.php` — it must return nothing after this edit (the two `use`-style fully-qualified class references only appeared in these two blocks).

- [ ] **Step 5: Run the guard test, then the full existing milestone/template-related test files**

Run: `./vendor/bin/phpunit tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php`
Expected: ALL 4 PASS.

Run: `./vendor/bin/phpunit tests/Feature/SimpleProjectMilestoneTest.php tests/Feature/VerySimpleProjectMilestoneTest.php tests/Feature/Api/PmDashboardApiTest.php`
Expected: ALL PASS unchanged (these only touch the model / a different controller, never the ones deleted).

- [ ] **Step 6: Commit**

```bash
git add routes/api.php tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php
git commit -m "chore(api): remove dead ProjectMilestoneController and ProjectTemplateController

Both had zero real consumers (no Blade/JS caller, no test) and pre-existing
structural bugs independent of the project.write permission issue that
surfaced them: the milestone routes never included {project} in the URL
while every controller method required it, and Api\ProjectTemplateController
was written against a project_templates schema (tenant_id/template_data/
milestones/is_public) that was never actually created on any real database
(see the 2025_09_16 migration removed in the next task). Models and every
place that reads them via Eloquent directly are untouched."
```

---

### Task 2: Remove the no-op duplicate project_templates migration

**Files:**
- Delete: `database/migrations/2025_09_16_081512_create_project_templates_table.php`
- Test: `tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php` (extend)

**Interfaces:**
- Consumes: none from Task 1.
- Produces: nothing downstream.

- [ ] **Step 1: Confirm the migration is genuinely a permanent no-op before deleting**

Run: `grep -n "Schema::hasTable\|Schema::create" database/migrations/2025_09_16_081512_create_project_templates_table.php`
Expected output includes `if (!Schema::hasTable('project_templates')) {` wrapping the `Schema::create('project_templates', ...)` call. Since `database/migrations/2024_02_15_000001_create_project_templates_table.php` (kept, earlier timestamp) always creates the table first in any environment, this guard means the 2025 migration's `up()` has never executed its `Schema::create` body. This is the confirmation step — if the grep does NOT show the guard (i.e., the file changed since this plan was written), STOP and report back instead of deleting.

- [ ] **Step 2: Write the failing test**

Add to `tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php` (add `use Illuminate\Support\Facades\File;` to the imports):

```php
    public function test_duplicate_noop_project_templates_migration_is_removed(): void
    {
        $this->assertFalse(
            File::exists(database_path('migrations/2025_09_16_081512_create_project_templates_table.php')),
            'Migration 2025_09_16_081512 phải bị gỡ (guard if (!Schema::hasTable) khiến up() không bao giờ thực thi — no-op vĩnh viễn từ khi tạo, bảng project_templates thật đã được migration 2024_02_15 tạo trước).'
        );
        $this->assertTrue(
            File::exists(database_path('migrations/2024_02_15_000001_create_project_templates_table.php')),
            'Migration 2024_02_15 là schema thật đang dùng — không được xoá.'
        );
    }
```

- [ ] **Step 3: Run the test to verify it fails**

Run: `./vendor/bin/phpunit --filter test_duplicate_noop_project_templates_migration_is_removed tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php`
Expected: FAIL — the 2025 migration file still exists.

- [ ] **Step 4: Delete the migration file**

```bash
git rm database/migrations/2025_09_16_081512_create_project_templates_table.php
```

- [ ] **Step 5: Run the test to verify it passes, then the full guard test file, then a full migration sanity check**

Run: `./vendor/bin/phpunit tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php`
Expected: ALL 5 PASS.

Run: `php artisan migrate:status --env=testing 2>&1 | grep -i "2025_09_16_081512"` — if this repo's testing DB has ever recorded that migration as run, it will still show in the migrations table (deleting the file doesn't retroactively un-record it; that's expected and harmless since its `up()` never did anything). This step is informational only, not a pass/fail gate — do not attempt to clean the migrations table.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2025_09_16_081512_create_project_templates_table.php tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php
git commit -m "chore(db): remove permanently no-op duplicate project_templates migration

Guarded by if (!Schema::hasTable('project_templates')), this migration's
up() has never executed its Schema::create body in any environment — the
2024_02_15 migration (kept) always creates the table first. Removing a
migration whose forward effect was always nothing."
```

---

## Final verification (after both tasks)

- [ ] `./vendor/bin/phpunit tests/Feature/Architecture/DeadMilestoneTemplateApiRemovedTest.php tests/Feature/SimpleProjectMilestoneTest.php tests/Feature/VerySimpleProjectMilestoneTest.php tests/Feature/Api/PmDashboardApiTest.php` — ALL PASS.
- [ ] `grep -rn "ProjectMilestoneController\|Api\\\\ProjectTemplateController" app/ routes/ tests/` returns nothing (confirms no dangling reference anywhere, including tests).
- [ ] CI green. Watch for PHPStan baseline drift from the two deleted controller files — if `phpstan-baseline.neon` has stale entries with `path: app/Http/Controllers/Api/ProjectMilestoneController.php` or `path: app/Http/Controllers/Api/ProjectTemplateController.php`, remove those entries (a baseline entry pointing at a deleted file doesn't error PHPStan itself, but CI's baseline-drift/unmatched-ignore check may flag it — take the exact guidance from the CI failure, not a guess).

## Out of Scope

- `App\Http\Controllers\ProjectTemplateController` (non-`Api` namespace): route shadowed by `Src\WorkTemplate\Controllers\TemplateController` (registered earlier in `routes/api.php`, same `api/templates/{id}` pattern wins first-match), and missing `use Illuminate\Http\Request;` breaks `store`/`update`/`apply`. Separate audit.
- `Src\WorkTemplate\Controllers\TemplateController`: not yet verified whether it's itself live or superseded by WorkTemplate v2 (`App\Http\Controllers\Api\WorkTemplateController`, confirmed live via PR#184/#210/#211). Separate audit.
- `App\Http\Controllers\TemplateController`, `App\Http\Controllers\Api\App\TemplateController`: discovered via filename search, mount points not yet verified. Separate audit.
- Migration `2024_02_15_000001_create_project_templates_table.php`: live schema, not touched.
