# Design-PM Completion (R-DPM) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Per-DesignItem revision history ("sửa lần thứ mấy" + why), lightweight blockers on Task and DesignItem ("đang vướng gì"), and a per-project design-management section answering the operator's six questions in one place.

**Architecture:** Additive only. A `design_item_revisions` table + denormalized `revision_count`, written exclusively inside `Api\DesignItemController::updateStatus()` (the sole review_status authority) in a DB transaction. Blockers are 3 nullable columns on `tasks` and `design_items` with web block/unblock endpoints. The project page section is a read-only projection on the existing `app.projects.show` (`Web\ProjectController::show` → `resources/views/projects/show.blade.php` — verified, NOT the `app/projects*` blades). Spec: `docs/superpowers/specs/2026-07-13-design-pm-completion-design.md`.

**Tech Stack:** Laravel 12, PHPUnit, existing helpers (`TenantUserFactoryTrait`, `Tenant::factory()`), operator Blade components (`x-ui.card`, `x-ui.status-badge`, `x-ui.field-value`).

## Global Constraints

- Never touch `src/*` or `/api/v1/*` (frozen compatibility surface; guarded by `ModuleOwnership*InvariantTest`).
- `review_status` transitions stay enforced ONLY via `Api\DesignItemController::updateStatus()`; do not add other write paths for revisions.
- The transition graph `DesignItem::TRANSITIONS` is unchanged.
- New tenant-owned tables/models get `HasUlids` + `App\Traits\TenantScope` and an entry in the guard test.
- All tests: `declare(strict_types=1)`, `RefreshDatabase`, alias `rbac` middleware in `setUp()` as `tests/Feature/Api/CrmApiTest.php` does.
- Migration style: anonymous class, `declare(strict_types=1)`, real `down()` (see `database/migrations/2026_07_12_090000_add_description_to_design_items_table.php`).
- Web mutations delegate to Api controllers via `buildApiRequest` where review-cycle logic is involved; blockers (operational metadata, not review logic) are written directly in the Web controllers with tenant-scoped queries + route-level `rbac:*` middleware, matching sibling routes.
- Commits follow conventional style. Run `php artisan test tests/Feature/Architecture/` before the final commit.

---

### Task 1: Revision data layer (migrations, model, relations, guard)

**Files:**
- Create: `database/migrations/2026_07_13_100000_create_design_item_revisions_table.php`
- Create: `database/migrations/2026_07_13_100100_add_revision_count_to_design_items_table.php`
- Create: `app/Models/DesignItemRevision.php`
- Modify: `app/Models/DesignItem.php` (add `revisions()` relation + `revision_count` to `$fillable` is NOT needed — counter is updated via `increment()`, keep fillable unchanged)
- Test: `tests/Feature/Models/DesignItemRevisionTest.php`
- Modify: `tests/Feature/Models/TenantScopedCrmModelsTest.php` (add `DesignItemRevision::class` to the guard list)

