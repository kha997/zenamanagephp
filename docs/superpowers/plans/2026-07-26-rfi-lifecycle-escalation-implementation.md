# RFI Lifecycle + Escalation History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give RFI escalation its own history-preserving table (`rfi_escalations`) fully independent of the RFI's lifecycle `status`, add the guards/authorization/notification the current single-column design lacks, and migrate legacy `status=escalated`/`pending` data through an additive, operator-confirmed gate — without ever silently defaulting an ambiguous record or dropping legacy fields.

**Architecture:** `RfiEscalationService` owns every invariant on `rfi_escalations` (at most one active escalation per RFI, resolution fields written exactly once, both `rfis` and the active `rfi_escalations` row locked via `lockForUpdate()` inside `DB::transaction()`). `RfiController` gains authorization checks (PM-of-project or admin for escalate; target/PM/admin for resolve) and lifecycle guards (respond only from open/in_progress, close blocked while an escalation is active) but keeps its existing `try/catch` + `BaseApiController` response-helper style. A brand-new `RfiEscalatedNotification` (never touching the dead `RfiEventListener`) is dispatched after commit. Legacy data crosses over in three Artisan commands — preflight report, per-record operator confirmation, and a cutover gate that refuses to run while any record is unconfirmed.

**Tech Stack:** Laravel 12, MySQL, PHPUnit (`RefreshDatabase`), existing `Tests\Traits\{AuthenticationTestTrait,RouteNameTrait,TenantUserFactoryTrait}` test helpers, existing `App\Services\ZenaAuditLogger` audit-log convention.

## Global Constraints

- Source spec: `docs/superpowers/specs/2026-07-26-rfi-lifecycle-escalation-design.md` (rev 3, commit `75ff0d69`, `APPROVED FOR WRITING-PLANS`).
- **`rfi_escalations` is the source of truth.** `rfis.escalated_to/escalated_at/escalated_by/escalation_reason` are compatibility mirrors only — every write to them happens inside the same transaction as the `rfi_escalations` write that causes it. No task in this plan may add a reader that treats the mirror fields as authoritative.
- **`rfis.current_escalation_id`** is the official pointer to the active escalation (`NULL` = none).
- **Never drop `status=escalated`/`status=pending` from the database in the same step that creates the new schema.** The legacy deployment gate (Tasks 12-14) is additive; cutover (Task 14) only tightens *application-level* validation, it does not alter the `status` column type or drop any DB-level enum.
- **Never drop the 4 legacy mirror fields in this plan.** Deprecation requires a separate reader inventory (spec §2.2) — out of scope here.
- **No record may be auto-mapped to `open`/any lifecycle value without an explicit operator confirmation record.** The cutover command (Task 14) must refuse to run while any legacy record lacks a confirmation.
- **`escalate()` and `resolveEscalation()` both lock the `rfis` row and the relevant `rfi_escalations` row inside one `DB::transaction()`.** Concurrent conflicting requests must receive HTTP 409, not a silent overwrite.
- **Resolution fields (`resolved_at`, `resolved_by`, `resolution`, `resolution_type`) are written exactly once.** A second resolve attempt on the same record is a 409, not an update.
- **Notifications dispatch only after the transaction that created the escalation commits**, and a failed notification send is logged, never rolled back, never re-attempted synchronously.
- **`RfiEventListener` and `App\Events\Rfi{Created,Updated,Responded,Closed}` are dead code and must not be resurrected, referenced, or "fixed" by any task in this plan.**
- **Overdue & Escalation Engine (SLA/auto-escalation) is out of scope.** No task in this plan builds SLA logic; the design only requires that a *future* engine call `RfiEscalationService`, which this plan builds regardless.
- Existing `RfiController` style must be preserved: `try { ... } catch (ModelNotFoundException $e) { ... } catch (\Exception $e) { ... }`, `BaseApiController` response helpers (`successResponse`, `errorResponse`, `validationError`, `notFound`, `unauthorized`, `serverError`), and a `ZenaAuditLogger->log($request, $action, 'rfi', $id, $statusCode, $projectId, $tenantId)` call on every state-changing action.

---

## Task 1: `rfi_escalations` table + `RfiEscalation` model

**Files:**
- Create: `database/migrations/2026_07_26_090000_create_rfi_escalations_table.php`
- Create: `app/Models/RfiEscalation.php`
- Test: `tests/Unit/Models/RfiEscalationTest.php`

