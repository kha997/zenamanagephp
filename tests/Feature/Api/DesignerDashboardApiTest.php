<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\Submittal;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class DesignerDashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    protected Tenant $tenant;
    protected User $actor;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'designer.dashboard',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
    }

    public function test_designer_dashboard_exposes_designer_summary_widget_from_locked_sources(): void
    {
        $project = $this->createAssignedProject('Designer dashboard project');
        $otherAssignedProject = $this->createAssignedProject('Second designer dashboard project');

        $this->createAssignedTask($project, [
            'status' => Task::STATUS_PENDING,
        ]);
        $this->createAssignedTask($project, [
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        $this->createAssignedTask($otherAssignedProject, [
            'status' => Task::STATUS_COMPLETED,
        ]);
        $this->createUnassignedTask($project);

        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'pending',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'answered',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'closed',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) User::factory()->create([
                'tenant_id' => (string) $this->tenant->id,
            ])->id,
            'status' => 'pending',
        ]);

        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'draft',
        ]);
        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'pending_review',
        ]);
        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'approved',
        ]);
        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'submitted',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign designer project',
        ]);
        Task::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'assigned_to' => (string) $foreignActor->id,
            'status' => Task::STATUS_PENDING,
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'assigned_to' => (string) $foreignActor->id,
            'status' => 'pending',
        ]);
        Submittal::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'status' => 'rejected',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.designer.dashboard', [], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.designer_widget.widget_key', 'designer_summary')
            ->assertJsonPath('data.designer_widget.tasks.total', 3)
            ->assertJsonPath('data.designer_widget.tasks.pending', 1)
            ->assertJsonPath('data.designer_widget.tasks.in_progress', 1)
            ->assertJsonPath('data.designer_widget.tasks.completed', 1)
            ->assertJsonPath('data.designer_widget.rfis.total', 3)
            ->assertJsonPath('data.designer_widget.rfis.pending', 1)
            ->assertJsonPath('data.designer_widget.rfis.answered', 1)
            ->assertJsonPath('data.designer_widget.rfis.closed', 1)
            ->assertJsonPath('data.designer_widget.submittals.total', 4)
            ->assertJsonPath('data.designer_widget.submittals.draft', 1)
            ->assertJsonPath('data.designer_widget.submittals.submitted', 1)
            ->assertJsonPath('data.designer_widget.submittals.pending_review', 1)
            ->assertJsonPath('data.designer_widget.submittals.approved', 1)
            ->assertJsonPath('data.designer_widget.submittals.rejected', 0);
    }

    public function test_designer_dashboard_widget_is_zero_safe_and_project_scoped(): void
    {
        $project = $this->createAssignedProject('Scoped designer project');
        $otherProject = $this->createAssignedProject('Excluded designer project');

        $this->createAssignedTask($project, [
            'status' => Task::STATUS_PENDING,
        ]);
        $this->createAssignedTask($otherProject, [
            'status' => Task::STATUS_COMPLETED,
        ]);

        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'pending',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'closed',
        ]);

        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'draft',
        ]);
        Submittal::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'approved',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.designer.dashboard', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.designer_widget.widget_key', 'designer_summary')
            ->assertJsonPath('data.designer_widget.tasks.total', 1)
            ->assertJsonPath('data.designer_widget.tasks.pending', 1)
            ->assertJsonPath('data.designer_widget.tasks.completed', 0)
            ->assertJsonPath('data.designer_widget.rfis.total', 1)
            ->assertJsonPath('data.designer_widget.rfis.pending', 1)
            ->assertJsonPath('data.designer_widget.rfis.closed', 0)
            ->assertJsonPath('data.designer_widget.submittals.total', 1)
            ->assertJsonPath('data.designer_widget.submittals.draft', 1)
            ->assertJsonPath('data.designer_widget.submittals.approved', 0);

        $emptyProject = $this->createAssignedProject('Empty designer project');

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.designer.dashboard', ['project_id' => (string) $emptyProject->id], false))
            ->assertOk()
            ->assertJsonPath('data.designer_widget.widget_key', 'designer_summary')
            ->assertJsonPath('data.designer_widget.tasks.total', 0)
            ->assertJsonPath('data.designer_widget.rfis.total', 0)
            ->assertJsonPath('data.designer_widget.submittals.total', 0);
    }

    public function test_designer_rfis_route_returns_only_assigned_project_rfis_for_actor(): void
    {
        $project = $this->createAssignedProject('Designer RFIs project');
        $otherAssignedProject = $this->createAssignedProject('Designer RFIs secondary project');
        $creator = User::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Canonical RFI Creator',
        ]);
        $asker = User::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Different Asking User',
        ]);
        $unassignedProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
            'name' => 'Unassigned designer project',
        ]);

        $visiblePending = Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $creator->id,
            'asked_by' => (string) $asker->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'pending',
            'title' => 'Visible pending RFI',
            'description' => 'Canonical description from rfis table',
        ]);
        $visibleAnswered = Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'answered',
            'title' => 'Visible answered RFI',
        ]);

        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) User::factory()->create([
                'tenant_id' => (string) $this->tenant->id,
            ])->id,
            'status' => 'pending',
            'title' => 'Hidden other assignee RFI',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $unassignedProject->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'pending',
            'title' => 'Hidden unassigned project RFI',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], [
            'designer.rfis',
        ]);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign designer project',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'assigned_to' => (string) $foreignActor->id,
            'status' => 'pending',
            'title' => 'Foreign tenant RFI',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.designer.rfis', [], false))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $visiblePending->id)
            ->assertJsonPath('data.0.title', 'Visible pending RFI')
            ->assertJsonPath('data.0.description', 'Canonical description from rfis table')
            ->assertJsonPath('data.0.project.id', (string) $project->id)
            ->assertJsonPath('data.0.created_by.id', (string) $creator->id)
            ->assertJsonPath('data.0.created_by.name', 'Canonical RFI Creator')
            ->assertJsonMissing(['title' => 'Hidden other assignee RFI'])
            ->assertJsonMissing(['title' => 'Hidden unassigned project RFI'])
            ->assertJsonMissing(['title' => 'Foreign tenant RFI'])
            ->assertJsonMissing(['name' => 'Different Asking User']);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.designer.rfis', [
                'status' => 'answered',
                'project_id' => (string) $otherAssignedProject->id,
            ], false))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $visibleAnswered->id)
            ->assertJsonPath('data.0.title', 'Visible answered RFI')
            ->assertJsonPath('data.0.status', 'answered');
    }

    public function test_designer_rfis_route_requires_designer_rfis_permission(): void
    {
        $restricted = $this->createTenantUser($this->tenant, [], ['member'], []);
        $restrictedToken = $this->apiLoginToken($restricted, $this->tenant);
        $restrictedHeaders = $this->authHeadersForUser($restricted, $restrictedToken);

        $this->withHeaders($restrictedHeaders)
            ->getJson(route('api.zena.designer.rfis', [], false))
            ->assertForbidden();
    }

    private function createAssignedProject(string $name): Project
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
            'name' => $name,
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'designer'],
            [
                'scope' => 'system',
                'description' => 'Designer',
                'is_active' => true,
            ]
        );

        UserRoleProject::query()->create([
            'project_id' => (string) $project->id,
            'user_id' => (string) $this->actor->id,
            'role_id' => (string) $role->id,
        ]);

        return $project;
    }

    private function createAssignedTask(Project $project, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => Task::STATUS_PENDING,
        ], $overrides));
    }

    private function createUnassignedTask(Project $project): Task
    {
        return Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) User::factory()->create([
                'tenant_id' => (string) $this->tenant->id,
            ])->id,
            'status' => Task::STATUS_PENDING,
        ]);
    }
}
