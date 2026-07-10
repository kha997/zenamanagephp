# zena-boq-core Integration (Phase 2) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** let a ZenaManage `Opportunity` reference a `zena-boq-core` project/quote and display its cached totals/status, without ZenaManage owning any pricing logic, hard-restricted to a single Z.E.N.A tenant so no other ZenaManage tenant can read or trigger the integration.

**Architecture:** a single-purpose `App\Services\ZenaBoqIntegrationService` owns both the tenant-authorization gate (`isTenantAuthorized()`, pure DB lookup, no HTTP) and the outbound HTTP call to `zena-boq-core`'s (not-yet-existing) read API (`fetchLatestQuote()`, mockable via `Http::fake()`). Two new `Api\OpportunityController` endpoints (`linkExternalBoqProject`, `syncExternalQuote`) both call the gate first, before touching any Opportunity data. `Web\CrmPageController` delegates to both via the existing `DelegatesToApiControllers` trait and hides the whole feature from the Opportunity page for any tenant the gate doesn't authorize.

**Tech Stack:** Laravel (PHP, `declare(strict_types=1)`), `Illuminate\Support\Facades\Http` for the outbound call (`Http::fake()` in tests, matching this repo's existing convention in `tests/Feature/Zena/OperatorPlatformUiTest.php`), Blade views extending `layouts.operator`, SQLite for local/test DB, PHPUnit feature tests.

**Spec reference:** `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 2 section (as revised through §6 and §7, after the 2026-07-10 brainstorm on tenant creation and the webhook deferral).

## Global Constraints

- Every PHP file starts with `<?php declare(strict_types=1);`.
- **The gate check runs before anything else in both new controller endpoints** — before fetching the `Opportunity`, before any validation. A tenant the gate doesn't authorize gets a 403 immediately, regardless of whether the `Opportunity` in the URL exists or belongs to them.
- **Fail-closed, both failure modes:** `ZenaBoqIntegrationService::isTenantAuthorized()` must return `false` when `config('zena_boq.integration_tenant_name')` is empty/null, AND when it's set but resolves to no `Tenant` row. Never let a `null === null` comparison accidentally pass.
- **No webhook in this phase.** Do not add an inbound webhook receiver route. `ZENA_BOQ_WEBHOOK_SECRET` is not referenced anywhere in this plan — that's for a separate, later, cross-repo-coordinated task.
- **Seeder, not migration, for the Z.E.N.A tenant.** Business data goes in a seeder (matching `ZenaPermissionsSeeder`/`ZenaRbacSeeder` precedent already in this codebase) — the `Tenant` row is created via `firstOrCreate(['name' => 'Z.E.N.A'], [...])`, keyed by `name`, not a hardcoded/generated ID copied into `.env`.
- **ZenaManage never edits zena-boq-core data.** `ZenaBoqIntegrationService::fetchLatestQuote()` only ever performs `GET` requests. There is no write path to `zena-boq-core` anywhere in this plan.
- **`zena-boq-core`'s read API does not exist yet.** Every test in this plan mocks it via `Http::fake([...])` (this repo's established convention — see `tests/Feature/Zena/OperatorPlatformUiTest.php`). Do not write a test that requires a live `zena-boq-core` endpoint.
- **The UNCALIBRATED/CALIBRATED distinction is data, not styling, in this phase.** Phase 2 stores and displays the raw `calibration` string from the quote payload verbatim — it does not need a badge/visual system (that's Phase 3's "Báo giá card" polish). Do not build styled status badges here; a plain text label is enough.
- **All four external columns (`external_boq_project_code`, `external_quote_id`, `external_quote_snapshot`, `external_quote_synced_at`) may be model-`$fillable`** (needed for test factories to seed them via `Opportunity::create()`), **but `Api\OpportunityController::update()`'s explicit field allowlist (`$request->only([...])`) must never include them.** Model-fillable alone is not the guard — the controller-level allowlist is what actually prevents a generic `PUT` from writing them. They are only ever set through the two dedicated, gate-checked endpoints built in this plan, mirroring the precedent set for `review_status` in the sibling Design Item feature. A regression test asserting the allowlist blocks these fields (not just that the dedicated endpoints work) is required — model-fillable alone gives no compile-time or type-level protection, so without such a test a future refactor to `fill($validated)` or `fill($request->all())` would silently reopen this exact bypass.

---

### Task 1: Z.E.N.A tenant seeder + `config/zena_boq.php`

**Files:**
- Create: `database/seeders/ZenaBoqTenantSeeder.php`
- Create: `config/zena_boq.php`
- Test: `tests/Unit/ZenaBoqTenantSeederTest.php`

**Interfaces:**
- Produces: running `ZenaBoqTenantSeeder` guarantees a `Tenant` row with `name = 'Z.E.N.A'` exists (idempotent — running it twice does not create a duplicate). `config('zena_boq.integration_tenant_name')` resolves to `'Z.E.N.A'` by default, overridable via `ZENA_BOQ_INTEGRATION_TENANT_NAME`. `config('zena_boq.read_api_secret')` resolves from `ZENA_BOQ_READ_API_SECRET`. `config('zena_boq.base_url')` resolves from `ZENA_BOQ_BASE_URL`.

- [ ] **Step 1: Write the failing test**

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use Database\Seeders\ZenaBoqTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenaBoqTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_zena_tenant(): void
    {
        $this->assertSame(0, Tenant::where('name', 'Z.E.N.A')->count());

        (new ZenaBoqTenantSeeder())->run();

        $this->assertSame(1, Tenant::where('name', 'Z.E.N.A')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        (new ZenaBoqTenantSeeder())->run();
        $firstId = Tenant::where('name', 'Z.E.N.A')->value('id');

        (new ZenaBoqTenantSeeder())->run();

        $this->assertSame(1, Tenant::where('name', 'Z.E.N.A')->count());
        $this->assertSame($firstId, Tenant::where('name', 'Z.E.N.A')->value('id'));
    }

    public function test_config_defaults_resolve(): void
    {
        $this->assertSame('Z.E.N.A', config('zena_boq.integration_tenant_name'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Unit/ZenaBoqTenantSeederTest.php`
Expected: FAIL — `Database\Seeders\ZenaBoqTenantSeeder` class does not exist; `config('zena_boq.integration_tenant_name')` returns `null` (no `config/zena_boq.php` file yet).

- [ ] **Step 3: Write the config file**

```php
<?php declare(strict_types=1);

return [
    // The Tenant.name value this integration is restricted to (see docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 2).
    // Resolved at runtime via Tenant::where('name', ...) — never a hardcoded/copied tenant ID.
    'integration_tenant_name' => env('ZENA_BOQ_INTEGRATION_TENANT_NAME', 'Z.E.N.A'),

    // Bearer secret for the outbound read-only call to zena-boq-core's (not-yet-existing) read API.
    'read_api_secret' => env('ZENA_BOQ_READ_API_SECRET'),

    // Base URL of the zena-boq-core deployment, e.g. https://zena-boq.vercel.app
    'base_url' => env('ZENA_BOQ_BASE_URL'),
];
```

- [ ] **Step 4: Write the seeder**

```php
<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Ensures a Tenant row named 'Z.E.N.A' exists — the anchor for the
 * zena-boq-core integration's tenant gate (spec Phase 2). Not a schema
 * change, so this is a seeder, not a migration.
 */
class ZenaBoqTenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['name' => 'Z.E.N.A'],
            [
                'status' => 'active',
                'is_active' => true,
            ]
        );
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test tests/Unit/ZenaBoqTenantSeederTest.php`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ZenaBoqTenantSeeder.php config/zena_boq.php tests/Unit/ZenaBoqTenantSeederTest.php
git commit -m "feat(zena-boq): add Z.E.N.A tenant seeder and integration config"
```

---

### Task 2: Migration — `external_boq_*` columns on `opportunities`

**Files:**
- Create: `database/migrations/2026_07_10_100000_add_external_boq_fields_to_opportunities_table.php`
- Modify: `app/Models/Opportunity.php:80-105` (fillable + casts)

**Interfaces:**
- Consumes: `opportunities` table (existing).
- Produces: nullable columns `external_boq_project_code` (string), `external_quote_id` (string), `external_quote_snapshot` (json), `external_quote_synced_at` (timestamp) on `opportunities`. `Opportunity` model exposes all four as fillable, with `external_quote_snapshot` cast to `array` and `external_quote_synced_at` cast to `datetime`.

- [ ] **Step 1: Write the migration**

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// zena-boq-core integration — spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('external_boq_project_code')->nullable()->after('converted_project_id');
            $table->string('external_quote_id')->nullable()->after('external_boq_project_code');
            $table->json('external_quote_snapshot')->nullable()->after('external_quote_id');
            $table->timestamp('external_quote_synced_at')->nullable()->after('external_quote_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'external_boq_project_code',
                'external_quote_id',
                'external_quote_snapshot',
                'external_quote_synced_at',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `php artisan migrate`
Expected: `2026_07_10_100000_add_external_boq_fields_to_opportunities_table ... DONE`

- [ ] **Step 3: Update the model**

In `app/Models/Opportunity.php`, find:

```php
    protected $fillable = [
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
        'created_by',
    ];

    protected $casts = [
        'estimated_fee' => 'decimal:0',
        'estimated_project_value' => 'decimal:0',
        'probability' => 'integer',
        'expected_close_date' => 'date',
    ];
```

Replace with:

```php
    protected $fillable = [
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
        'created_by',
        'external_boq_project_code',
        'external_quote_id',
        'external_quote_snapshot',
        'external_quote_synced_at',
    ];

    protected $casts = [
        'estimated_fee' => 'decimal:0',
        'estimated_project_value' => 'decimal:0',
        'probability' => 'integer',
        'expected_close_date' => 'date',
        'external_quote_snapshot' => 'array',
        'external_quote_synced_at' => 'datetime',
    ];
```

- [ ] **Step 4: Verify nothing broke**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS (all previously-passing CRM tests still pass — this is a purely additive schema/model change)

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_07_10_100000_add_external_boq_fields_to_opportunities_table.php app/Models/Opportunity.php
git commit -m "feat(zena-boq): add external_boq_* columns to opportunities"
```

---

### Task 3: `ZenaBoqIntegrationService` — tenant gate + outbound quote fetch

**Files:**
- Create: `app/Services/ZenaBoqIntegrationService.php`
- Test: `tests/Unit/ZenaBoqIntegrationServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Tenant`, `config('zena_boq.*')` (Task 1), `Illuminate\Support\Facades\Http`.
- Produces: `ZenaBoqIntegrationService::isTenantAuthorized(string $tenantId): bool`. `ZenaBoqIntegrationService::fetchLatestQuote(string $projectCode): ?array` returning `null` on any failure (unreachable, non-2xx, timeout) or an array shaped `['id', 'subtotal', 'vat_amount', 'total', 'status', 'calibration', 'issued_at']` on success. Both methods are used identically by Task 4 and Task 5's controller methods.

- [ ] **Step 1: Write the failing tests**

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\ZenaBoqIntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZenaBoqIntegrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ZenaBoqIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ZenaBoqIntegrationService();
    }

    public function test_authorized_when_tenant_matches_configured_name(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        $tenant = Tenant::factory()->create(['name' => 'Z.E.N.A']);

        $this->assertTrue($this->service->isTenantAuthorized((string) $tenant->id));
    }

    public function test_denied_when_tenant_does_not_match(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        Tenant::factory()->create(['name' => 'Z.E.N.A']);
        $otherTenant = Tenant::factory()->create(['name' => 'Some Other Company']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $otherTenant->id));
    }

    public function test_fail_closed_when_config_name_is_empty(): void
    {
        config(['zena_boq.integration_tenant_name' => null]);
        $tenant = Tenant::factory()->create(['name' => 'Z.E.N.A']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $tenant->id));
    }

    public function test_fail_closed_when_config_name_matches_no_tenant(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);
        // Deliberately do not create any tenant named Z.E.N.A.
        $someTenant = Tenant::factory()->create(['name' => 'Random Co']);

        $this->assertFalse($this->service->isTenantAuthorized((string) $someTenant->id));
    }

    public function test_fetch_latest_quote_returns_shaped_array_on_success(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['id' => 'proj_1', 'code' => 'PRJ-001'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => Http::response([
                'id' => 'quote_1',
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $result = (new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001');

        $this->assertNotNull($result);
        $this->assertSame('quote_1', $result['id']);
        $this->assertSame(108000000.0, $result['total']);
        $this->assertSame('UNCALIBRATED', $result['calibration']);
    }

    public function test_fetch_latest_quote_returns_null_on_project_not_found(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake([
            'https://zena-boq.example/api/external/projects/*' => Http::response(['message' => 'Not Found'], 404),
        ]);

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('MISSING'));
    }

    public function test_fetch_latest_quote_returns_null_when_unreachable(): void
    {
        config(['zena_boq.base_url' => 'https://zena-boq.example', 'zena_boq.read_api_secret' => 'test-secret']);

        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001'));
    }

    public function test_fetch_latest_quote_returns_null_when_config_missing(): void
    {
        config(['zena_boq.base_url' => null, 'zena_boq.read_api_secret' => null]);

        $this->assertNull((new ZenaBoqIntegrationService())->fetchLatestQuote('PRJ-001'));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Unit/ZenaBoqIntegrationServiceTest.php`
Expected: FAIL — `App\Services\ZenaBoqIntegrationService` does not exist.

- [ ] **Step 3: Write the service**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only integration with zena-boq-core (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 2).
 * ZenaManage never writes pricing data to zena-boq-core — only GET.
 */
class ZenaBoqIntegrationService
{
    /**
     * Fail-closed: both an empty configured name AND a configured name that
     * resolves to no Tenant row must deny, never accidentally match.
     */
    public function isTenantAuthorized(string $tenantId): bool
    {
        $configuredName = config('zena_boq.integration_tenant_name');

        if (empty($configuredName)) {
            return false;
        }

        $resolvedId = Tenant::where('name', $configuredName)->value('id');

        if (!$resolvedId) {
            return false;
        }

        return (string) $resolvedId === $tenantId;
    }

    /**
     * @return array{id: string, subtotal: float, vat_amount: float, total: float, status: string, calibration: string, issued_at: ?string}|null
     */
    public function fetchLatestQuote(string $projectCode): ?array
    {
        $baseUrl = rtrim((string) config('zena_boq.base_url'), '/');
        $secret = (string) config('zena_boq.read_api_secret');

        if ($baseUrl === '' || $secret === '') {
            Log::warning('zena_boq.sync_skipped_missing_config');

            return null;
        }

        try {
            $projectResponse = Http::timeout(5)
                ->withToken($secret)
                ->get("{$baseUrl}/api/external/projects/{$projectCode}");

            if (!$projectResponse->successful()) {
                Log::warning('zena_boq.project_fetch_failed', [
                    'status' => $projectResponse->status(),
                    'project_code' => $projectCode,
                ]);

                return null;
            }

            $quoteResponse = Http::timeout(5)
                ->withToken($secret)
                ->get("{$baseUrl}/api/external/quotes/latest", ['projectCode' => $projectCode]);

            if (!$quoteResponse->successful()) {
                Log::warning('zena_boq.quote_fetch_failed', [
                    'status' => $quoteResponse->status(),
                    'project_code' => $projectCode,
                ]);

                return null;
            }

            $quote = $quoteResponse->json();

            return [
                'id' => (string) ($quote['id'] ?? ''),
                'subtotal' => (float) ($quote['subtotal'] ?? 0),
                'vat_amount' => (float) ($quote['vatAmount'] ?? 0),
                'total' => (float) ($quote['total'] ?? 0),
                'status' => (string) ($quote['status'] ?? ''),
                'calibration' => (string) ($quote['calibration'] ?? ''),
                'issued_at' => $quote['issuedAt'] ?? null,
            ];
        } catch (Throwable $e) {
            Log::error('zena_boq.sync_exception', [
                'error' => $e->getMessage(),
                'project_code' => $projectCode,
            ]);

            return null;
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Unit/ZenaBoqIntegrationServiceTest.php`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/ZenaBoqIntegrationService.php tests/Unit/ZenaBoqIntegrationServiceTest.php
git commit -m "feat(zena-boq): add ZenaBoqIntegrationService with fail-closed tenant gate"
```

---

### Task 4: `Api\OpportunityController` — `linkExternalBoqProject`

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php`
- Modify: `routes/api_zena.php:355-360` (add one route)
- Modify: `tests/Feature/Api/CrmApiTest.php` (add tests)

**Interfaces:**
- Consumes: `ZenaBoqIntegrationService::isTenantAuthorized()` (Task 3), existing `tenantId()`/`scopedQuery()`/`serialize()` helpers on `OpportunityController` (unchanged, reused as-is).
- Produces: `OpportunityController::linkExternalBoqProject(Request $request, string $id): JsonResponse`.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Api/CrmApiTest.php` (inside the class):

```php
    public function test_can_link_opportunity_to_boq_project_when_tenant_authorized(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(200)->assertJsonPath('data.external_boq_project_code', 'PRJ-001');
        $this->assertDatabaseHas('opportunities', [
            'id' => (string) $opportunity->id,
            'external_boq_project_code' => 'PRJ-001',
        ]);
    }

    public function test_link_denied_for_non_authorized_tenant(): void
    {
        // Deliberately do not create/configure a Z.E.N.A tenant matching $this->tenantA.
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(403);
        $this->assertDatabaseHas('opportunities', [
            'id' => (string) $opportunity->id,
            'external_boq_project_code' => null,
        ]);
    }

    public function test_link_fails_closed_when_config_unset(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => null]);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-link', ['id' => $opportunity->id]), [
            'external_boq_project_code' => 'PRJ-001',
        ], $this->headersFor($this->userA));

        $response->assertStatus(403);
    }
