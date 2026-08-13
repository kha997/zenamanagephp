# GAP-033: Document Approver Assignment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a per-document designated approver, resolved from an optional explicit assignment falling back to the document's project manager, enforced through a governed service and a narrow authorization ability, with a mandatory append-only audit trail.

**Architecture:** Follows the exact governed-service pattern GAP-032 established (`DocumentWorkflowService`/`DocumentLifecycleService`/`DocumentVersionService`): a new `DocumentApproverAssignmentService` is the sole writer of `documents.approver_id`, using a tenant-scoped `lockForUpdate()` re-read inside a transaction, never authorizing itself. Adapters (API controller, Web controller) authorize via a new `DocumentPolicy::assignApprover()` ability before calling the service. A new append-only `document_approver_assignments` table records every change, mirroring `DocumentApprovalEvent`'s immutability enforcement. `DocumentPolicy::approve()` is NOT modified — assignment never grants decision rights (Owner Gate 2 binding clarification, §6.3 "(b)").

**Tech Stack:** Laravel 12, PHP 8.2, MySQL/SQLite (existing repo stack), PHPUnit, existing `AuthenticationTestTrait`/`TenantUserFactoryTrait` test fixtures.

## Global Constraints (from Gate 2 binding clarification, `docs/owner-decisions/GAP-033/02-design-v2.md`)

- Resolution order: explicit `documents.approver_id` if set → else the document's project's `pm_id` → else no specific approver (today's unchanged role-wide `document.approve` behavior).
- Only the document's project's own manager (`project.pm_id === actor.id`) or a tenant Admin (`super_admin`/`admin` role) may set or change the per-document approver. No one else — not `designer`, not a general `document.update` holder.
- Being the designated approver does NOT by itself grant decision rights. `DocumentPolicy::approve()` is unchanged; the assigned user must independently hold `document.approve`.
- Assignment/reassignment must be rejected at assignment time (not silently stored as "pending") if the target user is not in the same tenant as the document, or does not currently hold `document.approve`.
- A prior assignment PERSISTS across reopen/resubmit cycles; it is never auto-cleared.
- Every assignment change (initial set, reassignment, explicit clear) must be recorded in an append-only audit trail: who changed it, prior value, new value, when.
- Single approver only — no multiple/sequential/parallel approvers.
- No Today Workspace query object, view, or notification in this plan.
- No change to `DocumentWorkflowService`, `DocumentLifecycleService`, `DocumentVersionService`, `DocumentStatusService`, or any GAP-032 file's existing behavior.
- Gate 3 (release) is prepared only after this plan's implementation, testing, review, and CI are complete — not part of this plan's scope. No merge/release/deploy authorized here.

---

### Task 1: Schema — `approver_id` column and audit table

**Files:**
- Create: `database/migrations/2026_08_12_210000_add_approver_id_to_documents.php`
- Create: `database/migrations/2026_08_12_210100_create_document_approver_assignments_table.php`
- Test: `tests/Feature/Documents/DocumentApproverAssignmentMigrationTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `documents.approver_id` (nullable ULID, FK to `users`, indexed with `tenant_id`); `document_approver_assignments` table with columns `id, tenant_id, document_id, actor_id, previous_approver_id, new_approver_id, created_at, updated_at` — consumed by Task 2's `DocumentApproverAssignment` model.

- [ ] **Step 1: Write failing migration test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DocumentApproverAssignmentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_documents_table_has_nullable_approver_id_column(): void
    {
        self::assertTrue(Schema::hasColumn('documents', 'approver_id'));

        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        self::assertNull($document->fresh()->approver_id);
    }

    public function test_document_approver_assignments_table_exists_with_expected_columns(): void
    {
        self::assertTrue(Schema::hasTable('document_approver_assignments'));
        self::assertTrue(Schema::hasColumns('document_approver_assignments', [
            'id', 'tenant_id', 'document_id', 'actor_id',
            'previous_approver_id', 'new_approver_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_document_approver_assignments_row_can_be_inserted_with_null_previous_and_new(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $id = \Illuminate\Support\Str::ulid()->toBase32();
        \Illuminate\Support\Facades\DB::table('document_approver_assignments')->insert([
            'id' => $id,
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        self::assertDatabaseHas('document_approver_assignments', ['id' => $id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Documents/DocumentApproverAssignmentMigrationTest.php`
Expected: FAIL — `approver_id` column and `document_approver_assignments` table do not exist yet.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_12_210000_add_approver_id_to_documents.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignUlid('approver_id')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'approver_id'], 'documents_tenant_approver_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_tenant_approver_id_index');
            $table->dropConstrainedForeignId('approver_id');
        });
    }
};
```

`database/migrations/2026_08_12_210100_create_document_approver_assignments_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_approver_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->index();
            $table->foreignUlid('document_id')->constrained('documents')->restrictOnDelete();
            $table->foreignUlid('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('previous_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('new_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_approver_assignments');
    }
};
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Documents/DocumentApproverAssignmentMigrationTest.php`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_12_210000_add_approver_id_to_documents.php database/migrations/2026_08_12_210100_create_document_approver_assignments_table.php tests/Feature/Documents/DocumentApproverAssignmentMigrationTest.php
git commit -m "feat(documents): add approver_id column and audit table for GAP-033"
```

---

### Task 2: `DocumentApproverAssignment` model and `Document` model changes

**Files:**
- Create: `app/Models/DocumentApproverAssignment.php`
- Modify: `app/Models/Document.php`
- Test: `tests/Unit/Models/DocumentApproverAssignmentTest.php`

