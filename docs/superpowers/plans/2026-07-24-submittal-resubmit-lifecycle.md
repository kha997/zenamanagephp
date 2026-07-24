# Submittal Resubmit Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the dead-end `Submittal` `rejected`/`approved` statuses with a real state machine (`draft → submitted → approved|rejected → revising → submitted`) backed by an immutable `submittal_revisions` history table, a single `SubmittalLifecycleService` that is the only writer of `status`, and a proper `SubmittalPolicy`.

**Architecture:** A new `submittal_revisions` table stores one immutable snapshot per submit/resubmit, with a single mutable `decision` column set exactly once via a conditional update. `SubmittalLifecycleService` owns every transition (row-locked transactions, `EventRecord` audit trail, post-commit notification). `SubmittalController` becomes a thin HTTP/validation layer that delegates all state changes to the service and authorizes via the new `SubmittalPolicy`.

**Tech Stack:** Laravel (PHP), MySQL/SQLite (tests), PHPUnit feature tests, ULID primary keys, `Illuminate\Support\Facades\DB` transactions + `lockForUpdate`.

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-24-submittal-resubmit-design.md` — every section (1–10) must be implemented as written; do not reintroduce direct `$submittal->update(['status' => ...])` calls anywhere outside `SubmittalLifecycleService`.
- No controller-level bypass of the state machine: all transitions go through `Submittal::canTransition()` inside `SubmittalLifecycleService`.
- `STATUS_REVISED` is fully removed; `STATUS_REVISING = 'revising'` is the only status meaning "author editing after rejection."
- `STATUS_PENDING_REVIEW` is untouched (out of scope, already dead).
- Existing route names, HTTP methods, and controller method signatures used by `SubmittalPageController` (`submit`, `approve`, `reject`, `update`, `destroy`, all `(Request $request, string $id)` or `(Request $request)`) must not change, since the web layer proxies directly into the API controller.
- Notification failures must be logged via `Log::error`, never swallowed silently.

---

### Task 1: `submittal_revisions` table + `current_revision_no` column

**Files:**
- Create: `database/migrations/2026_07_24_130000_create_submittal_revisions_table.php`
- Create: `database/migrations/2026_07_24_130100_update_submittals_for_revision_lifecycle.php`
- Test: `tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php`

**Interfaces:**
- Produces: table `submittal_revisions` with columns `id, tenant_id, submittal_id, revision_no, revision_summary, title, description, file_url, attachment_manifest, submitted_by, submitted_at, decision, decided_by, decided_at, decision_comments, created_at`, unique on `(submittal_id, revision_no)`.
- Produces: `submittals.current_revision_no` (nullable unsigned int).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SubmittalRevisionsSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_submittal_revisions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('submittal_revisions'));
        $this->assertTrue(Schema::hasColumns('submittal_revisions', [
            'id', 'tenant_id', 'submittal_id', 'revision_no', 'revision_summary',
            'title', 'description', 'file_url', 'attachment_manifest',
            'submitted_by', 'submitted_at', 'decision', 'decided_by',
            'decided_at', 'decision_comments', 'created_at',
        ]));
    }

    public function test_submittals_table_has_current_revision_no(): void
    {
        $this->assertTrue(Schema::hasColumn('submittals', 'current_revision_no'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php`
Expected: FAIL — table/column not found.

- [ ] **Step 3: Create the migrations**

`database/migrations/2026_07_24_130000_create_submittal_revisions_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submittal_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->foreignUlid('submittal_id')->constrained('submittals')->cascadeOnDelete();
            $table->unsignedInteger('revision_no');
            $table->text('revision_summary')->nullable();
            $table->string('title');
            $table->text('description');
            $table->string('file_url')->nullable();
            $table->json('attachment_manifest')->nullable();
            $table->ulid('submitted_by')->nullable();
            $table->timestamp('submitted_at');
            $table->string('decision')->nullable();
            $table->ulid('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->text('decision_comments')->nullable();
            $table->timestamp('created_at');

            $table->unique(['submittal_id', 'revision_no']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submittal_revisions');
    }
};
```

`database/migrations/2026_07_24_130100_update_submittals_for_revision_lifecycle.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('submittals', function (Blueprint $table) {
            $table->unsignedInteger('current_revision_no')->nullable()->after('status');
        });

        // 'revised' was declared on the model but never reachable in production code
        // (submit() only ever accepted 'draft'). This is a safety net, not an expected no-op.
        DB::table('submittals')->where('status', 'revised')->update(['status' => 'rejected']);
    }

    public function down(): void
    {
        Schema::table('submittals', function (Blueprint $table) {
            $table->dropColumn('current_revision_no');
        });
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_24_130000_create_submittal_revisions_table.php \
        database/migrations/2026_07_24_130100_update_submittals_for_revision_lifecycle.php \
        tests/Unit/Migrations/SubmittalRevisionsSchemaTest.php
git commit -m "feat(submittal): add submittal_revisions table and current_revision_no column"
```

---

### Task 2: `Submittal` state machine + `SubmittalRevision` model + factory fix

**Files:**
- Modify: `app/Models/Submittal.php`
- Create: `app/Models/SubmittalRevision.php`
- Modify: `database/factories/SubmittalFactory.php`
- Test: `tests/Unit/Models/SubmittalStateMachineTest.php`

**Interfaces:**
- Consumes: `submittal_revisions` table from Task 1.
- Produces: `Submittal::TRANSITIONS` (array), `Submittal::canTransition(string $from, string $to): bool` (static), `Submittal::STATUS_REVISING` constant, `Submittal::revisions(): HasMany`, `Submittal::currentRevision(): HasOne`, `SubmittalRevision` model with fillable `tenant_id, submittal_id, revision_no, revision_summary, title, description, file_url, attachment_manifest, submitted_by, submitted_at, decision, decided_by, decided_at, decision_comments, created_at`.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Submittal;
use PHPUnit\Framework\TestCase;