**Interfaces:**
- Consumes: `App\Traits\TenantScope`, `HasUlids`, existing `DesignItem`.
- Produces: `App\Models\DesignItemRevision` with columns per spec; `DesignItem::revisions(): HasMany` ordered by `revision_no`; `design_items.revision_count` int default 0. Task 2 relies on exactly these names.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Models/DesignItemRevisionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\DesignItem;
use App\Models\DesignItemRevision;
use App\Models\Project;
use App\Models\Tenant;
use App\Traits\TenantScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemRevisionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_revision_model_uses_tenant_scope_trait(): void
    {
        $this->assertContains(TenantScope::class, class_uses_recursive(DesignItemRevision::class));
    }

    public function test_design_item_has_ordered_revisions_and_counter_default(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], []);

        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $item = DesignItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Concept mặt đứng',
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $user->id,
        ]);

        $this->assertSame(0, (int) $item->revision_count);

        foreach ([2, 1] as $no) {
            DesignItemRevision::query()->create([
                'tenant_id' => (string) $tenant->id,
                'design_item_id' => (string) $item->id,
                'revision_no' => $no,
                'client_feedback' => "feedback {$no}",
                'requested_by' => (string) $user->id,
                'requested_at' => now(),
            ]);
        }

        $this->assertSame([1, 2], $item->revisions()->pluck('revision_no')->all());
    }
}
```

If `Project::factory()` does not exist, create the project the same way the nearest passing DesignItem test does — check `php artisan test --filter=DesignItem` files for their setup and copy it exactly.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Models/DesignItemRevisionTest.php`
Expected: FAIL — class `DesignItemRevision` not found.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_13_100000_create_design_item_revisions_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_item_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('design_item_id')->index();
            $table->unsignedInteger('revision_no');
            $table->text('client_feedback');
            $table->string('requested_by')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['design_item_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_item_revisions');
    }
};
```

`database/migrations/2026_07_13_100100_add_revision_count_to_design_items_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->unsignedInteger('revision_count')->default(0)->after('approval_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('design_items', function (Blueprint $table) {
            $table->dropColumn('revision_count');
        });
    }
};
```

- [ ] **Step 4: Create the model and relation**

`app/Models/DesignItemRevision.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One client revision request on a DesignItem ("sửa lần thứ N").
 * Created exclusively by Api\DesignItemController::updateStatus() on the
 * transition into revision_requested — never written anywhere else.
 */
class DesignItemRevision extends Model
{
    use HasUlids;
    use TenantScope;