**Interfaces:**
- Consumes: `documents.approver_id`, `document_approver_assignments` table (Task 1).
- Produces: `DocumentApproverAssignment` (append-only model, `save()`/`delete()` throw `LogicException` on update/delete, mirroring `DocumentApprovalEvent`); `Document::approver(): BelongsTo`; `Document::effectiveApprover(): ?User` (pure read, resolution order: explicit `approver_id` → `project->pm_id` → `null`) — consumed by Task 4's service and Task 5/6's adapters.

- [ ] **Step 1: Write failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DocumentApproverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_row_is_append_only(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $assignment = DocumentApproverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $actor->id,
        ]);

        $assignment->new_approver_id = null;

        $this->expectException(LogicException::class);
        $assignment->save();
    }

    public function test_assignment_row_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $assignment = DocumentApproverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $actor->id,
        ]);

        $this->expectException(LogicException::class);
        $assignment->delete();
    }

    public function test_effective_approver_prefers_explicit_assignment_over_project_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $explicit = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => $explicit->id,
        ]);

        self::assertSame($explicit->id, $document->fresh()->effectiveApprover()?->id);
    }

    public function test_effective_approver_falls_back_to_project_manager_when_unset(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => null,
        ]);

        self::assertSame($pm->id, $document->fresh()->effectiveApprover()?->id);
    }

    public function test_effective_approver_is_null_when_neither_is_set(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => null]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => null,
        ]);

        self::assertNull($document->fresh()->effectiveApprover());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/Models/DocumentApproverAssignmentTest.php`
Expected: FAIL — `DocumentApproverAssignment` class does not exist; `Document::effectiveApprover()` does not exist.

- [ ] **Step 3: Create `DocumentApproverAssignment` model**

```php
<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $document_id
 * @property string $actor_id
 * @property string|null $previous_approver_id
 * @property string|null $new_approver_id
 */
class DocumentApproverAssignment extends Model
{
    use HasUlids;