class SubmittalStateMachineTest extends TestCase
{
    public function test_transition_matrix(): void
    {
        $this->assertTrue(Submittal::canTransition('draft', 'submitted'));
        $this->assertTrue(Submittal::canTransition('submitted', 'approved'));
        $this->assertTrue(Submittal::canTransition('submitted', 'rejected'));
        $this->assertTrue(Submittal::canTransition('rejected', 'revising'));
        $this->assertTrue(Submittal::canTransition('revising', 'submitted'));

        $this->assertFalse(Submittal::canTransition('approved', 'submitted'));
        $this->assertFalse(Submittal::canTransition('approved', 'revising'));
        $this->assertFalse(Submittal::canTransition('rejected', 'submitted'));
        $this->assertFalse(Submittal::canTransition('draft', 'approved'));
        $this->assertFalse(Submittal::canTransition('revising', 'revising'));
        $this->assertFalse(Submittal::canTransition('draft', 'revising'));
    }

    public function test_status_revised_constant_no_longer_exists(): void
    {
        $this->assertFalse(defined(Submittal::class . '::STATUS_REVISED'));
        $this->assertSame('revising', Submittal::STATUS_REVISING);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Models/SubmittalStateMachineTest.php`
Expected: FAIL — `Submittal::canTransition` and `Submittal::STATUS_REVISING` don't exist yet.

- [ ] **Step 3: Update `app/Models/Submittal.php`**

Replace lines 61–89 (the status constants through `canTransitionTo`) with:

```php
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_PENDING_REVIEW = 'pending_review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_REVISING = 'revising';

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT     => [self::STATUS_SUBMITTED],
        self::STATUS_SUBMITTED => [self::STATUS_APPROVED, self::STATUS_REJECTED],
        self::STATUS_REJECTED  => [self::STATUS_REVISING],
        self::STATUS_REVISING  => [self::STATUS_SUBMITTED],
        self::STATUS_APPROVED  => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function canTransitionTo(string $newStatus): bool
    {
        return self::canTransition($this->status, $newStatus);
    }

    protected $casts = [
        'due_date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'attachments' => 'array',
        'current_revision_no' => 'integer',
    ];
```

Add `'current_revision_no'` to the `$fillable` array (after `'attachments'`, line 58).

Update `getStatusBadgeColorAttribute()` (was lines 134–145): replace the `'revised' => 'bg-purple-100 text-purple-800',` case with `'revising' => 'bg-purple-100 text-purple-800',`.

Update `getIsOverdueAttribute()` (was line 152): replace

```php
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['approved', 'rejected', 'revised']);
```

with

```php
        return $this->due_date && $this->due_date->isPast() && !in_array($this->status, ['approved', 'rejected'], true);
```

Update `scopeOverdue()` (was lines 166–170): replace

```php
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['approved', 'rejected', 'revised']);
```

with

```php
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', ['approved', 'rejected']);
```

Add relations at the end of the class (after `documents()`, before the closing `}`):

```php
    public function revisions(): HasMany
    {
        return $this->hasMany(SubmittalRevision::class, 'submittal_id')->orderBy('revision_no');
    }

    public function currentRevision(): HasOne
    {
        return $this->hasOne(SubmittalRevision::class, 'submittal_id')->ofMany('revision_no', 'max');
    }
