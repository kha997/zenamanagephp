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
