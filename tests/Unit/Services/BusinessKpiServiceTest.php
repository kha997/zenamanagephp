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
            'name' => 'Overdue installment',
            'amount' => 20000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(5),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Future installment',
            'amount' => 30000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(5),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Paid installment',
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

    public function test_outstanding_debt_aging_buckets_group_correctly(): void
    {
        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an Aging',
            'code' => 'PRJ-AGING1',
            'status' => 'planning',
        ]);
        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-AGING1',
            'title' => 'Hop dong Aging',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Not due',
            'amount' => 1000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(5),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Due 1-30',
            'amount' => 2000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(10),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Due 31-60',
            'amount' => 3000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(45),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Due 61-90',
            'amount' => 4000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(75),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Due over 90',
            'amount' => 5000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(120),
        ]);

        $result = $this->service->outstandingDebt((string) $this->tenant->id);

        $this->assertSame(15000000.0, $result['total']);
        $this->assertSame(14000000.0, $result['overdue_total']);
        $this->assertSame(4, $result['overdue_count']);

        $this->assertArrayHasKey('aging', $result);
        $this->assertSame(1000000.0, $result['aging']['not_due']);
        $this->assertSame(2000000.0, $result['aging']['due_1_30']);
        $this->assertSame(3000000.0, $result['aging']['due_31_60']);
        $this->assertSame(4000000.0, $result['aging']['due_61_90']);
        $this->assertSame(5000000.0, $result['aging']['due_over_90']);
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