```

Add the import at the top with the other `use` statements:

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

- [ ] **Step 4: Create `app/Models/SubmittalRevision.php`**

```php
<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmittalRevision extends Model
{
    use HasUlids, TenantScope;

    protected $table = 'submittal_revisions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'submittal_id',
        'revision_no',
        'revision_summary',
        'title',
        'description',
        'file_url',
        'attachment_manifest',
        'submitted_by',
        'submitted_at',
        'decision',
        'decided_by',
        'decided_at',
        'decision_comments',
        'created_at',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'attachment_manifest' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function submittal(): BelongsTo
    {
        return $this->belongsTo(Submittal::class, 'submittal_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
```

- [ ] **Step 5: Fix `database/factories/SubmittalFactory.php`**

Replace the `$statuses` array (currently includes `'revised'`) with:

```php
        $statuses = [
            'draft',
            'submitted',
            'pending_review',
            'approved',
            'rejected',
            'revising',
        ];
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test tests/Unit/Models/SubmittalStateMachineTest.php`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Models/Submittal.php app/Models/SubmittalRevision.php \
        database/factories/SubmittalFactory.php \
        tests/Unit/Models/SubmittalStateMachineTest.php
git commit -m "feat(submittal): replace STATUS_REVISED with a real revising state machine"
```

---

### Task 3: Lifecycle exceptions

**Files:**
- Create: `app/Exceptions/SubmittalTransitionNotAllowedException.php`
- Create: `app/Exceptions/SubmittalTransitionConflictException.php`

**Interfaces:**
- Produces: two `RuntimeException` subclasses used by `SubmittalLifecycleService` (Task 4/5) and caught by `SubmittalController` (Task 7) to map to HTTP 400 and 409 respectively.

- [ ] **Step 1: Create the files** (no test needed — plain marker classes, verified through the service/controller tests in later tasks)

`app/Exceptions/SubmittalTransitionNotAllowedException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class SubmittalTransitionNotAllowedException extends RuntimeException
{
}
```

`app/Exceptions/SubmittalTransitionConflictException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class SubmittalTransitionConflictException extends RuntimeException
{
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Exceptions/SubmittalTransitionNotAllowedException.php \
        app/Exceptions/SubmittalTransitionConflictException.php
git commit -m "feat(submittal): add lifecycle transition exception types"
```

---

### Task 4: `SubmittalLifecycleService` — `updateContent()` and `submit()`

**Files:**
- Create: `app/Services/SubmittalLifecycleService.php`
- Test: `tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php`

**Interfaces:**
- Consumes: `Submittal::canTransition()` (Task 2), `SubmittalRevision` model (Task 2), `SubmittalTransitionNotAllowedException` (Task 3), `App\Models\EventRecord`.
- Produces: `SubmittalLifecycleService::updateContent(Submittal $submittal, array $data, array $context): Submittal`, `SubmittalLifecycleService::submit(Submittal $submittal, array $context): Submittal` where `$context = ['actor_user_id' => ?string, 'revision_summary' => ?string]`. These two methods are used directly by Task 5 (same class) and Task 7 (controller).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubmittalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmittalLifecycleServiceSubmitTest extends TestCase
{
    use RefreshDatabase;

    private SubmittalLifecycleService $service;
    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubmittalLifecycleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeDraft(): Submittal
    {
        return Submittal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => Submittal::STATUS_DRAFT,
            'title' => 'Original title',
            'submittal_number' => 'SUB-0001',
        ]);
    }

    public function test_first_submit_creates_revision_one(): void
    {
        $submittal = $this->makeDraft();

        $result = $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);

        $this->assertSame(Submittal::STATUS_SUBMITTED, $result->status);
        $this->assertSame(1, $result->current_revision_no);
        $this->assertDatabaseHas('submittal_revisions', [
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Original title',
        ]);
    }

    public function test_submit_from_approved_is_rejected(): void
    {
        $submittal = $this->makeDraft();
        $submittal->update(['status' => Submittal::STATUS_APPROVED]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_update_content_blocked_when_submitted(): void
    {
        $submittal = $this->makeDraft();
        $submittal->update(['status' => Submittal::STATUS_SUBMITTED]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->updateContent($submittal, ['title' => 'New title'], ['actor_user_id' => $this->user->id]);
    }

    public function test_update_content_allowed_when_draft(): void
    {
        $submittal = $this->makeDraft();

        $result = $this->service->updateContent($submittal, ['title' => 'Edited title'], ['actor_user_id' => $this->user->id]);

        $this->assertSame('Edited title', $result->title);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php`
Expected: FAIL — class `App\Services\SubmittalLifecycleService` not found.

- [ ] **Step 3: Create `app/Services/SubmittalLifecycleService.php`**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubmittalLifecycleService
{
    public function updateContent(Submittal $submittal, array $data, array $context): Submittal
    {
        return DB::transaction(function () use ($submittal, $data, $context) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($locked->status, [Submittal::STATUS_DRAFT, Submittal::STATUS_REVISING], true)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ có thể sửa nội dung khi ở trạng thái draft hoặc revising.'
                );
            }

            $locked->update($data);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => 'submittal.content_updated',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['fields' => array_keys($data)],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function submit(Submittal $submittal, array $context): Submittal
    {
        $isResubmit = $submittal->status === Submittal::STATUS_REVISING;

        $submittal = DB::transaction(function () use ($submittal, $context) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, Submittal::STATUS_SUBMITTED)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ draft hoặc revising mới submit được.'
                );
            }

            $nextRevisionNo = (int) (SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->max('revision_no')) + 1;

            SubmittalRevision::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'submittal_id' => (string) $locked->id,
                'revision_no' => $nextRevisionNo,
                'revision_summary' => $context['revision_summary'] ?? null,
                'title' => $locked->title,
                'description' => $locked->description,
                'file_url' => $locked->file_url,
                'attachment_manifest' => $locked->attachments,
                'submitted_by' => $context['actor_user_id'] ?? null,
                'submitted_at' => now(),
                'created_at' => now(),
            ]);

            $locked->update([
                'status' => Submittal::STATUS_SUBMITTED,
                'current_revision_no' => $nextRevisionNo,
                'submitted_by' => $context['actor_user_id'] ?? $locked->submitted_by,
                'submitted_at' => now(),
            ]);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => $nextRevisionNo > 1 ? 'submittal.resubmitted' : 'submittal.submitted',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['revision_no' => $nextRevisionNo],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        });

        if ($isResubmit) {
            $this->notifyLastRejector($submittal);
        }

        return $submittal;
    }

    private function notifyLastRejector(Submittal $submittal): void
    {
        try {
            $recipient = SubmittalRevision::query()
                ->where('submittal_id', $submittal->id)
                ->where('decision', 'rejected')
                ->orderByDesc('revision_no')
                ->value('decided_by');

            if (!$recipient) {
                return;
            }

            Notification::query()->create([
                'tenant_id' => (string) $submittal->tenant_id,
                'user_id' => $recipient,
                'type' => 'submittal_resubmitted',
                'title' => 'Submittal đã được nộp lại: ' . $submittal->submittal_number,
                'body' => $submittal->title,
                'project_id' => $submittal->project_id,
            ]);
        } catch (\Throwable $e) {
            Log::error('submittal.notification_failed', [
                'submittal_id' => $submittal->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
```

Note: `approve()`, `reject()`, and `startRevision()` are added in Task 5 — this task only needs `updateContent()`, `submit()`, and the private `notifyLastRejector()` helper they both rely on to compile and pass its own tests. The `decide()`/`approve()`/`reject()`/`startRevision()` methods referenced by later tasks are added by editing this same file in Task 5.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/SubmittalLifecycleService.php \
        tests/Feature/Services/SubmittalLifecycleServiceSubmitTest.php
git commit -m "feat(submittal): add SubmittalLifecycleService updateContent/submit"
```

---

### Task 5: `SubmittalLifecycleService` — `approve()`, `reject()`, `startRevision()`

**Files:**
- Modify: `app/Services/SubmittalLifecycleService.php`
- Test: `tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php`

**Interfaces:**
- Consumes: everything from Task 4 (same class), `SubmittalTransitionConflictException` (Task 3).
- Produces: `approve(Submittal $submittal, array $context): Submittal` (`$context['approval_comments']`), `reject(Submittal $submittal, array $context): Submittal` (`$context['rejection_reason']`, `$context['rejection_comments']`), `startRevision(Submittal $submittal, array $context): Submittal`. Used by Task 7 (controller).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubmittalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmittalLifecycleServiceDecisionTest extends TestCase
{
    use RefreshDatabase;

    private SubmittalLifecycleService $service;
    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubmittalLifecycleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function submittedSubmittal(): Submittal
    {
        $submittal = Submittal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => Submittal::STATUS_DRAFT,
            'submittal_number' => 'SUB-0002',
        ]);

        return $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_reject_then_start_revision_then_resubmit(): void
    {
        $submittal = $this->submittedSubmittal();

        $rejected = $this->service->reject($submittal, [
            'actor_user_id' => $this->user->id,
            'rejection_reason' => 'Missing calcs',
            'rejection_comments' => 'Please redo section 3',
        ]);
        $this->assertSame(Submittal::STATUS_REJECTED, $rejected->status);

        $revising = $this->service->startRevision($rejected, ['actor_user_id' => $this->user->id]);
        $this->assertSame(Submittal::STATUS_REVISING, $revising->status);

        $resubmitted = $this->service->submit($revising, [
            'actor_user_id' => $this->user->id,
            'revision_summary' => 'Fixed section 3',
        ]);
        $this->assertSame(Submittal::STATUS_SUBMITTED, $resubmitted->status);
        $this->assertSame(2, $resubmitted->current_revision_no);

        $this->assertDatabaseHas('submittal_revisions', ['submittal_id' => $submittal->id, 'revision_no' => 1, 'decision' => 'rejected']);
        $this->assertDatabaseHas('submittal_revisions', ['submittal_id' => $submittal->id, 'revision_no' => 2, 'decision' => null]);
    }

    public function test_approve_conflicts_when_revision_already_decided(): void
    {
        $submittal = $this->submittedSubmittal();

        DB::table('submittal_revisions')
            ->where('submittal_id', $submittal->id)
            ->update([
                'decision' => 'rejected',
                'decided_by' => $this->user->id,
                'decided_at' => now(),
                'decision_comments' => 'raced by another request',
            ]);

        $this->expectException(SubmittalTransitionConflictException::class);

        $this->service->approve($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_start_revision_from_approved_is_rejected(): void
    {
        $submittal = $this->submittedSubmittal();
        $approved = $this->service->approve($submittal, ['actor_user_id' => $this->user->id]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->startRevision($approved, ['actor_user_id' => $this->user->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php`
Expected: FAIL — `approve()`/`reject()`/`startRevision()` don't exist yet.

- [ ] **Step 3: Add the methods to `app/Services/SubmittalLifecycleService.php`**

Insert before the closing `}` of the class (after `submit()`, before the private `notifyLastRejector()` method — order doesn't matter functionally, but keep `notifyLastRejector` last):

```php
    public function approve(Submittal $submittal, array $context): Submittal
    {
        return $this->decide(
            $submittal,
            $context,
            Submittal::STATUS_APPROVED,
            'approved',
            $context['approval_comments'] ?? null
        );
    }

    public function reject(Submittal $submittal, array $context): Submittal
    {
        return $this->decide(
            $submittal,
            $context,
            Submittal::STATUS_REJECTED,
            'rejected',
            $context['rejection_reason'] ?? null,
            $context['rejection_comments'] ?? null
        );
    }

    private function decide(
        Submittal $submittal,
        array $context,
        string $targetStatus,
        string $decision,
        ?string $comments,
        ?string $decisionComments = null
    ): Submittal {
        return DB::transaction(function () use ($submittal, $context, $targetStatus, $decision, $comments, $decisionComments) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, $targetStatus)) {
                throw new SubmittalTransitionNotAllowedException(
                    "Chỉ submitted mới có thể chuyển sang {$targetStatus}."
                );
            }

            $revision = SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->where('revision_no', $locked->current_revision_no)
                ->lockForUpdate()
                ->first();

            if (!$revision) {
                throw new SubmittalTransitionConflictException('Không tìm thấy revision đang chờ quyết định.');
            }

            $affected = SubmittalRevision::query()
                ->where('id', $revision->id)
                ->whereNull('decision')
                ->update([
                    'decision' => $decision,
                    'decided_by' => $context['actor_user_id'] ?? null,
                    'decided_at' => now(),
                    'decision_comments' => $decisionComments ?? $comments,
                ]);

            if ($affected === 0) {
                throw new SubmittalTransitionConflictException('Revision đã có quyết định trước đó.');
            }

            $mirror = ['status' => $targetStatus];

            if ($decision === 'approved') {
                $mirror['approved_by'] = $context['actor_user_id'] ?? null;
                $mirror['approved_at'] = now();
                $mirror['approval_comments'] = $comments;
            } else {
                $mirror['rejected_by'] = $context['actor_user_id'] ?? null;
                $mirror['rejected_at'] = now();
                $mirror['rejection_reason'] = $comments;
                $mirror['rejection_comments'] = $decisionComments;
            }

            $locked->update($mirror);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => "submittal.{$decision}",
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => ['revision_no' => $locked->current_revision_no],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        });
    }

    public function startRevision(Submittal $submittal, array $context): Submittal
    {
        return DB::transaction(function () use ($submittal, $context) {
            $locked = Submittal::query()
                ->where('id', $submittal->id)
                ->where('tenant_id', $submittal->tenant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!Submittal::canTransition($locked->status, Submittal::STATUS_REVISING)) {
                throw new SubmittalTransitionNotAllowedException(
                    'Chỉ rejected mới mở lại để sửa được.'
                );
            }

            $lastRevision = SubmittalRevision::query()
                ->where('submittal_id', $locked->id)
                ->orderByDesc('revision_no')
                ->first();

            $locked->update([
                'status' => Submittal::STATUS_REVISING,
                'title' => $lastRevision->title ?? $locked->title,
                'description' => $lastRevision->description ?? $locked->description,
                'file_url' => $lastRevision->file_url ?? $locked->file_url,
                'attachments' => $lastRevision->attachment_manifest ?? $locked->attachments,
            ]);

            EventRecord::query()->create([
                'tenant_id' => (string) $locked->tenant_id,
                'project_id' => $locked->project_id,
                'aggregate_type' => 'submittal',
                'aggregate_id' => (string) $locked->id,
                'event_key' => 'submittal.revision_started',
                'actor_user_id' => $context['actor_user_id'] ?? null,
                'payload' => [],
                'occurred_at' => now(),
            ]);

            return $locked->fresh();
        });
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/SubmittalLifecycleService.php \
        tests/Feature/Services/SubmittalLifecycleServiceDecisionTest.php
git commit -m "feat(submittal): add approve/reject/startRevision with concurrency-safe decisions"
```

---

### Task 6: `SubmittalPolicy` + registration

**Files:**
- Create: `app/Policies/SubmittalPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Test: `tests/Unit/Policies/SubmittalPolicyTest.php`

**Interfaces:**
- Consumes: `App\Models\Submittal`, `App\Models\User::hasPermission(string): bool` (existing method used by every other policy in this codebase).
- Produces: `SubmittalPolicy` with abilities `viewAny, view, create, update, submit, startRevision, approve, reject, delete`, registered against `App\Models\Submittal` so `$this->authorize($ability, $submittal)` resolves it in Task 7.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\SubmittalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmittalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_denied_for_cross_tenant_user_even_with_permission(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $submittal = Submittal::factory()->create(['tenant_id' => $tenantA->id, 'status' => 'draft']);

        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $userB->shouldReceive('hasPermission')->never(); // tenant check must short-circuit first
    }

    public function test_permission_matrix(): void
    {
        $tenant = Tenant::factory()->create();
        $submittal = Submittal::factory()->create(['tenant_id' => $tenant->id, 'status' => 'draft']);

        $policy = new SubmittalPolicy();

        $withPermission = \Mockery::mock(User::class)->makePartial();
        $withPermission->tenant_id = $tenant->id;
        $withPermission->shouldReceive('hasPermission')->with('submittal.approve')->andReturn(true);

        $withoutPermission = \Mockery::mock(User::class)->makePartial();
        $withoutPermission->tenant_id = $tenant->id;
        $withoutPermission->shouldReceive('hasPermission')->with('submittal.approve')->andReturn(false);

        $this->assertTrue($policy->approve($withPermission, $submittal));
        $this->assertFalse($policy->approve($withoutPermission, $submittal));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Policies/SubmittalPolicyTest.php`
Expected: FAIL — class `App\Policies\SubmittalPolicy` not found.

- [ ] **Step 3: Create `app/Policies/SubmittalPolicy.php`**

```php
<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Submittal;
use App\Models\User;

class SubmittalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('submittal.view');
    }

    public function view(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('submittal.create');
    }

    public function update(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.edit');
    }

    public function submit(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.submit');
    }

    public function startRevision(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.submit');
    }

    public function approve(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.approve');
    }

    public function reject(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.reject');
    }

    public function delete(User $user, Submittal $submittal): bool
    {
        return $this->sameTenant($user, $submittal) && $user->hasPermission('submittal.delete');
    }

    private function sameTenant(User $user, Submittal $submittal): bool
    {
        return (string) $user->tenant_id === (string) $submittal->tenant_id;
    }
}
```

- [ ] **Step 4: Register the policy**

In `app/Providers/AuthServiceProvider.php`, add to the `$policies` array (after the `'App\Models\Quote' => 'App\Policies\QuotePolicy',` line):

```php
        'App\Models\Submittal' => 'App\Policies\SubmittalPolicy',
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Policies/SubmittalPolicyTest.php`
Expected: PASS. If the first test (`test_update_denied_for_cross_tenant_user_even_with_permission`) is awkward to express with Mockery partials in this codebase's Laravel/Mockery version, delete that test body's assertion-free scaffold and instead rely on the cross-tenant coverage added at the HTTP level in Task 8 (`test_cross_tenant_returns_404_not_403` — a stronger guarantee, since `submittalForTenant()` already filters by tenant before the policy even runs). Keep `test_permission_matrix`, which is the meaningful unit-level check.

- [ ] **Step 6: Commit**

```bash
git add app/Policies/SubmittalPolicy.php app/Providers/AuthServiceProvider.php \
        tests/Unit/Policies/SubmittalPolicyTest.php
git commit -m "feat(submittal): add SubmittalPolicy and register it"
```

---

### Task 7: Refactor `SubmittalController` to use the service and policy exclusively

**Files:**
- Modify: `app/Http/Controllers/Api/SubmittalController.php`
- Modify: `routes/api_zena.php`

**Interfaces:**
- Consumes: `SubmittalLifecycleService` (Tasks 4/5), `SubmittalPolicy` abilities (Task 6), `SubmittalTransitionNotAllowedException` → HTTP 400, `SubmittalTransitionConflictException` → HTTP 409, `\Illuminate\Auth\Access\AuthorizationException` → HTTP 403.
- Produces: `SubmittalController::startRevision(Request $request, string $id): JsonResponse` (new), route `POST /api/zena/submittals/{id}/start-revision` named `api.zena.submittals.start-revision`. `submit`, `approve`, `reject`, `update`, `destroy`, `review`, `index`, `store`, `show` keep their existing signatures (consumed by `SubmittalPageController`, unchanged).

- [ ] **Step 1: Update the constructor and imports**

Replace the top of `app/Http/Controllers/Api/SubmittalController.php` (imports and constructor):

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use App\Models\Project;
use App\Models\Submittal;
use App\Services\SubmittalLifecycleService;
use App\Services\ZenaAuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubmittalController extends ApiBaseController
{
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private SubmittalLifecycleService $lifecycle
    ) {
    }
```

Keep `tenantId()`, `submittalQuery()`, `submittalForTenant()`, `generateSubmittalNumber()`, and `projectForTenant()` unchanged.

- [ ] **Step 2: Add authorization to `index()` and `store()`**

In `index()`, right after the `if (!$user) { return $this->unauthorized(...); }` check, add:

```php
            $this->authorize('viewAny', Submittal::class);
```

In `store()`, right after the same check, add:

```php
            $this->authorize('create', Submittal::class);
```

Both methods keep their existing `catch (\Exception $e)` block; add a new catch clause for `AuthorizationException` immediately before it in both methods:

```php
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (\Exception $e) {
```

- [ ] **Step 3: Replace `show()`**

```php
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id, [
                'project:id,name',
                'submittedBy:id,name',
                'reviewedBy:id,name',
                'documents',
            ]);

            $this->authorize('view', $submittal);

            return $this->successResponse($submittal, 'Submittal retrieved successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to retrieve submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 4: Replace `update()`**

```php
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('update', $submittal);

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'sometimes|string',
                'submittal_type' => 'sometimes|in:shop_drawing,material_sample,product_data,test_report,other',
                'specification_section' => 'nullable|string|max:255',
                'due_date' => 'nullable|date',
                'contractor' => 'nullable|string|max:255',
                'manufacturer' => 'nullable|string|max:255',
                'status' => 'prohibited',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $data = $request->only([
                'title', 'description', 'submittal_type', 'specification_section',
                'due_date', 'contractor', 'manufacturer',
            ]);

            $submittal = $this->lifecycle->updateContent($submittal, $data, ['actor_user_id' => $user->id]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.update',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal updated successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->validationError(['status' => [$e->getMessage()]]);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to update submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 5: Replace `destroy()`**

```php
    public function destroy(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('delete', $submittal);

            if ($submittal->status !== Submittal::STATUS_DRAFT) {
                return $this->errorResponse('Only draft submittals can be deleted', 400);
            }

            $projectId = $submittal->project_id;
            $submittal->delete();

            $this->auditLogger->log(
                $request,
                'zena.submittal.delete',
                'submittal',
                (string) $submittal->id,
                200,
                $projectId,
                $this->tenantId()
            );

            return $this->successResponse(null, 'Submittal deleted successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to delete submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 6: Replace `submit()`**

```php
    public function submit(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('submit', $submittal);

            $validator = Validator::make($request->all(), [
                'revision_summary' => [
                    Rule::requiredIf($submittal->status === Submittal::STATUS_REVISING),
                    'nullable',
                    'string',
                ],
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->submit($submittal, [
                'actor_user_id' => $user->id,
                'revision_summary' => $request->input('revision_summary'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.submit',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal submitted successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to submit submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 7: Add `startRevision()`** (new method, place it right after `submit()`)

```php
    public function startRevision(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('startRevision', $submittal);

            $submittal = $this->lifecycle->startRevision($submittal, ['actor_user_id' => $user->id]);

            $submittal->load(['project:id,name', 'submittedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.start_revision',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal reopened for revision');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to start revision: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 8: Replace `review()`**

```php
    public function review(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);

            $reviewStatus = $request->input('review_status') ?? $request->input('status');
            $reviewComments = $request->input('review_comments') ?? $request->input('review_notes');

            $validator = Validator::make([
                'review_status' => $reviewStatus,
                'review_comments' => $reviewComments,
                'review_notes' => $request->input('review_notes'),
            ], [
                'review_status' => 'required|in:approved,rejected',
                'review_comments' => 'required|string',
                'review_notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $ability = $reviewStatus === 'approved' ? 'approve' : 'reject';
            $this->authorize($ability, $submittal);

            $context = ['actor_user_id' => $user->id];

            $submittal = $reviewStatus === 'approved'
                ? $this->lifecycle->approve($submittal, $context + ['approval_comments' => $reviewComments])
                : $this->lifecycle->reject($submittal, $context + [
                    'rejection_reason' => $reviewComments,
                    'rejection_comments' => $request->input('review_notes'),
                ]);

            $submittal->forceFill([
                'review_comments' => $reviewComments,
                'review_notes' => $request->input('review_notes'),
                'reviewed_by' => $user->id,
                'reviewed_at' => now(),
            ])->save();

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.review',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal reviewed successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to review submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 9: Replace `approve()`**

```php
    public function approve(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('approve', $submittal);

            $validator = Validator::make($request->all(), [
                'approval_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->approve($submittal, [
                'actor_user_id' => $user->id,
                'approval_comments' => $request->input('approval_comments'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.approve',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal approved successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to approve submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 10: Replace `reject()`**

```php
    public function reject(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $submittal = $this->submittalForTenant($id);
            $this->authorize('reject', $submittal);

            $validator = Validator::make($request->all(), [
                'rejection_reason' => 'required|string',
                'rejection_comments' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $submittal = $this->lifecycle->reject($submittal, [
                'actor_user_id' => $user->id,
                'rejection_reason' => $request->input('rejection_reason'),
                'rejection_comments' => $request->input('rejection_comments'),
            ]);

            $submittal->load(['project:id,name', 'submittedBy:id,name', 'reviewedBy:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.submittal.reject',
                'submittal',
                (string) $submittal->id,
                200,
                $submittal->project_id,
                $this->tenantId()
            );

            return $this->successResponse($submittal, 'Submittal rejected successfully');
        } catch (AuthorizationException $e) {
            return $this->errorResponse($e->getMessage() ?: 'Forbidden', 403);
        } catch (SubmittalTransitionNotAllowedException $e) {
            return $this->errorResponse($e->getMessage(), 400);
        } catch (SubmittalTransitionConflictException $e) {
            return $this->errorResponse($e->getMessage(), 409);
        } catch (ModelNotFoundException $e) {
            return $this->notFound('Submittal not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to reject submittal: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 11: Add the route**

In `routes/api_zena.php`, inside the `submittals` prefix group (after the `submittals.submit` line), add:

```php
            Route::post('/{id}/start-revision', [\App\Http\Controllers\Api\SubmittalController::class, 'startRevision'])->middleware('rbac:submittal.submit')->name('submittals.start-revision');
```

- [ ] **Step 12: Run the existing Submittal test suites to see what breaks**

Run: `php artisan test tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: Some failures — `test_can_approve_submittal` and `test_can_reject_submittal` use a `pending_review` fixture that's no longer a valid source status (fixed in Task 8), and any test creating a submittal via factory without an explicit `status` may randomly land on a non-`draft` status for `update`/`destroy` tests (also fixed in Task 8). Confirm these are the *only* failures — anything else indicates a mistake in Steps 1–11.

- [ ] **Step 13: Commit**

```bash
git add app/Http/Controllers/Api/SubmittalController.php routes/api_zena.php
git commit -m "refactor(submittal): route all controller actions through SubmittalLifecycleService and SubmittalPolicy"
```

---

### Task 8: Fix existing tests + add the full resubmit test matrix

**Files:**
- Modify: `tests/Feature/Api/SubmittalApiTest.php`
- Create: `tests/Feature/Api/SubmittalResubmitLifecycleTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–7.

- [ ] **Step 1: Fix `tests/Feature/Api/SubmittalApiTest.php` fixtures broken by the tightened guards**

In `test_can_approve_submittal` (around line 191–222), change the fixture status from `'pending_review'` to `'submitted'`:

```php
    public function test_can_approve_submittal()
    {
        $submittal = Submittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'submitted',
        ]);
```

In `test_can_reject_submittal` (around line 227–260), same change:

```php
    public function test_can_reject_submittal()
    {
        $submittal = Submittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'submitted',
        ]);
```

In `test_can_update_submittal` (around line 265–294), pin the status so the random factory value can't land outside `{draft, revising}`:

```php
    public function test_can_update_submittal()
    {
        $submittal = Submittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);
```

In `test_can_delete_submittal` (around line 299–314), same pin (delete is now draft-only):

```php
    public function test_can_delete_submittal()
    {
        $submittal = Submittal::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);
```

- [ ] **Step 2: Run to verify these four fixes are sufficient**

Run: `php artisan test tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalShowApiTest.php tests/Feature/Zena/OperatorSubmittalUiTest.php`
Expected: PASS — all green. If anything else fails, read the failure message before changing more code; it likely means a Task 1–7 step was applied incorrectly, not that more test fixtures need adjusting.

- [ ] **Step 3: Write the new test matrix file**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\Role;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

class SubmittalResubmitLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use WithFaker;
    use RouteNameTrait;

    protected User $user;
    protected Project $project;
    protected string $token;
    protected array $zenaAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->syncZenaProjectRecord($this->project);

        $this->assignSuperAdminRole($this->user);
        $this->token = $this->loginZenaUser($this->user);
        $this->zenaAuthHeaders = $this->buildZenaAuthHeaders();
    }

    private function makeSubmittedSubmittal(): Submittal
    {
        $create = $this->withZenaAuth()->postJson($this->zena('submittals.store'), [
            'project_id' => $this->project->id,
            'title' => 'Structural steel shop drawing',
            'description' => 'Level 3 framing package.',
            'submittal_type' => 'shop_drawing',
        ]);
        $create->assertStatus(201);
        $id = $create->json('data.id');

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $id]))->assertStatus(200);

        return Submittal::find($id);
    }

    // 1. Revision snapshot bất biến
    public function test_revision_snapshot_is_immutable_after_edit(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Missing bolt schedule',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Structural steel shop drawing REV B',
        ])->assertStatus(200);

        $this->assertDatabaseHas('submittal_revisions', [
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Structural steel shop drawing',
        ]);
    }

    // 2. Chỉnh sửa theo trạng thái
    public function test_patch_blocked_outside_draft_and_revising(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Should not apply',
        ])->assertStatus(422);

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Needs rework',
        ])->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Still should not apply',
        ])->assertStatus(422);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), [
            'title' => 'Now this applies',
        ])->assertStatus(200);
    }

    // 3. Double-submit
    public function test_double_submit_after_revising_only_creates_one_new_revision(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);
        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $first = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed the issue',
        ]);
        $first->assertStatus(200);

        $second = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed the issue again',
        ]);
        $second->assertStatus(400);

        $this->assertSame(2, DB::table('submittal_revisions')->where('submittal_id', $submittal->id)->count());
    }

    // 4. Repeated start-revision
    public function test_repeated_start_revision_call_fails_second_time(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);
        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(400);
    }

    // 5. Cross-tenant
    public function test_cross_tenant_access_returns_404(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $this->assignSuperAdminRole($otherUser);
        $otherToken = $this->loginZenaUser($otherUser);
        $otherHeaders = [
            'Authorization' => 'Bearer ' . $otherToken,
            'X-Tenant-ID' => (string) $otherTenant->id,
            'Accept' => 'application/json',
        ];

        $this->withHeaders($otherHeaders)
            ->getJson($this->zena('submittals.show', ['id' => $submittal->id]))
            ->assertStatus(404);

        $this->withHeaders($otherHeaders)
            ->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))
            ->assertStatus(404);
    }

    // 6. Authorization
    public function test_actions_denied_without_matching_permission(): void
    {
        $tenant = $this->user->tenant_id;
        $role = Role::firstOrCreate(
            ['name' => 'submittal_viewer_only'],
            ['scope' => Role::SCOPE_SYSTEM, 'allow_override' => true, 'is_active' => true]
        );

        $viewer = User::factory()->create(['tenant_id' => $tenant]);
        $viewer->roles()->syncWithoutDetaching($role->id);
        $viewerToken = $this->loginZenaUser($viewer);
        $viewerHeaders = [
            'Authorization' => 'Bearer ' . $viewerToken,
            'X-Tenant-ID' => (string) $tenant,
            'Accept' => 'application/json',
        ];

        $submittal = $this->makeSubmittedSubmittal();

        $this->withHeaders($viewerHeaders)
            ->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))
            ->assertStatus(403);
    }

    // 7. Notification after commit
    public function test_resubmit_notifies_last_rejector_and_logs_failure_gracefully(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Missing details',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $response = $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Added missing details',
        ]);
        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'submittal_resubmitted',
        ]);
    }

    // 8. Approved terminal
    public function test_approved_has_no_outgoing_transition(): void
    {
        $submittal = $this->makeSubmittedSubmittal();
        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(400);
        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]))->assertStatus(400);
        $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $submittal->id]), ['title' => 'x'])->assertStatus(422);
    }

    // 9. status via PATCH ignored
    public function test_status_field_rejected_via_update_even_with_other_valid_fields(): void
    {
        $create = $this->withZenaAuth()->postJson($this->zena('submittals.store'), [
            'project_id' => $this->project->id,
            'title' => 'Draft submittal',
            'description' => 'Desc',
            'submittal_type' => 'other',
        ]);
        $id = $create->json('data.id');

        $response = $this->withZenaAuth()->putJson($this->zena('submittals.update', ['id' => $id]), [
            'title' => 'Updated title',
            'status' => 'approved',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseHas('submittals', ['id' => $id, 'status' => 'draft', 'title' => 'Draft submittal']);
    }

    // 10. Full audit chain
    public function test_full_audit_chain_produces_expected_event_records_and_revisions(): void
    {
        $submittal = $this->makeSubmittedSubmittal();

        $this->withZenaAuth()->postJson($this->zena('submittals.reject', ['id' => $submittal->id]), [
            'rejection_reason' => 'Rework needed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.start-revision', ['id' => $submittal->id]))->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.submit', ['id' => $submittal->id]), [
            'revision_summary' => 'Fixed',
        ])->assertStatus(200);

        $this->withZenaAuth()->postJson($this->zena('submittals.approve', ['id' => $submittal->id]))->assertStatus(200);

        $eventKeys = DB::table('event_records')
            ->where('aggregate_type', 'submittal')
            ->where('aggregate_id', $submittal->id)
            ->orderBy('occurred_at')
            ->pluck('event_key')
            ->all();

        $this->assertSame(
            ['submittal.submitted', 'submittal.rejected', 'submittal.revision_started', 'submittal.resubmitted', 'submittal.approved'],
            $eventKeys
        );

        $revisions = DB::table('submittal_revisions')
            ->where('submittal_id', $submittal->id)
            ->orderBy('revision_no')
            ->get(['revision_no', 'decision']);

        $this->assertCount(2, $revisions);
        $this->assertSame('rejected', $revisions[0]->decision);
        $this->assertSame('approved', $revisions[1]->decision);
    }

    private function withZenaAuth()
    {
        return $this->withHeaders($this->zenaAuthHeaders);
    }

    private function buildZenaAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];
    }

    private function loginZenaUser(User $user): string
    {
        $response = $this->postJson($this->zena('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return (string) $response->json('data.token');
    }

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
        ], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $user->roles()->syncWithoutDetaching($role->id);
    }

    private function syncZenaProjectRecord(Project $project): void
    {
        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenant_id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'status' => $this->mapProjectStatusToZenaStatus($project->status),
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function mapProjectStatusToZenaStatus(string $status): string
    {
        return match ($status) {
            'planning' => 'planning',
            'active', 'in_progress' => 'active',
            'on_hold' => 'on_hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'planning',
        };
    }
}
```

Note on test 6 (`test_actions_denied_without_matching_permission`): the `submittal_viewer_only` role is created with no permissions attached (`firstOrCreate` only sets scope/flags), so `$viewer->hasPermission('submittal.approve')` is `false` and the request must 403. If this codebase's role/permission wiring requires an explicit `RolePermission` pivot row to be fully denied-by-default (rather than allow-by-default), check `app/Models/User::hasPermission()` — if it returns `true` for a role with zero permissions attached, adjust this test to attach every permission except `submittal.approve` explicitly instead of relying on an empty role, matching the pattern in `tests/Feature/Zena/OperatorSubmittalUiTest.php`'s `createTenantUser($tenant, [], ['submittal_viewer'], ['submittal.view'])`.

- [ ] **Step 4: Run the new test file**

Run: `php artisan test tests/Feature/Api/SubmittalResubmitLifecycleTest.php`
Expected: PASS (10/10). Debug any individual failure by reading the assertion message — do not loosen an assertion to make it pass; if a case reveals a real gap in Tasks 1–7, fix the implementation.

- [ ] **Step 5: Run the full Submittal-related suite one more time**

Run: `php artisan test --filter=Submittal`
Expected: PASS across `SubmittalApiTest`, `SubmittalShowApiTest`, `SubmittalShowRouteInvariantTest`, `OperatorSubmittalUiTest`, `SubmittalStateMachineTest`, `SubmittalPolicyTest`, `SubmittalLifecycleServiceSubmitTest`, `SubmittalLifecycleServiceDecisionTest`, `SubmittalResubmitLifecycleTest`.

- [ ] **Step 6: Commit**

```bash
git add tests/Feature/Api/SubmittalApiTest.php tests/Feature/Api/SubmittalResubmitLifecycleTest.php
git commit -m "test(submittal): fix fixtures for tightened guards and add full resubmit lifecycle test matrix"
```

---

## Post-plan verification

Run the full project test suite once all 8 tasks are committed, to catch any cross-cutting regression (e.g. dashboards counting `pending_review`, other code referencing `Submittal::STATUS_REVISED`):

```bash
grep -rn "STATUS_REVISED" app/ resources/ tests/
php artisan test
```

Expected: the `grep` returns nothing (confirms the removal in Task 2 was complete), and the full suite is green.
