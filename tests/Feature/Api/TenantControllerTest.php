<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for TenantController
 * 
 * Tests multi-tenant membership via user_tenants pivot table
 * 
 * @group auth
 * @group tenants
 */
class TenantControllerTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(45678);
        $this->setDomainName('tenants');
        $this->setupDomainIsolation();
    }
    
    public function test_get_me_tenants_uses_pivot_membership(): void
    {
        // Create multiple tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'member', 'is_default' => false]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me/tenants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenants' => [
                        '*' => ['id', 'name', 'slug', 'is_active', 'is_current', 'is_default']
                    ],
                    'count',
                    'current_tenant_id',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['count']);
        $this->assertCount(2, $data['tenants']);
        
        // Verify both tenants are present
        $tenantIds = collect($data['tenants'])->pluck('id')->toArray();
        $this->assertContains((string)$tenant1->id, $tenantIds);
        $this->assertContains((string)$tenant2->id, $tenantIds);
        
        // Verify current tenant is tenant1 (default)
        $this->assertEquals((string)$tenant1->id, $data['current_tenant_id']);
    }
    
    public function test_select_tenant_requires_membership(): void
    {
        // Create tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user only to tenant1 via pivot
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        
        Sanctum::actingAs($user);
        
        // Try to select tenant2 (user is not a member)
        $response = $this->postJson("/api/v1/me/tenants/{$tenant2->id}/select");
        
        $response->assertStatus(403)
            ->assertJson([
                'ok' => false,
                'code' => 'FORBIDDEN',
            ])
            ->assertJsonFragment([
                'message' => 'You do not have access to this tenant',
            ]);
    }
    
    public function test_select_tenant_updates_active_tenant_in_me_payload(): void
    {
        // Create tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'member', 'is_default' => false]);
        
        Sanctum::actingAs($user);
        
        // Select tenant2 with include_me=true
        $response = $this->postJson("/api/v1/me/tenants/{$tenant2->id}/select?include_me=true");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenant_id',
                    'tenant_name',
                    'message',
                    'me' => [
                        'user' => ['id', 'tenant_id'],
                        'tenants_summary',
                    ],
                ],
            ]);
        
        $data = $response->json('data');
        
        // Verify me payload reflects selected tenant
        $this->assertEquals($tenant2->id, $data['me']['user']['tenant_id']);
        
        // Verify session was updated
        $this->assertEquals($tenant2->id, session('selected_tenant_id'));
    }
    
    public function test_get_tenants_backward_compatibility_with_legacy_tenant_id(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create(['name' => 'Legacy Tenant', 'slug' => 'legacy-' . uniqid()]);
        
        // Create a user with tenant_id but no pivot row (legacy data)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // No pivot membership - should fallback to tenant_id
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me/tenants');
        
        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Should still return the tenant via legacy fallback
        $this->assertEquals(1, $data['count']);
        $this->assertCount(1, $data['tenants']);
        $this->assertEquals($tenant->id, $data['tenants'][0]['id']);
    }

    public function test_select_tenant_sets_default_membership_and_clears_other_defaults(): void
    {
        // Create tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot
        // tenant1 is default, tenant2 is not
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'member', 'is_default' => false]);
        
        Sanctum::actingAs($user);
        
        // Verify initial state
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant1->id,
            'is_default' => true,
        ]);
        
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant2->id,
            'is_default' => false,
        ]);
        
        // Select tenant2 with include_me=true
        $response = $this->postJson("/api/v1/me/tenants/{$tenant2->id}/select?include_me=true");
        
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
        
        // Verify tenant2 is now default
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant2->id,
            'is_default' => true,
        ]);
        
        // Verify tenant1 is no longer default
        $this->assertDatabaseHas('user_tenants', [
            'user_id' => $user->id,
            'tenant_id' => $tenant1->id,
            'is_default' => false,
        ]);
        
        // Verify session was updated
        $this->assertEquals($tenant2->id, session('selected_tenant_id'));
        
        // Verify me payload reflects selected tenant
        $data = $response->json('data');
        $this->assertEquals($tenant2->id, $data['me']['user']['tenant_id']);
        $this->assertEquals('member', $data['me']['current_tenant_role']);
    }

    public function test_get_tenants_includes_role_field(): void
    {
        // Create tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants via pivot with different roles
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'member', 'is_default' => false]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me/tenants');
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tenants' => [
                        '*' => ['id', 'name', 'slug', 'is_active', 'is_current', 'is_default', 'role']
                    ],
                    'count',
                    'current_tenant_id',
                ],
            ]);
        
        $data = $response->json('data');
        $this->assertEquals(2, $data['count']);
        
        // Verify roles are included
        $tenant1Data = collect($data['tenants'])->firstWhere('id', $tenant1->id);
        $this->assertNotNull($tenant1Data);
        $this->assertEquals('owner', $tenant1Data['role']);
        
        $tenant2Data = collect($data['tenants'])->firstWhere('id', $tenant2->id);
        $this->assertNotNull($tenant2Data);
        $this->assertEquals('member', $tenant2Data['role']);
    }
}