**Interfaces:**
- Produces: `App\Models\RfiEscalation` — ULID PK, fillable `rfi_id, tenant_id, escalated_to, escalated_by, escalated_at, escalation_reason, resolved_at, resolved_by, resolution, resolution_type`, constants `RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED = 'manually_resolved'`, `RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED = 'rfi_cancelled'`. Consumed by Tasks 2-14.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\RfiEscalation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolution_type_constants_have_expected_values(): void
    {
        $this->assertSame('manually_resolved', RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED);
        $this->assertSame('rfi_cancelled', RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED);
    }

    public function test_can_create_an_unresolved_escalation_with_immutable_origin_fields(): void
    {
        $escalation = RfiEscalation::create([
            'rfi_id' => (string) \Illuminate\Support\Str::ulid(),
            'tenant_id' => (string) \Illuminate\Support\Str::ulid(),
            'escalated_to' => (string) \Illuminate\Support\Str::ulid(),
            'escalated_by' => (string) \Illuminate\Support\Str::ulid(),
            'escalated_at' => now(),
            'escalation_reason' => 'Client needs answer by tomorrow',
        ]);

        $this->assertNull($escalation->resolved_at);
        $this->assertNull($escalation->resolved_by);
        $this->assertNull($escalation->resolution);
        $this->assertNull($escalation->resolution_type);
        $this->assertSame('Client needs answer by tomorrow', $escalation->escalation_reason);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Models/RfiEscalationTest.php`
Expected: FAIL with "Class App\Models\RfiEscalation not found" (or table `rfi_escalations` doesn't exist).

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_26_090000_create_rfi_escalations_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfi_escalations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('rfi_id');
            $table->ulid('tenant_id');
            $table->ulid('escalated_to');
            $table->ulid('escalated_by');
            $table->timestamp('escalated_at');
            $table->text('escalation_reason');
            $table->timestamp('resolved_at')->nullable();
            $table->ulid('resolved_by')->nullable();
            $table->text('resolution')->nullable();
            $table->string('resolution_type')->nullable();
            $table->timestamps();

            $table->foreign('rfi_id')->references('id')->on('rfis')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('escalated_to')->references('id')->on('users');
            $table->foreign('escalated_by')->references('id')->on('users');
            $table->foreign('resolved_by')->references('id')->on('users');

            $table->index(['rfi_id', 'resolved_at']);
            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_escalations');
    }
};
```

- [ ] **Step 4: Write the model**

`app/Models/RfiEscalation.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RfiEscalation extends Model
{
    use HasUlids;

    public const RESOLUTION_TYPE_MANUALLY_RESOLVED = 'manually_resolved';
    public const RESOLUTION_TYPE_RFI_CANCELLED = 'rfi_cancelled';

    protected $table = 'rfi_escalations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'rfi_id',
        'tenant_id',
        'escalated_to',
        'escalated_by',
        'escalated_at',
        'escalation_reason',
        'resolved_at',
        'resolved_by',
        'resolution',
        'resolution_type',
    ];

    protected $casts = [
        'escalated_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class, 'rfi_id');
    }

    public function escalatedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_to');
    }

    public function escalatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'escalated_by');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
```

- [ ] **Step 5: Run migration and test**

Run: `php artisan migrate && ./vendor/bin/phpunit tests/Unit/Models/RfiEscalationTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_26_090000_create_rfi_escalations_table.php app/Models/RfiEscalation.php tests/Unit/Models/RfiEscalationTest.php
git commit -m "feat(rfi): add rfi_escalations table and model"
```

---

## Task 2: `rfis.current_escalation_id` + `Rfi::currentEscalation()`

**Files:**
- Create: `database/migrations/2026_07_26_090100_add_current_escalation_id_to_rfis_table.php`
- Modify: `app/Models/Rfi.php`
- Test: `tests/Unit/Models/RfiTest.php` (create if it doesn't exist)

**Interfaces:**
- Consumes: `App\Models\RfiEscalation` (Task 1).
- Produces: `Rfi::currentEscalation(): BelongsTo` relation; `rfis.current_escalation_id` column. Consumed by Tasks 3-9.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_escalation_relation_resolves_to_the_linked_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Test RFI',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => 'open',
            'created_by' => $user->id,
            'rfi_number' => 'TST-RFI-0001',
        ]);

        $this->assertNull($rfi->current_escalation_id);
        $this->assertNull($rfi->currentEscalation);

        $escalation = RfiEscalation::create([
            'rfi_id' => $rfi->id,
            'tenant_id' => $tenant->id,
            'escalated_to' => $user->id,
            'escalated_by' => $user->id,
            'escalated_at' => now(),
            'escalation_reason' => 'Urgent',
        ]);

        $rfi->update(['current_escalation_id' => $escalation->id]);
        $rfi->refresh();

        $this->assertSame($escalation->id, $rfi->currentEscalation->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Models/RfiTest.php`
Expected: FAIL — column `current_escalation_id` does not exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_26_090100_add_current_escalation_id_to_rfis_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->ulid('current_escalation_id')->nullable()->after('escalated_at');
            $table->foreign('current_escalation_id')->references('id')->on('rfi_escalations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfis', function (Blueprint $table) {
            $table->dropForeign(['current_escalation_id']);
            $table->dropColumn('current_escalation_id');
        });
    }
};
```

- [ ] **Step 4: Add the relation and fillable entry to `Rfi`**

In `app/Models/Rfi.php`, add `'current_escalation_id',` to `$fillable` immediately after `'escalated_at',` (line 42), and add this method immediately after `escalatedBy()` (after line 128):

```php
    /**
     * Get the currently active escalation, if any.
     */
    public function currentEscalation(): BelongsTo
    {
        return $this->belongsTo(RfiEscalation::class, 'current_escalation_id');
    }
```

Add `use App\Models\RfiEscalation;`? Not needed — same namespace `App\Models`, no import required for `RfiEscalation::class` reference within the same namespace.

- [ ] **Step 5: Run migration and test**

Run: `php artisan migrate && ./vendor/bin/phpunit tests/Unit/Models/RfiTest.php`
Expected: PASS (1 test).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_26_090100_add_current_escalation_id_to_rfis_table.php app/Models/Rfi.php tests/Unit/Models/RfiTest.php
git commit -m "feat(rfi): add current_escalation_id pointer and Rfi::currentEscalation()"
```

---

## Task 3: `RfiEscalationService::escalate()` — insert + lock + active-escalation guard

**Files:**
- Create: `app/Services/RfiEscalationService.php`
- Create: `app/Exceptions/RfiEscalationConflictException.php`
- Test: `tests/Unit/Services/RfiEscalationServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\{Rfi, RfiEscalation}` (Tasks 1-2).
- Produces: `App\Services\RfiEscalationService::escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation`, `::hasActiveEscalation(string $rfiId): bool`. `App\Exceptions\RfiEscalationConflictException extends \RuntimeException`. Consumed by Task 5 (controller wiring), Task 4 (resolveEscalation reuses the same class), Task 9 (cancel).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RfiEscalationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationServiceTest extends TestCase
{
    use RefreshDatabase;

    private RfiEscalationService $service;
    private Rfi $rfi;
    private User $escalator;
    private User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RfiEscalationService::class);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $this->escalator = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->target = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->rfi = Rfi::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Test RFI',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => 'open',
            'created_by' => $this->escalator->id,
            'rfi_number' => 'TST-RFI-0002',
        ]);
    }

    public function test_escalate_creates_unresolved_escalation_and_updates_pointer_and_mirror(): void
    {
        $escalation = $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Needs urgent answer');

        $this->assertNull($escalation->resolved_at);
        $this->assertSame($this->target->id, $escalation->escalated_to);

        $this->rfi->refresh();
        $this->assertSame($escalation->id, $this->rfi->current_escalation_id);
        $this->assertSame($this->target->id, $this->rfi->escalated_to);
        $this->assertSame('Needs urgent answer', $this->rfi->escalation_reason);
    }

    public function test_escalate_throws_conflict_when_active_escalation_already_exists(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'First escalation');

        $this->expectException(RfiEscalationConflictException::class);

        $this->service->escalate($this->rfi->fresh(), $this->target->id, $this->escalator->id, 'Second escalation');
    }

    public function test_has_active_escalation_reflects_current_state(): void
    {
        $this->assertFalse($this->service->hasActiveEscalation($this->rfi->id));

        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');

        $this->assertTrue($this->service->hasActiveEscalation($this->rfi->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: FAIL — "Class App\Services\RfiEscalationService not found".

- [ ] **Step 3: Write the exception and service**

`app/Exceptions/RfiEscalationConflictException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

class RfiEscalationConflictException extends \RuntimeException
{
}
```

`app/Services/RfiEscalationService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use Illuminate\Support\Facades\DB;

class RfiEscalationService
{
    public function hasActiveEscalation(string $rfiId): bool
    {
        return RfiEscalation::where('rfi_id', $rfiId)->whereNull('resolved_at')->exists();
    }

    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        return DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason) {
            $lockedRfi = Rfi::where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            $activeExists = RfiEscalation::where('rfi_id', $lockedRfi->id)
                ->whereNull('resolved_at')
                ->lockForUpdate()
                ->exists();

            if ($activeExists) {
                throw new RfiEscalationConflictException('An active escalation already exists for this RFI.');
            }

            $escalation = RfiEscalation::create([
                'rfi_id' => $lockedRfi->id,
                'tenant_id' => $lockedRfi->tenant_id,
                'escalated_to' => $escalatedTo,
                'escalated_by' => $escalatedBy,
                'escalated_at' => now(),
                'escalation_reason' => $reason,
            ]);

            $lockedRfi->update([
                'current_escalation_id' => $escalation->id,
                'escalated_to' => $escalation->escalated_to,
                'escalated_by' => $escalation->escalated_by,
                'escalated_at' => $escalation->escalated_at,
                'escalation_reason' => $escalation->escalation_reason,
            ]);

            return $escalation;
        });
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RfiEscalationService.php app/Exceptions/RfiEscalationConflictException.php tests/Unit/Services/RfiEscalationServiceTest.php
git commit -m "feat(rfi): add RfiEscalationService::escalate() with lock+active-escalation guard"
```

---

## Task 4: `RfiEscalationService::resolveEscalation()` — lock both rows, resolve exactly once

**Files:**
- Modify: `app/Services/RfiEscalationService.php`
- Create: `app/Exceptions/RfiEscalationNotFoundException.php`
- Modify: `tests/Unit/Services/RfiEscalationServiceTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3), `RfiEscalationConflictException` (Task 3).
- Produces: `RfiEscalationService::resolveEscalation(Rfi $rfi, string $resolvedBy, string $resolution, string $resolutionType = RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED): RfiEscalation`. `App\Exceptions\RfiEscalationNotFoundException`. Consumed by Task 6 (controller), Task 9 (cancel).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Unit/Services/RfiEscalationServiceTest.php` (add `use App\Exceptions\RfiEscalationNotFoundException;` and `use App\Models\RfiEscalation;` to the imports, then add these methods before the final closing `}`):

```php
    public function test_resolve_escalation_sets_resolution_fields_once_and_clears_pointer(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');

        $resolved = $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Answered directly with the client');

        $this->assertNotNull($resolved->resolved_at);
        $this->assertSame($this->target->id, $resolved->resolved_by);
        $this->assertSame('Answered directly with the client', $resolved->resolution);
        $this->assertSame(RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED, $resolved->resolution_type);

        $this->rfi->refresh();
        $this->assertNull($this->rfi->current_escalation_id);
        $this->assertNull($this->rfi->escalated_to);
        $this->assertNull($this->rfi->escalation_reason);
    }

    public function test_resolve_escalation_twice_throws_conflict(): void
    {
        $this->service->escalate($this->rfi, $this->target->id, $this->escalator->id, 'Urgent');
        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'First resolution');

        $this->expectException(RfiEscalationConflictException::class);

        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Second attempt');
    }

    public function test_resolve_escalation_without_active_escalation_throws_not_found(): void
    {
        $this->expectException(RfiEscalationNotFoundException::class);

        $this->service->resolveEscalation($this->rfi, $this->escalator->id, 'No escalation to resolve');
    }
