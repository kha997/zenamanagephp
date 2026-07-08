<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRoleProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

class SiteEngineerDashboardApiTest extends TestCase
{
    use RefreshDatabase;
    use AuthenticationTestTrait;

    private const CAPA_CONTEXT_TAG = 'inspection-ncr-capa';

    protected Tenant $tenant;
    protected User $actor;
    protected array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['admin'], [
            'site-engineer.dashboard',
            'site-engineer.inspections',
        ]);

        $token = $this->apiLoginToken($this->actor, $this->tenant);
        $this->headers = $this->authHeadersForUser($this->actor, $token);
    }

    public function test_site_engineer_dashboard_exposes_qc_summary_widget_from_canonical_sources(): void
    {
        $project = $this->createAssignedProject('QC dashboard project');
        $otherAssignedProject = $this->createAssignedProject('Second QC dashboard project');

        $planA = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $planB = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $inspectionScheduled = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $planA->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'scheduled',
        ]);

        $inspectionFailed = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $planA->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'failed',
        ]);

        $inspectionCompleted = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $planB->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'completed',
        ]);

        Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'inspection_id' => (string) $inspectionScheduled->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'open',
        ]);

        Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'inspection_id' => (string) $inspectionFailed->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'in_progress',
        ]);

        Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherAssignedProject->id,
            'inspection_id' => (string) $inspectionCompleted->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'closed',
        ]);

        $this->createCapaTask($project, [
            'end_date' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);
        $this->createCapaTask($project, [
            'end_date' => now()->subDays(2),
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        $this->createCapaTask($project, [
            'end_date' => now()->subDay(),
            'status' => Task::STATUS_COMPLETED,
        ]);
        $this->createCapaTask($project, [
            'end_date' => now()->addDay(),
            'status' => Task::STATUS_PENDING,
        ]);
        $this->createGeneralTask($project);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign project',
        ]);
        $foreignPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
        ]);
        $foreignInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'qc_plan_id' => (string) $foreignPlan->id,
            'inspector_id' => (string) $foreignActor->id,
            'status' => 'failed',
        ]);
        Ncr::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'inspection_id' => (string) $foreignInspection->id,
            'created_by' => (string) $foreignActor->id,
            'status' => 'open',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.dashboard', [], false));

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.qc_widget.widget_key', 'qc_summary')
            ->assertJsonPath('data.qc_widget.inspections.total', 3)
            ->assertJsonPath('data.qc_widget.inspections.scheduled', 1)
            ->assertJsonPath('data.qc_widget.inspections.failed', 1)
            ->assertJsonPath('data.qc_widget.inspections.completed', 1)
            ->assertJsonPath('data.qc_widget.ncrs.total', 3)
            ->assertJsonPath('data.qc_widget.ncrs.open', 1)
            ->assertJsonPath('data.qc_widget.ncrs.in_progress', 1)
            ->assertJsonPath('data.qc_widget.ncrs.closed', 1)
            ->assertJsonPath('data.qc_widget.overdue_capa_tasks.total', 2);
    }

    public function test_site_engineer_dashboard_qc_widget_is_zero_safe_and_project_scoped(): void
    {
        $project = $this->createAssignedProject('Scoped project');
        $otherProject = $this->createAssignedProject('Excluded project');

        $planIncluded = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
        ]);
        $planExcluded = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $includedInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $planIncluded->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'scheduled',
        ]);

        $excludedInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $planExcluded->id,
            'inspector_id' => (string) $this->actor->id,
            'status' => 'failed',
        ]);

        Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'inspection_id' => (string) $includedInspection->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'open',
        ]);

        Ncr::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'inspection_id' => (string) $excludedInspection->id,
            'created_by' => (string) $this->actor->id,
            'status' => 'closed',
        ]);

        $this->createCapaTask($project, [
            'end_date' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);
        $this->createCapaTask($otherProject, [
            'end_date' => now()->subDay(),
            'status' => Task::STATUS_PENDING,
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.dashboard', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('data.qc_widget.inspections.total', 1)
            ->assertJsonPath('data.qc_widget.inspections.scheduled', 1)
            ->assertJsonPath('data.qc_widget.inspections.failed', 0)
            ->assertJsonPath('data.qc_widget.ncrs.total', 1)
            ->assertJsonPath('data.qc_widget.ncrs.open', 1)
            ->assertJsonPath('data.qc_widget.ncrs.closed', 0)
            ->assertJsonPath('data.qc_widget.overdue_capa_tasks.total', 1);

        $emptyProject = $this->createAssignedProject('Empty scoped project');

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.dashboard', ['project_id' => (string) $emptyProject->id], false))
            ->assertOk()
            ->assertJsonPath('data.qc_widget.widget_key', 'qc_summary')
            ->assertJsonPath('data.qc_widget.inspections.total', 0)
            ->assertJsonPath('data.qc_widget.ncrs.total', 0)
            ->assertJsonPath('data.qc_widget.overdue_capa_tasks.total', 0);
    }

    public function test_site_engineer_inspections_route_returns_project_scoped_qc_inspections(): void
    {
        $project = $this->createAssignedProject('Inspection list project');
        $otherProject = $this->createAssignedProject('Other inspection list project');

        $includedPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
        ]);
        $excludedPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $includedInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $includedPlan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Scheduled concrete inspection',
            'status' => 'scheduled',
            'inspection_date' => '2026-04-10',
        ]);
        QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $excludedPlan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Failed excluded inspection',
            'status' => 'failed',
            'inspection_date' => '2026-04-12',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.inspections', ['project_id' => (string) $project->id], false))
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $includedInspection->id)
            ->assertJsonPath('data.0.project.id', (string) $project->id)
            ->assertJsonPath('data.0.project.name', 'Inspection list project')
            ->assertJsonPath('data.0.scheduled_date', '2026-04-10T00:00:00.000000Z');
    }

    public function test_site_engineer_inspections_route_is_tenant_safe_and_status_filterable(): void
    {
        $project = $this->createAssignedProject('Inspection filter project');

        $plan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
        ]);

        QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $plan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Included failed inspection',
            'status' => 'failed',
            'inspection_date' => '2026-04-14',
        ]);
        QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $plan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Excluded completed inspection',
            'status' => 'completed',
            'inspection_date' => '2026-04-15',
        ]);

        $foreignTenant = Tenant::factory()->create();
        $foreignActor = $this->createTenantUser($foreignTenant, [], ['admin'], []);
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'created_by' => (string) $foreignActor->id,
            'pm_id' => (string) $foreignActor->id,
            'name' => 'Foreign inspection project',
        ]);
        $foreignPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'project_id' => (string) $foreignProject->id,
            'created_by' => (string) $foreignActor->id,
        ]);
        QcInspection::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'qc_plan_id' => (string) $foreignPlan->id,
            'inspector_id' => (string) $foreignActor->id,
            'title' => 'Foreign failed inspection',
            'status' => 'failed',
            'inspection_date' => '2026-04-16',
        ]);

        $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.inspections', ['status' => 'failed'], false))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Included failed inspection')
            ->assertJsonMissing(['title' => 'Excluded completed inspection'])
            ->assertJsonMissing(['title' => 'Foreign failed inspection']);
    }

    public function test_site_engineer_inspections_route_sorts_by_inspection_date_and_maps_it_to_scheduled_date(): void
    {
        $project = $this->createAssignedProject('Inspection ordering project');
        $otherProject = $this->createAssignedProject('Inspection ordering excluded project');

        $includedPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'created_by' => (string) $this->actor->id,
        ]);
        $excludedPlan = QcPlan::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $otherProject->id,
            'created_by' => (string) $this->actor->id,
        ]);

        $laterIncludedInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $includedPlan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Later included inspection',
            'status' => 'scheduled',
            'inspection_date' => '2026-04-22',
        ]);
        $earlierIncludedInspection = QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $includedPlan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Earlier included inspection',
            'status' => 'scheduled',
            'inspection_date' => '2026-04-20',
        ]);
        QcInspection::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'qc_plan_id' => (string) $excludedPlan->id,
            'inspector_id' => (string) $this->actor->id,
            'title' => 'Excluded earliest inspection',
            'status' => 'scheduled',
            'inspection_date' => '2026-04-18',
        ]);

        $response = $this->withHeaders($this->headers)
            ->getJson(route('api.zena.site-engineer.inspections', ['project_id' => (string) $project->id], false));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $earlierIncludedInspection->id)
            ->assertJsonPath('data.0.scheduled_date', '2026-04-20T00:00:00.000000Z')
            ->assertJsonPath('data.1.id', (string) $laterIncludedInspection->id)
            ->assertJsonPath('data.1.scheduled_date', '2026-04-22T00:00:00.000000Z')
            ->assertJsonMissing(['title' => 'Excluded earliest inspection']);
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
            ['name' => 'site_engineer'],
            [
                'scope' => 'system',
                'description' => 'Site Engineer',
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

    private function createCapaTask(Project $project, array $overrides = []): Task
    {
        return Task::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Dashboard CAPA task',
            'title' => 'Dashboard CAPA task',
            'description' => 'CAPA task for QC widget projection.',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_HIGH,
            'end_date' => now()->subDay(),
            'tags' => [self::CAPA_CONTEXT_TAG],
            'created_by' => (string) $this->actor->id,
        ], $overrides));
    }

    private function createGeneralTask(Project $project): Task
    {
        return Task::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'General task',
            'title' => 'General task',
            'description' => 'Non-CAPA task should not count toward QC widget overdue CAPA facts.',
            'status' => Task::STATUS_PENDING,
            'priority' => Task::PRIORITY_MEDIUM,
            'end_date' => now()->subDay(),
            'tags' => ['general-task'],
            'created_by' => (string) $this->actor->id,
        ]);
    }
}
