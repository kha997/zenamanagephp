# Work Template — Apply UI (chọn & áp dụng) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cho phép Project Manager chọn một `WorkTemplate` (v2) đã publish và áp dụng vào một dự án có sẵn, tự sinh Task/Phase/Checklist thay vì tạo tay, qua giao diện web (Blade + Alpine.js).

**Architecture:** Web controller mỏng (`App\Http\Controllers\Web\WorkTemplateApplyController`) delegate trực tiếp sang method có sẵn `App\Http\Controllers\Api\WorkTemplateController::applyToProject()` (đã có dry-run, duplicate detection, tạo `WorkInstance`+`Task` thật, 16/16 test xanh) — không viết lại logic nghiệp vụ. UI là 1 Blade partial dùng Alpine.js gọi `fetch()` tới route Web (session-auth, không qua Sanctum), theo đúng pattern đã có ở `invitations/create.blade.php`.

**Tech Stack:** Laravel 12, Blade, Alpine.js (đã có sẵn trong app, không cần cài thêm), PHPUnit (`RefreshDatabase`).

## Global Constraints

- Route Web dùng `middleware('rbac:template.view')` / `middleware('rbac:template.apply')` — **không** dùng `$this->authorize()` (chưa có `WorkTemplatePolicy` cho `App\Models\WorkTemplate`, ra khỏi phạm vi slice này).
- Route mới đặt bên trong group `Route::prefix('app')->name('app.')->middleware(['auth', 'tenant.isolation'])` đã có sẵn trong `routes/web.php` (dòng ~357) — group này **đã áp `tenant.isolation`**, không cần khai báo lại per-route.
- Danh sách template lọc theo tồn tại `WorkTemplateVersion.published_at != null` qua relation `publishedVersions()` có sẵn, **không** lọc theo cột `status` của `WorkTemplate`.
- Bản mới nhất phải resolve bằng PHP (`sortByDesc('published_at')->first()` sau eager-load) — **không** dùng `limit(1)` trong eager-load closure (`publishedVersions()` order theo `created_at`, không phải `published_at`).
- Gán quyền `template.view`+`template.apply` cho role Project Manager phải nằm trong `ZenaPermissionsSeeder::run()` (sau vòng lặp tạo permission), **không** trong `PermissionSeeder.php` — `DatabaseSeeder` chạy `PermissionSeeder` (thứ tự #29) trước `ZenaPermissionsSeeder` (#30); sửa sai chỗ sẽ silently no-op trên DB seed-từ-đầu.
- Sau khi áp dụng thật, card "Công việc" trên `projects/show.blade.php` phải hiện task mới — bắt buộc đổi biến nguồn dữ liệu từ `$project->tasks` (model legacy `Src\CoreProject\Models\Task`) sang `$sectionTasks` (biến có sẵn trong `ProjectController::show()`, dùng đúng `App\Models\Task` canonical).
- Không tách `buildTemplateApplicationPlan` ra service riêng trong slice này (xem spec — "Cleaner later", ngoài phạm vi).
- Không đụng vào `Src\CoreProject\Models\WorkTemplate` (model legacy trỏ cùng bảng `work_templates`) — nợ kỹ thuật riêng, không xử lý ở đây.

---

## Task 1: Cấp quyền `template.view`/`template.apply` cho Project Manager (đúng seeder, đúng thứ tự)

**Files:**
- Modify: `database/seeders/ZenaPermissionsSeeder.php`
- Test: `tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php` (mới)

**Interfaces:**
- Consumes: `App\Models\Role`, `App\Models\Permission` (đã tồn tại, không đổi gì).
- Produces: sau khi seed, role "Project Manager" có 2 permission `template.view` và `template.apply` (cột `name` = code, dùng bởi `User::hasPermission()`).

- [ ] **Step 1: Đọc `database/seeders/ZenaPermissionsSeeder.php` để xác định vị trí chèn code**

Chạy: `grep -n "public function run" database/seeders/ZenaPermissionsSeeder.php`
Xác nhận vòng lặp `foreach (self::CANONICAL_PERMISSIONS as ...) { ... Permission::updateOrCreate(...); }` là bước cuối cùng thao tác permission trong `run()`. Sẽ chèn code gán quyền NGAY SAU vòng lặp này, vẫn trong cùng method `run()`.

- [ ] **Step 2: Viết test trước (RED) — mô phỏng đúng thứ tự seeder thật**

Tạo file `tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagerTemplatePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_gets_template_view_and_apply_in_correct_seed_order(): void
    {
        // Chạy đúng thứ tự thật của DatabaseSeeder: RoleSeeder -> PermissionSeeder -> ZenaPermissionsSeeder.
        // Đây là test bắt buộc phải seed-từ-đầu (không dựa vào DB có sẵn) để bắt đúng
        // hazard thứ tự: template.view/template.apply chỉ được ZenaPermissionsSeeder tạo,
        // seeder này chạy SAU PermissionSeeder.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);

        $pm = Role::where('name', 'Project Manager')->firstOrFail();

        $this->assertTrue(
            $pm->permissions()->where('name', 'template.view')->exists(),
            'Project Manager phải có permission template.view (cột name, dùng bởi User::hasPermission())'
        );
        $this->assertTrue(
            $pm->permissions()->where('name', 'template.apply')->exists(),
            'Project Manager phải có permission template.apply'
        );
        $this->assertFalse(
            $pm->permissions()->where('name', 'template.edit_draft')->exists(),
            'Project Manager KHÔNG được cấp template.edit_draft (chỉ System Admin soạn thảo template)'
        );
    }
}
```

- [ ] **Step 3: Chạy test, xác nhận FAIL**

Run: `./vendor/bin/phpunit tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php`
Expected: FAIL — `template.view`/`template.apply` chưa được gán cho Project Manager (assertTrue thất bại).

- [ ] **Step 4: Thêm bước gán quyền vào `ZenaPermissionsSeeder::run()`**

Mở `database/seeders/ZenaPermissionsSeeder.php`. Ngay sau đoạn:

```php
        foreach (self::CANONICAL_PERMISSIONS as $permissionDefinition) {
            $permissionKey = $permissionDefinition['code'];

            $attributes = [
                'module' => $permissionDefinition['module'],
                'action' => $permissionDefinition['action'],
                'description' => $permissionDefinition['description'],
            ];

            if ($hasCodeColumn) {
                $attributes['code'] = $permissionKey;
            }

            if ($hasNameColumn) {
                $attributes['name'] = $permissionKey;
            }

            Permission::updateOrCreate([$lookupColumn => $permissionKey], $attributes);
        }
```

thêm ngay trước dấu `}` đóng method `run()`:

```php

        $this->grantTemplateApplyToProjectManager();
    }

    /**
     * PM cần chọn & áp dụng WorkTemplate cho dự án — không cần quyền soạn thảo
     * (template.edit_draft/publish/delete vẫn admin-only qua ZenaAdminRolePermissionSeeder).
     */
    private function grantTemplateApplyToProjectManager(): void
    {
        $pmRole = Role::where('name', 'Project Manager')->first();

        if (!$pmRole) {
            return;
        }

        $permissionIds = Permission::whereIn('code', ['template.view', 'template.apply'])
            ->pluck('id')
            ->all();

        if (empty($permissionIds)) {
            return;
        }

        $pmRole->permissions()->syncWithoutDetaching($permissionIds);
    }
```

Thêm `use App\Models\Role;` vào đầu file nếu chưa có (kiểm tra bằng `grep -n "^use App\\\\Models\\\\Role;" database/seeders/ZenaPermissionsSeeder.php` — file này đã `use App\Models\Permission;` và `use App\Models\Role;` sẵn vì `ZenaAdminRolePermissionSeeder` cùng thư mục dùng cách tương tự; xác nhận lại bằng grep trước khi thêm để tránh import trùng).

- [ ] **Step 5: Chạy lại test, xác nhận PASS**

Run: `./vendor/bin/phpunit tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php`
Expected: PASS (3 assertions, cả 2 permission được gán, `template.edit_draft` không bị gán nhầm).

- [ ] **Step 6: Chạy lại toàn bộ test seeder liên quan để không phá vỡ gì (RoleSeeder/PermissionSeeder/ZenaPermissionsSeeder có thể có test khác)**

Run: `./vendor/bin/phpunit --filter=Seeder`
Expected: tất cả PASS (không có test seeder nào khác bị ảnh hưởng — thay đổi chỉ THÊM một bước gán quyền `syncWithoutDetaching`, không xoá/sửa gì đã có).

- [ ] **Step 7: Commit**

```bash
git add database/seeders/ZenaPermissionsSeeder.php tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php
git commit -m "feat(rbac): grant template.view/apply to Project Manager in correct seed order"
```

---

## Task 2: Web controller + routes cho danh sách/preview/áp dụng template

**Files:**
- Create: `app/Http/Controllers/Web/WorkTemplateApplyController.php`
- Modify: `routes/web.php` (thêm 3 route trong group `app.` đã có, gần dòng `Route::get('/projects/{project}', ...)->name('projects.show');`)
- Test: `tests/Feature/Web/ProjectWorkTemplateApplyTest.php` (mới)

**Interfaces:**
- Consumes: `App\Http\Controllers\Api\WorkTemplateController::applyToProject(Request $request, string $projectId): JsonResponse` (method public có sẵn, không đổi chữ ký); `App\Models\WorkTemplate::publishedVersions(): HasMany` (có sẵn).
- Produces:
  - `GET /app/projects/{project}/work-templates` (tên route `app.projects.work-templates.index`) → JSON `{success: true, data: [{id, name, code, latest_published_version_id}, ...]}`.
  - `POST /app/projects/{project}/work-templates/preview` (tên route `app.projects.work-templates.preview`) → JSON y hệt response của `applyToProject(dry_run=true)`.
  - `POST /app/projects/{project}/work-templates/apply` (tên route `app.projects.work-templates.apply`) → JSON y hệt response của `applyToProject(dry_run=false)`.

- [ ] **Step 1: Viết test trước (RED) — cả 3 endpoint**

Tạo file `tests/Feature/Web/ProjectWorkTemplateApplyTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateChecklistItem;
use App\Models\WorkTemplatePhase;
use App\Models\WorkTemplateRequiredDocument;
use App\Models\WorkTemplateTask;
use App\Models\WorkTemplateTaskAssignment;
use App\Models\WorkTemplateTrigger;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class ProjectWorkTemplateApplyTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkTemplateV2Routes();
    }

    private function makeProjectManager(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $role = Role::factory()->create(['name' => 'Test PM Role ' . uniqid()]);
        $permissionModels = \App\Models\Permission::whereIn('code', $permissions)->get();
        $role->permissions()->sync($permissionModels->pluck('id'));

        \App\Models\UserRole::query()->create([
            'user_id' => (string) $user->id,
            'role_id' => (string) $role->id,
        ]);

        return $user;
    }

    private function publishedTemplate(Tenant $tenant, User $user, string $code): array
    {
        [$template, $version] = $this->seedV2Template($tenant, $user, $code);
        $version->update([
            'published_at' => now(),
            'is_immutable' => true,
            'published_by' => (string) $user->id,
        ]);

        return [$template, $version];
    }

    public function test_templates_list_only_returns_published_templates_for_current_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);

        [$published] = $this->publishedTemplate($tenant, $user, 'WT-PUB-1');

        // Template draft-only (chưa publish) — không được xuất hiện.
        WorkTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-DRAFT-ONLY',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        // Template đã publish nhưng thuộc tenant khác — không được xuất hiện.
        $otherUser = User::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        $this->publishedTemplate($otherTenant, $otherUser, 'WT-OTHER-TENANT');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson("/app/projects/{$project->id}/work-templates");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('WT-PUB-1', $data[0]['code']);
    }

    public function test_preview_returns_summary_without_writing_database(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);
        [$template] = $this->publishedTemplate($tenant, $user, 'WT-PREVIEW-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/preview",
            ['work_template_id' => (string) $template->id]
        );

        $response->assertOk()
            ->assertJsonPath('data.dry_run', true)
            ->assertJsonPath('data.duplicate', false)
            ->assertJsonPath('data.summary.phases', 1)
            ->assertJsonPath('data.summary.tasks', 1)
            ->assertJsonPath('data.summary.checklists', 1)
            ->assertJsonPath('data.summary.docs', 1);

        $this->assertSame(0, WorkInstance::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(0, Task::query()->where('project_id', (string) $project->id)->count());
    }

    public function test_apply_creates_real_task_and_second_apply_is_duplicate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, ['template.view', 'template.apply']);
        [$template] = $this->publishedTemplate($tenant, $user, 'WT-APPLY-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $firstApply = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        );

        $firstApply->assertStatus(201)
            ->assertJsonPath('data.duplicate', false);

        $this->assertSame(1, Task::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(1, WorkInstance::query()->where('project_id', (string) $project->id)->count());

        $secondApply = $this->actingAs($user)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        );

        $secondApply->assertOk()
            ->assertJsonPath('data.duplicate', true);

        // Không tạo trùng.
        $this->assertSame(1, Task::query()->where('project_id', (string) $project->id)->count());
        $this->assertSame(1, WorkInstance::query()->where('project_id', (string) $project->id)->count());
    }

    public function test_user_without_template_apply_permission_gets_403_on_preview_and_apply(): void
    {
        $tenant = Tenant::factory()->create();
        $viewOnlyUser = $this->makeProjectManager($tenant, ['template.view']);
        $adminUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        [$template] = $this->publishedTemplate($tenant, $adminUser, 'WT-NOPERM-1');

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $viewOnlyUser->id,
            'pm_id' => (string) $viewOnlyUser->id,
            'start_date' => now()->toDateString(),
        ]);

        $this->actingAs($viewOnlyUser)->postJson(
            "/app/projects/{$project->id}/work-templates/preview",
            ['work_template_id' => (string) $template->id]
        )->assertStatus(403);

        $this->actingAs($viewOnlyUser)->postJson(
            "/app/projects/{$project->id}/work-templates/apply",
            ['work_template_id' => (string) $template->id]
        )->assertStatus(403);
    }

    public function test_user_without_template_view_permission_gets_403_on_list(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeProjectManager($tenant, []);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
            'start_date' => now()->toDateString(),
        ]);

        $this->actingAs($user)->getJson("/app/projects/{$project->id}/work-templates")
            ->assertStatus(403);
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận FAIL (route chưa tồn tại)**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectWorkTemplateApplyTest.php`
Expected: FAIL — 404 (route không tồn tại) trên cả 5 test.

- [ ] **Step 3: Tạo `app/Http/Controllers/Web/WorkTemplateApplyController.php`**

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\WorkTemplateController as ApiWorkTemplateController;
use App\Http\Controllers\Controller;
use App\Models\WorkTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkTemplateApplyController extends Controller
{
    public function templates(Request $request, string $project): JsonResponse
    {
        $tenantId = (string) Auth::user()->tenant_id;

        $templates = WorkTemplate::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('publishedVersions')
            ->with('publishedVersions')
            ->orderBy('name')
            ->get()
            ->map(function (WorkTemplate $template): array {
                $latestPublished = $template->publishedVersions
                    ->sortByDesc('published_at')
                    ->first();

                return [
                    'id' => (string) $template->id,
                    'name' => $template->name,
                    'code' => $template->code,
                    'latest_published_version_id' => $latestPublished ? (string) $latestPublished->id : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    public function preview(Request $request, string $project): JsonResponse
    {
        $request->merge(['dry_run' => true]);

        return app(ApiWorkTemplateController::class)->applyToProject($request, $project);
    }

    public function apply(Request $request, string $project): JsonResponse
    {
        $request->merge(['dry_run' => false]);

        return app(ApiWorkTemplateController::class)->applyToProject($request, $project);
    }
}
```

- [ ] **Step 4: Thêm route vào `routes/web.php`**

Mở `routes/web.php`, tìm dòng (trong group `Route::prefix('app')->name('app.')->middleware(['auth', 'tenant.isolation'])->group(...)`):

```php
        Route::get('/projects/{project}', [App\Http\Controllers\Web\ProjectController::class, 'show'])->name('projects.show');
```

Thêm ngay sau dòng này:

```php
        Route::get('/projects/{project}/work-templates', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'templates'])->middleware('rbac:template.view')->name('projects.work-templates.index');
        Route::post('/projects/{project}/work-templates/preview', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'preview'])->middleware('rbac:template.apply')->name('projects.work-templates.preview');
        Route::post('/projects/{project}/work-templates/apply', [App\Http\Controllers\Web\WorkTemplateApplyController::class, 'apply'])->middleware('rbac:template.apply')->name('projects.work-templates.apply');
```

- [ ] **Step 5: Chạy lại test, xác nhận PASS**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectWorkTemplateApplyTest.php`
Expected: PASS (5 test).

Nếu gặp lỗi `RuntimeException: Tenant context missing` khi gọi `preview`/`apply`: xác nhận group route `app.` ở `routes/web.php` có `tenant.isolation` trong mảng middleware (đã có sẵn, không cần thêm) — nếu lỗi vẫn xảy ra, kiểm tra `TenantIsolationMiddleware` có đang set `request()->attributes` đúng thời điểm middleware chạy trước khi vào controller (không phải lỗi code mới, chỉ là xác nhận lại route nằm đúng trong group).

- [ ] **Step 6: Chạy PHPStan để bắt lỗi kiểu mới**

Run: `./vendor/bin/phpstan analyse app/Http/Controllers/Web/WorkTemplateApplyController.php --level=6`
Expected: không lỗi mới, hoặc nếu có (ví dụ về kiểu trả về của `$request->merge()`), thêm entry surgical vào `phpstan-baseline.neon` theo quy trình đã dùng cả phiên: bump `count:` cho entry đã có cùng message/path, hoặc thêm entry mới với path/message/count chính xác — **không regenerate cả file**, và nhân đôi mọi nháy đơn bên trong message string (`''` không phải `'`). Sau khi sửa, chạy script scan quote toàn file trước khi commit:

```bash
python3 -c "
content = open('phpstan-baseline.neon').read()
in_string = False
i = 0
while i < len(content):
    if content[i] == \"'\":
        if in_string and i+1 < len(content) and content[i+1] == \"'\":
            i += 2
            continue
        in_string = not in_string
    i += 1
print('OK - quotes balanced' if not in_string else 'BROKEN - unbalanced quote')
"
```

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/WorkTemplateApplyController.php routes/web.php tests/Feature/Web/ProjectWorkTemplateApplyTest.php
git commit -m "feat(projects): add web endpoints to list/preview/apply work templates"
```

---

## Task 3: Sửa card "Công việc" trên `projects/show.blade.php` dùng đúng model canonical

**Files:**
- Modify: `resources/views/projects/show.blade.php:86-107`
- Test: `tests/Feature/Web/ProjectShowTaskCardTest.php` (mới)

**Interfaces:**
- Consumes: biến `$sectionTasks` đã tồn tại sẵn trong `App\Http\Controllers\Web\ProjectController::show()` (không cần sửa controller — biến đã được truyền vào view qua `view('projects.show', [...])`, chỉ chưa được card này dùng).
- Produces: card "Công việc" hiển thị đúng `App\Models\Task` (canonical), tương thích để Task 4 (áp dụng template) hiện task mới ngay sau khi tạo.

- [ ] **Step 1: Xác nhận `$sectionTasks` đã được truyền vào view (không cần sửa gì ở controller)**

Chạy: `grep -n "sectionTasks" app/Http/Controllers/Web/ProjectController.php`
Xác nhận thấy dòng gán `$sectionTasks = \App\Models\Task::query()->where('tenant_id', ...)->where('project_id', ...)->with('assignee:id,name')->orderBy('created_at')->get();` và biến này nằm trong mảng `view('projects.show', [...])` ở cuối method `show()`. Nếu biến chưa có trong mảng trả về view, thêm `'sectionTasks' => $sectionTasks,` vào mảng đó.

- [ ] **Step 2: Viết test trước (RED)**

Tạo file `tests/Feature/Web/ProjectShowTaskCardTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShowTaskCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_card_shows_canonical_task_not_legacy_relation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $role = Role::factory()->create(['name' => 'Test Viewer Role ' . uniqid()]);
        $permission = Permission::where('code', 'project.view')->first()
            ?? Permission::factory()->create(['code' => 'project.view', 'name' => 'project.view']);
        $role->permissions()->sync([$permission->id]);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        Task::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Task Canonical Xuất Hiện',
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('Task Canonical Xuất Hiện');
    }
}
```

- [ ] **Step 3: Chạy test, xác nhận FAIL**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectShowTaskCardTest.php`
Expected: FAIL — task không xuất hiện (`assertSee` thất bại) vì view đang đọc `$project->tasks` (legacy, rỗng).

Nếu test lỗi ở bước setup (permission/role không đủ để `GET /app/projects/{project}` không bị 403 hoặc redirect) — kiểm tra route `app.projects.show` có yêu cầu permission cụ thể nào khác qua `grep -n "'/projects/{project}'" routes/web.php` và bổ sung permission tương ứng vào role test.

- [ ] **Step 4: Sửa `resources/views/projects/show.blade.php` dòng 86-107**

Thay:

```blade
    <x-ui.card title="Công việc ({{ $project->tasks->count() }})">
        <div class="mb-3">
            <x-ui.button-link href="/app/tasks/create?project_id={{ $project->id }}">Thêm công việc</x-ui.button-link>
        </div>

        @if ($project->tasks->isEmpty())
            <p class="text-sm text-slate-500">Dự án chưa có công việc nào.</p>
        @else
            <x-ui.data-table :headers="['Công việc', 'Trạng thái', 'Tiến độ', 'Kết thúc']">
                @foreach ($project->tasks as $task)
```

thành:

```blade
    <x-ui.card title="Công việc ({{ $sectionTasks->count() }})">
        <div class="mb-3">
            <x-ui.button-link href="/app/tasks/create?project_id={{ $project->id }}">Thêm công việc</x-ui.button-link>
        </div>

        @if ($sectionTasks->isEmpty())
            <p class="text-sm text-slate-500">Dự án chưa có công việc nào.</p>
        @else
            <x-ui.data-table :headers="['Công việc', 'Trạng thái', 'Tiến độ', 'Kết thúc']">
                @foreach ($sectionTasks as $task)
```

Giữ nguyên phần còn lại (dòng 96-107: `<td>` nội dung, `@endforeach`, `@endif`, `</x-ui.card>`) — không đổi field nào khác vì `App\Models\Task` đã có đủ `name`/`status`/`progress_percent`/`end_date`.

- [ ] **Step 5: Chạy lại test, xác nhận PASS**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectShowTaskCardTest.php`
Expected: PASS.

- [ ] **Step 6: Chạy toàn bộ test liên quan `projects/show` để không phá vỡ gì**

Run: `./vendor/bin/phpunit --filter=ProjectShow`
Expected: tất cả PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/views/projects/show.blade.php tests/Feature/Web/ProjectShowTaskCardTest.php
git commit -m "fix(projects): show canonical App\Models\Task in work card instead of legacy relation"
```

---

## Task 4: Blade/Alpine.js UI — khối "Áp dụng mẫu công việc" trên trang dự án

**Files:**
- Create: `resources/views/projects/_apply-work-template.blade.php`
- Modify: `resources/views/projects/show.blade.php` (thêm 1 dòng include)
- Test: `tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php` (mới)

**Interfaces:**
- Consumes: 3 route Web từ Task 2 (`app.projects.work-templates.index/preview/apply`); component `<x-ui.card>` có sẵn.
- Produces: khối UI ẩn/hiện theo quyền `template.apply`, gọi đúng 3 endpoint bằng Alpine.js + `fetch()`.

- [ ] **Step 1: Viết test trước (RED) — chỉ kiểm tra khối UI hiện/ẩn đúng theo quyền, không kiểm tra JS runtime (JS được test gián tiếp qua Task 2 đã pass)**

Tạo file `tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectApplyWorkTemplateUiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithPermissions(Tenant $tenant, array $codes): User
    {
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id]);
        $role = Role::factory()->create(['name' => 'Test Role ' . uniqid()]);

        $ids = [];
        foreach ($codes as $code) {
            $permission = Permission::where('code', $code)->first()
                ?? Permission::factory()->create(['code' => $code, 'name' => $code]);
            $ids[] = $permission->id;
        }
        $role->permissions()->sync($ids);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        return $user;
    }

    public function test_apply_template_card_visible_when_user_has_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view', 'template.apply']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('Áp dụng mẫu công việc');
    }

    public function test_apply_template_card_hidden_when_user_lacks_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithPermissions($tenant, ['project.view']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertDontSee('Áp dụng mẫu công việc');
    }
}
```

- [ ] **Step 2: Chạy test, xác nhận FAIL**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php`
Expected: FAIL trên test đầu (`assertSee` — khối chưa tồn tại trong view).

Nếu test setup lỗi vì route `app.projects.show` đòi permission khác `project.view` — kiểm tra bằng `grep -n "projects/{project}'" routes/web.php` và bổ sung permission cần thiết vào `makeUserWithPermissions`.

- [ ] **Step 3: Tạo `resources/views/projects/_apply-work-template.blade.php`**

```blade
@if (auth()->user()?->hasPermission('template.apply'))
    <div x-data="workTemplateApply('{{ $project->id }}')" x-init="init()">
        <x-ui.card title="Áp dụng mẫu công việc">
            <template x-if="loadingList">
                <p class="text-sm text-slate-500">Đang tải danh sách mẫu...</p>
            </template>

            <template x-if="!loadingList && templates.length === 0">
                <p class="text-sm text-slate-500">Chưa có mẫu công việc nào được publish. Liên hệ quản trị viên để tạo mẫu.</p>
            </template>

            <template x-if="!loadingList && templates.length > 0">
                <div class="space-y-3">
                    <select x-model="selectedTemplateId" class="operator-select" @change="preview = null; error = ''">
                        <option value="">-- Chọn mẫu công việc --</option>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="tpl.id" x-text="tpl.name"></option>
                        </template>
                    </select>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="operator-button operator-button-secondary"
                            :disabled="!selectedTemplateId || loadingPreview"
                            @click="fetchPreview()"
                        >Xem trước</button>
                        <button
                            type="button"
                            class="operator-button operator-button-primary"
                            x-show="preview && !preview.duplicate"
                            :disabled="applying"
                            @click="applyTemplate()"
                        >Áp dụng</button>
                    </div>

                    <template x-if="error">
                        <p class="text-sm text-rose-600" x-text="error"></p>
                    </template>

                    <template x-if="preview">
                        <div class="rounded border border-slate-200 p-3 text-sm">
                            <template x-if="preview.duplicate">
                                <p class="text-amber-600">Mẫu này đã được áp dụng cho dự án trước đó.</p>
                            </template>
                            <template x-if="!preview.duplicate">
                                <ul class="space-y-1 text-slate-700">
                                    <li>Giai đoạn: <span x-text="preview.summary.phases"></span></li>
                                    <li>Công việc: <span x-text="preview.summary.tasks"></span></li>
                                    <li>Checklist: <span x-text="preview.summary.checklists"></span></li>
                                    <li>Tài liệu: <span x-text="preview.summary.docs"></span></li>
                                </ul>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </x-ui.card>
    </div>

    <script>
        function workTemplateApply(projectId) {
            return {
                projectId: projectId,
                templates: [],
                selectedTemplateId: '',
                preview: null,
                loadingList: true,
                loadingPreview: false,
                applying: false,
                error: '',
                csrfToken() {
                    return document.querySelector('meta[name="csrf-token"]').getAttribute('content');
                },
                async init() {
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates`);
                        const result = await response.json();
                        this.templates = result.data ?? [];
                    } catch (e) {
                        this.error = 'Không tải được danh sách mẫu.';
                    } finally {
                        this.loadingList = false;
                    }
                },
                async fetchPreview() {
                    this.loadingPreview = true;
                    this.error = '';
                    this.preview = null;
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates/preview`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({ work_template_id: this.selectedTemplateId }),
                        });
                        const result = await response.json();
                        if (result.success) {
                            this.preview = result.data;
                        } else {
                            this.error = result.message || 'Không xem trước được.';
                        }
                    } catch (e) {
                        this.error = 'Có lỗi xảy ra, thử lại.';
                    } finally {
                        this.loadingPreview = false;
                    }
                },
                async applyTemplate() {
                    this.applying = true;
                    this.error = '';
                    try {
                        const response = await fetch(`/app/projects/${this.projectId}/work-templates/apply`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({ work_template_id: this.selectedTemplateId }),
                        });
                        const result = await response.json();
                        if (result.success && !result.data.duplicate) {
                            window.location.reload();
                        } else if (result.success && result.data.duplicate) {
                            this.preview = result.data;
                        } else {
                            this.error = result.message || 'Áp dụng thất bại.';
                        }
                    } catch (e) {
                        this.error = 'Có lỗi xảy ra, thử lại.';
                    } finally {
                        this.applying = false;
                    }
                },
            };
        }
    </script>
