# Today Workspace MVP Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `/app/today`, a role-aware read-only workspace page with six sections (Personal Open Work, Đang thực hiện, Overdue and Blocked, Upcoming Milestones, Unread Updates, PM/Team Exceptions), plus a permission-aware operator sidebar — with zero new tables, zero Action Required runtime code, and zero regressions to `/app/my-work` or `/app/workload`.

**Architecture:** `TodayController::index()` resolves the actor/tenant and calls `TodayWorkspaceReadService::build()`, which fetches open work exactly once and orchestrates four independent, tenant-scoped query collaborators (`OpenWorkReadQuery`, `UpcomingMilestoneQuery`, `UnreadUpdateQuery`, `TeamExceptionQuery`) and returns an immutable `TodayWorkspaceViewModel`. `OpenWorkReadQuery` is extracted from the currently-private `WorkloadPageController::collectOpenItems()` so `/app/my-work`, `/app/workload`, and `/app/today` share one qualification rule for "open work," expressed as a real `OpenWorkItem` DTO rather than an associative array. Navigation permission is derived at runtime from each destination route's real authorization middleware (`rbac:*` and `can:*`, whichever is present) via a separate `OperatorNavigationComposer` that preloads the actor's permission set once per request — not once per nav item, and not part of the Today service.

**Tech Stack:** Laravel 12 (PHP, Eloquent), Blade views, PHPUnit `RefreshDatabase` feature tests, existing `TenantScope` trait, existing `rbac`/`can` middleware, existing `App\Support\Dashboard\{Availability,Reliability}` trust enums.

## Global Constraints

- No canonical Project Progress percentage anywhere in Today output (`docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md` §2).
- No `Project.budget_actual` or any financial value in Today output.
- `Task.assigned_to` is the sole primary-assignee source; `TaskAssignment`/`assignedUsers` are never read by any Today query.
- No Action Required runtime section, no `ActionRequiredQuery` class, no `TodayActionItem` DTO, no dedup infrastructure — the qualification contract lives only as prose in the design doc §7, never as code.
- No new database table, no ActionItem ledger, no rule engine, no materialized projection, no background job.
- No second trust-state vocabulary — Today reuses `App\Support\Dashboard\Availability` and `App\Support\Dashboard\Reliability` exactly as they exist today; nothing in this feature edits those two files.
- Every query object must apply an **explicit** `tenant_id` (or explicit join to a tenant-scoped parent for `ProjectMilestone`) even on models that already carry `TenantScope` — never rely on the global scope alone.
- "Đang thực hiện" never marks any single task as the one being worked on "right now"; it lists every primary-assigned `in_progress` task.
- PM/Team Exceptions never computes or displays "free", "available X%", or "overloaded X%" — only recorded counts.
- Navigation: route authorization (`rbac:*` and `can:*` middleware) is never removed or bypassed; hiding a nav link never substitutes for it. A nav item is visible only when its destination's real, current authorization requirement is satisfied — never merely because a route happens to carry no `rbac:*` middleware.
- No route's authorization check (`hasPermission()`, Gate/Policy) is executed once per nav item — the actor's permission set is preloaded once per request.
- Query-count assertions are derived from an analytically documented fixed-query budget, then verified empirically — never "measure whatever the first implementation produces and lock that in."
- `ProjectMilestone` model itself is never modified in this feature; live overdue state is computed from `target_date`/`completed_date`/`status` directly in the query, not via the model's `scopeOverdue()`/`isOverdue()` helpers.
- GAP-030 and GAP-031 remediation are out of scope — Today only excludes those sources, it does not fix them.
- Existing `/app/my-work` and `/app/workload` behavior (routes, all current `MyWorkPageTest`/`WorkloadPageTest` assertions) must remain unchanged — Blade markup may change internally (array access → object property access) as long as rendered HTML text is identical.
- `/app/dashboard` route and controller are never removed or silently repurposed.
- No new icon dependency (package/library) is added; any new icon needed for the Today nav entry is a hand-written inline SVG path, same as every existing sidebar icon.

---

## Revision note (this is rev 2 of the plan — corrections found during a second implementation-readiness review)

Four structural corrections were made while re-verifying the plan against live code, in addition to the review's explicit requests:

1. **`OpenWorkReadQuery` now returns `OpenWorkItem` DTOs, not associative arrays.** The original plan kept arrays to avoid touching `_workload-items-table.blade.php`. Re-checked: `MyWorkPageTest`/`WorkloadPageTest` only assert rendered HTML text (`assertSee`/`assertDontSee`/`assertStatus`) — never PHP array shape — so the Blade partial **can** switch from `$item['name']` to `$item->name` and both test files still pass completely unmodified, because the rendered output is byte-identical either way. Task 1 now creates `App\Support\Work\OpenWorkItem` and updates the one Blade partial that reads it.
2. **`OpenWorkReadQuery::collect()` is now called exactly once per Today page request**, not once per section. The original Task 7 draft had `personalOpenWork()`, `inProgress()`, `overdueAndBlocked()`, and `TeamExceptionQuery::build()` each independently calling `collect()` — four redundant executions of the same two queries. `TodayWorkspaceReadService::build()` now fetches the collection once and passes it to all four consumers.
3. **`TeamExceptionQuery` no longer does a `User::find()` call inside a `->map()` over grouped members** (a real N+1 found while designing the query-budget analysis). It now does one bounded `whereIn('id', ...)` lookup for any member names not already available from `Team::activeMembers()`.
4. **Navigation authorization now recognizes `can:*` in addition to `rbac:*`**, with an explicit, evidence-based rule for when a zero-`rbac:*`/zero-`can:*` route may be treated as baseline-visible (a verified allowlist, not "every route with `auth`+`tenant.isolation`").

---

## Deviation from the design document tracker

- The design doc (§3.1, §4) named the shared DTO `OpenWorkItem` without specifying its namespace or that it must be an object (vs. array). This plan places it at `App\Support\Work\OpenWorkItem` (a **Work** namespace, not **Today**, because `WorkloadPageController` and `MyWorkPageTest`'s page consume it too — Today is not its owner).
- The design doc (§5) sketched Today-local `TodayAvailability`/`TodayReliability` enums. This plan reuses the already-shipped `App\Support\Dashboard\Availability`/`Reliability` instead (verified to exist at `app/Support/Dashboard/Availability.php` and `app/Support/Dashboard/Reliability.php`, from the Dashboard Data Trust Guardrails feature) — no second trust vocabulary. `TodaySectionResult` keeps its own name and stays under `App\Support\Today`, but its two enum-typed properties are typed against the Dashboard namespace.
- Neither deviation reopens the design's product decisions (six sections, no Action Required runtime, no canonical progress, etc.) — both are implementation-level corrections the design document did not pin down precisely enough to avoid the two problems described above.

The exact `OpenWorkItem` contract, going forward:

```php
final class OpenWorkItem
{
    public function __construct(
        public readonly string $sourceType,   // 'task' | 'design_item'
        public readonly string $sourceId,
        public readonly ?string $assignedTo,
        public readonly string $kindLabel,     // 'Công việc' | 'Hạng mục thiết kế'
        public readonly string $name,
        public readonly ?string $projectId,
        public readonly string $projectName,
        public readonly ?\Illuminate\Support\Carbon $endDate,
        public readonly bool $isOverdue,
        public readonly bool $isBlocked,
        public readonly ?string $blockerNote,
        public readonly ?string $blockedBy,
        public readonly ?string $priority,
        public readonly string $status,
        public readonly string $url,
    ) {}
}
```

---

### Task 1: `OpenWorkItem` DTO and `OpenWorkReadQuery` extraction

**Files:**
- Create: `app/Support/Work/OpenWorkItem.php`
- Create: `app/Services/OpenWorkReadQuery.php`
- Modify: `app/Http/Controllers/Web/WorkloadPageController.php:1-132`
- Modify: `resources/views/app/_workload-items-table.blade.php`
- Test: `tests/Feature/Services/OpenWorkReadQueryTest.php`

**Interfaces:**
- Produces: `App\Support\Work\OpenWorkItem` (readonly DTO, contract above). `App\Services\OpenWorkReadQuery::collect(string $tenantId): \Illuminate\Support\Collection` returning `Collection<int, OpenWorkItem>`. Every later task that needs "open work" (Tasks 3, 6, 7) consumes this exact type.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Services/OpenWorkReadQueryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Services\OpenWorkReadQuery;
use App\Support\Work\OpenWorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenWorkReadQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_collects_open_tasks_and_design_items_for_tenant(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc mở',
            'title' => 'Việc mở',
            'priority' => Task::PRIORITY_HIGH,
            'blocker_note' => 'Chờ vật tư',
            'blocked_by' => 'Nhà cung cấp A',
        ]);
        $designItem = DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_DRAFT,
        ]);

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $taskItem = $items->first(fn (OpenWorkItem $i) => $i->sourceId === (string) $task->id);
        $designItemRow = $items->first(fn (OpenWorkItem $i) => $i->sourceId === (string) $designItem->id);

        $this->assertInstanceOf(OpenWorkItem::class, $taskItem);
        $this->assertSame('task', $taskItem->sourceType);
        $this->assertSame((string) $this->project->id, $taskItem->projectId);
        $this->assertSame('Chờ vật tư', $taskItem->blockerNote);
        $this->assertSame('Nhà cung cấp A', $taskItem->blockedBy);
        $this->assertSame(Task::PRIORITY_HIGH, $taskItem->priority);
        $this->assertSame('design_item', $designItemRow->sourceType);
        $this->assertNull($designItemRow->priority);
    }

    public function test_excludes_closed_tasks_and_approved_design_items(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => Task::STATUS_COMPLETED,
            'name' => 'Việc đã xong',
            'title' => 'Việc đã xong',
        ]);
        DesignItem::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'review_status' => DesignItem::STATUS_FINAL,
        ]);

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $this->assertCount(0, $items);
    }

    public function test_cross_tenant_items_never_returned(): void
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

        $items = (new OpenWorkReadQuery())->collect((string) $this->tenant->id);

        $this->assertTrue($items->first(fn (OpenWorkItem $i) => $i->name === 'Việc tenant khác') === null);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/OpenWorkReadQueryTest.php`
Expected: FAIL — `Class "App\Support\Work\OpenWorkItem" not found`.

- [ ] **Step 3: Create the DTO**

Create `app/Support/Work/OpenWorkItem.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Work;

use Illuminate\Support\Carbon;

/**
 * Một Task hoặc DesignItem đang mở — dùng chung bởi WorkloadPageController
 * (My Work, Workload) và TodayWorkspaceReadService.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.1
 */
final class OpenWorkItem
{
    public function __construct(
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly ?string $assignedTo,
        public readonly string $kindLabel,
        public readonly string $name,
        public readonly ?string $projectId,
        public readonly string $projectName,
        public readonly ?Carbon $endDate,
        public readonly bool $isOverdue,
        public readonly bool $isBlocked,
        public readonly ?string $blockerNote,
        public readonly ?string $blockedBy,
        public readonly ?string $priority,
        public readonly string $status,
        public readonly string $url,
    ) {
    }
}
```

- [ ] **Step 4: Write `OpenWorkReadQuery`**

Create `app/Services/OpenWorkReadQuery.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DesignItem;
use App\Models\Task;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Toàn bộ open work item (Task + DesignItem) của 1 tenant — chưa lọc theo
 * actor, chưa nhóm theo người. Dùng chung bởi WorkloadPageController
 * (My Work, Workload) và TodayWorkspaceReadService.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.1
 */
class OpenWorkReadQuery
{
    /**
     * @return Collection<int, OpenWorkItem>
     */
    public function collect(string $tenantId): Collection
    {
        $today = Carbon::now()->startOfDay();

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD])
            ->with('project:id,tenant_id,name')
            ->get([
                'id', 'tenant_id', 'project_id', 'name', 'title', 'status',
                'assigned_to', 'end_date', 'blocked_at', 'blocker_note', 'blocked_by', 'priority',
            ]);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('review_status', [DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL])
            ->with('project:id,tenant_id,name')
            ->get([
                'id', 'tenant_id', 'project_id', 'name', 'review_status',
                'assigned_to', 'blocked_at', 'blocker_note', 'blocked_by',
            ]);

        $items = collect();

        foreach ($tasks as $task) {
            $isOverdue = $task->end_date !== null
                && Carbon::parse(substr((string) $task->end_date, 0, 10))->startOfDay()->lt($today);

            $items->push(new OpenWorkItem(
                sourceType: 'task',
                sourceId: (string) $task->id,
                assignedTo: $task->assigned_to !== null ? (string) $task->assigned_to : null,
                kindLabel: 'Công việc',
                name: (string) ($task->name ?? $task->title ?? $task->id),
                projectId: $task->project_id !== null ? (string) $task->project_id : null,
                projectName: $task->project?->name ?? '—',
                endDate: $task->end_date,
                isOverdue: $isOverdue,
                isBlocked: $task->blocked_at !== null,
                blockerNote: $task->blocker_note,
                blockedBy: $task->blocked_by,
                priority: $task->priority,
                status: (string) $task->status,
                url: route('app.tasks.show', $task->id),
            ));
        }

        foreach ($designItems as $designItem) {
            $items->push(new OpenWorkItem(
                sourceType: 'design_item',
                sourceId: (string) $designItem->id,
                assignedTo: $designItem->assigned_to !== null ? (string) $designItem->assigned_to : null,
                kindLabel: 'Hạng mục thiết kế',
                name: (string) $designItem->name,
                projectId: $designItem->project_id !== null ? (string) $designItem->project_id : null,
                projectName: $designItem->project?->name ?? '—',
                endDate: null,
                isOverdue: false,
                isBlocked: $designItem->blocked_at !== null,
                blockerNote: $designItem->blocker_note,
                blockedBy: $designItem->blocked_by,
                priority: null,
                status: (string) $designItem->review_status,
                url: route('operator.design-items.show', $designItem->id),
            ));
        }

        return $items;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/OpenWorkReadQueryTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Support/Work/OpenWorkItem.php app/Services/OpenWorkReadQuery.php tests/Feature/Services/OpenWorkReadQueryTest.php
