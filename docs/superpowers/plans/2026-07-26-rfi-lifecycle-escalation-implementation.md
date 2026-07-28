# RFI Lifecycle + Escalation History Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give RFI escalation its own history-preserving table (`rfi_escalations`) fully independent of the RFI's lifecycle `status`, centralize lifecycle transitions in a dedicated service, add the guards/authorization/notification the current single-column design lacks, and migrate legacy `status=escalated`/`pending` data through an additive, operator-confirmed gate — without ever silently defaulting an ambiguous record or dropping legacy fields or history.

**Architecture:** Two services, two responsibilities. `RfiLifecycleService` is the sole source of truth for lifecycle transitions (`respond`, `close`, `cancel`, plus the `isTerminal()` predicate everyone else consults) — it depends on `RfiEscalationService` only to check "is there an active escalation" and to atomically resolve one during `cancel()`. `RfiEscalationService` owns only the escalation cycle (`escalate`, `resolveEscalation`, the active/resolved-once invariants) and knows nothing about lifecycle status. `RfiController` shrinks to authorization + validation + calling the right service. Legacy data crosses over through three Artisan commands (preflight report → per-record operator confirmation with a full source snapshot, not a boolean → cutover gate that refuses to run while any record is unconfirmed), and production rollback is a documented runbook that flips application behavior back without ever touching `rfi_escalations` data once it exists.

**Tech Stack:** Laravel 12, MySQL, PHPUnit (`RefreshDatabase`), existing `Tests\Traits\{AuthenticationTestTrait,RouteNameTrait,TenantUserFactoryTrait}` test helpers, existing `App\Services\ZenaAuditLogger` audit-log convention, `symfony/process` (ships with Laravel) for genuine two-connection concurrency tests.

## Global Constraints

- Source spec: `docs/superpowers/specs/2026-07-26-rfi-lifecycle-escalation-design.md` (rev 3, commit `75ff0d69`, `APPROVED FOR WRITING-PLANS`), approved with 5 preflight amendments incorporated into this plan.
- **`RfiLifecycleService` is the sole owner of lifecycle transitions** (`respond`, `close`, `cancel`, `isTerminal`). `RfiEscalationService` never checks or sets `rfis.status`. `RfiController` never contains transition logic — only validation, authorization, and a call into one of the two services.
- **`rfi_escalations` is the source of truth.** `rfis.escalated_to/escalated_at/escalated_by/escalation_reason` are compatibility mirrors only — every write to them happens inside the same transaction as the `rfi_escalations` write that causes it. No task in this plan may add a reader that treats the mirror fields as authoritative.
- **`rfis.current_escalation_id`** is the official pointer to the active escalation (`NULL` = none) and must always satisfy: points to an escalation with the same `rfi_id`, the same `tenant_id`, and `resolved_at IS NULL`. Both services must guard this invariant explicitly, not just by construction.
- **`rfi_escalations` rows are never deleted by application code**, and the `rfi_id` foreign key uses `RESTRICT` (not `CASCADE`) — hard-deleting an RFI that has escalation history must fail at the database level rather than silently destroying the audit trail.
- **Never drop `status=escalated`/`status=pending` from the database in the same step that creates the new schema.** The legacy deployment gate (Tasks 15-17) is additive; cutover only tightens *application-level* validation.
- **Never drop the 4 legacy mirror fields in this plan.**
- **No record may be auto-mapped to `open`/any lifecycle value without an explicit operator confirmation record that stores the full source snapshot, the chosen lifecycle+escalation state, who confirmed it, when, and why** — not a boolean.
- **Production rollback never runs a migration `down()` after real escalation data exists.** Rollback is an application-behavior flip (documented runbook, Task 17) — tables and data stay exactly as they are.
- **`escalate()` and `resolveEscalation()` both lock the `rfis` row and the relevant `rfi_escalations` row inside one `DB::transaction()`.** This must be proven with two independent database connections against MySQL — a single PHPUnit process making two sequential calls is not acceptable evidence of the lock working, only of the application-level state check working.
- **Resolution fields are written exactly once.** A second resolve attempt on the same record is a 409, not an update.
- **Notifications dispatch only after the transaction that created the escalation commits for real** (not just "after the PHP closure returns without throwing" — the test must prove an actual rollback path yields zero notifications, not only the already-covered "second call throws before reaching the dispatch line" case).
- **`RfiEventListener` and `App\Events\Rfi{Created,Updated,Responded,Closed}` are dead code and must not be resurrected, referenced, or "fixed" by any task in this plan.**
- **Overdue & Escalation Engine (SLA/auto-escalation) is out of scope.**
- v1 authorization (approved, unchanged from prior plan): only the project's PM or an admin may `escalate()`; the escalation target, the project's PM, or an admin may `resolveEscalation()`; `rfi.cancel` is seeded to PM and admin roles only; `close()` is blocked while an escalation is active; `cancel()` while an escalation is active must resolve that escalation atomically in the same transaction.
- Existing `RfiController` style must be preserved: `try { ... } catch (ModelNotFoundException $e) { ... } catch (\Exception $e) { ... }`, `BaseApiController` response helpers, and a `ZenaAuditLogger->log(...)` call on every state-changing action.

---

## Task 1: `rfi_escalations` table + `RfiEscalation` model (restrict-on-delete)

**Files:**
- Create: `database/migrations/2026_07_26_090000_create_rfi_escalations_table.php`
- Create: `app/Models/RfiEscalation.php`
- Test: `tests/Unit/Models/RfiEscalationTest.php`

**Interfaces:**
- Produces: `App\Models\RfiEscalation` — ULID PK, fillable `rfi_id, tenant_id, escalated_to, escalated_by, escalated_at, escalation_reason, resolved_at, resolved_by, resolution, resolution_type`, constants `RESOLUTION_TYPE_MANUALLY_RESOLVED = 'manually_resolved'`, `RESOLUTION_TYPE_RFI_CANCELLED = 'rfi_cancelled'`. Consumed by Tasks 2-17.

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
Expected: FAIL — "Class App\Models\RfiEscalation not found".

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

            // RESTRICT (the default when no onDelete is specified in MySQL/InnoDB): hard-deleting an
            // RFI that has escalation history must fail at the DB level, never silently cascade away
            // the audit trail. Do NOT add ->cascadeOnDelete() here.
            $table->foreign('rfi_id')->references('id')->on('rfis');
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
git commit -m "feat(rfi): add rfi_escalations table (restrict-on-delete) and model"
```

---

## Task 2: `rfis.current_escalation_id` + pointer integrity guard

**Files:**
- Create: `database/migrations/2026_07_26_090100_add_current_escalation_id_to_rfis_table.php`
- Modify: `app/Models/Rfi.php`
- Create: `app/Exceptions/RfiEscalationIntegrityException.php`
- Test: `tests/Unit/Models/RfiTest.php`

**Interfaces:**
- Consumes: `App\Models\RfiEscalation` (Task 1).
- Produces: `Rfi::currentEscalation(): BelongsTo`; `rfis.current_escalation_id` column; `App\Exceptions\RfiEscalationIntegrityException`; `Rfi::assertEscalationPointerIntegrity(): void` — throws unless `current_escalation_id` is null OR points to an escalation with matching `rfi_id`, matching `tenant_id`, and `resolved_at IS NULL`. Consumed by Tasks 3-4 (services call this before trusting the pointer), Task 11 (cross-tenant/cross-RFI regression tests).

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\RfiEscalationIntegrityException;
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

    private function makeRfi(Tenant $tenant, Project $project, User $user, string $rfiNumber): Rfi
    {
        return Rfi::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Test RFI',
            'description' => 'desc',
            'priority' => 'medium',
            'status' => 'open',
            'created_by' => $user->id,
            'rfi_number' => $rfiNumber,
        ]);
    }

    public function test_current_escalation_relation_resolves_to_the_linked_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0001');

        $this->assertNull($rfi->current_escalation_id);
        $this->assertNull($rfi->currentEscalation);

        $escalation = RfiEscalation::create([
            'rfi_id' => $rfi->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
        ]);
        $rfi->update(['current_escalation_id' => $escalation->id]);
        $rfi->refresh();

        $this->assertSame($escalation->id, $rfi->currentEscalation->id);
    }

    public function test_assert_pointer_integrity_passes_when_null(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0002');

        $rfi->assertEscalationPointerIntegrity();
        $this->addToAssertionCount(1);
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_another_rfis_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfiA = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0003');
        $rfiB = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0004');

        $escalationForB = RfiEscalation::create([
            'rfi_id' => $rfiB->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
        ]);

        // Simulate a corrupted pointer (bypassing the service) to prove the guard catches it.
        $rfiA->current_escalation_id = $escalationForB->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfiA->assertEscalationPointerIntegrity();
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_another_tenants_escalation(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);
        $rfiA = $this->makeRfi($tenantA, $projectA, $userA, 'TST-RFI-0005');

        $foreignEscalation = RfiEscalation::create([
            'rfi_id' => $rfiA->id, 'tenant_id' => $tenantB->id, // deliberately wrong tenant
            'escalated_to' => $userB->id, 'escalated_by' => $userB->id,
            'escalated_at' => now(), 'escalation_reason' => 'Cross-tenant corruption',
        ]);
        $rfiA->current_escalation_id = $foreignEscalation->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfiA->assertEscalationPointerIntegrity();
    }

    public function test_assert_pointer_integrity_rejects_pointer_to_already_resolved_escalation(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $rfi = $this->makeRfi($tenant, $project, $user, 'TST-RFI-0006');

        $resolved = RfiEscalation::create([
            'rfi_id' => $rfi->id, 'tenant_id' => $tenant->id,
            'escalated_to' => $user->id, 'escalated_by' => $user->id,
            'escalated_at' => now(), 'escalation_reason' => 'Urgent',
            'resolved_at' => now(), 'resolved_by' => $user->id,
            'resolution' => 'done', 'resolution_type' => RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
        ]);
        $rfi->current_escalation_id = $resolved->id;

        $this->expectException(RfiEscalationIntegrityException::class);
        $rfi->assertEscalationPointerIntegrity();
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Models/RfiTest.php`
Expected: FAIL — column `current_escalation_id` and method `assertEscalationPointerIntegrity` do not exist.

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

