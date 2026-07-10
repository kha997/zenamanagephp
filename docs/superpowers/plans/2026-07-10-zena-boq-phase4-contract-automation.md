# Phase 4 — Tự động hoá hợp đồng Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** when an Opportunity is WON and its linked zena-boq-core quote is `ACCEPTED`, let staff generate a draft `Contract` pre-filled from the quote total, with a downloadable PDF, in one action — auto-converting the Opportunity to a Project first if that hasn't happened yet.

**Architecture:** One new API endpoint (`Api\OpportunityController::createContract()`) performs, in order: eligibility check, duplicate-guard, auto-convert-if-needed, `Contract::create()` with three new pinned columns. A Web delegate wires it into a new "Hợp đồng" card on the Opportunity page. A separate new `Api\ContractController::pdf()` endpoint renders a fixed Blade view to HTML and converts it via the already-existing `DeliverablePdfExportService`, streamed back as a direct download — mirroring `WorkInstanceController`'s existing export pattern exactly, no new storage infrastructure.

**Tech Stack:** Laravel 12, existing `DeliverablePdfExportService` (Node/Playwright-backed HTML→PDF), existing `Contract`/`Project`/`Opportunity` models and policies.

## Global Constraints

- **Three new nullable columns on `contracts`**: `source_opportunity_id`, `source_quote_id` (string), `source_quote_revision` (integer) — plain nullable columns, no DB foreign-key constraint, matching the existing `converted_project_id`/`external_boq_project_code` convention in this codebase (soft references, not enforced FKs).
- **Auto-convert first.** If the Opportunity has no `converted_project_id`, `createContract()` converts it to a `Project` first (same logic as the existing `convert()` action), then creates the `Contract` under that project — one action end-to-end.
- **Permission layering — this is the security-critical part of this phase.** The route itself is gated by `rbac:crm.manage` (matching the BOQ card's existing pattern), but the endpoint body must ALSO call `$this->authorize('convert', $opportunity)` before auto-converting (mirrors the existing `convert()` action's own gate — `OpportunityPolicy::convert()` requires the distinct `crm.convert` permission) and `$this->authorize('create', Contract::class)` before creating the `Contract` (mirrors `ContractPolicy::create()`, which requires `contract.create`). A `crm.manage`-only user without `crm.convert`/`contract.create` must get a 403 from these explicit checks, not silently bypass them.
- **Duplicate-guard:** `Contract::where('source_opportunity_id', $opportunity->id)->exists()` — checked before doing anything else (before auto-convert, before creating). If found, return the existing contract's id/code instead of creating a second one.
- **Eligibility:** `pipeline_stage === Opportunity::STAGE_WON` AND `external_quote_snapshot['status'] === 'ACCEPTED'` (read from the array-cast JSON column). Both must hold; return a validation error otherwise, matching this codebase's existing `validationError()` convention.
- **`Contract` field auto-fill:** `client_name` = `$opportunity->account->display_name`; `total_value` = `$opportunity->external_quote_snapshot['total']`; `currency = 'VND'` (explicit override of the model's `USD` default — this app is entirely VND-denominated elsewhere); `code` = `'CTR-' . Str::upper(Str::random(8))` with a retry-then-ULID-fallback loop, mirroring `Project::generateCode()`'s exact collision-avoidance pattern (see Task 2, Step 3, for the literal code); `status` stays the model default (`draft`); `title` = `'Hợp đồng dịch vụ - ' . $opportunity->account->display_name`.
- **Drift-guard is computed live, never stored.** Compare the `Contract`'s pinned `source_quote_id`/`source_quote_revision` against the parent Opportunity's *current* `external_quote_id`/`external_quote_snapshot['revision']` at render time, on both the Opportunity page's new "Hợp đồng" card and the Contract show page. `Contract.total_value` and the pinned `source_quote_id`/`source_quote_revision` are NEVER auto-updated by a re-sync — no code path in this plan writes to those fields after creation.
- **PDF is a fixed Blade view, not an editable template.** `resources/views/contracts/pdf.blade.php` is rendered via `view(...)->render()` to an HTML string, then passed to the existing `DeliverablePdfExportService::render()` — no new `DeliverableTemplate`/`DeliverableTemplateVersion` rows, no template-editing UI.
- **PDF is generated on demand and never persisted** — streamed back as `Content-Disposition: attachment`, exactly like `WorkInstanceController`'s existing deliverable-export endpoint. No `Document` model entry, no disk write.
- `declare(strict_types=1)` at the top of every PHP file touched.
- Every test that reaches `DeliverablePdfExportService::render()` must use the same test-double/subclass-override pattern already established in `tests/Unit/Services/DeliverablePdfExportServiceTest.php` (override the protected `runCommand()`/`ensureDependenciesAvailable()` methods) — never invoke real Node/Playwright in a test.

---

### Task 1: Migration + `Contract` model fields

**Files:**
- Create: `database/migrations/2026_07_10_120000_add_source_fields_to_contracts_table.php`
- Modify: `app/Models/Contract.php`
- Test: `tests/Unit/Models/ContractSourceFieldsTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Contract::$fillable` gains `source_opportunity_id`, `source_quote_id`, `source_quote_revision`; `Contract::$casts` gains `source_quote_revision => integer`. Task 2 writes these three fields at creation time; Task 3/4 read them for the duplicate-guard and drift-guard.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Models/ContractSourceFieldsTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractSourceFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_contract_can_store_source_opportunity_and_quote_fields(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an test',
            'code' => 'PRJ-TESTSRC1',
            'status' => 'planning',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-TESTSRC1',
            'title' => 'Hop dong test',
            'source_opportunity_id' => 'opp_123',
            'source_quote_id' => 'quote_123',
            'source_quote_revision' => 3,
        ]);

        $contract->refresh();

        $this->assertSame('opp_123', $contract->source_opportunity_id);
        $this->assertSame('quote_123', $contract->source_quote_id);
        $this->assertSame(3, $contract->source_quote_revision);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/Models/ContractSourceFieldsTest.php`
Expected: FAIL — `source_opportunity_id` column doesn't exist yet (SQL error) and/or the fields aren't fillable.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_10_120000_add_source_fields_to_contracts_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->string('source_opportunity_id')->nullable()->after('project_id');
            $table->string('source_quote_id')->nullable()->after('source_opportunity_id');
            $table->unsignedInteger('source_quote_revision')->nullable()->after('source_quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table): void {
            $table->dropColumn(['source_opportunity_id', 'source_quote_id', 'source_quote_revision']);
        });
    }
};
```