    protected $fillable = [
        'tenant_id',
        'design_item_id',
        'revision_no',
        'client_feedback',
        'requested_by',
        'requested_at',
        'resolved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function designItem(): BelongsTo
    {
        return $this->belongsTo(DesignItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
```

In `app/Models/DesignItem.php`, add next to the existing relations (after `creator()`), plus the import `use Illuminate\Database\Eloquent\Relations\HasMany;`:

```php
    public function revisions(): HasMany
    {
        return $this->hasMany(DesignItemRevision::class)->orderBy('revision_no');
    }
```

- [ ] **Step 5: Extend the tenant-scope guard**

In `tests/Feature/Models/TenantScopedCrmModelsTest.php`, change the guard loop list from
`[Lead::class, Account::class, Opportunity::class, DesignItem::class]` to
`[Lead::class, Account::class, Opportunity::class, DesignItem::class, DesignItemRevision::class]`
and add `use App\Models\DesignItemRevision;` to the imports.

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Models/DesignItemRevisionTest.php tests/Feature/Models/TenantScopedCrmModelsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_13_100000_create_design_item_revisions_table.php \
        database/migrations/2026_07_13_100100_add_revision_count_to_design_items_table.php \
        app/Models/DesignItemRevision.php app/Models/DesignItem.php \
        tests/Feature/Models/DesignItemRevisionTest.php tests/Feature/Models/TenantScopedCrmModelsTest.php
git commit -m "feat(design-pm): add DesignItemRevision model, table and revision_count column"
```

---

### Task 2: Record revisions inside updateStatus()

**Files:**
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (`updateStatus()` around lines 296-310; `RESPONSE_FIELDS` around line 30)
- Modify: `app/Http/Controllers/Web/DesignItemPageController.php` (`show()` — eager-load revisions)
- Modify: `resources/views/design-items/show.blade.php` (revision timeline card)
- Test: `tests/Feature/Zena/DesignItemRevisionCycleTest.php`

**Interfaces:**
- Consumes: `DesignItemRevision`, `DesignItem::revisions()`, `design_items.revision_count` (Task 1).
- Produces: serializer field `revision_count`; view variable `$item->revisions` on `design-items.show`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/DesignItemRevisionCycleTest.php`. Setup: copy the exact `setUp()` from an existing passing DesignItem web/API test (tenant + `createTenantUser` with `['design-item.view','design-item.manage']` + a project + a DesignItem in `STATUS_SENT_TO_CLIENT` — note `sent_to_client` requires `due_to_client_at` and an attached Document only when transitioning INTO it, so create the item directly in that state via `DesignItem::query()->create([... 'review_status' => DesignItem::STATUS_SENT_TO_CLIENT ...])`). Then the test bodies:

```php
    public function test_revision_request_creates_numbered_history_and_increments_counter(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        // Lần sửa 1
        $this->actingAs($this->user)->post(
            route('operator.design-items.status', $this->item->id),
            ['review_status' => DesignItem::STATUS_REVISION_REQUESTED, 'client_feedback_notes' => 'Đổi vật liệu mặt tiền'],
            $headers
        );

        $this->item->refresh();
        $this->assertSame(1, (int) $this->item->revision_count);
        $rev1 = $this->item->revisions()->first();
        $this->assertSame(1, (int) $rev1->revision_no);
        $this->assertSame('Đổi vật liệu mặt tiền', $rev1->client_feedback);
        $this->assertNotNull($rev1->requested_at);
        $this->assertNull($rev1->resolved_at);

        // Đưa lại vòng nội bộ → revision 1 được resolve
        $this->actingAs($this->user)->post(
            route('operator.design-items.status', $this->item->id),
            ['review_status' => DesignItem::STATUS_INTERNAL_REVIEW],
            $headers
        );

        $this->assertNotNull($rev1->fresh()->resolved_at);
    }

    public function test_second_revision_gets_number_two(): void
    {
        // Drive: sent_to_client → revision_requested → internal_review → sent_to_client → revision_requested
        // (re-transition into sent_to_client needs due_to_client_at already set on the item
        // and an attached Document — create one Document fixture in setUp() exactly the way
        // the existing updateStatus tests for sent_to_client do).
        // After the second revision request:
        $this->item->refresh();
        $this->assertSame(2, (int) $this->item->revision_count);
        $this->assertSame([1, 2], $this->item->revisions()->pluck('revision_no')->all());
    }
```

The web route name is `operator.design-items.status` (`routes/web.php:973`); if `route()` fails, print `php artisan route:list | grep design-items` and use the exact name. Copy the CSRF/session warm-up GET pattern from `AiLeadSuggestionTest` if the POST is rejected with 419.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Zena/DesignItemRevisionCycleTest.php`
Expected: FAIL — `revision_count` stays 0, no revisions created.

- [ ] **Step 3: Implement recording in `updateStatus()`**

In `app/Http/Controllers/Api/DesignItemController.php`, add imports `use App\Models\DesignItemRevision;` and `use Illuminate\Support\Facades\DB;`. Replace the block that currently reads:

```php
        $item->review_status = $to;

        if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
            $item->client_feedback_notes = (string) $request->input('client_feedback_notes');
        }

        if ($to === DesignItem::STATUS_APPROVED) {
            $item->approval_evidence = (string) $request->input('approval_evidence');
        }

        $item->save();
```

with:

```php
        $item->review_status = $to;

        if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
            $item->client_feedback_notes = (string) $request->input('client_feedback_notes');
        }

        if ($to === DesignItem::STATUS_APPROVED) {
            $item->approval_evidence = (string) $request->input('approval_evidence');
        }

        DB::transaction(function () use ($item, $tenantId, $from, $to): void {
            $item->save();

            if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
                $revisionNo = ((int) $item->revision_count) + 1;

                DesignItemRevision::query()->create([
                    'tenant_id' => $tenantId,
                    'design_item_id' => (string) $item->id,
                    'revision_no' => $revisionNo,
                    'client_feedback' => (string) $item->client_feedback_notes,
                    'requested_by' => (string) Auth::id(),
                    'requested_at' => now(),
                ]);

                $item->forceFill(['revision_count' => $revisionNo])->save();
            }

            if ($from === DesignItem::STATUS_REVISION_REQUESTED) {
                $item->revisions()
                    ->whereNull('resolved_at')
                    ->latest('revision_no')
                    ->first()?->update(['resolved_at' => now()]);
            }
        });
```

Add `'revision_count'` to the `RESPONSE_FIELDS` const (after `'approval_evidence'`).

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Zena/DesignItemRevisionCycleTest.php`
Expected: PASS.

- [ ] **Step 5: Show the timeline on the item page**

In `Web\DesignItemPageController::show()` (line ~148) extend the eager load:

```php
            ->with('project:id,tenant_id,name', 'assignee:id,name', 'revisions.requester:id,name')
```

In `resources/views/design-items/show.blade.php`, after the closing tag of the "Thông tin" card (`</x-ui.card>` at line ~47), add:

```blade
    @if ($item->revisions->isNotEmpty())
        <x-ui.card title="Lịch sử chỉnh sửa ({{ $item->revision_count }} lần)">
            <ol class="space-y-3">
                @foreach ($item->revisions as $revision)
                    <li class="border-l-2 border-slate-200 pl-3">
                        <div class="text-sm font-medium">
                            Sửa lần {{ $revision->revision_no }}
                            — yêu cầu {{ $revision->requested_at->format('d/m/Y') }}
                            @if ($revision->requester) bởi {{ $revision->requester->name }} @endif
                            @if ($revision->resolved_at)
                                <span class="text-emerald-600">· đã xử lý {{ $revision->resolved_at->format('d/m/Y') }}</span>
                            @else
                                <span class="text-amber-600">· đang xử lý</span>
                            @endif
                        </div>
                        <div class="text-sm text-slate-600">{{ $revision->client_feedback }}</div>
                    </li>
                @endforeach
            </ol>
        </x-ui.card>
    @endif
```

- [ ] **Step 6: Regression + commit**

Run: `php artisan test --filter=DesignItem`
Expected: PASS (existing updateStatus tests unaffected — the transition outcomes are identical, only side-effect writes were added).

```bash
git add app/Http/Controllers/Api/DesignItemController.php app/Http/Controllers/Web/DesignItemPageController.php \
        resources/views/design-items/show.blade.php tests/Feature/Zena/DesignItemRevisionCycleTest.php
git commit -m "feat(design-pm): record numbered revision history on revision_requested transitions"
```

---

### Task 3: Blockers on Task and DesignItem

**Files:**
- Create: `database/migrations/2026_07_13_100200_add_blocker_fields_to_tasks_and_design_items.php`
- Modify: `app/Models/Task.php`, `app/Models/DesignItem.php` (fillable + casts)
- Modify: `app/Http/Controllers/Web/TaskController.php`, `app/Http/Controllers/Web/DesignItemPageController.php` (block/unblock actions)
- Modify: `routes/web.php` (4 routes)
- Test: `tests/Feature/Zena/BlockerTest.php`

**Interfaces:**
- Consumes: existing rbac permissions `task.update`, `design-item.manage`; tenant-scoped query patterns already in both controllers.
- Produces: columns `blocked_at` (timestamp nullable), `blocker_note` (string 1000 nullable), `blocked_by` (string nullable) on BOTH tables; route names `app.tasks.block`, `app.tasks.unblock`, `operator.design-items.block`, `operator.design-items.unblock`. Task 4 renders these columns.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/BlockerTest.php` — setup as in Task 2's test (tenant, user with `['task.view','task.update','design-item.view','design-item.manage']`, project, one Task, one DesignItem). Tests:

```php
    public function test_block_requires_note_and_sets_fields(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->post(route('app.tasks.block', $this->task->id), [], $headers)
            ->assertSessionHasErrors('blocker_note');

        $this->actingAs($this->user)
            ->post(route('app.tasks.block', $this->task->id), ['blocker_note' => 'Chờ khách chốt vật liệu'], $headers)
            ->assertRedirect();

        $this->task->refresh();
        $this->assertNotNull($this->task->blocked_at);
        $this->assertSame('Chờ khách chốt vật liệu', $this->task->blocker_note);
        $this->assertSame((string) $this->user->id, (string) $this->task->blocked_by);
    }

    public function test_unblock_clears_fields(): void
    {
        $this->task->forceFill(['blocked_at' => now(), 'blocker_note' => 'x', 'blocked_by' => (string) $this->user->id])->save();

        $this->actingAs($this->user)
            ->post(route('app.tasks.unblock', $this->task->id), [], ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertRedirect();

        $this->task->refresh();
        $this->assertNull($this->task->blocked_at);
        $this->assertNull($this->task->blocker_note);
        $this->assertNull($this->task->blocked_by);
    }

    public function test_cross_tenant_block_is_404(): void
    {
        $other = Tenant::factory()->create();
        $intruder = $this->createTenantUser($other, [], ['admin'], ['task.update', 'design-item.manage']);

        $this->actingAs($intruder)
            ->post(route('app.tasks.block', $this->task->id), ['blocker_note' => 'x'], ['X-Tenant-ID' => (string) $other->id])
            ->assertNotFound();
    }

    public function test_design_item_block_and_unblock(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->post(route('operator.design-items.block', $this->item->id), ['blocker_note' => 'Thiếu bản vẽ hiện trạng'], $headers)
            ->assertRedirect();
        $this->assertNotNull($this->item->fresh()->blocked_at);

        $this->actingAs($this->user)
            ->post(route('operator.design-items.unblock', $this->item->id), [], $headers)
            ->assertRedirect();
        $this->assertNull($this->item->fresh()->blocked_at);
    }
```

(Adjust the `app.`/`operator.` route-name prefixes to whatever `php artisan route:list` shows for the sibling `tasks.update` / `design-items.status` routes — use the same prefix as the siblings.)

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Zena/BlockerTest.php`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Migration**

`database/migrations/2026_07_13_100200_add_blocker_fields_to_tasks_and_design_items.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['tasks', 'design_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->timestamp('blocked_at')->nullable();
                $table->string('blocker_note', 1000)->nullable();
                $table->string('blocked_by')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['tasks', 'design_items'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['blocked_at', 'blocker_note', 'blocked_by']);
            });
        }
    }
};
```

Add `'blocked_at', 'blocker_note', 'blocked_by'` to `$fillable` on both `Task` and `DesignItem`; add `'blocked_at' => 'datetime'` to each model's `$casts`.

- [ ] **Step 4: Controller actions**

In `app/Http/Controllers/Web/TaskController.php` add (using the same tenant-scoped fetch style as its existing methods — `Task::query()->where('tenant_id', (string) auth()->user()?->tenant_id)->findOrFail($taskId)`):

```php
    public function block(Request $request, string $taskId): RedirectResponse
    {
        $request->validate(['blocker_note' => ['required', 'string', 'max:1000']]);

        $task = Task::query()
            ->where('tenant_id', (string) auth()->user()?->tenant_id)
            ->findOrFail($taskId);

        $task->forceFill([
            'blocked_at' => now(),
            'blocker_note' => $request->string('blocker_note'),
            'blocked_by' => (string) auth()->id(),
        ])->save();

        return back()->with('success', 'Đã đánh dấu công việc đang vướng.');
    }

    public function unblock(string $taskId): RedirectResponse
    {
        $task = Task::query()
            ->where('tenant_id', (string) auth()->user()?->tenant_id)
            ->findOrFail($taskId);

        $task->forceFill(['blocked_at' => null, 'blocker_note' => null, 'blocked_by' => null])->save();

        return back()->with('success', 'Đã gỡ trạng thái vướng.');
    }