```

Also add `use App\Exceptions\RfiEscalationNotFoundException;` and `use App\Models\RfiEscalation;` near the top of the test file's `use` block.

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: FAIL — `resolveEscalation` method / `RfiEscalationNotFoundException` class not found.

- [ ] **Step 3: Write the exception and service method**

`app/Exceptions/RfiEscalationNotFoundException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

class RfiEscalationNotFoundException extends \RuntimeException
{
}
```

Add to `app/Services/RfiEscalationService.php` (add `use App\Exceptions\RfiEscalationNotFoundException;` to the imports), a new method after `escalate()`:

```php
    public function resolveEscalation(
        Rfi $rfi,
        string $resolvedBy,
        string $resolution,
        string $resolutionType = RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
    ): RfiEscalation {
        return DB::transaction(function () use ($rfi, $resolvedBy, $resolution, $resolutionType) {
            $lockedRfi = Rfi::where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            if (!$lockedRfi->current_escalation_id) {
                throw new RfiEscalationNotFoundException('This RFI has no active escalation.');
            }

            $escalation = RfiEscalation::where('id', $lockedRfi->current_escalation_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($escalation->resolved_at !== null) {
                throw new RfiEscalationConflictException('This escalation has already been resolved.');
            }

            $escalation->update([
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
                'resolution' => $resolution,
                'resolution_type' => $resolutionType,
            ]);

            $lockedRfi->update([
                'current_escalation_id' => null,
                'escalated_to' => null,
                'escalated_by' => null,
                'escalated_at' => null,
                'escalation_reason' => null,
            ]);

            return $escalation;
        });
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RfiEscalationService.php app/Exceptions/RfiEscalationNotFoundException.php tests/Unit/Services/RfiEscalationServiceTest.php
git commit -m "feat(rfi): add RfiEscalationService::resolveEscalation() with double-resolve guard"
```

---

## Task 5: Wire `escalate()` into `RfiController` — authorization, active-user check, service call

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Test: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3), `RfiEscalationConflictException` (Task 3).
- Produces: `RfiController::escalate()` (rewritten, same route `POST /rfis/{id}/escalate`), private helpers `actorIsProjectManagerOrAdminForProject(User, string $projectId): bool`, `userIsActive(User): bool`. Consumed by Task 6 (resolveEscalation reuses `actorIsProjectManagerOrAdminForProject`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php` (read the file first to find where `test_can_escalate_rfi` lives and match its setup style — it uses `$this->apiActingAsTenantAdmin()` from `setUp()`; these new tests need a *non-admin* actor, so add `use Tests\Traits\TenantUserFactoryTrait;` to the class's trait list and these methods anywhere in the class body):

```php
    public function test_project_manager_can_escalate_rfi_in_their_project(): void
    {
        $pmRole = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true],
        );
        $pm = $this->apiFeatureUser; // tenant admin from setUp doubles as escalate target owner check below is not needed here
        $pmUser = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        \App\Models\UserRoleProject::create([
            'project_id' => $this->project->id,
            'user_id' => $pmUser->id,
            'role_id' => $pmRole->id,
        ]);
        $permission = \App\Models\Permission::where('code', 'rfi.escalate')->first();
        $pmRole->permissions()->syncWithoutDetaching([$permission->id]);

        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $token = $this->apiLoginToken($pmUser, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($pmUser, $token))
            ->postJson($this->zena('rfis.escalate', ['id' => $rfi->id]), [
                'escalation_reason' => 'Client needs urgent clarification',
                'escalated_to' => $target->id,
            ]);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertNotNull($rfi->current_escalation_id);
        $this->assertSame('open', $rfi->status);
    }

    public function test_escalate_conflict_when_active_escalation_already_exists(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $payload = ['escalation_reason' => 'First', 'escalated_to' => $target->id];
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), $payload)->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Second', 'escalated_to' => $target->id]);

        $response->assertStatus(409);
    }

    public function test_escalate_rejects_target_from_another_tenant(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $otherTenant = \App\Models\Tenant::factory()->create();
        $foreignTarget = User::factory()->create(['tenant_id' => $otherTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $foreignTarget->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_escalate_rejects_deactivated_target(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $inactiveTarget = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => false]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $inactiveTarget->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_escalate_blocked_on_closed_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'closed',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ]);

        $response->assertStatus(422);
    }
```

(Note: `test_can_escalate_rfi`, the pre-existing test asserting a plain `in_progress → escalated` status change via `apiActingAsTenantAdmin()`, will need its assertion updated in Step 3 below since `status` no longer becomes `escalated` — see that step.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: FAIL — escalate still writes `status='escalated'` directly, no active-escalation conflict handling, no target validation.

- [ ] **Step 3: Rewrite `RfiController::escalate()` and fix the pre-existing test**

In `app/Http/Controllers/Api/RfiController.php`, add these imports after the existing `use` block:

```php
use App\Exceptions\RfiEscalationConflictException;
use App\Models\Role;
use App\Models\UserRoleProject;
use App\Services\RfiEscalationService;
```

Change the constructor to inject the new service:

```php
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private RfiEscalationService $escalationService,
    ) {
    }
```

Replace the entire `escalate()` method (lines 410-459) with:

```php
    /**
     * Escalate RFI.
     */
    public function escalate(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if (in_array($rfi->status, ['closed', 'cancelled'], true)) {
                return $this->errorResponse('Cannot escalate a closed or cancelled RFI', 422);
            }

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot escalate RFIs', 403);
            }

            if (!$this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id)) {
                return $this->errorResponse('Only the project manager or an admin can escalate this RFI', 403);
            }

            $validator = Validator::make($request->all(), [
                'escalation_reason' => 'required|string',
                'escalated_to' => 'required|exists:users,id',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $target = \App\Models\User::where('id', $request->input('escalated_to'))
                ->where('tenant_id', $this->tenantId())
                ->first();

            if (!$target) {
                return $this->errorResponse('Escalation target must belong to the same tenant', 422);
            }

            if (!$this->userIsActive($target)) {
                return $this->errorResponse('Escalation target must be an active user', 422);
            }

            if (!UserRoleProject::where('project_id', $rfi->project_id)->where('user_id', $target->id)->exists()
                && !$this->userHasAdminRole($target)) {
                return $this->errorResponse('Escalation target must be a member of this RFI\'s project', 422);
            }

            try {
                $this->escalationService->escalate(
                    $rfi,
                    $target->id,
                    $user->id,
                    $request->input('escalation_reason'),
                );
            } catch (RfiEscalationConflictException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            }

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.escalate',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            RfiEscalatedNotificationDispatcher_PLACEHOLDER_REMOVED_IN_TASK_10:

            return $this->successResponse($rfi, 'RFI escalated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to escalate RFI: ' . $e->getMessage());
        }
    }
```

Remove the stray label line `RfiEscalatedNotificationDispatcher_PLACEHOLDER_REMOVED_IN_TASK_10:` — it is not valid PHP and is only written here as a visual marker for where Task 10 inserts the notification dispatch call. Delete that entire line now so the file compiles; Task 10 will insert real code at that exact position (immediately after the `auditLogger->log(...)` call, before `return $this->successResponse(...)`).

Add these three private helper methods at the end of the class, immediately before the final closing `}`:

```php
    private function userIsActive(\App\Models\User $user): bool
    {
        return (bool) $user->is_active;
    }

    private function userHasAdminRole(\App\Models\User $user): bool
    {
        return $user->roles()->whereIn('name', ['System Admin', 'Admin', 'super_admin', 'system_admin'])->exists();
    }

    private function actorIsProjectManagerOrAdminForProject(\App\Models\User $user, string $projectId): bool
    {
        if ($this->userHasAdminRole($user)) {
            return true;
        }

        return UserRoleProject::where('user_id', $user->id)
            ->where('project_id', $projectId)
            ->whereHas('role', fn ($q) => $q->where('name', 'project_manager'))
            ->exists();
    }
```

Now fix the pre-existing `test_can_escalate_rfi` test in `tests/Feature/Api/RfiApiTest.php`: read it, and change its assertion from expecting `$rfi->status === 'escalated'` to expecting `$rfi->status` unchanged (still whatever it was before, e.g. `'in_progress'`) and `$rfi->current_escalation_id` to be non-null instead. If the existing test's fixture user is the tenant admin (`apiActingAsTenantAdmin()`), no further change is needed since admins pass `actorIsProjectManagerOrAdminForProject()`; if the fixture RFI's target (`escalated_to` payload) is not tenant-scoped/active in the existing test, add `'tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true` to that target user's factory call.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests in the file green including the corrected `test_can_escalate_rfi`.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): wire escalate() to RfiEscalationService with PM/admin authorization"
```

---

## Task 6: New `POST /rfis/{id}/resolve-escalation` route + controller action

**Files:**
- Modify: `routes/api_zena.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::resolveEscalation()` (Task 4), `actorIsProjectManagerOrAdminForProject()`/`userIsActive()` (Task 5).
- Produces: route `rfis.resolve-escalation`, `RfiController::resolveEscalation()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_escalation_target_can_resolve_their_own_escalation(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        \App\Models\UserRoleProject::firstOrCreate(
            ['project_id' => $this->project->id, 'user_id' => $target->id],
            ['role_id' => \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true])->id],
        );

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $token = $this->apiLoginToken($target, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($target, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), [
                'resolution' => 'Answered the client directly by phone',
            ]);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertNull($rfi->current_escalation_id);
    }

    public function test_resolve_escalation_by_unrelated_user_is_forbidden(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $unrelated = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $token = $this->apiLoginToken($unrelated, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($unrelated, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), [
                'resolution' => 'Trying to resolve someone else\'s escalation',
            ]);

        $response->assertStatus(403);
    }

    public function test_resolve_escalation_twice_returns_conflict(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'First'])
            ->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Second attempt']);

        $response->assertStatus(409);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter resolve_escalation`
Expected: FAIL — route `rfis.resolve-escalation` does not exist.

- [ ] **Step 3: Add the route**

In `routes/api_zena.php`, add this line immediately after the `escalate` route (inside the same `rfis` group):

```php
            Route::post('/{id}/resolve-escalation', [\App\Http\Controllers\Api\RfiController::class, 'resolveEscalation'])->middleware('rbac:rfi.escalate')->name('rfis.resolve-escalation');
```

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/Api/RfiController.php`, add this method immediately after `escalate()`:

```php
    /**
     * Resolve the RFI's active escalation.
     */
    public function resolveEscalation(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot resolve escalations', 403);
            }

            if (!$rfi->current_escalation_id) {
                return $this->errorResponse('This RFI has no active escalation', 404);
            }

            $isTarget = $rfi->escalated_to === $user->id;
            $isPmOrAdmin = $this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id);

            if (!$isTarget && !$isPmOrAdmin) {
                return $this->errorResponse('Only the escalation target, project manager, or an admin can resolve this escalation', 403);
            }

            $validator = Validator::make($request->all(), [
                'resolution' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            try {
                $this->escalationService->resolveEscalation($rfi, $user->id, $request->input('resolution'));
            } catch (RfiEscalationConflictException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            } catch (\App\Exceptions\RfiEscalationNotFoundException $e) {
                return $this->errorResponse($e->getMessage(), 404);
            }

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.resolve_escalation',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'Escalation resolved successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to resolve escalation: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 6: Commit**

```bash
git add routes/api_zena.php app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): add resolveEscalation() action open to target, project PM, or admin"
```

---

## Task 7: `respond()` lifecycle guard — only from `open`/`in_progress`

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: none new.
- Produces: guard inside existing `RfiController::respond()`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_cannot_respond_to_a_closed_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'closed',
        ]);

        $response = $this->apiPost($this->zena('rfis.respond', ['id' => $rfi->id]), [
            'response' => 'Trying to respond after close',
            'status' => 'answered',
        ]);

        $response->assertStatus(422);
    }

    public function test_can_respond_to_an_open_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.respond', ['id' => $rfi->id]), [
            'response' => 'Here is the answer',
            'status' => 'answered',
        ]);

        $response->assertStatus(200);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter respond`
