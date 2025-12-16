<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App\Dashboard;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Dashboard Active Tenant Context Tests
 * 
 * Tests that DashboardController respects the active tenant context
 * and does not leak cross-tenant data.
 * 
 * Scenarios covered:
 * - Multi-tenant membership, per-tenant data isolation
 * - Fallback to default tenant when no selected_tenant_id
 * - Legacy-only tenant_id fallback
 * 
 * @group dashboard
 * @group tenant-context
 */
class DashboardTenantContextTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $user;
    private Project $projectA;
    private Project $projectB;
    private Task $taskA;
    private Task $taskB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('dashboard-tenant-context');
        $this->setupDomainIsolation();
        
        // Create two tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);
        
        // Create user with membership in both tenants via user_tenants pivot
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantA->id, // Legacy tenant_id (fallback)
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot (owner role for both)
        $this->user->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        $this->user->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => false,
        ]);
        
        // Create projects in both tenants
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
        
        // Create tasks in both tenants
        $this->taskA = Task::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Task A',
        ]);
        
        $this->taskB = Task::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'name' => 'Task B',
        ]);
    }

    /**
     * Test multi-tenant membership with per-tenant data isolation
     * 
     * User U with membership in both Tenant A and Tenant B.
     * When Tenant A is selected, only Tenant A's data should appear.
     * When Tenant B is selected, only Tenant B's data should appear.
     */
    public function test_multi_tenant_membership_per_tenant_data_isolation(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Step 1: Select Tenant A via POST /api/v1/me/tenants/{A}/select?include_me=true
        $selectResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/me/tenants/{$this->tenantA->id}/select?include_me=true");
        
        $selectResponse->assertStatus(200);
        
        // Verify session was set
        $this->assertEquals($this->tenantA->id, session('selected_tenant_id'));
        
        // Step 2: Call GET /api/v1/app/dashboard/stats
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Assert: Only Tenant A's counts appear
        $this->assertEquals(1, $data['projects']['total'] ?? 0, 'Should only count Tenant A projects');
        $this->assertEquals(1, $data['tasks']['total'] ?? 0, 'Should only count Tenant A tasks');
        
        // Verify Tenant A's project is included
        $recentProjects = $this->getJson('/api/v1/app/dashboard/recent-projects')
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])
            ->json('data') ?? [];
        
        $projectIds = array_column($recentProjects, 'id');
        $this->assertContains($this->projectA->id, $projectIds, 'Should include Tenant A project');
        $this->assertNotContains($this->projectB->id, $projectIds, 'Should not include Tenant B project');
        
        // Step 3: Select Tenant B
        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/me/tenants/{$this->tenantB->id}/select?include_me=true");
        
        // Step 4: Call GET /api/v1/app/dashboard/stats again
        $responseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        $responseB->assertStatus(200);
        
        $dataB = $responseB->json('data');
        
        // Assert: Only Tenant B's counts appear
        $this->assertEquals(1, $dataB['projects']['total'] ?? 0, 'Should only count Tenant B projects');
        $this->assertEquals(1, $dataB['tasks']['total'] ?? 0, 'Should only count Tenant B tasks');
        
        // Verify Tenant B's project is included
        $recentProjectsB = $this->getJson('/api/v1/app/dashboard/recent-projects')
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])
            ->json('data') ?? [];
        
        $projectIdsB = array_column($recentProjectsB, 'id');
        $this->assertContains($this->projectB->id, $projectIdsB, 'Should include Tenant B project');
        $this->assertNotContains($this->projectA->id, $projectIdsB, 'Should not include Tenant A project');
    }

    /**
     * Test fallback to default tenant when no selected_tenant_id
     * 
     * User with pivot membership where is_default = true on Tenant A.
     * Do not call /select.
     * Call GET /api/v1/app/dashboard/stats.
     * Assert results only include Tenant A's data.
     */
    public function test_fallback_to_default_tenant_when_no_selected_tenant_id(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Ensure no session is set (no selected_tenant_id)
        $this->withSession([]);
        
        // Call GET /api/v1/app/dashboard/stats
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Assert: Results only include Tenant A's data (default tenant)
        $this->assertEquals(1, $data['projects']['total'] ?? 0, 'Should only count default tenant (Tenant A) projects');
        $this->assertEquals(1, $data['tasks']['total'] ?? 0, 'Should only count default tenant (Tenant A) tasks');
        
        // Verify Tenant A's project is included, Tenant B's is not
        $recentProjects = $this->getJson('/api/v1/app/dashboard/recent-projects')
            ->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])
            ->json('data') ?? [];
        
        $projectIds = array_column($recentProjects, 'id');
        $this->assertContains($this->projectA->id, $projectIds, 'Should include default tenant (Tenant A) project');
        $this->assertNotContains($this->projectB->id, $projectIds, 'Should not include Tenant B project');
    }

    /**
     * Test legacy-only tenant_id fallback
     * 
     * User with users.tenant_id set, no pivot membership.
     * Ensure DashboardController still returns data for that tenant, not null,
     * and does not crash.
     */
    public function test_legacy_only_tenant_id_fallback(): void
    {
        // Create a user with only legacy tenant_id (no pivot membership)
        $legacyUser = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        // Ensure no pivot membership exists
        $this->assertCount(0, $legacyUser->tenants()->get(), 'User should have no pivot memberships');
        
        Sanctum::actingAs($legacyUser);
        $token = $legacyUser->createToken('test-token')->plainTextToken;
        
        // Ensure no session is set
        $this->withSession([]);
        
        // Call GET /api/v1/app/dashboard/stats
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');
        
        // Should not crash and return 200
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Assert: Results include Tenant A's data (from legacy tenant_id)
        $this->assertIsArray($data, 'Should return data array');
        $this->assertArrayHasKey('projects', $data, 'Should have projects key');
        $this->assertArrayHasKey('tasks', $data, 'Should have tasks key');
        
        // Should have at least Tenant A's project and task
        $this->assertGreaterThanOrEqual(1, $data['projects']['total'] ?? 0, 'Should count Tenant A projects');
        $this->assertGreaterThanOrEqual(1, $data['tasks']['total'] ?? 0, 'Should count Tenant A tasks');
    }

    /**
     * Test that dashboard index endpoint respects active tenant
     */
    public function test_dashboard_index_respects_active_tenant(): void
    {
        Sanctum::actingAs($this->user);
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Select Tenant B
        $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson("/api/v1/me/tenants/{$this->tenantB->id}/select?include_me=true");
        
        // Call GET /api/v1/app/dashboard (index endpoint)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify stats are for Tenant B
        $this->assertEquals(1, $data['stats']['projects']['total'] ?? 0, 'Should only count Tenant B projects');
        $this->assertEquals(1, $data['stats']['tasks']['total'] ?? 0, 'Should only count Tenant B tasks');
        
        // Verify recent projects only include Tenant B
        $recentProjects = $data['recent_projects'] ?? [];
        $projectIds = array_column($recentProjects, 'id');
        $this->assertContains($this->projectB->id, $projectIds, 'Should include Tenant B project');
        $this->assertNotContains($this->projectA->id, $projectIds, 'Should not include Tenant A project');
        
        // Verify recent tasks only include Tenant B
        $recentTasks = $data['recent_tasks'] ?? [];
        $taskIds = array_column($recentTasks, 'id');
        $this->assertContains($this->taskB->id, $taskIds, 'Should include Tenant B task');
        $this->assertNotContains($this->taskA->id, $taskIds, 'Should not include Tenant A task');
    }
}

