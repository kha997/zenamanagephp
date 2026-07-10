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
