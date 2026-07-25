<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
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

    public function test_report_page_relabels_outstanding_debt_total_as_scheduled_unpaid(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang label test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an label test',
            'code' => 'PRJ-CRMLABEL',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-CRMLABEL',
            'title' => 'Hop dong label test',
            'total_value' => 50000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 20000000,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->viewer)
            ->get(route('operator.crm.reports'), $headers)
            ->assertOk()
            ->assertSee('Giá trị theo lịch chưa ghi nhận thanh toán')
            ->assertDontSee('Tổng công nợ');
    }

    public function test_report_page_shows_no_data_when_tenant_has_no_payment_schedule(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->viewer)
            ->get(route('operator.crm.reports'), $headers)
            ->assertOk()
            ->assertSee('Chưa có lịch thanh toán');
    }

    public function test_report_page_requires_crm_view_permission(): void
    {
        $noAccess = $this->createTenantUser($this->tenant, [], ['staff'], []);
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($noAccess)
            ->get(route('operator.crm.reports'), $headers)
            ->assertStatus(302);
    }

    public function test_report_page_shows_aging_bucket_labels_and_amounts(): void
    {
        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an bao cao',
            'code' => 'PRJ-RPT01',
            'status' => 'planning',
        ]);
        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-RPT01',
            'title' => 'Hop dong bao cao',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Not due payment',
            'amount' => 5000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(10),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Overdue 1-30',
            'amount' => 8000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(15),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Overdue 31-60',
            'amount' => 12000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(50),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Overdue 61-90',
            'amount' => 15000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(80),
        ]);
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Overdue over 90',
            'amount' => 20000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(120),
        ]);

        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->viewer)
            ->get(route('operator.crm.reports'), $headers)
            ->assertOk()
            ->assertSee('Chưa đến hạn', false)
            ->assertSee('Quá hạn 1-30 ngày', false)
            ->assertSee('Quá hạn 31-60 ngày', false)
            ->assertSee('Quá hạn 61-90 ngày', false)
            ->assertSee('Quá hạn trên 90 ngày', false)
            ->assertSee('5.000.000', false)
            ->assertSee('8.000.000', false)
            ->assertSee('12.000.000', false)
            ->assertSee('15.000.000', false)
            ->assertSee('20.000.000', false);
    }

    public function test_aging_not_due_bucket_excludes_future_dated_payment_from_overdue_total(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang aging test',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an aging test',
            'code' => 'PRJ-AGING1',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-AGING1',
            'title' => 'Hop dong aging test',
            'total_value' => 10000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Ky tuong lai',
            'amount' => 10000000,
            'due_date' => now()->addDays(45)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('operator.crm.reports'));

        $response->assertOk();
        $response->assertSee('Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán');
        // overdue_total must be 0 (the payment is not_due), rendered as "0₫"
        $response->assertSeeInOrder(['Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán', '0₫']);
    }

    public function test_aging_due_date_wins_over_stale_status_field(): void
    {
        $account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang aging test 2',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Du an aging test 2',
            'code' => 'PRJ-AGING2',
            'status' => 'active',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-AGING2',
            'title' => 'Hop dong aging test 2',
            'total_value' => 5000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        // status is still "planned" (never manually flipped to "overdue"), but due_date is 10 days in the past —
        // BusinessKpiService::outstandingDebt() must still count this as overdue because it compares due_date, not status.
        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Ky qua han nhung status chua cap nhat',
            'amount' => 5000000,
            'due_date' => now()->subDays(10)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($this->viewer)->get(route('operator.crm.reports'));

        $response->assertOk();
        $response->assertSeeInOrder(['Giá trị đã quá hạn theo lịch, chưa ghi nhận thanh toán', '5.000.000₫']);
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