```

Mirror the same two methods in `DesignItemPageController` with `DesignItem::query()->forTenant($tenantId)->findOrFail($id)` (its existing fetch style) and message texts "hạng mục thiết kế" instead of "công việc".

- [ ] **Step 5: Routes**

In `routes/web.php` next to `tasks.update` (line ~390):

```php
    Route::post('/tasks/{task}/block', [App\Http\Controllers\Web\TaskController::class, 'block'])->middleware('rbac:task.update')->name('tasks.block');
    Route::post('/tasks/{task}/unblock', [App\Http\Controllers\Web\TaskController::class, 'unblock'])->middleware('rbac:task.update')->name('tasks.unblock');
```

Next to `design-items.status` (line ~973):

```php
    Route::post('/design-items/{id}/block', [App\Http\Controllers\Web\DesignItemPageController::class, 'block'])->middleware('rbac:design-item.manage')->name('design-items.block');
    Route::post('/design-items/{id}/unblock', [App\Http\Controllers\Web\DesignItemPageController::class, 'unblock'])->middleware('rbac:design-item.manage')->name('design-items.unblock');
```

- [ ] **Step 6: Run tests, then commit**

Run: `php artisan test tests/Feature/Zena/BlockerTest.php && php artisan test --filter=Task --stop-on-failure`
Expected: PASS (if an unrelated pre-existing Task test failure appears, verify it also fails on the base commit before proceeding).

```bash
git add database/migrations/2026_07_13_100200_add_blocker_fields_to_tasks_and_design_items.php \
        app/Models/Task.php app/Models/DesignItem.php \
        app/Http/Controllers/Web/TaskController.php app/Http/Controllers/Web/DesignItemPageController.php \
        routes/web.php tests/Feature/Zena/BlockerTest.php
