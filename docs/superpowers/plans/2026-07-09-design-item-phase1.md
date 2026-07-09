# Design Item (Phase 1 — Quản lý công việc thiết kế) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** track design deliverables per project through a client-facing review/revision cycle (kanban board grouped by status), reusing existing `Document`/`DocumentVersion` for file versioning and `EventRecord` for audit history, without disturbing the existing `WorkInstanceStep` checklist system it optionally cross-references.

**Architecture:** new `DesignItem` Eloquent model + migration, `Api/DesignItemController` (JSON API under `/api/zena/design-items`, following the exact conventions of `Api/LeadController`/`Api/OpportunityController`), `Web/DesignItemPageController` (kanban + forms under `/operator/design-items`, delegating to the API controller via the existing `DelegatesToApiControllers` trait), RBAC via a new `DesignItemPolicy` + two new permissions (`design-item.view`, `design-item.manage`).

**Tech Stack:** Laravel (PHP, `declare(strict_types=1)`), Blade views extending `layouts.operator`, SQLite for local/test DB, PHPUnit feature tests.

**Spec reference:** `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 1 section (as revised through §5 and §6).

## Global Constraints

- Every PHP file starts with `<?php declare(strict_types=1);` (matches `Lead.php`, `Opportunity.php`, `LeadController.php`, etc. — every file touched in the CRM slice this repo just shipped).
- Tenant isolation is non-negotiable: every query scopes by `tenant_id`; every test must include a cross-tenant-denial assertion.
- `DesignItem.review_status` is the **sole authority** for the client review cycle. Never read or write `WorkInstanceStep`'s own status/`approveStep` state from any `DesignItem` code path — the FK between them is for cross-reference only (spec §Phase 1, "Authority rule").
- State machine transitions are exactly: `draft → internal_review`; `internal_review → draft | sent_to_client`; `sent_to_client → revision_requested | approved`; `revision_requested → internal_review`; `approved → final | revision_requested`; `final` is terminal. Any other transition is a 422.
- `sent_to_client` requires `due_to_client_at` already set AND at least one attached `Document`. `revision_requested` requires non-empty `client_feedback_notes`. `approved` requires non-empty `approval_evidence`.
- Every `review_status` change writes an `EventRecord` (`aggregate_type = 'design_item'`, `event_key = 'design_item.status_changed'`, payload `{from, to}`).
- **Deviation from the spec, discovered during planning (record this, don't silently "fix" the spec doc):** the spec listed an optional `phase_id` FK to `project_phases`. That table does not exist anywhere in this codebase — `ProjectPhase` is a model class with no backing migration and zero controller usage (verified via `Schema::getColumnListing`/`.tables` inspection during planning). This plan omits `phase_id` entirely. `project_id` alone is sufficient scoping for Phase 1. If `project_phases` ever becomes a real table, adding `phase_id` back is a small follow-up migration, not a blocker now.
- Reuse, do not reinvent: `Document`/`DocumentVersion` for files (via `Document::createNewVersion()`, already exists), `EventRecord` for audit, `CrmPageController::BOARD_GROUPS` kanban-grouping pattern for the board view, `DelegatesToApiControllers` trait for the Web→Api delegation.

---

### Task 1: Migration + `DesignItem` model + state machine

**Files:**
- Create: `database/migrations/2026_07_10_090000_create_design_items_table.php`
- Create: `app/Models/DesignItem.php`
- Test: `tests/Unit/DesignItemStateMachineTest.php`

**Interfaces:**
- Produces: `DesignItem` model with constants `TYPE_CONCEPT`, `TYPE_SCHEMATIC`, `TYPE_TECHNICAL`, `TYPE_STRUCTURAL`, `TYPE_MEP`, `TYPE_INTERIOR`, `TYPE_OTHER`, `VALID_TYPES`; `STATUS_DRAFT`, `STATUS_INTERNAL_REVIEW`, `STATUS_SENT_TO_CLIENT`, `STATUS_REVISION_REQUESTED`, `STATUS_APPROVED`, `STATUS_FINAL`, `VALID_STATUSES`, `TRANSITIONS` (array); `EVIDENCE_PHONE`, `EVIDENCE_EMAIL`, `EVIDENCE_ZALO`, `EVIDENCE_CLIENT_PORTAL`, `VALID_APPROVAL_EVIDENCE`. Static method `DesignItem::canTransition(string $from, string $to): bool`. Relations `project()`, `workInstanceStep()`, `assignee()`, `creator()`. Scope `scopeForTenant($query, string $tenantId)`.

- [ ] **Step 1: Write the failing unit test for the state machine**

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\DesignItem;
use PHPUnit\Framework\TestCase;

class DesignItemStateMachineTest extends TestCase
{
    public function test_forward_chain_transitions_are_allowed(): void
    {
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_INTERNAL_REVIEW));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_INTERNAL_REVIEW, DesignItem::STATUS_SENT_TO_CLIENT));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_SENT_TO_CLIENT, DesignItem::STATUS_APPROVED));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL));
    }

    public function test_loop_back_transitions_are_allowed(): void
    {
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_SENT_TO_CLIENT, DesignItem::STATUS_REVISION_REQUESTED));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_REVISION_REQUESTED, DesignItem::STATUS_INTERNAL_REVIEW));
        $this->assertTrue(DesignItem::canTransition(DesignItem::STATUS_INTERNAL_REVIEW, DesignItem::STATUS_DRAFT));
        $this->assertTrue(
            DesignItem::canTransition(DesignItem::STATUS_APPROVED, DesignItem::STATUS_REVISION_REQUESTED),
            'late change requests after approval must be a valid loop-back, not a dead end'
        );
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_APPROVED));
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_DRAFT, DesignItem::STATUS_FINAL));
        $this->assertFalse(DesignItem::canTransition(DesignItem::STATUS_FINAL, DesignItem::STATUS_DRAFT));
    }

    public function test_final_is_terminal(): void
    {
        $this->assertSame([], DesignItem::TRANSITIONS[DesignItem::STATUS_FINAL]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/DesignItemStateMachineTest.php`
Expected: FAIL — `Class "App\Models\DesignItem" not found`.

- [ ] **Step 3: Write the migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Design Item slice — spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
// NOTE: the spec listed an optional `phase_id` FK to `project_phases`, but that table has no
// backing migration anywhere in this codebase (ProjectPhase model is unused/unbacked — verified
// during planning). Deliberately omitted; project_id alone is enough scoping for now.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id');
            $table->ulid('project_id');
            $table->ulid('work_instance_step_id')->nullable();
            $table->string('name');
            $table->string('item_type')->default('other');
            $table->string('review_status')->default('draft');
            $table->ulid('assigned_to')->nullable();
            $table->date('due_to_client_at')->nullable();
            $table->text('client_feedback_notes')->nullable();
            $table->string('approval_evidence')->nullable();
            $table->ulid('created_by');
            $table->timestamps();

            $table->foreign('tenant_id', 'design_items_tenant_id_foreign')
                ->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('project_id', 'design_items_project_id_foreign')
                ->references('id')->on('projects')->cascadeOnDelete();
            $table->foreign('work_instance_step_id', 'design_items_wi_step_id_foreign')
                ->references('id')->on('work_instance_steps')->nullOnDelete();
            $table->foreign('assigned_to', 'design_items_assigned_to_foreign')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by', 'design_items_created_by_foreign')
                ->references('id')->on('users');

            $table->index(['tenant_id', 'project_id'], 'design_items_tenant_project_index');
            $table->index(['tenant_id', 'review_status'], 'design_items_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_items');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DesignItem — theo dõi công việc thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng.
 * Spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
 *
 * review_status is the sole authority for the client cycle — never synced with
 * WorkInstanceStep's own internal checklist status, even when work_instance_step_id is set.
 */
