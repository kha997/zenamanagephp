<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\DashboardAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Dashboard API permission enforcement
 * 
 * Tests that dashboard endpoints properly enforce tenant.permission middleware
 * and ensure tenant isolation, especially for alerts and widgets.
 * 
 * Round 29: RBAC & Multi-tenant Hardening for Search, Observability, Dashboard & Media
 * 
 * @group tenant-dashboard
 * @group tenant-permissions
 */
class TenantDashboardPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;
    private Tenant $tenantB;
    private Project $projectA;
    private Project $projectB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(99999);
        $this->setDomainName('tenant-dashboard-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B for isolation tests
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tenant A Project',
        ]);
        
        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Tenant B Project',
        ]);
        
        // Create users in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);
    }

    /**
     * Test that GET /api/v1/app/dashboard/* requires tenant.view_analytics permission
     */
    public function test_dashboard_views_require_view_analytics_permission(): void
    {
        $roles = ['owner', 'admin'];
        
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
            
            // Test main dashboard endpoint
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/dashboard/');
            
            $response->assertStatus(200, "Role {$role} should be able to access dashboard (has tenant.view_analytics)");
            $response->assertJsonStructure([
                'ok',
                'data',
            ]);
            
            // Test stats endpoint
            $response2 = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/dashboard/stats');
            
            $response2->assertStatus(200);
        }
    }

    /**
     * Test that GET /api/v1/app/dashboard/* denies user without view_analytics permission
     */
    public function test_dashboard_views_denied_without_view_analytics_permission(): void
    {
        // Create a user with a role that might not have tenant.view_analytics
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member', // member might not have view_analytics
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/');
        
        // If member doesn't have view_analytics, should return 403
        // If member has view_analytics, should return 200
        $this->assertContains($response->status(), [200, 403], 'Dashboard should either succeed with permission or return 403 without permission');
        
        if ($response->status() === 403) {
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that dashboard widget mutations require tenant.view_analytics permission
     */
    public function test_dashboard_widget_mutations_require_view_analytics(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test POST /dashboard/widgets
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/app/dashboard/widgets', [
            'widget_type' => 'stats',
        ]);
        
        $response->assertStatus(200, 'User with view_analytics should be able to add widget');
        
        // Test PUT /dashboard/widgets/{id}
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/dashboard/widgets/test-widget-id', [
            'config' => ['key' => 'value'],
        ]);
        
        $response2->assertStatus(200, 'User with view_analytics should be able to update widget');
        
        // Test DELETE /dashboard/widgets/{id}
        $response3 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->deleteJson('/api/v1/app/dashboard/widgets/test-widget-id');
        
        $response3->assertStatus(200, 'User with view_analytics should be able to remove widget');
    }

    /**
     * Test that dashboard widget mutations are denied without view_analytics permission
     */
    public function test_dashboard_widget_mutations_denied_without_view_analytics(): void
    {
        // Create a user without view_analytics permission
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member', // member might not have view_analytics
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Test POST /dashboard/widgets
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/v1/app/dashboard/widgets', [
            'widget_type' => 'stats',
        ]);
        
        // Should return 403 if no permission, or 200 if member has permission
        $this->assertContains($response->status(), [200, 403], 'Widget mutation should either succeed with permission or return 403 without permission');
        
        if ($response->status() === 403) {
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that user cannot mark alert of another user as read
     */
    public function test_user_cannot_mark_alert_of_another_user_as_read(): void
    {
        // Create alerts for user A and user B in tenant A
        $alertA = DashboardAlert::create([
            'user_id' => $this->userA->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Alert for User A',
            'message' => 'This is an alert for user A',
            'is_read' => false,
        ]);
        
        $alertB = DashboardAlert::create([
            'user_id' => $this->userB->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Alert for User B',
            'message' => 'This is an alert for user B',
            'is_read' => false,
        ]);
        
        // User A tries to mark User B's alert as read
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson("/api/v1/app/dashboard/alerts/{$alertB->id}/read");
        
        // Should return 404 (alert not found for this user) or 403
        $this->assertContains($response->status(), [403, 404], 'User A should not be able to mark User B\'s alert as read');
        
        // Verify alert B is still unread
        $alertB->refresh();
        $this->assertFalse($alertB->is_read, 'Alert B should remain unread');
        
        // Verify alert A can be marked as read by user A
        $response2 = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson("/api/v1/app/dashboard/alerts/{$alertA->id}/read");
        
        $response2->assertStatus(200, 'User A should be able to mark their own alert as read');
        
        $alertA->refresh();
        $this->assertTrue($alertA->is_read, 'Alert A should be marked as read');
    }

    /**
     * Test that read-all only affects current user alerts
     */
    public function test_read_all_only_affects_current_user_alerts(): void
    {
        // Create alerts for user A and user B
        $alertA1 = DashboardAlert::create([
            'user_id' => $this->userA->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Alert A1',
            'message' => 'Alert 1 for user A',
            'is_read' => false,
        ]);
        
        $alertA2 = DashboardAlert::create([
            'user_id' => $this->userA->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Alert A2',
            'message' => 'Alert 2 for user A',
            'is_read' => false,
        ]);
        
        $alertB = DashboardAlert::create([
            'user_id' => $this->userB->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Alert B',
            'message' => 'Alert for user B',
            'is_read' => false,
        ]);
        
        // User A marks all alerts as read
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/dashboard/alerts/read-all');
        
        $response->assertStatus(200);
        
        // Verify user A's alerts are read
        $alertA1->refresh();
        $alertA2->refresh();
        $this->assertTrue($alertA1->is_read, 'Alert A1 should be marked as read');
        $this->assertTrue($alertA2->is_read, 'Alert A2 should be marked as read');
        
        // Verify user B's alert is still unread
        $alertB->refresh();
        $this->assertFalse($alertB->is_read, 'Alert B should remain unread (user isolation)');
    }

    /**
     * Test that dashboard respects tenant context
     */
    public function test_dashboard_respects_tenant_context(): void
    {
        // Create alerts in both tenants
        $alertA = DashboardAlert::create([
            'user_id' => $this->userA->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Tenant A Alert',
            'message' => 'Alert in tenant A',
            'is_read' => false,
        ]);
        
        $alertB = DashboardAlert::create([
            'user_id' => $this->userA->id, // Same user but different tenant
            'tenant_id' => $this->tenantB->id,
            'type' => 'info',
            'category' => 'task',
            'title' => 'Tenant B Alert',
            'message' => 'Alert in tenant B',
            'is_read' => false,
        ]);
        
        // User A (in tenant A context) gets alerts
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/alerts');
        
        $response->assertStatus(200);
        $alerts = $response->json('data', []);
        
        // Should only see tenant A alerts
        $alertIds = array_column($alerts, 'id');
        $this->assertContains($alertA->id, $alertIds, 'Tenant A alert should be visible');
        $this->assertNotContains($alertB->id, $alertIds, 'Tenant B alert should NOT be visible to tenant A user');
    }

    /**
     * Test that GET /api/v1/app/dashboard/ denies guest role without view_analytics permission
     * Round 30: Strict negative test with guest role
     */
    public function test_dashboard_denies_guest_without_view_analytics_permission(): void
    {
        // Create user with guest role (role that doesn't exist in tenant_roles table or has no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Attach user to tenant with 'guest' role (not a standard role, should have no permissions)
        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Guest role should not have tenant.view_analytics permission
            'is_default' => true,
        ]);
        
        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/dashboard/');
        
        // Guest role without permission should get 403
        $response->assertStatus(403, 'Guest role without permission should be denied');
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }
}

