<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\ProjectMilestone;

class VerySimpleProjectMilestoneTest extends TestCase
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
     * Test ProjectMilestone model creation with only required fields
     */
    public function test_project_milestone_required_fields_only(): void
    {
        $project = Project::factory()->create([
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
    }

    /**
     * Test ProjectMilestone model creation with explicit order
     */
    public function test_project_milestone_with_explicit_order(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST-002',
            'name' => 'Test Project Order',
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'budget_total' => 100000
        ]);

        $milestone = ProjectMilestone::create([
            'project_id' => $project->id,
            'name' => 'Order Test Milestone',
            'order' => 5
        ]);

        $this->assertNotNull($milestone);
        $this->assertEquals('Order Test Milestone', $milestone->name);
        $this->assertEquals(5, $milestone->order);
        $this->assertEquals($project->id, $milestone->project_id);
    }
}