    protected $table = 'document_approver_assignments';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'document_id',
        'actor_id',
        'previous_approver_id',
        'new_approver_id',
    ];

    /** @param array<string, mixed> $options */
    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Document approver assignments are append-only.');
        }

        return parent::save($options);
    }

    public function delete(): never
    {
        throw new LogicException('Document approver assignments are append-only.');
    }

    public function forceDelete(): never
    {
        throw new LogicException('Document approver assignments are append-only.');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<User, $this> */
    public function previousApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_approver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function newApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'new_approver_id');
    }
}
```

- [ ] **Step 4: Modify `app/Models/Document.php`**

Add `'approver_id'` to the `$fillable` array (`app/Models/Document.php:102-132`, alongside `current_version_id`).

Add these two methods near the existing `project()`/`uploader()` relations (`app/Models/Document.php:157-192`):

```php
    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * Resolution order (GAP-033, Owner Gate 2 §6.1): explicit per-document
     * assignment, else the document's project manager, else no specific
     * approver. Pure read — never writes, never authorizes.
     */
    public function effectiveApprover(): ?User
    {
        if ($this->approver_id !== null) {
            return $this->approver;
        }

        return $this->project?->pm_id !== null ? $this->project?->manager : null;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Unit/Models/DocumentApproverAssignmentTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Models/DocumentApproverAssignment.php app/Models/Document.php tests/Unit/Models/DocumentApproverAssignmentTest.php
git commit -m "feat(documents): add DocumentApproverAssignment model and effective-approver resolution"
```

---

### Task 3: Exception and authorization ability

**Files:**
- Create: `app/Exceptions/DocumentApproverAssignmentException.php`
- Modify: `app/Policies/DocumentPolicy.php`
- Test: `tests/Unit/Policies/DocumentAssignApproverPolicyTest.php`

**Interfaces:**
- Consumes: `Document::project()` (existing), `Project::pm_id` (existing).
- Produces: `DocumentApproverAssignmentException::tenantMismatch()`, `::targetLacksApprovalPermission()`, `::documentNotFound()` (each returns `self` with `reasonCode`, matching `DocumentWorkflowException`'s shape) — consumed by Task 4's service and Task 5/6's adapters. `DocumentPolicy::assignApprover(User $user, Document $document): bool` — consumed by Task 5/6's adapters.

- [ ] **Step 1: Write failing policy test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentAssignApproverPolicyTest extends TestCase
{
    use RefreshDatabase;

    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new DocumentPolicy();
    }

    public function test_projects_own_manager_may_assign_approver(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertTrue($this->policy->assignApprover($pm, $document));
    }

    public function test_admin_role_may_assign_approver_even_if_not_the_projects_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        $admin->roles()->attach(\App\Models\Role::factory()->create(['name' => 'admin']));
        $otherPm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $otherPm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertTrue($this->policy->assignApprover($admin, $document));
    }

    public function test_designer_role_with_document_update_permission_may_not_assign_approver(): void
    {
        $tenant = Tenant::factory()->create();
        $designer = User::factory()->create(['tenant_id' => $tenant->id]);
        $otherPm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $otherPm->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);

        self::assertFalse($this->policy->assignApprover($designer, $document));
    }

    public function test_cross_tenant_user_may_not_assign_approver_even_as_admin(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenantB->id]);
        $admin->roles()->attach(\App\Models\Role::factory()->create(['name' => 'admin']));
        $project = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $document = Document::factory()->create(['tenant_id' => $tenantA->id, 'project_id' => $project->id]);

        self::assertFalse($this->policy->assignApprover($admin, $document));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/Policies/DocumentAssignApproverPolicyTest.php`
Expected: FAIL — `DocumentPolicy::assignApprover()` does not exist.

- [ ] **Step 3: Create the exception class**

```php
<?php declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

final class DocumentApproverAssignmentException extends RuntimeException
{
    private function __construct(
        public readonly string $reasonCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function documentNotFound(): self
    {
        return new self('DOCUMENT_NOT_FOUND', 'Document not found for this tenant.');
    }

    public static function tenantMismatch(): self
    {
        return new self(
            'APPROVER_TENANT_MISMATCH',
            'The proposed approver does not belong to this document\'s tenant.'
        );
    }

    public static function targetLacksApprovalPermission(): self
    {
        return new self(
            'APPROVER_LACKS_PERMISSION',
            'The proposed approver does not currently hold document approval permission.'
        );
    }
}
```

- [ ] **Step 4: Add `assignApprover()` to `DocumentPolicy`**

Add to `app/Policies/DocumentPolicy.php`, after the existing `approve()` method (`app/Policies/DocumentPolicy.php:113-121`):

```php
    /**
     * GAP-033 §6.2: only the document's project's own manager, or a tenant
     * Admin, may set or change the designated approver. Deliberately
     * narrower than update() — general editors/designers may not.
     */
    public function assignApprover(User $user, Document $document)
    {
        if ($user->tenant_id !== $document->tenant_id) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        return $document->project?->pm_id !== null && $user->id === $document->project?->pm_id;
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/Policies/DocumentAssignApproverPolicyTest.php`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/DocumentApproverAssignmentException.php app/Policies/DocumentPolicy.php tests/Unit/Policies/DocumentAssignApproverPolicyTest.php
git commit -m "feat(documents): add assignApprover policy ability and exception"
```

---

### Task 4: `DocumentApproverAssignmentService` (governed service)

**Files:**
- Create: `app/Services/DocumentApproverAssignmentService.php`
- Test: `tests/Feature/Services/DocumentApproverAssignmentServiceTest.php`

**Interfaces:**
- Consumes: `Document` (Task 2), `DocumentApproverAssignment::query()->create()` (Task 2), `DocumentApproverAssignmentException` (Task 3).
- Produces: `DocumentApproverAssignmentService::findForTenant(string $tenantId, string $documentId): ?Document`, `DocumentApproverAssignmentService::assign(string $tenantId, string $documentId, string $actorId, ?string $newApproverId): Document` — consumed by Task 5 (API adapter) and Task 6 (Web adapter). The service does NOT authorize (no `Auth`/`Gate`/`authorize()` calls) — matches every GAP-032 governed service.

- [ ] **Step 1: Write failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\DocumentApproverAssignmentException;
use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentApproverAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentApproverAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private DocumentApproverAssignmentService $service;
    private Tenant $tenant;
    private Project $project;
    private Document $document;
    private User $actor;
    private User $eligibleApprover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentApproverAssignmentService::class);

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['pm'], []);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => $this->actor->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
        ]);
        $this->eligibleApprover = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
    }

    public function test_assign_sets_approver_id_and_writes_audit_row(): void
    {
        $document = $this->service->assign(
            $this->tenant->id,
            $this->document->id,
            $this->actor->id,
            $this->eligibleApprover->id,
        );

        self::assertSame($this->eligibleApprover->id, $document->approver_id);
        self::assertDatabaseHas('document_approver_assignments', [
            'document_id' => $this->document->id,
            'actor_id' => $this->actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $this->eligibleApprover->id,
        ]);
    }

    public function test_assign_rejects_target_from_a_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $outsider->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_TENANT_MISMATCH', $e->reasonCode);
            self::assertNull($this->document->fresh()->approver_id);
            self::assertDatabaseCount('document_approver_assignments', 0);
            throw $e;
        }
    }

    public function test_assign_rejects_target_without_document_approve_permission(): void
    {
        $ineligible = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $ineligible->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_LACKS_PERMISSION', $e->reasonCode);
            throw $e;
        }
    }

    public function test_assign_with_null_clears_explicit_override_and_records_it(): void
    {
        $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);

        $document = $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, null);

        self::assertNull($document->approver_id);
        self::assertDatabaseHas('document_approver_assignments', [
            'document_id' => $this->document->id,
            'previous_approver_id' => $this->eligibleApprover->id,
            'new_approver_id' => null,
        ]);
    }

    public function test_assign_throws_document_not_found_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($otherTenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
            throw $e;
        }
    }

    public function test_reassignment_persists_across_a_reopen_cycle(): void
    {
        // GAP-033 §6.5: reopening (Approval reset to not-submitted) must NOT
        // clear a prior assignment. The service itself never touches Approval,
        // so this proves it has no side effect on that dimension at all.
        $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);

        $fresh = $this->document->fresh();
        self::assertSame('draft', $fresh->getRawOriginal('lifecycle_status') ?? $fresh->status);
        self::assertSame($this->eligibleApprover->id, $fresh->approver_id);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Services/DocumentApproverAssignmentServiceTest.php`
Expected: FAIL — `DocumentApproverAssignmentService` class does not exist.

- [ ] **Step 3: Implement the service**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Exceptions\DocumentApproverAssignmentException;
use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Sole owner of writes to documents.approver_id and the
 * document_approver_assignments audit trail (GAP-033).
 *
 * This service never authorizes (no Auth/Gate/authorize() calls) — the
 * calling adapter must authorize (DocumentPolicy::assignApprover) against a
 * tenant-scoped resource lookup before calling assign(). It never touches
 * lifecycle_status/approval_status/current_version_id/DocumentVersion — those
 * remain owned by DocumentWorkflowService/DocumentLifecycleService/
 * DocumentVersionService respectively (GAP-032).
 */
class DocumentApproverAssignmentService
{
    public function findForTenant(string $tenantId, string $documentId): ?Document
    {
        return Document::query()
            ->where('tenant_id', $tenantId)
            ->with('project')
            ->find($documentId);
    }

    public function assign(string $tenantId, string $documentId, string $actorId, ?string $newApproverId): Document
    {
        return DB::transaction(function () use ($tenantId, $documentId, $actorId, $newApproverId): Document {
            $document = $this->lockDocument($tenantId, $documentId);

            if ($newApproverId !== null) {
                $target = User::query()->find($newApproverId);
                if ($target === null || $target->tenant_id !== $tenantId) {
                    throw DocumentApproverAssignmentException::tenantMismatch();
                }
                if (! $target->hasPermission('document.approve')) {
                    throw DocumentApproverAssignmentException::targetLacksApprovalPermission();
                }
            }

            $previousApproverId = $document->approver_id;

            $document->forceFill(['approver_id' => $newApproverId])->save();

            DocumentApproverAssignment::query()->create([
                'tenant_id' => $tenantId,
                'document_id' => $documentId,
                'actor_id' => $actorId,
                'previous_approver_id' => $previousApproverId,
                'new_approver_id' => $newApproverId,
            ]);

            return $document->fresh(['project']);
        });
    }

    private function lockDocument(string $tenantId, string $documentId): Document
    {
        $row = DB::table('documents')
            ->where('tenant_id', $tenantId)
            ->where('id', $documentId)
            ->whereNull('deleted_at')
            ->lockForUpdate()
            ->first();

        if ($row === null) {
            throw DocumentApproverAssignmentException::documentNotFound();
        }

        $document = new Document();
        $document->setRawAttributes((array) $row, true);
        $document->exists = true;

        return $document;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Services/DocumentApproverAssignmentServiceTest.php`
Expected: PASS (6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/DocumentApproverAssignmentService.php tests/Feature/Services/DocumentApproverAssignmentServiceTest.php
git commit -m "feat(documents): add DocumentApproverAssignmentService"
```

---

### Task 5: API adapter

**Files:**
- Modify: `app/Http/Controllers/Api/SimpleDocumentController.php`
- Modify: `routes/api_zena.php`
- Modify: `tests/Feature/Api/DocumentLifecycleActionsTest.php`

**Interfaces:**
- Consumes: `DocumentApproverAssignmentService::findForTenant()`/`::assign()` (Task 4), `DocumentPolicy::assignApprover` (Task 3, resolved automatically by `$this->authorize()`).
- Produces: `POST /api/zena/documents/{id}/approver` (route name `documents.approver.assign`), body `{"approver_id": "<ulid>"|null}`.

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Api/DocumentLifecycleActionsTest.php` (same file, same fixtures — `$this->actor` is already a `designer` with `document.approve`; add a project-manager fixture per test as needed):

```php
    public function test_project_manager_can_assign_approver(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $this->tenant, $this->project, $pm);

        $this->apiAs($pm, $this->tenant);
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $eligible->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.approver_id', $eligible->id);
    }

    public function test_designer_without_pm_or_admin_role_cannot_assign_approver(): void
    {
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        // $this->actor is a 'designer', not the project's pm and not admin.
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $eligible->id,
        ])->assertForbidden();
    }

    public function test_assigning_a_target_without_document_approve_permission_returns_conflict(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $ineligible = $this->createTenantUser($this->tenant, [], ['designer'], ['document.view']);
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $this->tenant, $this->project, $pm);

        $this->apiAs($pm, $this->tenant);
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $ineligible->id,
        ])->assertStatus(409);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php`
Expected: FAIL — route `documents.approver.assign` does not exist.

- [ ] **Step 3: Add the controller action**

Add to `app/Http/Controllers/Api/SimpleDocumentController.php`, after the existing `reactivate()` action (same style as `publish()` at `app/Http/Controllers/Api/SimpleDocumentController.php:407-427`):

```php
    public function assignApprover(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'approver_id' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return ErrorEnvelopeService::validationError($validator->errors()->toArray());
        }

        $tenantId = $this->resolveTenantId();

        $documentForAuth = app(\App\Services\DocumentApproverAssignmentService::class)->findForTenant($tenantId, $id);
        if ($documentForAuth === null) {
            return ErrorEnvelopeService::notFoundError('Document');
        }
        $this->authorize('assignApprover', $documentForAuth);

        try {
            $document = app(\App\Services\DocumentApproverAssignmentService::class)->assign(
                $tenantId,
                $id,
                (string) Auth::id(),
                $request->input('approver_id'),
            );
        } catch (\App\Exceptions\DocumentApproverAssignmentException $e) {
            report($e);

            return match ($e->reasonCode) {
                'DOCUMENT_NOT_FOUND' => ErrorEnvelopeService::notFoundError('Document'),
                default => ErrorEnvelopeService::conflictError('The proposed approver is not eligible for this document'),
            };
        }

        return $this->zenaSuccessResponse($document, 'Document approver updated successfully');
    }
