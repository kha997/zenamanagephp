<?php declare(strict_types=1);

namespace Tests\Feature\Api\Settings;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Settings API tenant permission enforcement
 * 
 * Tests that settings endpoints properly enforce tenant.permission middleware
 * for mutation endpoints (PUT requests).
 * 
 * @group settings
 * @group tenant-permissions
 */
class SettingsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('settings-permission');
        $this->setupDomainIsolation();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
    }

    /**
     * Test that GET /api/v1/app/settings requires tenant.view_settings permission (Round 11)
     * 
     * Viewer role has tenant.view_settings from config, so should be able to GET settings.
     */
    public function test_get_settings_requires_view_permission(): void
    {
        // Create user with viewer role (has tenant.view_settings from config)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should be able to view settings (has tenant.view_settings)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/settings');

        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/settings/general requires tenant.manage_settings permission
     */
    public function test_update_general_settings_requires_manage_permission(): void
    {
        // Create user with admin role (has tenant.manage_settings)
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

        // Admin should be able to update settings
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/general', [
            'timezone' => 'America/New_York',
            'language' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => '24',
            'currency' => 'USD',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test that PUT /api/v1/app/settings/general returns 403 without tenant.manage_settings permission
     */
    public function test_update_general_settings_returns_403_without_permission(): void
    {
        // Create user with viewer role (only has view permissions, not manage)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should NOT be able to update settings
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/general', [
            'timezone' => 'America/New_York',
            'language' => 'en',
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that PUT /api/v1/app/settings/notifications requires tenant.manage_settings permission
     */
    public function test_update_notifications_requires_manage_permission(): void
    {
        // Create user with admin role
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

        // Admin should be able to update notification settings
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/notifications', [
            'email_notifications' => true,
            'push_notifications' => false,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test that PUT /api/v1/app/settings/notifications returns 403 without permission
     */
    public function test_update_notifications_returns_403_without_permission(): void
    {
        // Create user with member role (no tenant.manage_settings)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Member should NOT be able to update notification settings
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/notifications', [
            'email_notifications' => true,
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that all Settings PUT endpoints require tenant.manage_settings
     */
    public function test_all_settings_put_endpoints_require_permission(): void
    {
        // Create user with viewer role (no manage permission)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        $endpoints = [
            '/api/v1/app/settings/general',
            '/api/v1/app/settings/notifications',
            '/api/v1/app/settings/appearance',
            '/api/v1/app/settings/security',
            '/api/v1/app/settings/privacy',
            '/api/v1/app/settings/integrations',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->putJson($endpoint, []);

            $response->assertStatus(403);
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ], "Endpoint {$endpoint} should require tenant.manage_settings");
        }
    }

    /**
     * Test that owner role can update all settings endpoints
     */
    public function test_owner_role_can_update_all_settings(): void
    {
        // Create user with owner role (has tenant.manage_settings)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Owner should be able to update all settings endpoints
        $endpoints = [
            ['/api/v1/app/settings/general', ['timezone' => 'UTC']],
            ['/api/v1/app/settings/notifications', ['email_notifications' => true]],
            ['/api/v1/app/settings/appearance', ['theme' => 'dark']],
            ['/api/v1/app/settings/security', ['two_factor_enabled' => false]],
            ['/api/v1/app/settings/privacy', ['profile_visibility' => 'private']],
            ['/api/v1/app/settings/integrations', ['google_calendar_sync' => false]],
        ];

        foreach ($endpoints as [$endpoint, $data]) {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->putJson($endpoint, $data);

            $this->assertContains(
                $response->status(),
                [200, 201],
                "Owner should be able to update {$endpoint}"
            );
        }
    }

    /**
     * Test Settings view vs manage permissions (Round 9)
     * 
     * Confirm that viewer without tenant.manage_settings but with tenant.view_settings
     * can GET /api/v1/app/settings → 200 OK
     * But PUT /api/v1/app/settings/* → 403 + TENANT_PERMISSION_DENIED
     */
    public function test_viewer_with_view_settings_can_get_but_not_put(): void
    {
        // Create user with viewer role (has tenant.view_settings, not tenant.manage_settings)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Viewer should be able to GET settings (has tenant.view_settings)
        $getResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/settings');

        $getResponse->assertStatus(200);

        // Viewer should NOT be able to PUT settings (no tenant.manage_settings)
        $putResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/general', [
            'timezone' => 'America/New_York',
        ]);

        $putResponse->assertStatus(403);
        $putResponse->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that admin/owner with both view & manage can access all settings endpoints
     */
    public function test_admin_with_both_view_and_manage_can_access_all_endpoints(): void
    {
        // Create user with admin role (has both tenant.view_settings and tenant.manage_settings)
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

        // Admin should be able to GET settings
        $getResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/settings');

        $getResponse->assertStatus(200);

        // Admin should be able to PUT settings
        $putResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/general', [
            'timezone' => 'UTC',
            'language' => 'en',
        ]);

        $putResponse->assertStatus(200);
        $putResponse->assertJson([
            'success' => true,
        ]);
    }

    /**
     * Test that user without tenant.view_settings cannot GET settings (Round 11)
     * 
     * Negative test: role 'guest' is not defined in config/permissions.php tenant_roles,
     * so user will have no permissions and should get 403.
     */
    public function test_user_without_view_settings_cannot_get_settings(): void
    {
        // Create user with 'guest' role (not in config/permissions.php, so no permissions)
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_verified_at' => now(),
        ]);

        $user->tenants()->attach($this->tenant->id, [
            'role' => 'guest', // Role not in config, so no tenant.view_settings
            'is_default' => true,
        ]);

        Sanctum::actingAs($user);
        $token = $user->createToken('test-token')->plainTextToken;

        // Guest should NOT be able to GET settings (no tenant.view_settings)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/settings');

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'code' => 'TENANT_PERMISSION_DENIED',
        ]);
    }

    /**
     * Test that all 4 standard roles (owner/admin/member/viewer) can GET settings (Round 11)
     * 
     * All standard roles have tenant.view_settings from config, so should all pass.
     */
    public function test_all_standard_roles_can_get_settings(): void
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

            // All standard roles should be able to GET settings
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/settings');

            $response->assertStatus(200, "Role {$role} should be able to GET settings (has tenant.view_settings)");
        }
    }
}

