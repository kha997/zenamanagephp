<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Material;
use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorMaterialNcrUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private QcPlan $qcPlan;
    private QcInspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['material.view', 'material.create', 'inspection.view', 'inspection.create', 'inspection.edit']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Material NCR UI Project',
            'code' => 'PRJ-MN-001',
        ]);

        $this->qcPlan = QcPlan::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'NCR test QC plan',
            'status' => 'active',
            'created_by' => (string) $this->user->id,
        ]);

        $this->inspection = QcInspection::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $this->qcPlan->id,
            'title' => 'NCR host inspection',
            'inspection_date' => now(),
            'inspector_id' => (string) $this->user->id,
            'status' => 'in_progress',
        ]);
    }

    public function test_material_create_and_index_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.materials.index'), $headers)
            ->assertOk()
            ->assertSee('Danh mục vật tư');

        $create = $this->actingAs($this->user)
            ->post(route('operator.materials.store'), [
                'code' => 'MAT-UI-001',
                'name' => 'Cement PC40',
                'category' => 'Xi măng',
                'unit' => 'bao',
            ], $headers);

        $create->assertRedirect(route('operator.materials.index'));
        $create->assertSessionHas('success', 'Tạo vật tư thành công');

        $material = Material::query()->firstOrFail();
        $this->assertSame('MAT-UI-001', (string) $material->code);

        $this->actingAs($this->user)
            ->get(route('operator.materials.show', $material->id), $headers)
            ->assertOk()
            ->assertSee('Cement PC40');
    }

    public function test_ncr_create_and_status_transition_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.inspections.show', $this->inspection->id), $headers)
            ->assertOk()
            ->assertSee('Ghi nhận NCR mới');

        $create = $this->actingAs($this->user)
            ->post(route('operator.inspections.ncrs.store', $this->inspection->id), [
                'title' => 'Concrete honeycomb at column C5',
                'description' => 'Voids observed on formwork removal.',
                'severity' => 'high',
            ], $headers);

        $create->assertRedirect(route('operator.inspections.show', $this->inspection->id));
        $create->assertSessionHas('success', 'Đã tạo NCR');

        $ncr = Ncr::query()->firstOrFail();
        $this->assertSame('open', (string) $ncr->status);
        $this->assertNotEmpty($ncr->ncr_number);

        $this->actingAs($this->user)
            ->get(route('operator.inspections.ncrs.show', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), $headers)
            ->assertOk()
            ->assertSee($ncr->ncr_number)
            ->assertSee('Bắt đầu xử lý');

        $start = $this->actingAs($this->user)
            ->post(route('operator.inspections.ncrs.update-status', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), [
                'status' => 'in_progress',
            ], $headers);

        $start->assertRedirect(route('operator.inspections.ncrs.show', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]));
        $ncr->refresh();
        $this->assertSame('in_progress', (string) $ncr->status);

        $resolve = $this->actingAs($this->user)
            ->post(route('operator.inspections.ncrs.update-status', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), [
                'status' => 'resolved',
                'root_cause' => 'Insufficient vibration during pour.',
                'corrective_action' => 'Repair with non-shrink grout.',
                'resolution' => 'Repaired and re-inspected.',
            ], $headers);

        $resolve->assertRedirect(route('operator.inspections.ncrs.show', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]));
        $ncr->refresh();
        $this->assertSame('resolved', (string) $ncr->status);
        $this->assertNotNull($ncr->resolved_at);

        $close = $this->actingAs($this->user)
            ->post(route('operator.inspections.ncrs.update-status', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), [
                'status' => 'closed',
            ], $headers);

        $ncr->refresh();
        $this->assertSame('closed', (string) $ncr->status);
    }

    public function test_invalid_ncr_status_transition_is_rejected(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $ncr = Ncr::create([
            'project_id' => (string) $this->project->id,
            'tenant_id' => (string) $this->tenant->id,
            'inspection_id' => (string) $this->inspection->id,
            'ncr_number' => 'NCR-UI-001',
            'title' => 'Skip transition test',
            'description' => 'open → closed must be rejected.',
            'status' => 'open',
            'severity' => 'low',
            'created_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.inspections.ncrs.show', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), $headers)
            ->assertOk();

        $this->actingAs($this->user)
            ->post(route('operator.inspections.ncrs.update-status', ['inspection' => $this->inspection->id, 'ncr' => $ncr->id]), [
                'status' => 'closed',
            ], $headers);

        $ncr->refresh();
        $this->assertSame('open', (string) $ncr->status);
    }

    public function test_material_and_ncr_pages_require_authentication(): void
    {
        $this->get(route('operator.materials.index'))->assertRedirect();
    }

    public function test_material_creation_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['material_viewer'], ['material.view']);

        $this->actingAs($viewer)
            ->get(route('operator.materials.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.materials.store'), ['code' => 'X', 'name' => 'X'], $headers)
            ->assertForbidden();

        $this->assertSame(0, Material::query()->count());
    }
}