Expected: `test_cannot_respond_to_a_closed_rfi` FAILs (currently returns 200).

- [ ] **Step 3: Add the guard**

In `app/Http/Controllers/Api/RfiController.php`, in `respond()`, insert this check immediately after `$rfi = $this->rfiForTenant($id);` (before the `$validator = Validator::make(...)` line):

```php
            if (!in_array($rfi->status, ['open', 'in_progress'], true)) {
                return $this->errorResponse('RFI can only be responded to while open or in progress', 422);
            }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green (verify the pre-existing `test_can_respond_to_rfi`-style test, if any, still uses an `open`/`in_progress` fixture).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): guard respond() to only accept open/in_progress RFIs"
```

---

## Task 8: `close()` blocked while an escalation is active

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::hasActiveEscalation()` (Task 3).
- Produces: guard inside existing `RfiController::close()`.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_cannot_close_rfi_while_escalation_is_active(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'answered',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Still need confirmation',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.close', ['id' => $rfi->id]));

        $response->assertStatus(409);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter test_cannot_close_rfi_while_escalation_is_active`
Expected: FAIL (currently returns 200, closes the RFI regardless of escalation state).

- [ ] **Step 3: Add the guard**

In `app/Http/Controllers/Api/RfiController.php`, add `RfiEscalationService` usage (already injected via Task 5's constructor change) — in `close()`, replace:

```php
            if ($rfi->status !== 'answered') {
                return $this->errorResponse('RFI must be answered before it can be closed', 400);
            }
```

with:

```php
            if ($rfi->status !== 'answered') {
                return $this->errorResponse('RFI must be answered before it can be closed', 400);
            }

            if ($this->escalationService->hasActiveEscalation($rfi->id)) {
                return $this->errorResponse('Cannot close an RFI while it has an active escalation — resolve the escalation first', 409);
            }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): block close() while an escalation is active"
```

---

## Task 9: `rfi.cancel` permission + `cancel()` action (PM/admin required while escalated, atomic resolve)

**Files:**
- Modify: `database/seeders/ZenaPermissionsSeeder.php`
- Modify: `routes/api_zena.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::resolveEscalation()` (Task 4), `actorIsProjectManagerOrAdminForProject()` (Task 5).
- Produces: permission `rfi.cancel`, route `rfis.cancel`, `RfiController::cancel()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_can_cancel_open_rfi_without_active_escalation(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), [
            'reason' => 'Scope no longer applies',
        ]);

        $response->assertStatus(200);
        $this->assertSame('cancelled', $rfi->fresh()->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), []);

        $response->assertStatus(422);
    }

    public function test_cancel_with_active_escalation_requires_pm_or_admin_and_resolves_escalation_atomically(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        // Admin (apiFeatureUser via apiActingAsTenantAdmin) cancels while escalated.
        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), [
            'reason' => 'Project cancelled by client',
        ]);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertSame('cancelled', $rfi->status);
        $this->assertNull($rfi->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($escalation->resolved_at);
        $this->assertSame(\App\Models\RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED, $escalation->resolution_type);
    }

    public function test_cancel_with_active_escalation_by_non_pm_non_admin_is_forbidden(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $regular = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $permission = \App\Models\Permission::where('code', 'rfi.cancel')->first();
        $role = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        \App\Models\UserRoleProject::create(['project_id' => $this->project->id, 'user_id' => $regular->id, 'role_id' => $role->id]);

        $token = $this->apiLoginToken($regular, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($regular, $token))
            ->postJson($this->zena('rfis.cancel', ['id' => $rfi->id]), ['reason' => 'Trying to cancel'])
            ;

        $response->assertStatus(403);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter cancel`
Expected: FAIL — route `rfis.cancel` and permission `rfi.cancel` do not exist.

- [ ] **Step 3: Add the permission**

In `database/seeders/ZenaPermissionsSeeder.php`, add this line to the `RFIs` block of `CANONICAL_PERMISSIONS`, immediately after the `rfi.escalate` entry:

```php
        ['code' => 'rfi.cancel', 'module' => 'rfi', 'action' => 'cancel', 'description' => 'Cancel an RFI'],
```

- [ ] **Step 4: Add the route**

In `routes/api_zena.php`, add immediately after the `resolve-escalation` route added in Task 6:

```php
            Route::post('/{id}/cancel', [\App\Http\Controllers\Api\RfiController::class, 'cancel'])->middleware('rbac:rfi.cancel')->name('rfis.cancel');
```

- [ ] **Step 5: Add the controller action**

Add to `app/Http/Controllers/Api/RfiController.php`, immediately after `resolveEscalation()`:

```php
    /**
     * Cancel an RFI.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();

            if (!$user) {
                return $this->unauthorized('Authentication required');
            }

            $rfi = $this->rfiForTenant($id);

            if (in_array($rfi->status, ['closed', 'cancelled'], true)) {
                return $this->errorResponse('RFI is already closed or cancelled', 422);
            }

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot cancel RFIs', 403);
            }

            $validator = Validator::make($request->all(), [
                'reason' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $hasActiveEscalation = $this->escalationService->hasActiveEscalation($rfi->id);

            if ($hasActiveEscalation && !$this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id)) {
                return $this->errorResponse('Only the project manager or an admin can cancel an RFI while it has an active escalation', 403);
            }

            DB::transaction(function () use ($rfi, $user, $request, $hasActiveEscalation) {
                if ($hasActiveEscalation) {
                    $this->escalationService->resolveEscalation(
                        $rfi,
                        $user->id,
                        'RFI cancelled: ' . $request->input('reason'),
                        \App\Models\RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED,
                    );
                }

                $rfi->fresh()->update([
                    'status' => 'cancelled',
                ]);
            });

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log(
                $request,
                'zena.rfi.cancel',
                'rfi',
                (string) $rfi->id,
                200,
                $rfi->project_id,
                $this->tenantId()
            );

            return $this->successResponse($rfi, 'RFI cancelled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to cancel RFI: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan db:seed --class=ZenaPermissionsSeeder && ./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green. (Note: `RefreshDatabase` re-runs seeders configured in `TestCase`/`DatabaseSeeder` per the test suite's existing convention — confirm `rfi.cancel` is present in the seeded permission set the tests already rely on before assuming a manual `db:seed` is needed in CI.)

- [ ] **Step 7: Commit**

```bash
git add database/seeders/ZenaPermissionsSeeder.php routes/api_zena.php app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): add cancel() action with rfi.cancel permission and PM/admin escalation gate"
```

---

## Task 10: Seed `rfi.escalate` for the PM role

**Files:**
- Create: `database/seeders/ZenaProjectManagerRolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`

**Interfaces:**
- Consumes: `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` (existing).
- Produces: `project_manager` role gains `rfi.escalate` (and `rfi.cancel` from Task 9) permission after seeding.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenaProjectManagerRolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_role_receives_rfi_escalate_and_cancel_permissions(): void
    {
        $this->seed(\Database\Seeders\ZenaPermissionsSeeder::class);
        Role::firstOrCreate(['name' => 'project_manager'], ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true]);

        $this->seed(\Database\Seeders\ZenaProjectManagerRolePermissionSeeder::class);

        $pmRole = Role::where('name', 'project_manager')->first();
        $escalate = Permission::where('code', 'rfi.escalate')->first();
        $cancel = Permission::where('code', 'rfi.cancel')->first();

        $this->assertTrue($pmRole->permissions->contains($escalate->id));
        $this->assertTrue($pmRole->permissions->contains($cancel->id));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`
