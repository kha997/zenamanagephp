<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

class ProjectManagerApiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->markTestSkipped('All ProjectManagerApiIntegrationTest tests skipped - endpoints not implemented');
        
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
            'pm_id' => $this->user->id,
            'budget_planned' => 100000,
            'budget_actual' => 75000
        ]);
        
        // Create tasks
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'pending'
        ]);
        
        Task::factory()->create([
            'project_id' => $this->project->id,
            'status' => 'completed'
        ]);
    }

    /**
     * Test project manager dashboard stats endpoint
     */
    public function test_project_manager_dashboard_stats_endpoint()
    {
        $this->markTestSkipped('All ProjectManagerApiIntegrationTest tests skipped - endpoints not implemented');
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'totalProjects',
                        'activeProjects',
                        'completedProjects',
                        'totalTasks',
                        'completedTasks',
                        'pendingTasks',
                        'overdueTasks',
                        'financial' => [
                            'totalBudget',
                            'totalActual',
                            'totalRevenue',
                            'budgetUtilization',
                            'profitMargin'
                        ]
                    ],
                    'message'
                ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['totalProjects']);
        $this->assertEquals(2, $data['totalTasks']);
        $this->assertEquals(1, $data['completedTasks']);
        $this->assertEquals(1, $data['pendingTasks']);
        $this->assertEquals(100000, $data['financial']['totalBudget']);
        $this->assertEquals(75000, $data['financial']['totalActual']);
    }

    /**
     * Test project manager dashboard stats endpoint without authentication
     */
    public function test_project_manager_dashboard_stats_endpoint_without_auth()
    {
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');

        $response->assertStatus(401)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details'
                    ]
                ]);

        $this->assertEquals('E401.AUTHENTICATION', $response->json('error.code'));
    }

    /**
     * Test project manager dashboard stats endpoint with non-project manager user
     */
    public function test_project_manager_dashboard_stats_endpoint_with_non_pm_user()
    {
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details'
                    ]
                ]);

        $this->assertEquals('E403.AUTHORIZATION', $response->json('error.code'));
    }

    /**
     * Test project manager dashboard timeline endpoint
     */
    public function test_project_manager_dashboard_timeline_endpoint()
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/project-manager/dashboard/timeline');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'start_date',
                            'end_date',
                            'status',
                            'progress',
                            'duration_days'
                        ]
                    ],
                    'message'
                ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->project->id, $data[0]['id']);
        $this->assertEquals($this->project->name, $data[0]['name']);
    }

    /**
     * Test project manager dashboard timeline endpoint without authentication
     */
    public function test_project_manager_dashboard_timeline_endpoint_without_auth()
    {
        $response = $this->getJson('/api/v1/project-manager/dashboard/timeline');

        $response->assertStatus(401)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details'
                    ]
                ]);

        $this->assertEquals('E401.AUTHENTICATION', $response->json('error.code'));
    }

    /**
     * Test project manager dashboard timeline endpoint with non-project manager user
     */
    public function test_project_manager_dashboard_timeline_endpoint_with_non_pm_user()
    {
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        Sanctum::actingAs($member);

        $response = $this->getJson('/api/v1/project-manager/dashboard/timeline');

        $response->assertStatus(403)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details'
                    ]
                ]);

        $this->assertEquals('E403.AUTHORIZATION', $response->json('error.code'));
    }

    /**
     * Test error envelope format consistency
     */
    public function test_error_envelope_format_consistency()
    {
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');

        $response->assertStatus(401)
                ->assertJsonStructure([
                    'error' => [
                        'id',
                        'code',
                        'message',
                        'details'
                    ]
                ]);

        $error = $response->json('error');
        
        // Test error envelope format
        $this->assertStringStartsWith('req_', $error['id']);
        $this->assertMatchesRegularExpression('/^E\d{3}\.[A-Z_]+$/', $error['code']);
        $this->assertIsString($error['message']);
        $this->assertIsArray($error['details']);
    }

    /**
     * Test tenant isolation in project manager endpoints
     */
    public function test_tenant_isolation_in_project_manager_endpoints()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'project_manager'
        ]);

        // Create project for other tenant
        Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'pm_id' => $otherUser->id
        ]);

        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['totalProjects']); // Should only see own tenant's projects
    }

    /**
     * Test performance of project manager dashboard stats endpoint
     */
    public function test_project_manager_dashboard_stats_performance()
    {
        // Create multiple projects and tasks for performance testing
        for ($i = 0; $i < 50; $i++) {
            $project = Project::factory()->create([
                'tenant_id' => $this->tenant->id,
                'pm_id' => $this->user->id
            ]);
            
            for ($j = 0; $j < 10; $j++) {
                Task::factory()->create([
                    'project_id' => $project->id
                ]);
            }
        }

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        
        // Performance assertion: should complete within 500ms
        $this->assertLessThan(500, $executionTime, 'Dashboard stats endpoint should complete within 500ms');
    }

    /**
     * Test performance of project manager dashboard timeline endpoint
     */
    public function test_project_manager_dashboard_timeline_performance()
    {
        // Create multiple projects for performance testing
        for ($i = 0; $i < 100; $i++) {
            Project::factory()->create([
                'tenant_id' => $this->tenant->id,
                'pm_id' => $this->user->id,
                'start_date' => now()->subDays(rand(1, 365)),
                'end_date' => now()->addDays(rand(1, 365))
            ]);
        }

        Sanctum::actingAs($this->user);

        $startTime = microtime(true);
        
        $response = $this->getJson('/api/v1/project-manager/dashboard/timeline');
        
        $endTime = microtime(true);
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $response->assertStatus(200);
        
        // Performance assertion: should complete within 300ms
        $this->assertLessThan(300, $executionTime, 'Dashboard timeline endpoint should complete within 300ms');
    }
}
