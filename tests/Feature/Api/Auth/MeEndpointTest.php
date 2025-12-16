<?php declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\Tenant;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

/**
 * Feature tests for /api/v1/me endpoint
 * 
 * Tests the canonical "me" endpoint using MeService
 * 
 * @group auth
 * @group me
 */
class MeEndpointTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('me-endpoint');
        $this->setupDomainIsolation();
    }
    
    public function test_get_me_returns_canonical_structure(): void
    {
        // Create a tenant
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        // Create and authenticate a user
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'email',
                    'tenant_id',
                    'role',
                    'email_verified_at',
                    'last_login_at',
                    'created_at',
                    'is_active',
                ],
                'permissions',
                'abilities',
                'tenants_summary' => [
                    'count',
                    'items' => [
                        '*' => [
                            'id',
                            'name',
                            'slug',
                        ],
                    ],
                ],
                'onboarding_state',
            ],
        ]);
        
        // Check response data
        $data = $response->json('data');
        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertIsArray($data['permissions']);
        $this->assertIsArray($data['abilities']);
        $this->assertContains('tenant', $data['abilities']);
        $this->assertEquals(1, $data['tenants_summary']['count']);
        $this->assertEquals('completed', $data['onboarding_state']);
    }
    
    public function test_get_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me');
        
        $response->assertStatus(401);
    }
    
    public function test_get_me_without_tenant(): void
    {
        // Create a user without tenant
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(0, $data['tenants_summary']['count']);
        $this->assertEquals('tenant_setup', $data['onboarding_state']);
    }
    
    public function test_get_me_with_super_admin(): void
    {
        // Create a super admin user
        $user = User::factory()->create([
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me');
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertContains('admin', $data['abilities']);
    }
    
    public function test_auth_me_endpoint_still_works(): void
    {
        // Test backward compatibility of /api/auth/me
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/auth/me');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user',
                'permissions',
                'abilities',
                'tenants_summary',
                'onboarding_state',
            ],
        ]);
        
        // Should return same structure as /api/v1/me
        $data = $response->json('data');
        $this->assertEquals($user->id, $data['user']['id']);
        $this->assertIsArray($data['permissions']);
        $this->assertIsArray($data['abilities']);
    }
    
    public function test_get_me_tenants_returns_consistent_structure(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        $response = $this->getJson('/api/v1/me/tenants');
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'tenants' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'is_active',
                        'is_current',
                    ],
                ],
                'count',
                'current_tenant_id',
            ],
        ]);
        
        $data = $response->json('data');
        $this->assertCount(1, $data['tenants']);
        $this->assertEquals(1, $data['count']);
        $this->assertEquals($tenant->id, $data['current_tenant_id']);
    }
    
    public function test_select_tenant_returns_fresh_me_payload_when_requested(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
        ]);
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        // Select tenant with include_me=true
        $response = $this->postJson("/api/v1/me/tenants/{$tenant->id}/select?include_me=true");
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'tenant_id',
                'tenant_name',
                'message',
                'me' => [
                    'user',
                    'permissions',
                    'abilities',
                    'tenants_summary',
                    'onboarding_state',
                ],
            ],
        ]);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('me', $data);
        $this->assertEquals($user->id, $data['me']['user']['id']);
    }
    
    public function test_select_tenant_without_include_me(): void
    {
        $tenant = Tenant::factory()->create();
        
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        Sanctum::actingAs($user);
        
        // Select tenant without include_me
        $response = $this->postJson("/api/v1/me/tenants/{$tenant->id}/select");
        
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertArrayNotHasKey('me', $data);
        $this->assertEquals($tenant->id, $data['tenant_id']);
    }
}

