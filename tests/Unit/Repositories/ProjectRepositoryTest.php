<?php

namespace Tests\Unit\Repositories;

use Tests\TestCase;
use App\Repositories\ProjectRepository;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected $projectRepository;
    protected $tenant;
    protected $user;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->projectRepository = new ProjectRepository(new Project());
        
        // Create test tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create test user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'manager_id' => $this->user->id
        ]);
    }

    /** @test */
    public function it_can_get_all_projects_with_pagination()
    {
        // Create additional projects
        Project::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getAll([], 10);
        
        $this->assertInstanceOf(LengthAwarePaginator::class, $result);
        $this->assertEquals(6, $result->total()); // 1 original + 5 new
    }

    /** @test */
    public function it_can_filter_projects_by_tenant_id()
    {
        // Create projects for different tenant
        $otherTenant = Tenant::factory()->create();
        Project::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);
        
        $result = $this->projectRepository->getAll(['tenant_id' => $this->tenant->id], 10);
        
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_can_filter_projects_by_status()
    {
        // Create projects with different statuses
        Project::factory()->create(['status' => 'active', 'tenant_id' => $this->tenant->id]);
        Project::factory()->create(['status' => 'completed', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getAll(['status' => 'active'], 10);
        
        $this->assertEquals(2, $result->total()); // 1 original + 1 new
    }

    /** @test */
    public function it_can_filter_projects_by_manager_id()
    {
        $result = $this->projectRepository->getAll(['manager_id' => $this->user->id], 10);
        
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_can_search_projects()
    {
        // Create project with specific name
        Project::factory()->create([
            'name' => 'Test Project',
            'description' => 'Test Description',
            'tenant_id' => $this->tenant->id
        ]);
        
        $result = $this->projectRepository->search('Test', 10);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_project_by_id()
    {
        $result = $this->projectRepository->getById($this->project->id);
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals($this->project->id, $result->id);
    }

    /** @test */
    public function it_can_get_projects_by_tenant_id()
    {
        $result = $this->projectRepository->getByTenantId($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_projects_by_manager_id()
    {
        $result = $this->projectRepository->getByManagerId($this->user->id);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_projects_by_status()
    {
        $result = $this->projectRepository->getByStatus($this->project->status);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_create_project()
    {
        $projectData = [
            'name' => 'Test Project',
            'description' => 'Test Description',
            'status' => 'active',
            'priority' => 'high',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
            'tenant_id' => $this->tenant->id,
            'manager_id' => $this->user->id
        ];
        
        $result = $this->projectRepository->create($projectData);
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('Test Project', $result->name);
        $this->assertEquals('Test Description', $result->description);
    }

    /** @test */
    public function it_can_update_project()
    {
        $updateData = [
            'name' => 'Updated Project',
            'description' => 'Updated Description'
        ];
        
        $result = $this->projectRepository->update($this->project->id, $updateData);
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('Updated Project', $result->name);
        $this->assertEquals('Updated Description', $result->description);
    }

    /** @test */
    public function it_can_delete_project()
    {
        $result = $this->projectRepository->delete($this->project->id);
        
        $this->assertTrue($result);
        $this->assertNull(Project::find($this->project->id));
    }

    /** @test */
    public function it_can_soft_delete_project()
    {
        $result = $this->projectRepository->softDelete($this->project->id);
        
        $this->assertTrue($result);
        $this->assertSoftDeleted('projects', ['id' => $this->project->id]);
    }

    /** @test */
    public function it_can_restore_soft_deleted_project()
    {
        // Soft delete project first
        $this->projectRepository->softDelete($this->project->id);
        
        $result = $this->projectRepository->restore($this->project->id);
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'deleted_at' => null]);
    }

    /** @test */
    public function it_can_get_active_projects()
    {
        // Create active and inactive projects
        Project::factory()->create(['status' => 'active', 'tenant_id' => $this->tenant->id]);
        Project::factory()->create(['status' => 'completed', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getActive();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_completed_projects()
    {
        // Create completed project
        Project::factory()->create(['status' => 'completed', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getCompleted();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_overdue_projects()
    {
        // Create overdue project
        Project::factory()->create([
            'end_date' => now()->subDays(1),
            'status' => 'active',
            'tenant_id' => $this->tenant->id
        ]);
        
        $result = $this->projectRepository->getOverdue();
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_projects_starting_soon()
    {
        // Create project starting soon
        Project::factory()->create([
            'start_date' => now()->addDays(3),
            'status' => 'pending',
            'tenant_id' => $this->tenant->id
        ]);
        
        $result = $this->projectRepository->getStartingSoon(7);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_get_projects_ending_soon()
    {
        // Create project ending soon
        Project::factory()->create([
            'end_date' => now()->addDays(3),
            'status' => 'active',
            'tenant_id' => $this->tenant->id
        ]);
        
        $result = $this->projectRepository->getEndingSoon(7);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
    }

    /** @test */
    public function it_can_update_project_status()
    {
        $result = $this->projectRepository->updateStatus($this->project->id, 'completed');
        
        $this->assertTrue($result);
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'status' => 'completed'
        ]);
    }

    /** @test */
    public function it_can_assign_team_to_project()
    {
        $team = Team::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->assignTeam($this->project->id, $team->id, 'member');
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_remove_team_from_project()
    {
        $team = Team::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Assign team first
        $this->projectRepository->assignTeam($this->project->id, $team->id, 'member');
        
        $result = $this->projectRepository->removeTeam($this->project->id, $team->id);
        
        $this->assertTrue($result);
    }

    /** @test */
    public function it_can_get_project_statistics()
    {
        // Create projects with different statuses
        Project::factory()->create(['status' => 'active', 'tenant_id' => $this->tenant->id]);
        Project::factory()->create(['status' => 'completed', 'tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getStatistics($this->tenant->id);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_projects', $result);
        $this->assertArrayHasKey('active_projects', $result);
        $this->assertArrayHasKey('completed_projects', $result);
    }

    /** @test */
    public function it_can_get_projects_by_multiple_ids()
    {
        $project2 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $project3 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getByIds([$this->project->id, $project2->id, $project3->id]);
        
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertEquals(3, $result->count());
    }

    /** @test */
    public function it_can_bulk_update_projects()
    {
        $project2 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->bulkUpdate(
            [$this->project->id, $project2->id],
            ['status' => 'completed']
        );
        
        $this->assertEquals(2, $result);
        $this->assertDatabaseHas('projects', ['id' => $this->project->id, 'status' => 'completed']);
        $this->assertDatabaseHas('projects', ['id' => $project2->id, 'status' => 'completed']);
    }

    /** @test */
    public function it_can_bulk_delete_projects()
    {
        $project2 = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->bulkDelete([$this->project->id, $project2->id]);
        
        $this->assertEquals(2, $result);
        $this->assertNull(Project::find($this->project->id));
        $this->assertNull(Project::find($project2->id));
    }

    /** @test */
    public function it_can_get_project_progress()
    {
        $result = $this->projectRepository->getProgress($this->project->id);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_tasks', $result);
        $this->assertArrayHasKey('completed_tasks', $result);
        $this->assertArrayHasKey('progress_percentage', $result);
    }

    /** @test */
    public function it_can_get_project_timeline()
    {
        $result = $this->projectRepository->getTimeline($this->project->id);
        
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
    }

    /** @test */
    public function it_returns_null_for_nonexistent_project()
    {
        $result = $this->projectRepository->getById(99999);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_false_for_invalid_operations()
    {
        $result = $this->projectRepository->delete(99999);
        
        $this->assertFalse($result);
    }
}
