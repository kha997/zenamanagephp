<?php declare(strict_types=1);

namespace Tests\Unit\Controllers\Api;

use Tests\TestCase;
use App\Http\Controllers\Api\ProjectManagerController;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        Auth::logout();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create user with project manager role
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assignRole($this->user, 'project_manager');
        Auth::loginUsingId($this->user->id);

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

        Auth::loginUsingId($this->user->id);

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
        Auth::logout();

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
        Auth::logout();

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assignRole($user, 'staff');
        Auth::loginUsingId($user->id);

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

        Auth::loginUsingId($this->user->id);

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
        Auth::logout();

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
        Auth::logout();

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assignRole($user, 'staff');
        Auth::loginUsingId($user->id);

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
        Auth::loginUsingId($this->user->id);

        $originalDatabase = config('database.connections.mysql.database');
        $temporaryName = $originalDatabase . '_missing';
        $originalDefault = config('database.default');
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => $temporaryName]);
        DB::purge('mysql');

        try {
            $response = $this->controller->getStats();
        } finally {
            config(['database.connections.mysql.database' => $originalDatabase]);
            config(['database.default' => $originalDefault]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            if ($originalDefault !== 'mysql') {
                DB::purge($originalDefault);
                DB::reconnect($originalDefault);
            }
        }

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
        Auth::loginUsingId($this->user->id);

        $originalDatabase = config('database.connections.mysql.database');
        $temporaryName = $originalDatabase . '_missing';
        $originalDefault = config('database.default');
        config(['database.default' => 'mysql']);
        config(['database.connections.mysql.database' => $temporaryName]);
        DB::purge('mysql');

        try {
            $response = $this->controller->getProjectTimeline();
        } finally {
            config(['database.connections.mysql.database' => $originalDatabase]);
            config(['database.default' => $originalDefault]);
            DB::purge('mysql');
            DB::reconnect('mysql');
            if ($originalDefault !== 'mysql') {
                DB::purge($originalDefault);
                DB::reconnect($originalDefault);
            }
        }

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(500, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('E500.SERVER_ERROR', $data['error']['code']);
    }

    protected function assignRole(User $user, string $roleName, string $scope = Role::SCOPE_SYSTEM): Role
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            [
                'scope' => $scope,
                'allow_override' => false,
                'is_active' => true
            ]
        );
        $user->roles()->syncWithoutDetaching($role->id);
        return $role;
    }

}
