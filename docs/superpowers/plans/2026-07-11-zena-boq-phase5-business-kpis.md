# Phase 5 — BI Dashboard điều hành Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** add five real, cached, tenant-scoped business KPIs on a new dedicated "Báo cáo kinh doanh" page — not extending the dead, mock-only `KpiService`, and not mixing into the existing operational dashboard.

**Architecture:** A new `App\Services\BusinessKpiService` computes all five KPIs against real Eloquent queries, each independently wrapped in `Cache::remember(..., 60, ...)`. A new `Web\CrmReportController::index()` renders them on a new page nested under the existing CRM URL namespace (`/operator/crm/reports`), gated by the already-existing `crm.view` permission.

**Tech Stack:** Laravel 12, existing `Opportunity`/`ContractPayment` models, existing `<x-ui.card>`/`<x-ui.field-value>` Blade components, file-based `Cache` facade.

## Global Constraints

- **Do not touch `App\Services\KpiService` or `App\Http\Controllers\KpiController`.** They are confirmed dead code (100% mock data, gated behind `rbac:admin` at an unrouted-in-UI `/api/v1/universal-frame/` prefix) — out of scope for this phase, left exactly as-is.
- **Do not add anything to `Web\AppController::dashboard()` or `resources/views/app/dashboard.blade.php`.** The five new KPIs live on their own new page, not mixed into the operational task/project dashboard.
- **Every KPI method is independently wrapped in `Cache::remember("business_kpi_{key}_{$tenantId}", 60, ...)`** — a cache key per KPI per tenant, 60-second TTL, matching the spec's caching intent (even though the original `KpiService` never actually implemented it for real).
- **Revenue rule** (used by two of the five KPIs): `(float) ($opportunity->external_quote_snapshot['total'] ?? $opportunity->estimated_fee ?? 0)` — prefer the synced quote's real total when present (from Phase 2/3, only populated for BOQ-integrated tenants), else fall back to `estimated_fee`.
- **"Overdue" for the debt KPI is computed live** (`due_date < now()`) — never read from `ContractPayment::STATUS_OVERDUE`, since nothing in this codebase ever sets that value.
- **Monthly revenue groups by the month of `updated_at`** — there is no dedicated "won at" timestamp on `Opportunity`; do not add one for this phase.
- **New route nests under the existing CRM URL namespace**, not the existing unrelated `/operator/reports` CSV-export tool (`Web\ReportPageController`, a completely different feature — raw dataset exports, not KPI cards) — do not modify that controller or its routes.
- **Gated by the existing `crm.view` permission** — no new permission to create.
- `declare(strict_types=1)` at the top of every PHP file touched or created.
- Tests must `Cache::flush();` in `setUp()`, matching the established convention in `tests/Unit/CacheServiceTest.php`.
- Money formatting uses the existing convention: `number_format($value, 0, ',', '.') . '₫'`.

---

### Task 1: `App\Services\BusinessKpiService` — the five KPIs

**Files:**
- Create: `app/Services/BusinessKpiService.php`
- Test: `tests/Unit/Services/BusinessKpiServiceTest.php`

