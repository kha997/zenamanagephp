# Workload View Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Khối lượng công việc" page grouping open Tasks + DesignItems by assignee (sorted by load, red overdue / amber blocked counts), plus an assignee column + filter on the existing tasks list.

**Architecture:** One new server-rendered controller (`WorkloadPageController::index`) doing 3 tenant-scoped queries and PHP grouping into per-user view-models; a shared Blade items-table partial; small retrofit of `AppController::tasks()` + its view. No JS, no migration, no service class.

**Tech Stack:** Laravel 12, Blade, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-19-workload-view-design.md`

## Global Constraints

- Permission: the new page uses existing `rbac:task.view` (same as `schedule.index`). No new permission.
- Pinned definitions — copy exactly: open Task = `status ∈ {pending, in_progress, on_hold}`; open DesignItem = `status ∉ {approved, final}`; overdue = Task `end_date < today` (date-only; DesignItems never overdue); blocked = `blocked_at` not null; an item both overdue and blocked shows the red "Quá hạn" badge (overdue wins) but counts in BOTH counters.
- Users with zero open items still render (count 0, after loaded users); unassigned open items go in a final "Chưa phân công" block.
- Tasks-list filter: invalid or foreign-tenant `?assigned_to=` values are silently ignored (show all), never an error.
- Read-only feature: no writes, no model changes, `TaskAssignment` untouched.
- Run tests via `./vendor/bin/phpunit <path>` — never `php artisan test` (hybrid-vendor worktree crash).

---

### Task 1: Workload page (controller + route + nav + views + tests)

**Files:**
- Create: `app/Http/Controllers/Web/WorkloadPageController.php`
- Create: `resources/views/app/workload.blade.php`
- Create: `resources/views/app/_workload-items-table.blade.php`
- Modify: `routes/web.php` (one route, directly after the `Route::get('/tasks', [App\Http\Controllers\Web\AppController::class, 'tasks'])->name('tasks');` line ~385, inside the same `app.`-prefixed group)
- Modify: `resources/views/layouts/operator.blade.php` (nav link in the "Dự án" section, directly after the "Công việc" `</a>` block, ~line 99)
- Test: `tests/Feature/WorkloadPageTest.php` (new file)

**Interfaces:**
- Consumes: `App\Models\Task` (`STATUS_PENDING/IN_PROGRESS/ON_HOLD`, `assigned_to`, `end_date`, `blocked_at`, `project` relation), `App\Models\DesignItem` (`STATUS_APPROVED/FINAL`, `assigned_to`, `blocked_at`, `project` relation), `App\Models\User`. Routes `app.tasks.show`, `operator.design-items.show` (existing).
- Produces: route `app.workload.index` (GET `/app/workload`). Task 2 is independent of this task.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/WorkloadPageTest.php`:

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

class WorkloadPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;
    private User $worker;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['admin'], ['task.view']);
        $this->worker = $this->createTenantUser($this->tenant, ['name' => 'Kiến Trúc Sư A'], ['member'], []);

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
            'assigned_to' => (string) $this->worker->id,
            'blocked_at' => null,
            'end_date' => now()->addDays(7)->toDateString(),
        ], $overrides));
    }

    private function openDesignItem(array $overrides = []): DesignItem
    {
        return DesignItem::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => (string) $this->worker->id,
            'blocked_at' => null,
        ], $overrides));
    }

    public function test_shows_person_with_their_open_task_and_design_item(): void
    {
        $task = $this->openTask(['name' => 'Dựng mặt bằng tầng 1', 'title' => 'Dựng mặt bằng tầng 1']);
        $item = $this->openDesignItem(['name' => 'Concept nội thất phòng khách']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Khối lượng công việc');
        $response->assertSee('Kiến Trúc Sư A');
        $response->assertSee('Dựng mặt bằng tầng 1');
        $response->assertSee('Concept nội thất phòng khách');
        $response->assertSee('2 đang mở');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.workload.index'))->assertStatus(403);
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
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_closed_items_do_not_appear(): void
    {
        $this->openTask(['status' => Task::STATUS_COMPLETED, 'name' => 'Task đã xong', 'title' => 'Task đã xong']);
        $this->openDesignItem(['status' => DesignItem::STATUS_FINAL, 'name' => 'Hạng mục đã chốt']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertDontSee('Task đã xong');
        $response->assertDontSee('Hạng mục đã chốt');
    }

    public function test_unassigned_open_item_appears_under_unassigned_block(): void
    {
        $this->openTask(['assigned_to' => null, 'name' => 'Việc chưa ai nhận', 'title' => 'Việc chưa ai nhận']);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Chưa phân công');
        $response->assertSee('Việc chưa ai nhận');
    }

    public function test_overdue_task_counts_and_badges(): void
    {
        $this->openTask(['end_date' => now()->subDays(3)->toDateString()]);

        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('1 quá hạn');
        $response->assertSee('Quá hạn');
    }

    public function test_person_with_zero_items_still_renders(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.workload.index'));

        $response->assertOk();
        $response->assertSee('Kiến Trúc Sư A');
        $response->assertSee('0 đang mở');
    }
}
```

Note: `createTenantUser`'s second argument is an attribute-overrides array (the trait supports `['name' => ...]`); if the trait's signature differs, adapt the worker's name assignment accordingly (e.g., `$this->worker->update(['name' => 'Kiến Trúc Sư A'])`) and report the deviation.

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/WorkloadPageTest.php`
Expected: FAIL — route `app.workload.index` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, directly after `Route::get('/tasks', [App\Http\Controllers\Web\AppController::class, 'tasks'])->name('tasks');` (inside the `app.` group):

```php
    Route::get('/workload', [App\Http\Controllers\Web\WorkloadPageController::class, 'index'])->middleware('rbac:task.view')->name('workload.index');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Web/WorkloadPageController.php`:

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

/**
 * Trang "Khối lượng công việc" — việc đang mở (Task + Hạng mục thiết kế)
 * nhóm theo người, sắp theo tải giảm dần.
 * Spec: docs/superpowers/specs/2026-07-19-workload-view-design.md
 */
class WorkloadPageController extends Controller
{
    public function index(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;
        $today = Carbon::now()->startOfDay();

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name']);

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'assigned_to', 'end_date', 'blocked_at']);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('status', [DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL])
            ->with('project:id,tenant_id,name')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'status', 'assigned_to', 'blocked_at']);

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
                'status' => (string) $designItem->status,
                'url' => route('operator.design-items.show', $designItem->id),
            ]);
        }

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
}
```

- [ ] **Step 5: Create the items-table partial**

Create `resources/views/app/_workload-items-table.blade.php`:

```blade
<x-ui.data-table :headers="['Việc', 'Dự án', 'Loại', 'Hạn', 'Trạng thái']">
    @foreach ($items as $item)
        <tr>
            <td>
                <a href="{{ $item['url'] }}" class="operator-link font-medium">{{ $item['name'] }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $item['project_name'] }}</td>
            <td class="text-sm text-slate-600">{{ $item['kind_label'] }}</td>
            <td class="text-sm text-slate-600">{{ $item['end_date'] ? \Illuminate\Support\Carbon::parse($item['end_date'])->format('d/m/Y') : '—' }}</td>
            <td>
                @if ($item['is_overdue'])
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item['is_blocked'])
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item['status']" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
```

- [ ] **Step 6: Create the page view**

Create `resources/views/app/workload.blade.php`:

```blade
@extends('layouts.operator')

@section('title', 'Khối lượng công việc')
@section('page_title', 'Khối lượng công việc')

@section('content')
    <x-ui.page-header
        title="Khối lượng công việc"
        description="Việc đang mở (công việc + hạng mục thiết kế) theo từng người, sắp theo tải giảm dần."
    />

    @php $totalOpen = collect($blocks)->sum('open_count') + count($unassigned); @endphp

    @if ($totalOpen === 0 && collect($blocks)->isEmpty())
        <x-ui.empty-state title="Không có việc nào đang mở" description="Mọi công việc và hạng mục thiết kế đều đã đóng." />
    @else
        @foreach ($blocks as $block)
            <x-ui.card :title="$block['user']->name">
                <p class="mb-2 text-sm">
                    <span class="font-medium text-slate-900">{{ $block['open_count'] }} đang mở</span>
                    <span class="text-slate-400">·</span>
                    <span class="{{ $block['overdue_count'] > 0 ? 'font-medium text-rose-600' : 'text-slate-500' }}">{{ $block['overdue_count'] }} quá hạn</span>
                    <span class="text-slate-400">·</span>
                    <span class="{{ $block['blocked_count'] > 0 ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $block['blocked_count'] }} bị chặn</span>
                </p>
                @if ($block['items']->isEmpty())
                    <p class="text-sm text-slate-500">Không có việc đang mở.</p>
                @else
                    @include('app._workload-items-table', ['items' => $block['items']])
                @endif
            </x-ui.card>
        @endforeach

        @if (count($unassigned) > 0)
            <x-ui.card title="Chưa phân công">
                @include('app._workload-items-table', ['items' => $unassigned])
            </x-ui.card>
        @endif
    @endif
@endsection
```

- [ ] **Step 7: Add the nav link**

In `resources/views/layouts/operator.blade.php`, directly after the closing `</a>` of the "Công việc" nav link (the one with `route('app.tasks')`, ~line 99):

```blade
                <a href="{{ route('app.workload.index') }}"
                   class="operator-nav-link {{ request()->routeIs('app.workload*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>Khối lượng</span>
                </a>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/WorkloadPageTest.php`
Expected: PASS (7 tests).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Web/WorkloadPageController.php resources/views/app/workload.blade.php resources/views/app/_workload-items-table.blade.php routes/web.php resources/views/layouts/operator.blade.php tests/Feature/WorkloadPageTest.php
git commit -m "feat(workload): per-person workload page for tasks and design items"
```