Expected: FAIL — "Class Database\Seeders\ZenaProjectManagerRolePermissionSeeder not found".

- [ ] **Step 3: Write the seeder**

`database/seeders/ZenaProjectManagerRolePermissionSeeder.php`:

```php
<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class ZenaProjectManagerRolePermissionSeeder extends Seeder
{
    public const PROJECT_MANAGER_PERMISSION_CODES = [
        'rfi.escalate',
        'rfi.cancel',
    ];

    public function run(): void
    {
        $role = Role::whereRaw('LOWER(name) = ?', ['project_manager'])->first();

        if (!$role) {
            return;
        }

        $permissionIds = Permission::whereIn('code', self::PROJECT_MANAGER_PERMISSION_CODES)->pluck('id')->all();

        if (empty($permissionIds)) {
            return;
        }

        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
```

Add `Database\Seeders\ZenaProjectManagerRolePermissionSeeder::class` to the `$this->call([...])` list in `database/seeders/DatabaseSeeder.php`, immediately after `ZenaAdminRolePermissionSeeder::class`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ZenaProjectManagerRolePermissionSeeder.php database/seeders/DatabaseSeeder.php tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php
git commit -m "feat(rfi): seed rfi.escalate and rfi.cancel permissions for the project_manager role"
```

---

## Task 11: `RfiEscalatedNotification` — brand-new class, after-commit dispatch, test queries the table

**Files:**
- Create: `app/Notifications/RfiEscalatedNotification.php`
- Modify: `app/Services/RfiEscalationService.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Test: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3).
- Produces: `App\Notifications\RfiEscalatedNotification`, dispatched from `RfiEscalationService::escalate()` after the transaction commits.

**Explicit constraint**: do NOT touch `app/Listeners/RfiEventListener.php` or `App\Events\Rfi*` — they stay dead. This is a wholly separate code path.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_escalate_creates_an_in_app_notification_for_the_target_after_commit(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Urgent',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $target->id,
            'notifiable_type' => \App\Models\User::class,
            'type' => \App\Notifications\RfiEscalatedNotification::class,
        ]);
    }

    public function test_escalate_conflict_does_not_create_a_notification(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id,
            'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'First',
            'escalated_to' => $target->id,
        ])->assertStatus(200);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), [
            'escalation_reason' => 'Second (should conflict)',
            'escalated_to' => $target->id,
        ])->assertStatus(409);

        $this->assertDatabaseCount('notifications', 1);
    }
```

(These tests require Laravel's standard `notifications` table migration to already exist — confirm via `php artisan migrate:status` before running; if absent, run `php artisan notifications:table && php artisan migrate` first as a prerequisite, not part of this task's own migration set since it's a Laravel framework table likely already present from another feature.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter notification`
Expected: FAIL — "Class App\Notifications\RfiEscalatedNotification not found", 0 rows in `notifications`.

- [ ] **Step 3: Write the notification class**

`app/Notifications/RfiEscalatedNotification.php`:

```php
<?php declare(strict_types=1);

namespace App\Notifications;

use App\Models\RfiEscalation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class RfiEscalatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly RfiEscalation $escalation)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'rfi_id' => $this->escalation->rfi_id,
            'escalation_id' => $this->escalation->id,
            'escalation_reason' => $this->escalation->escalation_reason,
            'escalated_by' => $this->escalation->escalated_by,
        ];
    }
}
```

- [ ] **Step 4: Dispatch after commit from the service**

In `app/Services/RfiEscalationService.php`, add imports:

```php
use App\Notifications\RfiEscalatedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
```

Change `escalate()` to dispatch after the transaction commits — replace:

```php
    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        return DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason) {
```

with:

```php
    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        $escalation = DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason) {
```

and change the closure's `return $escalation;` to just `return $escalation;` (unchanged), then after the closing `});` of the transaction, before the method's final `}`, add:

```php

        $this->dispatchEscalatedNotification($escalation);

        return $escalation;
    }

    private function dispatchEscalatedNotification(RfiEscalation $escalation): void
    {
        try {
            $target = User::find($escalation->escalated_to);

            if ($target) {
                $target->notify(new RfiEscalatedNotification($escalation));
            }
        } catch (\Throwable $e) {
            Log::error('rfi_escalation_notification_failed', [
                'escalation_id' => $escalation->id,
                'rfi_id' => $escalation->rfi_id,
                'exception' => $e->getMessage(),
            ]);
        }
```