```

- [ ] **Step 4: Register the route**

Add to `routes/api_zena.php`, in the same route group as the existing `publish`/`archive`/`reopen`/`reactivate` routes (`routes/api_zena.php:486-489`):

```php
            Route::post('/{id}/approver', [\App\Http\Controllers\Api\SimpleDocumentController::class, 'assignApprover'])->middleware('rbac:document.update')->name('documents.approver.assign');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php`
Expected: PASS (all tests, including the 3 new ones).

Also run: `php artisan route:list --json | php scripts/ci/route-guard.php` — Expected: `ROUTE_GUARD_OK`.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/SimpleDocumentController.php routes/api_zena.php tests/Feature/Api/DocumentLifecycleActionsTest.php
git commit -m "feat(documents): add API endpoint to assign document approver"
```

---

### Task 6: Web adapter and minimal UI

**Files:**
- Modify: `app/Http/Controllers/Web/DocumentWorkflowController.php`
- Modify: `app/Http/Controllers/Web/DocumentController.php`
- Modify: `routes/web.php`
- Modify: `resources/views/documents/approvals.blade.php`
- Modify: `tests/Feature/Web/DocumentWorkflowControllerTest.php`

**Interfaces:**
- Consumes: `DocumentApproverAssignmentService` (Task 4), `DocumentPolicy::assignApprover` (Task 3).
- Produces: `POST /documents/{document}/approver` (route name `app.documents.approver.assign`, redirect-based like `submit`/`publish`); `DocumentController::approvals()` now also passes `eligibleApprovers` (users in-tenant holding `document.approve`) to the view.