```

(`createOpportunity()` is an existing private helper already in this file — reused as-is, not redefined.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php`
Expected: FAIL — route `api.zena.crm.opportunities.boq-link` not defined.

- [ ] **Step 3: Add the method**

In `app/Http/Controllers/Api/OpportunityController.php`, add this import alongside the existing ones:

```php
use App\Services\ZenaBoqIntegrationService;
```

Add this method after `convert()` (the last existing method, before the class's closing `}`):

```php
    public function linkExternalBoqProject(Request $request, string $id, ZenaBoqIntegrationService $boqService): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$boqService->isTenantAuthorized($tenantId)) {
            return $this->forbidden('This tenant is not authorized for the zena-boq-core integration');
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        $validator = Validator::make($request->all(), [
            'external_boq_project_code' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors());
        }

        $opportunity->external_boq_project_code = (string) $request->input('external_boq_project_code');
        $opportunity->save();

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            'Opportunity linked to zena-boq-core project successfully'
        );
    }
```

Also add the four new columns to `RESPONSE_FIELDS`. Find:

```php
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
        'created_by',
        'created_at',
        'updated_at',
    ];
```

Replace with:

```php
    private const RESPONSE_FIELDS = [
        'id',
        'tenant_id',
        'account_id',
        'opportunity_name',
        'service_category',
        'service_scope_summary',
        'pipeline_stage',
        'forecast_category',
        'estimated_fee',
        'estimated_project_value',
        'probability',
        'expected_close_date',
        'sales_owner_id',
        'technical_owner_id',
        'priority',
        'lost_reason',
        'converted_project_id',
        'created_by',
        'external_boq_project_code',
        'external_quote_id',
        'external_quote_snapshot',
        'external_quote_synced_at',
        'created_at',
        'updated_at',
    ];
```

- [ ] **Step 4: Add the route**

In `routes/api_zena.php`, find:

```php
            Route::post('/opportunities/{id}/convert', [\App\Http\Controllers\Api\OpportunityController::class, 'convert'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
```

Replace with:

```php
            Route::post('/opportunities/{id}/convert', [\App\Http\Controllers\Api\OpportunityController::class, 'convert'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
            Route::post('/opportunities/{id}/boq-link', [\App\Http\Controllers\Api\OpportunityController::class, 'linkExternalBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php`
Expected: PASS (all previous CrmApiTest tests + 3 new ones)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php routes/api_zena.php tests/Feature/Api/CrmApiTest.php
git commit -m "feat(zena-boq): add linkExternalBoqProject API endpoint with tenant gate"
```

---

### Task 5: `Api\OpportunityController` — `syncExternalQuote`

**Files:**
- Modify: `app/Http/Controllers/Api/OpportunityController.php`
- Modify: `routes/api_zena.php` (add one route)
- Modify: `tests/Feature/Api/CrmApiTest.php` (add tests)

**Interfaces:**
- Consumes: `ZenaBoqIntegrationService::isTenantAuthorized()` + `fetchLatestQuote()` (Task 3), `recordEvent()` (existing private helper on `OpportunityController`, reused as-is).
- Produces: `OpportunityController::syncExternalQuote(Request $request, string $id, ZenaBoqIntegrationService $boqService): JsonResponse`.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Api/CrmApiTest.php`:

```php
    public function test_sync_populates_snapshot_on_success(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1',
                'subtotal' => 100000000,
                'vatAmount' => 8000000,
                'total' => 108000000,
                'status' => 'ISSUED',
                'calibration' => 'UNCALIBRATED',
                'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(200)
            ->assertJsonPath('data.external_quote_snapshot.total', 108000000)
            ->assertJsonPath('data.external_quote_snapshot.calibration', 'UNCALIBRATED');

        $opportunity->refresh();
        $this->assertNotNull($opportunity->external_quote_synced_at);
        $this->assertSame('quote_1', $opportunity->external_quote_id);
    }

    public function test_sync_degrades_gracefully_when_zena_boq_unreachable(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/*' => \Illuminate\Support\Facades\Http::response(null, 500),
        ]);

        $opportunity = $this->createOpportunity([
            'external_boq_project_code' => 'PRJ-001',
            'external_quote_snapshot' => ['total' => 999, 'status' => 'ISSUED'],
        ]);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        // Must not 500; must not wipe out the existing cached snapshot.
        $response->assertStatus(200);
        $opportunity->refresh();
        $this->assertSame(999.0, (float) $opportunity->external_quote_snapshot['total']);
    }

    public function test_sync_requires_project_code_to_be_linked_first(): void
    {
        $this->tenantA->update(['name' => 'Z.E.N.A']);
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity();

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(422);
    }

    public function test_sync_denied_for_non_authorized_tenant(): void
    {
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $opportunity = $this->createOpportunity(['external_boq_project_code' => 'PRJ-001']);

        $response = $this->postJson($this->route('opportunities.boq-sync', ['id' => $opportunity->id]), [], $this->headersFor($this->userA));

        $response->assertStatus(403);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php`
Expected: FAIL — route `api.zena.crm.opportunities.boq-sync` not defined.

- [ ] **Step 3: Add the method**

In `app/Http/Controllers/Api/OpportunityController.php`, add after `linkExternalBoqProject()`:

```php
    public function syncExternalQuote(Request $request, string $id, ZenaBoqIntegrationService $boqService): JsonResponse
    {
        if (!Auth::check()) {
            return $this->unauthorized('Authentication required');
        }

        $tenantId = $this->tenantId($request);
        if ($tenantId === '') {
            return $this->errorResponse('Tenant context missing', 400);
        }

        if (!$boqService->isTenantAuthorized($tenantId)) {
            return $this->forbidden('This tenant is not authorized for the zena-boq-core integration');
        }

        $opportunity = $this->scopedQuery($tenantId)->whereKey($id)->first();

        if (!$opportunity instanceof Opportunity) {
            return $this->notFound('Opportunity not found');
        }

        $this->authorize('update', $opportunity);

        if (!$opportunity->external_boq_project_code) {
            return $this->validationError([
                'external_boq_project_code' => ['Link this opportunity to a zena-boq-core project before syncing.'],
            ]);
        }

        $quote = $boqService->fetchLatestQuote((string) $opportunity->external_boq_project_code);

        if ($quote !== null) {
            $opportunity->external_quote_id = $quote['id'];
            $opportunity->external_quote_snapshot = [
                'subtotal' => $quote['subtotal'],
                'vat_amount' => $quote['vat_amount'],
                'total' => $quote['total'],
                'status' => $quote['status'],
                'calibration' => $quote['calibration'],
                'issued_at' => $quote['issued_at'],
            ];
            $opportunity->external_quote_synced_at = now();
            $opportunity->save();

            $this->recordEvent($opportunity, 'crm.opportunity.boq_synced', [
                'external_quote_id' => $quote['id'],
                'total' => $quote['total'],
            ]);
        }
        // $quote === null: zena-boq-core unreachable or returned an error. Degrade gracefully —
        // keep whatever was already cached and do not overwrite external_quote_synced_at, so the
        // UI can keep showing "last synced at X" instead of silently going blank. Never a 500 here.

        return $this->zenaSuccessResponse(
            $this->serialize($opportunity->fresh() ?? $opportunity),
            $quote !== null ? 'Quote synced successfully' : 'Could not reach zena-boq-core — showing last synced data'
        );
    }
```

- [ ] **Step 4: Add the route**

In `routes/api_zena.php`, find:

```php
            Route::post('/opportunities/{id}/boq-link', [\App\Http\Controllers\Api\OpportunityController::class, 'linkExternalBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
```

Replace with:

```php
            Route::post('/opportunities/{id}/boq-link', [\App\Http\Controllers\Api\OpportunityController::class, 'linkExternalBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
            Route::post('/opportunities/{id}/boq-sync', [\App\Http\Controllers\Api\OpportunityController::class, 'syncExternalQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/CrmApiTest.php`
Expected: PASS (all previous tests + 4 new ones)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/OpportunityController.php routes/api_zena.php tests/Feature/Api/CrmApiTest.php
git commit -m "feat(zena-boq): add syncExternalQuote API endpoint with graceful degradation"
```

---

### Task 6: `Web\CrmPageController` + view — link/sync UI on the Opportunity page

**Files:**
- Modify: `app/Http/Controllers/Web/CrmPageController.php`
- Modify: `resources/views/crm/opportunity-show.blade.php`
- Modify: `routes/web.php:966-968` (add two routes)
- Modify: `tests/Feature/Zena/OperatorCrmUiTest.php` (add tests)

**Interfaces:**
- Consumes: `Api\OpportunityController::linkExternalBoqProject`/`syncExternalQuote` (Tasks 4-5), `DelegatesToApiControllers` trait (existing, unchanged — no file uploads needed here so the original 2-argument `buildApiRequest()` signature is sufficient), `ZenaBoqIntegrationService::isTenantAuthorized()` (Task 3).
- Produces: `CrmPageController::linkBoqProject`/`syncBoqQuote` methods; `showOpportunity()` gains a `boqIntegrationEnabled` view variable.

- [ ] **Step 1: Add failing tests**

Append to `tests/Feature/Zena/OperatorCrmUiTest.php` (inside the class):

```php
    public function test_boq_link_and_sync_ui_flow_for_authorized_tenant(): void
    {
        $this->tenant->update(['name' => 'Z.E.N.A']);
        config([
            'zena_boq.integration_tenant_name' => 'Z.E.N.A',
            'zena_boq.base_url' => 'https://zena-boq.example',
            'zena_boq.read_api_secret' => 'test-secret',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'https://zena-boq.example/api/external/projects/*' => \Illuminate\Support\Facades\Http::response(['id' => 'proj_1'], 200),
            'https://zena-boq.example/api/external/quotes/latest*' => \Illuminate\Support\Facades\Http::response([
                'id' => 'quote_1', 'subtotal' => 100000000, 'vatAmount' => 8000000, 'total' => 108000000,
                'status' => 'ISSUED', 'calibration' => 'UNCALIBRATED', 'issuedAt' => '2026-07-10T00:00:00Z',
            ], 200),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tich hop BOQ',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi tich hop BOQ',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk();

        $link = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.boq-link', $opportunity->id), [
                'external_boq_project_code' => 'PRJ-001',
            ], $headers);
        $link->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertSame('PRJ-001', $opportunity->external_boq_project_code);

        $sync = $this->actingAs($this->user)
            ->post(route('operator.crm.opportunities.boq-sync', $opportunity->id), [], $headers);
        $sync->assertRedirect(route('operator.crm.opportunities.show', $opportunity->id));

        $opportunity->refresh();
        $this->assertSame(108000000.0, (float) $opportunity->external_quote_snapshot['total']);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertSee('PRJ-001');
    }

    public function test_boq_card_is_hidden_for_non_authorized_tenant(): void
    {
        // Deliberately no Z.E.N.A tenant configured to match $this->tenant.
        config(['zena_boq.integration_tenant_name' => 'Z.E.N.A']);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $account = \App\Models\Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => \App\Models\Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang khong tich hop',
            'status' => \App\Models\Account::STATUS_ACTIVE,
        ]);

        $opportunity = \App\Models\Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi khong co BOQ',
            'service_category' => 'architecture',
            'pipeline_stage' => \App\Models\Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.opportunities.show', $opportunity->id), $headers)
            ->assertOk()
            ->assertDontSee('external_boq_project_code')
            ->assertDontSee('Đồng bộ báo giá');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: FAIL — routes `operator.crm.opportunities.boq-link`/`boq-sync` not defined.

