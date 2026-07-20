# "Việc của tôi" Personal Work Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Việc của tôi" page where any employee sees only their own open Task/DesignItem work, with a one-line open/overdue/blocked count.

**Architecture:** Extract the item-building loop already inside `WorkloadPageController::index()` (#195) into a shared private method, add a new `myWork()` action that filters to the logged-in user, reuse the existing `_workload-items-table` partial, add one nav link. Single task — this is a small, self-contained slice.

**Tech Stack:** Laravel 12, Blade, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-20-my-work-page-design.md`

## Global Constraints

- Permission: `rbac:task.view` on the new route — the same gate already on `app.workload.index` (`routes/web.php:387`). `app.tasks` has no middleware at all; do not copy from that route. No new permission.
- Definitions identical to #195, reused verbatim: open Task = `status ∈ {pending, in_progress, on_hold}`; open DesignItem = `review_status ∉ {approved, final}`; overdue = Task `end_date < today` (date-only, DesignItems never overdue); blocked = `blocked_at` not null; overdue wins the badge display but both counters increment when an item is both.
- This page shows ONLY the logged-in user's own items — no "unassigned" block (that's a manager-view-only concept from #195).
- `WorkloadPageTest`'s existing tests must keep passing unmodified — the extraction must not change `index()`'s behavior.
- Run tests via `./vendor/bin/phpunit <path>` — never `php artisan test` (hybrid-vendor worktree crash). Ignore imagick/memcached dylib startup warnings — environmental noise.

---

### Task 1: Extract shared item-collection + add `myWork()` action + nav link + tests

**Files:**
- Modify: `app/Http/Controllers/Web/WorkloadPageController.php` (extract a private method, add `myWork()`)
- Create: `resources/views/app/my-work.blade.php`
- Modify: `routes/web.php` (one route, directly after the `app.workload.index` line ~387)
- Modify: `resources/views/layouts/operator.blade.php` (nav link directly after the "Khối lượng" link, ~line 104)
- Test: `tests/Feature/MyWorkPageTest.php` (new file)

**Interfaces:**
- Consumes: `App\Models\Task`, `App\Models\DesignItem`, `App\Models\User` (all existing, same as #195); `resources/views/app/_workload-items-table.blade.php` (existing partial, unchanged).
- Produces: route `app.my-work.index` (GET `/app/my-work`). Nothing later consumes this — single-task plan.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/MyWorkPageTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MyWorkPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, ['name' => 'Kiến Trúc Sư A'], ['admin'], ['task.view']);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
        ]);
    }

    private function openTask(array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => (string) $this->viewer->id,
            'blocked_at' => null,
            'end_date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    private function openDesignItem(array $overrides = []): DesignItem
    {
        return DesignItem::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => (string) $this->viewer->id,
            'blocked_at' => null,
        ], $overrides));
    }

    public function test_shows_only_my_own_open_task_and_design_item(): void
    {
        $this->openTask(['name' => 'Dựng mặt bằng tầng 1', 'title' => 'Dựng mặt bằng tầng 1']);
        $this->openDesignItem(['name' => 'Concept nội thất phòng khách']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertSee('Dựng mặt bằng tầng 1');
        $response->assertSee('Concept nội thất phòng khách');
        $response->assertSee('2 đang mở');
    }

    public function test_coworkers_items_do_not_render(): void
    {
        $coworker = $this->createTenantUser($this->tenant, ['name' => 'Người Khác B'], ['member'], []);
        $this->openTask(['name' => 'Việc của B', 'title' => 'Việc của B', 'assigned_to' => (string) $coworker->id]);
        $this->openTask(['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertDontSee('Việc của B');
    }

    public function test_unassigned_items_do_not_render(): void
    {
        $this->openTask(['name' => 'Việc chưa ai nhận', 'title' => 'Việc chưa ai nhận', 'assigned_to' => null]);
        $this->openTask(['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Việc của tôi');
        $response->assertDontSee('Việc chưa ai nhận');
    }

    public function test_count_line_matches_overdue_and_blocked(): void
    {
        $this->openTask(['name' => 'Việc trễ', 'title' => 'Việc trễ', 'end_date' => now()->subDays(2)->toDateString()]);
        $this->openTask(['name' => 'Việc bị chặn', 'title' => 'Việc bị chặn', 'blocked_at' => now()]);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('2 đang mở');
        $response->assertSee('1 quá hạn');
        $response->assertSee('1 bị chặn');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.my-work.index'))->assertStatus(403);
    }

    public function test_cross_tenant_items_never_render(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $otherProject->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc tenant khác',
            'title' => 'Việc tenant khác',
            'assigned_to' => (string) $this->viewer->id,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_empty_state_when_no_open_items(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.my-work.index'));

        $response->assertOk();
        $response->assertSee('Bạn chưa có việc nào đang mở');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/MyWorkPageTest.php`
Expected: FAIL — route `app.my-work.index` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, directly after the existing line:

```php
    Route::get('/workload', [App\Http\Controllers\Web\WorkloadPageController::class, 'index'])->middleware('rbac:task.view')->name('workload.index');
```

add:

```php
    Route::get('/my-work', [App\Http\Controllers\Web\WorkloadPageController::class, 'myWork'])->middleware('rbac:task.view')->name('my-work.index');
```

- [ ] **Step 4: Extract the shared item-collection method and add `myWork()`**

In `app/Http/Controllers/Web/WorkloadPageController.php`, the current `index()` method builds `$items` with two `foreach` loops over `$tasks` and `$designItems` (reading tasks/design items already scoped to `$tenantId`). Extract that construction into a new private method `collectOpenItems(string $tenantId): Collection`, and have `index()` call it. Replace the whole class body with:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\DesignItem;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Trang "Khối lượng công việc" — việc đang mở (Task + Hạng mục thiết kế)
 * nhóm theo người, sắp theo tải giảm dần.
 * Spec: docs/superpowers/specs/2026-07-19-workload-view-design.md
 *
 * myWork() là góc nhìn cá nhân của cùng dữ liệu — chỉ việc của
 * người đang đăng nhập, không có khối "Chưa phân công".
 * Spec: docs/superpowers/specs/2026-07-20-my-work-page-design.md
 */
class WorkloadPageController extends Controller
{
    public function index(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name']);

        $items = $this->collectOpenItems($tenantId);

        $grouped = $items->groupBy(fn (array $i) => $i['assigned_to'] ?? '__unassigned');

        $blocks = $users
            ->map(function (User $user) use ($grouped): array {
                /** @var Collection<int, array<string, mixed>> $list */
                $list = $grouped->get((string) $user->id, collect())->values();

                return [
                    'user' => $user,
                    'items' => $list,
                    'open_count' => $list->count(),
                    'overdue_count' => $list->where('is_overdue', true)->count(),
                    'blocked_count' => $list->where('is_blocked', true)->count(),
                ];
            })
            ->sortByDesc('open_count')
            ->values();

        $unassigned = $grouped->get('__unassigned', collect())->values();

        return view('app.workload', [
            'blocks' => $blocks,
            'unassigned' => $unassigned,
        ]);
    }

    public function myWork(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;
        $userId = (string) Auth::id();

        $items = $this->collectOpenItems($tenantId)
            ->where('assigned_to', $userId)
            ->values();

        return view('app.my-work', [
            'items' => $items,
            'open_count' => $items->count(),
            'overdue_count' => $items->where('is_overdue', true)->count(),
            'blocked_count' => $items->where('is_blocked', true)->count(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function collectOpenItems(string $tenantId): Collection
    {
        $today = Carbon::now()->startOfDay();

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'assigned_to', 'end_date', 'blocked_at']);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('review_status', [DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'review_status', 'assigned_to', 'blocked_at']);

        $items = collect();

        foreach ($tasks as $task) {
            $isOverdue = $task->end_date !== null
                && Carbon::parse(substr((string) $task->end_date, 0, 10))->startOfDay()->lt($today);

            $items->push([
                'assigned_to' => $task->assigned_to !== null ? (string) $task->assigned_to : null,
                'kind_label' => 'Công việc',
                'name' => (string) ($task->name ?? $task->title ?? $task->id),
                'project_name' => $task->project?->name ?? '—',
                'end_date' => $task->end_date,
                'is_overdue' => $isOverdue,
                'is_blocked' => $task->blocked_at !== null,
                'status' => (string) $task->status,
                'url' => route('app.tasks.show', $task->id),
            ]);
        }

        foreach ($designItems as $designItem) {
            $items->push([
                'assigned_to' => $designItem->assigned_to !== null ? (string) $designItem->assigned_to : null,
                'kind_label' => 'Hạng mục thiết kế',
                'name' => (string) $designItem->name,
                'project_name' => $designItem->project?->name ?? '—',
                'end_date' => null,
                'is_overdue' => false,
                'is_blocked' => $designItem->blocked_at !== null,
                'status' => (string) $designItem->review_status,
                'url' => route('operator.design-items.show', $designItem->id),
            ]);
        }

        return $items;
    }
}
```

- [ ] **Step 5: Create the view**

Create `resources/views/app/my-work.blade.php`:

```blade
@extends('layouts.operator')

@section('title', 'Việc của tôi')
@section('page_title', 'Việc của tôi')

@section('content')
    <x-ui.page-header
        title="Việc của tôi"
        description="Công việc và hạng mục thiết kế đang mở, gán cho bạn."
    />

    <x-ui.card>
        <p class="mb-3 text-sm">
            <span class="font-medium text-slate-900">{{ $open_count }} đang mở</span>
            <span class="text-slate-400">·</span>
            <span class="{{ $overdue_count > 0 ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $overdue_count }} quá hạn</span>
            <span class="text-slate-400">·</span>
            <span class="{{ $blocked_count > 0 ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $blocked_count }} bị chặn</span>
        </p>

        @if ($items->isEmpty())
            <p class="text-sm text-slate-500">Bạn chưa có việc nào đang mở.</p>
        @else
            @include('app._workload-items-table', ['items' => $items])
        @endif
    </x-ui.card>
@endsection
```

- [ ] **Step 6: Add the nav link**

In `resources/views/layouts/operator.blade.php`, directly after the closing `</a>` of the "Khối lượng" nav link (the one with `route('app.workload.index')`, ~line 104):

```blade
                <a href="{{ route('app.my-work.index') }}"
                   class="operator-nav-link {{ request()->routeIs('app.my-work*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                    <span>Việc của tôi</span>
                </a>
```

- [ ] **Step 7: Run the full slice test sweep**

Run: `./vendor/bin/phpunit tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php`
Expected: PASS (7 new + existing WorkloadPageTest tests, all green — confirms the extraction didn't change `index()`'s behavior).

Run: `php artisan view:cache`
Expected: "Blade templates cached successfully".

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/WorkloadPageController.php resources/views/app/my-work.blade.php routes/web.php resources/views/layouts/operator.blade.php tests/Feature/MyWorkPageTest.php
git commit -m "feat(workload): personal 'Việc của tôi' page for individual employees"
```