- [ ] **Step 4: Run migrations**

Run: `php artisan migrate`
Expected: migration runs clean, no errors.

- [ ] **Step 5: Update the `Contract` model**

In `app/Models/Contract.php`, find:

```php
    protected $fillable = [
        'tenant_id',
        'project_id',
        'code',
        'contract_number',
        'title',
        'status',
        'currency',
        'total_value',
        'signed_at',
        'start_date',
        'end_date',
        'created_by',
        'description',
        'version',
        'signed_date',
        'terms',
        'client_name',
        'notes',
        'updated_by'
    ];
```

Replace with:

```php
    protected $fillable = [
        'tenant_id',
        'project_id',
        'source_opportunity_id',
        'source_quote_id',
        'source_quote_revision',
        'code',
        'contract_number',
        'title',
        'status',
        'currency',
        'total_value',
        'signed_at',
        'start_date',
        'end_date',
        'created_by',
        'description',
        'version',
        'signed_date',
        'terms',
        'client_name',
        'notes',
        'updated_by'
    ];
```

Then find:

```php
    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'code' => 'string',
        'status' => 'string',
        'currency' => 'string',
        'total_value' => 'float',
        'version' => 'integer',
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_date' => 'date',
        'terms' => 'array'
    ];
```

Replace with:

```php
    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'source_opportunity_id' => 'string',
        'source_quote_id' => 'string',
        'source_quote_revision' => 'integer',
        'code' => 'string',
        'status' => 'string',
        'currency' => 'string',
        'total_value' => 'float',
        'version' => 'integer',
        'signed_at' => 'date',
        'start_date' => 'date',
        'end_date' => 'date',
        'signed_date' => 'date',
        'terms' => 'array'
    ];
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Unit/Models/ContractSourceFieldsTest.php`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_07_10_120000_add_source_fields_to_contracts_table.php app/Models/Contract.php tests/Unit/Models/ContractSourceFieldsTest.php
git commit -m "feat(zena-boq): add source_opportunity_id/source_quote_id/source_quote_revision to contracts"
```

---

### Task 2: `Api\OpportunityController::createContract()` — the core action

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php`
- Modify: `routes/api_zena.php:360-362`
- Test: `tests/Feature/Api/CrmApiTest.php`

