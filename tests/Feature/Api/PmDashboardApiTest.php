<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class PmDashboardApiTest extends TestCase
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
            'pm.dashboard',
            'pm.progress',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
    }

    public function test_pm_dashboard_exposes_minimal_pm_widget_from_pm_scoped_project_task_and_rfi_facts(): void
    {
        $project = $this->createAssignedProject('PM dashboard project', Project::STATUS_ACTIVE);
        $otherAssignedProject = $this->createAssignedProject('Second PM dashboard project', Project::STATUS_COMPLETED);

        $this->createTask($project, [
            'status' => Task::STATUS_PENDING,
            'end_date' => now()->subDay(),
        ]);
        $this->createTask($project, [
            'status' => Task::STATUS_COMPLETED,
            'end_date' => now()->subDays(2),
        ]);
        $this->createTask($otherAssignedProject, [
            'status' => Task::STATUS_IN_PROGRESS,
            'end_date' => now()->addDay(),
        ]);
        $this->createTask($project, [
            'status' => Task::STATUS_CANCELLED,
            'end_date' => now()->subDays(3),
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign PM project',
            'status' => Project::STATUS_ACTIVE,
        ]);
        Task::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'assigned_to' => (string) $foreignActor->id,
            'status' => Task::STATUS_PENDING,
            'end_date' => now()->subDay(),
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
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'pending',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => 'closed',
        ]);
        Rfi::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
            'assigned_to' => (string) $foreignActor->id,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.dashboard', [], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.pm_widget.widget_key', 'pm_summary')
            ->assertJsonPath('data.pm_widget.projects.total', 2)
            ->assertJsonPath('data.pm_widget.projects.active', 1)
            ->assertJsonPath('data.pm_widget.projects.completed', 1)
            ->assertJsonPath('data.pm_widget.tasks.total', 4)
            ->assertJsonPath('data.pm_widget.tasks.completed', 1)
            ->assertJsonPath('data.pm_widget.tasks.overdue', 1)
            ->assertJsonPath('data.pm_widget.rfis.pending', 2)
            ->assertJsonMissingPath('data.pm_widget.alerts')
            ->assertJsonMissingPath('data.pm_widget.notification_rules')
            ->assertJsonMissingPath('data.pm_widget.event_records')
            ->assertJsonMissingPath('data.qc_widget')
            ->assertJsonMissingPath('data.designer_widget');
    }

    public function test_pm_dashboard_widget_is_zero_safe_and_project_scoped(): void
    {
        $project = $this->createAssignedProject('Scoped PM project', Project::STATUS_ACTIVE);
        $otherProject = $this->createAssignedProject('Excluded PM project', Project::STATUS_COMPLETED);

        $this->createTask($project, [
            'status' => Task::STATUS_PENDING,
            'end_date' => now()->subDay(),
        ]);
        $this->createTask($otherProject, [
            'status' => Task::STATUS_COMPLETED,
            'end_date' => now()->subDay(),
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
            'status' => 'pending',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.dashboard', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.pm_widget.widget_key', 'pm_summary')
            ->assertJsonPath('data.pm_widget.projects.total', 1)
            ->assertJsonPath('data.pm_widget.projects.active', 1)
            ->assertJsonPath('data.pm_widget.projects.completed', 0)
            ->assertJsonPath('data.pm_widget.tasks.total', 1)
            ->assertJsonPath('data.pm_widget.tasks.completed', 0)
            ->assertJsonPath('data.pm_widget.tasks.overdue', 1)
            ->assertJsonPath('data.pm_widget.rfis.pending', 1);

        $emptyProject = $this->createAssignedProject('Empty PM project', Project::STATUS_PLANNING);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.dashboard', ['project_id' => (string) $emptyProject->id], false))
            ->assertOk()
            ->assertJsonPath('data.pm_widget.widget_key', 'pm_summary')
            ->assertJsonPath('data.pm_widget.projects.total', 1)
            ->assertJsonPath('data.pm_widget.tasks.total', 0)
            ->assertJsonPath('data.pm_widget.tasks.overdue', 0)
            ->assertJsonPath('data.pm_widget.rfis.pending', 0);
    }

    public function test_pm_progress_route_returns_schema_backed_projection_for_accessible_project(): void
    {
        $project = $this->createAssignedProject('PM progress project', Project::STATUS_ACTIVE);
        $project->forceFill([
            'budget_total' => 100000,
            'budget_actual' => 25000,
            'start_date' => now()->subDays(10)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ])->save();

        $this->createTask($project, [
            'status' => Task::STATUS_COMPLETED,
            'end_date' => now()->subDays(3),
        ]);
        $this->createTask($project, [
            'status' => Task::STATUS_IN_PROGRESS,
            'end_date' => now()->subDay(),
        ]);
        $this->createTask($project, [
            'status' => Task::STATUS_PENDING,
            'end_date' => now()->addDays(2),
        ]);
        $this->createTask($project, [
            'status' => Task::STATUS_CANCELLED,
            'end_date' => now()->subDays(2),
        ]);

        ProjectMilestone::query()->create([
            'project_id' => (string) $project->id,
            'name' => 'Foundation complete',
            'status' => ProjectMilestone::STATUS_COMPLETED,
            'target_date' => now()->subDays(5)->toDateString(),
            'completed_date' => now()->subDays(4)->toDateString(),
            'created_by' => (string) $this->actor->id,
        ]);
        $upcomingMilestone = ProjectMilestone::query()->create([
            'project_id' => (string) $project->id,
            'name' => 'Frame inspection',
            'status' => ProjectMilestone::STATUS_PENDING,
            'target_date' => now()->addDays(3)->toDateString(),
            'created_by' => (string) $this->actor->id,
        ]);
        ProjectMilestone::query()->create([
            'project_id' => (string) $project->id,
            'name' => 'Delayed handoff',
            'status' => ProjectMilestone::STATUS_OVERDUE,
            'target_date' => now()->subDay()->toDateString(),
            'created_by' => (string) $this->actor->id,
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.project.id', (string) $project->id)
            ->assertJsonPath('data.project.name', 'PM progress project')
            ->assertJsonPath('data.overall_progress', 25)
            ->assertJsonPath('data.overall_progress_meta.value', 25)
            ->assertJsonPath('data.overall_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.task_progress.total', 4)
            ->assertJsonPath('data.task_progress.completed', 1)
            ->assertJsonPath('data.task_progress.in_progress', 1)
            ->assertJsonPath('data.task_progress.pending', 1)
            ->assertJsonPath('data.task_progress.overdue', 1)
            ->assertJsonPath('data.milestone_progress.total_milestones', 3)
            ->assertJsonPath('data.milestone_progress.completed_milestones', 1)
            ->assertJsonPath('data.milestone_progress.pending_milestones', 1)
            ->assertJsonPath('data.milestone_progress.overdue_milestones', 1)
            ->assertJsonPath('data.milestone_progress.completion_rate', 33.33)
            ->assertJsonPath('data.milestone_progress_meta.value', 33.33)
            ->assertJsonPath('data.milestone_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.milestone_progress_meta.reliability', 'LEGACY')
            ->assertJsonCount(2, 'data.milestone_progress.upcoming_milestones')
            ->assertJsonPath('data.milestone_progress.upcoming_milestones.0.name', 'Delayed handoff')
            ->assertJsonPath('data.milestone_progress.upcoming_milestones.1.id', (string) $upcomingMilestone->id)
            ->assertJsonPath('data.budget_progress.total_budget', 100000)
            ->assertJsonPath('data.budget_progress.spent_amount', 25000)
            ->assertJsonPath('data.budget_progress.remaining_amount', 75000)
            ->assertJsonPath('data.budget_progress.percentage_spent', 25)
            ->assertJsonPath('data.budget_progress_meta.value', 25)
            ->assertJsonPath('data.budget_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.timeline_progress.total_days', 20)
            ->assertJsonPath('data.timeline_progress.days_elapsed', 10)
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 50)
            ->assertJsonPath('data.timeline_progress_meta.value', 50)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.timeline_progress_meta.label', 'Tỷ lệ thời gian kế hoạch đã trôi qua');
    }

    public function test_timeline_progress_meta_is_not_applicable_when_dates_missing(): void
    {
        $project = $this->createAssignedProject('No dates project', Project::STATUS_ACTIVE, [
            'start_date' => null,
            'end_date' => null,
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 0)
            ->assertJsonPath('data.timeline_progress_meta.value', null)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'NOT_APPLICABLE')
            ->assertJsonPath('data.timeline_progress_meta.reliability', 'RELIABLE')
            ->assertJsonPath('data.timeline_progress_meta.label', 'Tỷ lệ thời gian kế hoạch đã trôi qua')
            ->assertJsonPath('data.timeline_progress_meta.as_of', null);
    }

    public function test_timeline_progress_is_clamped_to_zero_when_project_starts_in_the_future(): void
    {
        $project = $this->createAssignedProject('Future start project', Project::STATUS_PLANNING, [
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 0)
            ->assertJsonPath('data.timeline_progress_meta.value', 0)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'AVAILABLE');
    }

    public function test_timeline_progress_is_clamped_to_one_hundred_when_project_has_ended(): void
    {
        $project = $this->createAssignedProject('Ended project', Project::STATUS_COMPLETED, [
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDays(10)->toDateString(),
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.timeline_progress.percentage_elapsed', 100)
            ->assertJsonPath('data.timeline_progress_meta.value', 100)
            ->assertJsonPath('data.timeline_progress_meta.availability', 'AVAILABLE');
    }

    public function test_pm_progress_route_requires_project_id_and_hides_inaccessible_projects(): void
    {
        $project = $this->createAssignedProject('Accessible PM progress project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', [], false))
            ->assertStatus(400)
            ->assertJsonPath('message', 'Project ID is required');

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign progress project',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $foreignProject->id], false))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Project not found or access denied');

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.project.id', (string) $project->id);
    }

    public function test_pm_progress_route_requires_project_membership_even_within_same_tenant(): void
    {
        $sameTenantProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
            'name' => 'Same-tenant unassigned project',
            'status' => Project::STATUS_ACTIVE,
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $sameTenantProject->id], false))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Project not found or access denied');
    }

    public function test_overall_progress_meta_is_no_data_when_project_has_no_tasks(): void
    {
        $project = $this->createAssignedProject('Empty progress project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.overall_progress', 0)
            ->assertJsonPath('data.overall_progress_meta.value', null)
            ->assertJsonPath('data.overall_progress_meta.availability', 'NO_DATA')
            ->assertJsonPath('data.overall_progress_meta.reliability', 'RELIABLE')
            ->assertJsonPath('data.overall_progress_meta.freshness', 'UNKNOWN')
            ->assertJsonPath('data.overall_progress_meta.as_of', null)
            ->assertJsonPath('data.overall_progress_meta.explanation', 'Dự án chưa có công việc (Task) nào được tạo.');
    }

    public function test_milestone_progress_meta_is_no_data_and_legacy_when_project_has_no_milestones(): void
    {
        $project = $this->createAssignedProject('No milestone project', Project::STATUS_ACTIVE);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.milestone_progress_meta.value', null)
            ->assertJsonPath('data.milestone_progress_meta.availability', 'NO_DATA')
            ->assertJsonPath('data.milestone_progress_meta.reliability', 'LEGACY');
    }

    public function test_budget_progress_meta_is_not_applicable_when_no_budget_entered(): void
    {
        $project = $this->createAssignedProject('No budget project', Project::STATUS_ACTIVE, ['budget_actual' => 0]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.budget_progress', ['total_budget' => 0, 'spent_amount' => 0, 'remaining_amount' => 0, 'percentage_spent' => 0])
            ->assertJsonPath('data.budget_progress_meta.value', null)
            ->assertJsonPath('data.budget_progress_meta.availability', 'NOT_APPLICABLE')
            ->assertJsonPath('data.budget_progress_meta.reliability', 'RELIABLE');
    }

    public function test_task_query_failure_degrades_overall_and_task_progress_without_500ing_the_endpoint(): void
    {
        $project = $this->createAssignedProject('Task query failure project', Project::STATUS_ACTIVE, [
            'budget_total' => 100000,
            'budget_actual' => 25000,
        ]);

        ProjectMilestone::query()->create([
            'project_id' => (string) $project->id,
            'name' => 'Unaffected milestone',
            'status' => ProjectMilestone::STATUS_COMPLETED,
            'target_date' => now()->subDays(5)->toDateString(),
            'completed_date' => now()->subDays(4)->toDateString(),
            'created_by' => (string) $this->actor->id,
        ]);

        Schema::rename('tasks', 'tasks_test_failure_injection');

        try {
            $response = $this->withHeaders($this->headers)
                ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false));
        } finally {
            Schema::rename('tasks_test_failure_injection', 'tasks');
        }

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.overall_progress', 0)
            ->assertJsonPath('data.overall_progress_meta.value', null)
            ->assertJsonPath('data.overall_progress_meta.availability', 'ERROR')
            ->assertJsonPath('data.task_progress', ['total' => 0, 'completed' => 0, 'in_progress' => 0, 'pending' => 0, 'overdue' => 0])
            // Milestone and budget metrics run their own queries and must be unaffected by the Task table failure.
            ->assertJsonPath('data.milestone_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.milestone_progress_meta.value', 100)
            ->assertJsonPath('data.budget_progress_meta.availability', 'AVAILABLE');
    }

    public function test_milestone_query_failure_degrades_milestone_progress_without_500ing_the_endpoint(): void
    {
        $project = $this->createAssignedProject('Milestone query failure project', Project::STATUS_ACTIVE);

        $this->createTask($project, [
            'status' => Task::STATUS_COMPLETED,
            'end_date' => now()->subDay(),
        ]);

        Schema::rename('project_milestones', 'project_milestones_test_failure_injection');

        try {
            $response = $this->withHeaders($this->headers)
                ->getJson(route('api.zena.pm.progress', ['project_id' => (string) $project->id], false));
        } finally {
            Schema::rename('project_milestones_test_failure_injection', 'project_milestones');
        }

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.milestone_progress_meta.value', null)
            ->assertJsonPath('data.milestone_progress_meta.availability', 'ERROR')
            ->assertJsonPath('data.milestone_progress', [
                'total_milestones' => 0,
                'completed_milestones' => 0,
                'pending_milestones' => 0,
                'overdue_milestones' => 0,
                'completion_rate' => 0,
                'upcoming_milestones' => [],
            ])
            // Task metrics run their own query and must be unaffected by the Milestone table failure.
            ->assertJsonPath('data.overall_progress_meta.availability', 'AVAILABLE')
            ->assertJsonPath('data.overall_progress_meta.value', 100);
    }

    private function createAssignedProject(string $name, string $status, array $attributes = []): Project
    {
        $project = Project::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->actor->id,
            'pm_id' => (string) $this->actor->id,
            'name' => $name,
            'status' => $status,
        ], $attributes));

        $role = Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'system',
                'description' => 'Project Manager',
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

    private function createTask(Project $project, array $overrides = []): Task
    {
        return Task::factory()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
            'assigned_to' => (string) $this->actor->id,
            'status' => Task::STATUS_PENDING,
            'end_date' => now()->addDay(),
        ], $overrides));
    }
}