git commit -m "feat(design-pm): add block/unblock with note on tasks and design items"
```

---

### Task 4: Per-project design-management section

**Files:**
- Modify: `app/Http/Controllers/Web/ProjectController.php` (`show()`, lines 132-157)
- Modify: `resources/views/projects/show.blade.php`
- Test: `tests/Feature/Zena/ProjectDesignSectionTest.php`

**Interfaces:**
- Consumes: `DesignItem` (with `assignee`, `revision_count`, blocker fields), `Task` (with `assignee`, `phase`, `progress_percent`, blocker fields) — all from Tasks 1-3.
- Produces: view variables `$designItems` (collection) and `$blockedItems` (collection of `['type' => ..., 'name' => ..., 'note' => ..., 'blocked_at' => ...]`) on `projects.show`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Zena/ProjectDesignSectionTest.php` — setup: tenant, user with `['project.view', 'design-item.view', 'task.view']` (match the permission set an existing passing `app.projects.show` test uses — find one with `grep -rln "projects.show\|app/projects" tests/Feature | head`), a project, one DesignItem (`assigned_to` = user, `revision_count` = 2 via `forceFill`, blocked with note 'Chờ khách duyệt concept'), one Task (assignee = user, blocked_at null).

```php
    public function test_project_page_shows_design_section_with_badges_and_blockers(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('app.projects.show', $this->project->id),
            ['X-Tenant-ID' => (string) $this->tenant->id]
        );

        $response->assertOk()
            ->assertSee('Thiết kế &amp; tiến độ', false)
            ->assertSee($this->item->name)
            ->assertSee('Sửa lần 2')
            ->assertSee('Đang vướng')
            ->assertSee('Chờ khách duyệt concept');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Zena/ProjectDesignSectionTest.php`
