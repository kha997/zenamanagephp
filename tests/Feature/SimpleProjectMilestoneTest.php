<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectMilestone;

class SimpleProjectMilestoneTest extends TestCase
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
     * Test ProjectMilestone model creation with minimal data
     */
    public function test_project_milestone_minimal_creation(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-001',
            'name' => 'Test Project',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Test Milestone'
        ]);

        $this->assertNotNull($milestone);
        $this->assertEquals('Test Milestone', $milestone->name);
        $this->assertEquals('pending', $milestone->status);
        $this->assertEquals($project->id, $milestone->project_id);
        $this->assertEquals(0, $milestone->order);
    }

    /**
     * Test ProjectMilestone model creation with all required fields
     */
    public function test_project_milestone_full_creation(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-002',
            'name' => 'Test Project Full',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Full Test Milestone',
            'description' => 'Full milestone description',
            'status' => 'pending',
            'order' => 1
        ]);

        $this->assertNotNull($milestone);
        $this->assertEquals('Full Test Milestone', $milestone->name);
        $this->assertEquals('Full milestone description', $milestone->description);
        $this->assertEquals('pending', $milestone->status);
        $this->assertEquals(1, $milestone->order);
        $this->assertEquals($project->id, $milestone->project_id);
    }
}
