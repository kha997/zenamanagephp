<?php declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

use Tests\TestCase;
use App\Http\Controllers\Api\ProjectManagerController;
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
        
        $this->controller = new ProjectManagerController();
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
        $this->user->shouldReceive('hasRole')->with('project_manager')->andReturn(true);

        $response = $this->controller->getStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('totalProjects', $data['data']);
        $this->assertArrayHasKey('activeProjects', $data['data']);
        $this->assertArrayHasKey('completedProjects', $data['data']);
        $this->assertArrayHasKey('totalTasks', $data['data']);
        $this->assertArrayHasKey('completedTasks', $data['data']);
        $this->assertArrayHasKey('pendingTasks', $data['data']);
        $this->assertArrayHasKey('overdueTasks', $data['data']);
        $this->assertArrayHasKey('financial', $data['data']);
    }

    /**
     * Test getStats method without authentication
     */
    public function test_get_stats_without_authentication()
    {
        Auth::shouldReceive('user')->andReturn(null);

        $response = $this->controller->getStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
    }

    /**
     * Test getStats method with user without project manager role
     */
    public function test_get_stats_without_project_manager_role()
    {
        $user = User::factory()->create(['role' => 'member']);
        
        Auth::shouldReceive('user')->andReturn($user);
        $user->shouldReceive('hasRole')->with('project_manager')->andReturn(false);

        $response = $this->controller->getStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
    }

    /**
     * Test getProjectTimeline method with valid project manager
     */
    public function test_get_project_timeline_with_valid_project_manager()
    {
        // Create additional projects with dates
        Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id,
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(30),
            'status' => 'active',
            'progress' => 50
        ]);

        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasRole')->with('project_manager')->andReturn(true);

        $response = $this->controller->getProjectTimeline();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertIsArray($data['data']);
        
        if (!empty($data['data'])) {
            $project = $data['data'][0];
            $this->assertArrayHasKey('id', $project);
            $this->assertArrayHasKey('name', $project);
            $this->assertArrayHasKey('start_date', $project);
            $this->assertArrayHasKey('end_date', $project);
            $this->assertArrayHasKey('status', $project);
            $this->assertArrayHasKey('progress', $project);
            $this->assertArrayHasKey('duration_days', $project);
        }
    }

    /**
     * Test getProjectTimeline method without authentication
     */
    public function test_get_project_timeline_without_authentication()
    {
        Auth::shouldReceive('user')->andReturn(null);

        $response = $this->controller->getProjectTimeline();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
    }

    /**
     * Test getProjectTimeline method with user without project manager role
     */
    public function test_get_project_timeline_without_project_manager_role()
    {
        $user = User::factory()->create(['role' => 'member']);
        
        Auth::shouldReceive('user')->andReturn($user);
        $user->shouldReceive('hasRole')->with('project_manager')->andReturn(false);

        $response = $this->controller->getProjectTimeline();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(403, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E403.AUTHORIZATION', $data['error']['code']);
    }

    /**
     * Test error handling in getStats method
     */
    public function test_get_stats_error_handling()
    {
        // Mock database error
        Project::shouldReceive('where')->andThrow(new \Exception('Database error'));

        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasRole')->with('project_manager')->andReturn(true);

        $response = $this->controller->getStats();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
    }

    /**
     * Test error handling in getProjectTimeline method
     */
    public function test_get_project_timeline_error_handling()
    {
        // Mock database error
        Project::shouldReceive('where')->andThrow(new \Exception('Database error'));

        Auth::shouldReceive('user')->andReturn($this->user);
        $this->user->shouldReceive('hasRole')->with('project_manager')->andReturn(true);

        $response = $this->controller->getProjectTimeline();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
