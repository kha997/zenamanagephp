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
        
        // Create test tenant first
        $this->tenant = Tenant::factory()->create();
        
        // Create test user with same tenant
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test-' . uniqid() . '@example.com' // Ensure unique email
        ]);
        
        // Create test project with same tenant
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'owner_id' => $this->user->id,
            'code' => 'PRJ-TEST-' . uniqid(), // Ensure unique code
            'status' => 'active' // Ensure active status for tests
        ]);
    }

    /** @test */
    public function it_can_get_all_projects_with_pagination()
    {
        // Create additional projects
        Project::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);
        
        $result = $this->projectRepository->getAll(['tenant_id' => $this->tenant->id], 10);
        
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
        
        $result = $this->projectRepository->getAll(['status' => 'active', 'tenant_id' => $this->tenant->id], 10);
        
        $this->assertGreaterThanOrEqual(1, $result->total()); // At least 1 project with 'active' status
    }

    /** @test */
    public function it_can_filter_projects_by_manager_id()
    {
        $result = $this->projectRepository->getAll(['manager_id' => $this->user->id, 'tenant_id' => $this->tenant->id], 10);
        
        $this->assertEquals(1, $result->total());
    }

    /** @test */
    public function it_can_search_projects()
    {
        $projects = $this->projectRepository->search('Test', $this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $projects);
        $this->assertGreaterThan(0, $projects->count());
        $this->assertTrue($projects->contains('id', $this->project->id));
    }

    /** @test */
    public function it_can_get_project_by_id()
    {
        $foundProject = $this->projectRepository->getById($this->project->id, $this->tenant->id);
        
        $this->assertInstanceOf(Project::class, $foundProject);
        $this->assertEquals($this->project->id, $foundProject->id);
        $this->assertEquals($this->project->name, $foundProject->name);
    }

    /** @test */
    public function it_can_get_projects_by_tenant_id()
    {
        $result = $this->projectRepository->getAll(['tenant_id' => $this->tenant->id], 10);
        
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
    }

    /** @test */
    public function it_can_get_projects_by_manager_id()
    {
        $result = $this->projectRepository->getAll(['manager_id' => $this->user->id, 'tenant_id' => $this->tenant->id], 10);
        
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
    }

    /** @test */
    public function it_can_get_projects_by_status()
    {
        $result = $this->projectRepository->getAll(['status' => $this->project->status, 'tenant_id' => $this->tenant->id], 10);
        
        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
        $this->assertGreaterThan(0, $result->total());
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
            'manager_id' => $this->user->id,
            'code' => 'PRJ-TEST-001'
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
        
        $result = $this->projectRepository->update($this->project->id, $updateData, $this->tenant->id);
        
        $this->assertInstanceOf(Project::class, $result);
        $this->assertEquals('Updated Project', $result->name);
        $this->assertEquals('Updated Description', $result->description);
    }

    /** @test */
    public function it_can_delete_project()
    {
        $result = $this->projectRepository->delete($this->project->id, $this->tenant->id);
        
        $this->assertTrue($result);
        $this->assertNull(Project::find($this->project->id));
    }

    /** @test */
    public function it_can_soft_delete_project()
    {
        $result = $this->projectRepository->softDelete($this->project->id, $this->tenant->id);
        
        $this->assertTrue($result);
        
        // Verify project is soft deleted
        $deletedProject = Project::withTrashed()->find($this->project->id);
        $this->assertNotNull($deletedProject->deleted_at);
    }

    /** @test */
    public function it_can_restore_soft_deleted_project()
    {
        // First soft delete the project
        $this->projectRepository->softDelete($this->project->id, $this->tenant->id);
        
        // Then restore it
        $result = $this->projectRepository->restore($this->project->id, $this->tenant->id);
        
        $this->assertTrue($result);
        
        // Verify project is restored
        $restoredProject = Project::find($this->project->id);
        $this->assertNotNull($restoredProject);
        $this->assertNull($restoredProject->deleted_at);
    }

    /** @test */
    public function it_can_get_active_projects()
    {
        $activeProjects = $this->projectRepository->getActive($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $activeProjects);
        $this->assertGreaterThan(0, $activeProjects->count());
    }

    /** @test */
    public function it_can_get_completed_projects()
    {
        // Create a completed project
        $completedProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'completed',
            'code' => 'PRJ-COMPLETED-' . uniqid()
        ]);
        
        $completedProjects = $this->projectRepository->getCompleted($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $completedProjects);
        $this->assertGreaterThan(0, $completedProjects->count());
        $this->assertTrue($completedProjects->contains('id', $completedProject->id));
    }

    /** @test */
    public function it_can_get_overdue_projects()
    {
        // Create an overdue project
        $overdueProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'end_date' => now()->subDays(5), // 5 days ago
            'code' => 'PRJ-OVERDUE-' . uniqid()
        ]);
        
        $overdueProjects = $this->projectRepository->getOverdue($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $overdueProjects);
        $this->assertGreaterThan(0, $overdueProjects->count());
        $this->assertTrue($overdueProjects->contains('id', $overdueProject->id));
    }

    /** @test */
    public function it_can_get_projects_starting_soon()
    {
        // Create a project starting soon
        $startingSoonProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'planning',
            'start_date' => now()->addDays(3), // 3 days from now
            'code' => 'PRJ-STARTING-' . uniqid()
        ]);
        
        $startingSoonProjects = $this->projectRepository->getStartingSoon($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $startingSoonProjects);
        $this->assertGreaterThan(0, $startingSoonProjects->count());
        $this->assertTrue($startingSoonProjects->contains('id', $startingSoonProject->id));
    }

    /** @test */
    public function it_can_get_projects_ending_soon()
    {
        // Create a project ending soon
        $endingSoonProject = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'end_date' => now()->addDays(3), // 3 days from now
            'code' => 'PRJ-ENDING-' . uniqid()
        ]);
        
        $endingSoonProjects = $this->projectRepository->getEndingSoon($this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $endingSoonProjects);
        $this->assertGreaterThan(0, $endingSoonProjects->count());
        $this->assertTrue($endingSoonProjects->contains('id', $endingSoonProject->id));
    }

    /** @test */
    public function it_can_update_project_status()
    {
        $updatedProject = $this->projectRepository->updateStatus($this->project->id, 'completed', $this->tenant->id);
        
        $this->assertInstanceOf(Project::class, $updatedProject);
        $this->assertEquals('completed', $updatedProject->status);
        
        // Verify in database
        $projectInDb = Project::find($this->project->id);
        $this->assertEquals('completed', $projectInDb->status);
    }

    /** @test */
    public function it_can_assign_team_to_project()
    {
        // Create a team
        $team = Team::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Team'
        ]);
        
        $result = $this->projectRepository->assignTeam($this->project->id, $team->id, $this->tenant->id);
        
        $this->assertTrue($result);
        
        // Verify team is assigned
        $project = Project::find($this->project->id);
        $this->assertTrue($project->teams->contains('id', $team->id));
    }

    /** @test */
    public function it_can_remove_team_from_project()
    {
        // Create a team and assign it first
        $team = Team::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Team'
        ]);
        
        $this->projectRepository->assignTeam($this->project->id, $team->id, $this->tenant->id);
        
        // Now remove the team
        $result = $this->projectRepository->removeTeam($this->project->id, $this->tenant->id);
        
        $this->assertTrue($result);
        
        // Verify team is removed
        $project = Project::find($this->project->id);
        $this->assertFalse($project->teams->contains('id', $team->id));
    }

    /** @test */
    public function it_can_get_project_statistics()
    {
        $stats = $this->projectRepository->getStatistics($this->tenant->id);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('by_status', $stats);
        $this->assertArrayHasKey('by_priority', $stats);
        $this->assertArrayHasKey('average_progress', $stats);
        $this->assertArrayHasKey('total_budget', $stats);
        $this->assertArrayHasKey('total_spent', $stats);
        $this->assertArrayHasKey('created_this_month', $stats);
        $this->assertArrayHasKey('overdue', $stats);
        $this->assertGreaterThan(0, $stats['total']);
    }

    /** @test */
    public function it_can_get_projects_by_multiple_ids()
    {
        // Create additional projects
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST2-' . uniqid()
        ]);
        
        $projectIds = [$this->project->id, $project2->id];
        $projects = $this->projectRepository->getByIds($projectIds, $this->tenant->id);
        
        $this->assertInstanceOf(Collection::class, $projects);
        $this->assertEquals(2, $projects->count());
        $this->assertTrue($projects->contains('id', $this->project->id));
        $this->assertTrue($projects->contains('id', $project2->id));
    }

    /** @test */
    public function it_can_bulk_update_projects()
    {
        // Create additional project
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST2-' . uniqid()
        ]);
        
        $projectIds = [$this->project->id, $project2->id];
        $updateData = ['status' => 'completed', 'priority' => 'high'];
        
        $updatedCount = $this->projectRepository->bulkUpdate($projectIds, $updateData, $this->tenant->id);
        
        $this->assertEquals(2, $updatedCount);
        
        // Verify updates
        $updatedProjects = Project::whereIn('id', $projectIds)->get();
        foreach ($updatedProjects as $project) {
            $this->assertEquals('completed', $project->status);
            $this->assertEquals('high', $project->priority);
        }
    }

    /** @test */
    public function it_can_bulk_delete_projects()
    {
        // Create additional project
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'PRJ-TEST2-' . uniqid()
        ]);
        
        $projectIds = [$this->project->id, $project2->id];
        
        $deletedCount = $this->projectRepository->bulkDelete($projectIds, $this->tenant->id);
        
        $this->assertEquals(2, $deletedCount);
        
        // Verify deletions
        $deletedProjects = Project::whereIn('id', $projectIds)->get();
        $this->assertEquals(0, $deletedProjects->count());
    }

    /** @test */
    public function it_can_get_project_progress()
    {
        $progress = $this->projectRepository->getProgress($this->project->id, $this->tenant->id);
        
        $this->assertIsArray($progress);
        $this->assertArrayHasKey('project_id', $progress);
        $this->assertArrayHasKey('progress_pct', $progress);
        $this->assertArrayHasKey('completion_percentage', $progress);
        $this->assertArrayHasKey('budget_spent', $progress);
        $this->assertArrayHasKey('budget_total', $progress);
        $this->assertArrayHasKey('budget_variance', $progress);
        $this->assertArrayHasKey('hours_estimated', $progress);
        $this->assertArrayHasKey('hours_actual', $progress);
        $this->assertArrayHasKey('status', $progress);
        $this->assertArrayHasKey('last_activity_at', $progress);
        $this->assertEquals($this->project->id, $progress['project_id']);
    }

    /** @test */
    public function it_can_get_project_timeline()
    {
        $timeline = $this->projectRepository->getTimeline($this->project->id, $this->tenant->id);
        
        $this->assertIsArray($timeline);
        $this->assertArrayHasKey('project_id', $timeline);
        $this->assertArrayHasKey('project_name', $timeline);
        $this->assertArrayHasKey('timeline', $timeline);
        $this->assertIsArray($timeline['timeline']);
        $this->assertEquals($this->project->id, $timeline['project_id']);
        $this->assertEquals($this->project->name, $timeline['project_name']);
        
        // Should have at least project creation event
        $this->assertGreaterThan(0, count($timeline['timeline']));
    }

    /** @test */
    public function it_returns_null_for_nonexistent_project()
    {
        $nonexistentId = '01HXYZ123456789ABCDEFGHIJK'; // Valid ULID format but doesn't exist
        $result = $this->projectRepository->getById($nonexistentId, $this->tenant->id);
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_returns_false_for_invalid_operations()
    {
        $nonexistentId = '01HXYZ123456789ABCDEFGHIJK'; // Valid ULID format but doesn't exist
        
        // Test that delete throws exception for nonexistent project
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $this->projectRepository->delete($nonexistentId, $this->tenant->id);
    }
}
