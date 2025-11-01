<?php

namespace Tests\Feature\Dashboard;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class AppDashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;
    protected $project;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $uniqueId = time() . rand(1000, 9999);
        
        // Create test tenant
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.com',
            'is_active' => true
        ]);
        
        // Create test user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => "test{$uniqueId}@example.com",
            'password' => Hash::make('password'),
            'role' => 'project_manager',
            'tenant_id' => $this->tenant->id
        ]);
        
        // Create test project
        $this->project = Project::create([
            'code' => 'TEST-' . $uniqueId,
            'name' => 'Test Project',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'start_date' => now(),
            'budget_total' => 100000,
            'progress_pct' => 0
        ]);
        
        // Create test tasks - use minimal fields to avoid schema issues
        Task::create([
            'name' => 'Test Task 1',
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);

        Task::create([
            'name' => 'Test Task 2',
            'status' => 'completed',
            'priority' => 'medium',
            'assignee_id' => $this->user->id,
            'project_id' => $this->project->id,
            'tenant_id' => $this->tenant->id
        ]);
        
        // Authenticate user
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function it_can_get_main_dashboard_endpoint()
    {
        $response = $this->getJson('/api/v1/app/dashboard');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'stats',
                        'recent_projects',
                        'recent_tasks',
                        'recent_activity'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_dashboard_stats()
    {
        $response = $this->getJson('/api/v1/app/dashboard/stats');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'projects' => [
                            'total',
                            'active',
                            'completed'
                        ],
                        'tasks' => [
                            'total',
                            'completed',
                            'in_progress',
                            'overdue'
                        ],
                        'users' => [
                            'total',
                            'active'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_recent_projects()
    {
        $response = $this->getJson('/api/v1/app/dashboard/recent-projects?limit=5');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'status',
                            'progress',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_recent_tasks()
    {
        $response = $this->getJson('/api/v1/app/dashboard/recent-tasks?limit=5');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'status',
                            'project_name',
                            'updated_at'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_recent_activity()
    {
        $response = $this->getJson('/api/v1/app/dashboard/recent-activity?limit=10');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'type',
                            'action',
                            'description',
                            'timestamp',
                            'user' => [
                                'id',
                                'name'
                            ]
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_dashboard_metrics()
    {
        $response = $this->getJson('/api/v1/app/dashboard/metrics');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'project_progress',
                        'task_completion'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_team_status()
    {
        $response = $this->getJson('/api/v1/app/dashboard/team-status');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role',
                            'status'
                        ]
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_chart_data_for_project_progress()
    {
        $response = $this->getJson('/api/v1/app/dashboard/charts/project-progress?period=30d');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'labels',
                        'datasets'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_chart_data_for_task_completion()
    {
        $response = $this->getJson('/api/v1/app/dashboard/charts/task-completion?period=30d');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'labels',
                        'datasets'
                    ]
                ]);
    }

    /** @test */
    public function it_rejects_invalid_chart_type()
    {
        $response = $this->getJson('/api/v1/app/dashboard/charts/invalid-type');

        $response->assertStatus(400)
                ->assertJsonStructure([
                    'success',
                    'error'
                ]);
    }

    /** @test */
    public function it_requires_authentication()
    {
        // Clear authentication
        $this->app['auth']->forgetGuards();
        
        $response = $this->getJson('/api/v1/app/dashboard');

        $response->assertStatus(401);
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        // Create another tenant
        $otherTenant = \App\Models\Tenant::create([
            'name' => 'Other Tenant',
            'domain' => 'other.com',
            'is_active' => true
        ]);

        // Create another project for the other tenant
        $otherProject = Project::create([
            'code' => 'OTHER-' . time(),
            'name' => 'Other Project',
            'description' => 'Other project description',
            'status' => 'active',
            'budget_total' => 50000,
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
            'tenant_id' => $otherTenant->id
        ]);

        // Get dashboard stats - should only show projects for current tenant
        $response = $this->getJson('/api/v1/app/dashboard/stats');
        
        $response->assertStatus(200);
        
        // Verify that the other tenant's project is not included
        $data = $response->json('data.projects.total');
        $this->assertEquals(1, $data); // Only our tenant's project
    }
}
