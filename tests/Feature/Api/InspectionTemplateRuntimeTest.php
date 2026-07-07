<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InspectionTemplateRuntimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_inspection_runtime_can_link_and_sync_generated_checklist_instance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->orderBy('created_at')->firstOrFail();
        $actor = $this->createActorWithPermissions($tenant, [
            'template.view',
            'template.apply',
            'inspection.create',
            'inspection.view',
            'inspection.conduct',
            'inspection.complete',
        ]);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $actor->id,
            'pm_id' => (string) $actor->id,
        ]);

        $plan = QcPlan::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $actor->id,
            'checklist_items' => [],
        ]);

        $template = WorkTemplate::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('code', 'WT-BL-INSPECTION')
            ->firstOrFail();

        $apply = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
            'work_template_id' => (string) $template->id,
        ], $this->authHeaders($actor))->assertCreated();

        $instanceId = (string) $apply->json('data.id');
        $inspectionStep = WorkInstanceStep::query()
            ->with(['workInstance.templateVersion.template', 'values'])
            ->where('work_instance_id', $instanceId)
            ->where('step_key', 'perform-inspection')
            ->firstOrFail();

        $create = $this->postJson($this->inspectionRoute('store'), [
            'qc_plan_id' => (string) $plan->id,
            'title' => 'Template-backed inspection',
            'description' => 'Generated from work template',
            'inspection_date' => now()->addDay()->toDateString(),
            'inspector_id' => (string) $actor->id,
            'work_instance_step_id' => (string) $inspectionStep->id,
        ], $this->authHeaders($actor));

        $create->assertCreated()
            ->assertJsonPath('data.work_instance_step_id', (string) $inspectionStep->id)
            ->assertJsonPath('data.generated_checklist.work_instance_step_id', (string) $inspectionStep->id)
            ->assertJsonPath('data.generated_checklist.template_code', 'WT-BL-INSPECTION')
            ->assertJsonPath('data.generated_checklist.step_type', 'inspection')
            ->assertJsonPath('data.generated_checklist.items.0.item_key', 'capture_measurements')
            ->assertJsonPath('data.checklist_results.0.item_key', 'capture_measurements');

        $inspectionId = (string) $create->json('data.id');

        $conduct = $this->postJson($this->inspectionRoute('conduct', ['id' => $inspectionId]), [
            'findings' => 'Initial field findings',
            'checklist_results' => [
                ['item_key' => 'capture_measurements', 'result' => 'pass', 'notes' => 'Measurements recorded'],
                ['item_key' => 'record_nonconformances', 'result' => 'fail', 'notes' => 'One crack observed'],
            ],
        ], $this->authHeaders($actor));

        $conduct->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.generated_checklist.items.0.result', 'pass')
            ->assertJsonPath('data.generated_checklist.items.1.result', 'fail');

        $this->assertDatabaseHas('qc_inspections', [
            'id' => $inspectionId,
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $inspectionStep->id,
        ]);

        $this->assertSame(2, WorkInstanceFieldValue::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('work_instance_step_id', (string) $inspectionStep->id)
            ->count());

        $show = $this->getJson($this->inspectionRoute('show', ['id' => $inspectionId]), $this->authHeaders($actor));
        $show->assertOk()
            ->assertJsonPath('data.generated_checklist.items.0.item_key', 'capture_measurements')
            ->assertJsonPath('data.generated_checklist.items.1.result', 'fail');

        $complete = $this->postJson($this->inspectionRoute('complete', ['id' => $inspectionId]), [
            'findings' => 'Final review complete',
            'recommendations' => 'Accepted with closeout note',
            'checklist_results' => [
                ['item_key' => 'capture_measurements', 'result' => 'pass', 'notes' => 'Closed'],
                ['item_key' => 'record_nonconformances', 'result' => 'pass', 'notes' => 'Corrected'],
            ],
        ], $this->authHeaders($actor));

        $complete->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.generated_checklist.items.1.result', 'pass');

        $inspectionStep->refresh();
        $this->assertSame('completed', $inspectionStep->status);

        $inspection = QcInspection::query()->findOrFail($inspectionId);
        $this->assertSame((string) $inspectionStep->id, (string) $inspection->work_instance_step_id);
    }

    public function test_inspection_create_rbac_is_enforced(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->orderBy('created_at')->firstOrFail();
        $actor = $this->createActorWithPermissions($tenant, ['inspection.view']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $actor->id,
            'pm_id' => (string) $actor->id,
        ]);
        $plan = QcPlan::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $actor->id,
            'checklist_items' => [],
        ]);

        $this->postJson($this->inspectionRoute('store'), [
            'qc_plan_id' => (string) $plan->id,
            'title' => 'Forbidden inspection',
            'inspection_date' => now()->addDay()->toDateString(),
            'inspector_id' => (string) $actor->id,
        ], $this->authHeaders($actor))->assertStatus(403);
    }

    public function test_inspection_create_rejects_foreign_tenant_generated_checklist_step(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenantA = Tenant::query()->orderBy('created_at')->firstOrFail();
        $tenantB = Tenant::query()->orderBy('created_at')->skip(1)->firstOrFail();

        $actorA = $this->createActorWithPermissions($tenantA, [
            'template.view',
            'template.apply',
            'inspection.create',
            'inspection.view',
            'inspection.conduct',
            'inspection.complete',
        ]);
        $actorB = $this->createActorWithPermissions($tenantB, ['template.view', 'template.apply']);

        $projectA = Project::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'created_by' => (string) $actorA->id,
            'pm_id' => (string) $actorA->id,
        ]);
        $planA = QcPlan::factory()->create([
            'tenant_id' => (string) $tenantA->id,
            'project_id' => (string) $projectA->id,
            'created_by' => (string) $actorA->id,
            'checklist_items' => [],
        ]);

        $projectB = Project::factory()->create([
            'tenant_id' => (string) $tenantB->id,
            'created_by' => (string) $actorB->id,
            'pm_id' => (string) $actorB->id,
        ]);

        $templateB = WorkTemplate::query()
            ->where('tenant_id', (string) $tenantB->id)
            ->where('code', 'WT-BL-INSPECTION')
            ->firstOrFail();

        $applyB = $this->postJson($this->projectApplyTemplateRoute((string) $projectB->id), [
            'work_template_id' => (string) $templateB->id,
        ], $this->authHeaders($actorB))->assertCreated();

        $foreignStep = WorkInstanceStep::query()
            ->where('work_instance_id', (string) $applyB->json('data.id'))
            ->where('step_key', 'perform-inspection')
            ->firstOrFail();

        $this->postJson($this->inspectionRoute('store'), [
            'qc_plan_id' => (string) $planA->id,
            'title' => 'Foreign linked inspection',
            'inspection_date' => now()->addDay()->toDateString(),
            'inspector_id' => (string) $actorA->id,
            'work_instance_step_id' => (string) $foreignStep->id,
        ], $this->authHeaders($actorA))
            ->assertStatus(403);

        $this->assertDatabaseMissing('qc_inspections', [
            'tenant_id' => (string) $tenantA->id,
            'title' => 'Foreign linked inspection',
        ]);
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('inspection-template-runtime')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createActorWithPermissions(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'inspection-template-runtime-' . Str::lower(Str::random(8)),
            'scope' => Role::SCOPE_SYSTEM,
            'description' => 'Inspection template runtime test role',
            'allow_override' => false,
            'is_active' => true,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('code', $permissions)
            ->orWhereIn('name', $permissions)
            ->pluck('id')
            ->values()
            ->all();

        $this->assertCount(count($permissions), $permissionIds, 'Expected canonical permissions to exist after DatabaseSeeder.');

        $role->permissions()->sync($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function projectApplyTemplateRoute(string $projectId): string
    {
        return route('api.zena.projects.apply-template', ['id' => $projectId], false);
    }

    private function inspectionRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.inspections.' . $name, $parameters, false);
    }
}
