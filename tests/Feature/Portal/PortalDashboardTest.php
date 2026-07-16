<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