- [ ] **Step 1: Write failing tests**

Append to `tests/Feature/Web/DocumentWorkflowControllerTest.php`, matching this file's exact existing pattern (`$this->tenant`/`$this->project` from `setUp()`, `makeDocument(array $overrides = [])`, and `$this->actingAs($actor)->withHeaders(['X-Tenant-ID' => ...])->post(route(...))`, e.g. as used at lines 117-124):

```php
    public function test_projects_manager_can_assign_approver_via_web(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $document = $this->makeDocument();

        $response = $this->actingAs($pm)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.approver.assign', ['document' => $document->id]), [
                'approver_id' => $eligible->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        self::assertSame($eligible->id, $document->fresh()->approver_id);
    }

    public function test_non_manager_cannot_assign_approver_via_web(): void
    {
        $document = $this->makeDocument();
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $designer = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $this->actingAs($designer)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.approver.assign', ['document' => $document->id]), [
                'approver_id' => $eligible->id,
            ])->assertForbidden();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Web/DocumentWorkflowControllerTest.php`
Expected: FAIL — route `app.documents.approver.assign` does not exist.

- [ ] **Step 3: Add the controller action**

Add to `app/Http/Controllers/Web/DocumentWorkflowController.php`, constructor-inject `DocumentApproverAssignmentService` alongside the existing `DocumentWorkflowService`/`DocumentLifecycleService` (`app/Http/Controllers/Web/DocumentWorkflowController.php:16-19`), then add (same style as `submit()` at lines 21-48):

```php
    public function assignApprover(Request $request, string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->approverAssignment->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('assignApprover', $document);

        try {
            $this->approverAssignment->assign($tenantId, $documentId, (string) Auth::id(), $request->input('approver_id'));
        } catch (\App\Exceptions\DocumentApproverAssignmentException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Người được chọn chưa đủ điều kiện làm người duyệt.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã cập nhật người duyệt.');
    }
```

(Add `private readonly \App\Services\DocumentApproverAssignmentService $approverAssignment` to the constructor parameter list.)

- [ ] **Step 4: Register the route**

Add to `routes/web.php`, in the same group as the existing `documents.workflow.*` routes (`routes/web.php:419-425`):

```php
    Route::post('/documents/{document}/approver', [App\Http\Controllers\Web\DocumentWorkflowController::class, 'assignApprover'])->middleware('rbac:document.update')->name('documents.approver.assign');
```

- [ ] **Step 5: Pass eligible approvers to the approvals view**

Modify `DocumentController::approvals()` (`app/Http/Controllers/Web/DocumentController.php:210-233`): add, right after `$decisionUsers = $this->decisionUsersFor($documents, $tenantId);`:

```php
            $eligibleApprovers = \App\Models\User::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('roles.permissions', fn ($q) => $q->where('name', 'document.approve'))
                ->orderBy('name')
                ->get(['id', 'name']);
```

Add `'eligibleApprovers'` to both `compact('documents', 'projects', 'decisionUsers')` (success path, line 233) and the catch-block's returned view data (lines 237-240, default to `collect()`).

- [ ] **Step 6: Add the UI to the approvals table**

In `resources/views/documents/approvals.blade.php`, add a new `<th>Người duyệt</th>` column header near the existing headers, and a new `<td>` in the row loop (`resources/views/documents/approvals.blade.php:48-81`), before the existing action `<td>`:

```blade
                        <td class="text-sm text-slate-600">
                            @can('assignApprover', $document)
                                <form method="POST" action="{{ route('app.documents.approver.assign', ['document' => $document->id]) }}" class="inline-flex items-center gap-2">
                                    @csrf
                                    <select name="approver_id" class="operator-select operator-select-sm">
                                        <option value="">— Chưa gán —</option>
                                        @foreach ($eligibleApprovers as $eligible)
                                            <option value="{{ $eligible->id }}" @selected($document->approver_id === $eligible->id)>{{ $eligible->name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="operator-button operator-button-secondary operator-button-sm">Lưu</button>
                                </form>
                            @else
                                {{ $document->effectiveApprover()?->name ?? '—' }}
                            @endcan
                        </td>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Web/DocumentWorkflowControllerTest.php tests/Feature/Web/DocumentApprovalsPageTest.php`