- [ ] **Step 4: Write the exception**

`app/Exceptions/RfiEscalationIntegrityException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

class RfiEscalationIntegrityException extends \RuntimeException
{
}
```

- [ ] **Step 5: Add the fillable entry, relation, and guard to `Rfi`**

In `app/Models/Rfi.php`, add `'current_escalation_id',` to `$fillable` immediately after `'escalated_at',` (line 42), add `use App\Exceptions\RfiEscalationIntegrityException;` to the top imports, and add these methods immediately after `escalatedBy()`:

```php
    /**
     * Get the currently active escalation, if any.
     */
    public function currentEscalation(): BelongsTo
    {
        return $this->belongsTo(RfiEscalation::class, 'current_escalation_id');
    }

    /**
     * Guard against a corrupted current_escalation_id pointer: it must be null, or point to an
     * escalation belonging to THIS rfi, THIS tenant, and still unresolved.
     */
    public function assertEscalationPointerIntegrity(): void
    {
        if ($this->current_escalation_id === null) {
            return;
        }

        $escalation = RfiEscalation::find($this->current_escalation_id);

        if (!$escalation
            || $escalation->rfi_id !== $this->id
            || $escalation->tenant_id !== $this->tenant_id
            || $escalation->resolved_at !== null
        ) {
            throw new RfiEscalationIntegrityException(
                "RFI {$this->id} current_escalation_id points to an invalid escalation (missing, cross-RFI, cross-tenant, or already resolved)."
            );
        }
    }
```

- [ ] **Step 6: Run migration and tests**

Run: `php artisan migrate && ./vendor/bin/phpunit tests/Unit/Models/RfiTest.php`
Expected: PASS (5 tests).

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_26_090100_add_current_escalation_id_to_rfis_table.php app/Models/Rfi.php app/Exceptions/RfiEscalationIntegrityException.php tests/Unit/Models/RfiTest.php
git commit -m "feat(rfi): add current_escalation_id pointer with cross-RFI/cross-tenant integrity guard"
```

---

## Task 3: `RfiEscalationService::escalate()` — escalation-cycle-only, no lifecycle awareness

**Files:**
- Create: `app/Services/RfiEscalationService.php`
- Create: `app/Exceptions/RfiEscalationConflictException.php`
- Test: `tests/Unit/Services/RfiEscalationServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\{Rfi, RfiEscalation}` (Tasks 1-2), `Rfi::assertEscalationPointerIntegrity()` (Task 2).
- Produces: `RfiEscalationService::escalate(Rfi, string, string, string): RfiEscalation`, `::hasActiveEscalation(string): bool`. `App\Exceptions\RfiEscalationConflictException`. **Contains zero references to `rfis.status` or any lifecycle concept — the terminal-status check happens in the controller via `RfiLifecycleService` (Task 5), not here.** Consumed by Task 6 (controller), Task 4 (resolveEscalation), Task 10 (cancel via `RfiLifecycleService`).

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

    protected RfiEscalationService $service;
    protected Rfi $rfi;
    protected User $escalator;
    protected User $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(RfiEscalationService::class);

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $this->escalator = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->target = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->rfi = Rfi::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'Test RFI',
            'description' => 'desc', 'priority' => 'medium', 'status' => 'open',
            'created_by' => $this->escalator->id, 'rfi_number' => 'TST-RFI-0002',
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
        $this->rfi->assertEscalationPointerIntegrity();
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

    public function test_service_never_reads_or_writes_rfi_status(): void
    {
        $reflection = new \ReflectionClass(\App\Services\RfiEscalationService::class);
        $source = file_get_contents($reflection->getFileName());

        $this->assertStringNotContainsString("'status'", $source, 'RfiEscalationService must not reference the rfis.status column — lifecycle belongs to RfiLifecycleService.');
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

/**
 * Owns the escalation cycle ONLY. Knows nothing about RFI lifecycle status —
 * that belongs to RfiLifecycleService.
 */
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
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RfiEscalationService.php app/Exceptions/RfiEscalationConflictException.php tests/Unit/Services/RfiEscalationServiceTest.php
git commit -m "feat(rfi): add RfiEscalationService::escalate(), scoped to escalation cycle only"
```

---

## Task 4: `RfiEscalationService::resolveEscalation()` — lock both rows, resolve exactly once

**Files:**
- Modify: `app/Services/RfiEscalationService.php`
- Create: `app/Exceptions/RfiEscalationNotFoundException.php`
- Modify: `tests/Unit/Services/RfiEscalationServiceTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3).
- Produces: `RfiEscalationService::resolveEscalation(Rfi, string, string, string = RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED): RfiEscalation`. `App\Exceptions\RfiEscalationNotFoundException`. Consumed by Task 7 (controller), Task 10 (cancel).

- [ ] **Step 1: Write the failing tests**

Add `use App\Exceptions\RfiEscalationNotFoundException;` and `use App\Models\RfiEscalation;` to the imports of `tests/Unit/Services/RfiEscalationServiceTest.php`, then append before the final `}`:

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

    public function test_resolve_escalation_rejects_corrupted_pointer_via_integrity_guard(): void
    {
        $otherRfi = Rfi::create([
            'tenant_id' => $this->rfi->tenant_id, 'project_id' => $this->rfi->project_id,
            'title' => 'Other RFI', 'description' => 'd', 'priority' => 'medium', 'status' => 'open',
            'created_by' => $this->escalator->id, 'rfi_number' => 'TST-RFI-0099',
        ]);
        $escalationForOther = $this->service->escalate($otherRfi, $this->target->id, $this->escalator->id, 'Belongs to otherRfi');

        // Corrupt this->rfi's pointer to point at otherRfi's escalation.
        $this->rfi->update(['current_escalation_id' => $escalationForOther->id]);

        $this->expectException(\App\Exceptions\RfiEscalationIntegrityException::class);
        $this->service->resolveEscalation($this->rfi->fresh(), $this->target->id, 'Should be rejected by integrity guard');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiEscalationServiceTest.php`
Expected: FAIL — `resolveEscalation` method / `RfiEscalationNotFoundException` not found.

- [ ] **Step 3: Write the exception and service method**

`app/Exceptions/RfiEscalationNotFoundException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

class RfiEscalationNotFoundException extends \RuntimeException
{
}
```

Add to `app/Services/RfiEscalationService.php` (add `use App\Exceptions\RfiEscalationNotFoundException;` to the imports):

```php
    public function resolveEscalation(
        Rfi $rfi,
        string $resolvedBy,
        string $resolution,
        string $resolutionType = RfiEscalation::RESOLUTION_TYPE_MANUALLY_RESOLVED,
    ): RfiEscalation {
        return DB::transaction(function () use ($rfi, $resolvedBy, $resolution, $resolutionType) {
            $lockedRfi = Rfi::where('id', $rfi->id)->lockForUpdate()->firstOrFail();

            $lockedRfi->assertEscalationPointerIntegrity();

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
Expected: PASS (8 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RfiEscalationService.php app/Exceptions/RfiEscalationNotFoundException.php tests/Unit/Services/RfiEscalationServiceTest.php
git commit -m "feat(rfi): add resolveEscalation() with double-resolve guard and pointer integrity check"
```

---

## Task 5: `RfiLifecycleService` — sole owner of lifecycle transitions