---

### Task 2: Tasks list — assignee column + filter

**Files:**
- Modify: `app/Http/Controllers/Web/AppController.php` (`tasks()` method)
- Modify: `resources/views/app/tasks.blade.php`
- Test: append to `tests/Feature/WorkloadPageTest.php`

**Interfaces:**
- Consumes: `Task.assignee` BelongsTo relation (existing), `User` model.
- Produces: nothing consumed later — final task.

- [ ] **Step 1: Write the failing tests**

Append inside the class in `tests/Feature/WorkloadPageTest.php`:

```php
    public function test_tasks_list_shows_assignee_column(): void
    {
        $this->openTask(['name' => 'Task có người nhận', 'title' => 'Task có người nhận']);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks'));

        $response->assertOk();
        $response->assertSee('Người phụ trách');
        $response->assertSee('Kiến Trúc Sư A');
    }

    public function test_tasks_list_filters_by_assignee(): void
    {
        $other = $this->createTenantUser($this->tenant, ['name' => 'Người Khác B'], ['member'], []);
        $this->openTask(['name' => 'Việc của A', 'title' => 'Việc của A']);
        $this->openTask(['name' => 'Việc của B', 'title' => 'Việc của B', 'assigned_to' => (string) $other->id]);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks', ['assigned_to' => (string) $this->worker->id]));

        $response->assertOk();
        $response->assertSee('Việc của A');
        $response->assertDontSee('Việc của B');
    }

    public function test_tasks_list_ignores_foreign_tenant_assignee_filter(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createTenantUser($otherTenant, [], ['member'], []);
        $this->openTask(['name' => 'Việc của A', 'title' => 'Việc của A']);

        $response = $this->actingAs($this->viewer)->get(route('app.tasks', ['assigned_to' => (string) $foreign->id]));

        $response->assertOk();
        $response->assertSee('Việc của A');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/WorkloadPageTest.php --filter tasks_list`
Expected: FAIL — "Người phụ trách" not found.

- [ ] **Step 3: Update `AppController::tasks()`**

Replace the method body with:

```php
    public function tasks(Request $request)
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $assignedTo = (string) $request->query('assigned_to', '');
        if ($assignedTo !== '' && !User::query()->where('tenant_id', $tenantId)->whereKey($assignedTo)->exists()) {
            $assignedTo = '';
        }

        $tasksQuery = Task::query()
            ->where('tenant_id', $tenantId)
            ->with(['project:id,tenant_id,name,code', 'assignee:id,name'])
            ->orderByDesc('updated_at')
            ->limit(200);

        if ($assignedTo !== '') {
            $tasksQuery->where('assigned_to', $assignedTo);
        }

        return view('app.tasks', [
            'tasks' => $tasksQuery->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'priority', 'progress_percent', 'start_date', 'end_date', 'blocked_at', 'assigned_to']),
            'tenantUsers' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'tenant_id', 'name']),
            'assignedTo' => $assignedTo,
        ]);
    }
```

(`Request` is already imported in this controller; `User` is already imported.)

- [ ] **Step 4: Update the tasks list view**

In `resources/views/app/tasks.blade.php`:

1. Directly after the `<x-ui.page-header ...>` block, add the filter form:

```blade
    <form method="GET" action="{{ route('app.tasks') }}" class="mb-4 flex items-end gap-2">
        <div class="operator-field w-64">
            <label for="assigned_to">Người phụ trách</label>
            <select id="assigned_to" name="assigned_to" class="operator-select">
                <option value="">Tất cả</option>
                @foreach ($tenantUsers as $tenantUser)
                    <option value="{{ $tenantUser->id }}" @selected($assignedTo === (string) $tenantUser->id)>{{ $tenantUser->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="operator-button operator-button-secondary">Lọc</button>
    </form>
```

2. Change the headers line to:

```blade
            <x-ui.data-table :headers="['Công việc', 'Dự án', 'Người phụ trách', 'Trạng thái', 'Ưu tiên', 'Tiến độ', 'Kết thúc']">
```

3. Inside the row loop, directly after the project `<td>`, add:

```blade
                        <td class="text-sm text-slate-600">{{ $task->assignee?->name ?? '—' }}</td>
```

- [ ] **Step 5: Run the full slice sweep**

Run: `./vendor/bin/phpunit tests/Feature/WorkloadPageTest.php`
Expected: PASS (10 tests).

Run: `php artisan view:cache`
Expected: "Blade templates cached successfully".

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/AppController.php resources/views/app/tasks.blade.php tests/Feature/WorkloadPageTest.php
git commit -m "feat(tasks): assignee column and filter on tasks list"
```