**Interfaces:**
- Consumes: `Opportunity` (existing, with `pipeline_stage`, `estimated_fee`, `external_quote_snapshot`, `sales_owner_id`, `service_category`, `tenant_id`), `ContractPayment` (existing, with `status`, `due_date`, `amount`, `tenant_id`).
- Produces: `BusinessKpiService::monthlyRevenue(string $tenantId): array` (keyed `'YYYY-MM' => float`, sorted ascending), `::pipelineByStage(string $tenantId): array` (keyed by stage constant => float), `::outstandingDebt(string $tenantId): array` (`{'total': float, 'overdue_total': float, 'overdue_count': int}`), `::salesWinRate(string $tenantId): array` (keyed by `sales_owner_id` => `{'won': int, 'total': int, 'rate': float}`), `::serviceCategoryPerformance(string $tenantId): array` (keyed by `service_category` => `{'won': int, 'total': int, 'rate': float, 'avg_fee': float}`). Task 2 consumes all five.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/BusinessKpiServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessKpiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BusinessKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $userA;
    private User $userB;
    private Account $account;
    private BusinessKpiService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new BusinessKpiService();
        $this->tenant = Tenant::factory()->create();

        $this->userA = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Sale A']);
        $this->userB = User::factory()->create(['tenant_id' => (string) $this->tenant->id, 'name' => 'Sale B']);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang KPI',
            'status' => Account::STATUS_ACTIVE,
        ]);
    }

    private function createOpportunity(array $overrides = []): Opportunity
    {
        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Test opportunity',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $this->userA->id,
            'created_by' => (string) $this->userA->id,
        ], $overrides));
    }

    public function test_monthly_revenue_prefers_quote_total_over_estimated_fee(): void
    {
        $this->createOpportunity([
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 100000000,
            'external_quote_snapshot' => ['total' => 150000000, 'status' => 'ACCEPTED'],
        ]);
        $this->createOpportunity([
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 50000000,
        ]);
        $this->createOpportunity([
            'pipeline_stage' => Opportunity::STAGE_QUALIFIED,
            'estimated_fee' => 999999999,
        ]);

        $result = $this->service->monthlyRevenue((string) $this->tenant->id);
        $month = now()->format('Y-m');

        $this->assertArrayHasKey($month, $result);
        $this->assertSame(200000000.0, $result[$month]);
    }

    public function test_pipeline_by_stage_sums_estimated_fee_grouped_by_stage(): void
    {
        $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_QUALIFIED, 'estimated_fee' => 10000000]);
        $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_QUALIFIED, 'estimated_fee' => 20000000]);
        $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_WON, 'estimated_fee' => 5000000]);

        $result = $this->service->pipelineByStage((string) $this->tenant->id);

        $this->assertSame(30000000.0, $result[Opportunity::STAGE_QUALIFIED]);
        $this->assertSame(5000000.0, $result[Opportunity::STAGE_WON]);
    }

    public function test_outstanding_debt_separates_overdue_from_total(): void
    {
        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an KPI',
            'code' => 'PRJ-KPITEST1',
            'status' => 'planning',
        ]);
        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-KPITEST1',
            'title' => 'Hop dong KPI',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'amount' => 20000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(5),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'amount' => 30000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(5),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'amount' => 999999,
            'status' => ContractPayment::STATUS_PAID,
            'due_date' => now()->subDays(10),
        ]);

        $result = $this->service->outstandingDebt((string) $this->tenant->id);

        $this->assertSame(50000000.0, $result['total']);
        $this->assertSame(20000000.0, $result['overdue_total']);
        $this->assertSame(1, $result['overdue_count']);
    }

    public function test_sales_win_rate_grouped_by_sales_owner(): void
    {
        $this->createOpportunity(['sales_owner_id' => (string) $this->userA->id, 'pipeline_stage' => Opportunity::STAGE_WON]);
        $this->createOpportunity(['sales_owner_id' => (string) $this->userA->id, 'pipeline_stage' => Opportunity::STAGE_LOST]);
        $this->createOpportunity(['sales_owner_id' => (string) $this->userB->id, 'pipeline_stage' => Opportunity::STAGE_WON]);
        $this->createOpportunity(['sales_owner_id' => (string) $this->userB->id, 'pipeline_stage' => Opportunity::STAGE_QUALIFIED]);

        $result = $this->service->salesWinRate((string) $this->tenant->id);

        $this->assertSame(1, $result[(string) $this->userA->id]['won']);
        $this->assertSame(2, $result[(string) $this->userA->id]['total']);
        $this->assertSame(0.5, $result[(string) $this->userA->id]['rate']);

        $this->assertSame(1, $result[(string) $this->userB->id]['won']);
        $this->assertSame(1, $result[(string) $this->userB->id]['total']);
        $this->assertSame(1.0, $result[(string) $this->userB->id]['rate']);
    }

    public function test_service_category_performance_computes_win_rate_and_avg_fee(): void
    {
        $this->createOpportunity(['service_category' => 'architecture', 'pipeline_stage' => Opportunity::STAGE_WON, 'estimated_fee' => 100000000]);
        $this->createOpportunity(['service_category' => 'architecture', 'pipeline_stage' => Opportunity::STAGE_WON, 'estimated_fee' => 200000000]);
        $this->createOpportunity(['service_category' => 'architecture', 'pipeline_stage' => Opportunity::STAGE_LOST, 'estimated_fee' => 50000000]);

        $result = $this->service->serviceCategoryPerformance((string) $this->tenant->id);

        $this->assertSame(2, $result['architecture']['won']);
        $this->assertSame(3, $result['architecture']['total']);
        $this->assertEqualsWithDelta(2 / 3, $result['architecture']['rate'], 0.0001);
        $this->assertSame(150000000.0, $result['architecture']['avg_fee']);
    }

    public function test_kpis_are_tenant_isolated(): void
    {
        $otherTenant = Tenant::factory()->create();
        Opportunity::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Other tenant opportunity',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 999999999,
            'sales_owner_id' => (string) $this->userA->id,
            'created_by' => (string) $this->userA->id,
        ]);

        $result = $this->service->pipelineByStage((string) $this->tenant->id);

        $this->assertArrayNotHasKey(Opportunity::STAGE_WON, $result);
    }

    public function test_results_are_cached_for_60_seconds(): void
    {
        $this->createOpportunity(['pipeline_stage' => Opportunity::STAGE_WON, 'estimated_fee' => 10000000]);

        $first = $this->service->pipelineByStage((string) $this->tenant->id);

        Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Should not appear yet',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 500000000,
            'sales_owner_id' => (string) $this->userA->id,
            'created_by' => (string) $this->userA->id,
        ]);

        $second = $this->service->pipelineByStage((string) $this->tenant->id);

        $this->assertSame($first[Opportunity::STAGE_WON], $second[Opportunity::STAGE_WON]);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Services/BusinessKpiServiceTest.php`
Expected: FAIL — `App\Services\BusinessKpiService` doesn't exist yet.

- [ ] **Step 3: Create the service**

Create `app/Services/BusinessKpiService.php`:

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ContractPayment;
use App\Models\Opportunity;
use Illuminate\Support\Facades\Cache;

class BusinessKpiService
{
    private const TERMINAL_LOST_STAGES = [
        Opportunity::STAGE_WON,
        Opportunity::STAGE_LOST,
        Opportunity::STAGE_NO_BID,
    ];

    /**
     * @return array<string, float>
     */
    public function monthlyRevenue(string $tenantId): array
    {
        return Cache::remember("business_kpi_monthly_revenue_{$tenantId}", 60, function () use ($tenantId): array {
            $result = [];

            Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->where('pipeline_stage', Opportunity::STAGE_WON)
                ->get(['updated_at', 'estimated_fee', 'external_quote_snapshot'])
                ->each(function (Opportunity $opportunity) use (&$result): void {
                    $month = $opportunity->updated_at->format('Y-m');
                    $revenue = $this->revenueFor($opportunity);
                    $result[$month] = ($result[$month] ?? 0.0) + $revenue;
                });

            ksort($result);

            return $result;
        });
    }

    /**
     * @return array<string, float>
     */
    public function pipelineByStage(string $tenantId): array
    {
        return Cache::remember("business_kpi_pipeline_by_stage_{$tenantId}", 60, function () use ($tenantId): array {
            return Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('pipeline_stage, SUM(estimated_fee) as total')
                ->groupBy('pipeline_stage')
                ->pluck('total', 'pipeline_stage')
                ->map(fn ($value) => (float) $value)
                ->toArray();
        });
    }

    /**
     * @return array{total: float, overdue_total: float, overdue_count: int}
     */
    public function outstandingDebt(string $tenantId): array
    {
        return Cache::remember("business_kpi_outstanding_debt_{$tenantId}", 60, function () use ($tenantId): array {
            $unpaid = ContractPayment::query()
                ->where('tenant_id', $tenantId)
                ->where('status', '!=', ContractPayment::STATUS_PAID);

            $total = (float) (clone $unpaid)->sum('amount');

            $overdue = (clone $unpaid)->where('due_date', '<', now());

            return [
                'total' => $total,
                'overdue_total' => (float) (clone $overdue)->sum('amount'),
                'overdue_count' => (int) $overdue->count(),
            ];
        });
    }

    /**
     * @return array<string, array{won: int, total: int, rate: float}>
     */
    public function salesWinRate(string $tenantId): array
    {
        return Cache::remember("business_kpi_sales_win_rate_{$tenantId}", 60, function () use ($tenantId): array {
            $rows = Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('pipeline_stage', self::TERMINAL_LOST_STAGES)
                ->whereNotNull('sales_owner_id')
                ->get(['sales_owner_id', 'pipeline_stage']);

            $result = [];

            foreach ($rows->groupBy('sales_owner_id') as $ownerId => $group) {
                $total = $group->count();
                $won = $group->where('pipeline_stage', Opportunity::STAGE_WON)->count();

                $result[(string) $ownerId] = [
                    'won' => $won,
                    'total' => $total,
                    'rate' => $total > 0 ? $won / $total : 0.0,
                ];
            }

            return $result;
        });
    }

    /**
     * @return array<string, array{won: int, total: int, rate: float, avg_fee: float}>
     */
    public function serviceCategoryPerformance(string $tenantId): array
    {
        return Cache::remember("business_kpi_service_category_performance_{$tenantId}", 60, function () use ($tenantId): array {
            $rows = Opportunity::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('pipeline_stage', self::TERMINAL_LOST_STAGES)
                ->whereNotNull('service_category')
                ->get(['service_category', 'pipeline_stage', 'estimated_fee', 'external_quote_snapshot']);

            $result = [];

            foreach ($rows->groupBy('service_category') as $category => $group) {
                $total = $group->count();
                $wonOpportunities = $group->where('pipeline_stage', Opportunity::STAGE_WON);
                $won = $wonOpportunities->count();
                $avgFee = $won > 0
                    ? $wonOpportunities->sum(fn (Opportunity $opportunity) => $this->revenueFor($opportunity)) / $won
                    : 0.0;

                $result[(string) $category] = [
                    'won' => $won,
                    'total' => $total,
                    'rate' => $total > 0 ? $won / $total : 0.0,
                    'avg_fee' => $avgFee,
                ];
            }

            return $result;
        });
    }

    private function revenueFor(Opportunity $opportunity): float
    {
        $snapshot = $opportunity->external_quote_snapshot ?? [];

        return (float) ($snapshot['total'] ?? $opportunity->estimated_fee ?? 0);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Services/BusinessKpiServiceTest.php`