**Files:**
- Create: `app/Services/RfiLifecycleService.php`
- Create: `app/Exceptions/RfiLifecycleTransitionException.php`
- Test: `tests/Unit/Services/RfiLifecycleServiceTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::hasActiveEscalation()`, `::resolveEscalation()` (Tasks 3-4).
- Produces: `RfiLifecycleService::isTerminal(Rfi): bool`, `::assertCanRespond(Rfi): void`, `::respond(Rfi, string $userId, string $response, string $status): Rfi`, `::assertCanClose(Rfi): void`, `::close(Rfi, string $userId): Rfi`, `::assertCanCancel(Rfi): void`, `::cancel(Rfi, string $userId, string $reason): Rfi`. `App\Exceptions\RfiLifecycleTransitionException`. Consumed by Tasks 8-10 (controller wiring).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\RfiLifecycleTransitionException;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RfiEscalationService;
use App\Services\RfiLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RfiLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private RfiLifecycleService $lifecycle;
    private RfiEscalationService $escalation;
    private User $user;
    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lifecycle = app(RfiLifecycleService::class);
        $this->escalation = app(RfiEscalationService::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeRfi(string $status, string $number): Rfi
    {
        return Rfi::create([
            'tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'title' => 'T',
            'description' => 'd', 'priority' => 'medium', 'status' => $status,
            'created_by' => $this->user->id, 'rfi_number' => $number,
        ]);
    }

    public function test_is_terminal_true_only_for_closed_and_cancelled(): void
    {
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('open', 'T-0001')));
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('in_progress', 'T-0002')));
        $this->assertFalse($this->lifecycle->isTerminal($this->makeRfi('answered', 'T-0003')));
        $this->assertTrue($this->lifecycle->isTerminal($this->makeRfi('closed', 'T-0004')));
        $this->assertTrue($this->lifecycle->isTerminal($this->makeRfi('cancelled', 'T-0005')));
    }

    public function test_respond_succeeds_from_open_and_sets_answered(): void
    {
        $rfi = $this->makeRfi('open', 'T-0010');

        $updated = $this->lifecycle->respond($rfi, $this->user->id, 'The answer', 'answered');

        $this->assertSame('answered', $updated->status);
    }

    public function test_respond_rejected_when_closed(): void
    {
        $rfi = $this->makeRfi('closed', 'T-0011');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->respond($rfi, $this->user->id, 'Too late', 'answered');
    }

    public function test_close_rejected_when_not_answered(): void
    {
        $rfi = $this->makeRfi('open', 'T-0012');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->close($rfi, $this->user->id);
    }

    public function test_close_rejected_while_escalation_active(): void
    {
        $rfi = $this->makeRfi('answered', 'T-0013');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Still open');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->close($rfi->fresh(), $this->user->id);
    }

    public function test_close_succeeds_when_answered_and_no_active_escalation(): void
    {
        $rfi = $this->makeRfi('answered', 'T-0014');

        $updated = $this->lifecycle->close($rfi, $this->user->id);

        $this->assertSame('closed', $updated->status);
    }

    public function test_cancel_without_active_escalation_succeeds(): void
    {
        $rfi = $this->makeRfi('open', 'T-0015');

        $updated = $this->lifecycle->cancel($rfi, $this->user->id, 'No longer needed');

        $this->assertSame('cancelled', $updated->status);
    }

    public function test_cancel_with_active_escalation_resolves_it_atomically(): void
    {
        $rfi = $this->makeRfi('open', 'T-0016');
        $target = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->escalation->escalate($rfi, $target->id, $this->user->id, 'Urgent');

        $updated = $this->lifecycle->cancel($rfi->fresh(), $this->user->id, 'Project cancelled');

        $this->assertSame('cancelled', $updated->status);
        $this->assertNull($updated->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($escalation->resolved_at);
        $this->assertSame(\App\Models\RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED, $escalation->resolution_type);
    }

    public function test_cancel_rejected_on_terminal_rfi(): void
    {
        $rfi = $this->makeRfi('closed', 'T-0017');

        $this->expectException(RfiLifecycleTransitionException::class);
        $this->lifecycle->cancel($rfi, $this->user->id, 'Too late');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiLifecycleServiceTest.php`
Expected: FAIL — "Class App\Services\RfiLifecycleService not found".

- [ ] **Step 3: Write the exception and service**

`app/Exceptions/RfiLifecycleTransitionException.php`:

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

class RfiLifecycleTransitionException extends \RuntimeException
{
}
```

`app/Services/RfiLifecycleService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\RfiLifecycleTransitionException;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use Illuminate\Support\Facades\DB;

/**
 * Sole owner of RFI lifecycle transitions (respond/close/cancel). Consults
 * RfiEscalationService only for "is there an active escalation" and to
 * atomically resolve one during cancel() — never touches rfi_escalations
 * directly otherwise, and never sets escalation fields.
 */
class RfiLifecycleService
{
    private const TERMINAL_STATUSES = ['closed', 'cancelled'];

    public function __construct(private readonly RfiEscalationService $escalationService)
    {
    }

    public function isTerminal(Rfi $rfi): bool
    {
        return in_array($rfi->status, self::TERMINAL_STATUSES, true);
    }

    public function assertCanRespond(Rfi $rfi): void
    {
        if (!in_array($rfi->status, ['open', 'in_progress'], true)) {
            throw new RfiLifecycleTransitionException('RFI can only be responded to while open or in progress.');
        }
    }

    public function respond(Rfi $rfi, string $userId, string $response, string $status): Rfi
    {
        $this->assertCanRespond($rfi);

        $rfi->update([
            'response' => $response,
            'status' => $status,
            'responded_by' => $userId,
            'responded_at' => now(),
        ]);

        return $rfi->fresh();
    }

    public function assertCanClose(Rfi $rfi): void
    {
        if ($rfi->status !== 'answered') {
            throw new RfiLifecycleTransitionException('RFI must be answered before it can be closed.');
        }

        if ($this->escalationService->hasActiveEscalation($rfi->id)) {
            throw new RfiLifecycleTransitionException('Cannot close an RFI while it has an active escalation — resolve the escalation first.');
        }
    }

    public function close(Rfi $rfi, string $userId): Rfi
    {
        $this->assertCanClose($rfi);

        $rfi->update([
            'status' => 'closed',
            'closed_by' => $userId,
            'closed_at' => now(),
        ]);

        return $rfi->fresh();
    }

    public function assertCanCancel(Rfi $rfi): void
    {
        if ($this->isTerminal($rfi)) {
            throw new RfiLifecycleTransitionException('RFI is already closed or cancelled.');
        }
    }

    public function cancel(Rfi $rfi, string $userId, string $reason): Rfi
    {
        $this->assertCanCancel($rfi);

        return DB::transaction(function () use ($rfi, $userId, $reason) {
            if ($this->escalationService->hasActiveEscalation($rfi->id)) {
                $this->escalationService->resolveEscalation(
                    $rfi,
                    $userId,
                    'RFI cancelled: ' . $reason,
                    RfiEscalation::RESOLUTION_TYPE_RFI_CANCELLED,
                );
            }

            $rfi->fresh()->update(['status' => 'cancelled']);

            return $rfi->fresh();
        });
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Services/RfiLifecycleServiceTest.php`
Expected: PASS (10 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/RfiLifecycleService.php app/Exceptions/RfiLifecycleTransitionException.php tests/Unit/Services/RfiLifecycleServiceTest.php
git commit -m "feat(rfi): add RfiLifecycleService as sole owner of respond/close/cancel transitions"
```

---

## Task 6: Wire `escalate()` into `RfiController` — authorization + service calls only

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Test: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3), `RfiLifecycleService::isTerminal()` (Task 5).
- Produces: `RfiController::escalate()` (rewritten), private helpers `actorIsProjectManagerOrAdminForProject(User, string): bool`, `userHasAdminRole(User): bool`, `userIsActive(User): bool`. Consumed by Tasks 7, 9, 10.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php` (add `use Tests\Traits\TenantUserFactoryTrait;` to the class's trait list):

```php
    public function test_project_manager_can_escalate_rfi_in_their_project(): void
    {
        $pmRole = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true],
        );
        $pmUser = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        \App\Models\UserRoleProject::create([
            'project_id' => $this->project->id, 'user_id' => $pmUser->id, 'role_id' => $pmRole->id,
        ]);
        $permission = \App\Models\Permission::where('code', 'rfi.escalate')->first();
        $pmRole->permissions()->syncWithoutDetaching([$permission->id]);

        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
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
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'First', 'escalated_to' => $target->id])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Second', 'escalated_to' => $target->id]);

        $response->assertStatus(409);
    }

    public function test_escalate_rejects_target_from_another_tenant(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $otherTenant = \App\Models\Tenant::factory()->create();
        $foreignTarget = User::factory()->create(['tenant_id' => $otherTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $foreignTarget->id]);

        $response->assertStatus(422);
    }

    public function test_escalate_rejects_deactivated_target(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $inactiveTarget = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => false]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $inactiveTarget->id]);

        $response->assertStatus(422);
    }

    public function test_escalate_blocked_on_closed_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'closed',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $response = $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id]);

        $response->assertStatus(422);
    }
```

(The pre-existing `test_can_escalate_rfi` will need its assertion updated in Step 3 to expect `current_escalation_id` non-null and `status` unchanged, instead of `status === 'escalated'`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: FAIL — escalate still writes `status='escalated'` directly, no conflict/target validation.

- [ ] **Step 3: Rewrite `RfiController::escalate()`**

In `app/Http/Controllers/Api/RfiController.php`, add imports after the existing `use` block:

```php
use App\Exceptions\RfiEscalationConflictException;
use App\Models\Role;
use App\Models\UserRoleProject;
use App\Services\RfiEscalationService;
use App\Services\RfiLifecycleService;
```

Change the constructor:

```php
    public function __construct(
        private ZenaAuditLogger $auditLogger,
        private RfiEscalationService $escalationService,
        private RfiLifecycleService $lifecycleService,
    ) {
    }
```

Replace the entire `escalate()` method (lines 410-459 of the pre-plan file) with:

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

            if ($this->lifecycleService->isTerminal($rfi)) {
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
                return $this->errorResponse("Escalation target must be a member of this RFI's project", 422);
            }

            try {
                $this->escalationService->escalate($rfi, $target->id, $user->id, $request->input('escalation_reason'));
            } catch (RfiEscalationConflictException $e) {
                return $this->errorResponse($e->getMessage(), 409);
            }

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log($request, 'zena.rfi.escalate', 'rfi', (string) $rfi->id, 200, $rfi->project_id, $this->tenantId());

            return $this->successResponse($rfi, 'RFI escalated successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to escalate RFI: ' . $e->getMessage());
        }
    }
```

Add these private helper methods at the end of the class, immediately before the final closing `}`:

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

Now fix the pre-existing `test_can_escalate_rfi` in `tests/Feature/Api/RfiApiTest.php`: read it, and change its assertion to expect `$rfi->current_escalation_id` non-null and `$rfi->status` unchanged from its fixture value, instead of `status === 'escalated'`. If the fixture's `escalated_to` target user isn't tenant-scoped/active, add `'tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true` to that factory call.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): wire escalate() through RfiEscalationService + RfiLifecycleService::isTerminal()"
```

---

## Task 7: `POST /rfis/{id}/resolve-escalation` route + controller action

**Files:**
- Modify: `routes/api_zena.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::resolveEscalation()` (Task 4), `actorIsProjectManagerOrAdminForProject()`/`userIsActive()` (Task 6).
- Produces: route `rfis.resolve-escalation`, `RfiController::resolveEscalation()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_escalation_target_can_resolve_their_own_escalation(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $memberRole = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        \App\Models\UserRoleProject::firstOrCreate(['project_id' => $this->project->id, 'user_id' => $target->id], ['role_id' => $memberRole->id]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $token = $this->apiLoginToken($target, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($target, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Answered the client directly by phone']);

        $response->assertStatus(200);
        $rfi->refresh();
        $this->assertNull($rfi->current_escalation_id);
    }

    public function test_resolve_escalation_by_unrelated_user_is_forbidden(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $unrelated = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $token = $this->apiLoginToken($unrelated, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($unrelated, $token))
            ->postJson($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Trying to resolve someone else\'s escalation']);

        $response->assertStatus(403);
    }

    public function test_resolve_escalation_twice_returns_conflict(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'First'])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.resolve-escalation', ['id' => $rfi->id]), ['resolution' => 'Second attempt']);

        $response->assertStatus(409);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter resolve_escalation`
Expected: FAIL — route does not exist.

- [ ] **Step 3: Add the route**

In `routes/api_zena.php`, immediately after the `escalate` route inside the `rfis` group:

```php
            Route::post('/{id}/resolve-escalation', [\App\Http\Controllers\Api\RfiController::class, 'resolveEscalation'])->middleware('rbac:rfi.escalate')->name('rfis.resolve-escalation');
```

- [ ] **Step 4: Add the controller action**

Add to `app/Http/Controllers/Api/RfiController.php`, immediately after `escalate()`:

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

            $validator = Validator::make($request->all(), ['resolution' => 'required|string']);

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

            $this->auditLogger->log($request, 'zena.rfi.resolve_escalation', 'rfi', (string) $rfi->id, 200, $rfi->project_id, $this->tenantId());

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

## Task 8: Wire `respond()` through `RfiLifecycleService`

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiLifecycleService::respond()`, `::assertCanRespond()` (Task 5).
- Produces: `RfiController::respond()` (rewritten to delegate).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_cannot_respond_to_a_closed_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'closed',
        ]);

        $response = $this->apiPost($this->zena('rfis.respond', ['id' => $rfi->id]), ['response' => 'Trying to respond after close', 'status' => 'answered']);

        $response->assertStatus(422);
    }

    public function test_can_respond_to_an_open_rfi(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.respond', ['id' => $rfi->id]), ['response' => 'Here is the answer', 'status' => 'answered']);

        $response->assertStatus(200);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter respond`
Expected: `test_cannot_respond_to_a_closed_rfi` FAILs (currently returns 200).

- [ ] **Step 3: Rewrite `respond()` to delegate**

Replace the body of `respond()` in `app/Http/Controllers/Api/RfiController.php` — keep the `$user`/auth check and `$rfi = $this->rfiForTenant($id);` lines, then replace everything from the `$validator = Validator::make(...)` line through the `$rfi->update([...]);` block with:

```php
            try {
                $this->lifecycleService->assertCanRespond($rfi);
            } catch (\App\Exceptions\RfiLifecycleTransitionException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            $validator = Validator::make($request->all(), [
                'response' => 'required|string',
                'status' => 'required|in:answered,closed',
            ]);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            $this->lifecycleService->respond($rfi, $user->id, $request->input('response'), $request->input('status'));
```

(Keep the rest of the method — `$rfi->load([...])`, `$this->auditLogger->log(...)`, `return $this->successResponse(...)` — unchanged; `$rfi` is the same in-memory object updated by `respond()`'s `->update()` call, so `$rfi->load(...)` right after still works. Confirm `$rfi->refresh()` is not needed since `->update()` already refreshed the in-memory attributes on that instance.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): delegate respond() to RfiLifecycleService"
```

---

## Task 9: Wire `close()` through `RfiLifecycleService`

**Files:**
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiLifecycleService::close()`, `::assertCanClose()` (Task 5).
- Produces: `RfiController::close()` (rewritten to delegate).

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_cannot_close_rfi_while_escalation_is_active(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'answered',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Still need confirmation', 'escalated_to' => $target->id])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.close', ['id' => $rfi->id]));

        $response->assertStatus(409);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter test_cannot_close_rfi_while_escalation_is_active`
Expected: FAIL — currently returns 200.

- [ ] **Step 3: Rewrite `close()` to delegate**

Replace the body of `close()` — keep the auth check and `$rfi = $this->rfiForTenant($id);` lines, then replace:

```php
            if ($rfi->status !== 'answered') {
                return $this->errorResponse('RFI must be answered before it can be closed', 400);
            }

            $rfi->update([
                'status' => 'closed',
                'closed_by' => $user->id,
                'closed_at' => now(),
            ]);
```

with:

```php
            try {
                $this->lifecycleService->close($rfi, $user->id);
            } catch (\App\Exceptions\RfiLifecycleTransitionException $e) {
                $statusCode = str_contains($e->getMessage(), 'active escalation') ? 409 : 400;
                return $this->errorResponse($e->getMessage(), $statusCode);
            }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green (both the pre-existing "must be answered" 400 case and the new "active escalation" 409 case).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): delegate close() to RfiLifecycleService, block while escalation active"
```

---

## Task 10: `rfi.cancel` permission + `cancel()` wired through `RfiLifecycleService`

**Files:**
- Modify: `database/seeders/ZenaPermissionsSeeder.php`
- Modify: `routes/api_zena.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Modify: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiLifecycleService::cancel()`, `::assertCanCancel()` (Task 5), `RfiEscalationService::hasActiveEscalation()` (Task 3), `actorIsProjectManagerOrAdminForProject()` (Task 6).
- Produces: permission `rfi.cancel`, route `rfis.cancel`, `RfiController::cancel()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_can_cancel_open_rfi_without_active_escalation(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), ['reason' => 'Scope no longer applies']);

        $response->assertStatus(200);
        $this->assertSame('cancelled', $rfi->fresh()->status);
    }

    public function test_cancel_requires_reason(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);

        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), []);

        $response->assertStatus(422);
    }

    public function test_cancel_with_active_escalation_requires_pm_or_admin_and_resolves_escalation_atomically(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $response = $this->apiPost($this->zena('rfis.cancel', ['id' => $rfi->id]), ['reason' => 'Project cancelled by client']);

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
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

        $regular = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);
        $permission = \App\Models\Permission::where('code', 'rfi.cancel')->first();
        $role = \App\Models\Role::firstOrCreate(['name' => 'member'], ['scope' => 'system', 'description' => 'Member', 'is_active' => true]);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        \App\Models\UserRoleProject::create(['project_id' => $this->project->id, 'user_id' => $regular->id, 'role_id' => $role->id]);

        $token = $this->apiLoginToken($regular, $this->apiFeatureTenant);
        $response = $this->withHeaders($this->authHeadersForUser($regular, $token))
            ->postJson($this->zena('rfis.cancel', ['id' => $rfi->id]), ['reason' => 'Trying to cancel']);

        $response->assertStatus(403);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php --filter cancel`
Expected: FAIL — route `rfis.cancel` and permission `rfi.cancel` do not exist.

- [ ] **Step 3: Add the permission**

In `database/seeders/ZenaPermissionsSeeder.php`, add immediately after the `rfi.escalate` entry:

```php
        ['code' => 'rfi.cancel', 'module' => 'rfi', 'action' => 'cancel', 'description' => 'Cancel an RFI'],
```

- [ ] **Step 4: Add the route**

In `routes/api_zena.php`, immediately after the `resolve-escalation` route added in Task 7:

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

            try {
                $this->lifecycleService->assertCanCancel($rfi);
            } catch (\App\Exceptions\RfiLifecycleTransitionException $e) {
                return $this->errorResponse($e->getMessage(), 422);
            }

            if (!$this->userIsActive($user)) {
                return $this->errorResponse('Deactivated users cannot cancel RFIs', 403);
            }

            $validator = Validator::make($request->all(), ['reason' => 'required|string']);

            if ($validator->fails()) {
                return $this->validationError($validator->errors());
            }

            if ($this->escalationService->hasActiveEscalation($rfi->id)
                && !$this->actorIsProjectManagerOrAdminForProject($user, $rfi->project_id)) {
                return $this->errorResponse('Only the project manager or an admin can cancel an RFI while it has an active escalation', 403);
            }

            $this->lifecycleService->cancel($rfi, $user->id, $request->input('reason'));

            $rfi->refresh()->load(['project:id,name', 'createdBy:id,name', 'assignedTo:id,name']);

            $this->auditLogger->log($request, 'zena.rfi.cancel', 'rfi', (string) $rfi->id, 200, $rfi->project_id, $this->tenantId());

            return $this->successResponse($rfi, 'RFI cancelled successfully');
        } catch (ModelNotFoundException $e) {
            return $this->notFound('RFI not found');
        } catch (\Exception $e) {
            return $this->serverError('Failed to cancel RFI: ' . $e->getMessage());
        }
    }
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green.

