<?php declare(strict_types=1);

namespace Tests\E2E;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CriticalUserFlowsE2ETest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;
    protected $project;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'project_manager'
        ]);
        
        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $this->user->id
        ]);
    }

    /**
     * Test complete user authentication flow
     */
    public function test_complete_user_authentication_flow()
    {
        // Test login page accessibility
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Login');
        $response->assertSee('Email');
        $response->assertSee('Password');

        // Test login with valid credentials
        $response = $this->post('/login', [
            'email' => $this->user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/app/dashboard');
        $this->assertAuthenticated();

        // Test dashboard access after login
        $response = $this->get('/app/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');

        // Test logout
        $response = $this->post('/logout');
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    /**
     * Test complete project management flow
     */
    public function test_complete_project_management_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test project listing
        $response = $this->get('/app/projects');
        $response->assertStatus(200);
        $response->assertSee('Projects');

        // Test project creation via API
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Test Project E2E',
            'description' => 'Test project for E2E testing',
            'budget_planned' => 50000,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(3)->format('Y-m-d')
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'description',
                'budget_planned',
                'start_date',
                'end_date'
            ],
            'message'
        ]);

        $projectId = $response->json('data.id');

        // Test project update via API
        $response = $this->putJson("/api/v1/projects/{$projectId}", [
            'name' => 'Updated Test Project E2E',
            'description' => 'Updated test project for E2E testing',
            'budget_planned' => 75000
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'name' => 'Updated Test Project E2E',
                'budget_planned' => 75000
            ]
        ]);

        // Test project deletion via API
        $response = $this->deleteJson("/api/v1/projects/{$projectId}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test complete task management flow
     */
    public function test_complete_task_management_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test task listing
        $response = $this->get('/app/tasks');
        $response->assertStatus(200);
        $response->assertSee('Tasks');

        // Test task creation via API
        $response = $this->postJson('/api/v1/tasks', [
            'title' => 'Test Task E2E',
            'description' => 'Test task for E2E testing',
            'project_id' => $this->project->id,
            'priority' => 'high',
            'due_date' => now()->addDays(7)->format('Y-m-d')
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'title',
                'description',
                'project_id',
                'priority',
                'due_date'
            ],
            'message'
        ]);

        $taskId = $response->json('data.id');

        // Test task update via API
        $response = $this->putJson("/api/v1/tasks/{$taskId}", [
            'title' => 'Updated Test Task E2E',
            'description' => 'Updated test task for E2E testing',
            'priority' => 'medium',
            'status' => 'in_progress'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'title' => 'Updated Test Task E2E',
                'priority' => 'medium',
                'status' => 'in_progress'
            ]
        ]);

        // Test task completion via API
        $response = $this->putJson("/api/v1/tasks/{$taskId}", [
            'status' => 'completed'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'status' => 'completed'
            ]
        ]);

        // Test task deletion via API
        $response = $this->deleteJson("/api/v1/tasks/{$taskId}");
        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /**
     * Test complete dashboard flow
     */
    public function test_complete_dashboard_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test dashboard access
        $response = $this->get('/app/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');

        // Test dashboard API data
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'totalProjects',
                'activeProjects',
                'completedProjects',
                'totalTasks',
                'completedTasks',
                'pendingTasks',
                'overdueTasks',
                'financial'
            ],
            'message'
        ]);

        // Test dashboard timeline
        $response = $this->getJson('/api/v1/project-manager/dashboard/timeline');
        $response->assertStatus(200);
        $response->assertJsonStructure([
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
    }

    /**
     * Test complete error handling flow
     */
    public function test_complete_error_handling_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test 404 error
        $response = $this->getJson('/api/v1/nonexistent-endpoint');
        $response->assertStatus(404);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);

        // Test validation error
        $response = $this->postJson('/api/v1/projects', [
            'name' => '', // Invalid empty name
            'description' => 'Test project'
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);

        // Test authorization error
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        $this->actingAs($member);
        $this->apiAs($member, $this->tenant);

        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $response->assertStatus(403);
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details'
            ]
        ]);
    }

    /**
     * Test complete multi-tenant isolation flow
     */
    public function test_complete_multi_tenant_isolation_flow()
    {
        // Create another tenant and user
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'project_manager'
        ]);

        // Create project for other tenant
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenant->id,
            'pm_id' => $otherUser->id
        ]);

        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test that user cannot access other tenant's data
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(1, $data['totalProjects']); // Should only see own tenant's projects

        // Test that user cannot access other tenant's project
        $response = $this->getJson("/api/v1/projects/{$otherProject->id}");
        $response->assertStatus(404); // Should not find other tenant's project
    }

    /**
     * Test complete API rate limiting flow
     */
    public function test_complete_api_rate_limiting_flow()
    {
        // Test rate limiting on authentication endpoints
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/auth/login', [
                'email' => 'test@example.com',
                'password' => 'password'
            ]);
            
            if ($i < 5) {
                $response->assertStatus(401); // Authentication failed
            } else {
                $response->assertStatus(429); // Rate limited
                break;
            }
        }
    }

    /**
     * Test complete performance flow
     */
    public function test_complete_performance_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test dashboard performance
        $startTime = microtime(true);
        $response = $this->getJson('/api/v1/project-manager/dashboard/stats');
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(300, $executionTime, 'Dashboard stats should complete within 300ms');

        // Test page performance
        $startTime = microtime(true);
        $response = $this->get('/app/dashboard');
        $endTime = microtime(true);
        
        $executionTime = ($endTime - $startTime) * 1000;
        
        $response->assertStatus(200);
        $this->assertLessThan(500, $executionTime, 'Dashboard page should complete within 500ms');
    }

    /**
     * Test complete accessibility flow
     */
    public function test_complete_accessibility_flow()
    {
        $this->actingAs($this->user);
        $this->apiAs($this->user, $this->tenant);

        // Test dashboard accessibility
        $response = $this->get('/app/dashboard');
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Dashboard should have h1 heading');
        
        // Test for proper navigation
        $this->assertStringContainsString('nav', $content, 'Dashboard should have navigation');
        
        // Test for proper form labels
        $this->assertStringNotContainsString('<input', $content, 'Dashboard should not have unlabeled inputs');

        // Test projects page accessibility
        $response = $this->get('/app/projects');
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        // Test for proper heading structure
        $this->assertStringContainsString('<h1', $content, 'Projects page should have h1 heading');
        
        // Test for proper table structure
        $this->assertStringContainsString('<table', $content, 'Projects page should have proper table structure');
    }
}