Expected: PASS (7/7).

- [ ] **Step 5: Commit**

```bash
git add app/Services/BusinessKpiService.php tests/Unit/Services/BusinessKpiServiceTest.php
git commit -m "feat(zena-boq): add BusinessKpiService with 5 real, cached, tenant-scoped CRM KPIs"
```

---

### Task 2: `Web\CrmReportController` — the new "Báo cáo kinh doanh" page

**Files:**
- Create: `app/Http/Controllers/Web/CrmReportController.php`
- Create: `resources/views/crm/report.blade.php`
- Modify: `routes/web.php` (add one route, near the existing CRM routes block)
- Modify: `resources/views/layouts/operator.blade.php` (add one nav link)
- Test: `tests/Feature/Zena/CrmReportPageTest.php`

**Interfaces:**
- Consumes: `BusinessKpiService` (Task 1) — all five public methods.
- Produces: `GET /operator/crm/reports`, route name `operator.crm.reports`, gated by `rbac:crm.view`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Zena/CrmReportPageTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class CrmReportPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['sales'], ['crm.view']);
    }

    public function test_report_page_renders_real_kpi_data(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang bao cao',
            'status' => Account::STATUS_ACTIVE,
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi bao cao',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 123000000,
            'sales_owner_id' => (string) $this->viewer->id,
            'created_by' => (string) $this->viewer->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->viewer)
            ->get(route('operator.crm.reports'), $headers)
            ->assertOk()
            ->assertSee('123.000.000', false);
    }

    public function test_report_page_requires_crm_view_permission(): void
    {
        $noAccess = $this->createTenantUser($this->tenant, [], ['staff'], []);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($noAccess)
            ->get(route('operator.crm.reports'), $headers)
            ->assertForbidden();
    }

    public function test_report_page_is_tenant_isolated(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tenant khac',
            'status' => Account::STATUS_ACTIVE,
        ]);
        Opportunity::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_id' => (string) $otherAccount->id,
            'opportunity_name' => 'Co hoi tenant khac',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'estimated_fee' => 987654321,
            'sales_owner_id' => (string) $this->viewer->id,
            'created_by' => (string) $this->viewer->id,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->viewer)
            ->get(route('operator.crm.reports'), $headers)
            ->assertOk()
            ->assertDontSee('987.654.321', false);
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Zena/CrmReportPageTest.php`
Expected: FAIL — route `operator.crm.reports` doesn't exist.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Web/CrmReportController.php`:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BusinessKpiService;
use Illuminate\Contracts\View\View;