- [ ] **Step 3: Modify the Web controller**

In `app/Http/Controllers/Web/CrmPageController.php`, add this import alongside the existing ones:

```php
use App\Services\ZenaBoqIntegrationService;
```

Find:

```php
    public function showOpportunity(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => \App\Models\EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'opportunity')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }
```

Replace with:

```php
    public function showOpportunity(string $id, ZenaBoqIntegrationService $boqService): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $opportunity = Opportunity::query()
            ->forTenant($tenantId)
            ->with('account:id,tenant_id,display_name,phone,email', 'salesOwner:id,name', 'technicalOwner:id,name', 'convertedProject:id,name,code')
            ->findOrFail($id);

        $this->authorize('view', $opportunity);

        return view('crm.opportunity-show', [
            'opportunity' => $opportunity,
            'boqIntegrationEnabled' => $boqService->isTenantAuthorized($tenantId),
            'users' => User::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => \App\Models\EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'opportunity')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }
```

Add these two methods after `convertOpportunity()` (the last existing method, before the class's closing `}`):

```php
    public function linkBoqProject(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'external_boq_project_code' => ['required', 'string', 'max:100'],
        ]);

        try {
            $response = $apiController->linkExternalBoqProject($this->buildApiRequest($request, $validated), $id, app(\App\Services\ZenaBoqIntegrationService::class));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã liên kết dự án zena-boq-core');
    }

    public function syncBoqQuote(Request $request, string $id, ApiOpportunityController $apiController): RedirectResponse
    {
        try {
            $response = $apiController->syncExternalQuote($this->buildApiRequest($request), $id, app(\App\Services\ZenaBoqIntegrationService::class));
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, route('operator.crm.opportunities.show', $id), 'Đã đồng bộ báo giá');
    }
```

(`ApiOpportunityController` is the existing import alias already at the top of this file, `use App\Http\Controllers\Api\OpportunityController as ApiOpportunityController;`.)

- [ ] **Step 4: Add the routes**

In `routes/web.php`, find:

```php
    Route::post('/crm/opportunities/{id}/convert', [App\Http\Controllers\Web\CrmPageController::class, 'convertOpportunity'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
```

Replace with:

```php
    Route::post('/crm/opportunities/{id}/convert', [App\Http\Controllers\Web\CrmPageController::class, 'convertOpportunity'])->middleware('rbac:crm.convert')->name('crm.opportunities.convert');
    Route::post('/crm/opportunities/{id}/boq-link', [App\Http\Controllers\Web\CrmPageController::class, 'linkBoqProject'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-link');
    Route::post('/crm/opportunities/{id}/boq-sync', [App\Http\Controllers\Web\CrmPageController::class, 'syncBoqQuote'])->middleware('rbac:crm.manage')->name('crm.opportunities.boq-sync');
```

- [ ] **Step 5: Add the view card**

In `resources/views/crm/opportunity-show.blade.php`, find:

```blade
    <x-ui.card title="Lịch sử">
```

Insert immediately before it:

```blade
    @if ($boqIntegrationEnabled)
        <x-ui.card title="Báo giá — zena-boq-core">
            @if ($opportunity->external_boq_project_code)
                <div class="operator-form-grid">
                    <x-ui.field-value label="Mã dự án" :value="$opportunity->external_boq_project_code" />
                    <x-ui.field-value label="Trạng thái" :value="$opportunity->external_quote_snapshot['status'] ?? '—'" />
                    <x-ui.field-value label="Hiệu chỉnh giá" :value="$opportunity->external_quote_snapshot['calibration'] ?? '—'" />
                    <x-ui.field-value label="Tổng tiền" :value="isset($opportunity->external_quote_snapshot['total']) ? number_format((float) $opportunity->external_quote_snapshot['total'], 0, ',', '.') . '₫' : '—'" />
                    <x-ui.field-value label="Đồng bộ lần cuối" :value="optional($opportunity->external_quote_synced_at)->format('d/m/Y H:i') ?? 'Chưa đồng bộ'" />
                </div>

                <form method="POST" action="{{ route('operator.crm.opportunities.boq-sync', $opportunity->id) }}" class="mt-3">
                    @csrf
                    <button type="submit" class="operator-button operator-button-primary">Đồng bộ báo giá</button>
                </form>
            @else
                <form method="POST" action="{{ route('operator.crm.opportunities.boq-link', $opportunity->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    <div class="operator-field flex-1 min-w-64">
                        <label for="external_boq_project_code">Mã dự án zena-boq-core</label>
                        <input id="external_boq_project_code" name="external_boq_project_code" type="text" class="operator-input" value="{{ old('external_boq_project_code') }}" required placeholder="vd: PRJ-001">
                    </div>
                    <button type="submit" class="operator-button operator-button-primary">Liên kết</button>
                </form>
            @endif
        </x-ui.card>
    @endif

    <x-ui.card title="Lịch sử">
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS (all previous tests + 2 new ones)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Web/CrmPageController.php resources/views/crm/opportunity-show.blade.php routes/web.php tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "feat(zena-boq): add link/sync UI to the Opportunity page, hidden for non-authorized tenants"
```

---

### Task 7: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including this plan's new tests (~3 in Task 1, ~7 in Task 3, ~3 in Task 4, ~4 in Task 5, ~2 in Task 6 — roughly +19 over the pre-Phase-2 baseline).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. `ZenaBoqIntegrationService` is a `Services` layer class per `deptrac.yaml`'s existing rules (`Services: [Models, Jobs]`) — it depends only on `Tenant` (a Model) and the `Http` facade (not tracked by Deptrac), so it should satisfy the ruleset with no changes to `deptrac.yaml`. `Api\OpportunityController` calling into `App\Services\ZenaBoqIntegrationService` matches the existing `ApiControllers: [Models, Services, Jobs]` rule. If a violation appears, it means a dependency was drawn in the wrong direction — fix the direction, don't add a `skip_violations` entry.

- [ ] **Step 3: Manually verify the seeder runs standalone**

Run: `php artisan db:seed --class=ZenaBoqTenantSeeder --force`
Expected: runs without error; re-running it a second time also succeeds without creating a duplicate (`firstOrCreate` is idempotent by design).

- [ ] **Step 4: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(zena-boq): confirm full suite and Deptrac are green"
```

(Skip this commit if steps 1-3 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 2 section as revised through §7):
- Tenant gate (name-based resolution, both fail-closed modes), Z.E.N.A tenant seeder (not migration), two separate config-driven values (`integration_tenant_name`, `read_api_secret`, `base_url`), mocked-HTTP-only testing, graceful degradation on `zena-boq-core` unreachability, `external_boq_project_code`/`external_quote_snapshot` excluded from the generic `update()` path, webhook explicitly not built — all covered by Tasks 1-6.
- The "Out of scope: any UI for creating/editing quotes" boundary is respected — Task 6's view only displays cached snapshot fields plainly and links out to nothing (no Phase-3-style external link or staleness-warning styling is built here; that's explicitly Phase 3's job per the roadmap).

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code.

**Type/signature consistency check:** `ZenaBoqIntegrationService::isTenantAuthorized(string): bool` and `fetchLatestQuote(string): ?array` (Task 3) are called identically in Tasks 4, 5, and 6 (both directly in the Api controller and via `app(ZenaBoqIntegrationService::class)` in the Web controller). The `external_quote_snapshot` array shape (`subtotal`, `vat_amount`, `total`, `status`, `calibration`, `issued_at`) is produced identically by Task 5's `syncExternalQuote()` and consumed identically by Task 6's Blade view. Route names (`crm.opportunities.boq-link`/`boq-sync` for both API and Web, matching the existing `crm.opportunities.stage`/`convert` naming convention) are used consistently between the route-registration steps and the test files that call `route(...)`/`$this->route(...)`.