(Verify after editing that the method's final closing brace count is correct — `escalate()` now has: `$escalation = DB::transaction(...)`, then a call to `dispatchEscalatedNotification()`, then `return $escalation;`, then the method's closing `}`. `dispatchEscalatedNotification()` is a separate private method with its own `try/catch` and closing `}`.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green, including the two notification tests (the conflict test creates 0 additional notifications because the second `escalate()` call throws before `dispatchEscalatedNotification()` is ever reached — the exception propagates out of `DB::transaction()`'s closure before that line executes).

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/RfiEscalatedNotification.php app/Services/RfiEscalationService.php app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): add RfiEscalatedNotification dispatched after commit, log-not-rollback on failure"
```

---

## Task 12: Legacy deployment gate — Preflight report command

**Files:**
- Create: `app/Console/Commands/RfiEscalationPreflightReport.php`
- Test: `tests/Feature/Console/RfiEscalationPreflightReportTest.php`

**Interfaces:**
- Consumes: `App\Models\Rfi` (existing).
- Produces: Artisan command `rfi:escalation-preflight-report {--output=}`. Consumed conceptually by Task 13 (operator reads the CSV it produces).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationPreflightReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_lists_escalated_rows_and_anomalous_pending_rows(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $assignee = User::factory()->create(['tenant_id' => $tenant->id]);

        $escalatedWithAssignee = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0001', 'status' => 'escalated', 'assigned_to' => $assignee->id,
        ]);
        $escalatedWithoutAssignee = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'B',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0002', 'status' => 'escalated',
        ]);
        $pendingAnomaly = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'C',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0003', 'status' => 'pending',
        ]);
        $closedWithSnapshot = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'D',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0004', 'status' => 'closed',
            'escalated_to' => $assignee->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'old escalation before close overwrote status',
        ]);
        Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'E',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0005', 'status' => 'open',
        ]);

        $outputPath = storage_path('app/test-preflight-report.csv');
        @unlink($outputPath);

        $this->artisan('rfi:escalation-preflight-report', ['--output' => $outputPath])
            ->assertExitCode(0);

        $this->assertFileExists($outputPath);
        $contents = file_get_contents($outputPath);

        $this->assertStringContainsString($escalatedWithAssignee->id, $contents);
        $this->assertStringContainsString($escalatedWithoutAssignee->id, $contents);
        $this->assertStringContainsString($pendingAnomaly->id, $contents);
        $this->assertStringContainsString($closedWithSnapshot->id, $contents);
        $this->assertStringNotContainsString('T-RFI-0005', $contents);

        @unlink($outputPath);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiEscalationPreflightReportTest.php`
Expected: FAIL — command `rfi:escalation-preflight-report` does not exist.

- [ ] **Step 3: Write the command**

`app/Console/Commands/RfiEscalationPreflightReport.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use Illuminate\Console\Command;

class RfiEscalationPreflightReport extends Command
{
    protected $signature = 'rfi:escalation-preflight-report {--output= : Path to write the CSV report to}';

    protected $description = 'List every legacy RFI row that needs an operator confirmation before the escalation cutover can run';

    public function handle(): int
    {
        $outputPath = $this->option('output') ?: storage_path('app/rfi-escalation-preflight-' . now()->format('Ymd-His') . '.csv');

        $rows = [];
        $rows[] = ['rfi_id', 'legacy_status', 'assigned_to', 'has_escalation_snapshot', 'proposed_lifecycle', 'proposed_escalation_state', 'reason'];

        Rfi::where('status', 'escalated')->orderBy('id')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $rfi) {
                $proposedLifecycle = $rfi->assigned_to ? 'in_progress' : 'open';
                $rows[] = [$rfi->id, 'escalated', (string) $rfi->assigned_to, 'yes', $proposedLifecycle, 'unresolved', 'status=escalated, no event log to confirm timing'];
            }
        });

        Rfi::where('status', '!=', 'escalated')
            ->whereNotNull('escalated_to')
            ->orderBy('id')
            ->chunk(200, function ($chunk) use (&$rows) {
                foreach ($chunk as $rfi) {
                    $rows[] = [$rfi->id, $rfi->status, (string) $rfi->assigned_to, 'yes', $rfi->status, 'resolved_estimated', 'has escalation snapshot but status already moved on; resolved_at will be estimated from updated_at'];
                }
            });

        Rfi::where('status', 'pending')->orderBy('id')->chunk(200, function ($chunk) use (&$rows) {
            foreach ($chunk as $rfi) {
                $rows[] = [$rfi->id, 'pending', (string) $rfi->assigned_to, 'no', 'open', 'none', 'anomaly: pending status never set by any current action'];
            }
        });

        $handle = fopen($outputPath, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->info("Preflight report written to {$outputPath} (" . (count($rows) - 1) . ' records needing confirmation)');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiEscalationPreflightReportTest.php`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/RfiEscalationPreflightReport.php tests/Feature/Console/RfiEscalationPreflightReportTest.php
git commit -m "feat(rfi): add legacy escalation preflight report command"
```

---

## Task 13: Legacy deployment gate — confirmation tracking table + confirm command

**Files:**
- Create: `database/migrations/2026_07_26_090200_create_rfi_legacy_migration_confirmations_table.php`
- Create: `app/Models/RfiLegacyMigrationConfirmation.php`
- Create: `app/Console/Commands/RfiConfirmLegacyEscalation.php`
- Test: `tests/Feature/Console/RfiConfirmLegacyEscalationTest.php`

**Interfaces:**
- Consumes: `App\Models\Rfi`, `App\Models\RfiEscalation` (Task 1).
- Produces: table `rfi_legacy_migration_confirmations`, model `RfiLegacyMigrationConfirmation`, command `rfi:confirm-legacy-escalation`. Consumed by Task 14 (cutover checks this table).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiLegacyMigrationConfirmation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiConfirmLegacyEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_an_escalated_row_creates_unresolved_escalation_and_confirmation_record(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0010', 'status' => 'escalated', 'assigned_to' => $user->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id, 'escalated_at' => now(),
            'escalation_reason' => 'legacy reason',
        ]);

        $this->artisan('rfi:confirm-legacy-escalation', [
            'rfi_id' => $rfi->id,
            '--lifecycle' => 'in_progress',
            '--escalation' => 'unresolved',
            '--confirmed-by' => $user->id,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('rfi_legacy_migration_confirmations', [
            'rfi_id' => $rfi->id,
            'confirmed_by' => $user->id,
        ]);

        $rfi->refresh();
        $this->assertSame('in_progress', $rfi->status);
        $this->assertNotNull($rfi->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNull($escalation->resolved_at);
    }

    public function test_confirming_a_pending_anomaly_maps_to_open_with_no_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'B',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0011', 'status' => 'pending',
        ]);

        $this->artisan('rfi:confirm-legacy-escalation', [
            'rfi_id' => $rfi->id,
            '--lifecycle' => 'open',
            '--escalation' => 'none',
            '--confirmed-by' => $user->id,
        ])->assertExitCode(0);

        $rfi->refresh();
        $this->assertSame('open', $rfi->status);
        $this->assertNull($rfi->current_escalation_id);
        $this->assertSame(0, \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiConfirmLegacyEscalationTest.php`
Expected: FAIL — table/command do not exist.

- [ ] **Step 3: Write the migration and model**

`database/migrations/2026_07_26_090200_create_rfi_legacy_migration_confirmations_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfi_legacy_migration_confirmations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('rfi_id')->unique();
            $table->ulid('confirmed_by');
            $table->timestamp('confirmed_at');
            $table->string('confirmed_lifecycle_status');
            $table->string('confirmed_escalation_state');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('rfi_id')->references('id')->on('rfis')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_legacy_migration_confirmations');
    }
};
```

`app/Models/RfiLegacyMigrationConfirmation.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RfiLegacyMigrationConfirmation extends Model
{
    use HasUlids;

    protected $table = 'rfi_legacy_migration_confirmations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'rfi_id',
        'confirmed_by',
        'confirmed_at',
        'confirmed_lifecycle_status',
        'confirmed_escalation_state',
        'notes',
    ];

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
```

- [ ] **Step 4: Write the confirm command**

`app/Console/Commands/RfiConfirmLegacyEscalation.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\RfiLegacyMigrationConfirmation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RfiConfirmLegacyEscalation extends Command
{
    protected $signature = 'rfi:confirm-legacy-escalation
        {rfi_id : The RFI to confirm}
        {--lifecycle= : The confirmed lifecycle status (open|in_progress|answered|closed)}
        {--escalation= : unresolved|resolved|none}
        {--confirmed-by= : User id of the operator confirming this record}
        {--notes= : Optional notes}';

    protected $description = 'Record an operator confirmation for one legacy RFI row ahead of the escalation cutover';

    public function handle(): int
    {
        $rfiId = $this->argument('rfi_id');
        $lifecycle = $this->option('lifecycle');
        $escalationState = $this->option('escalation');
        $confirmedBy = $this->option('confirmed-by');

        if (!in_array($lifecycle, ['open', 'in_progress', 'answered', 'closed', 'cancelled'], true)) {
            $this->error('--lifecycle must be one of: open, in_progress, answered, closed, cancelled');
            return self::FAILURE;
        }

        if (!in_array($escalationState, ['unresolved', 'resolved', 'none'], true)) {
            $this->error('--escalation must be one of: unresolved, resolved, none');
            return self::FAILURE;
        }

        if (!$confirmedBy) {
            $this->error('--confirmed-by is required');
            return self::FAILURE;
        }

        $rfi = Rfi::find($rfiId);

        if (!$rfi) {
            $this->error("RFI {$rfiId} not found");
            return self::FAILURE;
        }

        DB::transaction(function () use ($rfi, $lifecycle, $escalationState, $confirmedBy) {
            if ($escalationState === 'unresolved') {
                $escalation = RfiEscalation::create([
                    'rfi_id' => $rfi->id,
                    'tenant_id' => $rfi->tenant_id,
                    'escalated_to' => $rfi->escalated_to ?? $confirmedBy,
                    'escalated_by' => $rfi->escalated_by ?? $confirmedBy,
                    'escalated_at' => $rfi->escalated_at ?? now(),
                    'escalation_reason' => $rfi->escalation_reason ?? 'Backfilled from legacy status=escalated during migration confirmation.',
                ]);
                $rfi->current_escalation_id = $escalation->id;
            } elseif ($escalationState === 'resolved') {
                $escalation = RfiEscalation::create([
                    'rfi_id' => $rfi->id,
                    'tenant_id' => $rfi->tenant_id,
                    'escalated_to' => $rfi->escalated_to ?? $confirmedBy,
                    'escalated_by' => $rfi->escalated_by ?? $confirmedBy,
                    'escalated_at' => $rfi->escalated_at ?? $rfi->updated_at,
                    'escalation_reason' => $rfi->escalation_reason ?? 'Backfilled: legacy escalation snapshot found on a non-escalated RFI.',
                    'resolved_at' => $rfi->updated_at,
                    'resolved_by' => null,
                    'resolution' => 'Backfilled automatically: exact resolution time/actor unknown, no event log available. Estimated from updated_at.',
                    'resolution_type' => RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
                ]);
                $rfi->current_escalation_id = null;
            } else {
                $rfi->current_escalation_id = null;
            }

            $rfi->status = $lifecycle;
            $rfi->save();

            RfiLegacyMigrationConfirmation::updateOrCreate(
                ['rfi_id' => $rfi->id],
                [
                    'confirmed_by' => $confirmedBy,
                    'confirmed_at' => now(),
                    'confirmed_lifecycle_status' => $lifecycle,
                    'confirmed_escalation_state' => $escalationState,
                    'notes' => $this->option('notes'),
                ],
            );
        });

        $this->info("Confirmed RFI {$rfi->id}: lifecycle={$lifecycle}, escalation={$escalationState}");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run migration and tests**

Run: `php artisan migrate && ./vendor/bin/phpunit tests/Feature/Console/RfiConfirmLegacyEscalationTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_26_090200_create_rfi_legacy_migration_confirmations_table.php app/Models/RfiLegacyMigrationConfirmation.php app/Console/Commands/RfiConfirmLegacyEscalation.php tests/Feature/Console/RfiConfirmLegacyEscalationTest.php
git commit -m "feat(rfi): add per-record legacy escalation confirmation table and command"
```

---

## Task 14: Legacy deployment gate — Cutover command with hard stop condition

**Files:**
- Create: `database/migrations/2026_07_26_090300_create_rfi_escalation_migration_state_table.php`
- Create: `app/Console/Commands/RfiEscalationCutover.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Test: `tests/Feature/Console/RfiEscalationCutoverTest.php`

**Interfaces:**
- Consumes: `RfiLegacyMigrationConfirmation` (Task 13).
- Produces: table `rfi_escalation_migration_state` (single row, `cutover_completed_at`), command `rfi:escalation-cutover`. `RfiController::update()`'s status validation becomes cutover-aware.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiLegacyMigrationConfirmation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiEscalationCutoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_cutover_refuses_to_run_while_any_legacy_row_is_unconfirmed(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0020', 'status' => 'escalated',
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(1);

        $this->assertDatabaseMissing('rfi_escalation_migration_state', []);
    }

    public function test_cutover_succeeds_when_every_legacy_row_is_confirmed(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'A',
            'description' => 'd', 'priority' => 'medium', 'created_by' => $user->id,
            'rfi_number' => 'T-RFI-0021', 'status' => 'in_progress',
        ]);
        RfiLegacyMigrationConfirmation::create([
            'rfi_id' => $rfi->id,
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
            'confirmed_lifecycle_status' => 'in_progress',
            'confirmed_escalation_state' => 'none',
        ]);

        $this->artisan('rfi:escalation-cutover')->assertExitCode(0);

        $this->assertDatabaseCount('rfi_escalation_migration_state', 1);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiEscalationCutoverTest.php`
Expected: FAIL — table/command do not exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_07_26_090300_create_rfi_escalation_migration_state_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfi_escalation_migration_state', function (Blueprint $table) {
            $table->id();
            $table->timestamp('cutover_completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfi_escalation_migration_state');
    }
};
```