class CrmReportController extends Controller
{
    public function index(BusinessKpiService $kpiService): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        return view('crm.report', [
            'monthlyRevenue' => $kpiService->monthlyRevenue($tenantId),
            'pipelineByStage' => $kpiService->pipelineByStage($tenantId),
            'outstandingDebt' => $kpiService->outstandingDebt($tenantId),
            'salesWinRate' => $kpiService->salesWinRate($tenantId),
            'serviceCategoryPerformance' => $kpiService->serviceCategoryPerformance($tenantId),
        ]);
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/web.php`, find:

```php
    Route::post('/crm/opportunities/{id}/create-contract', [App\Http\Controllers\Web\CrmPageController::class, 'createContract'])->middleware('rbac:crm.manage')->name('crm.opportunities.create-contract');
});
```

Replace with:

```php
    Route::post('/crm/opportunities/{id}/create-contract', [App\Http\Controllers\Web\CrmPageController::class, 'createContract'])->middleware('rbac:crm.manage')->name('crm.opportunities.create-contract');
    Route::get('/crm/reports', [App\Http\Controllers\Web\CrmReportController::class, 'index'])->middleware('rbac:crm.view')->name('crm.reports');
});
```

- [ ] **Step 5: Create the Blade view**

Create `resources/views/crm/report.blade.php`:

```blade
@extends('layouts.operator')

