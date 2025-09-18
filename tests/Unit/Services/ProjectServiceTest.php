<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\User;
use Src\CoreProject\Services\ProjectService;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;

/**
 * Unit tests cho ProjectService
 */
class ProjectServiceTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait;
    
    private ProjectService $projectService;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->projectService = app(ProjectService::class);
    }
    
    /**
     * Test create project
     */
    public function test_can_create_project(): void
    {
        $user = $this->createAuthenticatedUser();
        
        $projectData = [
            'name' => 'Test Project',
            'description' => 'Test Description',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'status' => 'planning',
        ];
        
        $project = $this->projectService->createProject($projectData);
        
        $this->assertInstanceOf(Project::class, $project);
        $this->assertEquals('Test Project', $project->name);
        $this->assertEquals($user->tenant_id, $project->tenant_id);
    }
    
    /**
     * Test update project
     */
    public function test_can_update_project(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);
        
        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'active',
        ];
        
        $updatedProject = $this->projectService->updateProject($project->id, $updateData);
        
        $this->assertEquals('Updated Project Name', $updatedProject->name);
        $this->assertEquals('active', $updatedProject->status);
    }
    
    /**
     * Test delete project
     */
    public function test_can_delete_project(): void
    {
        $user = $this->createAuthenticatedUser();
        $project = Project::factory()->create(['tenant_id' => $user->tenant_id]);
        
        $result = $this->projectService->deleteProject($project->id);
        
        $this->assertTrue($result);
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    }
    
    /**
     * Test get projects with filters
     */
    public function test_can_get_projects_with_filters(): void
    {
        $user = $this->createAuthenticatedUser();
        
        // Create test projects
        Project::factory()->count(3)->create([
            'tenant_id' => $user->tenant_id,
            'status' => 'active'
        ]);
        
        Project::factory()->count(2)->create([
            'tenant_id' => $user->tenant_id,
            'status' => 'completed'
        ]);
        
        // Test filter by status
        $activeProjects = $this->projectService->getProjectsList(
            ['status' => 'active']
        );
        
        $this->assertCount(3, $activeProjects);
        
        foreach ($activeProjects as $project) {
            $this->assertEquals('active', $project->status);
        }
    }
}