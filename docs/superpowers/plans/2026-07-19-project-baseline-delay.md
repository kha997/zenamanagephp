# Project Baseline & Delay Flags Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Surface the existing (API-only) project baseline system in the web UI and flag late / forecast-late projects against their committed plan.

**Architecture:** One pure-PHP evaluator (`ProjectDelayStatus`), one new web POST action calling the existing `Src\CoreProject\Services\BaselineService::createBaselineFromProject()`, a `latestBaseline()` HasOne relation, and Blade additions (badge partial + "Kế hoạch gốc" card + list column). No migration, no JS.

**Tech Stack:** Laravel 12, Blade, PHPUnit. Existing `baselines` table + `BaselineService` untouched.

**Spec:** `docs/superpowers/specs/2026-07-19-project-baseline-delay-design.md`

## Global Constraints

- The `baselines` table has NO `tenant_id`: every web path MUST tenant-check the project via `App\Models\Project` BEFORE touching baselines. The cross-tenant feature test is the gate.
- Permissions: commit/re-commit = existing `rbac:project.update`; viewing flags needs nothing beyond the page's existing access. No new permission.
- Baselines are append-only from the web: no edit/delete endpoint anywhere in this plan.
- "Latest baseline" = newest `created_at` across both types.
- All date comparisons are date-only (substr 0,10 + CarbonImmutable), immune to time-of-day/timezone.
- Delay states exactly: `completed | no_baseline | late | forecast_late | on_track`; `days_late` is int for `late`/`forecast_late`, null otherwise.
- Run tests via `./vendor/bin/phpunit <path>` — never `php artisan test` (hybrid-vendor worktree crash).

---

### Task 1: `ProjectDelayStatus` evaluator

**Files:**
- Create: `app/Services/ProjectDelayStatus.php`
- Test: `tests/Unit/ProjectDelayStatusTest.php` (new file)

**Interfaces:**
- Consumes: `App\Models\Project`, `App\Models\Baseline` (attribute reads only — works on unsaved in-memory models; no DB).
- Produces: `public static function evaluate(Project $project, ?Baseline $baseline): array` returning `array{state: string, days_late: int|null, baseline: \App\Models\Baseline|null}`. Tasks 2–3 consume this.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/ProjectDelayStatusTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Baseline;
use App\Models\Project;
use App\Services\ProjectDelayStatus;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Pure evaluator — no DB, models are in-memory. Date comparisons are
 * date-only, so time-of-day must never change a verdict.
 */
class ProjectDelayStatusTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function project(array $attributes): Project
    {
        $project = new Project();
        $project->forceFill($attributes);

        return $project;
    }

    private function baseline(array $attributes): Baseline
    {
        $baseline = new Baseline();
        $baseline->forceFill($attributes);

        return $baseline;
    }

    public function test_completed_project_has_no_delay_flag(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'completed', 'end_date' => '2026-01-01']),
            $this->baseline(['end_date' => '2025-12-01'])
        );

        $this->assertSame('completed', $result['state']);
        $this->assertNull($result['days_late']);
    }

    public function test_no_baseline(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-08-01']),
            null
        );

        $this->assertSame('no_baseline', $result['state']);
        $this->assertNull($result['days_late']);
        $this->assertNull($result['baseline']);
    }

    public function test_late_when_today_is_past_committed_end(): void
    {
        Carbon::setTestNow('2026-07-19 23:59:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-09-01']),
            $this->baseline(['end_date' => '2026-07-09'])
        );

        $this->assertSame('late', $result['state']);
        $this->assertSame(10, $result['days_late']);
    }

    public function test_forecast_late_when_current_end_moved_past_committed_end(): void
    {
        Carbon::setTestNow('2026-07-19 00:01:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-09-15']),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('forecast_late', $result['state']);
        $this->assertSame(15, $result['days_late']);
    }

    public function test_on_track(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-08-20']),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('on_track', $result['state']);
        $this->assertNull($result['days_late']);
    }

    public function test_project_without_current_end_date_skips_forecast_rule(): void
    {
        Carbon::setTestNow('2026-07-19 10:00:00');

        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => null]),
            $this->baseline(['end_date' => '2026-08-31'])
        );

        $this->assertSame('on_track', $result['state']);
    }

    public function test_datetime_strings_compare_date_only(): void
    {
        Carbon::setTestNow('2026-07-19 00:00:01');

        // Committed end is "today" with a later time-of-day — NOT late.
        $result = ProjectDelayStatus::evaluate(
            $this->project(['status' => 'active', 'end_date' => '2026-07-19 18:00:00']),
            $this->baseline(['end_date' => '2026-07-19 23:00:00'])
        );

        $this->assertSame('on_track', $result['state']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/ProjectDelayStatusTest.php`
Expected: FAIL — `Class "App\Services\ProjectDelayStatus" not found`.

- [ ] **Step 3: Implement the evaluator**

Create `app/Services/ProjectDelayStatus.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Baseline;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Đánh giá trạng thái trễ tiến độ của dự án so với kế hoạch gốc đã chốt
 * (spec: docs/superpowers/specs/2026-07-19-project-baseline-delay-design.md).
 * Thuần PHP, không I/O — mọi so sánh là date-only để không lệch theo giờ/múi giờ.
 */
class ProjectDelayStatus
{
    public const STATE_COMPLETED = 'completed';
    public const STATE_NO_BASELINE = 'no_baseline';
    public const STATE_LATE = 'late';
    public const STATE_FORECAST_LATE = 'forecast_late';
    public const STATE_ON_TRACK = 'on_track';

    /**
     * @return array{state: string, days_late: int|null, baseline: Baseline|null}
     */
    public static function evaluate(Project $project, ?Baseline $baseline): array
    {
        if ((string) $project->status === 'completed') {
            return ['state' => self::STATE_COMPLETED, 'days_late' => null, 'baseline' => $baseline];
        }

        if ($baseline === null || $baseline->end_date === null) {
            return ['state' => self::STATE_NO_BASELINE, 'days_late' => null, 'baseline' => null];
        }

        $today = self::dateOnly(Carbon::now());
        $committedEnd = self::dateOnly($baseline->end_date);

        if ($today->greaterThan($committedEnd)) {
            return [
                'state' => self::STATE_LATE,
                'days_late' => (int) $committedEnd->diffInDays($today),
                'baseline' => $baseline,
            ];
        }

        if ($project->end_date !== null) {
            $currentEnd = self::dateOnly($project->end_date);

            if ($currentEnd->greaterThan($committedEnd)) {
                return [
                    'state' => self::STATE_FORECAST_LATE,
                    'days_late' => (int) $committedEnd->diffInDays($currentEnd),
                    'baseline' => $baseline,
                ];
            }
        }

        return ['state' => self::STATE_ON_TRACK, 'days_late' => null, 'baseline' => $baseline];
    }

    private static function dateOnly(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse(substr((string) $value, 0, 10))->startOfDay();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/ProjectDelayStatusTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/ProjectDelayStatus.php tests/Unit/ProjectDelayStatusTest.php
git commit -m "feat(project): delay-status evaluator against committed baseline"
```

---

### Task 2: `latestBaseline` relation + commit endpoint + feature tests

**Files:**
- Modify: `app/Models/Project.php` (add `HasOne` import at line ~11 and one relation after `baselines()` at line ~303)
- Modify: `app/Http/Controllers/Web/ProjectController.php` (add `storeBaseline()` method)
- Modify: `routes/web.php` (one route after `app.projects.update`, line ~368)
- Test: `tests/Feature/ProjectBaselineTest.php` (new file)

**Interfaces:**
- Consumes: `Src\CoreProject\Services\BaselineService::createBaselineFromProject(string $projectId, string $type, string $userId, ?string $note): Baseline` (existing, untouched).
- Produces: `Project::latestBaseline(): HasOne` (returns `App\Models\Baseline`); route `app.projects.baseline.store` (POST `/app/projects/{project}/baseline`). Task 3 consumes both.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/ProjectBaselineTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectBaselineTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $manager;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->manager = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['project.view', 'project.update']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'status' => 'active',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
        ]);

        // Real session for CSRF (TestCase refuses to fabricate one — 2026-07-15 note).
        $this->get('/login');
    }

    public function test_manager_commits_baseline(): void
    {
        $response = $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Chốt theo hợp đồng ký 01/07']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('baselines', [
            'project_id' => (string) $this->project->id,
            'type' => 'execution',
            'version' => 1,
        ]);
    }

    public function test_second_commit_appends_new_version(): void
    {
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        );
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Dời do phát sinh CR-12']
        );

        $this->assertDatabaseCount('baselines', 2);
        $this->assertDatabaseHas('baselines', ['project_id' => (string) $this->project->id, 'version' => 1]);
        $this->assertDatabaseHas('baselines', ['project_id' => (string) $this->project->id, 'version' => 2]);
    }

    public function test_viewer_cannot_commit(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['member'], ['project.view']);

        $this->actingAs($viewer)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        )->assertStatus(403);

        $this->assertDatabaseCount('baselines', 0);
    }

    public function test_cross_tenant_commit_is_404_and_writes_nothing(): void
    {
        $otherTenant = Tenant::factory()->create();
        $outsider = $this->createTenantUser($otherTenant, [], ['admin'], ['project.view', 'project.update']);

        $this->actingAs($outsider)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution']
        )->assertStatus(404);

        $this->assertDatabaseCount('baselines', 0);
    }

    public function test_invalid_type_is_rejected(): void
    {
        $this->actingAs($this->manager)->from(route('app.projects.show', $this->project->id))->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'wishful']
        )->assertSessionHasErrors('type');

        $this->assertDatabaseCount('baselines', 0);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/ProjectBaselineTest.php`
Expected: FAIL — route `app.projects.baseline.store` not defined.

- [ ] **Step 3: Add the relation**

In `app/Models/Project.php`: add to the imports (next to the existing `HasMany` import, line ~11):

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Then add directly after the existing `baselines()` relation (~line 306):

```php
    /**
     * Kế hoạch gốc chốt gần nhất (mọi loại) — dùng cho cờ trễ tiến độ.
     * Dùng App\Models\Baseline (canonical); bảng baselines không có tenant_id
     * nên mọi truy cập phải đi qua Project đã tenant-check.
     */
    public function latestBaseline(): HasOne
    {
        return $this->hasOne(\App\Models\Baseline::class, 'project_id')->latestOfMany('created_at');
    }
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, directly after the `app.projects.update` line (~368):

```php
        Route::post('/projects/{project}/baseline', [App\Http\Controllers\Web\ProjectController::class, 'storeBaseline'])->middleware('rbac:project.update')->name('projects.baseline.store');
```

(The enclosing group already carries the `app.` name prefix and `auth` + `tenant.isolation` middleware — same as the neighboring project routes.)

- [ ] **Step 5: Add the controller action**

In `app/Http/Controllers/Web/ProjectController.php`, add after the `show()` method:

```php
    public function storeBaseline(Request $request, string $projectId): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:contract,execution'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        // Tenant gate TRƯỚC khi gọi service: bảng baselines không có tenant_id,
        // và BaselineService chỉ findOrFail trơn theo project id.
        $project = AppProject::query()
            ->where('tenant_id', (string) Auth::user()?->tenant_id)
            ->findOrFail($projectId);

        app(\Src\CoreProject\Services\BaselineService::class)->createBaselineFromProject(
            (string) $project->id,
            $validated['type'],
            (string) Auth::id(),
            $validated['note'] ?? null
        );

        return redirect()
            ->route('app.projects.show', $project->id)
            ->with('success', 'Đã chốt kế hoạch gốc (phiên bản mới).');
    }
```

(`AppProject` is the existing alias `use App\Models\Project as AppProject;` at the top of this controller; `Auth` and `Request` are already imported.)

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/ProjectBaselineTest.php`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Project.php app/Http/Controllers/Web/ProjectController.php routes/web.php tests/Feature/ProjectBaselineTest.php
git commit -m "feat(project): web endpoint to commit baseline from current plan"
```

---

### Task 3: UI — badge partial, "Kế hoạch gốc" card, list column

**Files:**
- Create: `resources/views/projects/_delay-badge.blade.php`
- Modify: `resources/views/projects/show.blade.php` (add card after "Thông tin chung", ~line 43)
- Modify: `app/Http/Controllers/Web/ProjectController.php` (`show()`: load baseline + evaluate)
- Modify: `app/Http/Controllers/Web/AppController.php` (`projects()`: eager-load + evaluate per row)
- Modify: `resources/views/app/projects.blade.php` (add "Tiến độ KH" column)
- Test: append one render test to `tests/Feature/ProjectBaselineTest.php`

**Interfaces:**
- Consumes: `ProjectDelayStatus::evaluate()` (Task 1), `Project::latestBaseline()` + route `app.projects.baseline.store` (Task 2).
- Produces: nothing consumed later — final task.

- [ ] **Step 1: Write the failing render test**

Append to `tests/Feature/ProjectBaselineTest.php` (inside the class):

```php
    public function test_project_show_renders_baseline_card_and_delay_badge(): void
    {
        $this->actingAs($this->manager)->post(
            route('app.projects.baseline.store', $this->project->id),
            ['type' => 'execution', 'note' => 'Chốt lần đầu']
        );

        $response = $this->actingAs($this->manager)->get(
            route('app.projects.show', $this->project->id)
        );

        $response->assertOk();
        $response->assertSee('Kế hoạch gốc');
        $response->assertSee('Chốt lần đầu');
        // Project end (2026-12-31) equals committed end → on track.
        $response->assertSee('Đúng tiến độ');
    }

    public function test_project_show_offers_commit_button_when_no_baseline(): void
    {
        $response = $this->actingAs($this->manager)->get(
            route('app.projects.show', $this->project->id)
        );

        $response->assertOk();
        $response->assertSee('Chưa chốt kế hoạch gốc');
        $response->assertSee('Chốt kế hoạch từ ngày hiện tại');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/ProjectBaselineTest.php --filter renders_baseline_card`
Expected: FAIL — "Kế hoạch gốc" not found in response.

(`--filter` matches only the first new test; the second fails the same way — run both with `--filter "renders_baseline_card|offers_commit_button"` if preferred.)

- [ ] **Step 3: Create the badge partial**

Create `resources/views/projects/_delay-badge.blade.php`:

```blade
@php /** @var array{state: string, days_late: int|null} $delay */ @endphp
@if ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_LATE)
    <span class="inline-flex items-center rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700">Trễ {{ $delay['days_late'] }} ngày</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_FORECAST_LATE)
    <span class="inline-flex items-center rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700">Dự kiến trễ {{ $delay['days_late'] }} ngày</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_ON_TRACK)
    <span class="inline-flex items-center rounded bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Đúng tiến độ</span>
@elseif ($delay['state'] === \App\Services\ProjectDelayStatus::STATE_NO_BASELINE)
    <span class="inline-flex items-center rounded bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Chưa chốt KH</span>
@endif
{{-- state 'completed': cố ý không render gì — badge status sẵn có đã nói đủ --}}
```

- [ ] **Step 4: Wire `ProjectController::show()`**

Inside the existing `show()` method, after `$contracts = ...` and before the `return view(...)`, add:

```php
            $latestBaseline = \App\Models\Baseline::query()
                ->where('project_id', (string) $project->id)
                ->orderByDesc('created_at')
                ->first();
            $delay = \App\Services\ProjectDelayStatus::evaluate($project, $latestBaseline);
```

and extend the `return view('projects.show', [...])` array with:

```php
                'delay' => $delay,
```

(Direct query instead of the relation here because `show()` loads the project through `AppProject` with specific eager loads; one extra indexed query on a detail page is fine and keeps this diff minimal.)

- [ ] **Step 5: Add the card to `projects/show.blade.php`**

Directly after the closing `</x-ui.card>` of "Thông tin chung" (~line 43), add:

```blade
    <x-ui.card title="Kế hoạch gốc">
        @if (session('success'))
            <p class="mb-2 text-sm text-emerald-700">{{ session('success') }}</p>
        @endif

        @if ($delay['baseline'] !== null)
            <div class="operator-form-grid">
                <x-ui.field-value label="Bắt đầu (chốt)" :value="\Illuminate\Support\Carbon::parse($delay['baseline']->start_date)->format('d/m/Y')" />
                <x-ui.field-value label="Kết thúc (chốt)" :value="\Illuminate\Support\Carbon::parse($delay['baseline']->end_date)->format('d/m/Y')" />
                <x-ui.field-value label="Loại" :value="$delay['baseline']->type === 'contract' ? 'Hợp đồng' : 'Thực thi'" />
                <x-ui.field-value label="Phiên bản" :value="'v' . $delay['baseline']->version" />
                <x-ui.field-value label="Người chốt" :value="$delay['baseline']->creator?->name ?? '—'" />
                <x-ui.field-value label="Chốt lúc" :value="$delay['baseline']->created_at?->format('d/m/Y H:i') ?? '—'" />
            </div>
            @if ($delay['baseline']->note)
                <p class="mt-2 text-sm text-slate-600">{{ $delay['baseline']->note }}</p>
            @endif
            <div class="mt-3">@include('projects._delay-badge', ['delay' => $delay])</div>
        @else
            <p class="text-sm text-slate-500">Chưa chốt kế hoạch gốc — cờ trễ tiến độ chỉ hoạt động sau khi chốt.</p>
        @endif

        @if (auth()->user()?->hasPermission('project.update'))
            <form method="POST" action="{{ route('app.projects.baseline.store', $project->id) }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div class="operator-field w-40">
                    <label for="baseline_type">Loại kế hoạch</label>
                    <select id="baseline_type" name="type" class="operator-select">
                        <option value="execution">Thực thi</option>
                        <option value="contract">Hợp đồng</option>
                    </select>
                </div>
                <div class="operator-field flex-1 min-w-48">
                    <label for="baseline_note">Ghi chú / lý do chốt {{ $delay['baseline'] !== null ? 'lại' : '' }}</label>
                    <input id="baseline_note" name="note" type="text" class="operator-input" maxlength="1000">
                </div>
                <button type="submit" class="operator-button operator-button-secondary">Chốt kế hoạch từ ngày hiện tại</button>
            </form>
            @error('type')<p class="mt-1 text-sm text-rose-600">{{ $message }}</p>@enderror
        @endif
    </x-ui.card>
```

- [ ] **Step 6: Wire the projects list**

In `app/Http/Controllers/Web/AppController.php`, replace the body of `projects()` with:

```php
    public function projects()
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->with('latestBaseline')
            ->orderByDesc('updated_at')
            ->get(['id', 'tenant_id', 'name', 'code', 'status', 'progress', 'start_date', 'end_date', 'budget_total']);

        $delays = $projects->mapWithKeys(fn (Project $p) => [
            (string) $p->id => \App\Services\ProjectDelayStatus::evaluate($p, $p->latestBaseline),
        ]);

        return view('app.projects', [
            'projects' => $projects,
            'delays' => $delays,
        ]);
    }
```

In `resources/views/app/projects.blade.php`: change the headers line (~23) to:

```blade
            <x-ui.data-table :headers="['Dự án', 'Trạng thái', 'Tiến độ', 'Tiến độ KH', 'Bắt đầu', 'Kết thúc', 'Ngân sách']">
```

and inside the row loop add, directly after the existing "Tiến độ" (`progress`) cell:

```blade
                        <td>@include('projects._delay-badge', ['delay' => $delays[(string) $project->id]])</td>
```

- [ ] **Step 7: Run the full slice test sweep**

Run: `./vendor/bin/phpunit tests/Unit/ProjectDelayStatusTest.php tests/Feature/ProjectBaselineTest.php`
Expected: PASS (7 unit + 7 feature).

Run: `php artisan view:cache`
Expected: "Blade templates cached successfully".

- [ ] **Step 8: Commit**

```bash
git add resources/views/projects/_delay-badge.blade.php resources/views/projects/show.blade.php resources/views/app/projects.blade.php app/Http/Controllers/Web/ProjectController.php app/Http/Controllers/Web/AppController.php tests/Feature/ProjectBaselineTest.php
git commit -m "feat(project): baseline card and delay badges on project pages"
```
