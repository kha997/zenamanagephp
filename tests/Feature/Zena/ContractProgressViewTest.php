<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\DesignItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractProgressViewTest extends TestCase
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
        $this->user = $this->createTenantUser($this->tenant, [], ['admin'], ['contract.view']);
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
    }

    private function makeContract(string $type): Contract
    {
        return Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-PRG-' . strtoupper($type),
            'title' => 'HĐ ' . $type,
            'contract_type' => $type,
            'created_by' => (string) $this->user->id,
        ]);
    }

    public function test_design_contract_shows_design_progress_partial(): void
    {
        DesignItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Concept sảnh chính',
            'review_status' => DesignItem::STATUS_INTERNAL_REVIEW,
            'created_by' => (string) $this->user->id,
        ]);

        $contract = $this->makeContract(Contract::TYPE_DESIGN);

        $this->actingAs($this->user)
            ->get(route('operator.contracts.show', $contract->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee('Thiết kế &amp; tiến độ', false)
            ->assertSee('Concept sảnh chính');
    }

    public function test_construction_contract_shows_tasks_and_counts(): void
    {
        Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'name' => 'Ép cọc đại trà',
            'title' => 'Ép cọc đại trà',
            'status' => 'in_progress',
            'progress_percent' => 30,
        ]);

        $contract = $this->makeContract(Contract::TYPE_CONSTRUCTION);

        $this->actingAs($this->user)
            ->get(route('operator.contracts.show', $contract->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee('Tiến độ thi công')
            ->assertSee('Ép cọc đại trà')
            ->assertSee('Nghiệm thu:')
            ->assertSee('NCR đang mở:')
            ->assertSee('Phiếu nhận vật tư:');
    }

    public function test_other_contract_shows_unclassified_note(): void
    {
        $contract = $this->makeContract(Contract::TYPE_OTHER);

        $this->actingAs($this->user)
            ->get(route('operator.contracts.show', $contract->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee('Hợp đồng chưa phân loại');
    }
}
