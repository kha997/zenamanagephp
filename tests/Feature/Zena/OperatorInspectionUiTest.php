<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorInspectionUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private QcPlan $qcPlan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['inspection.view', 'inspection.create', 'inspection.conduct', 'inspection.complete']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Inspection UI Project',
            'code' => 'PRJ-INS-001',
        ]);

        $this->qcPlan = QcPlan::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'title' => 'Concrete pour QC plan',
            'status' => 'active',
            'created_by' => (string) $this->user->id,
        ]);
    }

    public function test_inspection_full_flow_create_conduct_complete(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.inspections.index'), $headers)
            ->assertOk()
            ->assertSee('Kiểm định chất lượng');

        $this->actingAs($this->user)
            ->get(route('operator.inspections.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin phiên kiểm định');

        $create = $this->actingAs($this->user)
            ->post(route('operator.inspections.store'), [
                'qc_plan_id' => (string) $this->qcPlan->id,
                'title' => 'Slab pour inspection L3',
                'inspection_date' => now()->addDay()->format('Y-m-d'),
                'inspector_id' => (string) $this->user->id,
            ], $headers);

        $inspection = QcInspection::query()->firstOrFail();
        $create->assertRedirect(route('operator.inspections.show', $inspection->id));
        $create->assertSessionHas('success', 'Tạo phiên kiểm định thành công');
        $this->assertSame('scheduled', (string) $inspection->status);

        $conduct = $this->actingAs($this->user)
            ->post(route('operator.inspections.conduct', $inspection->id), [
                'findings' => 'Formwork acceptable, rebar per drawing.',
            ], $headers);

        $conduct->assertRedirect(route('operator.inspections.show', $inspection->id));
        $inspection->refresh();
        $this->assertSame('in_progress', (string) $inspection->status);

        $complete = $this->actingAs($this->user)
            ->post(route('operator.inspections.complete', $inspection->id), [
                'findings' => 'Pour completed satisfactorily.',
                'recommendations' => 'Cure minimum 7 days.',
            ], $headers);

        $complete->assertRedirect(route('operator.inspections.show', $inspection->id));
        $complete->assertSessionHas('success', 'Đã hoàn tất kiểm định');

        $inspection->refresh();
        $this->assertSame('completed', (string) $inspection->status);
    }

    public function test_inspection_pages_require_authentication(): void
    {
        $this->get(route('operator.inspections.index'))->assertRedirect();
    }

    public function test_inspection_actions_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['inspection_viewer'], ['inspection.view']);

        $inspection = QcInspection::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $this->qcPlan->id,
            'title' => 'Locked inspection',
            'inspection_date' => now()->addDay(),
            'inspector_id' => (string) $this->user->id,
            'status' => 'scheduled',
        ]);

        $this->actingAs($viewer)
            ->get(route('operator.inspections.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.inspections.conduct', $inspection->id), [], $headers)
            ->assertStatus(302);

        $inspection->refresh();
        $this->assertSame('scheduled', (string) $inspection->status);
    }
}