**Interfaces:**
- Consumes: `Contract` model (Task 1), `Opportunity::convertedProject()`/`converted_project_id`, `OpportunityPolicy::convert()`, `ContractPolicy::create()`, `Opportunity::account()` relation, `Project::generateCode()`-style pattern (not the method itself — `Project::generateCode()` is `private static`, this task writes its own equivalent inline).
- Produces: `Api\OpportunityController::createContract(Request $request, string $id): JsonResponse` — new route `POST /api/zena/crm/opportunities/{id}/create-contract`, name `crm.opportunities.create-contract`. Task 3 (Web layer) delegates to this.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Api/CrmApiTest.php` (inside the class, after the last BOQ-related test):

```php
    public function test_create_contract_auto_converts_and_creates_contract_pinned_to_quote(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'external_boq_project_code' => 'PRJ-004',
            'external_quote_id' => 'quote_won_1',
            'external_quote_snapshot' => [
                'revision' => 2,
                'total' => 250000000,
                'status' => 'ACCEPTED',
                'calibration' => 'CALIBRATED',
            ],
        ]);

        $this->assertNull($opportunity->converted_project_id);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(201);

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $contract = \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($contract);
        $this->assertSame((string) $opportunity->converted_project_id, (string) $contract->project_id);
        $this->assertSame('quote_won_1', $contract->source_quote_id);
        $this->assertSame(2, $contract->source_quote_revision);
        $this->assertSame(250000000.0, (float) $contract->total_value);
        $this->assertSame('VND', $contract->currency);
        $this->assertSame('draft', $contract->status);
    }

    public function test_create_contract_reuses_project_when_already_converted(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $project = \App\Models\Project::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'name' => 'Du an da convert',
            'code' => 'PRJ-ALREADY1',
            'status' => 'planning',
        ]);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'external_boq_project_code' => 'PRJ-005',
            'external_quote_id' => 'quote_won_2',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 100000000, 'status' => 'ACCEPTED'],
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(201);

        $contract = \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->first();
        $this->assertNotNull($contract);
        $this->assertSame((string) $project->id, (string) $contract->project_id);
    }

    public function test_create_contract_does_not_duplicate_on_second_call(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'external_boq_project_code' => 'PRJ-006',
            'external_quote_id' => 'quote_won_3',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 50000000, 'status' => 'ACCEPTED'],
        ]);

        $first = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );
        $first->assertStatus(201);

        $second = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );
        $second->assertStatus(200);

        $this->assertSame(
            1,
            \App\Models\Contract::query()->where('source_opportunity_id', $opportunity->id)->count()
        );
    }

    public function test_create_contract_requires_won_stage_and_accepted_quote(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity([
            'pipeline_stage' => \App\Models\Opportunity::STAGE_QUALIFIED,
            'external_boq_project_code' => 'PRJ-007',
            'external_quote_id' => 'quote_won_4',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 50000000, 'status' => 'ISSUED'],
        ]);

        $response = $this->postJson(
            $this->route('opportunities.create-contract', ['id' => $opportunity->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(422);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php --filter test_create_contract`
Expected: FAIL — route `opportunities.create-contract` doesn't exist.

- [ ] **Step 3: Add the route**

In `routes/api_zena.php`, find:

```php
            Route::post('/opportunities/{id}/convert', [\App\Http\Controllers\Api\OpportunityController::class, 'convert'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
            Route::post('/opportunities/{id}/boq-link', [\App\Http\Controllers\Api\OpportunityController::class, 'linkExternalBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
            Route::post('/opportunities/{id}/boq-sync', [\App\Http\Controllers\Api\OpportunityController::class, 'syncExternalQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
```

Replace with:

```php
            Route::post('/opportunities/{id}/convert', [\App\Http\Controllers\Api\OpportunityController::class, 'convert'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
            Route::post('/opportunities/{id}/boq-link', [\App\Http\Controllers\Api\OpportunityController::class, 'linkExternalBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
            Route::post('/opportunities/{id}/boq-sync', [\App\Http\Controllers\Api\OpportunityController::class, 'syncExternalQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
            Route::post('/opportunities/{id}/create-contract', [\App\Http\Controllers\Api\OpportunityController::class, 'createContract'])->middleware('rbac:crm.manage')->name('crm.opportunities.create-contract');
```

- [ ] **Step 4: Add the `createContract()` method**

In `app/Http/Controllers/Api/OpportunityController.php`, add this import alongside the existing ones:

```php
use App\Models\Contract;
```

Add this method after `convert()` (before `linkExternalBoqProject()`):

```php
    public function createContract(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $existingContract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->where('source_opportunity_id', $opportunity->id)
            ->first();

        if ($existingContract instanceof Contract) {
            return $this->zenaSuccessResponse(
                [
                    'contract_id' => (string) $existingContract->id,
                    'project_id' => (string) $existingContract->project_id,
                ],
                'A contract already exists for this opportunity'
            );
        }

        if ((string) $opportunity->pipeline_stage !== Opportunity::STAGE_WON) {
            return $this->validationError([
                'pipeline_stage' => ['Only won opportunities can generate a contract.'],
            ]);
        }

        $snapshot = $opportunity->external_quote_snapshot ?? [];
        if (($snapshot['status'] ?? null) !== 'ACCEPTED') {
            return $this->validationError([
                'external_quote_snapshot' => ['The linked quote must be ACCEPTED before generating a contract.'],
            ]);
        }

        $projectId = $opportunity->converted_project_id;

        if (!$projectId) {
            $this->authorize('convert', $opportunity);

            $project = DB::transaction(function () use ($opportunity, $user, $tenantId): Project {
                $project = Project::query()->create([
                    'tenant_id' => $tenantId,
                    'name' => (string) $opportunity->opportunity_name,
                    'code' => 'PRJ-' . Str::upper(Str::random(8)),
                    'description' => $opportunity->service_scope_summary,
                    'status' => 'planning',
                    'progress' => 0,
                    'budget_total' => $opportunity->estimated_project_value ?? ($opportunity->estimated_fee ?? 0),
                    'pm_id' => $opportunity->technical_owner_id ?? $opportunity->sales_owner_id,
                    'created_by' => (string) $user->id,
                ]);

                $opportunity->converted_project_id = (string) $project->id;
                $opportunity->save();

                return $project;
            });

            $this->recordEvent($opportunity, 'crm.opportunity.converted', [
                'project_id' => (string) $project->id,
                'project_name' => (string) $project->name,
            ]);

            $projectId = (string) $project->id;
        }

        $this->authorize('create', Contract::class);

        $account = $opportunity->account;
        $clientName = $account?->display_name ?? '';

        $contract = Contract::query()->create([
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'source_opportunity_id' => (string) $opportunity->id,
            'source_quote_id' => $opportunity->external_quote_id,
            'source_quote_revision' => $snapshot['revision'] ?? null,
            'code' => $this->generateContractCode(),
            'title' => 'Hợp đồng dịch vụ - ' . $clientName,
            'client_name' => $clientName,
            'total_value' => (float) ($snapshot['total'] ?? 0),
            'currency' => 'VND',
            'created_by' => (string) $user->id,
        ]);

        $this->recordEvent($opportunity, 'crm.opportunity.contract_created', [
            'contract_id' => (string) $contract->id,
            'project_id' => $projectId,
            'total_value' => (float) $contract->total_value,
        ]);

        return $this->zenaSuccessResponse(
            [
                'contract_id' => (string) $contract->id,
                'project_id' => $projectId,
            ],
            'Contract created successfully',
            201
        );
    }

    private function generateContractCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $candidate = 'CTR-' . Str::upper(Str::random(8));
            if (!Contract::query()->where('code', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'CTR-' . Str::upper((string) Str::ulid());
    }
```

- [ ] **Step 5: Add `create-contract` route helper support to the test's `route()` method**

Check `tests/Feature/Api/CrmApiTest.php`'s private `route(string $name, ...)` helper (around line 485) — it builds URLs from the route name directly via Laravel's `route()` helper, so no changes are needed there; `route('opportunities.create-contract', ...)` will resolve automatically once Step 3's route exists. (This step is a verification note, not a code change.)

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php`
Expected: PASS (all tests in this file, including the 4 new ones).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php routes/api_zena.php tests/Feature/Api/CrmApiTest.php
git commit -m "feat(zena-boq): add createContract endpoint with auto-convert, permission layering, duplicate-guard"
```

---

### Task 3: Web delegation + "Hợp đồng" card on the Opportunity page

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php`
- Modify: `resources/views/crm/opportunity-show.blade.php`
- Modify: `routes/web.php:966-970`
- Modify: `tests/Feature/Zena/OperatorCrmUiTest.php`

**Interfaces:**
- Consumes: `Api\OpportunityController::createContract()` (Task 2), `Contract` model (Task 1).
- Produces: `CrmPageController::createContract()` method; `showOpportunity()` gains a `contractCard` view variable: `null` when not eligible for the action and no contract exists yet; otherwise `{eligible: bool, contract: array|null, has_drift: bool}`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Zena/OperatorCrmUiTest.php` (inside the class, after the existing BOQ staleness tests):

```php
    public function test_contract_action_appears_for_won_opportunity_with_accepted_quote(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang won',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi da thang',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-008',
            'external_quote_id' => 'quote_ui_1',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 75000000, 'status' => 'ACCEPTED'],
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Tạo hợp đồng');

        $create = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.create-contract', $opportunity->id), [], $headers);
        $create->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertNotNull($opportunity->converted_project_id);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('Tạo hợp đồng')
            ->assertSee('CTR-');
    }

    public function test_contract_action_hidden_for_non_won_opportunity(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang chua thang',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi dang xu ly',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_QUALIFIED,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('Tạo hợp đồng');
    }

    public function test_contract_card_shows_drift_warning_when_quote_changed_after_creation(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang drift',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bi drift',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
            'external_boq_project_code' => 'PRJ-009',
            'external_quote_id' => 'quote_ui_2',
            'external_quote_snapshot' => ['revision' => 1, 'total' => 90000000, 'status' => 'ACCEPTED'],
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.create-contract', $opportunity->id), [], $headers);

        // Simulate a later re-sync that pulled a different revision.
        $opportunity->refresh();
        $opportunity->external_quote_snapshot = ['revision' => 2, 'total' => 120000000, 'status' => 'ACCEPTED'];
        $opportunity->save();

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('Báo giá đã đổi kể từ khi tạo hợp đồng');
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: FAIL — route `operator.crm.opportunities.create-contract` doesn't exist; the card/text isn't rendered yet.

- [ ] **Step 3: Add the route**

In `routes/web.php`, find:

```php
    Route::post('/crm/opportunities/{id}/boq-link', [App\Http\Controllers\Web\CrmPageController::class, 'linkBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
    Route::post('/crm/opportunities/{id}/boq-sync', [App\Http\Controllers\Web\CrmPageController::class, 'syncBoqQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
```

Replace with:

```php
    Route::post('/crm/opportunities/{id}/boq-link', [App\Http\Controllers\Web\CrmPageController::class, 'linkBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
    Route::post('/crm/opportunities/{id}/boq-sync', [App\Http\Controllers\Web\CrmPageController::class, 'syncBoqQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
    Route::post('/crm/opportunities/{id}/create-contract', [App\Http\Controllers\Web\CrmPageController::class, 'createContract'])->middleware('rbac:crm.manage')->name('crm.opportunities.create-contract');
```

- [ ] **Step 4: Add `createContract()` to the Web controller and wire `contractCard` into `showOpportunity()`**

In `app/Http/Controllers/Web/CrmPageController.php`, find:

```php
        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'boqCard' => $this->buildBoqCardViewModel($opportunity),
            'canManageBoq' => (bool) auth()->user()?->hasPermission('crm.manage'),
```

Replace with:

```php
        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'boqCard' => $this->buildBoqCardViewModel($opportunity),
            'canManageBoq' => (bool) auth()->user()?->hasPermission('crm.manage'),
            'contractCard' => $this->buildContractCardViewModel($opportunity),
```

Add this private method right after `buildBoqCardViewModel()` (find its closing `}` and add after it):

```php
    /**
     * @return array{eligible: bool, contract: array{id: string, code: string}|null, has_drift: bool}|null
     */
    private function buildContractCardViewModel(Opportunity $opportunity): ?array
    {
        $snapshot = $opportunity->external_quote_snapshot ?? [];
        $eligible = (string) $opportunity->pipeline_stage === Opportunity::STAGE_WON
            && ($snapshot['status'] ?? null) === 'ACCEPTED';

        $existingContract = \App\Models\Contract::query()
            ->where('tenant_id', (string) $opportunity->tenant_id)
            ->where('source_opportunity_id', $opportunity->id)
            ->first();

        if (!$eligible && !$existingContract instanceof \App\Models\Contract) {
            return null;
        }

        $hasDrift = false;
        $contractData = null;

        if ($existingContract instanceof \App\Models\Contract) {
            $hasDrift = $existingContract->source_quote_id !== $opportunity->external_quote_id
                || $existingContract->source_quote_revision !== ($snapshot['revision'] ?? null);

            $contractData = [
                'id' => (string) $existingContract->id,
                'code' => (string) $existingContract->code,
            ];
        }

        return [
            'eligible' => $eligible,
            'contract' => $contractData,
            'has_drift' => $hasDrift,
        ];
    }
```

Now add the `Opportunity` import check: `App\Models\Opportunity` is already imported in this file (used throughout `showOpportunity()`), so no new import is needed for that. Add this new public method after `syncBoqQuote()` (the last existing method, before the class's closing `}`):

```php
    public function createContract(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->createContract($this->buildApiRequest($request), $id);
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã tạo hợp đồng');
    }
```

- [ ] **Step 5: Add the "Hợp đồng" card to the Blade view**

In `resources/views/crm/opportunity-show.blade.php`, find the closing `@endif` of the BOQ card block (immediately followed by the "Lịch sử" card):

```blade
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Lịch sử">
```

Replace with:

```blade
            @endif
        </x-ui.card>
    @endif

    @if ($contractCard !== null)
        <x-ui.card title="Hợp đồng">
            @if ($contractCard['has_drift'])
                <p class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
                    Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp.
                </p>
            @endif

            @if ($contractCard['contract'])
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã hợp đồng" :value="$contractCard['contract']['code']" />
                </div>
                <a href="{{ route('operator.contracts.show', $contractCard['contract']['id']) }}" class="operator-link mt-3 inline-block">Xem hợp đồng</a>
            @elseif ($contractCard['eligible'] && $canManageBoq)
                <form method="POST" action="{{ route('operator.crm.opportunities.create-contract', $opportunity->id) }}">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Tạo hợp đồng</button>
                </form>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Lịch sử">
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS (all tests in this file, including the 3 new ones).

- [ ] **Step 7: Run the full CRM test files to confirm no regression**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS across both files.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php resources/views/crm/opportunity-show.blade.php routes/web.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "feat(zena-boq): add contract card + create-contract action to Opportunity page, with live drift-guard"
```

---

### Task 4: Contract PDF generation + drift warning on the Contract page

**Files:**
- Create: `resources/views/contracts/pdf.blade.php`
- Modify: `app/Http/Controllers/Api/ContractController.php`
- Modify: `app/Http/Controllers/Web/ContractPageController.php`
- Modify: `resources/views/contracts/show.blade.php`
- Modify: `routes/api_zena.php:231` area, `routes/web.php:897` area
- Test: `tests/Feature/Api/ContractPdfExportTest.php`

**Interfaces:**
- Consumes: `DeliverablePdfExportService::render()` (existing, from Phase 1's era of this codebase), `DeliverablePdfExportUnavailableException` (existing).
- Produces: `Api\ContractController::pdf(Request $request, string $project, string $contract, DeliverablePdfExportService $pdfService): Response`; `Web\ContractPageController::downloadPdf()`; `ContractPageController::show()` gains drift-detection view data.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Api/ContractPdfExportTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractPdfExportTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_contract_pdf_endpoint_streams_pdf_bytes(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    return '%PDF-1.4 fake-contract-pdf-bytes';
                }
            };
        });

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['contract.view']);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an pdf',
            'code' => 'PRJ-PDFTEST1',
            'status' => 'planning',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-PDFTEST1',
            'title' => 'Hop dong pdf test',
            'client_name' => 'Khach hang pdf',
            'total_value' => 123000000,
            'currency' => 'VND',
        ]);

        $response = $this->actingAs($user)->get(
            "/api/zena/projects/{$project->id}/contracts/{$contract->id}/pdf",
            ['X-Tenant-ID' => (string) $tenant->id]
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fake-contract-pdf-bytes', $response->getContent());
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Feature/Api/ContractPdfExportTest.php`
Expected: FAIL — route doesn't exist (404).

- [ ] **Step 3: Create the fixed Blade PDF view**

Create `resources/views/contracts/pdf.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Hợp đồng {{ $contract->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1e293b; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        td { padding: 6px 0; border-bottom: 1px solid #e2e8f0; }
        td.label { font-weight: bold; width: 40%; }
    </style>
</head>
<body>
    <h1>Hợp đồng {{ $contract->code }}</h1>
    <p>{{ $contract->title }}</p>

    <table>
        <tr><td class="label">Khách hàng</td><td>{{ $contract->client_name ?? '—' }}</td></tr>
        <tr><td class="label">Giá trị hợp đồng</td><td>{{ number_format((float) $contract->total_value, 0, ',', '.') }} {{ $contract->currency }}</td></tr>
        <tr><td class="label">Trạng thái</td><td>{{ $contract->status }}</td></tr>
        <tr><td class="label">Ngày tạo</td><td>{{ optional($contract->created_at)->format('d/m/Y') }}</td></tr>
    </table>
</body>
</html>
```

- [ ] **Step 4: Add the `pdf()` method to `Api\ContractController`**

In `app/Http/Controllers/Api/ContractController.php`, add these imports alongside the existing ones:

```php
use App\Services\DeliverablePdfExportService;
use App\Exceptions\DeliverablePdfExportUnavailableException;
use Illuminate\Http\Response;
```

Add this method after `costSummary()` (the last existing method, before the class's closing `}`):

```php
    public function pdf(Request $request, string $project, string $contract, DeliverablePdfExportService $pdfService): JsonResponse|Response
    {
        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        try {
            $contractModel = $this->findContractOrFail($tenantId, $project, $contract);
        } catch (ModelNotFoundException) {
            return $this->notFound('Contract not found');
        }

        $this->authorize('view', $contractModel);

        $html = view('contracts.pdf', ['contract' => $contractModel])->render();

        try {
            $pdf = $pdfService->render($html, [], [
                'generated_at' => now()->toIso8601String(),
            ]);
        } catch (DeliverablePdfExportUnavailableException $exception) {
            return $this->errorResponse($exception->getMessage(), 501);
        }

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="hop-dong-' . $contractModel->code . '.pdf"',
        ]);
    }
```

- [ ] **Step 5: Add the API route**

In `routes/api_zena.php`, find:

```php
                Route::get('/{contract}/cost-summary', [\App\Http\Controllers\Api\ContractController::class, 'costSummary'])->middleware('rbac:contract.view')->name('cost-summary.show');
```

Replace with:

```php
                Route::get('/{contract}/cost-summary', [\App\Http\Controllers\Api\ContractController::class, 'costSummary'])->middleware('rbac:contract.view')->name('cost-summary.show');
                Route::get('/{contract}/pdf', [\App\Http\Controllers\Api\ContractController::class, 'pdf'])->middleware('rbac:contract.view')->name('pdf');
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test tests/Feature/Api/ContractPdfExportTest.php`
Expected: PASS.

- [ ] **Step 7: Add the Web download proxy + drift detection on the Contract show page**

In `app/Http/Controllers/Web/ContractPageController.php`, add this import alongside the existing ones:

```php
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
```

Find:

```php
        return view('contracts.show', [
            'contract' => $contract,
            'summary' => $summary,
            'summaryUnavailableMessage' => $summaryUnavailableMessage,
        ]);
    }
}
```

Replace with:

```php
        $hasDrift = false;
        if ($contract->source_opportunity_id) {
            $sourceOpportunity = \App\Models\Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->find($contract->source_opportunity_id);

            if ($sourceOpportunity) {
                $snapshot = $sourceOpportunity->external_quote_snapshot ?? [];
                $hasDrift = $contract->source_quote_id !== $sourceOpportunity->external_quote_id
                    || $contract->source_quote_revision !== ($snapshot['revision'] ?? null);
            }
        }

        return view('contracts.show', [
            'contract' => $contract,
            'summary' => $summary,
            'summaryUnavailableMessage' => $summaryUnavailableMessage,
            'hasQuoteDrift' => $hasDrift,
        ]);
    }

    public function downloadPdf(Request $request, string $id, ApiContractController $apiController): SymfonyResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $contract = Contract::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        try {
            return $apiController->pdf(
                $this->buildApiRequest($request),
                (string) $contract->project_id,
                (string) $contract->id
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể tạo PDF hợp đồng vào lúc này.');
        }
    }
}
```

- [ ] **Step 8: Add the Web route**

In `routes/web.php`, find:

```php
    Route::get('/contracts/{id}', [App\Http\Controllers\Web\ContractPageController::class, 'show'])->middleware('rbac:contract.view')->name('contracts.show');
```

Replace with:

```php
    Route::get('/contracts/{id}', [App\Http\Controllers\Web\ContractPageController::class, 'show'])->middleware('rbac:contract.view')->name('contracts.show');
    Route::get('/contracts/{id}/pdf', [App\Http\Controllers\Web\ContractPageController::class, 'downloadPdf'])->middleware('rbac:contract.view')->name('contracts.pdf');
```

- [ ] **Step 9: Add the download button + drift warning to the Contract show view**

In `resources/views/contracts/show.blade.php`, find:

```blade
    <x-ui.page-header
        :title="'Hợp đồng ' . $contract->code"
        :description="$contract->title"
    >
        <x-ui.button-link :href="route('operator.contracts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    <div class="space-y-6">
```

Replace with:

```blade
    <x-ui.page-header
        :title="'Hợp đồng ' . $contract->code"
        :description="$contract->title"
    >
        <x-ui.button-link :href="route('operator.contracts.pdf', $contract->id)" variant="secondary">Tải PDF</x-ui.button-link>
        <x-ui.button-link :href="route('operator.contracts.index')" variant="secondary">Quay lại</x-ui.button-link>
    </x-ui.page-header>

    @if ($hasQuoteDrift)
        <p class="mb-4 rounded-lg bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
            Báo giá đã đổi kể từ khi tạo hợp đồng — số tiền hợp đồng có thể không còn khớp.
        </p>
    @endif

    <div class="space-y-6">
```

- [ ] **Step 10: Run the full contract + CRM test files to confirm no regression**

Run: `php artisan test tests/Feature/Api/ContractPdfExportTest.php tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php --filter "Contract|contract"`

(If that filter is too narrow and misses relevant tests, just run the three files directly: `php artisan test tests/Feature/Api/ContractPdfExportTest.php tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php`.)

Expected: PASS across all files.

- [ ] **Step 11: Commit**

```bash
git add resources/views/contracts/pdf.blade.php app/Http/Controllers/Api/ContractController.php app/Http/Controllers/Web/ContractPageController.php resources/views/contracts/show.blade.php routes/api_zena.php routes/web.php tests/Feature/Api/ContractPdfExportTest.php
git commit -m "feat(zena-boq): add contract PDF download endpoint and drift warning on the Contract page"
```

---

### Task 5: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including this plan's new tests (~1 in Task 1, ~4 in Task 2, ~3 in Task 3, ~1 in Task 4 — roughly +9 over the pre-Phase-4 baseline of 1363 passed).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. `Api\OpportunityController` now also depends on `Contract` (a Model) — already allowed under `ApiControllers: [Models, Services, Jobs]`. `Api\ContractController` now depends on `DeliverablePdfExportService` — a `Services` class per `deptrac.yaml`, already an allowed edge for `ApiControllers`. If a violation appears, it means a dependency was drawn in the wrong direction — fix the direction, don't add a `skip_violations` entry.

- [ ] **Step 3: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(zena-boq): confirm full suite and Deptrac are green for Phase 4"
```

(Skip this commit if step 1-2 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 4 section as revised through §9):
- Auto-convert-then-create in one action, permission layering (`crm.convert`/`contract.create` on top of `crm.manage`), duplicate-guard via `source_opportunity_id`, live drift-guard (never auto-updating pinned fields), fixed-Blade-view PDF via the existing `DeliverablePdfExportService`, VND currency override — all covered by Tasks 1-4.
- "Contract.total_value and pinned fields never auto-updated by re-sync" — verified no task writes to those fields after Task 2's initial `Contract::create()` call; the drift-guard in Tasks 3/4 only ever reads and compares, never writes.

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code.

**Type/signature consistency check:** `Contract::$source_opportunity_id`/`source_quote_id`/`source_quote_revision` (Task 1) are written identically in Task 2's `createContract()` and read identically in Task 3's `buildContractCardViewModel()` and Task 4's `ContractPageController::show()` drift check. `Api\OpportunityController::createContract()`'s route name (`crm.opportunities.create-contract`, both API in Task 2 and Web in Task 3) is used consistently between route registration and the test files that call `route(...)`. `Api\ContractController::pdf()`'s signature (`Request, string $project, string $contract, DeliverablePdfExportService`) matches exactly how Task 4's Web `downloadPdf()` calls it.