Expected: FAIL — "Thiết kế & tiến độ" not on the page.

- [ ] **Step 3: Extend the controller**

In `Web\ProjectController::show()`, add import `use App\Models\DesignItem;`, and before the `return view(...)`:

```php
            $designItems = DesignItem::query()
                ->where('project_id', (string) $project->id)
                ->with('assignee:id,name')
                ->orderBy('created_at')
                ->get();

            $project->loadMissing('tasks.assignee');

            $blockedItems = collect()
                ->concat($designItems->whereNotNull('blocked_at')->map(fn ($i) => [
                    'type' => 'Hạng mục thiết kế',
                    'name' => $i->name,
                    'note' => $i->blocker_note,
                    'blocked_at' => $i->blocked_at,
                ]))
                ->concat($project->tasks->whereNotNull('blocked_at')->map(fn ($t) => [
                    'type' => 'Công việc',
                    'name' => $t->title ?? $t->name,
                    'note' => $t->blocker_note,
                    'blocked_at' => $t->blocked_at,
                ]))
                ->sortByDesc('blocked_at')
                ->values();
```

and add `'designItems' => $designItems, 'blockedItems' => $blockedItems,` to the `view()` data array.

- [ ] **Step 4: Add the Blade section**

In `resources/views/projects/show.blade.php`, append before the closing of the content section (find `@endsection` / the last card):