- [ ] **Step 4: Write the cutover command**

`app/Console/Commands/RfiEscalationCutover.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Rfi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RfiEscalationCutover extends Command
{
    protected $signature = 'rfi:escalation-cutover';

    protected $description = 'Flip application-level validation to reject status=escalated/pending, ONLY if every legacy row has an operator confirmation';

    public function handle(): int
    {
        $unconfirmedEscalated = Rfi::where('status', 'escalated')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('rfi_legacy_migration_confirmations')
                    ->whereColumn('rfi_legacy_migration_confirmations.rfi_id', 'rfis.id');
            })
            ->count();

        $unconfirmedPending = Rfi::where('status', 'pending')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('rfi_legacy_migration_confirmations')
                    ->whereColumn('rfi_legacy_migration_confirmations.rfi_id', 'rfis.id');
            })
            ->count();

        $unconfirmedSnapshot = Rfi::where('status', '!=', 'escalated')
            ->whereNotNull('escalated_to')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('rfi_legacy_migration_confirmations')
                    ->whereColumn('rfi_legacy_migration_confirmations.rfi_id', 'rfis.id');
            })
            ->count();

        $totalUnconfirmed = $unconfirmedEscalated + $unconfirmedPending + $unconfirmedSnapshot;

        if ($totalUnconfirmed > 0) {
            $this->error("Cutover blocked: {$totalUnconfirmed} legacy RFI record(s) still unconfirmed (escalated={$unconfirmedEscalated}, pending={$unconfirmedPending}, snapshot-only={$unconfirmedSnapshot}). Run rfi:escalation-preflight-report and rfi:confirm-legacy-escalation for each one first.");
            return self::FAILURE;
        }

        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->info('Cutover complete: application-level validation will now reject status=escalated/pending on new writes.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Make `RfiController::update()` cutover-aware**

In `app/Http/Controllers/Api/RfiController.php`, in `update()`, replace:

```php
                'status' => 'sometimes|in:open,answered,closed',
```

with:

```php
                'status' => 'sometimes|in:' . implode(',', $this->allowedStatusValues()),
```

Add this private method at the end of the class:

```php
    private function allowedStatusValues(): array
    {
        $cutoverComplete = DB::table('rfi_escalation_migration_state')->whereNotNull('cutover_completed_at')->exists();

        $base = ['open', 'in_progress', 'answered', 'closed', 'cancelled'];

        return $cutoverComplete ? $base : array_merge($base, ['escalated', 'pending']);
    }