@section('title', 'Báo cáo kinh doanh')
@section('page_title', 'Báo cáo kinh doanh')

@section('content')
    <div class="space-y-6">
        <x-ui.card title="Doanh số theo tháng">
            @if (empty($monthlyRevenue))
                <p class="text-sm text-slate-500">Chưa có doanh số (chưa có cơ hội nào thắng).</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($monthlyRevenue as $month => $total)
                        <x-ui.field-value :label="$month" :value="number_format($total, 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Giá trị pipeline theo giai đoạn">
            @if (empty($pipelineByStage))
                <p class="text-sm text-slate-500">Chưa có dữ liệu pipeline.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($pipelineByStage as $stage => $total)
                        <x-ui.field-value :label="$stage" :value="number_format($total, 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Công nợ">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-ui.field-value label="Tổng công nợ" :value="number_format($outstandingDebt['total'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Quá hạn" :value="number_format($outstandingDebt['overdue_total'], 0, ',', '.') . '₫'" />
                <x-ui.field-value label="Số khoản quá hạn" :value="(string) $outstandingDebt['overdue_count']" />
            </div>
        </x-ui.card>

        <x-ui.card title="Hiệu quả sale">
            @if (empty($salesWinRate))
                <p class="text-sm text-slate-500">Chưa có dữ liệu.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($salesWinRate as $ownerId => $stats)
                        <x-ui.field-value :label="$ownerId" :value="$stats['won'] . '/' . $stats['total'] . ' (' . number_format($stats['rate'] * 100, 1) . '%)'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Hiệu quả gói dịch vụ">
            @if (empty($serviceCategoryPerformance))
                <p class="text-sm text-slate-500">Chưa có dữ liệu.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($serviceCategoryPerformance as $category => $stats)
                        <x-ui.field-value :label="$category" :value="$stats['won'] . '/' . $stats['total'] . ' (' . number_format($stats['rate'] * 100, 1) . '%) — TB ' . number_format($stats['avg_fee'], 0, ',', '.') . '₫'" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>
    </div>
