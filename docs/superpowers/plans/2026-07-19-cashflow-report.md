# Cashflow Report Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A "Dòng tiền" report page: 12-month table (9 back → 2 forward) of actual cash in/out, net, cumulative, and expected-in from planned/overdue contract payments.

**Architecture:** One new `cashflow()` method on the existing `ReportPageController`, PHP bucketing over two tenant-scoped queries, one Blade view, one link from the Reports index. No cache/JS/migration/new permission.

**Tech Stack:** Laravel 12, Blade, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-07-19-cashflow-report-design.md`

## Global Constraints

- Permission: `rbac:report.view` (existing) on the new route. No new permission.
- Pinned bucketing — copy exactly: thu = paid payments by `paid_at` month, NULL `paid_at` → fall back to `due_date`, both NULL → skip; chờ thu = planned/overdue by `due_date`, NULL → skip; chi = expenses by `expense_date`, NULL → skip. Window = 12 months, from start-of-month 9 months ago through 2 months ahead; zero rows still render.
- Cumulative = running net within the displayed window only; the view must carry the caption "lũy kế trong kỳ hiển thị" and the KPI-vs-cash caption from the spec.
- Read-only: no writes, no model/service changes (`BusinessKpiService` untouched).
- Run tests via `./vendor/bin/phpunit <path>` — never `php artisan test` (hybrid-vendor crash). Ignore imagick/memcached dylib warnings.

---

### Task 1: Cashflow page (method + route + view + link + tests)

**Files:**
- Modify: `app/Http/Controllers/Web/ReportPageController.php` (add `cashflow()` after `index()`; add `use App\Models\ContractExpense;` and `use App\Models\ContractPayment;` and `use Illuminate\Support\Carbon;` to the imports)
- Modify: `routes/web.php` (one route after the existing `operator.reports.index` line ~960)
- Create: `resources/views/reports/cashflow.blade.php`
- Modify: `resources/views/reports/index.blade.php` (link card at the bottom of the content section)
- Test: `tests/Feature/CashflowReportTest.php` (new file)

**Interfaces:**
- Consumes: `ContractPayment` (`STATUS_PAID`, `amount`, `status`, `due_date`, `paid_at` — date casts), `ContractExpense` (`amount`, `expense_date` — date cast), `Contract` + factories per the fixture pattern below.
- Produces: route `operator.reports.cashflow`. Nothing later consumes this — single-task plan.

- [ ] **Step 1: Write the failing feature tests**

Create `tests/Feature/CashflowReportTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class CashflowReportTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $viewer;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        Carbon::setTestNow('2026-07-19 10:00:00');

        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['admin'], ['report.view']);

        $project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-CF-01',
            'title' => 'HĐ test dòng tiền',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $this->viewer->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payment(array $overrides = []): ContractPayment
    {
        return ContractPayment::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
        ], $overrides));
    }

    private function expense(array $overrides = []): ContractExpense
    {
        return ContractExpense::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'expense_date' => '2026-07-05',
            'amount' => 40000000,
            'category' => 'labor',
            'description' => 'Chi test',
            'recorded_by' => (string) $this->viewer->id,
        ], $overrides));
    }

    public function test_shows_monthly_cash_in_out_net_and_expected(): void
    {
        $this->payment([
            'status' => ContractPayment::STATUS_PAID,
            'paid_at' => '2026-07-10',
            'due_date' => '2026-07-01',
            'amount' => 100000000,
        ]);
        $this->payment([
            'status' => ContractPayment::STATUS_PLANNED,
            'paid_at' => null,
            'due_date' => '2026-08-15',
            'amount' => 55000000,
        ]);
        $this->expense();

        $response = $this->actingAs($this->viewer)->get(route('operator.reports.cashflow'));

        $response->assertOk();
        $response->assertSee('Dòng tiền');
        $response->assertSee('lũy kế trong kỳ hiển thị');
        // Thu thực tháng 7: 100.000.000 — Chi: 40.000.000 — Ròng/Lũy kế: 60.000.000
        $response->assertSee('100.000.000');
        $response->assertSee('40.000.000');
        $response->assertSee('60.000.000');
        // Chờ thu tháng 8
        $response->assertSee('55.000.000');
    }

    public function test_requires_report_view_permission(): void
    {
        $noPerm = $this->createTenantUser($this->tenant, [], ['member'], []);

        $this->actingAs($noPerm)->get(route('operator.reports.cashflow'))->assertStatus(403);
    }

    public function test_cross_tenant_sums_never_appear(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], []);
        $otherProject = Project::factory()->create(['tenant_id' => (string) $otherTenant->id]);
        $otherContract = Contract::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'project_id' => (string) $otherProject->id,
            'code' => 'CTR-CF-XX',
            'title' => 'HĐ tenant khác',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $otherUser->id,
        ]);
        ContractPayment::factory()->create([
            'tenant_id' => (string) $otherTenant->id,
            'contract_id' => (string) $otherContract->id,
            'status' => ContractPayment::STATUS_PAID,
            'paid_at' => '2026-07-10',
            'amount' => 777000000,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('operator.reports.cashflow'));

        $response->assertOk();
        $response->assertDontSee('777.000.000');
    }

    public function test_paid_payment_without_paid_at_buckets_by_due_date(): void
    {
        $this->payment([
            'status' => ContractPayment::STATUS_PAID,
            'paid_at' => null,
            'due_date' => '2026-06-20',
            'amount' => 33000000,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('operator.reports.cashflow'));

        $response->assertOk();
        // Xuất hiện ở cột Thu thực (tháng 6) — đủ để chứng minh không bị bỏ rơi
        $response->assertSee('33.000.000');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/CashflowReportTest.php`
Expected: FAIL — route `operator.reports.cashflow` not defined.

- [ ] **Step 3: Add the route**

In `routes/web.php`, directly after the `operator.reports.index` line (~960):

```php
    Route::get('/reports/cashflow', [App\Http\Controllers\Web\ReportPageController::class, 'cashflow'])->middleware('rbac:report.view')->name('reports.cashflow');
```

(Must come BEFORE any `/reports/{...}` wildcard if one ever exists — today there is none, but keep it adjacent to `reports.index`.)

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/Web/ReportPageController.php`: add to the imports block:

```php
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use Illuminate\Support\Carbon;
```

Then add after `index()`:

```php
    public function cashflow(): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        // Cửa sổ 12 tháng: 9 tháng trước -> 2 tháng tới (spec: Definitions).
        $start = Carbon::now()->startOfMonth()->subMonths(9);
        $months = [];
        $cursor = $start->copy();
        for ($i = 0; $i < 12; $i++) {
            $months[$cursor->format('Y-m')] = ['thu' => 0.0, 'chi' => 0.0, 'cho_thu' => 0.0];
            $cursor = $cursor->addMonth();
        }

        $payments = ContractPayment::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'tenant_id', 'amount', 'status', 'due_date', 'paid_at']);

        foreach ($payments as $payment) {
            if ($payment->status === ContractPayment::STATUS_PAID) {
                $date = $payment->paid_at ?? $payment->due_date;
                if ($date === null) {
                    continue;
                }
                $month = $date->format('Y-m');
                if (isset($months[$month])) {
                    $months[$month]['thu'] += (float) $payment->amount;
                }
            } else {
                if ($payment->due_date === null) {
                    continue;
                }
                $month = $payment->due_date->format('Y-m');
                if (isset($months[$month])) {
                    $months[$month]['cho_thu'] += (float) $payment->amount;
                }
            }
        }

        $expenses = ContractExpense::query()
            ->where('tenant_id', $tenantId)
            ->get(['id', 'tenant_id', 'amount', 'expense_date']);

        foreach ($expenses as $expense) {
            if ($expense->expense_date === null) {
                continue;
            }
            $month = $expense->expense_date->format('Y-m');
            if (isset($months[$month])) {
                $months[$month]['chi'] += (float) $expense->amount;
            }
        }

        $rows = [];
        $cumulative = 0.0;
        $hasAny = false;
        foreach ($months as $month => $sums) {
            $net = $sums['thu'] - $sums['chi'];
            $cumulative += $net;
            if ($sums['thu'] > 0 || $sums['chi'] > 0 || $sums['cho_thu'] > 0) {
                $hasAny = true;
            }
            $rows[] = [
                'month' => $month,
                'thu' => $sums['thu'],
                'chi' => $sums['chi'],
                'net' => $net,
                'cumulative' => $cumulative,
                'cho_thu' => $sums['cho_thu'],
            ];
        }

        return view('reports.cashflow', [
            'rows' => $rows,
            'hasAny' => $hasAny,
            'currentMonth' => Carbon::now()->format('Y-m'),
        ]);
    }
```

- [ ] **Step 5: Create the view**

Create `resources/views/reports/cashflow.blade.php`:

```blade
@extends('layouts.operator')

@section('title', 'Dòng tiền')
@section('page_title', 'Dòng tiền')

@section('content')
    <x-ui.page-header
        title="Dòng tiền"
        description="Số liệu tiền thực thu/chi theo hợp đồng — khác với doanh số ghi nhận (KPI). Lũy kế trong kỳ hiển thị."
    />

    <x-ui.card>
        @unless ($hasAny)
            <p class="mb-3 text-sm text-slate-500">Chưa có giao dịch nào được ghi nhận.</p>
        @endunless

        <x-ui.data-table :headers="['Tháng', 'Thu thực', 'Chi thực', 'Ròng', 'Lũy kế', 'Chờ thu']">
            @foreach ($rows as $row)
                <tr @class(['bg-slate-50 font-medium' => $row['month'] === $currentMonth])>
                    <td class="text-sm text-slate-700">{{ \Illuminate\Support\Carbon::createFromFormat('Y-m', $row['month'])->format('m/Y') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['thu'], 0, ',', '.') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['chi'], 0, ',', '.') }}</td>
                    <td class="text-sm {{ $row['net'] < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ number_format($row['net'], 0, ',', '.') }}</td>
                    <td class="text-sm {{ $row['cumulative'] < 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ number_format($row['cumulative'], 0, ',', '.') }}</td>
                    <td class="text-sm text-slate-700">{{ number_format($row['cho_thu'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </x-ui.data-table>
    </x-ui.card>
@endsection
```

- [ ] **Step 6: Add the link from the Reports index**

In `resources/views/reports/index.blade.php`, directly before the closing `@endsection`:

```blade
    <x-ui.card title="Báo cáo dòng tiền">
        <p class="mb-2 text-sm text-slate-600">Thu thực / chi thực / ròng / lũy kế theo tháng, kèm khoản chờ thu từ các hợp đồng.</p>
        <x-ui.button-link :href="route('operator.reports.cashflow')" variant="secondary">Mở Dòng tiền</x-ui.button-link>
    </x-ui.card>
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/CashflowReportTest.php`
Expected: PASS (4 tests).

Run: `php artisan view:cache`
Expected: "Blade templates cached successfully".

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/ReportPageController.php routes/web.php resources/views/reports/cashflow.blade.php resources/views/reports/index.blade.php tests/Feature/CashflowReportTest.php
git commit -m "feat(reports): company cashflow report with expected-in column"
```
