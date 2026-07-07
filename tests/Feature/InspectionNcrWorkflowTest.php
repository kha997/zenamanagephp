<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class InspectionNcrWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $actor;
    protected User $assignee;
    protected array $headers;
    protected Project $project;
    protected QcPlan $plan;
    protected QcInspection $inspection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'inspection.view',
            'inspection.create',
            'inspection.edit',
            'task.create',
            'task.view',
        ]);
        $this->assignee = $this->createTenantUser($this->tenant, [], ['admin'], []);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
        ]);

        $this->plan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $this->inspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $this->plan->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'failed',
            'title' => 'Inspection NCR owner test',
        ]);
    }

    public function test_can_prove_inspection_owned_ncr_slice_and_task_handoff(): void
    {
        $create = $this->withHeaders($this->headers)->postJson($this->ncrRoute('store'), [
            'title' => 'Rebar spacing non-conformance',
            'description' => 'Spacing does not match approved drawing.',
            'severity' => 'high',
            'assigned_to' => (string) $this->assignee->id,
            'corrective_action' => 'Rework and reinspect the reinforcement cage.',
            'preventive_action' => 'Add hold-point verification before pour.',
        ]);

        $create->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.inspection_id', (string) $this->inspection->id)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.task_handoff.owner_route', '/api/zena/tasks')
            ->assertJsonPath('data.task_handoff.payload.project_id', (string) $this->project->id)
            ->assertJsonPath('data.task_handoff.payload.status', 'pending')
            ->assertJsonPath('data.task_handoff.payload.priority', 'high');

        $ncrId = (string) $create->json('data.id');
        $handoffPayload = $create->json('data.task_handoff.payload');

        $this->assertDatabaseHas('ncrs', [
            'id' => $ncrId,
            'tenant_id' => (string) $this->tenant->id,
            'inspection_id' => (string) $this->inspection->id,
            'status' => 'open',
        ]);

        $this->withHeaders($this->headers)->getJson($this->ncrRoute('index'))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.pagination.total', 1)
            ->assertJsonPath('data.0.id', $ncrId);

        $this->withHeaders($this->headers)->getJson($this->ncrRoute('show', ['ncr' => $ncrId]))
            ->assertOk()
            ->assertJsonPath('data.id', $ncrId)
            ->assertJsonPath('data.assignee.id', (string) $this->assignee->id);

        $this->withHeaders($this->headers)->patchJson($this->ncrRoute('status', ['ncr' => $ncrId]), [
            'status' => 'resolved',
        ])->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('error.details.data.current_status', 'open')
            ->assertJsonPath('error.details.data.target_status', 'resolved');

        $this->withHeaders($this->headers)->patchJson($this->ncrRoute('status', ['ncr' => $ncrId]), [
            'status' => 'in_progress',
            'root_cause' => 'Crew placed bars on legacy layout.',
        ])->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.root_cause', 'Crew placed bars on legacy layout.');

        $resolved = $this->withHeaders($this->headers)->patchJson($this->ncrRoute('status', ['ncr' => $ncrId]), [
            'status' => 'resolved',
            'resolution' => 'Rework completed and ready for closeout.',
        ]);

        $resolved->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'Rework completed and ready for closeout.');

        $this->assertNotNull($resolved->json('data.resolved_at'));

        $this->withHeaders($this->headers)->patchJson($this->ncrRoute('status', ['ncr' => $ncrId]), [
            'status' => 'closed',
        ])->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $taskCreate = $this->withHeaders($this->headers)->postJson('/api/zena/tasks', $handoffPayload);
        $taskCreate->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.status', 'pending');

        $taskId = (string) $taskCreate->json('data.id');

        $this->assertDatabaseHas('tasks', [
            'id' => $taskId,
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers)->getJson('/api/zena/tasks/' . $taskId)
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id', $taskId);
    }

    public function test_nested_inspection_ncr_routes_are_tenant_safe(): void
    {
        $ncr = Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'inspection_id' => (string) $this->inspection->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->assignee->id,
            'status' => 'open',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], ['inspection.view']);
        $foreignToken = $this->apiLoginToken($foreignActor, $foreignTenant);
        $foreignHeaders = $this->authHeadersForUser($foreignActor, $foreignToken);

        $this->withHeaders($foreignHeaders)->getJson($this->ncrRoute('index'))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Inspection not found');

        $this->withHeaders($foreignHeaders)->getJson($this->ncrRoute('show', ['ncr' => (string) $ncr->id]))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Inspection NCR not found');
    }

    public function test_nested_inspection_ncr_routes_enforce_rbac(): void
    {
        $restricted = $this->createTenantUser($this->tenant, [], ['member'], ['inspection.view']);
        $restrictedToken = $this->apiLoginToken($restricted, $this->tenant);
        $restrictedHeaders = $this->authHeadersForUser($restricted, $restrictedToken);

        $this->withHeaders($restrictedHeaders)->postJson($this->ncrRoute('store'), [
            'title' => 'Forbidden NCR',
            'description' => 'Should be blocked by RBAC.',
        ])->assertStatus(403);
    }

    private function ncrRoute(string $name, array $parameters = []): string
    {
        return match ($name) {
            'index' => route('api.zena.inspections.ncrs.index', ['inspection' => (string) $this->inspection->id], false),
            'store' => route('api.zena.inspections.ncrs.store', ['inspection' => (string) $this->inspection->id], false),
            'show' => route('api.zena.inspections.ncrs.show', ['inspection' => (string) $this->inspection->id, 'ncr' => $parameters['ncr']], false),
            'status' => route('api.zena.inspections.ncrs.update-status', ['inspection' => (string) $this->inspection->id, 'ncr' => $parameters['ncr']], false),
        };
    }
}