@endsection
```

- [ ] **Step 6: Add the nav link**

In `resources/views/layouts/operator.blade.php`, find:

```blade
                <span class="operator-nav-section">Kinh doanh</span>
                <a href="{{ route('operator.crm.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.crm.*') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>CRM</span>
                </a>

                <span class="operator-nav-section">Mua sắm</span>
```

Replace with:

```blade
                <span class="operator-nav-section">Kinh doanh</span>
                <a href="{{ route('operator.crm.index') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.crm.*') && !request()->routeIs('operator.crm.reports') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                    <span>CRM</span>
                </a>
                <a href="{{ route('operator.crm.reports') }}"
                   class="operator-nav-link {{ request()->routeIs('operator.crm.reports') ? 'is-active' : '' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                    <span>Báo cáo kinh doanh</span>
                </a>

                <span class="operator-nav-section">Mua sắm</span>
```

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Zena/CrmReportPageTest.php`
Expected: PASS (3/3).

- [ ] **Step 8: Run the CRM + BusinessKpiService test files to confirm no regression**

Run: `php artisan test tests/Feature/Zena/CrmReportPageTest.php tests/Unit/Services/BusinessKpiServiceTest.php tests/Feature/Zena/OperatorCrmUiTest.php`
Expected: PASS across all three files.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Web/CrmReportController.php resources/views/crm/report.blade.php routes/web.php resources/views/layouts/operator.blade.php tests/Feature/Zena/CrmReportPageTest.php
git commit -m "feat(zena-boq): add Báo cáo kinh doanh page rendering the 5 BusinessKpiService KPIs"
```

---

### Task 3: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including this plan's new tests (~7 in Task 1, ~3 in Task 2 — roughly +10 over the pre-Phase-5 baseline of 1367 passed).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. `BusinessKpiService` is a `Services` layer class per `deptrac.yaml`'s existing rules (`Services: [Models, Jobs]`) — it depends only on `Opportunity`/`ContractPayment` (Models), so it satisfies the ruleset with no changes to `deptrac.yaml`. `Web\CrmReportController` depending on `BusinessKpiService` matches the existing `WebControllers: [ApiControllers, Models, Services, Jobs]` rule. If a violation appears, it means a dependency was drawn in the wrong direction — fix the direction, don't add a `skip_violations` entry.

- [ ] **Step 3: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(zena-boq): confirm full suite and Deptrac are green for Phase 5"
```

(Skip this commit if steps 1-2 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 5 section as revised through §10):
- All five KPIs (monthly revenue, pipeline by stage, outstanding debt with overdue flag, sales win-rate, service-category performance) — covered by Task 1.
- Real caching (`Cache::remember`, 60s TTL, per-tenant keys) — covered by Task 1, verified by `test_results_are_cached_for_60_seconds`.
- New dedicated page, not mixed into the operational dashboard, not touching dead `KpiService` — covered by Task 2.
- `crm.view` gate, tenant isolation — covered by Task 2's tests.
- Quote-total-else-estimated_fee revenue rule — covered by Task 1's `revenueFor()` helper, used identically by both KPI #1 and KPI #5's `avg_fee`.

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code.

**Type/signature consistency check:** `BusinessKpiService`'s five method names and return shapes (Task 1) are consumed identically by `CrmReportController::index()` (Task 2) and by the Blade view's variable names (`monthlyRevenue`, `pipelineByStage`, `outstandingDebt`, `salesWinRate`, `serviceCategoryPerformance`) — matched exactly between the controller's `view(...)` call and the Blade template's `@foreach`/array-access usage. Route name `operator.crm.reports` is used consistently between route registration (Task 2, Step 4) and both the nav link (Step 6) and the test file's `route(...)` calls.
