<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractTypeTest extends TestCase
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
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['contract.view', 'contract.create', 'project.view']
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->get('/login');
    }

    public function test_contract_created_with_design_type(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-TK-01',
            'title' => 'HĐ thiết kế nhà phố',
            'contract_type' => 'design',
            'total_value' => 500000000,
        ], $headers)->assertRedirect();

        $this->assertDatabaseHas('contracts', ['code' => 'CTR-TK-01', 'contract_type' => 'design']);
    }

    public function test_contract_type_defaults_to_other_and_rejects_invalid(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-XX-01',
            'title' => 'HĐ chưa phân loại',
        ], $headers)->assertRedirect();
        $this->assertDatabaseHas('contracts', ['code' => 'CTR-XX-01', 'contract_type' => 'other']);

        $this->actingAs($this->user)->post(route('operator.contracts.store'), [
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-XX-02',
            'title' => 'Loại sai',
            'contract_type' => 'bogus',
        ], $headers)->assertSessionHasErrors('contract_type');
    }
}
