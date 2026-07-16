<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProjectContractsRollupTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['project.view', 'contract.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $design = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-RLP-TK',
            'title' => 'HĐ thiết kế',
            'contract_type' => Contract::TYPE_DESIGN,
            'total_value' => 500000000,
            'created_by' => (string) $this->user->id,
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $design->id,
            'name' => 'Đợt 1',
            'amount' => 200000000,
            'status' => ContractPayment::STATUS_PAID,
            'due_date' => now()->subWeek(),
            'paid_at' => now()->subWeek(),
        ]);

        ContractExpense::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $design->id,
            'expense_date' => '2026-07-13',
            'amount' => 50000000,
            'category' => 'design_outsource',
            'description' => 'Thuê ngoài kết cấu',
        ]);

        Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-RLP-TC',
            'title' => 'HĐ thi công',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 2000000000,
            'created_by' => (string) $this->user->id,
        ]);
    }

    public function test_project_page_shows_contracts_rollup(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('app.projects.show', $this->project->id),
            ['X-Tenant-ID' => (string) $this->tenant->id]
        );

        $response->assertOk()
            ->assertSee('Hợp đồng &amp; tài chính', false)
            ->assertSee('CTR-RLP-TK')
            ->assertSee('CTR-RLP-TC')
            ->assertSee('Thiết kế')
            ->assertSee('Thi công')
            ->assertSee('200,000,000')
            ->assertSee('50,000,000')
            ->assertSee('2,500,000,000');
    }
}