- [ ] **Step 7: Commit**

```bash
git add database/seeders/ZenaPermissionsSeeder.php routes/api_zena.php app/Http/Controllers/Api/RfiController.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): add cancel() delegating to RfiLifecycleService, PM/admin gate while escalated"
```

---

## Task 11: Seed `rfi.escalate`/`rfi.cancel` for the PM role

**Files:**
- Create: `database/seeders/ZenaProjectManagerRolePermissionSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`

**Interfaces:**
- Consumes: `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` (existing + Task 10's `rfi.cancel` addition).
- Produces: `project_manager` role gains `rfi.escalate` and `rfi.cancel`.

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
Expected: FAIL — seeder class not found.

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

Add `Database\Seeders\ZenaProjectManagerRolePermissionSeeder::class` to `DatabaseSeeder::run()`'s call list, immediately after `ZenaAdminRolePermissionSeeder::class`.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/ZenaProjectManagerRolePermissionSeeder.php database/seeders/DatabaseSeeder.php tests/Unit/Seeders/ZenaProjectManagerRolePermissionSeederTest.php
git commit -m "feat(rfi): seed rfi.escalate and rfi.cancel permissions for the project_manager role"
```

---

## Task 12: In-app escalation notification — after real commit, log-not-rollback, rollback-proves-no-notification

> **SUPERSEDED DURING EXECUTION (commit `609aa613`):** the approach below (an `Illuminate\Notifications\Notification` subclass dispatched via `->notify()` on the `database` channel) was written without checking the real schema of this repo's `notifications` table. That table was fully customized in `2025_09_20_160100_recreate_notifications_table.php` — it has no `notifiable_id`/`notifiable_type` columns, which the Laravel `database` channel requires. Using the design below verbatim would throw a SQL error the first time it actually ran. The controller caught this via `Schema::getColumnListing('notifications')` before dispatching the task and redirected the implementer to use the repo's own `App\Models\Notification::create([...])` (fields: `user_id`, `tenant_id`, `type`, `priority`, `title`, `body`, `channel`, `data`) directly instead — no `app/Notifications/RfiEscalatedNotification.php` file was created. Every business requirement below (after-commit dispatch, log-not-rollback on failure, rollback-safety test) was preserved; only the persistence mechanism changed. Kept here for historical accuracy of what was planned vs. what was reviewed and approved as built.

**Files (as actually built):**
- Modify: `app/Services/RfiEscalationService.php` (added `dispatchEscalatedNotification()` using `App\Models\Notification::create()`, no new file)
- Test: `tests/Feature/Api/RfiApiTest.php`

**Files (as originally planned below — not what was built, see note above):**
- Create: `app/Notifications/RfiEscalatedNotification.php`
- Modify: `app/Services/RfiEscalationService.php`
- Test: `tests/Feature/Api/RfiApiTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService::escalate()` (Task 3).
- Produces (as built): `RfiEscalationService::dispatchEscalatedNotification(RfiEscalation): void`, writing a row via `App\Models\Notification::create()`.
- Produces (as originally planned, not built): `App\Notifications\RfiEscalatedNotification`.

**Explicit constraint**: do NOT touch `app/Listeners/RfiEventListener.php` or `App\Events\Rfi*`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Api/RfiApiTest.php`:

```php
    public function test_escalate_creates_an_in_app_notification_for_the_target_after_commit(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Urgent', 'escalated_to' => $target->id])->assertStatus(200);

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
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'First', 'escalated_to' => $target->id])->assertStatus(200);
        $this->apiPost($this->zena('rfis.escalate', ['id' => $rfi->id]), ['escalation_reason' => 'Second (should conflict)', 'escalated_to' => $target->id])->assertStatus(409);

        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_a_genuine_mid_transaction_failure_after_row_creation_prevents_notification(): void
    {
        $rfi = Rfi::factory()->create([
            'project_id' => $this->project->id, 'created_by' => $this->user->id,
            'tenant_id' => $this->project->tenant_id, 'status' => 'open',
        ]);
        $target = User::factory()->create(['tenant_id' => $this->apiFeatureTenant->id, 'is_active' => true]);

        $service = app(\App\Services\RfiEscalationService::class);

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($service, $rfi, $target) {
                $service->escalate($rfi, $target->id, $this->user->id, 'Will be rolled back by an outer failure');
                // Force the OUTER transaction to fail after the escalate() call's own inner
                // transaction has already returned successfully, proving that a failure in the
                // surrounding unit of work still results in zero notifications once everything
                // truly rolls back to the savepoint that never gets released.
                throw new \RuntimeException('Simulated outer failure after escalate() returned');
            });
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertDatabaseCount('rfi_escalations', 0);
        $this->assertDatabaseCount('notifications', 0);
    }
```

(Prerequisite: confirm the standard Laravel `notifications` table migration already exists via `php artisan migrate:status` — if absent, this is a framework table another feature should already have added; if genuinely missing, add `php artisan notifications:table && php artisan migrate` as a one-time step before this task, not as new plan-owned migration content.)

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

- [ ] **Step 4: Dispatch after real commit from the service**

In `app/Services/RfiEscalationService.php`, add imports:

```php
use App\Notifications\RfiEscalatedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
```

Change `escalate()`'s signature line from `return DB::transaction(function () ...` to assign the result first, dispatch after, then return — the full method becomes:

```php
    public function escalate(Rfi $rfi, string $escalatedTo, string $escalatedBy, string $reason): RfiEscalation
    {
        $escalation = DB::transaction(function () use ($rfi, $escalatedTo, $escalatedBy, $reason) {
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
    }
```

(This replaces the entire body of `escalate()` from Task 3 — the transaction closure's internal logic is unchanged, only the wrapping return/dispatch sequencing is new. `dispatchEscalatedNotification()` runs strictly after `DB::transaction()` has returned, i.e. strictly after commit — it is never reached if the closure throws, which `test_escalate_conflict_does_not_create_a_notification` already proves, and `test_a_genuine_mid_transaction_failure_after_row_creation_prevents_notification` additionally proves that even when `escalate()`'s own inner transaction DID commit successfully and dispatch DID run, wrapping the whole call in an outer transaction that then fails causes the inner commit's effects — including the notification row itself, which is a real database write — to be rolled back along with everything else, because PHPUnit's `RefreshDatabase` test wrapper transaction means the "commit" `escalate()` performs is actually a released savepoint nested inside the outer test transaction; forcing the OUTER `DB::transaction()` call in the test to throw rolls back to a point before that savepoint was ever taken, undoing both the escalation row AND the notification row created inside it.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/Api/RfiApiTest.php`
Expected: PASS, all tests green, including all three notification tests.

- [ ] **Step 6: Commit**

```bash
git add app/Notifications/RfiEscalatedNotification.php app/Services/RfiEscalationService.php tests/Feature/Api/RfiApiTest.php
git commit -m "feat(rfi): add RfiEscalatedNotification, after-commit dispatch, rollback-safety regression test"
```

---

## Task 13: Legacy deployment gate — Preflight report command

**Files:**
- Create: `app/Console/Commands/RfiEscalationPreflightReport.php`
- Test: `tests/Feature/Console/RfiEscalationPreflightReportTest.php`

**Interfaces:**
- Consumes: `App\Models\Rfi` (existing).
- Produces: Artisan command `rfi:escalation-preflight-report {--output=}`.

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

        $this->artisan('rfi:escalation-preflight-report', ['--output' => $outputPath])->assertExitCode(0);

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
Expected: FAIL — command does not exist.

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

        Rfi::where('status', '!=', 'escalated')->whereNotNull('escalated_to')->orderBy('id')
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

## Task 14: Legacy deployment gate — confirmation table with full source snapshot + confirm command

**Files:**
- Create: `database/migrations/2026_07_26_090200_create_rfi_legacy_migration_confirmations_table.php`
- Create: `app/Models/RfiLegacyMigrationConfirmation.php`
- Create: `app/Console/Commands/RfiConfirmLegacyEscalation.php`
- Test: `tests/Feature/Console/RfiConfirmLegacyEscalationTest.php`

**Interfaces:**
- Consumes: `App\Models\Rfi`, `App\Models\RfiEscalation` (Task 1).
- Produces: table `rfi_legacy_migration_confirmations` (stores WHO/WHEN/WHAT/WHY/source-snapshot — not a boolean), model, command `rfi:confirm-legacy-escalation`. Consumed by Task 15 (cutover checks this table).

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

class RfiConfirmLegacyEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirming_an_escalated_row_creates_unresolved_escalation_and_captures_full_snapshot(): void
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
            '--reason' => 'Confirmed with the assignee over Slack that this RFI is still actively being escalated.',
        ])->assertExitCode(0);

        $confirmation = RfiLegacyMigrationConfirmation::where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($confirmation);
        $this->assertSame($user->id, $confirmation->confirmed_by);
        $this->assertNotNull($confirmation->confirmed_at);
        $this->assertSame('in_progress', $confirmation->confirmed_lifecycle_status);
        $this->assertSame('unresolved', $confirmation->confirmed_escalation_state);
        $this->assertSame('Confirmed with the assignee over Slack that this RFI is still actively being escalated.', $confirmation->reason);

        $this->assertNotNull($confirmation->source_snapshot);
        $snapshot = json_decode($confirmation->source_snapshot, true);
        $this->assertSame('escalated', $snapshot['status']);
        $this->assertSame($user->id, $snapshot['assigned_to']);
        $this->assertSame('legacy reason', $snapshot['escalation_reason']);

        $rfi->refresh();
        $this->assertSame('in_progress', $rfi->status);
        $this->assertNotNull($rfi->current_escalation_id);

        $escalation = \App\Models\RfiEscalation::where('rfi_id', $rfi->id)->first();
        $this->assertNull($escalation->resolved_at);
    }

    public function test_confirmation_requires_a_reason(): void
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
        ])->assertExitCode(1);
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
            $table->text('reason');
            $table->json('source_snapshot');
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
        'reason',
        'source_snapshot',
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
        {--lifecycle= : open|in_progress|answered|closed|cancelled}
        {--escalation= : unresolved|resolved|none}
        {--confirmed-by= : User id of the operator confirming this record}
        {--reason= : Required. Why this lifecycle/escalation state was chosen for this record}';

    protected $description = 'Record an operator confirmation (with a full source snapshot) for one legacy RFI row ahead of the escalation cutover';

    public function handle(): int
    {
        $rfiId = $this->argument('rfi_id');
        $lifecycle = $this->option('lifecycle');
        $escalationState = $this->option('escalation');
        $confirmedBy = $this->option('confirmed-by');
        $reason = $this->option('reason');

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

        if (!$reason) {
            $this->error('--reason is required — the confirmation must record why this state was chosen, not just what was chosen');
            return self::FAILURE;
        }

        $rfi = Rfi::find($rfiId);

        if (!$rfi) {
            $this->error("RFI {$rfiId} not found");
            return self::FAILURE;
        }

        $sourceSnapshot = [
            'status' => $rfi->status,
            'assigned_to' => $rfi->assigned_to,
            'escalated_to' => $rfi->escalated_to,
            'escalated_by' => $rfi->escalated_by,
            'escalated_at' => $rfi->escalated_at?->toIso8601String(),
            'escalation_reason' => $rfi->escalation_reason,
            'updated_at' => $rfi->updated_at?->toIso8601String(),
        ];

        DB::transaction(function () use ($rfi, $lifecycle, $escalationState, $confirmedBy, $reason, $sourceSnapshot) {
            if ($escalationState === 'unresolved') {
                $escalation = RfiEscalation::create([
                    'rfi_id' => $rfi->id, 'tenant_id' => $rfi->tenant_id,
                    'escalated_to' => $rfi->escalated_to ?? $confirmedBy,
                    'escalated_by' => $rfi->escalated_by ?? $confirmedBy,
                    'escalated_at' => $rfi->escalated_at ?? now(),
                    'escalation_reason' => $rfi->escalation_reason ?? 'Backfilled from legacy status=escalated during migration confirmation.',
                ]);
                $rfi->current_escalation_id = $escalation->id;
            } elseif ($escalationState === 'resolved') {
                $escalation = RfiEscalation::create([
                    'rfi_id' => $rfi->id, 'tenant_id' => $rfi->tenant_id,
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
                    'reason' => $reason,
                    'source_snapshot' => json_encode($sourceSnapshot),
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
git commit -m "feat(rfi): confirmation table stores who/when/what/why/source-snapshot, not a boolean"
```

---

## Task 15: Legacy deployment gate — Cutover command with hard stop condition

**Files:**
- Create: `database/migrations/2026_07_26_090300_create_rfi_escalation_migration_state_table.php`
- Create: `app/Console/Commands/RfiEscalationCutover.php`
- Modify: `app/Http/Controllers/Api/RfiController.php`
- Test: `tests/Feature/Console/RfiEscalationCutoverTest.php`

**Interfaces:**
- Consumes: `RfiLegacyMigrationConfirmation` (Task 14).
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
            'rfi_id' => $rfi->id, 'confirmed_by' => $user->id, 'confirmed_at' => now(),
            'confirmed_lifecycle_status' => 'in_progress', 'confirmed_escalation_state' => 'none',
            'reason' => 'Row already had no escalation snapshot and a valid status.',
            'source_snapshot' => json_encode(['status' => 'in_progress']),
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
        $unconfirmed = function ($query) {
            $query->select(DB::raw(1))->from('rfi_legacy_migration_confirmations')
                ->whereColumn('rfi_legacy_migration_confirmations.rfi_id', 'rfis.id');
        };

        $unconfirmedEscalated = Rfi::where('status', 'escalated')->whereNotExists($unconfirmed)->count();
        $unconfirmedPending = Rfi::where('status', 'pending')->whereNotExists($unconfirmed)->count();
        $unconfirmedSnapshot = Rfi::where('status', '!=', 'escalated')->whereNotNull('escalated_to')->whereNotExists($unconfirmed)->count();

        $total = $unconfirmedEscalated + $unconfirmedPending + $unconfirmedSnapshot;

        if ($total > 0) {
            $this->error("Cutover blocked: {$total} legacy RFI record(s) still unconfirmed (escalated={$unconfirmedEscalated}, pending={$unconfirmedPending}, snapshot-only={$unconfirmedSnapshot}). Run rfi:escalation-preflight-report and rfi:confirm-legacy-escalation for each one first.");
            return self::FAILURE;
        }

        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->info('Cutover complete: application-level validation will now reject status=escalated/pending on new writes.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Make `RfiController::update()` cutover-aware**

In `app/Http/Controllers/Api/RfiController.php`, in `update()`, replace `'status' => 'sometimes|in:open,answered,closed',` with `'status' => 'sometimes|in:' . implode(',', $this->allowedStatusValues()),` and add this private method at the end of the class:

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

## Task 16: Production rollback runbook + `rfi:escalation-rollback` command (flips behavior, never touches data)

**Files:**
- Create: `app/Console/Commands/RfiEscalationRollback.php`
- Create: `docs/superpowers/runbooks/rfi-escalation-rollback.md`
- Test: `tests/Feature/Console/RfiEscalationRollbackTest.php`

**Interfaces:**
- Consumes: `rfi_escalation_migration_state` table (Task 15).
- Produces: command `rfi:escalation-rollback {--reason=}` — clears `cutover_completed_at` so `allowedStatusValues()` (Task 15) reverts to accepting legacy values again; documented runbook for the parts of rollback that are operational rather than a single command (e.g. disabling the new routes at the load balancer/feature-flag level if a full revert to the old escalate() behavior is ever needed).

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RfiEscalationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_clears_cutover_flag_without_touching_escalation_tables(): void
    {
        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('rfi:escalation-rollback', ['--reason' => 'Reverting after an unrelated production incident, needs investigation'])
            ->assertExitCode(0);

        $stillCutover = DB::table('rfi_escalation_migration_state')->whereNotNull('cutover_completed_at')->exists();
        $this->assertFalse($stillCutover);

        // The rfi_escalations and rfi_legacy_migration_confirmations TABLES themselves are never
        // touched by this command — it only ever writes to rfi_escalation_migration_state.
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('rfi_escalations'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('rfi_legacy_migration_confirmations'));
    }

    public function test_rollback_requires_a_reason(): void
    {
        DB::table('rfi_escalation_migration_state')->insert(['cutover_completed_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('rfi:escalation-rollback')->assertExitCode(1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiEscalationRollbackTest.php`
Expected: FAIL — command does not exist.

- [ ] **Step 3: Write the command**

`app/Console/Commands/RfiEscalationRollback.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RfiEscalationRollback extends Command
{
    protected $signature = 'rfi:escalation-rollback {--reason=}';

    protected $description = 'Revert application-level validation to pre-cutover behavior. Never touches rfi_escalations or confirmation data.';

    public function handle(): int
    {
        $reason = $this->option('reason');

        if (!$reason) {
            $this->error('--reason is required — rollback must record why it happened');
            return self::FAILURE;
        }

        DB::table('rfi_escalation_migration_state')->update(['cutover_completed_at' => null, 'updated_at' => now()]);

        Log::warning('rfi_escalation_cutover_rolled_back', ['reason' => $reason, 'rolled_back_at' => now()->toIso8601String()]);

        $this->info('Cutover flag cleared. status=escalated/pending are accepted again at the application layer. No rows in rfi_escalations or rfi_legacy_migration_confirmations were modified.');

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Write the runbook**

`docs/superpowers/runbooks/rfi-escalation-rollback.md`:

```markdown
# RFI Escalation — Production Rollback Runbook

**Do not run any migration `down()` in production once real escalation data exists.** All migrations in this feature are additive; their `down()` methods exist only for local/test iteration before any real data is written.

## What "rollback" means here

Rolling back this feature means reverting *application behavior* — new escalate/resolve/cancel actions, the cutover's tightened validation — while the `rfi_escalations`, `rfi_legacy_migration_confirmations`, and `rfi_escalation_migration_state` tables and all data in them stay exactly as they are. There is no scenario in this plan where rolling back means deleting or altering that data.

## Steps

1. **Deploy the previous application release** (the one before this feature's code was deployed), OR, if only the cutover specifically needs reverting while keeping the rest of the code:
2. Run `php artisan rfi:escalation-rollback --reason="<why>"` — clears `rfi_escalation_migration_state.cutover_completed_at`, which makes `RfiController::allowedStatusValues()` accept `escalated`/`pending` again at the application layer. This does not touch `rfi_escalations` or `rfi_legacy_migration_confirmations`.
3. If the new routes (`escalate`, `resolve-escalation`, `cancel`) must stop being reachable entirely (not just the cutover flag), disable them via the RBAC permission layer: revoke `rfi.escalate`/`rfi.cancel` from all roles (`ZenaAdminRolePermissionSeeder`/`ZenaProjectManagerRolePermissionSeeder` output), which causes the `rbac:rfi.*` route middleware to reject all callers with 403 without touching routes or code.
4. Confirm no data loss: `SELECT COUNT(*) FROM rfi_escalations` and `SELECT COUNT(*) FROM rfi_legacy_migration_confirmations` before and after rollback must be identical.
5. If the previous application release genuinely predates `current_escalation_id`/`rfi_escalations` (i.e. rolling back past this entire feature, not just the cutover), that older code simply never reads those columns/tables — they remain in the schema, unused but intact, until a future decision is made about them. Do not drop them as part of an emergency rollback.
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Console/RfiEscalationRollbackTest.php`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/RfiEscalationRollback.php docs/superpowers/runbooks/rfi-escalation-rollback.md tests/Feature/Console/RfiEscalationRollbackTest.php
git commit -m "feat(rfi): add production rollback command + runbook (flips behavior, never touches data)"
```

---

## Task 17: Real two-connection concurrency verification (MySQL only, not sqlite)

**Files:**
- Create: `app/Console/Commands/Testing/RfiConcurrencyTestEscalate.php`
- Create: `app/Console/Commands/Testing/RfiConcurrencyTestResolve.php`
- Create: `tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php`

**Interfaces:**
- Consumes: `RfiEscalationService` (Tasks 3-4).
- Produces: two Artisan test-support commands that invoke the real service from a genuinely separate OS process/DB connection; a test class that proves the row lock — not just the application-level state check — serializes concurrent access.

**This task requires a real MySQL connection.** The default test `DB_CONNECTION` in `phpunit.xml` is `sqlite` — this test class explicitly uses the `mysql` connection defined in `config/database.php` regardless of the default, and skips itself with a clear message if that connection cannot be reached, rather than silently passing on sqlite.

- [ ] **Step 1: Write the test-support commands**

`app/Console/Commands/Testing/RfiConcurrencyTestEscalate.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Exceptions\RfiEscalationConflictException;
use App\Models\Rfi;
use App\Services\RfiEscalationService;
use Illuminate\Console\Command;

/**
 * Test-support command: invokes RfiEscalationService::escalate() from a genuinely
 * separate OS process, so concurrency tests can prove real row-locking behavior
 * instead of simulating it with sequential calls in one PHPUnit process.
 */
class RfiConcurrencyTestEscalate extends Command
{
    protected $signature = 'rfi:concurrency-test-escalate {rfi_id} {target_id} {escalator_id} {reason}';

    protected $hidden = true;

    public function handle(RfiEscalationService $service): int
    {
        $rfi = Rfi::on('mysql')->findOrFail($this->argument('rfi_id'));

        try {
            $escalation = $service->escalate($rfi, $this->argument('target_id'), $this->argument('escalator_id'), $this->argument('reason'));
            $this->line('OK ' . $escalation->id);
            return self::SUCCESS;
        } catch (RfiEscalationConflictException $e) {
            $this->line('CONFLICT ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

`app/Console/Commands/Testing/RfiConcurrencyTestResolve.php`:

```php
<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Exceptions\RfiEscalationConflictException;
use App\Exceptions\RfiEscalationNotFoundException;
use App\Models\Rfi;
use App\Services\RfiEscalationService;
use Illuminate\Console\Command;

class RfiConcurrencyTestResolve extends Command
{
    protected $signature = 'rfi:concurrency-test-resolve {rfi_id} {resolver_id} {resolution}';

    protected $hidden = true;

    public function handle(RfiEscalationService $service): int
    {
        $rfi = Rfi::on('mysql')->findOrFail($this->argument('rfi_id'));

        try {
            $escalation = $service->resolveEscalation($rfi, $this->argument('resolver_id'), $this->argument('resolution'));
            $this->line('OK ' . $escalation->id);
            return self::SUCCESS;
        } catch (RfiEscalationConflictException|RfiEscalationNotFoundException $e) {
            $this->line('CONFLICT ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
```

- [ ] **Step 2: Write the concurrency test**

`tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\RfiEscalation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class RfiEscalationConcurrencyTest extends TestCase
{
    private function skipUnlessMysqlAvailable(): void
    {
        try {
            DB::connection('mysql')->select('SELECT 1');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'This test proves real row-locking behavior and MUST run against a real MySQL '
                . 'connection, not sqlite. The "mysql" connection in config/database.php is not '
                . 'reachable in this environment (' . $e->getMessage() . '). Run this suite in an '
                . 'environment with MySQL configured before treating concurrency as verified — a '
                . 'passing sqlite/sequential-call test is NOT evidence per the plan\'s blocker #5.'
            );
        }
    }

    protected function tearDown(): void
    {
        if (DB::connection('mysql')->getPdo()) {
            DB::connection('mysql')->table('rfi_escalations')->delete();
            DB::connection('mysql')->table('rfis')->delete();
            DB::connection('mysql')->table('projects')->delete();
            DB::connection('mysql')->table('tenants')->delete();
            DB::connection('mysql')->table('users')->delete();
        }
        parent::tearDown();
    }

    public function test_two_concurrent_escalate_calls_on_the_same_rfi_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $escalator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $target = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id, 'is_active' => true]));

        $rfi = Rfi::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'Concurrency test',
            'description' => 'd', 'priority' => 'medium', 'status' => 'open',
            'created_by' => $escalator->id, 'rfi_number' => 'CONC-RFI-0001',
        ]);

        // Hold the RFI row lock open on a second, independent PDO connection.
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s', config('database.connections.mysql.host'), config('database.connections.mysql.port'), config('database.connections.mysql.database'));
        $holder = new \PDO($dsn, config('database.connections.mysql.username'), config('database.connections.mysql.password'));
        $holder->beginTransaction();
        $holder->prepare('SELECT id FROM rfis WHERE id = ? FOR UPDATE')->execute([$rfi->id]);

        // Spawn a real second OS process that will block on the row lock held above.
        $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find();
        $process = new Process([
            $php, 'artisan', 'rfi:concurrency-test-escalate', $rfi->id, $target->id, $escalator->id, 'From subprocess',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $process->start();

        usleep(300_000); // give the subprocess time to reach and block on the FOR UPDATE lock

        $this->assertTrue($process->isRunning(), 'Subprocess should still be blocked waiting on the row lock held by the primary connection.');

        $holder->rollBack(); // release the lock without creating an escalation via this connection

        $process->wait();
        $this->assertSame(0, $process->getExitCode(), 'Subprocess escalate() should succeed once the lock is released: ' . $process->getOutput());
        $this->assertStringContainsString('OK', $process->getOutput());

        $this->assertSame(1, RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->count());

        // Now that an active escalation exists, a second concurrent attempt must conflict.
        $second = new Process([
            $php, 'artisan', 'rfi:concurrency-test-escalate', $rfi->id, $target->id, $escalator->id, 'Should conflict',
        ], base_path(), ['DB_CONNECTION' => 'mysql']);
        $second->run();

        $this->assertSame(1, $second->getExitCode());
        $this->assertStringContainsString('CONFLICT', $second->getOutput());
        $this->assertSame(1, RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->count(), 'Still exactly one escalation row after the conflicting attempt.');
    }

    public function test_two_concurrent_resolve_calls_on_the_same_escalation_only_one_succeeds(): void
    {
        $this->skipUnlessMysqlAvailable();

        $tenant = Tenant::on('mysql')->create(Tenant::factory()->raw());
        $project = Project::on('mysql')->create(Project::factory()->raw(['tenant_id' => $tenant->id]));
        $escalator = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id]));
        $target = User::on('mysql')->create(User::factory()->raw(['tenant_id' => $tenant->id, 'is_active' => true]));

        $rfi = Rfi::on('mysql')->create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id, 'title' => 'Concurrency resolve test',
            'description' => 'd', 'priority' => 'medium', 'status' => 'open',
            'created_by' => $escalator->id, 'rfi_number' => 'CONC-RFI-0002',
        ]);

        app(\App\Services\RfiEscalationService::class)->escalate($rfi->setConnection('mysql'), $target->id, $escalator->id, 'Urgent');

        $php = (new \Symfony\Component\Process\PhpExecutableFinder())->find();
        $procA = new Process([$php, 'artisan', 'rfi:concurrency-test-resolve', $rfi->id, $target->id, 'Resolved by A'], base_path(), ['DB_CONNECTION' => 'mysql']);
        $procB = new Process([$php, 'artisan', 'rfi:concurrency-test-resolve', $rfi->id, $escalator->id, 'Resolved by B'], base_path(), ['DB_CONNECTION' => 'mysql']);

        $procA->start();
        $procB->start();
        $procA->wait();
        $procB->wait();

        $exitCodes = [$procA->getExitCode(), $procB->getExitCode()];
        sort($exitCodes);
        $this->assertSame([0, 1], $exitCodes, 'Exactly one of the two concurrent resolve attempts must succeed and the other must conflict. A: ' . $procA->getOutput() . ' B: ' . $procB->getOutput());

        $escalation = RfiEscalation::on('mysql')->where('rfi_id', $rfi->id)->first();
        $this->assertNotNull($escalation->resolved_at);
        $this->assertNotNull($escalation->resolved_by);
    }
}
```

- [ ] **Step 3: Run the test against MySQL**

Run: `DB_CONNECTION=mysql ./vendor/bin/phpunit tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php`
Expected: PASS (2 tests) if a MySQL connection is reachable per `config/database.php`'s `mysql` connection; otherwise both tests report SKIPPED with the explicit message from `skipUnlessMysqlAvailable()` — a skip here is an honest "not verified in this environment", not a false pass, and must be treated as an open item for CI/staging before this plan's blocker #5 is considered closed.

- [ ] **Step 4: Commit**

```bash
git add app/Console/Commands/Testing/RfiConcurrencyTestEscalate.php app/Console/Commands/Testing/RfiConcurrencyTestResolve.php tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php
git commit -m "test(rfi): add genuine two-connection MySQL concurrency tests for escalate/resolveEscalation"
```

---

## Task 18: Full regression pass + legacy gate + rollback end-to-end verification

**Files:** none created/modified — verification only.

- [ ] **Step 1: Run every touched test file together**

Run: `./vendor/bin/phpunit tests/Unit/Models tests/Unit/Services tests/Unit/Seeders tests/Feature/Api/RfiApiTest.php tests/Feature/Console`
Expected: PASS, 0 failures.

- [ ] **Step 2: Run the full test suite to check for unrelated regressions**

Run: `./vendor/bin/phpunit`
Expected: same pass/fail baseline as before this plan started, plus this plan's new tests (the MySQL-only concurrency test SKIPPED is acceptable in the sqlite-default CI run; it is not acceptable to be silently absent from the suite). Read `RfiWorkflowTest`/`OperatorRfiUiTest` output carefully — Task 8/9's guards change existing behavior those tests may exercise; fix the guard's edge case or the pre-existing fixture, never silently loosen the new guard.

- [ ] **Step 3: Manually verify the legacy deployment gate end-to-end**

Against a local dev DB seeded with at least one `status='escalated'` row:
1. `php artisan rfi:escalation-preflight-report` — confirm it lists the row.
2. `php artisan rfi:escalation-cutover` — confirm exit code 1 (blocked).
3. `php artisan rfi:confirm-legacy-escalation {rfi id} --lifecycle=in_progress --escalation=unresolved --confirmed-by={user id} --reason="manual verification"` — confirm exit code 0.
4. `php artisan rfi:escalation-cutover` — confirm exit code 0.
5. `php artisan rfi:escalation-rollback --reason="manual verification of rollback path"` — confirm exit code 0, confirm `rfi_escalations`/`rfi_legacy_migration_confirmations` row counts are unchanged from before this step, confirm the RFI's `status='escalated'` value from step 3 is once again accepted by `RfiController::update()`'s validator.

Expected: exit codes 0(list has 1 row), 1, 0, 0, 0 in that order, and zero data loss confirmed at step 5.

- [ ] **Step 4: Run the MySQL concurrency suite if a MySQL environment is available**

Run: `DB_CONNECTION=mysql ./vendor/bin/phpunit tests/Feature/Concurrency/RfiEscalationConcurrencyTest.php`
Expected: PASS (2 tests) — record the result in the final whole-branch review; if this environment has no MySQL available, flag it explicitly as an unverified item for the reviewer rather than omitting it from the report.

- [ ] **Step 5: Commit (only if Steps 2-4 required a follow-up fix; otherwise nothing to commit)**

```bash
git status
```

---

## Self-Review

**Spec coverage:** all of rev 3's sections map to a task as in the prior version of this plan (see Tasks 1-2 for §2/§2.1/§2.2, 3-4 for §2/§9, 6-10 for §3-4/§7/§8, 13-15 for §6, 12 for §10, 17 for §9's concurrency claim, 16 for the rollback constraint this revision adds beyond the spec).

**Blocker coverage (this revision):**
1. Lifecycle centralization → Task 5 (`RfiLifecycleService`), Tasks 8-10 rewire the controller to delegate instead of containing transition logic; Task 3's own test (`test_service_never_reads_or_writes_rfi_status`) mechanically enforces the boundary stays clean.
2. Production rollback → Task 16 (command + runbook), Global Constraints explicitly forbid running `down()` after data exists.
3. Legacy gate schema → Task 14's `rfi_legacy_migration_confirmations` now stores `reason` (required, non-null) and `source_snapshot` (JSON, captured before any mutation) alongside who/when/what — not a boolean.
4. Current escalation invariant → Task 2's `assertEscalationPointerIntegrity()` + cross-RFI/cross-tenant/already-resolved regression tests; Task 1's `rfi_escalations.rfi_id` foreign key uses RESTRICT, not CASCADE.
5. Concurrency verification → Task 17's genuine two-PDO-connection, two-OS-process test against the real `mysql` connection, explicit skip-with-reason on sqlite rather than a false pass; Task 12's notification tests add a genuine outer-transaction-rollback case beyond the "second call throws before reaching dispatch" case already covered.

**Placeholder scan:** no "TBD"/"add error handling"/"similar to Task N" found.

**Type consistency check:** `RfiLifecycleService::respond/close/cancel(Rfi, ...): Rfi` (Task 5) called identically in Tasks 8-10. `RfiEscalationService::escalate/resolveEscalation/hasActiveEscalation` (Tasks 3-4) signatures unchanged from Task 3/4's own definitions when consumed in Tasks 5-7, 10, 12, 17. `Rfi::assertEscalationPointerIntegrity()` (Task 2) called identically in Task 4's `resolveEscalation()`. `RfiLegacyMigrationConfirmation` fillable fields (Task 14) match the columns Task 15's cutover query checks against (`rfi_legacy_migration_confirmations.rfi_id`).

---

Plan revised and saved to `docs/superpowers/plans/2026-07-26-rfi-lifecycle-escalation-implementation.md`. Proceeding to Subagent-Driven Development per operator instruction — no further execution-choice prompt needed.
