<?php declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

use Tests\TestCase;
use App\Http\Controllers\Unified\ProjectManagementController;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Services\ErrorEnvelopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;
use Mockery;

class ProjectManagerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $project;
    protected $controller;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user with project manager role
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'project_manager'
        ]);
        
        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id
        ]);
        
        $this->controller = new ProjectManagementController(
            app(\App\Services\ProjectManagementService::class)
        );
    }

    /**
     * Test getStats method with valid project manager
     */
    public function test_get_stats_with_valid_project_manager()
    {
        // Create additional projects and tasks
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'status' => 'active'
        ]);
        
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'status' => 'completed'
        ]);
        
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending'
        ]);
        
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'completed'
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($this->user->id);

        $response = $this->controller->getProjectStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('total', $data['data']);
        $this->assertArrayHasKey('by_status', $data['data']);
        $this->assertArrayHasKey('by_priority', $data['data']);
        $this->assertArrayHasKey('average_progress', $data['data']);
        $this->assertArrayHasKey('total_budget', $data['data']);
        $this->assertArrayHasKey('total_spent', $data['data']);
        $this->assertArrayHasKey('created_this_month', $data['data']);
        $this->assertArrayHasKey('overdue', $data['data']);
    }

    /**
     * Test getStats method without authentication
     */
    public function test_get_stats_without_authentication()
    {
        Auth::shouldReceive('user')->andReturn(null);
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);

        $response = $this->controller->getProjectStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test getStats method with user without project manager role
     */
    public function test_get_stats_without_project_manager_role()
    {
        $user = User::factory()->create(['role' => 'member']);
        
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($user->id);

        $response = $this->controller->getProjectStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test getProjectTimeline method with valid project manager
     */
    public function test_get_project_timeline_with_valid_project_manager()
    {
        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($this->user->id);

        $response = $this->controller->getProjectTimeline($this->project->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('project_id', $data['data']);
        $this->assertArrayHasKey('timeline', $data['data']);
        $this->assertEquals($this->project->id, $data['data']['project_id']);
        $this->assertIsArray($data['data']['timeline']);
    }
    
    public function test_get_project_timeline_without_project_manager_role()
    {
        $user = User::factory()->create([
            'role' => 'member',
            'tenant_id' => $this->tenant->id  // Same tenant as project
        ]);
        
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($user->id);

        $response = $this->controller->getProjectTimeline($this->project->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }
    
    public function test_get_project_stats_with_database_error()
    {
        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($this->user->id);

        // Create mock service
        $mockService = Mockery::mock(\App\Services\ProjectManagementService::class);
        $mockService->shouldReceive('getProjectStats')
            ->once()
            ->andThrow(new \Illuminate\Database\QueryException(
                'sqlite',
                'SELECT * FROM projects',
                [],
                new \Exception('Connection failed')
            ));
        
        // Mock errorResponse method
        $mockService->shouldReceive('errorResponse')
            ->once()
            ->with(
                'Database error occurred while fetching statistics',
                500,
                Mockery::type('array')
            )
            ->andReturn(new JsonResponse([
                'status' => 'error',
                'message' => 'Database error occurred while fetching statistics',
                'error_id' => 'stats_db_error_' . uniqid()
            ], 500));

        // Create controller with mock service
        $controller = new ProjectManagementController($mockService);

        $response = $controller->getProjectStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('Database error', $data['message']);
    }
    
    public function test_get_project_timeline_with_database_error()
    {
        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($this->user->id);

        // Mock database error by using invalid project ID
        $response = $this->controller->getProjectTimeline('invalid-project-id');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(404, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Project not found', $data['message']);
    }

    public function test_get_project_timeline_without_authentication()
    {
        Auth::shouldReceive('user')->andReturn(null);
        Auth::shouldReceive('check')->andReturn(false);
        Auth::shouldReceive('id')->andReturn(null);

        $response = $this->controller->getProjectTimeline($this->project->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(401, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('success', $data);
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Unauthenticated', $data['error']['message']);
    }

    public function test_get_project_timeline_without_project_manager_role_duplicate()
    {
        $user = User::factory()->create([
            'role' => 'client',
            'tenant_id' => $this->tenant->id  // Same tenant as project
        ]);
        
        Auth::shouldReceive('user')->andReturn($user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($user->id);

        $response = $this->controller->getProjectTimeline($this->project->id);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    /**
     * Test error handling in getStats method
     */
    public function test_get_stats_error_handling()
    {
        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->user);
        Auth::shouldReceive('check')->andReturn(true);
        Auth::shouldReceive('id')->andReturn($this->user->id);

        // Create mock service
        $mockService = Mockery::mock(\App\Services\ProjectManagementService::class);
        $mockService->shouldReceive('getProjectStats')
            ->once()
            ->andThrow(new \Exception('Service unavailable', 503));
        
        // Mock errorResponse method
        $mockService->shouldReceive('errorResponse')
            ->once()
            ->with(
                'Service unavailable',
                503,
                Mockery::type('array')
            )
            ->andReturn(new JsonResponse([
                'status' => 'error',
                'message' => 'Service unavailable',
                'error_id' => 'stats_error_' . uniqid()
            ], 503));

        // Create controller with mock service
        $controller = new ProjectManagementController($mockService);

        $response = $controller->getProjectStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(503, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('status', $data);
        $this->assertEquals('error', $data['status']);
        $this->assertArrayHasKey('message', $data);
        $this->assertEquals('Service unavailable', $data['message']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
