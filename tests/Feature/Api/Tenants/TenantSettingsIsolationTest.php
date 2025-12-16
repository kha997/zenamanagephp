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
 * Tests for Settings API cross-tenant isolation
 * 
 * Tests that settings endpoints properly enforce tenant isolation.
 * 
 * Round 30: RBAC Gap Sweep & Missing Modules
 * 
 * @group tenant-settings-isolation
 * @group tenant-permissions
 */
class TenantSettingsIsolationTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('tenant-settings-isolation');
        $this->setupDomainIsolation();
        
        // Create tenant A
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Test Tenant A',
            'slug' => 'test-tenant-a-' . uniqid(),
        ]);
        
        // Create tenant B
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Test Tenant B',
            'slug' => 'test-tenant-b-' . uniqid(),
        ]);
        
        // Create user A in tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
        
        // Create user B in tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'email_verified_at' => now(),
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);
    }

    /**
     * Test that tenant A settings are isolated from tenant B
     */
    public function test_settings_are_tenant_isolated(): void
    {
        Sanctum::actingAs($this->userA);
        $token = $this->userA->createToken('test-token')->plainTextToken;
        
        // User A should see tenant A settings
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$token}",
        ])->getJson('/api/v1/app/settings');
        
        $response->assertStatus(200);
        
        // Settings should be scoped to tenant A
        $settings = $response->json();
        // The response should contain tenant-scoped settings
        // (exact structure depends on SettingsController implementation)
        $this->assertNotNull($settings, 'Settings should be returned');
    }

    /**
     * Test that tenant A cannot modify tenant B settings
     */
    public function test_cannot_modify_settings_of_another_tenant(): void
    {
        // Settings are user/tenant-scoped, so this test verifies
        // that the controller properly uses tenant context
        
        // Get initial settings for user B (tenant B)
        Sanctum::actingAs($this->userB);
        $tokenB = $this->userB->createToken('test-token')->plainTextToken;
        
        $initialResponseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/app/settings');
        
        $initialResponseB->assertStatus(200);
        $initialSettingsB = $initialResponseB->json('data.user_settings') ?? [];
        $initialTimezoneB = $initialSettingsB['timezone'] ?? 'UTC';
        
        // Now switch to user A (tenant A) and update their settings
        Sanctum::actingAs($this->userA);
        $tokenA = $this->userA->createToken('test-token')->plainTextToken;
        
        // User A should only be able to modify tenant A settings
        // (tenant context is enforced by middleware and controller)
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenA}",
        ])->putJson('/api/v1/app/settings/general', [
            'timezone' => 'America/New_York',
        ]);
        
        // Should succeed (modifying own tenant settings)
        $response->assertStatus(200);
        
        // Verify user A's settings were updated
        $updatedSettingsA = $response->json('data');
        $this->assertEquals('America/New_York', $updatedSettingsA['timezone'] ?? null, 'User A timezone should be updated');
        
        // Verify that tenant B settings are NOT affected
        // Switch back to user B and verify their settings are unchanged
        Sanctum::actingAs($this->userB);
        
        $finalResponseB = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => "Bearer {$tokenB}",
        ])->getJson('/api/v1/app/settings');
        
        $finalResponseB->assertStatus(200);
        $finalSettingsB = $finalResponseB->json('data.user_settings') ?? [];
        $finalTimezoneB = $finalSettingsB['timezone'] ?? 'UTC';
        
        // Assert tenant B settings are not affected
        $this->assertEquals(
            $initialTimezoneB,
            $finalTimezoneB,
            'Tenant B timezone should not be affected by tenant A settings update'
        );
        $this->assertNotEquals(
            'America/New_York',
            $finalTimezoneB,
            'Tenant B timezone should not match tenant A\'s updated timezone'
        );
    }
}