git commit -m "feat(today): add OpenWorkItem DTO and OpenWorkReadQuery"
```

- [ ] **Step 7: Wire `WorkloadPageController` to the extracted query (regression-protected)**

Modify `app/Http/Controllers/Web/WorkloadPageController.php` — replace the whole class body with:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Support\Work\OpenWorkItem;
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
    public function __construct(private readonly OpenWorkReadQuery $openWorkReadQuery)
    {
    }

    public function index(): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $users = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name']);

        $items = $this->openWorkReadQuery->collect($tenantId);

        $grouped = $items->groupBy(fn (OpenWorkItem $i) => $i->assignedTo ?? '__unassigned');

        $blocks = $users
            ->map(function (User $user) use ($grouped): array {
                /** @var Collection<int, OpenWorkItem> $list */
                $list = $grouped->get((string) $user->id, collect())->values();

                return [
                    'user' => $user,
                    'items' => $list,
                    'open_count' => $list->count(),
                    'overdue_count' => $list->where('isOverdue', true)->count(),
                    'blocked_count' => $list->where('isBlocked', true)->count(),
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

        $items = $this->openWorkReadQuery->collect($tenantId)
            ->where('assignedTo', $userId)
            ->values();

        return view('app.my-work', [
            'items' => $items,
            'open_count' => $items->count(),
            'overdue_count' => $items->where('isOverdue', true)->count(),
            'blocked_count' => $items->where('isBlocked', true)->count(),
        ]);
    }
}
```

`Collection::where('isOverdue', true)`/`groupBy(fn (OpenWorkItem $i) => $i->assignedTo ...)` work identically on objects as they did on arrays — Laravel's `Collection::where()` uses `data_get()`, which reads plain object properties by name, so `'isOverdue'` (matching the DTO's public property name) is the correct key string here, not the old array key `'is_overdue'`.

- [ ] **Step 8: Update the Blade partial to read object properties**

Modify `resources/views/app/_workload-items-table.blade.php` — replace array-bracket access with object-property access (rendered HTML text is unchanged):

```blade
<x-ui.data-table :headers="['Việc', 'Dự án', 'Loại', 'Hạn', 'Trạng thái']">
    @foreach ($items as $item)
        <tr>
            <td>
                <a href="{{ $item->url }}" class="operator-link font-medium">{{ $item->name }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $item->projectName }}</td>
            <td class="text-sm text-slate-600">{{ $item->kindLabel }}</td>
            <td class="text-sm text-slate-600">{{ $item->endDate ? $item->endDate->format('d/m/Y') : '—' }}</td>
            <td>
                @if ($item->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item->isBlocked)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item->status" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
```

