<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App\Dashboard;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Dashboard View Permission Tests (Round 11)
 * 
 * Tests that dashboard GET endpoints require tenant.view_analytics permission.
 * Round 11 hardens GET routes with tenant.view_* permissions.
 * 
 * @group dashboard
 * @group tenant-permissions
 */
class DashboardViewPermissionTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('dashboard-view-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
    }

    /**
     * Test that viewer or member with tenant.view_analytics can access GET /api/v1/app/dashboard/stats (Round 11)
     * 
     * Round 11: GET routes now require tenant.view_analytics permission.
     * Viewer and member roles have tenant.view_analytics from config, so should pass.
     */
    public function test_viewer_member_with_view_analytics_can_access_dashboard_stats(): void
    {
        // Create user with viewer role (has tenant.view_analytics from config)
        $viewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $viewer->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($viewer);
        $token = $viewer->createToken('test-token')->plainTextToken;

        // Viewer should be able to access dashboard stats (has tenant.view_analytics)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');

        $response->assertStatus(200);
        $this->assertIsArray($response->json('data'));

        // Create user with member role (has tenant.view_analytics from config)
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $member->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($member);
        $memberToken = $member->createToken('test-token')->plainTextToken;

        // Member should be able to access dashboard stats (has tenant.view_analytics)
        $memberResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$memberToken}",
        ])->getJson('/api/v1/app/dashboard/stats');

        $memberResponse->assertStatus(200);
        $this->assertIsArray($memberResponse->json('data'));
    }

    /**
     * Test that admin/owner with tenant.view_analytics can access dashboard
     */
    public function test_admin_owner_can_access_dashboard(): void
    {
        // Create user with admin role (has tenant.view_analytics from config)
        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $admin->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        Sanctum::actingAs($admin);
        $token = $admin->createToken('test-token')->plainTextToken;

        // Admin should be able to access dashboard index
        $indexResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard');

        $indexResponse->assertStatus(200);
        $this->assertIsArray($indexResponse->json('data'));

        // Admin should be able to access dashboard stats
        $statsResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');

        $statsResponse->assertStatus(200);
        $this->assertIsArray($statsResponse->json('data'));
    }

    /**
     * Test that user without tenant.view_analytics cannot access dashboard GET endpoints (Round 11)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_analytics_cannot_access_dashboard_stats(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_analytics
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET dashboard stats
        $statsResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/stats');

        $statsResponse->assertStatus(403);
        $statsResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);

        // Guest should NOT be able to GET dashboard index
        $indexResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard');

        $indexResponse->assertStatus(403);
        $indexResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can access dashboard GET endpoints (Round 11)
     * 
     * All standard roles have tenant.view_analytics from config, so should all pass.
     */
    public function test_all_standard_roles_can_access_dashboard(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            // All standard roles should be able to GET dashboard stats
            $statsResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/dashboard/stats');

            $statsResponse->assertStatus(200, "Role {$role} should be able to GET dashboard/stats (has tenant.view_analytics)");
            $this->assertIsArray($statsResponse->json('data'));

            // All standard roles should be able to GET dashboard index
            $indexResponse = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/dashboard');

            $indexResponse->assertStatus(200, "Role {$role} should be able to GET dashboard (has tenant.view_analytics)");
            $this->assertIsArray($indexResponse->json('data'));
        }
    }

    /**
     * Test that all 4 standard roles can access other dashboard GET endpoints (Round 12)
     * 
     * Tests additional endpoints: recent-projects, recent-tasks, alerts, widgets
     * All standard roles have tenant.view_analytics from config, so should all pass.
     */
    public function test_all_standard_roles_can_access_other_dashboard_endpoints(): void
    {
        $roles = ['owner', 'admin', 'member', 'viewer'];
        $endpoints = [
            '/api/v1/app/dashboard/recent-projects',
            '/api/v1/app/dashboard/recent-tasks',
            '/api/v1/app/dashboard/alerts',
            '/api/v1/app/dashboard/widgets',
        ];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'tenant_id' => $this->tenant->id,
                'email_verified_at' => now(),
            ]);

            $user->tenants()->attach($this->tenant->id, [
                'role' => $role,
                'is_default' => true,
            ]);

            Sanctum::actingAs($user);
            $token = $user->createToken('test-token')->plainTextToken;

            foreach ($endpoints as $endpoint) {
                $response = $this->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ])->getJson($endpoint);

                $response->assertStatus(200, "Role {$role} should be able to GET {$endpoint} (has tenant.view_analytics)");
                
                // Verify response structure (should have data or be an array)
                $responseData = $response->json('data');
                $this->assertNotNull($responseData, "Response for {$endpoint} should have data field");
            }
        }
    }

    /**
     * Test that user without tenant.view_analytics cannot access other dashboard GET endpoints (Round 12)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403 on all dashboard endpoints.
     */
    public function test_user_without_view_analytics_cannot_access_other_dashboard_endpoints(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_analytics
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $endpoints = [
            '/api/v1/app/dashboard/recent-projects',
            '/api/v1/app/dashboard/recent-tasks',
            '/api/v1/app/dashboard/alerts',
            '/api/v1/app/dashboard/widgets',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson($endpoint);

            $response->assertStatus(403, "Guest should NOT be able to GET {$endpoint} (no tenant.view_analytics)");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }
}

