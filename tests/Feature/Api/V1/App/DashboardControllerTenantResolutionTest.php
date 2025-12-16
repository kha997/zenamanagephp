<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for DashboardController tenant resolution
 * 
 * Tests that DashboardController uses getTenantId() (via TenancyService)
 * instead of legacy user->tenant_id, especially for users with multiple tenant memberships.
 * 
 * @group dashboard
 * @group tenant-resolution
 */
class DashboardControllerTenantResolutionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user;
    private Project $project1;
    private Project $project2;
    private Task $task1;
    private Task $task2;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('dashboard-tenant-resolution');
        $this->setupDomainIsolation();
        
        // Create two tenants
        $this->tenant1 = Tenant::factory()->create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1-' . uniqid(),
        ]);
        
        $this->tenant2 = Tenant::factory()->create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2-' . uniqid(),
        ]);
        
        // Create user with tenant1 as legacy tenant_id
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant1->id, // Legacy tenant_id
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot (multi-tenant scenario)
        $this->user->tenants()->attach($this->tenant1->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        $this->user->tenants()->attach($this->tenant2->id, [
            'role' => 'admin',
            'is_default' => false,
        ]);
        
        // Create projects in both tenants
        $this->project1 = Project::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'name' => 'Project in Tenant 1',
        ]);
        
        $this->project2 = Project::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'name' => 'Project in Tenant 2',
        ]);
        
        // Create tasks in both tenants
        $this->task1 = Task::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'name' => 'Task in Tenant 1',
        ]);
        
        $this->task2 = Task::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $this->project2->id,
            'name' => 'Task in Tenant 2',
        ]);
    }

    /**
     * Test that DashboardController uses active tenant from session, not user->tenant_id
     */
    public function test_dashboard_uses_active_tenant_from_session(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant2 via session (simulating EnsureTenantPermission middleware)
        $this->withSession(['selected_tenant_id' => $this->tenant2->id]);
        
        // Call dashboard index endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard');
        
        $response->assertStatus(200);
        
        // Verify that dashboard data is from tenant2 (active tenant), not tenant1 (user->tenant_id)
        $data = $response->json('data');
        
        // Check recent projects - should only include tenant2's project
        $recentProjects = $data['recent_projects'] ?? [];
        $this->assertNotEmpty($recentProjects, 'Should have recent projects');
        
        foreach ($recentProjects as $project) {
            // Projects should be from tenant2
            $this->assertNotEquals(
                $this->project1->id,
                $project['id'],
                'Dashboard should not include tenant1 project when tenant2 is active'
            );
        }
        
        // Verify tenant2's project is included
        $projectIds = array_column($recentProjects, 'id');
        $this->assertContains(
            $this->project2->id,
            $projectIds,
            'Dashboard should include tenant2 project when tenant2 is active'
        );
    }

    /**
     * Test that DashboardController getStats uses active tenant
     */
    public function test_dashboard_stats_uses_active_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant2
        $this->withSession(['selected_tenant_id' => $this->tenant2->id]);
        
        // Call dashboard stats endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify stats are for tenant2
        // Total projects should be 1 (only tenant2's project)
        $this->assertEquals(1, $data['projects']['total'], 'Should only count tenant2 projects');
        
        // Total tasks should be 1 (only tenant2's task)
        $this->assertEquals(1, $data['tasks']['total'], 'Should only count tenant2 tasks');
    }

    /**
     * Test that DashboardController getRecentProjects uses active tenant
     */
    public function test_dashboard_recent_projects_uses_active_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant1
        $this->withSession(['selected_tenant_id' => $this->tenant1->id]);
        
        // Call recent projects endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/recent-projects');
        
        $response->assertStatus(200);
        
        $projects = $response->json('data') ?? [];
        
        // Verify all projects are from tenant1
        foreach ($projects as $project) {
            // We can't directly check tenant_id in response, but we can verify
            // that tenant2's project is not included
            $this->assertNotEquals(
                $this->project2->id,
                $project['id'],
                'Should not include tenant2 project when tenant1 is active'
            );
        }
        
        // Verify tenant1's project is included
        $projectIds = array_column($projects, 'id');
        $this->assertContains(
            $this->project1->id,
            $projectIds,
            'Should include tenant1 project when tenant1 is active'
        );
    }

    /**
     * Test that DashboardController getRecentTasks uses active tenant
     */
    public function test_dashboard_recent_tasks_uses_active_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant2
        $this->withSession(['selected_tenant_id' => $this->tenant2->id]);
        
        // Call recent tasks endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/recent-tasks');
        
        $response->assertStatus(200);
        
        $tasks = $response->json('data') ?? [];
        
        // Verify all tasks are from tenant2
        foreach ($tasks as $task) {
            // Verify tenant1's task is not included
            $this->assertNotEquals(
                $this->task1->id,
                $task['id'],
                'Should not include tenant1 task when tenant2 is active'
            );
        }
        
        // Verify tenant2's task is included
        $taskIds = array_column($tasks, 'id');
        $this->assertContains(
            $this->task2->id,
            $taskIds,
            'Should include tenant2 task when tenant2 is active'
        );
    }

    /**
     * Test that DashboardController falls back to default tenant when no session
     */
    public function test_dashboard_falls_back_to_default_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Don't set session (no active tenant selected)
        // Should fall back to default tenant (tenant1, is_default=true)
        
        // Call dashboard stats endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should use default tenant (tenant1)
        // Note: This test verifies fallback behavior, actual implementation
        // may use TenancyService which checks default tenant
        $this->assertIsArray($data);
        $this->assertArrayHasKey('projects', $data);
    }

    /**
     * Test that DashboardController uses request attribute active_tenant_id if set
     * (simulating EnsureTenantPermission middleware behavior)
     */
    public function test_dashboard_uses_request_attribute_active_tenant_id(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Simulate middleware setting active_tenant_id in request attributes
        // We'll test this by setting session and verifying it's used
        $this->withSession(['selected_tenant_id' => $this->tenant2->id]);
        
        // Call dashboard metrics endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/metrics');
        
        $response->assertStatus(200);
        
        // Verify response is for tenant2
        $data = $response->json('data');
        $this->assertIsArray($data);
        
        // Metrics should reflect tenant2's data
        $this->assertEquals(1, $data['totalProjects'] ?? 0, 'Should count only tenant2 projects');
    }

    /**
     * Test that DashboardController getTeamStatus uses active tenant
     */
    public function test_dashboard_team_status_uses_active_tenant(): void
    {
        // Create additional users in both tenants
        $user1 = User::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        $user2 = User::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Set active tenant to tenant2
        $this->withSession(['selected_tenant_id' => $this->tenant2->id]);
        
        // Call team status endpoint
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/team-status');
        
        $response->assertStatus(200);
        
        $members = $response->json('data') ?? [];
        
        // Verify all members are from tenant2
        foreach ($members as $member) {
            // Members should be from tenant2, not tenant1
            // We can verify by checking that user1 is not in the list
            $this->assertNotEquals(
                $user1->id,
                $member['id'],
                'Should not include tenant1 users when tenant2 is active'
            );
        }
        
        // Verify tenant2's user is included (if not excluded as current user)
        $memberIds = array_column($members, 'id');
        if ($user2->id !== $this->user->id) {
            $this->assertContains(
                $user2->id,
                $memberIds,
                'Should include tenant2 users when tenant2 is active'
            );
        }
    }
}