(`$item->endDate` is already an `Illuminate\Support\Carbon` instance via the `Task` model's `end_date` cast — no `Carbon::parse()` call needed here, unlike the old array version which held the raw cast value the same way; behavior is identical.)

- [ ] **Step 9: Run the existing regression suites unchanged**

Run: `php artisan test tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php`
Expected: PASS — all pre-existing assertions in both files pass with **zero edits** to either test file. If any assertion fails, the extraction changed rendered output; stop and fix `OpenWorkReadQuery`/`WorkloadPageController`/the Blade partial until both test files pass unmodified.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/WorkloadPageController.php resources/views/app/_workload-items-table.blade.php
git commit -m "refactor(workload): delegate open-work collection to OpenWorkReadQuery"
```

---

### Task 2: Today section-result and DTO contracts (reusing existing trust enums)

**Files:**
- Create: `app/Support/Today/TodaySectionResult.php`
- Create: `app/Support/Today/TodayMilestoneItem.php`
- Create: `app/Support/Today/TodayNotificationItem.php`
- Create: `app/Support/Today/TodayTeamMemberSummary.php`
- Create: `app/Support/Today/TodayWorkspaceViewModel.php`
- Test: `tests/Unit/Support/Today/TodaySectionResultTest.php`

**Interfaces:**
- Consumes: `App\Support\Dashboard\Availability` (cases `AVAILABLE`, `NO_DATA`, `NOT_APPLICABLE`, `ERROR` — verified at `app/Support/Dashboard/Availability.php`), `App\Support\Dashboard\Reliability` (cases `RELIABLE`, `LIMITED`, `LEGACY`, `UNKNOWN` — verified at `app/Support/Dashboard/Reliability.php`). **Neither file is modified by this plan.**
- Produces: `TodaySectionResult` (readonly, `items: array`, `availability: Availability`, `reliability: Reliability`, `explanation: ?string`), `TodayMilestoneItem`, `TodayNotificationItem`, `TodayTeamMemberSummary`, `TodayWorkspaceViewModel`. Tasks 4, 5, 6, 7 consume these exact class names and constructor signatures.

**Section-semantics mapping used consistently by every task below:**

| Situation | `Availability` | `Reliability` |
|---|---|---|
| Data present | `AVAILABLE` | `RELIABLE` |
| No qualifying records | `NO_DATA` | `RELIABLE` |
| Section not applicable to this actor | `NOT_APPLICABLE` | `RELIABLE` |
| Optional query failure | `ERROR` | `UNKNOWN` |

(`Reliability::LIMITED` and `Reliability::LEGACY` are not used by any Today section in this MVP — they remain available on the shared enum for other consumers, unchanged.)

Note on `NOT_APPLICABLE` and PM/Team Exceptions: the approved design (§6.6, not reopened by this plan) models "actor has no managed team/project" as `TodayWorkspaceViewModel::$teamException` being `null` — a section that does not exist for this actor, decided before this revision. This plan keeps that nullable-field shape unchanged rather than converting it to a non-null `TodaySectionResult` with `NOT_APPLICABLE`, because changing that structural type would be reopening a design decision, not fixing an implementation gap. The `NOT_APPLICABLE` case is available on the reused enum for any section that needs it (none does, in this MVP).

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/Today/TodaySectionResultTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Support\Today;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use PHPUnit\Framework\TestCase;

class TodaySectionResultTest extends TestCase
{
    public function test_available_result_carries_items_and_no_explanation_required(): void
    {
        $result = new TodaySectionResult(
            items: [['name' => 'Việc A']],
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            explanation: null,
        );

        $this->assertCount(1, $result->items);
        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertNull($result->explanation);
    }

    public function test_error_result_carries_explanation_and_empty_items(): void
    {
        $result = new TodaySectionResult(
            items: [],
            availability: Availability::ERROR,
            reliability: Reliability::UNKNOWN,
            explanation: 'Không thể tải mục này lúc này.',
        );

        $this->assertSame([], $result->items);
        $this->assertSame(Availability::ERROR, $result->availability);
        $this->assertSame('Không thể tải mục này lúc này.', $result->explanation);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Support/Today/TodaySectionResultTest.php`
Expected: FAIL — `Class "App\Support\Today\TodaySectionResult" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Support/Today/TodaySectionResult.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Today;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;

final class TodaySectionResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly ?string $explanation,
    ) {
    }
}
```

Create `app/Support/Today/TodayMilestoneItem.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Today;

use Illuminate\Support\Carbon;

final class TodayMilestoneItem
{
    public function __construct(
        public readonly string $milestoneId,
        public readonly string $name,
        public readonly string $projectId,
        public readonly string $projectName,
        public readonly ?Carbon $targetDate,
        public readonly bool $isOverdue,
        public readonly string $status,
        public readonly string $url,
    ) {
    }
}
```

Create `app/Support/Today/TodayNotificationItem.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Today;

use Illuminate\Support\Carbon;

final class TodayNotificationItem
{
    public function __construct(
        public readonly string $notificationId,
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
        public readonly Carbon $createdAt,
    ) {
    }
}
```

Create `app/Support/Today/TodayTeamMemberSummary.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Today;

final class TodayTeamMemberSummary
{
    public function __construct(
        public readonly string $userId,
        public readonly string $userName,
        public readonly int $openCount,
        public readonly int $overdueCount,
        public readonly int $blockedCount,
    ) {
    }
}
```

Create `app/Support/Today/TodayWorkspaceViewModel.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Today;

final class TodayWorkspaceViewModel
{
    public function __construct(
        public readonly TodaySectionResult $personalOpenWork,
        public readonly TodaySectionResult $inProgress,
        public readonly TodaySectionResult $overdueAndBlocked,
        public readonly TodaySectionResult $upcomingMilestones,
        public readonly TodaySectionResult $unreadUpdates,
        public readonly ?TodaySectionResult $teamException,
    ) {
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Support/Today/TodaySectionResultTest.php`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Support/Today/
git commit -m "feat(today): add TodaySectionResult and Today DTO contracts reusing Dashboard trust enums"
```

---

### Task 3: `TodayWorkspaceReadService` — Personal Open Work, Đang thực hiện, Overdue and Blocked

**Files:**
- Create: `app/Services/TodayWorkspaceReadService.php`
- Test: `tests/Feature/Services/TodayWorkspaceReadServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\OpenWorkReadQuery::collect(string $tenantId): Collection<int, OpenWorkItem>` (Task 1).
- Produces: `App\Services\TodayWorkspaceReadService::personalOpenWork(\Illuminate\Support\Collection $openWork, string $actorId): TodaySectionResult`, `::inProgress(Collection $openWork, string $actorId): TodaySectionResult`, `::overdueAndBlocked(Collection $openWork, string $actorId): TodaySectionResult`. **These three methods take the already-fetched `$openWork` collection as a parameter — they never call `OpenWorkReadQuery` themselves.** Task 7 fetches the collection once and passes it to all three, plus to `TeamExceptionQuery` (Task 6).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Services/TodayWorkspaceReadServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Services\TodayWorkspaceReadService;
use App\Support\Dashboard\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TodayWorkspaceReadServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $actor;
    private Project $project;
    private TodayWorkspaceReadService $service;
    private OpenWorkReadQuery $openWorkReadQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $this->service = app(TodayWorkspaceReadService::class);
        $this->openWorkReadQuery = app(OpenWorkReadQuery::class);
    }

    private function taskFor(User $assignee, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $assignee->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc',
            'title' => 'Việc',
        ], $overrides));
    }

    public function test_personal_open_work_contains_only_assigned_to_actor(): void
    {
        $coworker = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $mine = $this->taskFor($this->actor, ['name' => 'Việc của tôi', 'title' => 'Việc của tôi']);
        $this->taskFor($coworker, ['name' => 'Việc của B', 'title' => 'Việc của B']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->personalOpenWork($openWork, (string) $this->actor->id);

        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertCount(1, $result->items);
        $this->assertSame((string) $mine->id, $result->items[0]->sourceId);
    }

    public function test_in_progress_shows_multiple_tasks_without_marking_one_primary(): void
    {
        $this->taskFor($this->actor, ['status' => Task::STATUS_IN_PROGRESS, 'name' => 'Việc 1', 'title' => 'Việc 1']);
        $this->taskFor($this->actor, ['status' => Task::STATUS_IN_PROGRESS, 'name' => 'Việc 2', 'title' => 'Việc 2']);
        $this->taskFor($this->actor, ['status' => Task::STATUS_PENDING, 'name' => 'Việc chưa bắt đầu', 'title' => 'Việc chưa bắt đầu']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->inProgress($openWork, (string) $this->actor->id);

        $this->assertCount(2, $result->items);
        foreach ($result->items as $item) {
            $this->assertFalse(property_exists($item, 'isPrimary'));
            $this->assertFalse(property_exists($item, 'isCurrent'));
        }
    }

    public function test_overdue_and_blocked_includes_items_matching_either_condition(): void
    {
        $overdue = $this->taskFor($this->actor, [
            'name' => 'Việc trễ', 'title' => 'Việc trễ',
            'end_date' => now()->subDays(2)->toDateString(),
        ]);
        $blocked = $this->taskFor($this->actor, [
            'name' => 'Việc bị chặn', 'title' => 'Việc bị chặn',
            'blocked_at' => now(),
        ]);
        $this->taskFor($this->actor, ['name' => 'Việc bình thường', 'title' => 'Việc bình thường']);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = $this->service->overdueAndBlocked($openWork, (string) $this->actor->id);

        $ids = array_map(fn ($item) => $item->sourceId, $result->items);
        $this->assertContains((string) $overdue->id, $ids);
        $this->assertContains((string) $blocked->id, $ids);
        $this->assertCount(2, $result->items);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/TodayWorkspaceReadServiceTest.php`
Expected: FAIL — `Class "App\Services\TodayWorkspaceReadService" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/TodayWorkspaceReadService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Collection;

/**
 * Orchestration boundary cho trang /app/today.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.2
 */
class TodayWorkspaceReadService
{
    private const PERSONAL_OPEN_WORK_LIMIT = 20;
    private const IN_PROGRESS_LIMIT = 10;
    private const OVERDUE_AND_BLOCKED_LIMIT = 20;

    public function __construct(private readonly OpenWorkReadQuery $openWorkReadQuery)
    {
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function personalOpenWork(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId)
            ->sort(fn (OpenWorkItem $a, OpenWorkItem $b) => $this->sortRank($a, $b))
            ->take(self::PERSONAL_OPEN_WORK_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function inProgress(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId
                && $i->sourceType === 'task'
                && $i->status === Task::STATUS_IN_PROGRESS)
            ->sort(fn (OpenWorkItem $a, OpenWorkItem $b) => $this->sortRank($a, $b))
            ->take(self::IN_PROGRESS_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function overdueAndBlocked(Collection $openWork, string $actorId): TodaySectionResult
    {
        $items = $openWork
            ->filter(fn (OpenWorkItem $i) => $i->assignedTo === $actorId && ($i->isOverdue || $i->isBlocked))
            ->sort(function (OpenWorkItem $a, OpenWorkItem $b) {
                $rankDiff = $this->overdueBlockedRank($b) <=> $this->overdueBlockedRank($a);

                return $rankDiff !== 0 ? $rankDiff : $this->compareEndDate($a, $b);
            })
            ->take(self::OVERDUE_AND_BLOCKED_LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult($items, Availability::AVAILABLE, Reliability::RELIABLE, null);
    }

    private function sortRank(OpenWorkItem $a, OpenWorkItem $b): int
    {
        $overdueDiff = ($b->isOverdue ? 1 : 0) <=> ($a->isOverdue ? 1 : 0);
        if ($overdueDiff !== 0) {
            return $overdueDiff;
        }

        $dateDiff = $this->compareEndDate($a, $b);
        if ($dateDiff !== 0) {
            return $dateDiff;
        }

        $priorityDiff = $this->comparePriority($a, $b);
        if ($priorityDiff !== 0) {
            return $priorityDiff;
        }

        return $a->sourceId <=> $b->sourceId;
    }

    /** overdue+blocked=2, overdue-only=1, blocked-only=0 */
    private function overdueBlockedRank(OpenWorkItem $item): int
    {
        return (int) $item->isOverdue + (int) $item->isBlocked;
    }

    private function compareEndDate(OpenWorkItem $a, OpenWorkItem $b): int
    {
        if ($a->endDate === null && $b->endDate === null) {
            return 0;
        }
        if ($a->endDate === null) {
            return 1;
        }
        if ($b->endDate === null) {
            return -1;
        }

        return $a->endDate <=> $b->endDate;
    }

    private function comparePriority(OpenWorkItem $a, OpenWorkItem $b): int
    {
        $rank = [
            Task::PRIORITY_CRITICAL => 0,
            Task::PRIORITY_HIGH => 1,
            Task::PRIORITY_MEDIUM => 2,
            Task::PRIORITY_LOW => 3,
        ];

        $aRank = $a->priority !== null ? ($rank[$a->priority] ?? 4) : 4;
        $bRank = $b->priority !== null ? ($rank[$b->priority] ?? 4) : 4;

        return $aRank <=> $bRank;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/TodayWorkspaceReadServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/TodayWorkspaceReadService.php tests/Feature/Services/TodayWorkspaceReadServiceTest.php
git commit -m "feat(today): Personal Open Work, Đang thực hiện, Overdue and Blocked composition"
```

---

### Task 4: `UpcomingMilestoneQuery`

**Files:**
- Create: `app/Services/UpcomingMilestoneQuery.php`
- Test: `tests/Feature/Services/UpcomingMilestoneQueryTest.php`

**Interfaces:**
- Produces: `App\Services\UpcomingMilestoneQuery::build(string $tenantId, string $actorId, array $relatedProjectIds): TodaySectionResult` where `$relatedProjectIds` is a `string[]`.

This query never calls `ProjectMilestone::scopeOverdue()` or `->isOverdue()` — both are verified inconsistent with each other (`scopeOverdue()` only matches a stored `status === 'overdue'` set by a `saving` hook that fires only on write; `isOverdue()` additionally requires `status === STATUS_PENDING`, so it returns `false` for a milestone whose status has *already* flipped to `'overdue'`). The query computes live state directly from `target_date`/`completed_date`/`status` and never modifies `app/Models/ProjectMilestone.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Services/UpcomingMilestoneQueryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Services\UpcomingMilestoneQuery;
use App\Support\Today\TodayMilestoneItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingMilestoneQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    private function milestone(array $overrides): ProjectMilestone
    {
        return ProjectMilestone::create(array_merge([
            'project_id' => (string) $this->project->id,
            'name' => 'Milestone',
        ], $overrides));
    }

    public function test_pending_milestone_with_past_target_date_is_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Nghiệm thu điện',
            'target_date' => now()->subDays(3)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertTrue($item->isOverdue);
    }

    public function test_already_overdue_status_milestone_with_past_target_date_is_still_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Nghiệm thu nước',
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ProjectMilestone::STATUS_OVERDUE,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertTrue($item->isOverdue);
    }

    public function test_completed_milestone_with_past_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Đã hoàn thành',
            'target_date' => now()->subDays(10)->toDateString(),
            'completed_date' => now()->subDays(1)->toDateString(),
            'status' => ProjectMilestone::STATUS_COMPLETED,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_cancelled_milestone_with_past_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Đã huỷ',
            'target_date' => now()->subDays(10)->toDateString(),
            'status' => ProjectMilestone::STATUS_CANCELLED,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_future_milestone_is_upcoming_not_overdue(): void
    {
        $m = $this->milestone([
            'name' => 'Bàn giao móng',
            'target_date' => now()->addDays(5)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $item = collect($result->items)->firstWhere('milestoneId', (string) $m->id);
        $this->assertNotNull($item);
        $this->assertFalse($item->isOverdue);
    }

    public function test_null_target_date_is_excluded(): void
    {
        $m = $this->milestone([
            'name' => 'Chưa có ngày',
            'target_date' => null,
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $this->assertNull(collect($result->items)->firstWhere('milestoneId', (string) $m->id));
    }

    public function test_cross_tenant_project_milestone_is_excluded_even_with_matching_project_id_input(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        ProjectMilestone::create([
            'project_id' => (string) $otherProject->id,
            'name' => 'Milestone tenant khác',
            'target_date' => now()->addDays(2)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        // Cố tình truyền project_id của tenant KHÁC — query vẫn phải tự lọc
        // theo tenant thật qua join Project.tenant_id, không tin danh sách đầu vào.
        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $otherProject->id]);

        $this->assertSame([], $result->items);
    }

    public function test_ordering_is_overdue_first_then_nearest_target_date_then_stable_id(): void
    {
        $farOverdue = $this->milestone([
            'name' => 'M-far-overdue', 'target_date' => now()->subDays(20)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $nearOverdue = $this->milestone([
            'name' => 'M-near-overdue', 'target_date' => now()->subDays(1)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $soonUpcoming = $this->milestone([
            'name' => 'M-soon', 'target_date' => now()->addDays(1)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);
        $laterUpcoming = $this->milestone([
            'name' => 'M-later', 'target_date' => now()->addDays(10)->toDateString(), 'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $result = (new UpcomingMilestoneQuery())->build((string) $this->tenant->id, 'actor', [(string) $this->project->id]);

        $ids = array_map(fn (TodayMilestoneItem $i) => $i->milestoneId, $result->items);
        $expectedOverdueFirst = [(string) $nearOverdue->id, (string) $farOverdue->id];
        // cả 2 overdue phải đứng trước cả 2 upcoming; giữa 2 overdue, target_date gần hơn (nearOverdue) đứng trước.
        $this->assertSame(array_slice($ids, 0, 2), $expectedOverdueFirst);
        $this->assertSame(array_slice($ids, 2, 2), [(string) $soonUpcoming->id, (string) $laterUpcoming->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/UpcomingMilestoneQueryTest.php`
Expected: FAIL — `Class "App\Services\UpcomingMilestoneQuery" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/UpcomingMilestoneQuery.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ProjectMilestone;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodayMilestoneItem;
use App\Support\Today\TodaySectionResult;
use Carbon\Carbon;

/**
 * Milestone overdue/sắp tới của các project actor có work hoặc là PM.
 * ProjectMilestone KHÔNG có tenant_id/TenantScope — tenant isolation bắt
 * buộc đi qua join Project.tenant_id. Không dùng scopeOverdue()/isOverdue()
 * của model (cả hai không nhất quán với nhau) — tính "overdue" trực tiếp
 * từ target_date/completed_date tại đây.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.4
 */
class UpcomingMilestoneQuery
{
    private const UPCOMING_WINDOW_DAYS = 30;
    private const LIMIT = 10;

    /**
     * @param string[] $relatedProjectIds
     */
    public function build(string $tenantId, string $actorId, array $relatedProjectIds): TodaySectionResult
    {
        if ($relatedProjectIds === []) {
            return new TodaySectionResult([], Availability::NO_DATA, Reliability::RELIABLE, null);
        }

        $today = Carbon::now()->startOfDay();
        $windowEnd = $today->copy()->addDays(self::UPCOMING_WINDOW_DAYS);

        $milestones = ProjectMilestone::query()
            ->whereIn('project_id', $relatedProjectIds)
            ->whereHas('project', fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereNotIn('status', [ProjectMilestone::STATUS_CANCELLED, ProjectMilestone::STATUS_COMPLETED])
            ->whereNotNull('target_date')
            ->with('project:id,tenant_id,name')
            ->get();

        $items = $milestones
            ->filter(function (ProjectMilestone $milestone) use ($today, $windowEnd) {
                $isLiveOverdue = $milestone->target_date->lt($today) && $milestone->completed_date === null;
                $isUpcoming = $milestone->target_date->between($today, $windowEnd);

                return $isLiveOverdue || $isUpcoming;
            })
            ->map(function (ProjectMilestone $milestone) use ($today) {
                $isOverdue = $milestone->target_date->lt($today) && $milestone->completed_date === null;

                return new TodayMilestoneItem(
                    milestoneId: (string) $milestone->id,
                    name: $milestone->name,
                    projectId: (string) $milestone->project_id,
                    projectName: $milestone->project?->name ?? '—',
                    targetDate: $milestone->target_date,
                    isOverdue: $isOverdue,
                    status: $milestone->status,
                    url: route('app.projects.show', $milestone->project_id),
                );
            })
            ->sort(function (TodayMilestoneItem $a, TodayMilestoneItem $b) {
                $overdueDiff = ($b->isOverdue ? 1 : 0) <=> ($a->isOverdue ? 1 : 0);
                if ($overdueDiff !== 0) {
                    return $overdueDiff;
                }

                $dateDiff = $a->targetDate <=> $b->targetDate;
                if ($dateDiff !== 0) {
                    return $dateDiff;
                }

                return $a->milestoneId <=> $b->milestoneId;
            })
            ->take(self::LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/UpcomingMilestoneQueryTest.php`
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/UpcomingMilestoneQuery.php tests/Feature/Services/UpcomingMilestoneQueryTest.php
git commit -m "feat(today): add UpcomingMilestoneQuery with live overdue state and explicit tenant join"
```

---

### Task 5: `UnreadUpdateQuery`

**Files:**
- Create: `app/Services/UnreadUpdateQuery.php`
- Test: `tests/Feature/Services/UnreadUpdateQueryTest.php`

**Interfaces:**
- Produces: `App\Services\UnreadUpdateQuery::build(string $tenantId, string $actorId): TodaySectionResult` containing `TodayNotificationItem[]`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Services/UnreadUpdateQueryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UnreadUpdateQuery;
use App\Support\Dashboard\Availability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnreadUpdateQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_returns_only_actors_unread_notifications(): void
    {
        $coworker = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'title' => 'Thông báo của tôi',
            'read_at' => null,
        ]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'title' => 'Đã đọc rồi',
            'read_at' => now(),
        ]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $coworker->id,
            'title' => 'Của người khác',
            'read_at' => null,
        ]);

        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertCount(1, $result->items);
        $this->assertSame('Thông báo của tôi', $result->items[0]->title);
    }

    public function test_does_not_mutate_read_at_by_rendering(): void
    {
        $notification = Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->actor->id,
            'read_at' => null,
        ]);

        (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_cross_tenant_notifications_never_returned(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Notification::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'user_id' => (string) $otherUser->id,
            'title' => 'Tenant khác',
            'read_at' => null,
        ]);

        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame([], $result->items);
    }

    public function test_empty_state_when_no_unread(): void
    {
        $result = (new UnreadUpdateQuery())->build((string) $this->tenant->id, (string) $this->actor->id);

        $this->assertSame(Availability::NO_DATA, $result->availability);
        $this->assertSame([], $result->items);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/UnreadUpdateQueryTest.php`
Expected: FAIL — `Class "App\Services\UnreadUpdateQuery" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/UnreadUpdateQuery.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodayNotificationItem;
use App\Support\Today\TodaySectionResult;

/**
 * Notification chưa đọc của actor — không tự động là Action Required
 * (không có Action Required nào ở MVP).
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.5
 */
class UnreadUpdateQuery
{
    private const LIMIT = 10;

    public function build(string $tenantId, string $actorId): TodaySectionResult
    {
        $notifications = Notification::query()
            ->where('tenant_id', $tenantId)
            ->forUser($actorId)
            ->unread()
            ->orderByDesc('created_at')
            ->limit(self::LIMIT)
            ->get();

        $items = $notifications
            ->map(fn (Notification $n) => new TodayNotificationItem(
                notificationId: (string) $n->id,
                title: $n->title,
                body: $n->body,
                url: $n->link_url,
                createdAt: $n->created_at,
            ))
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/UnreadUpdateQueryTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/UnreadUpdateQuery.php tests/Feature/Services/UnreadUpdateQueryTest.php
git commit -m "feat(today): add UnreadUpdateQuery"
```

---

### Task 6: `TeamExceptionQuery`

**Files:**
- Create: `app/Services/TeamExceptionQuery.php`
- Test: `tests/Feature/Services/TeamExceptionQueryTest.php`

**Interfaces:**
- Consumes: an already-fetched `Collection<int, OpenWorkItem>` (Task 1) — **this class does not depend on `OpenWorkReadQuery` and never calls `collect()` itself**, matching Task 3's pattern.
- Produces: `App\Services\TeamExceptionQuery::build(string $tenantId, string $actorId, \Illuminate\Support\Collection $openWork): ?TodaySectionResult` containing `TodayTeamMemberSummary[]`, or `null` when the actor manages nothing.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Services/TeamExceptionQueryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OpenWorkReadQuery;
use App\Services\TeamExceptionQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamExceptionQueryTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private OpenWorkReadQuery $openWorkReadQuery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->openWorkReadQuery = app(OpenWorkReadQuery::class);
    }

    public function test_null_for_actor_with_no_managed_project_or_team(): void
    {
        $actor = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);

        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $actor->id, $openWork);

        $this->assertNull($result);
    }

    public function test_summarizes_open_work_for_pm_owned_project_members(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên A']);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Việc thành viên',
            'title' => 'Việc thành viên',
        ]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);

        $this->assertNotNull($result);
        $summary = collect($result->items)->firstWhere('userId', (string) $member->id);
        $this->assertSame(1, $summary->openCount);
    }

    public function test_summarizes_open_work_for_team_lead_members(): void
    {
        $lead = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên B']);
        $team = Team::factory()->create(['tenant_id' => (string) $this->tenant->id, 'team_lead_id' => (string) $lead->id]);
        $team->members()->attach($member->id, ['role' => 'member', 'joined_at' => now()]);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc team',
            'title' => 'Việc team',
        ]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $lead->id, $openWork);

        $this->assertNotNull($result);
        $summary = collect($result->items)->firstWhere('userId', (string) $member->id);
        $this->assertSame(1, $summary->openCount);
    }

    public function test_never_computes_availability_or_capacity_percentage(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);

        foreach ($result->items ?? [] as $summary) {
            $this->assertFalse(property_exists($summary, 'availabilityPercent'));
            $this->assertFalse(property_exists($summary, 'capacityPercent'));
        }
    }

    public function test_member_name_lookup_is_bounded_not_per_member(): void
    {
        $pm = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $pm->id]);
        // 5 thành viên khác nhau, không thuộc team nào (activeMembers không phủ được
        // tên của họ) — TeamExceptionQuery phải tra tên bằng 1 whereIn(), không phải
        // 1 find() cho mỗi người.
        $members = User::factory()->count(5)->create(['tenant_id' => (string) $this->tenant->id]);
        foreach ($members as $i => $member) {
            Task::factory()->create([
                'tenant_id' => (string) $this->tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $member->id,
                'status' => Task::STATUS_PENDING,
                'name' => "Việc {$i}", 'title' => "Việc {$i}",
            ]);
        }

        $openWork = $this->openWorkReadQuery->collect((string) $this->tenant->id);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $result = (new TeamExceptionQuery())->build((string) $this->tenant->id, (string) $pm->id, $openWork);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertNotNull($result);
        $this->assertCount(5, $result->items);
        // Project lookup (1) + Team lookup (1, empty) + 1 bounded name lookup = 3.
        // Không được tăng theo số thành viên (nếu là N+1, con số này sẽ là 3 + 5 = 8).
        $this->assertLessThanOrEqual(3, $queryCount);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/TeamExceptionQueryTest.php`
Expected: FAIL — `Class "App\Services\TeamExceptionQuery" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Services/TeamExceptionQuery.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use App\Support\Today\TodayTeamMemberSummary;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Collection;