class DesignItem extends Model
{
    use HasUlids;

    public const TYPE_CONCEPT = 'concept';
    public const TYPE_SCHEMATIC = 'schematic';
    public const TYPE_TECHNICAL = 'technical';
    public const TYPE_STRUCTURAL = 'structural';
    public const TYPE_MEP = 'mep';
    public const TYPE_INTERIOR = 'interior';
    public const TYPE_OTHER = 'other';

    public const VALID_TYPES = [
        self::TYPE_CONCEPT,
        self::TYPE_SCHEMATIC,
        self::TYPE_TECHNICAL,
        self::TYPE_STRUCTURAL,
        self::TYPE_MEP,
        self::TYPE_INTERIOR,
        self::TYPE_OTHER,
    ];

    public const STATUS_DRAFT = 'draft';
    public const STATUS_INTERNAL_REVIEW = 'internal_review';
    public const STATUS_SENT_TO_CLIENT = 'sent_to_client';
    public const STATUS_REVISION_REQUESTED = 'revision_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_FINAL = 'final';

    public const VALID_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_INTERNAL_REVIEW,
        self::STATUS_SENT_TO_CLIENT,
        self::STATUS_REVISION_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_FINAL,
    ];

    /** @var array<string, list<string>> */
    public const TRANSITIONS = [
        self::STATUS_DRAFT => [self::STATUS_INTERNAL_REVIEW],
        self::STATUS_INTERNAL_REVIEW => [self::STATUS_DRAFT, self::STATUS_SENT_TO_CLIENT],
        self::STATUS_SENT_TO_CLIENT => [self::STATUS_REVISION_REQUESTED, self::STATUS_APPROVED],
        self::STATUS_REVISION_REQUESTED => [self::STATUS_INTERNAL_REVIEW],
        self::STATUS_APPROVED => [self::STATUS_FINAL, self::STATUS_REVISION_REQUESTED],
        self::STATUS_FINAL => [],
    ];

    public const EVIDENCE_PHONE = 'phone';
    public const EVIDENCE_EMAIL = 'email';
    public const EVIDENCE_ZALO = 'zalo';
    public const EVIDENCE_CLIENT_PORTAL = 'client_portal';

    public const VALID_APPROVAL_EVIDENCE = [
        self::EVIDENCE_PHONE,
        self::EVIDENCE_EMAIL,
        self::EVIDENCE_ZALO,
        self::EVIDENCE_CLIENT_PORTAL,
    ];

    protected $table = 'design_items';

    protected $fillable = [
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
    ];

    protected $casts = [
        'due_to_client_at' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function workInstanceStep(): BelongsTo
    {
        return $this->belongsTo(WorkInstanceStep::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }
}
```

- [ ] **Step 5: Run migration and the unit test**

Run: `php artisan migrate`
Expected: `2026_07_10_090000_create_design_items_table ... DONE`

Run: `php artisan test tests/Unit/DesignItemStateMachineTest.php`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_07_10_090000_create_design_items_table.php app/Models/DesignItem.php tests/Unit/DesignItemStateMachineTest.php
git commit -m "feat(design-items): add DesignItem model, migration, and state machine"
```

---

### Task 2: `Document::ENTITY_TYPE_DESIGN_ITEM` + `DesignItemPolicy` + RBAC wiring

**Files:**
- Modify: `app/Models/Document.php:55-70` (add constant + `VALID_ENTITY_TYPES` entry)
- Create: `app/Policies/DesignItemPolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php:43` (register policy)
- Modify: `database/seeders/ZenaPermissionsSeeder.php:170-172` (add 2 permissions)
- Modify: `database/seeders/ZenaRbacSeeder.php:83-84` (wire permissions into admin role)
- Test: `tests/Feature/Api/DesignItemApiTest.php` (RBAC-only assertions in this task; more added in later tasks)

**Interfaces:**
- Consumes: `DesignItem` from Task 1.
- Produces: `Document::ENTITY_TYPE_DESIGN_ITEM = 'design_item'`; `App\Policies\DesignItemPolicy` with `viewAny`, `view`, `create`, `update` methods checking `hasPermission('design-item.view'|'design-item.manage')`.

- [ ] **Step 1: Write the failing RBAC test**

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DesignItemApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['design-item.view', 'design-item.manage']);

        $this->projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson($this->route('index'), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ]);

        $response->assertStatus(401);
    }

    public function test_index_denied_without_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenantA, [], ['no_perm'], []);

        $response = $this->getJson($this->route('index'), $this->headersFor($noPerm));

        $response->assertStatus(403);
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.design-items.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('design-item-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: FAIL — route `api.zena.design-items.index` not defined (this is expected; the route lands in Task 7, but this file establishes the test scaffold now so later tasks only add methods to it).

- [ ] **Step 3: Add the entity type constant to `Document`**

In `app/Models/Document.php`, find:
```php
    public const ENTITY_TYPE_SUBMITTAL = 'submittal';

    /**
     * Danh sách các loại entity hợp lệ
     */
    public const VALID_ENTITY_TYPES = [
        self::ENTITY_TYPE_TASK,
        self::ENTITY_TYPE_COMPONENT,
        self::ENTITY_TYPE_DIARY,
        self::ENTITY_TYPE_CR,
        self::ENTITY_TYPE_SUBMITTAL,
    ];
```

Replace with:
```php
    public const ENTITY_TYPE_SUBMITTAL = 'submittal';
    public const ENTITY_TYPE_DESIGN_ITEM = 'design_item';

    /**
     * Danh sách các loại entity hợp lệ
     */
    public const VALID_ENTITY_TYPES = [
        self::ENTITY_TYPE_TASK,
        self::ENTITY_TYPE_COMPONENT,
        self::ENTITY_TYPE_DIARY,
        self::ENTITY_TYPE_CR,
        self::ENTITY_TYPE_SUBMITTAL,
        self::ENTITY_TYPE_DESIGN_ITEM,
    ];
```

- [ ] **Step 4: Write the policy**

```php
<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\DesignItem;
use App\Models\User;

class DesignItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('design-item.view');
    }

    public function view(User $user, DesignItem $designItem): bool
    {
        return $this->belongsToUserTenant($user, $designItem)
            && $user->hasPermission('design-item.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('design-item.manage');
    }

    public function update(User $user, DesignItem $designItem): bool
    {
        return $this->belongsToUserTenant($user, $designItem)
            && $user->hasPermission('design-item.manage');
    }

    private function belongsToUserTenant(User $user, DesignItem $designItem): bool
    {
        return (string) $user->tenant_id === (string) $designItem->tenant_id;
    }
}
```

- [ ] **Step 5: Register the policy**

In `app/Providers/AuthServiceProvider.php`, find:
```php
        'App\Models\Opportunity' => 'App\Policies\OpportunityPolicy',
```

Replace with:
```php
        'App\Models\Opportunity' => 'App\Policies\OpportunityPolicy',
        'App\Models\DesignItem' => 'App\Policies\DesignItemPolicy',
```

- [ ] **Step 6: Add permissions to the seeder**

In `database/seeders/ZenaPermissionsSeeder.php`, find:
```php
        ['code' => 'crm.convert', 'module' => 'crm', 'action' => 'convert', 'description' => 'Convert won opportunities into projects'],

        // Reports
```

Replace with:
```php
        ['code' => 'crm.convert', 'module' => 'crm', 'action' => 'convert', 'description' => 'Convert won opportunities into projects'],

        // Design work management (design-item kanban — spec zena-ops-roadmap Phase 1)
        ['code' => 'design-item.view', 'module' => 'design-item', 'action' => 'view', 'description' => 'View design items and their review status'],
        ['code' => 'design-item.manage', 'module' => 'design-item', 'action' => 'manage', 'description' => 'Create/update design items, change review status, upload files'],

        // Reports
```

- [ ] **Step 7: Wire permissions into the RBAC seeder**

In `database/seeders/ZenaRbacSeeder.php`, find:
```php
            'crm.view', 'crm.manage', 'crm.convert',
            // Alert taxonomy (S6.2)
```

Replace with:
```php
            'crm.view', 'crm.manage', 'crm.convert',
            // Design work management
            'design-item.view', 'design-item.manage',
            // Alert taxonomy (S6.2)
```

- [ ] **Step 8: Run the test again (will still fail on missing route — expected until Task 7)**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: still FAIL (route not registered yet) — this is fine, this task's job was the policy/RBAC scaffold, not the route. Confirm the failure message is specifically about the route/URL, not about the policy or seeder code (i.e., nothing broke).

- [ ] **Step 9: Commit**

```bash
git add app/Models/Document.php app/Policies/DesignItemPolicy.php app/Providers/AuthServiceProvider.php database/seeders/ZenaPermissionsSeeder.php database/seeders/ZenaRbacSeeder.php tests/Feature/Api/DesignItemApiTest.php
git commit -m "feat(design-items): add DesignItemPolicy, permissions, and Document entity type"
```

---

### Task 3: `Api/DesignItemController` — `index` + `store`

**Files:**
- Create: `app/Http/Controllers/Api/DesignItemController.php`
- Modify: `tests/Feature/Api/DesignItemApiTest.php` (add tests, replacing the two RBAC-only tests' route helper is unaffected)

**Interfaces:**
- Consumes: `DesignItem` (Task 1), `ZenaContractResponseTrait`, `BaseApiController` (existing).
- Produces: `DesignItemController::index(Request $request): JsonResponse`, `DesignItemController::store(Request $request): JsonResponse`. Response envelope: `{success, status, data, message}` via `zenaSuccessResponse()`. Serialized fields: `id, tenant_id, project_id, work_instance_step_id, name, item_type, review_status, assigned_to, due_to_client_at, client_feedback_notes, approval_evidence, created_by, created_at, updated_at`.

- [ ] **Step 1: Add failing tests for index and store**

Append to `tests/Feature/Api/DesignItemApiTest.php` (inside the class, after `test_index_denied_without_view_permission`):

```php
    public function test_can_create_and_list_design_items(): void
    {
        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Phoi canh mat tien phuong an 2',
            'item_type' => 'concept',
        ], $this->headersFor($this->userA));

        $response->assertStatus(201)
            ->assertJsonPath('data.review_status', DesignItem::STATUS_DRAFT)
            ->assertJsonPath('data.item_type', 'concept');

        $this->assertDatabaseHas('design_items', [
            'name' => 'Phoi canh mat tien phuong an 2',
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $index = $this->getJson($this->route('index'), $this->headersFor($this->userA));
        $index->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_create_requires_manage_permission(): void
    {
        $viewOnly = $this->createTenantUser($this->tenantA, [], ['viewer'], ['design-item.view']);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Should be denied',
        ], $this->headersFor($viewOnly));

        $response->assertStatus(403);
        $this->assertDatabaseMissing('design_items', ['name' => 'Should be denied']);
    }

    public function test_create_rejects_project_from_another_tenant(): void
    {
        $projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);

        $response = $this->postJson($this->route('store'), [
            'project_id' => (string) $projectB->id,
            'name' => 'Cross tenant project',
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_design_items_are_tenant_isolated(): void
    {
        DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant A item',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('index'), $this->headersFor($this->userB));

        $response->assertStatus(200)->assertJsonCount(0, 'data');
    }
```

Add the `use App\Models\DesignItem;` import at the top of the file if not already present (it is, from Task 2's setUp).

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: FAIL — `Api/DesignItemController` does not exist / route not defined.

- [ ] **Step 3: Write the controller (index + store only)**

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\DesignItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * DesignItem — công việc thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng.
 * Spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 1).
 */
class DesignItemController extends BaseApiController
{
    use ZenaContractResponseTrait;

    /** @var list<string> */
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'project_id',
        'work_instance_step_id',
        'name',
        'item_type',
        'review_status',
        'assigned_to',
        'due_to_client_at',
        'client_feedback_notes',
        'approval_evidence',
        'created_by',
        'created_at',
        'updated_at',
    ];

    private function tenantId(Request $request): string
    {
        $tenantId = $request->attributes->get('tenant_id')
            ?? app('current_tenant_id')
            ?? Auth::user()?->tenant_id;

        return $tenantId ? (string) $tenantId : '';
    }

    private function scopedQuery(string $tenantId): Builder
    {
        return DesignItem::query()->forTenant($tenantId);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(DesignItem $item): array
    {
        return Arr::only($item->attributesToArray(), self::RESPONSE_FIELDS);
    }

    private function rules(string $tenantId): array
    {
        return [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'work_instance_step_id' => [
                'nullable',
                'string',
                Rule::exists('work_instance_steps', 'id')->where('tenant_id', $tenantId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', Rule::in(DesignItem::VALID_TYPES)],
            'assigned_to' => [
                'nullable',
                'string',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'due_to_client_at' => ['nullable', 'date'],
        ];
    }

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('viewAny', DesignItem::class);

        $query = $this->scopedQuery($tenantId);

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('review_status')) {
            $query->where('review_status', (string) $request->input('review_status'));
        }

        $items = $query
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn (DesignItem $item): array => $this->serialize($item))
            ->values();

        return $this->zenaSuccessResponse($items, 'Design items retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $this->authorize('create', DesignItem::class);

        $validator = Validator::make($request->all(), $this->rules($tenantId));

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $item = DesignItem::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $request->input('project_id'),
            'work_instance_step_id' => $request->input('work_instance_step_id'),
            'name' => (string) $request->input('name'),
            'item_type' => (string) $request->input('item_type', DesignItem::TYPE_OTHER),
            'review_status' => DesignItem::STATUS_DRAFT,
            'assigned_to' => $request->input('assigned_to'),
            'due_to_client_at' => $request->input('due_to_client_at'),
            'created_by' => (string) $user->id,
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($item->fresh() ?? $item),
            'Design item created successfully',
            201
        );
    }
}
```

- [ ] **Step 4: Register the route (minimal — just enough for these two tests to pass; the full route set lands in Task 7)**

This step is intentionally deferred — do not add routes yet. Instead, run the tests now against the controller class directly being wired by Task 7's routes. **Skip ahead:** to keep this task's tests runnable in isolation without jumping to Task 7 out of order, add the minimal `index`/`store` routes now in `routes/api_zena.php`. Find:

```php
        Route::group(['prefix' => 'crm'], function () {
```

Insert immediately before it:

```php
        // Design Item (design-item kanban — spec zena-ops-roadmap Phase 1)
        Route::group(['prefix' => 'design-items'], function () {
            Route::get('/', [\App\Http\Controllers\Api\DesignItemController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
            Route::post('/', [\App\Http\Controllers\Api\DesignItemController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
        });

        Route::group(['prefix' => 'crm'], function () {
```

(Task 7 will add the remaining `show`/`update`/`status`/`documents` routes to this same group — do not duplicate the group wrapper then, just add lines inside it.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: PASS (6 tests: unauthenticated, index-denied, create+list, create-denied, cross-tenant-project-rejected, tenant-isolated)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/DesignItemController.php routes/api_zena.php tests/Feature/Api/DesignItemApiTest.php
git commit -m "feat(design-items): add index and store API endpoints"
```

---

### Task 4: `Api/DesignItemController` — `show` + `update`

**Files:**
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (add two methods)
- Modify: `tests/Feature/Api/DesignItemApiTest.php` (add tests)

**Interfaces:**
- Consumes: `rules()`, `scopedQuery()`, `serialize()` from Task 3 (same file, private methods reused as-is).
- Produces: `DesignItemController::show(Request $request, string $id): JsonResponse`, `DesignItemController::update(Request $request, string $id): JsonResponse`. `update` never accepts `review_status` (that's exclusively `updateStatus` in Task 5).

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Api/DesignItemApiTest.php`:

```php
    public function test_can_show_and_update_design_item(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Ban ve ky thuat tang 1',
        ], $this->headersFor($this->userA));

        $itemId = $create->json('data.id');

        $show = $this->getJson($this->route('show', ['id' => $itemId]), $this->headersFor($this->userA));
        $show->assertStatus(200)->assertJsonPath('data.name', 'Ban ve ky thuat tang 1');

        $update = $this->putJson($this->route('update', ['id' => $itemId]), [
            'name' => 'Ban ve ky thuat tang 1 (revised)',
            'item_type' => 'technical',
        ], $this->headersFor($this->userA));

        $update->assertStatus(200)->assertJsonPath('data.item_type', 'technical');
    }

    public function test_show_from_other_tenant_is_not_found(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Tenant A only',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->getJson($this->route('show', ['id' => $item->id]), $this->headersFor($this->userB));

        $response->assertStatus(404);
    }

    public function test_update_does_not_accept_review_status(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Should not skip state machine',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $this->putJson($this->route('update', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $item->refresh();
        $this->assertSame(DesignItem::STATUS_DRAFT, (string) $item->review_status);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: FAIL — `show`/`update` methods and routes don't exist yet.

- [ ] **Step 3: Add the two methods to the controller**

In `app/Http/Controllers/Api/DesignItemController.php`, add after `store()` (before the final closing `}` of the class):

```php
    public function show(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('view', $item);

        return $this->zenaSuccessResponse($this->serialize($item), 'Design item retrieved successfully');
    }

    public function update(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $rules = $this->rules($tenantId);
        $rules['project_id'] = ['sometimes'] + $rules['project_id'];
        $rules['name'] = ['sometimes', 'required', 'string', 'max:255'];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        // review_status is deliberately excluded here — it is only ever changed via updateStatus(),
        // which enforces the transition graph and its side-effect rules. Silently ignore it if sent.
        $item->fill($request->only([
            'project_id', 'work_instance_step_id', 'name', 'item_type', 'assigned_to', 'due_to_client_at',
        ]));
        $item->save();

        return $this->zenaSuccessResponse($this->serialize($item->fresh() ?? $item), 'Design item updated successfully');
    }
```

- [ ] **Step 4: Add the routes**

In `routes/api_zena.php`, find the `design-items` group added in Task 3:

```php
        Route::group(['prefix' => 'design-items'], function () {
            Route::get('/', [\App\Http\Controllers\Api\DesignItemController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
            Route::post('/', [\App\Http\Controllers\Api\DesignItemController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
        });
```

Replace with:

```php
        Route::group(['prefix' => 'design-items'], function () {
            Route::get('/', [\App\Http\Controllers\Api\DesignItemController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
            Route::post('/', [\App\Http\Controllers\Api\DesignItemController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
            Route::get('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'show'])->middleware('rbac:design-item.view')->name('design-items.show');
            Route::put('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'update'])->middleware('rbac:design-item.manage')->name('design-items.update');
        });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: PASS (9 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/DesignItemController.php routes/api_zena.php tests/Feature/Api/DesignItemApiTest.php
git commit -m "feat(design-items): add show and update API endpoints"
```

---

### Task 5: `Api/DesignItemController` — `updateStatus` (state machine enforcement + audit trail)

**Files:**
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (add method + imports)
- Modify: `tests/Feature/Api/DesignItemApiTest.php` (add tests)

**Interfaces:**
- Consumes: `DesignItem::canTransition()` (Task 1), `Document::scopeForEntity()` + `Document::ENTITY_TYPE_DESIGN_ITEM` (Task 2, existing scope on `Document`), `EventRecord` model (existing, fields `tenant_id, project_id, aggregate_type, aggregate_id, event_key, actor_user_id, payload, occurred_at`).
- Produces: `DesignItemController::updateStatus(Request $request, string $id): JsonResponse`.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Api/DesignItemApiTest.php`:

```php
    public function test_full_status_loop_including_late_revision_after_approval(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Full loop item',
        ], $this->headersFor($this->userA));
        $itemId = $create->json('data.id');

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);

        // sent_to_client requires due_to_client_at + an attached document first — set the date via update,
        // attach a document via a direct factory-less DB write (upload endpoint is tested in Task 6).
        DesignItem::query()->whereKey($itemId)->update(['due_to_client_at' => now()->addDays(3)->toDateString()]);
        \App\Models\Document::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'uploaded_by' => (string) $this->userA->id,
            'name' => 'concept.pdf',
            'original_name' => 'concept.pdf',
            'file_path' => 'design-items/test/concept.pdf',
            'file_type' => 'pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 100,
            'file_hash' => 'test-hash',
            'linked_entity_type' => \App\Models\Document::ENTITY_TYPE_DESIGN_ITEM,
            'linked_entity_id' => (string) $itemId,
        ]);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $revision = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Doi mau tuong ngoai that',
        ], $this->headersFor($this->userA));
        $revision->assertStatus(200)->assertJsonPath('data.client_feedback_notes', 'Doi mau tuong ngoai that');

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);

        $approve = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_APPROVED,
            'approval_evidence' => 'zalo',
        ], $this->headersFor($this->userA));
        $approve->assertStatus(200)->assertJsonPath('data.approval_evidence', 'zalo');

        // Late change request after approval — must be allowed, not a dead end.
        $lateRevision = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
            'client_feedback_notes' => 'Khach doi lai sau khi da duyet',
        ], $this->headersFor($this->userA));
        $lateRevision->assertStatus(200);

        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
        ], $this->headersFor($this->userA))->assertStatus(200);
        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(200);
        $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_APPROVED,
            'approval_evidence' => 'email',
        ], $this->headersFor($this->userA))->assertStatus(200);

        $final = $this->postJson($this->route('status', ['id' => $itemId]), [
            'review_status' => DesignItem::STATUS_FINAL,
        ], $this->headersFor($this->userA));
        $final->assertStatus(200)->assertJsonPath('data.review_status', DesignItem::STATUS_FINAL);

        $events = \App\Models\EventRecord::query()
            ->where('aggregate_type', 'design_item')
            ->where('aggregate_id', $itemId)
            ->orderBy('occurred_at')
            ->get();

        $this->assertGreaterThanOrEqual(9, $events->count(), 'every status change must produce an EventRecord');
        $this->assertSame(DesignItem::STATUS_DRAFT, $events->first()->payload['from']);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Invalid transition target',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
        $item->refresh();
        $this->assertSame(DesignItem::STATUS_DRAFT, (string) $item->review_status);
    }

    public function test_sent_to_client_requires_due_date_and_attachment(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'Missing prerequisites',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
            'created_by' => (string) $this->userA->id,
        ]);

        // No due_to_client_at, no attachment yet.
        $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(422);

        $item->update(['due_to_client_at' => now()->addDay()->toDateString()]);

        // Due date set, but still no attachment.
        $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ], $this->headersFor($this->userA))->assertStatus(422);
    }

    public function test_revision_requested_requires_feedback_notes(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'No feedback provided',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_REVISION_REQUESTED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_approved_requires_evidence(): void
    {
        $item = DesignItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => (string) $this->projectA->id,
            'name' => 'No evidence provided',
            'item_type' => DesignItem::TYPE_OTHER,
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson($this->route('status', ['id' => $item->id]), [
            'review_status' => DesignItem::STATUS_APPROVED,
        ], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: FAIL — `updateStatus` method/route doesn't exist yet.

- [ ] **Step 3: Add the method**

In `app/Http/Controllers/Api/DesignItemController.php`, add the following imports at the top (alongside the existing ones):

```php
use App\Models\Document;
use App\Models\EventRecord;
```

Then add this method after `update()`:

```php
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $validator = Validator::make($request->all(), [
            'review_status' => ['required', Rule::in(DesignItem::VALID_STATUSES)],
            'client_feedback_notes' => ['nullable', 'string', 'max:2000'],
            'approval_evidence' => ['nullable', Rule::in(DesignItem::VALID_APPROVAL_EVIDENCE)],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $from = (string) $item->review_status;
        $to = (string) $request->input('review_status');

        if (!DesignItem::canTransition($from, $to)) {
            return $this->validationError([
                'review_status' => ["Cannot transition from {$from} to {$to}."],
            ]);
        }

        if ($to === DesignItem::STATUS_REVISION_REQUESTED && !$request->filled('client_feedback_notes')) {
            return $this->validationError([
                'client_feedback_notes' => ['Required when requesting a revision.'],
            ]);
        }

        if ($to === DesignItem::STATUS_SENT_TO_CLIENT) {
            if (!$item->due_to_client_at) {
                return $this->validationError([
                    'due_to_client_at' => ['Must be set before sending to client.'],
                ]);
            }

            $hasAttachment = Document::query()
                ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
                ->exists();

            if (!$hasAttachment) {
                return $this->validationError([
                    'review_status' => ['At least one attached document is required before sending to client.'],
                ]);
            }
        }

        if ($to === DesignItem::STATUS_APPROVED && !$request->filled('approval_evidence')) {
            return $this->validationError([
                'approval_evidence' => ['Required when approving — record how the client confirmed (phone/email/zalo/client_portal).'],
            ]);
        }

        $item->review_status = $to;

        if ($to === DesignItem::STATUS_REVISION_REQUESTED) {
            $item->client_feedback_notes = (string) $request->input('client_feedback_notes');
        }

        if ($to === DesignItem::STATUS_APPROVED) {
            $item->approval_evidence = (string) $request->input('approval_evidence');
        }

        $item->save();

        EventRecord::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => (string) $item->project_id,
            'aggregate_type' => 'design_item',
            'aggregate_id' => (string) $item->id,
            'event_key' => 'design_item.status_changed',
            'actor_user_id' => (string) Auth::id(),
            'payload' => ['from' => $from, 'to' => $to],
            'occurred_at' => now(),
        ]);

        return $this->zenaSuccessResponse(
            $this->serialize($item->fresh() ?? $item),
            'Design item status updated successfully'
        );
    }
```

- [ ] **Step 4: Add the route**

In `routes/api_zena.php`, find the `design-items` group (from Task 4):

```php
            Route::put('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'update'])->middleware('rbac:design-item.manage')->name('design-items.update');
        });
```

Replace with:

```php
            Route::put('/{id}', [\App\Http\Controllers\Api\DesignItemController::class, 'update'])->middleware('rbac:design-item.manage')->name('design-items.update');
            Route::post('/{id}/status', [\App\Http\Controllers\Api\DesignItemController::class, 'updateStatus'])->middleware('rbac:design-item.manage')->name('design-items.status');
        });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: PASS (14 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/DesignItemController.php routes/api_zena.php tests/Feature/Api/DesignItemApiTest.php
git commit -m "feat(design-items): add updateStatus with full transition graph and audit trail"
```

---

### Task 6: `Api/DesignItemController` — file attach/version endpoints

**Files:**
- Modify: `app/Http/Controllers/Api/DesignItemController.php` (add two methods + imports)
- Modify: `tests/Feature/Api/DesignItemApiTest.php` (add tests)

**Interfaces:**
- Consumes: `Document::createNewVersion(array $versionData): DocumentVersion` (existing method on `Document`, already in this codebase — auto-increments `version_number`, updates `current_version_id`).
- Produces: `DesignItemController::uploadDocument(Request $request, string $id): JsonResponse`, `DesignItemController::listDocuments(Request $request, string $id): JsonResponse`.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Api/DesignItemApiTest.php`:

```php
    public function test_can_upload_and_list_document_versions(): void
    {
        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'Upload target',
        ], $this->headersFor($this->userA));
        $itemId = $create->json('data.id');

        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('concept-v1.pdf', 50, 'application/pdf');

        $upload1 = $this->post($this->route('documents.store', ['id' => $itemId]), [
            'file' => $file,
        ], $this->headersFor($this->userA));

        $upload1->assertStatus(201)->assertJsonPath('data.version_number', 1);

        $file2 = \Illuminate\Http\UploadedFile::fake()->create('concept-v2.pdf', 60, 'application/pdf');

        $upload2 = $this->post($this->route('documents.store', ['id' => $itemId]), [
            'file' => $file2,
            'comment' => 'Cap nhat theo phan hoi khach',
        ], $this->headersFor($this->userA));

        $upload2->assertStatus(201)->assertJsonPath('data.version_number', 2);

        $list = $this->getJson($this->route('documents.index', ['id' => $itemId]), $this->headersFor($this->userA));
        $list->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_upload_requires_manage_permission(): void
    {
        $viewOnly = $this->createTenantUser($this->tenantA, [], ['viewer'], ['design-item.view']);

        $create = $this->postJson($this->route('store'), [
            'project_id' => (string) $this->projectA->id,
            'name' => 'RBAC upload target',
        ], $this->headersFor($this->userA));
        $itemId = $create->json('data.id');

        \Illuminate\Support\Facades\Storage::fake('local');
        $file = \Illuminate\Http\UploadedFile::fake()->create('blocked.pdf', 10, 'application/pdf');

        $response = $this->post($this->route('documents.store', ['id' => $itemId]), [
            'file' => $file,
        ], $this->headersFor($viewOnly));

        $response->assertStatus(403);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: FAIL — `uploadDocument`/`listDocuments` methods and routes don't exist yet.

- [ ] **Step 3: Add the two methods**

In `app/Http/Controllers/Api/DesignItemController.php`, add these imports:

```php
use App\Models\DocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
```

Add these methods after `updateStatus()`:

```php
    public function uploadDocument(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('update', $item);

        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'max:10240'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $directory = sprintf('design-items/%s', $item->id);
        $storedFilename = (string) Str::ulid() . '.' . $file->getClientOriginalExtension();
        $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedFilename);

        if ($storedPath === false) {
            return $this->serverError('Failed to store file');
        }

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        if (!$document instanceof Document) {
            $document = Document::query()->create([
                'tenant_id' => $tenantId,
                'project_id' => (string) $item->project_id,
                'uploaded_by' => (string) $user->id,
                'created_by' => (string) $user->id,
                'name' => (string) $file->getClientOriginalName(),
                'original_name' => (string) $file->getClientOriginalName(),
                'title' => (string) $item->name,
                'file_path' => $storedPath,
                'file_type' => (string) $file->getClientOriginalExtension(),
                'mime_type' => (string) $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'file_hash' => (string) (hash_file('sha256', $file->getRealPath()) ?: Str::random(32)),
                'linked_entity_type' => Document::ENTITY_TYPE_DESIGN_ITEM,
                'linked_entity_id' => (string) $item->id,
                'status' => 'active',
                'visibility' => Document::VISIBILITY_INTERNAL,
            ]);
        }

        $version = $document->createNewVersion([
            'file_path' => $storedPath,
            'storage_driver' => DocumentVersion::STORAGE_LOCAL,
            'comment' => $request->input('comment'),
            'metadata' => [
                'original_filename' => (string) $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ],
            'created_by' => (string) $user->id,
        ]);

        return $this->zenaSuccessResponse([
            'document_id' => (string) $document->id,
            'version_id' => (string) $version->id,
            'version_number' => $version->version_number,
        ], 'File uploaded successfully', 201);
    }

    public function listDocuments(Request $request, string $id): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $item = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$item instanceof DesignItem) {
            return $this->notFound('Design item not found');
        }

        $this->authorize('view', $item);

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        $versions = $document
            ? $document->versions()->get(['id', 'document_id', 'version_number', 'comment', 'created_by', 'created_at'])
            : collect();

        return $this->zenaSuccessResponse($versions, 'Document versions retrieved successfully');
    }
```

- [ ] **Step 4: Add the routes**

In `routes/api_zena.php`, find the `design-items` group (from Task 5):

```php
            Route::post('/{id}/status', [\App\Http\Controllers\Api\DesignItemController::class, 'updateStatus'])->middleware('rbac:design-item.manage')->name('design-items.status');
        });
```

Replace with:

```php
            Route::post('/{id}/status', [\App\Http\Controllers\Api\DesignItemController::class, 'updateStatus'])->middleware('rbac:design-item.manage')->name('design-items.status');
            Route::post('/{id}/documents', [\App\Http\Controllers\Api\DesignItemController::class, 'uploadDocument'])->middleware('rbac:design-item.manage')->name('design-items.documents.store');
            Route::get('/{id}/documents', [\App\Http\Controllers\Api\DesignItemController::class, 'listDocuments'])->middleware('rbac:design-item.view')->name('design-items.documents.index');
        });
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/DesignItemApiTest.php`
Expected: PASS (16 tests total)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/DesignItemController.php routes/api_zena.php tests/Feature/Api/DesignItemApiTest.php
git commit -m "feat(design-items): add document attach/version-list API endpoints"
```

---

### Task 7: `DelegatesToApiControllers` file-forwarding support (small, shared prerequisite for Task 8)

**Files:**
- Modify: `app/Http/Controllers/Web/Concerns/DelegatesToApiControllers.php`

**Interfaces:**
- Produces: `buildApiRequest(Request $request, array $payload = [], array $files = []): Request` — adds an optional third parameter. Every existing call site (`CrmPageController`) calls this with 2 args and is unaffected.

**Why this task exists:** the trait's `buildApiRequest()` currently hardcodes `[]` for the `$files` argument to `Request::create()`, so no existing Web→Api delegation can forward an uploaded file. Task 8 needs to forward a `file` upload from the Web layer to `Api\DesignItemController::uploadDocument()`. This is a minimal, backward-compatible addition to shared infrastructure — not a new pattern.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/DelegatesToApiControllersFileForwardingTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class DelegatesToApiControllersFileForwardingTest extends TestCase
{
    public function test_build_api_request_forwards_files(): void
    {
        $harness = new class {
            use DelegatesToApiControllers;

            public function callBuild(Request $request, array $payload, array $files): Request
            {
                return $this->buildApiRequest($request, $payload, $files);
            }
        };

        $original = Request::create('/test', 'POST');
        $file = UploadedFile::fake()->create('a.pdf', 10);

        $rebuilt = $harness->callBuild($original, ['comment' => 'hi'], ['file' => $file]);

        $this->assertTrue($rebuilt->hasFile('file'));
        $this->assertSame('hi', $rebuilt->input('comment'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/DelegatesToApiControllersFileForwardingTest.php`
Expected: FAIL — `buildApiRequest()` does not accept a third argument (arity mismatch), or `hasFile('file')` is false.

- [ ] **Step 3: Modify the trait**

In `app/Http/Controllers/Web/Concerns/DelegatesToApiControllers.php`, find:

```php
    private function buildApiRequest(Request $request, array $payload = []): Request
    {
        $apiRequest = Request::create(
            $request->fullUrl(),
            $request->method(),
            $payload,
            $request->cookies->all(),
            [],
            $request->server->all()
        );
```

Replace with:

```php
    private function buildApiRequest(Request $request, array $payload = [], array $files = []): Request
    {
        $apiRequest = Request::create(
            $request->fullUrl(),
            $request->method(),
            $payload,
            $request->cookies->all(),
            $files,
            $request->server->all()
        );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Unit/DelegatesToApiControllersFileForwardingTest.php`
Expected: PASS

- [ ] **Step 5: Run the full CRM test suite to confirm no regression (this trait is shared)**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS (all previously-passing tests still pass — the new third parameter is optional and defaults to `[]`, identical to the old hardcoded behavior)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Web/Concerns/DelegatesToApiControllers.php tests/Unit/DelegatesToApiControllersFileForwardingTest.php
git commit -m "fix(web): allow DelegatesToApiControllers to forward uploaded files"
```

---

### Task 8: `Web/DesignItemPageController` — kanban, create, show, status, upload

**Files:**
- Create: `app/Http/Controllers/Web/DesignItemPageController.php`
- Create: `resources/views/design-items/index.blade.php`
- Create: `resources/views/design-items/create.blade.php`
- Create: `resources/views/design-items/show.blade.php`
- Create: `tests/Feature/Zena/OperatorDesignItemUiTest.php`

**Interfaces:**
- Consumes: `Api\DesignItemController` (Tasks 3-6), `DelegatesToApiControllers::buildApiRequest()` (Task 7, now with `$files` support), `DesignItem::BOARD_GROUPS`-equivalent local constant modeled on `CrmPageController::BOARD_GROUPS`.
- Produces: `DesignItemPageController::index/create/store/show/updateStatus/uploadDocument`.

- [ ] **Step 1: Write the failing UI test (full flow)**

Create `tests/Feature/Zena/OperatorDesignItemUiTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorDesignItemUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['design-item.view', 'design-item.manage']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    public function test_design_item_ui_full_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk()
            ->assertSee('Công việc thiết kế');

        $this->actingAs($this->user)
            ->get(route('operator.design-items.create'), $headers)
            ->assertOk()
            ->assertSee('Tạo công việc thiết kế mới');

        $create = $this->actingAs($this->user)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Phoi canh mat tien',
                'item_type' => 'concept',
            ], $headers);

        $item = DesignItem::query()->firstOrFail();
        $create->assertRedirect(route('operator.design-items.show', $item->id));
        $create->assertSessionHas('success', 'Đã tạo công việc thiết kế');

        $this->actingAs($this->user)
            ->get(route('operator.design-items.show', $item->id), $headers)
            ->assertOk()
            ->assertSee('Phoi canh mat tien');

        $toInternalReview = $this->actingAs($this->user)
            ->post(route('operator.design-items.status', $item->id), [
                'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
            ], $headers);
        $toInternalReview->assertRedirect();
        $toInternalReview->assertSessionHas('success', 'Đã cập nhật trạng thái');

        $item->update(['due_to_client_at' => now()->addDays(2)->toDateString()]);

        Storage::fake('local');
        $upload = $this->actingAs($this->user)
            ->post(route('operator.design-items.documents.store', $item->id), [
                'file' => UploadedFile::fake()->create('concept.pdf', 40, 'application/pdf'),
            ], $headers);
        $upload->assertRedirect();
        $upload->assertSessionHas('success', 'Đã tải file lên');

        $toSentToClient = $this->actingAs($this->user)
            ->post(route('operator.design-items.status', $item->id), [
                'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            ], $headers);
        $toSentToClient->assertSessionHas('success', 'Đã cập nhật trạng thái');

        $item->refresh();
        $this->assertSame(DesignItem::STATUS_SENT_TO_CLIENT, (string) $item->review_status);
    }

    public function test_design_item_pages_require_authentication(): void
    {
        $this->get(route('operator.design-items.index'))->assertRedirect();
    }

    public function test_design_item_actions_denied_without_manage_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['design-item.view']);

        $this->actingAs($viewer)
            ->get(route('operator.design-items.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.design-items.store'), [
                'project_id' => (string) $this->project->id,
                'name' => 'Should be denied',
            ], $headers)
            ->assertForbidden();

        $this->assertDatabaseMissing('design_items', ['name' => 'Should be denied']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Zena/OperatorDesignItemUiTest.php`
Expected: FAIL — routes/controller/views don't exist yet.

- [ ] **Step 3: Write the Web controller**

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\DesignItemController as ApiDesignItemController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Document;
use App\Models\DesignItem;
use App\Models\EventRecord;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class DesignItemPageController extends Controller
{
    use DelegatesToApiControllers;

    /** Nhóm review_status thành cột kanban. */
    private const BOARD_GROUPS = [
        'Nháp' => [DesignItem::STATUS_DRAFT],
        'Đang duyệt nội bộ' => [DesignItem::STATUS_INTERNAL_REVIEW],
        'Đã gửi khách' => [DesignItem::STATUS_SENT_TO_CLIENT],
        'Khách yêu cầu sửa' => [DesignItem::STATUS_REVISION_REQUESTED],
        'Đã duyệt' => [DesignItem::STATUS_APPROVED],
        'Hoàn tất' => [DesignItem::STATUS_FINAL],
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DesignItem::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        $items = DesignItem::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name', 'assignee:id,name')
            ->orderByDesc('updated_at')
            ->get();

        $board = [];
        foreach (self::BOARD_GROUPS as $label => $statuses) {
            $filtered = $items->whereIn('review_status', $statuses)->values();
            $board[$label] = [
                'items' => $filtered,
                'count' => $filtered->count(),
            ];
        }

        return view('design-items.index', ['board' => $board]);
    }

    public function create(): View
    {
        $this->authorize('create', DesignItem::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        return view('design-items.create', [
            'projects' => Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ApiDesignItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'work_instance_step_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'string'],
            'due_to_client_at' => ['nullable', 'date'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest(
                $request,
                array_filter($validated, fn ($value) => $value !== null && $value !== '')
            ));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $itemId = $response->getData(true)['data']['id'] ?? null;

            return redirect()->route('operator.design-items.show', $itemId)->with('success', 'Đã tạo công việc thiết kế');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $item = DesignItem::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name', 'assignee:id,name')
            ->findOrFail($id);

        $this->authorize('view', $item);

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        return view('design-items.show', [
            'item' => $item,
            'versions' => $document ? $document->versions()->with('creator:id,name')->get() : collect(),
            'users' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'events' => EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'design_item')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateStatus(Request $request, string $id, ApiDesignItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', 'string'],
            'client_feedback_notes' => ['nullable', 'string', 'max:2000'],
            'approval_evidence' => ['nullable', 'string'],
        ]);

        try {
            $response = $apiController->updateStatus(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null && $value !== '')),
                $id
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã cập nhật trạng thái');
    }

    public function uploadDocument(Request $request, string $id, ApiDesignItemController $apiController): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $response = $apiController->uploadDocument(
                $this->buildApiRequest($request, ['comment' => $request->input('comment')], ['file' => $request->file('file')]),
                $id
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã tải file lên');
    }
}
```

- [ ] **Step 4: Write the kanban index view**

```blade
@extends('layouts.operator')

@section('title', 'Công việc thiết kế')
@section('page_title', 'Công việc thiết kế')

@section('content')
    <x-ui.page-header
        title="Công việc thiết kế"
        description="Theo dõi deliverable thiết kế qua vòng duyệt nội bộ và phản hồi khách hàng."
    >
        <x-ui.button-link :href="route('operator.design-items.create')" variant="primary">Tạo công việc mới</x-ui.button-link>
    </x-ui.page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($board as $label => $column)
            <x-ui.card>
                <div class="mb-3 flex items-center justify-between">
                    <span class="font-semibold text-slate-900">{{ $label }}</span>
                    <span class="text-sm text-slate-500">{{ $column['count'] }}</span>
                </div>

                @if ($column['items']->isEmpty())
                    <p class="text-sm text-slate-400">Trống</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($column['items'] as $item)
                            <li class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                                <a href="{{ route('operator.design-items.show', $item->id) }}" class="operator-link font-medium">
                                    {{ $item->name }}
                                </a>
                                <div class="text-xs text-slate-500">
                                    {{ $item->project?->name ?? '—' }}
                                    · {{ $item->assignee?->name ?? 'Chưa gán' }}
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-ui.card>
        @endforeach
    </div>
@endsection
```

- [ ] **Step 5: Write the create form view**

```blade
@extends('layouts.operator')

@section('title', 'Tạo công việc thiết kế')
@section('page_title', 'Tạo công việc thiết kế mới')

@section('content')
    <x-ui.page-header title="Tạo công việc thiết kế mới">
        <x-ui.button-link :href="route('operator.design-items.index')" variant="secondary">Quay lại bảng</x-ui.button-link>
    </x-ui.page-header>

    <x-ui.card title="Thông tin công việc">
        @if ($errors->any())
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('operator.design-items.store') }}" class="space-y-4">
            @csrf
            <div class="operator-form-grid">
                <div class="operator-field">
                    <label for="project_id">Dự án <span class="text-rose-600">*</span></label>
                    <select id="project_id" name="project_id" class="operator-select" required>
                        <option value="">-- Chọn dự án --</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected(old('project_id') === $project->id)>{{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field">
                    <label for="item_type">Loại</label>
                    <select id="item_type" name="item_type" class="operator-select">
                        @foreach (['concept' => 'Ý tưởng', 'schematic' => 'Sơ bộ', 'technical' => 'Kỹ thuật', 'structural' => 'Kết cấu', 'mep' => 'MEP', 'interior' => 'Nội thất', 'other' => 'Khác'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('item_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="operator-field">
                <label for="name">Tên công việc <span class="text-rose-600">*</span></label>
                <input id="name" name="name" type="text" class="operator-input" value="{{ old('name') }}" required placeholder="vd: Phối cảnh mặt tiền phương án 2">
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tạo</button>
        </form>
    </x-ui.card>
@endsection
```

- [ ] **Step 6: Write the show/detail view**

```blade
@extends('layouts.operator')

@section('title', $item->name . ' — Công việc thiết kế')
@section('page_title', 'Chi tiết công việc thiết kế')

@php
    $statusLabels = [
        'draft' => 'Nháp', 'internal_review' => 'Đang duyệt nội bộ', 'sent_to_client' => 'Đã gửi khách',
        'revision_requested' => 'Khách yêu cầu sửa', 'approved' => 'Đã duyệt', 'final' => 'Hoàn tất',
    ];
    $evidenceLabels = ['phone' => 'Điện thoại', 'email' => 'Email', 'zalo' => 'Zalo', 'client_portal' => 'Cổng khách hàng'];
@endphp

@section('content')
    <x-ui.page-header title="{{ $item->name }}" description="Dự án: {{ $item->project?->name ?? '—' }}">
        <x-ui.button-link :href="route('operator.design-items.index')" variant="secondary">Bảng công việc</x-ui.button-link>
    </x-ui.page-header>

    @if ($errors->any())
        <x-ui.card>
            <div class="operator-error-list">
                <ul class="space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card title="Thông tin">
        <div class="operator-form-grid">
            <x-ui.field-value label="Trạng thái">
                <x-ui.status-badge :status="$item->review_status" />
                <span class="ml-1 text-sm text-slate-600">{{ $statusLabels[$item->review_status] ?? '' }}</span>
            </x-ui.field-value>
            <x-ui.field-value label="Loại" :value="$item->item_type" />
            <x-ui.field-value label="Người phụ trách" :value="$item->assignee?->name ?? '—'" />
            <x-ui.field-value label="Hạn giao khách" :value="optional($item->due_to_client_at)->format('d/m/Y') ?? '—'" />
            @if ($item->client_feedback_notes)
                <x-ui.field-value label="Phản hồi khách" :value="$item->client_feedback_notes" />
            @endif
            @if ($item->approval_evidence)
                <x-ui.field-value label="Bằng chứng duyệt" :value="$evidenceLabels[$item->approval_evidence] ?? $item->approval_evidence" />
            @endif
        </div>
    </x-ui.card>

    @unless ($item->review_status === 'final')
        <x-ui.card title="Chuyển trạng thái">
            <form method="POST" action="{{ route('operator.design-items.status', $item->id) }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div class="operator-field flex-1 min-w-64">
                    <label for="review_status">Trạng thái mới</label>
                    <select id="review_status" name="review_status" class="operator-select">
                        @foreach ($statusLabels as $value => $label)
                            <option value="{{ $value }}" @selected($item->review_status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="operator-field flex-1 min-w-64">
                    <label for="client_feedback_notes">Phản hồi khách (bắt buộc nếu yêu cầu sửa)</label>
                    <input id="client_feedback_notes" name="client_feedback_notes" type="text" class="operator-input" value="{{ old('client_feedback_notes') }}">
                </div>
                <div class="operator-field flex-1 min-w-64">
                    <label for="approval_evidence">Bằng chứng duyệt (bắt buộc nếu duyệt)</label>
                    <select id="approval_evidence" name="approval_evidence" class="operator-select">
                        <option value="">--</option>
                        @foreach ($evidenceLabels as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
            </form>
        </x-ui.card>
    @endunless

    <x-ui.card title="File đính kèm">
        <form method="POST" action="{{ route('operator.design-items.documents.store', $item->id) }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="operator-field flex-1 min-w-64">
                <label for="file">Chọn file</label>
                <input id="file" name="file" type="file" class="operator-input" required>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Tải lên</button>
        </form>

        @if ($versions->isEmpty())
            <p class="mt-3 text-sm text-slate-400">Chưa có file nào.</p>
        @else
            <ul class="mt-3 space-y-1">
                @foreach ($versions as $version)
                    <li class="text-sm">Version {{ $version->version_number }} — {{ $version->creator?->name ?? '—' }} · {{ optional($version->created_at)->format('d/m/Y H:i') }}</li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-ui.card title="Lịch sử">
        @if ($events->isEmpty())
            <p class="text-sm text-slate-500">Chưa có sự kiện.</p>
        @else
            <ul class="space-y-2">
                @foreach ($events as $event)
                    <li class="text-sm">
                        <span class="font-medium text-slate-900">{{ $event->event_key }}</span>
                        <span class="text-slate-500">— {{ $event->actor?->name ?? 'Hệ thống' }} · {{ optional($event->occurred_at)->format('d/m/Y H:i') }}</span>
                        @if (($event->payload['from'] ?? null) && ($event->payload['to'] ?? null))
                            <span class="text-slate-500">({{ $statusLabels[$event->payload['from']] ?? $event->payload['from'] }} → {{ $statusLabels[$event->payload['to']] ?? $event->payload['to'] }})</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>
@endsection
```

- [ ] **Step 7: Add the web routes**

In `routes/web.php`, find (inside the `operator` group, right before the closing `});` of that group — the same anchor used when CRM routes were added):

```php
    // CRM (lead inbox → account/opportunity → project; spec crm-zena)
```

Insert immediately before it:

```php
    // Design Item (design-item kanban — spec zena-ops-roadmap Phase 1)
    Route::get('/design-items', [App\Http\Controllers\Web\DesignItemPageController::class, 'index'])->middleware('rbac:design-item.view')->name('design-items.index');
    Route::get('/design-items/create', [App\Http\Controllers\Web\DesignItemPageController::class, 'create'])->middleware('rbac:design-item.manage')->name('design-items.create');
    Route::post('/design-items', [App\Http\Controllers\Web\DesignItemPageController::class, 'store'])->middleware('rbac:design-item.manage')->name('design-items.store');
    Route::get('/design-items/{id}', [App\Http\Controllers\Web\DesignItemPageController::class, 'show'])->middleware('rbac:design-item.view')->name('design-items.show');
    Route::post('/design-items/{id}/status', [App\Http\Controllers\Web\DesignItemPageController::class, 'updateStatus'])->middleware('rbac:design-item.manage')->name('design-items.status');
    Route::post('/design-items/{id}/documents', [App\Http\Controllers\Web\DesignItemPageController::class, 'uploadDocument'])->middleware('rbac:design-item.manage')->name('design-items.documents.store');

    // CRM (lead inbox → account/opportunity → project; spec crm-zena)
```

- [ ] **Step 8: Add the nav link**

In `resources/views/layouts/operator.blade.php`, find:

```php
                    <span>Công việc</span>
                </a>
                <a href="{{ route('app.calendar') }}"
```

Replace with:

```php
                    <span>Công việc</span>
                </a>
                <a href="{{ route('operator.design-items.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.design-items.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span>Công việc thiết kế</span>
                </a>
                <a href="{{ route('app.calendar') }}"
```

- [ ] **Step 9: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Zena/OperatorDesignItemUiTest.php`
Expected: PASS (3 tests)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Web/DesignItemPageController.php resources/views/design-items routes/web.php resources/views/layouts/operator.blade.php tests/Feature/Zena/OperatorDesignItemUiTest.php
git commit -m "feat(design-items): add kanban board, create/show pages, and nav entry"
```

---

### Task 9: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including the pre-existing 1342 (+ this plan's ~24 new tests across `DesignItemStateMachineTest`, `DesignItemApiTest`, `DelegatesToApiControllersFileForwardingTest`, `OperatorDesignItemUiTest`).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. `Api\DesignItemController` → `Models`/`Policies` and `Web\DesignItemPageController` → `Api`/`Models` must already satisfy the existing ruleset in `deptrac.yaml` (same shape as the CRM slice) — if a violation appears, it means a dependency was drawn in the wrong direction; fix the direction, don't add a new `skip_violations` entry.

- [ ] **Step 3: Manually verify the RBAC seeders are idempotent**

Run: `php artisan db:seed --class=ZenaPermissionsSeeder --force && php artisan db:seed --class=ZenaRbacSeeder --force`
Expected: both run without error (they use `updateOrCreate`/`firstOrCreate`, safe to re-run).

- [ ] **Step 4: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(design-items): confirm full suite and Deptrac are green"
```

(Skip this commit if steps 1-3 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 1 section):
- Data model, state machine with loop-backs, authority rule vs. `WorkInstanceStep`, audit trail, file versioning via `Document`/`DocumentVersion`, `approval_evidence` requirement, attachment-before-`sent_to_client` requirement, permissions, kanban UI, nav entry, both test files — all covered by Tasks 1-8.
- The one deliberate deviation (dropping `phase_id`) is called out in Global Constraints and in Task 1's migration comment, not silently done.
- Open question the spec left for the implementer ("fixed enum vs free text for `item_type`") is resolved here as a fixed enum (`Rule::in(DesignItem::VALID_TYPES)`), matching the `Opportunity::VALID_SERVICE_CATEGORIES` precedent the spec pointed at.

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code.

**Type/signature consistency check:** `DesignItem::canTransition(string, string): bool` (Task 1) is called identically in Task 5's `updateStatus()`. `Document::ENTITY_TYPE_DESIGN_ITEM` (Task 2) is used identically in Tasks 5, 6, and 8. `buildApiRequest($request, $payload, $files)` (Task 7) matches its only two call sites needing files (Task 8's `uploadDocument`) and is backward-compatible with the CRM slice's existing 2-argument calls. Route names (`design-items.index/store/show/update/status/documents.store/documents.index` for API; `operator.design-items.index/create/store/show/status/documents.store` for Web) are used consistently between the route-registration steps and the test files that call `route(...)`/`$this->route(...)`.