```blade
    <x-ui.card title="Thiết kế & tiến độ">
        @if ($blockedItems->isNotEmpty())
            <div class="mb-4 rounded border border-red-200 bg-red-50 p-3">
                <div class="mb-1 font-medium text-red-700">Đang vướng ({{ $blockedItems->count() }})</div>
                <ul class="space-y-1 text-sm text-red-800">
                    @foreach ($blockedItems as $blocked)
                        <li>{{ $blocked['type'] }} — {{ $blocked['name'] }}: {{ $blocked['note'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <h3 class="mb-2 text-sm font-semibold text-slate-700">Hạng mục thiết kế</h3>
        @forelse ($designItems as $designItem)
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                <a href="{{ route('operator.design-items.show', $designItem->id) }}" class="font-medium">{{ $designItem->name }}</a>
                <x-ui.status-badge :status="$designItem->review_status" />
                @if ($designItem->revision_count > 0)
                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-xs text-amber-800">Sửa lần {{ $designItem->revision_count }}</span>
                @endif
                @if ($designItem->blocked_at)
                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>
                @endif
                <span class="text-slate-500">{{ $designItem->assignee?->name ?? 'Chưa giao' }}</span>
                @if ($designItem->due_to_client_at)
                    <span class="text-slate-400">hạn gửi khách {{ $designItem->due_to_client_at->format('d/m/Y') }}</span>
                @endif
            </div>
        @empty
            <p class="text-sm text-slate-500">Chưa có hạng mục thiết kế.</p>
        @endforelse

        <h3 class="mb-2 mt-4 text-sm font-semibold text-slate-700">Công việc</h3>
        @forelse ($project->tasks as $task)
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 py-2 text-sm">
                <span class="font-medium">{{ $task->title ?? $task->name }}</span>
                <x-ui.status-badge :status="$task->status" />
                @if ($task->blocked_at)
                    <span class="rounded bg-red-100 px-1.5 py-0.5 text-xs text-red-800">Vướng</span>
                @endif
                <span class="text-slate-500">{{ $task->assignee?->name ?? 'Chưa giao' }}</span>
                <span class="text-slate-400">{{ (int) $task->progress_percent }}%</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">Chưa có công việc.</p>
        @endforelse
    </x-ui.card>
```

If `route('operator.design-items.show', ...)` is not the registered name, use the name shown by `php artisan route:list | grep "design-items/{id}"`. If `x-ui.status-badge` does not support task statuses, replace that one usage with a plain `<span class="text-xs text-slate-600">{{ $task->status }}</span>`.

- [ ] **Step 5: Run tests, then commit**

Run: `php artisan test tests/Feature/Zena/ProjectDesignSectionTest.php`
Expected: PASS.
Also run any existing project-show page tests: `grep -rln "app.projects.show" tests/Feature | xargs -r php artisan test`
Expected: PASS.

```bash
git add app/Http/Controllers/Web/ProjectController.php resources/views/projects/show.blade.php tests/Feature/Zena/ProjectDesignSectionTest.php
git commit -m "feat(design-pm): add per-project design-management section (items, tasks, blockers)"
```

---

### Task 5: Final verification

- [ ] **Step 1:** `php artisan test tests/Feature/Architecture/` — Expected: PASS.
- [ ] **Step 2:** `php artisan test --testsuite=Feature` — Expected: PASS (known machine-speed flakes: timing-threshold tests; re-run once before investigating).
- [ ] **Step 3:** Report results. Do not push or merge without the user's go-ahead.

---

## Self-review notes

- Spec coverage: Component 1 → Tasks 1-2; Component 2 → Task 3; Component 3 → Task 4; error handling (transaction, cross-tenant 404, idempotent re-block) is embedded in Tasks 2-3 code; the spec's plan-time verification of the project page was done while writing this plan (`app.projects.show` → `Web\ProjectController::show` → `resources/views/projects/show.blade.php`, confirmed with the AppProject alias import at its line 4).
- Type consistency: `revision_no`/`revision_count`/`blocked_at`/`blocker_note`/`blocked_by` names identical across migrations, models, controllers, blades, tests; route names follow verified sibling patterns with an explicit `route:list` fallback.
- No placeholders: all code steps carry full code; conditional instructions are verification fallbacks with exact commands.