/**
 * "Khối lượng công việc đã ghi nhận" — không phải capacity/availability.
 * Chỉ hiển thị cho actor là PM (Project.pm_id) hoặc team lead (Team.team_lead_id).
 * Nhận $openWork đã fetch sẵn — không tự gọi OpenWorkReadQuery::collect()
 * (tránh gọi lại cùng 2 truy vấn nhiều lần trong 1 request Today).
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.6
 */
class TeamExceptionQuery
{
    private const LIMIT = 10;

    /**
     * @param Collection<int, OpenWorkItem> $openWork
     */
    public function build(string $tenantId, string $actorId, Collection $openWork): ?TodaySectionResult
    {
        $managedProjectIds = Project::query()
            ->where('tenant_id', $tenantId)
            ->where('pm_id', $actorId)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $ledTeams = Team::query()
            ->where('tenant_id', $tenantId)
            ->where('team_lead_id', $actorId)
            ->with('activeMembers:id,name')
            ->get();

        if ($managedProjectIds === [] && $ledTeams->isEmpty()) {
            return null;
        }

        $memberIds = $ledTeams
            ->flatMap(fn (Team $team) => $team->activeMembers->pluck('id'))
            ->map(fn ($id) => (string) $id)
            ->unique();

        $memberNames = $ledTeams
            ->flatMap(fn (Team $team) => $team->activeMembers)
            ->unique('id')
            ->pluck('name', 'id');

        $relevant = $openWork
            ->filter(function (OpenWorkItem $item) use ($managedProjectIds, $memberIds) {
                $inManagedProject = $item->projectId !== null && in_array($item->projectId, $managedProjectIds, true);
                $isTeamMember = $item->assignedTo !== null && $memberIds->contains($item->assignedTo);

                return $inManagedProject || $isTeamMember;
            })
            ->filter(fn (OpenWorkItem $item) => $item->assignedTo !== null);

        $grouped = $relevant->groupBy(fn (OpenWorkItem $item) => $item->assignedTo);

        // Tra tên 1 lần cho mọi assignedTo chưa có tên từ activeMembers — không
        // gọi User::find() bên trong vòng lặp/map() (tránh N+1 theo số thành viên).
        $missingNameIds = $grouped->keys()->diff($memberNames->keys())->values()->all();
        $fetchedNames = $missingNameIds === []
            ? collect()
            : User::query()->whereIn('id', $missingNameIds)->pluck('name', 'id');
        $allNames = $memberNames->union($fetchedNames);

        $items = $grouped
            ->map(fn (Collection $group, string $userId) => new TodayTeamMemberSummary(
                userId: $userId,
                userName: $allNames->get($userId) ?? '—',
                openCount: $group->count(),
                overdueCount: $group->where('isOverdue', true)->count(),
                blockedCount: $group->where('isBlocked', true)->count(),
            ))
            ->sortByDesc('openCount')
            ->take(self::LIMIT)
            ->values()
            ->all();

        return new TodaySectionResult(
            $items,
            $items === [] ? Availability::NO_DATA : Availability::AVAILABLE,
            Reliability::RELIABLE,
            null,
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/TeamExceptionQueryTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/TeamExceptionQuery.php tests/Feature/Services/TeamExceptionQueryTest.php
git commit -m "feat(today): add TeamExceptionQuery with bounded member-name lookup"
```

---

### Task 7: Orchestration — `TodayWorkspaceReadService::build()` with per-section degradation

**Files:**
- Modify: `app/Services/TodayWorkspaceReadService.php` (from Task 3)
- Test: `tests/Feature/Services/TodayWorkspaceReadServiceTest.php` (extend from Task 3)

**Interfaces:**
- Consumes: `UpcomingMilestoneQuery::build()` (Task 4), `UnreadUpdateQuery::build()` (Task 5), `TeamExceptionQuery::build(string, string, Collection)` (Task 6).
- Produces: `App\Services\TodayWorkspaceReadService::build(\App\Models\User $actor): \App\Support\Today\TodayWorkspaceViewModel`. This is the **only** `OpenWorkReadQuery::collect()` call site for the entire Today page — Task 8's `TodayController` calls only `build()`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/Services/TodayWorkspaceReadServiceTest.php` (inside the existing class, after the last test method):

```php
    public function test_build_returns_complete_view_model_with_single_open_work_fetch(): void
    {
        $this->taskFor($this->actor, ['name' => 'Việc build', 'title' => 'Việc build']);

        \Illuminate\Support\Facades\DB::flushQueryLog();
        \Illuminate\Support\Facades\DB::enableQueryLog();
        $viewModel = $this->service->build($this->actor);
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $this->assertInstanceOf(\App\Support\Today\TodayWorkspaceViewModel::class, $viewModel);
        $this->assertCount(1, $viewModel->personalOpenWork->items);
        $this->assertNull($viewModel->teamException);

        // OpenWorkReadQuery::collect() chạy đúng 1 lần (Task + eager-load project,
        // DesignItem + eager-load project = 4 query) — không phải 3-4 lần cho
        // personalOpenWork/inProgress/overdueAndBlocked/teamException riêng rẽ.
        $openWorkQueries = collect($queries)->filter(
            fn (array $q) => str_contains($q['query'], 'from "tasks"') || str_contains($q['query'], 'from "design_items"')
        );
        $this->assertLessThanOrEqual(4, $openWorkQueries->count());
    }

    public function test_one_section_failure_does_not_break_other_sections(): void
    {
        $this->taskFor($this->actor, ['name' => 'Việc sống sót', 'title' => 'Việc sống sót']);

        $failingService = new class(app(OpenWorkReadQuery::class)) extends TodayWorkspaceReadService {
            public function upcomingMilestones(string $tenantId, string $actorId, array $projectIds): \App\Support\Today\TodaySectionResult
            {
                throw new \RuntimeException('boom');
            }
        };

        $viewModel = $failingService->build($this->actor);

        $this->assertSame(\App\Support\Dashboard\Availability::ERROR, $viewModel->upcomingMilestones->availability);
        $this->assertCount(1, $viewModel->personalOpenWork->items);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/TodayWorkspaceReadServiceTest.php`
Expected: FAIL — `Call to undefined method App\Services\TodayWorkspaceReadService::build()`.

- [ ] **Step 3: Write minimal implementation**

Modify `app/Services/TodayWorkspaceReadService.php` — change the constructor and add the orchestration methods:

```php
    public function __construct(
        private readonly OpenWorkReadQuery $openWorkReadQuery,
        private readonly UpcomingMilestoneQuery $upcomingMilestoneQuery,
        private readonly UnreadUpdateQuery $unreadUpdateQuery,
        private readonly TeamExceptionQuery $teamExceptionQuery,
    ) {
    }

    public function build(\App\Models\User $actor): \App\Support\Today\TodayWorkspaceViewModel
    {
        $tenantId = (string) $actor->tenant_id;
        $actorId = (string) $actor->id;

        [$openWork, $openWorkFailed] = $this->loadOpenWork($tenantId);

        $errorResult = new \App\Support\Today\TodaySectionResult(
            [],
            \App\Support\Dashboard\Availability::ERROR,
            \App\Support\Dashboard\Reliability::UNKNOWN,
            'Không thể tải mục này lúc này.',
        );

        $personalOpenWork = $openWorkFailed ? $errorResult : $this->safeSection(fn () => $this->personalOpenWork($openWork, $actorId));
        $inProgress = $openWorkFailed ? $errorResult : $this->safeSection(fn () => $this->inProgress($openWork, $actorId));
        $overdueAndBlocked = $openWorkFailed ? $errorResult : $this->safeSection(fn () => $this->overdueAndBlocked($openWork, $actorId));

        $relatedProjectIds = $openWorkFailed
            ? []
            : collect($personalOpenWork->items)
                ->pluck('projectId')
                ->filter()
                ->unique()
                ->values()
                ->all();

        $upcomingMilestones = $this->safeSection(
            fn () => $this->upcomingMilestones($tenantId, $actorId, $relatedProjectIds)
        );
        $unreadUpdates = $this->safeSection(fn () => $this->unreadUpdateQuery->build($tenantId, $actorId));
        $teamException = $this->safeTeamException($tenantId, $actorId, $openWork);

        return new \App\Support\Today\TodayWorkspaceViewModel(
            personalOpenWork: $personalOpenWork,
            inProgress: $inProgress,
            overdueAndBlocked: $overdueAndBlocked,
            upcomingMilestones: $upcomingMilestones,
            unreadUpdates: $unreadUpdates,
            teamException: $teamException,
        );
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, \App\Support\Work\OpenWorkItem>, 1: bool}
     */
    private function loadOpenWork(string $tenantId): array
    {
        try {
            return [$this->openWorkReadQuery->collect($tenantId), false];
        } catch (\Throwable $e) {
            report($e);

            return [collect(), true];
        }
    }

    /**
     * @param string[] $relatedProjectIds
     */
    public function upcomingMilestones(string $tenantId, string $actorId, array $relatedProjectIds): \App\Support\Today\TodaySectionResult
    {
        return $this->upcomingMilestoneQuery->build($tenantId, $actorId, $relatedProjectIds);
    }

    private function safeSection(\Closure $build): \App\Support\Today\TodaySectionResult
    {
        try {
            return $build();
        } catch (\Throwable $e) {
            report($e);

            return new \App\Support\Today\TodaySectionResult(
                [],
                \App\Support\Dashboard\Availability::ERROR,
                \App\Support\Dashboard\Reliability::UNKNOWN,
                'Không thể tải mục này lúc này.',
            );
        }
    }

    private function safeTeamException(string $tenantId, string $actorId, \Illuminate\Support\Collection $openWork): ?\App\Support\Today\TodaySectionResult
    {
        try {
            return $this->teamExceptionQuery->build($tenantId, $actorId, $openWork);
        } catch (\Throwable $e) {
            report($e);

            return new \App\Support\Today\TodaySectionResult(
                [],
                \App\Support\Dashboard\Availability::ERROR,
                \App\Support\Dashboard\Reliability::UNKNOWN,
                'Không thể tải mục này lúc này.',
            );
        }
    }
```

Note: `upcomingMilestones()` is `public` (not `private`) specifically so the RED-step test above can subclass `TodayWorkspaceReadService` and override it to simulate one section failing — the same pattern used successfully in rev 1 of this plan, matching the try/catch-per-metric approach documented in `docs/superpowers/specs/2026-07-25-dashboard-data-trust-guardrails-design.md` §8.2.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/TodayWorkspaceReadServiceTest.php`
Expected: PASS (7 tests total).

- [ ] **Step 5: Run the full Services regression group**

Run: `php artisan test tests/Feature/Services/`
Expected: PASS — `OpenWorkReadQueryTest`, `TodayWorkspaceReadServiceTest`, `UpcomingMilestoneQueryTest`, `UnreadUpdateQueryTest`, `TeamExceptionQueryTest` all green.

- [ ] **Step 6: Commit**

```bash
git add app/Services/TodayWorkspaceReadService.php tests/Feature/Services/TodayWorkspaceReadServiceTest.php
git commit -m "feat(today): orchestrate Today sections with a single open-work fetch and per-section degradation"
```

---

### Task 8: `TodayController`, route, and Blade UI

**Files:**
- Create: `app/Http/Controllers/Web/TodayController.php`
- Create: `resources/views/app/today.blade.php`
- Create: `resources/views/app/_today-open-work-table.blade.php`
- Create: `resources/views/app/_today-milestones-table.blade.php`
- Create: `resources/views/app/_today-notifications-list.blade.php`
- Create: `resources/views/app/_today-team-exceptions.blade.php`
- Modify: `routes/web.php` (inside the `app.` group, next to the other task routes near line 391)
- Test: `tests/Feature/TodayPageTest.php`

**Interfaces:**
- Consumes: `App\Services\TodayWorkspaceReadService::build(User $actor): TodayWorkspaceViewModel` (Task 7).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/TodayPageTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayPageTest extends TestCase
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
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_personal_open_work_and_in_progress_sections_render(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'name' => 'Dựng mặt bằng tầng 1',
            'title' => 'Dựng mặt bằng tầng 1',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Hôm nay');
        $response->assertSee('Dựng mặt bằng tầng 1');
    }

    public function test_requires_task_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.today'))->assertStatus(302);
    }

    public function test_cross_tenant_items_never_render(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        Task::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $otherProject->id,
            'status' => Task::STATUS_IN_PROGRESS,
            'assigned_to' => (string) $this->viewer->id,
            'name' => 'Việc tenant khác',
            'title' => 'Việc tenant khác',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Việc tenant khác');
    }

    public function test_upcoming_milestone_renders_for_project_with_open_work(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc dự án',
            'title' => 'Việc dự án',
        ]);
        ProjectMilestone::create([
            'project_id' => (string) $this->project->id,
            'name' => 'Bàn giao móng',
            'target_date' => now()->addDays(3)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Bàn giao móng');
    }

    public function test_unread_notification_renders_and_is_not_labeled_action_required(): void
    {
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'user_id' => (string) $this->viewer->id,
            'title' => 'Có tài liệu mới',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Có tài liệu mới');
    }

    public function test_no_action_required_section_rendered(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Action Required');
        $response->assertDontSee('Cần hành động');
        $response->assertDontSeeText('actionRequired');
    }

    public function test_project_progress_percentage_absent(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('progress_percent');
        $response->assertDontSee('overall_progress');
        $response->assertDontSee('completion_rate');
    }

    public function test_financial_data_absent(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('budget_actual');
    }

    public function test_employee_cannot_see_pm_sections(): void
    {
        Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) User::factory()->create(['tenant_id' => (string) $this->tenant->id])->id]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Khối lượng công việc đã ghi nhận');
    }

    public function test_pm_sees_team_exception_section(): void
    {
        $member = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Thành viên PM']);
        $pmProject = Project::factory()->create(['tenant_id' => (string) $this->tenant->id, 'pm_id' => (string) $this->viewer->id]);
        Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $pmProject->id,
            'assigned_to' => (string) $member->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc thành viên PM',
            'title' => 'Việc thành viên PM',
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Khối lượng công việc đã ghi nhận');
        $response->assertSee('Thành viên PM');
    }

    public function test_empty_state_when_no_open_items(): void
    {
        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertSee('Bạn chưa có việc nào đang mở');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/TodayPageTest.php`
Expected: FAIL — `Route [app.today] not defined`.

- [ ] **Step 3: Add the route**

Modify `routes/web.php` — inside the `app.` group, immediately after the `my-work.index` line and before `Route::get('/tasks/create', ...)`:

```php
    Route::get('/today', [App\Http\Controllers\Web\TodayController::class, 'index'])->middleware('rbac:task.view')->name('today');
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/Web/TodayController.php`:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TodayWorkspaceReadService;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * "Hôm nay" — trang tổng hợp read-only theo vai trò.
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md
 */
class TodayController extends Controller
{
    public function __construct(private readonly TodayWorkspaceReadService $todayWorkspaceReadService)
    {
    }

    public function index(): View
    {
        $actor = Auth::user();

        $workspace = $this->todayWorkspaceReadService->build($actor);

        return view('app.today', ['workspace' => $workspace]);
    }
}
```

- [ ] **Step 5: Create the Blade view and partials**

Before writing `today.blade.php`, read the first line of `resources/views/app/my-work.blade.php` (`@extends('layouts.operator')` — already confirmed in this planning session) and copy that exact `@extends`/`@section` structure, so `today.blade.php` inherits the identical layout shell as every other `/app/*` page.

Create `resources/views/app/_today-open-work-table.blade.php` (object-property version, consistent with Task 1's updated `_workload-items-table.blade.php`):

```blade
<x-ui.data-table :headers="['Việc', 'Dự án', 'Loại', 'Hạn', 'Trạng thái']">
    @foreach ($items as $item)
        <tr>
            <td>
                <a href="{{ $item->url }}" class="operator-link font-medium">{{ $item->name }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $item->projectName }}</td>
            <td class="text-sm text-slate-600">{{ $item->kindLabel }}</td>
            <td class="text-sm text-slate-600">{{ $item->endDate ? $item->endDate->format('d/m/Y') : '—' }}</td>
            <td>
                @if ($item->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @elseif ($item->isBlocked)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-700">Bị chặn</span>
                @else
                    <x-ui.status-badge :status="$item->status" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
```

Create `resources/views/app/_today-milestones-table.blade.php`:

```blade
<x-ui.data-table :headers="['Milestone', 'Dự án', 'Ngày mục tiêu', 'Trạng thái']">
    @foreach ($items as $milestone)
        <tr>
            <td class="font-medium">{{ $milestone->name }}</td>
            <td>
                <a href="{{ $milestone->url }}" class="operator-link">{{ $milestone->projectName }}</a>
            </td>
            <td class="text-sm text-slate-600">{{ $milestone->targetDate?->format('d/m/Y') ?? '—' }}</td>
            <td>
                @if ($milestone->isOverdue)
                    <span class="rounded bg-rose-100 px-1.5 py-0.5 text-xs font-medium text-rose-700">Quá hạn</span>
                @else
                    <x-ui.status-badge :status="$milestone->status" />
                @endif
            </td>
        </tr>
    @endforeach
</x-ui.data-table>
```

Create `resources/views/app/_today-notifications-list.blade.php`:

```blade
<ul class="space-y-2">
    @foreach ($items as $notification)
        <li class="text-sm">
            @if ($notification->url)
                <a href="{{ $notification->url }}" class="operator-link font-medium">{{ $notification->title }}</a>
            @else
                <span class="font-medium">{{ $notification->title }}</span>
            @endif
            <span class="text-slate-500"> — {{ $notification->createdAt->format('d/m/Y H:i') }}</span>
        </li>
    @endforeach
</ul>
```

Create `resources/views/app/_today-team-exceptions.blade.php`:

```blade
<h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
<x-ui.data-table :headers="['Thành viên', 'Đang mở', 'Quá hạn', 'Bị chặn']">
    @foreach ($items as $summary)
        <tr>
            <td class="font-medium">{{ $summary->userName }}</td>
            <td>{{ $summary->openCount }}</td>
            <td>{{ $summary->overdueCount }}</td>
            <td>{{ $summary->blockedCount }}</td>
        </tr>
    @endforeach
</x-ui.data-table>
<p class="mt-2 text-xs text-slate-500">Đây là số việc đã ghi nhận, không phải năng lực hay mức độ sẵn sàng.</p>
```

Create `resources/views/app/today.blade.php` (same `@extends`/`@section('content')` shell as `my-work.blade.php`):

```blade
@extends('layouts.operator')

@section('title', 'Hôm nay')
@section('page_title', 'Hôm nay')

@section('content')
    <section>
        <h2 class="text-base font-semibold">Việc của tôi</h2>
        @if ($workspace->personalOpenWork->items === [])
            @if ($workspace->personalOpenWork->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Bạn chưa có việc nào đang mở.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->personalOpenWork->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Đang thực hiện</h2>
        @if ($workspace->inProgress->items === [])
            @if ($workspace->inProgress->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Bạn chưa có việc nào đang thực hiện.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->inProgress->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Quá hạn và bị chặn</h2>
        @if ($workspace->overdueAndBlocked->items === [])
            @if ($workspace->overdueAndBlocked->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có việc nào quá hạn hoặc bị chặn.</p>
            @endif
        @else
            @include('app._today-open-work-table', ['items' => $workspace->overdueAndBlocked->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Milestone sắp tới</h2>
        @if ($workspace->upcomingMilestones->items === [])
            @if ($workspace->upcomingMilestones->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có milestone nào sắp tới hoặc trễ cho các dự án bạn tham gia.</p>
            @endif
        @else
            @include('app._today-milestones-table', ['items' => $workspace->upcomingMilestones->items])
        @endif
    </section>

    <section class="mt-6">
        <h2 class="text-base font-semibold">Thông báo chưa đọc</h2>
        @if ($workspace->unreadUpdates->items === [])
            @if ($workspace->unreadUpdates->availability === \App\Support\Dashboard\Availability::ERROR)
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @else
                <p class="text-sm text-slate-500">Không có thông báo chưa đọc.</p>
            @endif
        @else
            @include('app._today-notifications-list', ['items' => $workspace->unreadUpdates->items])
        @endif
    </section>

    @if ($workspace->teamException !== null)
        <section class="mt-6">
            @if ($workspace->teamException->availability === \App\Support\Dashboard\Availability::ERROR)
                <h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
                <p class="text-sm text-rose-600">Không thể tải mục này lúc này.</p>
            @elseif ($workspace->teamException->items === [])
                <h3 class="mb-2 mt-6 text-base font-semibold">Khối lượng công việc đã ghi nhận</h3>
                <p class="text-sm text-slate-500">Không có thành viên nào có việc đang mở.</p>
            @else
                @include('app._today-team-exceptions', ['items' => $workspace->teamException->items])
            @endif
        </section>
    @endif
@endsection
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Feature/TodayPageTest.php`
Expected: PASS (11 tests).

- [ ] **Step 7: Run adjacent regression tests**

Run: `php artisan test tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php tests/Feature/Services/`
Expected: PASS — no regressions from adding the new route/controller/views.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/TodayController.php resources/views/app/today.blade.php resources/views/app/_today-open-work-table.blade.php resources/views/app/_today-milestones-table.blade.php resources/views/app/_today-notifications-list.blade.php resources/views/app/_today-team-exceptions.blade.php routes/web.php tests/Feature/TodayPageTest.php
git commit -m "feat(today): add /app/today route, controller, and Blade UI"
```

---

### Task 9: Permission-aware operator navigation composer

**Files:**
- Create: `app/Support/Navigation/OperatorNavItem.php`
- Create: `app/Support/Navigation/NavAuthorizationRequirement.php`
- Create: `app/Support/Navigation/OperatorNavigationDefinition.php`
- Create: `app/Support/Navigation/OperatorNavigationComposer.php`
- Create: `resources/views/components/operator-nav-icon.blade.php`
- Modify: `resources/views/layouts/operator.blade.php:1-179`
- Test: `tests/Feature/OperatorNavigationTest.php`

**Interfaces:**
- Produces: `App\Support\Navigation\OperatorNavItem` (readonly: `label`, `routeName`, `section`, `iconKey`), `App\Support\Navigation\NavAuthorizationRequirement` (readonly value object with named constructors `rbac(array $permissions)`, `can(string $ability, string $subjectClass)`, `baseline()`, `unresolvable()`), `App\Support\Navigation\OperatorNavigationComposer::resolveAuthorization(string $routeName): NavAuthorizationRequirement`, `::loadPermissionNames(\App\Models\User $actor): array` (the one and only place the actor's permission set is read from the database, evaluated once per request), `::visibleFromItems(array $items, \App\Models\User $actor): array` (testable seam accepting an arbitrary item list), `::visibleFor(\App\Models\User $actor): array` (delegates to `visibleFromItems(OperatorNavigationDefinition::items(), $actor)`).

**Verified authorization middleware inventory across all 27 current sidebar destinations plus the new `app.today`** (captured via `Route::getRoutes()->getByName($name)->gatherMiddleware()` in this planning session — not assumed):

| Route name | Middleware (beyond `web`) | Requirement type |
|---|---|---|
| `app.today` (new, Task 8) | `auth,tenant.isolation,rbac:task.view` | `rbac` |
| `app.dashboard` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `operator.activity-feed.index` | `auth,tenant.isolation,rbac:event-record.view` | `rbac` |
| `operator.schedule.index` | `auth,tenant.isolation,rbac:task.view` | `rbac` |
| `operator.crm.index` | `auth,tenant.isolation,rbac:crm.view` | `rbac` |
| `operator.crm.reports` | `auth,tenant.isolation,rbac:crm.view` | `rbac` |
| `operator.material-requests.index` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `operator.receipts.index` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `operator.materials.index` | `auth,tenant.isolation,rbac:material.view` | `rbac` |
| `operator.vendors.index` | `auth,tenant.isolation,rbac:vendor.view` | `rbac` |
| `operator.boqs.index` | `auth,tenant.isolation,rbac:boq.view` | `rbac` |
| `operator.contracts.index` | `auth,tenant.isolation,rbac:contract.view` | `rbac` |
| `app.projects` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `app.tasks` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `app.workload.index` | `auth,tenant.isolation,rbac:task.view` | `rbac` |
| `app.my-work.index` | `auth,tenant.isolation,rbac:task.view` | `rbac` |
| `operator.design-items.index` | `auth,tenant.isolation,rbac:design-item.view` | `rbac` |
| `app.calendar` | `auth,tenant.isolation` | zero-auth, **verified baseline** |
| `app.team.index` | `auth,tenant.isolation,can:viewAny,App\Models\Team` | **`can`** — verified list-route (URI `/team` has no route parameters), evaluates `TeamPolicy::viewAny()`, which requires `team.view` |
| `operator.site-diaries.index` | `auth,tenant.isolation,rbac:site_diary.view` | `rbac` |
| `operator.inspections.index` | `auth,tenant.isolation,rbac:inspection.view` | `rbac` |
| `operator.knowledge.index` | `auth,tenant.isolation,rbac:knowledge.view` | `rbac` |
| `operator.rfis.index` | `auth,tenant.isolation,rbac:rfi.view` | `rbac` |
| `operator.submittals.index` | `auth,tenant.isolation,rbac:submittal.view` | `rbac` |
| `operator.change-requests.index` | `auth,tenant.isolation,rbac:change-request.view` | `rbac` |
| `operator.reports.index` | `auth,tenant.isolation,rbac:report.view` | `rbac` |
| `operator.webhooks.index` | `auth,tenant.isolation,rbac:webhook.view` | `rbac` |
| `operator.api-tokens.index` | `auth,tenant.isolation` | zero-auth, **verified baseline** |

No route among these 28 uses `ability:*` or a dedicated role-middleware alias — `ability:*` exists in the codebase only inside commented-out routes (`routes/web.php:352-354`), never live. This plan therefore recognizes exactly `rbac:*` and `can:*` as concrete authorization-middleware types (both have real routes above), and treats `ability:*`/role-middleware as a documented "not present today, not implemented speculatively" category — `resolveAuthorization()` explicitly does not special-case them; if a future sidebar item adds one, `resolveAuthorization()` must be extended then, not now (per the constitution's "Không biến một yêu cầu nhỏ thành cuộc đại tu kiến trúc không cần thiết").

**7 routes are zero-`rbac:*`/zero-`can:*`** (`app.dashboard`, `operator.material-requests.index`, `operator.receipts.index`, `app.projects`, `app.tasks`, `app.calendar`, `operator.api-tokens.index`). Per the review's explicit rule ("a route with no feature authorization middleware may be baseline-visible only after the sidebar inventory explicitly verifies that this is intentional"), these 7 are hard-coded into a `KNOWN_BASELINE_ROUTES` allowlist inside the composer — **not** inferred generically from "has `auth`+`tenant.isolation` and nothing else." A route absent from this allowlist that also has no `rbac:*`/`can:*` is treated as `unresolvable` (hidden), even though it technically sits in the authenticated tenant surface — this is a deliberate fail-closed choice for anything not explicitly reviewed. `operator.api-tokens.index` being baseline-visible to any authenticated tenant user (no `rbac:*` gate on API token management) is flagged here as a notable, verified finding — out of scope to change in this feature, but worth a follow-up ticket outside this plan.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/OperatorNavigationTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Navigation\OperatorNavigationComposer;
use App\Support\Navigation\OperatorNavItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorNavigationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->tenant = Tenant::factory()->create();
    }

    private function labels(array $sections): array
    {
        return collect($sections)->flatten()->map(fn (OperatorNavItem $i) => $i->routeName)->all();
    }

    public function test_every_defined_navigation_route_resolves(): void
    {
        foreach (\App\Support\Navigation\OperatorNavigationDefinition::items() as $item) {
            $this->assertTrue(
                \Illuminate\Support\Facades\Route::has($item->routeName),
                "Nav item '{$item->label}' references undefined route '{$item->routeName}'."
            );
            $this->assertNotSame('', $item->label);
            $this->assertNotSame('', $item->section);
            $this->assertNotSame('', $item->iconKey);
        }
    }

    public function test_zero_rbac_route_on_verified_baseline_allowlist_is_visible(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('app.tasks');
        $this->assertSame('baseline', $requirement->type);

        $labels = $this->labels($composer->visibleFor($employee));
        $this->assertContains('app.tasks', $labels);
    }

    public function test_single_rbac_route_hidden_without_permission_and_visible_with_it(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);
        $pm = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);

        $composer = app(OperatorNavigationComposer::class);

        $this->assertNotContains('app.workload.index', $this->labels($composer->visibleFor($employee)));
        $this->assertContains('app.workload.index', $this->labels($composer->visibleFor($pm)));
    }

    public function test_can_middleware_route_hidden_without_matching_permission_and_visible_with_it(): void
    {
        $withoutTeamView = $this->createTenantUser($this->tenant, [], ['member'], []);
        $withTeamView = $this->createTenantUser($this->tenant, [], ['member'], ['team.view']);

        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('app.team.index');
        $this->assertSame('can', $requirement->type);
        $this->assertSame('viewAny', $requirement->ability);
        $this->assertSame(\App\Models\Team::class, $requirement->subjectClass);

        $this->assertNotContains('app.team.index', $this->labels($composer->visibleFor($withoutTeamView)));
        $this->assertContains('app.team.index', $this->labels($composer->visibleFor($withTeamView)));
    }

    public function test_unresolvable_route_fails_closed(): void
    {
        $anyUser = $this->createTenantUser($this->tenant, [], ['super_admin'], []);
        $composer = app(OperatorNavigationComposer::class);

        $requirement = $composer->resolveAuthorization('this.route.does.not.exist');

        $this->assertSame('unresolvable', $requirement->type);

        $syntheticItems = [new OperatorNavItem('Ẩn', 'this.route.does.not.exist', 'Test', 'generic')];
        $this->assertSame([], $composer->visibleFromItems($syntheticItems, $anyUser));
    }

    public function test_zero_rbac_route_not_on_baseline_allowlist_fails_closed(): void
    {
        $anyUser = $this->createTenantUser($this->tenant, [], ['super_admin'], []);
        $composer = app(OperatorNavigationComposer::class);

        // app.tasks.show carries auth+tenant.isolation only (verified earlier),
        // and is deliberately NOT on the baseline allowlist because it was never
        // reviewed as a sidebar destination (it is a detail page, not a nav item)
        // — this proves zero-rbac alone is not sufficient without the allowlist.
        $requirement = $composer->resolveAuthorization('app.tasks.show');

        $this->assertSame('unresolvable', $requirement->type);
    }

    public function test_empty_sections_are_omitted(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        $composer = app(OperatorNavigationComposer::class);
        $visible = $composer->visibleFor($employee);

        foreach ($visible as $section => $items) {
            $this->assertNotEmpty($items, "Section '{$section}' must not appear with zero items.");
        }
    }

    public function test_hidden_navigation_does_not_weaken_route_rbac(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($employee)->get(route('app.workload.index'))->assertStatus(302);
        $this->actingAs($employee)->get(route('app.team.index'))->assertStatus(302);
    }

    public function test_nav_item_never_visible_when_destination_deterministically_returns_302_for_same_actor(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], []);
        $composer = app(OperatorNavigationComposer::class);

        $visibleRouteNames = $this->labels($composer->visibleFor($employee));

        foreach ($visibleRouteNames as $routeName) {
            $status = $this->actingAs($employee)->get(route($routeName))->getStatusCode();
            $this->assertNotSame(302, $status, "Nav shows '{$routeName}' but the route redirects (would 403-equivalent) for this actor.");
        }
    }

    public function test_permission_query_count_is_bounded_independent_of_nav_item_count(): void
    {
        $employee = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);
        $composer = app(OperatorNavigationComposer::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $composer->visibleFor($employee);
        $baselineCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 50 synthetic rbac-gated items reusing 2 already-checked permissions —
        // must not add any new query, because rbac evaluation reads the
        // preloaded in-memory permission set, not the database, per item.
        $syntheticItems = array_map(
            fn (int $i) => new OperatorNavItem("Synthetic {$i}", 'app.workload.index', 'Test', 'generic'),
            range(1, 50)
        );

        DB::flushQueryLog();
        DB::enableQueryLog();
        $composer->visibleFromItems($syntheticItems, $employee);
        $syntheticCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($baselineCount, $syntheticCount);
    }

    public function test_multiple_rbac_requirements_on_one_route_use_and_semantics(): void
    {
        // routes/web.php:981 (design-items.suggest-description) carries both
        // rbac:design-item.manage AND rbac:ai.suggest — verified real 2-permission case.
        $composer = app(OperatorNavigationComposer::class);
        $requirement = $composer->resolveAuthorization('operator.design-items.suggest-description');

        $this->assertSame('rbac', $requirement->type);
        $this->assertSame(['design-item.manage', 'ai.suggest'], $requirement->permissions);

        $onlyOne = $this->createTenantUser($this->tenant, [], ['member'], ['design-item.manage']);
        $both = $this->createTenantUser($this->tenant, [], ['member'], ['design-item.manage', 'ai.suggest']);

        $syntheticItems = [new OperatorNavItem('Gợi ý AI', 'operator.design-items.suggest-description', 'Test', 'generic')];
        $this->assertSame([], $composer->visibleFromItems($syntheticItems, $onlyOne));
        $this->assertNotEmpty($composer->visibleFromItems($syntheticItems, $both));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/OperatorNavigationTest.php`
Expected: FAIL — `Class "App\Support\Navigation\OperatorNavigationComposer" not found`.

- [ ] **Step 3: Write minimal implementation**

Create `app/Support/Navigation/OperatorNavItem.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class OperatorNavItem
{
    public function __construct(
        public readonly string $label,
        public readonly string $routeName,
        public readonly string $section,
        public readonly string $iconKey,
    ) {
    }
}
```

Create `app/Support/Navigation/NavAuthorizationRequirement.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class NavAuthorizationRequirement
{
    /**
     * @param string[] $permissions
     */
    private function __construct(
        public readonly string $type,
        public readonly array $permissions = [],
        public readonly ?string $ability = null,
        public readonly ?string $subjectClass = null,
    ) {
    }

    /**
     * @param string[] $permissions
     */
    public static function rbac(array $permissions): self
    {
        return new self('rbac', $permissions);
    }

    public static function can(string $ability, string $subjectClass): self
    {
        return new self('can', [], $ability, $subjectClass);
    }

    public static function baseline(): self
    {
        return new self('baseline');
    }

    public static function unresolvable(): self
    {
        return new self('unresolvable');
    }
}
```

Create `app/Support/Navigation/OperatorNavigationDefinition.php` (28 items, `app.today` first, `iconKey` reuses a stable slug per route — the exact `<svg>` markup per key lives in the icon component from Step 5, not here):

```php
<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class OperatorNavigationDefinition
{
    /**
     * @return OperatorNavItem[]
     */
    public static function items(): array
    {
        return [
            new OperatorNavItem('Hôm nay', 'app.today', 'Tổng quan', 'today'),
            new OperatorNavItem('Bảng điều hành', 'app.dashboard', 'Tổng quan', 'dashboard'),
            new OperatorNavItem('Hoạt động', 'operator.activity-feed.index', 'Tổng quan', 'activity-feed'),
            new OperatorNavItem('Lịch trình', 'operator.schedule.index', 'Tổng quan', 'schedule'),
            new OperatorNavItem('CRM', 'operator.crm.index', 'Kinh doanh', 'crm'),
            new OperatorNavItem('Báo cáo CRM', 'operator.crm.reports', 'Kinh doanh', 'crm-reports'),
            new OperatorNavItem('Yêu cầu vật tư', 'operator.material-requests.index', 'Mua sắm', 'material-requests'),
            new OperatorNavItem('Phiếu nhập', 'operator.receipts.index', 'Mua sắm', 'receipts'),
            new OperatorNavItem('Vật tư', 'operator.materials.index', 'Mua sắm', 'materials'),
            new OperatorNavItem('Nhà cung cấp', 'operator.vendors.index', 'Mua sắm', 'vendors'),
            new OperatorNavItem('BOQ', 'operator.boqs.index', 'Thương mại', 'boqs'),
            new OperatorNavItem('Hợp đồng', 'operator.contracts.index', 'Thương mại', 'contracts'),
            new OperatorNavItem('Dự án', 'app.projects', 'Dự án', 'projects'),
            new OperatorNavItem('Công việc', 'app.tasks', 'Dự án', 'tasks'),
            new OperatorNavItem('Khối lượng', 'app.workload.index', 'Dự án', 'workload'),
            new OperatorNavItem('Việc của tôi', 'app.my-work.index', 'Dự án', 'my-work'),
            new OperatorNavItem('Hạng mục thiết kế', 'operator.design-items.index', 'Dự án', 'design-items'),
            new OperatorNavItem('Lịch', 'app.calendar', 'Dự án', 'calendar'),
            new OperatorNavItem('Đội nhóm', 'app.team.index', 'Dự án', 'team'),
            new OperatorNavItem('Nhật ký công trường', 'operator.site-diaries.index', 'Công trường', 'site-diaries'),
            new OperatorNavItem('Kiểm định', 'operator.inspections.index', 'Chất lượng', 'inspections'),
            new OperatorNavItem('Tri thức', 'operator.knowledge.index', 'Tri thức', 'knowledge'),
            new OperatorNavItem('RFI', 'operator.rfis.index', 'Tài liệu', 'rfis'),
            new OperatorNavItem('Submittal', 'operator.submittals.index', 'Tài liệu', 'submittals'),
            new OperatorNavItem('Yêu cầu thay đổi', 'operator.change-requests.index', 'Tài liệu', 'change-requests'),
            new OperatorNavItem('Báo cáo', 'operator.reports.index', 'Hệ thống', 'reports'),
            new OperatorNavItem('Webhook', 'operator.webhooks.index', 'Hệ thống', 'webhooks'),
            new OperatorNavItem('API Token', 'operator.api-tokens.index', 'Hệ thống', 'api-tokens'),
        ];
    }
}
```

Create `app/Support/Navigation/OperatorNavigationComposer.php`:

```php
<?php declare(strict_types=1);

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Permission per nav item được suy tự động từ route middleware thật —
 * không phải bảng dữ liệu do implementation tự gán tay. Permission set
 * của actor được preload đúng 1 lần/request — không gọi hasPermission()
 * hay Gate cho từng nav item rbac.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.7
 */
class OperatorNavigationComposer
{
    /**
     * Route xác nhận KHÔNG có rbac:*/can:* nhưng vẫn được coi là hiển thị
     * mặc định — chỉ những route đã thực sự kiểm tra middleware và xác nhận
     * cố ý ở đây mới được thêm vào danh sách này. Không route nào khác được
     * tự động coi là an toàn chỉ vì thiếu rbac:*/can:*.
     */
    private const KNOWN_BASELINE_ROUTES = [
        'app.dashboard',
        'operator.material-requests.index',
        'operator.receipts.index',
        'app.projects',
        'app.tasks',
        'app.calendar',
        'operator.api-tokens.index',
    ];

    public function resolveAuthorization(string $routeName): NavAuthorizationRequirement
    {
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return NavAuthorizationRequirement::unresolvable();
        }

        $middleware = $route->gatherMiddleware();

        $rbacPermissions = collect($middleware)
            ->filter(fn (string $m) => str_starts_with($m, 'rbac:'))
            ->map(fn (string $m) => substr($m, strlen('rbac:')))
            ->values()
            ->all();

        if ($rbacPermissions !== []) {
            return NavAuthorizationRequirement::rbac($rbacPermissions);
        }

        $canMiddleware = collect($middleware)->first(fn (string $m) => str_starts_with($m, 'can:'));

        if ($canMiddleware !== null) {
            $parts = explode(',', substr($canMiddleware, strlen('can:')), 2);
            $ability = $parts[0];
            $subject = $parts[1] ?? null;

            $isStaticListLevelAbility = $subject !== null
                && class_exists($subject)
                && $route->parameterNames() === [];

            if ($isStaticListLevelAbility) {
                return NavAuthorizationRequirement::can($ability, $subject);
            }

            // can:* trên route có route-model parameter không đánh giá được
            // ở cấp nav toàn cục mà không có 1 record cụ thể — fail closed.
            return NavAuthorizationRequirement::unresolvable();
        }

        if (in_array($routeName, self::KNOWN_BASELINE_ROUTES, true)) {
            return NavAuthorizationRequirement::baseline();
        }

        return NavAuthorizationRequirement::unresolvable();
    }

    /**
     * @return string[]
     */
    public function loadPermissionNames(User $actor): array
    {
        return $actor->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, OperatorNavItem[]>
     */
    public function visibleFor(User $actor): array
    {
        return $this->visibleFromItems(OperatorNavigationDefinition::items(), $actor);
    }

    /**
     * @param OperatorNavItem[] $items
     * @return array<string, OperatorNavItem[]>
     */
    public function visibleFromItems(array $items, User $actor): array
    {
        $permissionNames = $this->loadPermissionNames($actor);
        $gate = Gate::forUser($actor);

        return collect($items)
            ->filter(function (OperatorNavItem $item) use ($permissionNames, $gate) {
                $requirement = $this->resolveAuthorization($item->routeName);

                return match ($requirement->type) {
                    'unresolvable' => false,
                    'baseline' => true,
                    'rbac' => collect($requirement->permissions)->every(fn (string $p) => in_array($p, $permissionNames, true)),
                    'can' => $gate->allows($requirement->ability, $requirement->subjectClass),
                };
            })
            ->groupBy(fn (OperatorNavItem $item) => $item->section)
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }
}
```

Note on query cost: `loadPermissionNames()` runs exactly 2 queries (roles, then eager-loaded permissions) regardless of nav item count — this covers every `rbac:*` item at zero additional cost. The `'can'` branch calls `Gate::allows()`, which executes `TeamPolicy::viewAny()` → `hasPermission('team.view')` for real — 1 additional query, but bounded by the number of **distinct `can:*`-gated items** (currently exactly one, `app.team.index`), not by total nav item count. This is why `test_permission_query_count_is_bounded_independent_of_nav_item_count` reuses `app.workload.index` (an `rbac:*` route) for its 50 synthetic items rather than a `can:*` route — adding more `rbac:*` items is the case that must stay flat, and does.

Create `resources/views/components/operator-nav-icon.blade.php` — one centralized icon lookup instead of scattering `<svg>` markup across a loop (no new icon package dependency; every path below is a plain inline SVG, the same technique already used in the pre-edit `operator.blade.php`):

```blade
@props(['iconKey'])

@php
    // Mỗi <svg>...</svg> ở đây phải là bản sao chính xác của icon đang gắn với
    // route tương ứng trong bản operator.blade.php TRƯỚC khi sửa (không phải icon
    // phát minh mới) — implementer đọc file gốc và dán lại nguyên trạng từng khối,
    // một lần duy nhất, tại chỗ này. Ngoại lệ duy nhất là 'today' (mục nav MỚI,
    // không có icon cũ để copy) — dùng 1 icon inline mới, đơn giản, không cần
    // thư viện icon nào.
@endphp

@switch($iconKey)
    @case('today')
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        @break
    {{-- 'dashboard', 'activity-feed', 'schedule', 'crm', 'crm-reports',
         'material-requests', 'receipts', 'materials', 'vendors', 'boqs',
         'contracts', 'projects', 'tasks', 'workload', 'my-work',
         'design-items', 'calendar', 'team', 'site-diaries', 'inspections',
         'knowledge', 'rfis', 'submittals', 'change-requests', 'reports',
         'webhooks', 'api-tokens' — each @case below must contain the exact
         pre-edit <svg>...</svg> for that route, transcribed once. --}}
    @default
        {{-- unreachable if OperatorNavigationDefinitionIconKeyTest (Step 6) passes --}}
@endswitch
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/OperatorNavigationTest.php`
Expected: PASS (10 tests).

- [ ] **Step 5: Wire the composer and icon component into the Blade layout**

Read `resources/views/layouts/operator.blade.php:20-180` fully before editing, to preserve every class name and the exact `is-active` computation (`request()->routeIs(...)`) already used per link. Replace the **hardcoded list of `<a>` tags between lines 27 and 179** with a loop driven by the composer's output:

```blade
@php($operatorNavSections = app(\App\Support\Navigation\OperatorNavigationComposer::class)->visibleFor(auth()->user()))

@foreach ($operatorNavSections as $section => $items)
    <span class="operator-nav-section">{{ $section }}</span>
    @foreach ($items as $item)
        <a href="{{ route($item->routeName) }}"
           class="operator-nav-link {{ request()->routeIs($item->routeName) ? 'is-active' : '' }}">
            <x-operator-nav-icon :icon-key="$item->iconKey" />
            <span>{{ $item->label }}</span>
        </a>
    @endforeach
@endforeach
```

Transcribe each existing route's `<svg>...</svg>` block (read from the pre-edit version of this same file, one block per route name) into the matching `@case` in `operator-nav-icon.blade.php` from Step 3 — every `iconKey` in `OperatorNavigationDefinition::items()` must have a corresponding `@case` with the real, unmodified icon markup for that route, except `'today'` which is intentionally a new, simple inline SVG (not transcribed, since it has no prior icon to copy).

- [ ] **Step 6: Add the icon-migration structural test**

Add to `tests/Feature/OperatorNavigationTest.php`:

```php
    public function test_every_nav_item_icon_key_has_a_rendered_case_in_the_icon_component(): void
    {
        $iconComponentSource = file_get_contents(resource_path('views/components/operator-nav-icon.blade.php'));

        foreach (\App\Support\Navigation\OperatorNavigationDefinition::items() as $item) {
            $this->assertStringContainsString(
                "@case('{$item->iconKey}')",
                $iconComponentSource,
                "Icon component has no @case for iconKey '{$item->iconKey}' (route '{$item->routeName}')."
            );
        }
    }

    public function test_active_route_class_applied_to_current_page_link(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['task.view']);

        $response = $this->actingAs($viewer)->get(route('app.workload.index'));

        $response->assertOk();
        // is-active phải nằm trên đúng link Khối lượng, không phải link khác.
        $response->assertSee('operator-nav-link is-active', false);
    }
```

Run: `php artisan test tests/Feature/OperatorNavigationTest.php`
Expected: PASS (12 tests) — the first new test fails loudly (naming the missing `iconKey`) if any of the 28 icons was skipped during transcription, instead of silently rendering a blank icon in production.

- [ ] **Step 7: Run the navigation and full page tests**

Run: `php artisan test tests/Feature/OperatorNavigationTest.php tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php tests/Feature/TodayPageTest.php`
Expected: PASS — nav composer tests green, and every page test that renders `operator.blade.php` (all of them, since it's the shared layout) still passes with the new loop-driven nav.

- [ ] **Step 8: Commit**

```bash
git add app/Support/Navigation/ resources/views/components/operator-nav-icon.blade.php resources/views/layouts/operator.blade.php tests/Feature/OperatorNavigationTest.php
git commit -m "feat(nav): permission-aware operator sidebar with rbac/can authorization and bounded permission preload"
```

---

### Task 10: Landing-page rollout

**Files:**
- Modify: `app/Http/Controllers/AuthController.php:46`
- Modify: `routes/web.php` (root `/` redirect, currently `routes/web.php:29-31`)
- Test: `tests/Feature/Auth/LoginRedirectTest.php` (new, or extend the existing login test file if `tests/Feature/Auth/` already has one covering `AuthController::login` — check before creating a duplicate)

**Interfaces:**
- Consumes: nothing new — this task only changes two redirect targets, gated by all of Tasks 1–9 already being green.

- [ ] **Step 1: Confirm prerequisite gate before touching redirects**

Run the full suite covering everything Today/nav depends on:

Run: `php artisan test tests/Feature/TodayPageTest.php tests/Feature/OperatorNavigationTest.php tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php tests/Feature/Services/`
Expected: PASS, 100%. Do not proceed to Step 2 unless this is fully green.

- [ ] **Step 2: Write the failing test**

First run `grep -rn "function test_" tests/Feature/Auth/*.php | grep -i login` to check for an existing redirect-target test to extend instead of duplicating. If none asserts the post-login destination, create `tests/Feature/Auth/LoginRedirectTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_login_redirects_to_today(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/app/today');
    }

    public function test_root_url_redirects_to_today(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/app/today');
    }

    public function test_dashboard_route_still_exists_and_is_reachable(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `php artisan test tests/Feature/Auth/LoginRedirectTest.php`
Expected: FAIL — both redirect assertions currently point at `/app/dashboard`.

- [ ] **Step 4: Change the two redirect targets**

Modify `app/Http/Controllers/AuthController.php:46` — change `return redirect()->intended('/app/dashboard');` to `return redirect()->intended('/app/today');`.

Modify `routes/web.php:29-31` — change:

```php
Route::get('/', function () {
    return redirect('/app/dashboard');
});
```

to:

```php
Route::get('/', function () {
    return redirect('/app/today');
});
```

Do **not** remove or rename the `app.dashboard` route or `AppController::dashboard()` — they stay reachable at `/app/dashboard`, just no longer the default landing target.

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Feature/Auth/LoginRedirectTest.php`
Expected: PASS (3 tests).

- [ ] **Step 6: Run the broader auth/dashboard regression group**

Run: `php artisan test tests/Feature/Auth/`
Expected: PASS — if a pre-existing test in this directory asserted `/app/dashboard` as the login target, update that one assertion to `/app/today` (this task's entire point is changing the login destination, so this is the one file allowed to change an existing assertion).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/AuthController.php routes/web.php tests/Feature/Auth/LoginRedirectTest.php
git commit -m "feat(today): make /app/today the post-login landing page"
```

---

### Task 11: Full security, tenant-isolation, and performance verification

**Files:**
- Create: `tests/Feature/TodayTenantIsolationTest.php`
- Create: `tests/Feature/TodayPerformanceTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–9. No production code changes in this task — verification only, plus any focused bug-fix commit if a gap is found.

- [ ] **Step 1: Write the cross-tenant matrix test**

Create `tests/Feature/TodayTenantIsolationTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayTenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $viewer;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenantA, [], ['admin'], ['task.view']);
        $this->projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);
        $this->projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);
    }

    public function test_milestone_of_other_tenant_never_appears_even_with_matching_project_id_collision_risk(): void
    {
        Task::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'assigned_to' => (string) $this->viewer->id,
            'status' => Task::STATUS_PENDING,
            'name' => 'Việc A', 'title' => 'Việc A',
        ]);
        ProjectMilestone::create([
            'project_id' => (string) $this->projectB->id,
            'name' => 'Milestone tenant B',
            'target_date' => now()->addDays(2)->toDateString(),
            'status' => ProjectMilestone::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Milestone tenant B');
    }

    public function test_notification_of_other_tenant_never_appears(): void
    {
        $userB = User::factory()->create(['tenant_id' => (string) $this->tenantB->id]);
        Notification::factory()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'user_id' => (string) $userB->id,
            'title' => 'Thông báo tenant B',
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Thông báo tenant B');
    }

    public function test_team_exception_of_other_tenant_never_appears(): void
    {
        $leadA = $this->viewer;
        $memberB = User::factory()->create(['tenant_id' => (string) $this->tenantB->id, 'name' => 'Thành viên B']);
        $teamB = Team::factory()->create(['tenant_id' => (string) $this->tenantB->id, 'team_lead_id' => (string) $leadA->id]);
        $teamB->members()->attach($memberB->id, ['role' => 'member', 'joined_at' => now()]);

        $response = $this->actingAs($leadA)->get(route('app.today'));

        $response->assertOk();
        $response->assertDontSee('Thành viên B');
    }

    public function test_hidden_navigation_does_not_weaken_route_rbac_across_tenants(): void
    {
        $noPerm = $this->createTenantUser($this->tenantA, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('app.today'))->assertStatus(302);
        $this->actingAs($noPerm)->get(route('app.workload.index'))->assertStatus(302);
    }
}
```

- [ ] **Step 2: Run test to verify it passes without modification**

Run: `php artisan test tests/Feature/TodayTenantIsolationTest.php`
Expected: PASS immediately (this task adds no new production code; a failure means a real gap in an earlier task, fixed there with a focused commit before continuing).

- [ ] **Step 3: Establish the analytical query budget before writing any assertion**

Document the intended fixed query count per collaborator, counted from the code written in Tasks 1–9 (not measured yet):

| Source | Queries |
|---|---|
| `OpenWorkReadQuery::collect()` (Task 1) — called exactly once per request (Task 7) | 2 main queries (`tasks`, `design_items`) + 2 eager-load `project` queries = **4** |
| `UpcomingMilestoneQuery::build()` (Task 4), when `$relatedProjectIds !== []` | 1 main query with `whereHas` join + 1 eager-load `project` = **2** |
| `UnreadUpdateQuery::build()` (Task 5) | **1** |
| `TeamExceptionQuery::build()` (Task 6) | `Project::where(pm_id)` (1) + `Team::where(team_lead_id)` with eager `activeMembers` (2) + at most 1 bounded name-lookup `whereIn` = **at most 4** |
| `OperatorNavigationComposer::visibleFor()` (Task 9), rendered once per page via the shared layout | `loadPermissionNames()` = 2, plus 1 `Gate::allows()` call for the single `can:*` item (`app.team.index`) = **3** |
| Framework session/auth/tenant-isolation middleware | not hand-counted here — inherent to every `/app/*` page regardless of this feature, measured empirically in Step 4 |

**Documented maximum for the full `/app/today` GET request: 4 + 2 + 1 + 4 + 3 = 14, plus framework overhead.** Set the absolute-ceiling assertion at **20** (14 analytically-derived collaborator/nav queries + a 6-query allowance for framework session/auth/tenant-isolation overhead, which this plan does not hand-count since it is identical on every existing `/app/*` page and not something this feature changes). This ceiling is fixed **before** running the test in Step 4 — if the real measurement exceeds it, that is a genuine regression to fix in the owning task's file, not a number to raise.

- [ ] **Step 4: Write the two-part query-budget test**

Create `tests/Feature/TodayPerformanceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class TodayPerformanceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ANALYTICAL_MAX_QUERIES = 20;

    private function seedFixture(Tenant $tenant, \App\Models\User $viewer, Project $project, int $rowCount): void
    {
        foreach (range(1, $rowCount) as $i) {
            Task::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $viewer->id,
                'status' => Task::STATUS_PENDING,
                'name' => "Việc {$i}", 'title' => "Việc {$i}",
            ]);
            DesignItem::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'project_id' => (string) $project->id,
                'assigned_to' => (string) $viewer->id,
                'review_status' => DesignItem::STATUS_DRAFT,
            ]);
            ProjectMilestone::create([
                'project_id' => (string) $project->id,
                'name' => "Milestone {$i}",
                'target_date' => now()->addDays($i)->toDateString(),
                'status' => ProjectMilestone::STATUS_PENDING,
            ]);
        }
    }

    private function countQueriesForTodayPage(Tenant $tenant, \App\Models\User $viewer): int
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($viewer)->get(route('app.today'))->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_query_count_does_not_grow_with_rendered_row_count(): void
    {
        $tenantSmall = Tenant::factory()->create();
        $viewerSmall = $this->createTenantUser($tenantSmall, [], ['admin'], ['task.view']);
        $projectSmall = Project::factory()->create(['tenant_id' => (string) $tenantSmall->id]);
        $this->seedFixture($tenantSmall, $viewerSmall, $projectSmall, 3);
        $smallCount = $this->countQueriesForTodayPage($tenantSmall, $viewerSmall);

        $tenantLarge = Tenant::factory()->create();
        $viewerLarge = $this->createTenantUser($tenantLarge, [], ['admin'], ['task.view']);
        $projectLarge = Project::factory()->create(['tenant_id' => (string) $tenantLarge->id]);
        $this->seedFixture($tenantLarge, $viewerLarge, $projectLarge, 40);
        $largeCount = $this->countQueriesForTodayPage($tenantLarge, $viewerLarge);

        // Cho phép sai lệch bằng 0 — số query phải giống hệt nhau dù số row
        // tăng từ 3 lên 40, vì mọi query đều dùng get()/whereIn() 1 lần, không
        // lặp theo số bản ghi. Nếu có N+1 thật, $largeCount sẽ lớn hơn hẳn.
        $this->assertSame($smallCount, $largeCount, 'Query count must not scale with rendered row count.');
    }

    public function test_page_query_count_stays_at_or_below_documented_ceiling(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createTenantUser($tenant, [], ['admin'], ['task.view']);
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $this->seedFixture($tenant, $viewer, $project, 40);

        $count = $this->countQueriesForTodayPage($tenant, $viewer);

        $this->assertLessThanOrEqual(
            self::ANALYTICAL_MAX_QUERIES,
            $count,
            'Query count exceeded the documented analytical ceiling (see Task 11 Step 3) — this is a regression to fix, not a number to raise.'
        );
    }

    public function test_page_p95_style_single_sample_under_500ms(): void
    {
        $tenant = Tenant::factory()->create();
        $viewer = $this->createTenantUser($tenant, [], ['admin'], ['task.view']);
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $start = microtime(true);
        $this->actingAs($viewer)->get(route('app.today'))->assertOk();
        $elapsedMs = (microtime(true) - $start) * 1000;

        $this->assertLessThan(500, $elapsedMs, 'Today page should respond in under 500ms per PROJECT_CONSTITUTION.md Appendix A.8.');
    }
}
```

- [ ] **Step 5: Run and resolve any ceiling breach in its owning task**

Run: `php artisan test tests/Feature/TodayPerformanceTest.php -v`
Expected: PASS (3 tests). If `test_page_query_count_stays_at_or_below_documented_ceiling` fails, the real count is higher than the Step 3 analysis predicted — find which collaborator (Task 1, 4, 5, 6, or 9) is issuing more queries than documented, fix it there (this is exactly the kind of undiscovered N+1 this two-part strategy exists to catch), and only then re-run this test. Do not raise `ANALYTICAL_MAX_QUERIES` to make a real regression pass.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/TodayTenantIsolationTest.php tests/Feature/TodayPerformanceTest.php
git commit -m "test(today): lock tenant-isolation matrix and two-part query-budget regression"
```

---

### Task 12: Documentation and final operational simulation

**Files:**
- Modify: none (verification-only task; if any step below surfaces a real defect, fix it in the owning file from the relevant earlier task and commit that fix separately before re-running this task's checklist)

**Interfaces:** none — this is the closing verification gate.

- [ ] **Step 1: Run every focused Today test file**

Run: `php artisan test tests/Feature/TodayPageTest.php tests/Feature/TodayTenantIsolationTest.php tests/Feature/TodayPerformanceTest.php tests/Feature/OperatorNavigationTest.php tests/Feature/Auth/LoginRedirectTest.php tests/Feature/Services/ tests/Unit/Support/Today/`
Expected: PASS, all green.

- [ ] **Step 2: Run existing My Work and Workload regression suites**

Run: `php artisan test tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php`
Expected: PASS, zero edits present in either file (`git diff --stat tests/Feature/MyWorkPageTest.php tests/Feature/WorkloadPageTest.php` against the pre-Task-1 commit must show no changes).

- [ ] **Step 3: Run route/RBAC invariant tests**

Run: `php artisan test tests/Feature/RouteMiddlewareSecurityContractTest.php tests/Feature/Zena/ZenaRouteSurfaceInvariantTest.php`
Expected: PASS — these target `api.*`/`api/zena*` surfaces and are unaffected by this feature's `app.*` web routes.

- [ ] **Step 4: Run the relevant full Feature suite**

Run: `php artisan test tests/Feature/`
Expected: PASS. Any unrelated pre-existing failure not touched by this plan must be reported as a pre-existing condition, not silently ignored.

- [ ] **Step 5: Static analysis**

Run: `./vendor/bin/phpstan analyse`
Expected: no new errors introduced by any file created/modified in Tasks 1–10.

- [ ] **Step 6: Frontend build**

This feature added no JS/CSS/Vite assets — Blade views only, using existing `<x-ui.*>` components plus one new `<x-operator-nav-icon>` component (also plain Blade, no build step). Run: `npm run build` to confirm the existing asset pipeline still compiles cleanly.

- [ ] **Step 7: Browser/Dusk tests**

Do not add new Dusk/browser tests for this feature — the repository's own operational history records a recurring environment-level segfault in `tests/Browser/*` unrelated to application code. All Today/nav behavior in this plan is already covered by `RefreshDatabase` Feature tests asserting rendered HTML.

- [ ] **Step 8: Manual operational simulation — employee persona**

Log in as a user with only `task.view` (no PM/team-lead standing, no `team.view`). Confirm: `/app/today` shows Personal Open Work, Đang thực hiện, Overdue and Blocked, Upcoming Milestones (own assigned projects only), Unread Updates; does **not** show "Khối lượng công việc đã ghi nhận"; sidebar does not show `app.team.index` ("Đội nhóm") since this actor lacks `team.view`, and does not show any `rbac:*`-gated item they lack permission for.

- [ ] **Step 9: Manual operational simulation — PM persona**

Log in as a user who is `Project.pm_id` on at least one project. Confirm `/app/today` additionally shows "Khối lượng công việc đã ghi nhận" with that project's assignees' counts, with no "Rảnh"/"Khả dụng X%"/"Quá tải X%" text anywhere (view page source and grep for those exact strings).

- [ ] **Step 10: Manual operational simulation — admin/team-lead persona**

Log in as a user holding `team.view` (or broader admin permissions covering `operator.api-tokens.index`, `operator.webhooks.index`, etc. — confirm exact permissions via the Task 9 middleware inventory table, not guessed). Confirm the sidebar shows "Đội nhóm" and every other section including "Hệ thống", and `/app/today` renders normally.

- [ ] **Step 11: Confirm no project percentage or financial value appears**

As part of Step 8's browser check, view page source and confirm zero occurrences of: `progress_percent`, `overall_progress`, `completion_rate`, `budget_actual`. Cross-reference against the automated assertions already locked in `tests/Feature/TodayPageTest.php::test_project_progress_percentage_absent` and `::test_financial_data_absent` (Task 8).

- [ ] **Step 12: Confirm no Action Required runtime artifacts were introduced**

Run: `grep -rn "ActionRequiredQuery\|TodayActionItem\|actionRequired" app/ resources/views/app/ tests/Feature/TodayPageTest.php tests/Feature/Services/`
Expected: zero matches for `ActionRequiredQuery` and `TodayActionItem` anywhere in `app/`; the only `actionRequired`-adjacent text allowed anywhere is the negative assertion `assertDontSeeText('actionRequired')` already present in `TodayPageTest` (Task 8).

- [ ] **Step 13: Confirm GAP-031 was not accidentally implemented**

Run: `git diff --stat <base-commit>..HEAD -- app/Models/Document.php app/Http/Controllers/Web/DocumentController.php`
Expected: empty output — neither file appears in the diff for this feature.

- [ ] **Step 14: Confirm no duplicate trust-state vocabulary was introduced**

Run: `grep -rln "TodayAvailability\|TodayReliability" app/ tests/`
Expected: zero matches — every Today section uses `App\Support\Dashboard\Availability`/`Reliability` (Task 2), and `git diff --stat <base-commit>..HEAD -- app/Support/Dashboard/Availability.php app/Support/Dashboard/Reliability.php app/Support/Dashboard/MetricResult.php` shows no changes to those three files.

- [ ] **Step 15: Final commit (if any step required a fix)**

If every step above passed with no fixes needed, there is nothing to commit in this task. If a fix was required at any step, commit it now with a message describing exactly what was found and fixed, referencing which task's file it belongs to:

```bash
git add <fixed files>
git commit -m "fix(today): <precise description of the gap found during final verification>"
```

---

## Self-review

**1. Every referenced class has a task that creates or modifies it:** `OpenWorkItem` (Task 1), `OpenWorkReadQuery` (Task 1), `TodaySectionResult`/`TodayMilestoneItem`/`TodayNotificationItem`/`TodayTeamMemberSummary`/`TodayWorkspaceViewModel` (Task 2), `TodayWorkspaceReadService` (Tasks 3 & 7), `UpcomingMilestoneQuery` (Task 4), `UnreadUpdateQuery` (Task 5), `TeamExceptionQuery` (Task 6), `TodayController` (Task 8), `OperatorNavItem`/`NavAuthorizationRequirement`/`OperatorNavigationDefinition`/`OperatorNavigationComposer` (Task 9). No class is referenced by a later task without an earlier task defining it first.

**2. No duplicate trust enums remain:** Task 2 explicitly reuses `App\Support\Dashboard\Availability`/`Reliability`; no `TodayAvailability`/`TodayReliability` file is created anywhere in this plan; Task 12 Step 14 greps for both to confirm.

**3. Zero-RBAC routes with `can:*` are not baseline-visible:** `resolveAuthorization()` (Task 9) checks `rbac:*` first, then `can:*` **before** ever considering the baseline allowlist — a route with `can:*` never falls through to the baseline branch regardless of what's in `KNOWN_BASELINE_ROUTES`. `app.team.index` is verified as the concrete `can:*` case with its own dedicated tests.

**4. RBAC checks do not query once per nav item:** `loadPermissionNames()` runs exactly twice per request (Task 9), independent of item count; `test_permission_query_count_is_bounded_independent_of_nav_item_count` proves adding 50 synthetic `rbac:*` items adds zero queries.

**5. Query-budget tests cannot bless an N+1 implementation:** Task 11 documents the analytical ceiling (14 collaborator/nav queries + 6 framework allowance = 20) **before** running any test, and pairs it with a row-count-invariance test (3 rows vs. 40 rows must produce the identical query count) — a real N+1 would fail the invariance test regardless of what the ceiling is set to, and the ceiling itself cannot be silently raised to fit a bad measurement without that being a visible, described change to Step 3's table.

**6. Milestone tests avoid the model's inconsistent helpers:** Task 4 explicitly states `UpcomingMilestoneQuery` never calls `scopeOverdue()`/`isOverdue()`, computes live state from `target_date`/`completed_date`/`status` directly, and has 8 concrete tests covering the exact matrix the review specified (pending-overdue, already-flagged-overdue, completed-excluded, cancelled-excluded, future-upcoming, null-date-excluded, cross-tenant-excluded, ordering-with-stable-id).

**7. No production code changed** by this planning revision — only `docs/superpowers/plans/2026-07-31-today-workspace-mvp.md` is edited and committed.

**8. Existing 12-task structure kept** — no task was added or removed; Tasks 1, 2, 3, 6, 7, 9, 11 were revised in place to absorb the DTO, enum-reuse, N+1, and navigation-authorization corrections, since each correction lives inside a task that already existed and was already independently reviewable.

---

Today Workspace MVP implementation plan is written. Ready for `superpowers:subagent-driven-development` or `superpowers:executing-plans`.
