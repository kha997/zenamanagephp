<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectMilestone;

class ProjectModuleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Test Project model creation and relationships
     */
    public function test_project_model_creation(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-001',
            'name' => 'Test Project',
            'description' => 'Test project description',
            'status' => 'active',
            'progress' => 0,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $this->assertNotNull($project);
        $this->assertEquals('PRJ-TEST-001', $project->code);
        $this->assertEquals('Test Project', $project->name);
        $this->assertEquals('active', $project->status);
        $this->assertEquals(0, $project->progress);
        $this->assertEquals($this->tenant->id, $project->tenant_id);
    }

    /**
     * Test Project milestone creation
     */
    public function test_project_milestone_creation(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-002',
            'name' => 'Test Project with Milestones',
            'description' => 'Test project with milestones',
            'status' => 'active',
            'progress' => 0,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'First Milestone',
            'status' => 'pending'
        ]);

        $this->assertNotNull($milestone);
        $this->assertEquals('First Milestone', $milestone->name);
        $this->assertEquals('pending', $milestone->status);
        $this->assertEquals($project->id, $milestone->project_id);
        // $this->assertEquals($this->user->id, $milestone->created_by);
    }

    /**
     * Test Project milestone status update
     */
    public function test_project_milestone_status_update(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-003',
            'name' => 'Test Project Status Update',
            'description' => 'Test project for status update',
            'status' => 'active',
            'progress' => 0,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Test Milestone',
            'status' => 'pending'
        ]);

        // Test mark as completed
        $milestone->markCompleted();
        $this->assertEquals('completed', $milestone->status);
        $this->assertNotNull($milestone->completed_date);

        // Test mark as cancelled
        $milestone->markCancelled();
        $this->assertEquals('cancelled', $milestone->status);
    }

    /**
     * Test Project progress calculation
     */
    public function test_project_progress_calculation(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-004',
            'name' => 'Test Project Progress',
            'description' => 'Test project for progress calculation',
            'status' => 'active',
            'progress' => 0,
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        // Create milestones
        ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 1',
            'status' => 'completed'
        ]);

        ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Milestone 2',
            'status' => 'pending'
        ]);

        // Test progress calculation
        $progress = $project->calculateProgress();
        $this->assertEquals(50, $progress); // 1 completed out of 2 milestones = 50%
    }

    /**
     * Test Project scopes
     */
    public function test_project_scopes(): void
    {
        // Create projects with different statuses
        $activeProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-ACTIVE-001',
            'name' => 'Active Project',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $completedProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-COMPLETED-001',
            'name' => 'Completed Project',
            'status' => 'completed',
            'start_date' => now()->subDays(30),
            'end_date' => now()->subDays(1),
            'budget_total' => 100000
        ]);

        // Test active scope
        $activeProjects = Project::active()->get();
        $this->assertTrue($activeProjects->contains('id', $activeProject->id));

        // Test byStatus scope
        $completedProjects = Project::byStatus('completed')->get();
        $this->assertTrue($completedProjects->contains('id', $completedProject->id));
    }
}