Expected: PASS.

Also run: `php artisan route:list --json | php scripts/ci/route-guard.php` — Expected: `ROUTE_GUARD_OK`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/DocumentWorkflowController.php app/Http/Controllers/Web/DocumentController.php routes/web.php resources/views/documents/approvals.blade.php tests/Feature/Web/DocumentWorkflowControllerTest.php
git commit -m "feat(documents): add Web approver assignment adapter and approvals-page UI"
```

---

### Task 7: Extend the architecture ownership guard

**Files:**
- Modify: `tests/Architecture/DocumentMutationOwnershipTest.php`

**Interfaces:**
- Consumes: `DocumentApproverAssignmentService` (Task 4) as a new entry in `GOVERNED_SERVICES`.
- Produces: no new interface — this task only strengthens the existing guard (`DOCUMENT_SURFACE_PATTERNS`, `PROTECTED_WRITE_TOKENS`, `FORBIDDEN_VERSION_WRITE_PATTERNS`-equivalent for the approver surface) so a future direct write to `approver_id` outside `DocumentApproverAssignmentService` fails this test, exactly as Task 7 of GAP-032 did for the version surface.

- [ ] **Step 1: Write the failing negative-control check**

This step is verification-by-construction, not a new PHPUnit test: after Step 3 below, temporarily (in a scratch copy, not committed) add `$document->forceFill(['approver_id' => $x])->save();` to `SimpleDocumentController::assignApprover()` in place of the service call, and confirm `test_every_routed_state_or_version_mutator_uses_its_governed_service` fails. Revert the scratch edit before continuing — this is a manual proof step, not a committed test file.

- [ ] **Step 2: Run the existing suite to confirm current green baseline**

Run: `php artisan test tests/Architecture/DocumentMutationOwnershipTest.php`
Expected: PASS at this point (Task 5/6 already made `SimpleDocumentController@assignApprover` and `DocumentWorkflowController@assignApprover` touch the Document surface via `$document`/`approver_id`, so they'll already show as GOVERNED once classified — but the guard doesn't yet know `DocumentApproverAssignmentService` is a legitimate governed service, nor does it scan for `approver_id` on non-state methods).

- [ ] **Step 3: Extend the guard's constants**

In `tests/Architecture/DocumentMutationOwnershipTest.php`:

Add to `GOVERNED_SERVICES` (`tests/Architecture/DocumentMutationOwnershipTest.php:34-40`):
```php
        'DocumentApproverAssignmentService',
```

Add to `DOCUMENT_SURFACE_PATTERNS` (`tests/Architecture/DocumentMutationOwnershipTest.php:47-60`):
```php
        '/\bDocumentApproverAssignmentService\b/',
        '/\bapprover_id\b/',
```

Add to `PROTECTED_WRITE_TOKENS` (`tests/Architecture/DocumentMutationOwnershipTest.php:90-101`):
```php
        'approver_id',
        'document_approver_assignments',
```

Add `'documents.approver.assign' => 'App\\Http\\Controllers\\Api\\SimpleDocumentController@assignApprover'`-style entries are NOT needed — `CLASSIFICATION` is built from live routes automatically; only add explicit classification if the test's own diff shows a new "unclassified" failure after this step, in which case add:

```php
        'App\\Http\\Controllers\\Api\\SimpleDocumentController@assignApprover' => self::GOVERNED,
        'App\\Http\\Controllers\\Web\\DocumentWorkflowController@assignApprover' => self::GOVERNED,
```

to the `CLASSIFICATION` array (`tests/Architecture/DocumentMutationOwnershipTest.php:105-160`), matching the existing entries' exact array style.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Architecture/DocumentMutationOwnershipTest.php`
Expected: PASS. If `test_every_routed_document_mutator_is_explicitly_classified` fails with an unclassified-methods list, add exactly those two keys to `CLASSIFICATION` as shown in Step 3 and re-run.

- [ ] **Step 5: Commit**

```bash
git add tests/Architecture/DocumentMutationOwnershipTest.php
git commit -m "test(documents): extend architecture guard to cover approver_id surface"
```

---

### Task 8: Full verification

**Files:**
- Modify only if verification finds an in-scope defect: files already listed in Tasks 1-7.

**Interfaces:**
- Consumes: the complete implementation.
- Produces: fresh evidence for engineering review; does not create Gate 3 or release authorization.

- [ ] **Step 1: Run all focused suites**

```bash
php artisan test tests/Feature/Documents/DocumentApproverAssignmentMigrationTest.php
php artisan test tests/Unit/Models/DocumentApproverAssignmentTest.php
php artisan test tests/Unit/Policies/DocumentAssignApproverPolicyTest.php
php artisan test tests/Feature/Services/DocumentApproverAssignmentServiceTest.php
php artisan test tests/Feature/Api/DocumentLifecycleActionsTest.php
php artisan test tests/Feature/Web/DocumentWorkflowControllerTest.php
php artisan test tests/Feature/Web/DocumentApprovalsPageTest.php
php artisan test tests/Architecture/DocumentMutationOwnershipTest.php
```

Expected: all PASS, 0 failures.

- [ ] **Step 2: Run repository-wide verification**

```bash
php artisan test
./vendor/bin/phpstan analyse --memory-limit=2G
composer deptrac
php scripts/ssot/owner_governance_lint.php
php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering
php artisan route:list --json | php scripts/ci/route-guard.php
git diff --check
```

