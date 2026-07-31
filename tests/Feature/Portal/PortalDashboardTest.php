<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_projects_design_items_documents_and_balance_for_the_account(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang dashboard',
            'email' => 'dashboard@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Biet thu Thao Dien',
            'code' => 'PRJ-PORTAL1',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi da convert',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        DesignItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Phoi canh mat tien',
            'item_type' => 'concept',
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
            'created_by' => (string) $staffUser->id,
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Ban ve duyet',
            'title' => null,
            'status' => 'approved',
            'uploaded_by' => (string) $staffUser->id,
        ]);

        Document::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Ban ve nhap',
            'title' => null,
            'status' => 'draft',
            'uploaded_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-PORTAL1',
            'title' => 'Hop dong portal test',
            'total_value' => 500000000,
            'currency' => 'VND',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 100000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(10),
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash']));

        $response->assertOk();
        $response->assertSee('Biet thu Thao Dien');
        $response->assertSee('Phoi canh mat tien');
        $response->assertSee('Ban ve duyet');
        $response->assertDontSee('Ban ve nhap');
        $response->assertSee('100.000.000', false);
    }

    public function test_dashboard_shows_unpaid_payment_schedule_and_hides_paid(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-pay']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang payment',
            'email' => 'payment@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Nha pho payment',
            'code' => 'PRJ-PAY01',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi payment',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-PAY01',
            'title' => 'Hop dong payment',
            'total_value' => 300000000,
            'currency' => 'VND',
        ]);

        // Overdue planned payment (past due_date)
        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot qua han',
            'amount' => 50000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->subDays(5),
        ]);

        // Not-yet-due planned payment (future due_date)
        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot chua den han',
            'amount' => 80000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(10),
        ]);

        // Paid payment — must NOT appear in schedule
        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot da thanh toan',
            'amount' => 70000000,
            'status' => ContractPayment::STATUS_PAID,
            'due_date' => now()->subDays(20),
            'paid_at' => now()->subDays(18),
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-pay']));

        $response->assertOk();

        // outstandingBalance regression: only unpaid amounts (50M + 80M = 130M)
        $response->assertSee('130.000.000', false);

        // Payment schedule table rendered
        $response->assertSee('Các đợt thanh toán còn lại', false);
        $response->assertSee('Dot qua han', false);
        $response->assertSee('Dot chua den han', false);
        $response->assertDontSee('Dot da thanh toan', false);

        // Amounts formatted
        $response->assertSee('50.000.000', false);
        $response->assertSee('80.000.000', false);
        $response->assertDontSee('70.000.000', false);

        // Overdue badge for past due_date
        $response->assertSee('Quá hạn', false);
        // Not-yet-due badge for future due_date
        $response->assertSee('Chưa đến hạn', false);
    }

    public function test_dashboard_hides_payment_schedule_when_all_paid(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-allpaid']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang all paid',
            'email' => 'allpaid@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an all paid',
            'code' => 'PRJ-ALLPAID',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi all paid',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-ALLPAID',
            'title' => 'Hop dong all paid',
            'total_value' => 100000000,
            'currency' => 'VND',
        ]);

        // All payments are paid — no unpaid schedule
        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot da thanh toan',
            'amount' => 100000000,
            'status' => ContractPayment::STATUS_PAID,
            'due_date' => now()->subDays(10),
            'paid_at' => now()->subDays(8),
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-allpaid']));

        $response->assertOk();
        // outstandingBalance = 0 (all paid)
        $response->assertSee('0₫', false);
        // Payment schedule table should be completely hidden
        $response->assertDontSee('Các đợt thanh toán còn lại', false);
    }

    public function test_dashboard_never_shows_another_accounts_data(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash2']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang A',
            'email' => 'a@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang B',
            'email' => 'b@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherProject = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an cua khach B',
            'code' => 'PRJ-PORTAL2',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $otherAccount->id,
            'opportunity_name' => 'Co hoi cua B',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $otherProject->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $this->actingAs($account, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash2']))
            ->assertOk()
            ->assertDontSee('Du an cua khach B');
    }

    public function test_dashboard_shows_project_with_no_opportunity_data_gracefully(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash3']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang chua co du an',
            'email' => 'noproject@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($account, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash3']))
            ->assertOk()
            ->assertSee('Chưa có dự án');
    }

    public function test_dashboard_shows_sent_quote_for_account_opportunity(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash4']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang quote dash',
            'email' => 'quotedash@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi co bao gia',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => 'BQ-DASH-001',
            'revision_no' => 1,
            'status' => Quote::STATUS_SENT,
            'subtotal' => 15000000,
            'total' => 15000000,
            'sent_at' => now(),
            'created_by' => (string) $staffUser->id,
        ]);

        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'quote_number' => 'BQ-DASH-002',
            'revision_no' => 2,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 5000000,
            'total' => 5000000,
            'created_by' => (string) $staffUser->id,
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash4']));
        $response->assertOk();
        $response->assertSee('BQ-DASH-001');
        $response->assertSee('15.000.000', false);
        $response->assertDontSee('BQ-DASH-002');
    }

    public function test_dashboard_does_not_show_other_accounts_quotes(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash5']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang A quote',
            'email' => 'a-quote@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang B quote',
            'email' => 'b-quote@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherOpportunity = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $otherAccount->id,
            'opportunity_name' => 'Co hoi cua B',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $otherOpportunity->id,
            'quote_number' => 'BQ-OTHER-001',
            'revision_no' => 1,
            'status' => Quote::STATUS_SENT,
            'subtotal' => 20000000,
            'sent_at' => now(),
            'created_by' => (string) $staffUser->id,
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash5']));
        $response->assertOk();
        $response->assertDontSee('BQ-OTHER-001');
    }

    public function test_dashboard_shows_scheduled_unpaid_label_and_explanation_not_confirmed_debt_wording(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash-2']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang label test',
            'email' => 'label-test@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an label test',
            'code' => 'PRJ-LABEL1',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi label test',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-LABEL1',
            'title' => 'Hop dong label test',
            'total_value' => 100000000,
            'currency' => 'VND',
            'status' => 'active',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 30000000,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        $response = $this->actingAs($account, 'client')->get('/portal/zena-dash-2/dashboard');

        $response->assertOk();
        $response->assertSee('Giá trị theo lịch chưa ghi nhận thanh toán');
        $response->assertDontSee('Số dư còn lại');
        $response->assertSee('chưa ghi nhận thanh toán từng phần', false);
    }

    public function test_dashboard_renders_with_error_state_when_outstanding_balance_query_fails(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-balance-fail']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang balance fail',
            'email' => 'balancefail@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an balance fail',
            'code' => 'PRJ-BALFAIL',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi balance fail',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-BALFAIL',
            'title' => 'Hop dong balance fail',
            'total_value' => 100000000,
            'currency' => 'VND',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot balance fail',
            'amount' => 50000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(10),
        ]);

        $this->actingAs($account, 'client');

        Schema::rename('contract_payments', 'contract_payments_test_failure_injection');

        try {
            $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-balance-fail']));
        } finally {
            Schema::rename('contract_payments_test_failure_injection', 'contract_payments');
        }

        // The whole page must still render — not 500 — with the rest of the
        // dashboard (projects/documents/contracts) unaffected.
        $response->assertOk();
        $response->assertSee('Du an balance fail', false);
        $response->assertSee('Giá trị theo lịch chưa ghi nhận thanh toán');
        $response->assertSee('Không thể tính được', false);
    }
}