```

- [ ] **Step 6: Run migration and tests**

Run: `php artisan migrate && ./vendor/bin/phpunit tests/Feature/Console/RfiEscalationCutoverTest.php tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_26_090300_create_rfi_escalation_migration_state_table.php app/Console/Commands/RfiEscalationCutover.php app/Http/Controllers/Api/RfiController.php tests/Feature/Console/RfiEscalationCutoverTest.php
git commit -m "feat(rfi): add escalation cutover command with hard stop condition on unconfirmed legacy rows"
```

---

## Task 15: Concurrency tests — two simultaneous escalate()/resolveEscalation() requests

**Files:**
- Modify: `tests/Unit/Services/RfiEscalationServiceTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService` (Tasks 3-4).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Services/RfiEscalationServiceTest.php`:

```php
    public function test_two_concurrent_escalate_calls_one_wins_one_conflicts(): void
    {
        $rfi = $this->rfi;

        $first = $this->service->escalate($rfi, $this->target->id, $this->escalator->id, 'First');

        $this->expectException(RfiEscalationConflictException::class);
        $this->service->escalate($rfi->fresh(), $this->target->id, $this->escalator->id, 'Second, should conflict because first already committed');

        $this->assertNotNull($first->id);
    }

    public function test_two_concurrent_resolve_calls_on_same_escalation_one_wins_one_conflicts(): void
    {
        $rfi = $this->rfi;
        $this->service->escalate($rfi, $this->target->id, $this->escalator->id, 'Urgent');

        $this->service->resolveEscalation($rfi->fresh(), $this->target->id, 'First resolve wins');

        $this->expectException(RfiEscalationConflictException::class);
        $this->service->resolveEscalation($rfi->fresh(), $this->escalator->id, 'Second resolve, should conflict');
    }
```

(Note: true multi-process concurrency cannot be exercised inside a single PHPUnit process against SQLite/MySQL without a second connection; these tests exercise the *sequential* guard logic — the second call, made after the first has already committed, must observe the row's committed state and be rejected. This validates the guard condition that would also reject a genuinely concurrent second transaction once it acquires the row lock, per spec §9's design — full multi-connection concurrency validation belongs in a manual/staging load test, out of scope for this automated suite.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: These specific two tests should already PASS given Tasks 3-4's implementation (they test the same guard as the conflict tests already written) — if so, this task's "step 2" confirms no regression rather than a red state. If either fails, the guard logic from Task 3/4 has a bug — fix it before proceeding, do not weaken the test.

- [ ] **Step 3: N/A — no new implementation code, this task only strengthens test coverage of existing guards**

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: PASS (8 tests total in the file).

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Services/RfiEscalationServiceTest.php
git commit -m "test(rfi): add explicit concurrency-guard regression tests for escalate/resolveEscalation"
```

---

## Task 16: Full regression pass

**Files:** none created/modified — verification only.

- [ ] **Step 1: Run every touched test file together**

Run: `./vendor/bin/phpunit tests/Unit/Models/RfiEscalationTest.php tests/Unit/Models/RfiTest.php tests/Unit/Services/RfiEscalationServiceTest.php tests/Feature/Api/RfiApiTest.php tests/Feature/Console tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`
Expected: PASS, 0 failures.

- [ ] **Step 2: Run the full test suite to check for unrelated regressions**

Run: `./vendor/bin/phpunit`
Expected: same pass/fail baseline as before this plan started, plus this plan's new tests, no new failures in files this plan didn't touch (e.g. `RfiWorkflowTest`, `OperatorRfiUiTest` — read their output carefully since Task 7's `respond()` guard and Task 8's `close()` guard change existing behavior those tests may exercise; if either fails, fix the guard's edge case or fix the pre-existing test's fixture to match the new, intentionally-tightened contract — do not silently loosen the guard back to pre-plan behavior without flagging it).

- [ ] **Step 3: Manually verify the legacy deployment gate order end-to-end**

Run in sequence against a local dev DB seeded with at least one `status='escalated'` row:
1. `php artisan rfi:escalation-preflight-report` — confirm it lists the row.
2. `php artisan rfi:escalation-cutover` — confirm exit code 1 (blocked).
3. `php artisan rfi:confirm-legacy-escalation {the rfi id} --lifecycle=in_progress --escalation=unresolved --confirmed-by={a real user id}` — confirm exit code 0.
4. `php artisan rfi:escalation-cutover` — confirm exit code 0 this time.

Expected: exactly this sequence of exit codes (0, 1, 0, 0), proving the stop condition is real and not decorative.

- [ ] **Step 4: Commit (only if Step 2/3 required a follow-up fix; otherwise nothing to commit)**

```bash
git status
```

---

## Self-Review

**Spec coverage:**
- §1 (pure lifecycle enum, no `escalated`/`pending` value going forward) → Tasks 5, 7, 9, 14 (validation list drops them only after cutover).
- §2 (`rfi_escalations`, immutable origin fields, resolution written once, ≤1 active) → Tasks 1, 3, 4.
- §2.1/§2.2 (`current_escalation_id`, compatibility mirror, no new reader treats mirror as truth) → Task 2 (pointer), Tasks 3-4 (mirror sync inside the same transaction as every writer this plan adds).
- §3-4 (state diagram, transition table) → Tasks 5-9 (guards on escalate/respond/close/cancel).
- §5 (owner/reassignment) → unchanged from current `assign()`, no task needed (spec explicitly carries it forward unmodified).
- §6 (legacy deployment gate: additive → preflight → operator confirmation → cutover, hard stop condition) → Tasks 12, 13, 14.
- §7 (answer doesn't resolve, close blocked, cancel-with-escalation PM/admin+atomic) → Tasks 7, 8, 9.
- §8 (authorization matrix: PM+admin escalate, target/PM/admin resolve, active actor/target) → Tasks 5, 6, 9, 10.
- §9 (lock both rows, resolution once, 409 on conflict) → Tasks 3, 4, 15.
- §10 (new Notification class, after-commit, log-not-rollback, no dead-listener reuse) → Task 11.
- §11 (32-case test matrix) → distributed across Tasks 3-15; every numbered case in the spec has a corresponding test in some task above (schema/model cases in 1-2, escalate/resolve/conflict/double-resolve/cycle in 3-4, respond/close/cancel guards in 7-9, cross-tenant/project/active-user in 5, notification-after-commit in 11, migration decision branches in 12-14, concurrency in 15).
- §12/§13 (operational gaps, non-goals) → reflected in Global Constraints (Overdue Engine explicitly out of scope; no field drop; project-membership middleware gap noted but not silently "fixed" by this plan since spec left it as a separate concern — Task 5's target-membership check is new application code, not a claim that the underlying RBAC middleware gap is resolved).

**Placeholder scan:** no "TBD"/"add error handling"/"similar to Task N" found. The one intentionally temporary marker (`RfiEscalatedNotificationDispatcher_PLACEHOLDER_REMOVED_IN_TASK_10`) is explicitly a dead-on-arrival label the plan instructs to delete in the same step it's introduced — not a deferred TODO — kept only as a textual anchor so Task 10 knows exactly where to insert code; flagging this here in case a reviewer treats it as a placeholder violation, since the intent is that it never exists in committed code (Task 5's Step 3 deletes it before Step 4's tests run).

**Type consistency check:** `RfiEscalationService::escalate(Rfi, string, string, string): RfiEscalation` (Task 3) called identically in Tasks 5, 9, 13. `resolveEscalation(Rfi, string, string, string = ...): RfiEscalation` (Task 4) called identically in Tasks 6, 9. `hasActiveEscalation(string): bool` (Task 3) called identically in Task 8. `RfiEscalationConflictException`/`RfiEscalationNotFoundException` caught with matching class names in Tasks 5, 6. `actorIsProjectManagerOrAdminForProject(User, string): bool` and `userIsActive(User): bool` (Task 5) reused with identical signatures in Tasks 6, 8, 9.

---

Plan complete and saved to `docs/superpowers/plans/2026-07-26-rfi-lifecycle-escalation-implementation.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