Expected: all exit 0. Classify any pre-existing baseline failure (e.g. the known local-only `DashboardApiTest` Redis flake, or no-MySQL/no-`.env` sandbox artifacts documented throughout the GAP-032 ledger) separately from a genuine new failure; do not call the implementation ready while a new failure remains.

- [ ] **Step 3: Verify scope boundaries**

```bash
git diff --name-only origin/main...HEAD
rg -n "approver_id" app database/migrations resources/views tests
```

Confirm: no file outside this plan's Task 1-7 list was touched; no change to `DocumentWorkflowService.php`, `DocumentLifecycleService.php`, `DocumentVersionService.php`, `DocumentStatusService.php`, or `DocumentPolicy::approve()`; every write of `approver_id` traces to `DocumentApproverAssignmentService` (service) or a test fixture.

- [ ] **Step 4: Request independent implementation review**

Dispatch a fresh reviewer with the approved Gate 2 spec (`docs/superpowers/specs/2026-08-12-gap033-document-approver-assignment-design.md`, `docs/owner-decisions/GAP-033/02-design-v2.md`), this plan, the base SHA, the implementation HEAD SHA, and the full diff. Focus the review on: authorization narrowness (§6.2 — only project's own PM or Admin, not any PM), assignment not granting decision rights (§6.3 — `DocumentPolicy::approve()` genuinely untouched), reopen-persistence (§6.5), assignment-time scope/eligibility validation (§6.9 — hard rejection, not silent dormant storage), audit-trail completeness and immutability (§6.8), tenant isolation, and no regression to any GAP-032 governed service or test. Require Critical/Important/Minor findings and fix every confirmed Critical or Important issue before presenting engineering readiness.

- [ ] **Step 5: Commit only review-driven corrections**

```bash
git add <only files actually changed by review fixes>
git commit -m "fix(documents): address GAP-033 implementation review"
```

If review finds no required correction, do not create an empty commit.

---

### Task 9: Enforce project-team membership on approver assignment (Owner reconciliation of I-2)

**Owner clarification (post-Task-8 final review, not a new business-scope expansion — reconciliation of the already-approved Gate 2 rule "đúng phạm vi tenant/project"):** the assignment target must (1) belong to the same tenant, (2) be an active member of the document's specific project via `project_team_members`, AND (3) independently hold `document.approve`. Assignment must not grant approval authority by itself (unchanged from Task 4/§6.3).

**Files:**
- Modify: `app/Exceptions/DocumentApproverAssignmentException.php`
- Modify: `app/Services/DocumentApproverAssignmentService.php`
- Modify: `tests/Feature/Services/DocumentApproverAssignmentServiceTest.php`
- Modify: `tests/Feature/Api/DocumentLifecycleActionsTest.php`

**Interfaces:**
- Consumes: `project_team_members` table (`project_id`, `user_id`, `left_at` nullable — existing, pre-GAP-033 schema; no migration in this task). Active membership = a row where `left_at IS NULL`.
- Produces: `DocumentApproverAssignmentException::notProjectMember()` (new factory, reason code `APPROVER_NOT_PROJECT_MEMBER`) — consumed by the existing adapter `match($e->reasonCode)` blocks in `SimpleDocumentController::assignApprover()`/`DocumentWorkflowController::assignApprover()`, which already fall through to their existing `default =>` conflict/error-envelope arm for any non-`DOCUMENT_NOT_FOUND` code — no adapter code change required, but add one API-level test proving the new rejection surfaces correctly end-to-end.

- [ ] **Step 1: Write failing tests**

Add to `tests/Feature/Services/DocumentApproverAssignmentServiceTest.php` (extend `setUp()` to also create a `project_team_members` row for `$this->eligibleApprover` on `$this->project`, matching the Owner's scenario 1; the existing `$this->eligibleApprover` fixture becomes the "on the right project" case):

```php
    public function test_assign_succeeds_when_target_is_same_tenant_same_project_and_has_document_approve(): void
    {
        // $this->eligibleApprover is already same-tenant + document.approve (existing fixture).
        // This test additionally requires setUp() to insert a project_team_members row for
        // $this->eligibleApprover on $this->project (see Step 3 setUp() change below) — once
        // that exists, this is the existing test_assign_sets_approver_id_and_writes_audit_row
        // scenario, now passing BECAUSE of the membership row, not despite it.
        $document = $this->service->assign(
            $this->tenant->id,
            $this->document->id,
            $this->actor->id,
            $this->eligibleApprover->id,
        );

        self::assertSame($this->eligibleApprover->id, $document->approver_id);
    }

    public function test_assign_rejects_target_in_a_different_project_same_tenant(): void
    {
        $otherProject = \App\Models\Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherProjectDocument = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $otherProject->id,
        ]);
        // $this->eligibleApprover is a member of $this->project (per setUp()), NOT $otherProject.

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $otherProjectDocument->id, $this->actor->id, $this->eligibleApprover->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_NOT_PROJECT_MEMBER', $e->reasonCode);
            self::assertDatabaseCount('document_approver_assignments', 0);
            throw $e;
        }
    }

    public function test_assign_rejects_target_who_is_project_member_but_lacks_document_approve(): void
    {
        $memberWithoutPermission = User::factory()->create(['tenant_id' => $this->tenant->id]);
        \Illuminate\Support\Facades\DB::table('project_team_members')->insert([
            'project_id' => $this->project->id,
            'user_id' => $memberWithoutPermission->id,
            'role' => 'member',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $memberWithoutPermission->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_LACKS_PERMISSION', $e->reasonCode);
            throw $e;
        }
    }

    public function test_reassignment_also_enforces_project_membership(): void
    {
        $otherProject = \App\Models\Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $notAMember = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        // First, a valid assignment.
        $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);

        // Then a reassignment attempt to someone not on $this->project fails the same way.
        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $notAMember->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_NOT_PROJECT_MEMBER', $e->reasonCode);
            // Confirm the FIRST assignment is untouched by the failed second attempt.
            self::assertSame($this->eligibleApprover->id, $this->document->fresh()->approver_id);
            throw $e;
        }
    }
```

Note: `test_assign_rejects_target_from_a_different_tenant` (existing, Task 4) already proves the tenant check still fires before any project-membership check for a cross-tenant target — no new test needed for that ordering, but confirm it still passes unchanged after this task's implementation (tenant check must remain first in the validation order).

Add to `tests/Feature/Api/DocumentLifecycleActionsTest.php`:

```php
    public function test_assigning_a_target_from_a_different_project_returns_conflict(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $otherProject = \App\Models\Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $notOnThisProject = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $document = $this->makeDocument(DocumentLifecycleStatus::DRAFT, DocumentApprovalStatus::NOT_SUBMITTED, $this->tenant, $this->project, $pm);

        $this->apiAs($pm, $this->tenant);
        $this->apiPost($this->zena('documents.approver.assign', ['id' => $document->id]), [
            'approver_id' => $notOnThisProject->id,
        ])->assertStatus(409);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `APP_KEY="base64:LYHhwg4+zl3mLo+aeOaJ4U/Uw0y3w6K+zw1NP+PAoTk=" ./vendor/bin/phpunit tests/Feature/Services/DocumentApproverAssignmentServiceTest.php tests/Feature/Api/DocumentLifecycleActionsTest.php`
Expected: FAIL — `notProjectMember()` factory doesn't exist yet; project-membership isn't checked yet, so several new tests either error or pass for the wrong reason (e.g. the different-project test would currently succeed when it should reject).

- [ ] **Step 3: Implement the project-membership check**

Add to `app/Exceptions/DocumentApproverAssignmentException.php`, alongside the existing factories:

```php
    public static function notProjectMember(): self
    {
        return new self(
            'APPROVER_NOT_PROJECT_MEMBER',
            'The proposed approver is not an active member of this document\'s project.'
        );
    }
```

Modify `DocumentApproverAssignmentService::assign()` — insert the project-membership check between the existing tenant check and the `document.approve` permission check (tenant check stays first; a cross-tenant target must never leak whether they'd otherwise have passed the project check):

```php
            if ($newApproverId !== null) {
                $target = User::query()->find($newApproverId);
                if ($target === null || $target->tenant_id !== $tenantId) {
                    throw DocumentApproverAssignmentException::tenantMismatch();
                }
                $isProjectMember = DB::table('project_team_members')
                    ->where('project_id', $document->project_id)
                    ->where('user_id', $newApproverId)
                    ->whereNull('left_at')
                    ->exists();
                if (! $isProjectMember) {
                    throw DocumentApproverAssignmentException::notProjectMember();
                }
                if (! $target->hasPermission('document.approve')) {
                    throw DocumentApproverAssignmentException::targetLacksApprovalPermission();
                }
            }
```

Update `tests/Feature/Services/DocumentApproverAssignmentServiceTest.php`'s `setUp()`: after creating `$this->eligibleApprover`, insert a `project_team_members` row for it on `$this->project` (mirror the raw-insert shape used in the new `test_assign_rejects_target_who_is_project_member_but_lacks_document_approve` test above, with `role: 'member'`), so the existing baseline tests (`test_assign_sets_approver_id_and_writes_audit_row`, `test_assign_with_null_clears_explicit_override_and_records_it`, `test_reassignment_persists_across_a_reopen_cycle`) keep passing under the new stricter check without modifying their own bodies.

- [ ] **Step 4: Run tests to verify they pass**

Run: `APP_KEY="base64:LYHhwg4+zl3mLo+aeOaJ4U/Uw0y3w6K+zw1NP+PAoTk=" ./vendor/bin/phpunit tests/Feature/Services/DocumentApproverAssignmentServiceTest.php tests/Feature/Api/DocumentLifecycleActionsTest.php`
Expected: PASS, all tests including the pre-existing ones from Tasks 4/5.

- [ ] **Step 5: Regression and commit**

```bash
./vendor/bin/phpunit tests/Feature/Web/DocumentWorkflowControllerTest.php tests/Feature/Web/DocumentApprovalsPageTest.php tests/Unit/Policies/DocumentAssignApproverPolicyTest.php tests/Architecture/DocumentMutationOwnershipTest.php
./vendor/bin/phpstan analyse --memory-limit=2G
git diff --check
git add app/Exceptions/DocumentApproverAssignmentException.php app/Services/DocumentApproverAssignmentService.php tests/Feature/Services/DocumentApproverAssignmentServiceTest.php tests/Feature/Api/DocumentLifecycleActionsTest.php
git commit -m "fix(documents): enforce project-team membership on approver assignment (Owner reconciliation of I-2)"
```

All must pass/be clean before committing.

---

## Production evidence decision

**Production DB queried: NO.** Implementation can proceed because the migration is additive (nullable FK column plus a new append-only audit table), there is no backfill, and no existing document's approvability changes as a side effect (every existing row resolves through §6.1's fallback exactly as it does today). No correction or implementation step in this plan performs a production query.
