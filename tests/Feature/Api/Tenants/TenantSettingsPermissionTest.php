<?php declare(strict_types=1);

namespace Tests\Feature\Api\Tenants;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Laravel\Sanctum\Sanctum;

/**
 * Tests for Settings API permission enforcement
 * 
 * Tests that settings endpoints properly enforce tenant.permission middleware
 * for view endpoints (GET) and mutation endpoints (PUT).
 * 
 * Round 30: RBAC Gap Sweep & Missing Modules
 * 
 * @group tenant-settings
 * @group tenant-permissions
 */
class TenantSettingsPermissionTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(66666);
        $this->setDomainName('tenant-settings-permission');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
    }

    /**
     * Test that GET /api/v1/app/settings requires tenant.view_settings permission
     */
    public function test_get_settings_requires_view_permission(): void
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->getJson('/api/v1/app/settings');
            
            $response->assertStatus(200, "Role {$role} should be able to GET settings (has tenant.view_settings)");
        }
    }

    /**
     * Test that PUT /api/v1/app/settings/general requires tenant.manage_settings permission
     */
    public function test_update_general_settings_requires_manage_permission(): void
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->putJson('/api/v1/app/settings/general', [
                'timezone' => 'UTC',
            ]);
            
            $response->assertStatus(200, "Role {$role} should be able to update general settings");
        }
    }

    /**
     * Test that PUT /api/v1/app/settings/general returns 403 without permission
     */
    public function test_update_general_settings_returns_403_without_permission(): void
    {
        $roles = ['member', 'viewer'];
        
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
            
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'Authorization' => "Bearer {$token}",
            ])->putJson('/api/v1/app/settings/general', [
                'timezone' => 'UTC',
            ]);
            
            $response->assertStatus(403, "Role {$role} should NOT be able to update general settings");
            $response->assertJson([
                'ok' => false,
                'code' => 'TENANT_PERMISSION_DENIED',
            ]);
        }
    }

    /**
     * Test that PUT /api/v1/app/settings/notifications requires tenant.manage_settings permission
     */
    public function test_update_notifications_settings_requires_manage_permission(): void
    {
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
        
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->putJson('/api/v1/app/settings/notifications', [
            'email_notifications' => true,
        ]);
        
        $response->assertStatus(200);
    }

    /**
     * Test that PUT /api/v1/app/settings/notifications returns 403 without permission
     */
    public function test_update_notifications_settings_returns_403_without_permission(): void
    {
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
}