@endif
```

- [ ] **Step 4: Nhúng partial vào `resources/views/projects/show.blade.php`**

Tìm dòng (sau khối "Kế hoạch gốc", trước khối "Công việc" đã sửa ở Task 3 — dùng `grep -n "Kế hoạch gốc\|Công việc (" resources/views/projects/show.blade.php` để xác định vị trí chính xác trước khi sửa vì số dòng có thể lệch sau Task 3):

```blade
    <x-ui.card title="Công việc ({{ $sectionTasks->count() }})">
```

Thêm dòng include NGAY TRƯỚC dòng này:

```blade
    @include('projects._apply-work-template', ['project' => $project])

```

- [ ] **Step 5: Chạy lại test, xác nhận PASS**

Run: `./vendor/bin/phpunit tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php`
Expected: PASS (2 test).

- [ ] **Step 6: Chạy toàn bộ test của cả 4 task để xác nhận không có regression**

Run: `./vendor/bin/phpunit tests/Feature/Seeders/ProjectManagerTemplatePermissionSeederTest.php tests/Feature/Web/ProjectWorkTemplateApplyTest.php tests/Feature/Web/ProjectShowTaskCardTest.php tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php`
Expected: tất cả PASS.

Run thêm: `./vendor/bin/phpunit tests/Feature/Api/WorkTemplateV2ApiTest.php tests/Feature/Api/WorkTemplateV2RbacTest.php tests/Feature/Api/WorkTemplateV2TenantIsolationTest.php tests/Feature/Api/WorkTemplateV2ValidationTest.php`
Expected: vẫn 16/16 PASS như trước khi bắt đầu (không đụng gì vào `Api\WorkTemplateController`).

- [ ] **Step 7: Commit**

```bash
git add resources/views/projects/_apply-work-template.blade.php resources/views/projects/show.blade.php tests/Feature/Web/ProjectApplyWorkTemplateUiTest.php
git commit -m "feat(projects): add apply-work-template UI card with preview/apply flow"
```

---

## Sau khi xong cả 4 task

Chạy toàn bộ test suite liên quan lần cuối trước khi mở PR:

```bash
./vendor/bin/phpunit tests/Feature/Seeders/ tests/Feature/Web/Project tests/Feature/Api/WorkTemplateV2
```

Chạy PHPStan toàn bộ file đã đổi:

```bash
./vendor/bin/phpstan analyse app/Http/Controllers/Web/WorkTemplateApplyController.php app/Http/Controllers/Web/ProjectController.php database/seeders/ZenaPermissionsSeeder.php --level=6
```
