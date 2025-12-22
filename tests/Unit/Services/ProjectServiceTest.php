<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
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
        
        // Mock dependencies
        $projectRepository = $this->createMock(\App\Repositories\ProjectRepository::class);
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $metricsService = $this->createMock(\App\Services\MetricsService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        
        $this->projectService = new ProjectService($projectRepository, $auditService, $metricsService, $permissionService);
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
        
        // Mock project creation
        $mockProject = new Project([
            'id' => 1,
            'name' => 'Test Project',
            'description' => 'Test Description',
            'code' => 'PRJ-ABC-20250109-1234',
            'status' => 'planning',
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id
        ]);
        
        // Configure mocks
        $projectRepository = $this->createMock(\App\Repositories\ProjectRepository::class);
        $projectRepository->method('create')->willReturn($mockProject);
        
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->method('log')->willReturn(true);
        
        $metricsService = $this->createMock(\App\Services\MetricsService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        
        $this->projectService = new ProjectService($projectRepository, $auditService, $metricsService, $permissionService);
        
        $project = $this->projectService->createProject($projectData, (string)$user->id, (string)$user->tenant_id);
        
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
        
        // Ensure project has correct tenant_id
        $project->tenant_id = $user->tenant_id;
        $project->save(); // Save to database
        
        // Debug: Check tenant_id values
        $this->assertEquals($user->tenant_id, $project->tenant_id, 'Project tenant_id should match user tenant_id');
        
        $updateData = [
            'name' => 'Updated Project Name',
            'status' => 'active',
        ];
        
        // Mock project update
        $mockUpdatedProject = new Project([
            'id' => $project->id,
            'name' => 'Updated Project Name',
            'status' => 'active',
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id
        ]);
        
        // Configure mocks
        $projectRepository = $this->createMock(\App\Repositories\ProjectRepository::class);
        $projectRepository->method('findById')->willReturn($project);
        $projectRepository->method('update')->willReturn($mockUpdatedProject);
        
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->method('log')->willReturn(true);
        
        $metricsService = $this->createMock(\App\Services\MetricsService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        
        $this->projectService = new ProjectService($projectRepository, $auditService, $metricsService, $permissionService);
        
        $updatedProject = $this->projectService->updateProject($project->id, $updateData, (string)$user->id, (string)$user->tenant_id);
        
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
        
        // Ensure project has correct tenant_id
        $project->tenant_id = $user->tenant_id;
        
        // Configure mocks
        $projectRepository = $this->createMock(\App\Repositories\ProjectRepository::class);
        $projectRepository->method('findById')->willReturn($project);
        $projectRepository->method('delete')->willReturn(true);
        
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $auditService->method('log')->willReturn(true);
        
        $metricsService = $this->createMock(\App\Services\MetricsService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        
        $this->projectService = new ProjectService($projectRepository, $auditService, $metricsService, $permissionService);
        
        $result = $this->projectService->deleteProject($project->id, (string)$user->id, (string)$user->tenant_id);
        
        $this->assertTrue($result);
    }
    
    /**
     * Test get projects with filters
     */
    public function test_can_get_projects_with_filters(): void
    {
        $user = $this->createAuthenticatedUser();
        
        // Mock projects collection
        $mockProjects = new \Illuminate\Database\Eloquent\Collection([
            new Project(['id' => 1, 'name' => 'Project 1', 'status' => 'active', 'tenant_id' => $user->tenant_id]),
            new Project(['id' => 2, 'name' => 'Project 2', 'status' => 'active', 'tenant_id' => $user->tenant_id]),
            new Project(['id' => 3, 'name' => 'Project 3', 'status' => 'active', 'tenant_id' => $user->tenant_id]),
        ]);
        
        // Configure mocks
        $projectRepository = $this->createMock(\App\Repositories\ProjectRepository::class);
        $projectRepository->method('getList')->willReturn($mockProjects);
        
        $auditService = $this->createMock(\App\Services\AuditService::class);
        $metricsService = $this->createMock(\App\Services\MetricsService::class);
        $permissionService = $this->createMock(\App\Services\PermissionService::class);
        $permissionService->method('canUserCreateProjects')->willReturn(true);
        $permissionService->method('canUserModifyProject')->willReturn(true);
        $permissionService->method('canUserDeleteProject')->willReturn(true);
        
        $this->projectService = new ProjectService($projectRepository, $auditService, $metricsService, $permissionService);
        
        $activeProjects = $this->projectService->getProjectsList(
            ['status' => 'active'], (string)$user->id, (string)$user->tenant_id
        );
        
        $this->assertCount(3, $activeProjects);
        
        foreach ($activeProjects as $project) {
            $this->assertEquals('active', $project->status);
        }
    }
}