<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Services\MeService;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for MeService
 * 
 * Tests the canonical "me" response builder
 * 
 * @group auth
 * @group me
 */
class MeServiceTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;
    
    private MeService $meService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(78901);
        $this->setDomainName('me');
        $this->setupDomainIsolation();
        
        $this->meService = app(MeService::class);
    }
    
    public function test_build_me_response_returns_correct_structure(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);
        
        // Create a user with tenant
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'email_verified_at' => now(),
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('user', $response);
        $this->assertArrayHasKey('permissions', $response);
        $this->assertArrayHasKey('abilities', $response);
        $this->assertArrayHasKey('tenants_summary', $response);
        $this->assertArrayHasKey('onboarding_state', $response);
        $this->assertArrayHasKey('current_tenant_role', $response);
        $this->assertArrayHasKey('current_tenant_permissions', $response);
        
        // Check user data
        $this->assertEquals($user->id, $response['user']['id']);
        $this->assertEquals($user->name, $response['user']['name']);
        $this->assertEquals($user->email, $response['user']['email']);
        $this->assertEquals($user->tenant_id, $response['user']['tenant_id']);
        $this->assertEquals('member', $response['user']['role']);
        
        // Check permissions
        $this->assertIsArray($response['permissions']);
        
        // Check abilities
        $this->assertIsArray($response['abilities']);
        $this->assertContains('tenant', $response['abilities']);
        
        // Check tenants_summary
        $this->assertIsArray($response['tenants_summary']);
        $this->assertArrayHasKey('count', $response['tenants_summary']);
        $this->assertArrayHasKey('items', $response['tenants_summary']);
        $this->assertEquals(1, $response['tenants_summary']['count']);
        $this->assertCount(1, $response['tenants_summary']['items']);
        
        // Check onboarding_state
        $this->assertEquals('completed', $response['onboarding_state']);
    }
    
    public function test_build_me_response_without_tenant(): void
    {
        // Create a user without tenant
        $user = User::factory()->create([
            'tenant_id' => null,
            'role' => 'member',
            'email_verified_at' => now(),
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check tenants_summary is empty
        $this->assertEquals(0, $response['tenants_summary']['count']);
        $this->assertCount(0, $response['tenants_summary']['items']);
        
        // Check onboarding_state
        $this->assertEquals('tenant_setup', $response['onboarding_state']);
        
        // Check abilities - should not have 'tenant' if no tenant_id
        $this->assertNotContains('tenant', $response['abilities']);
    }
    
    public function test_build_me_response_with_super_admin(): void
    {
        // Create a super admin user
        $user = User::factory()->create([
            'role' => 'super_admin',
            'email_verified_at' => now(),
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check abilities include 'admin'
        $this->assertContains('admin', $response['abilities']);
    }
    
    public function test_build_me_response_with_email_verification_pending(): void
    {
        // Create a user without email verification
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check onboarding_state
        $this->assertEquals('email_verification', $response['onboarding_state']);
    }
    
    public function test_build_me_response_includes_tenant_info(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-' . uniqid(),
        ]);
        
        // Create a user with tenant
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check tenant info in tenants_summary
        $tenantItem = $response['tenants_summary']['items'][0];
        $this->assertEquals($tenant->id, $tenantItem['id']);
        $this->assertEquals($tenant->name, $tenantItem['name']);
        $this->assertEquals($tenant->slug, $tenantItem['slug']);
        // Verify role field is present (may be null for legacy tenants)
        $this->assertArrayHasKey('role', $tenantItem);
    }

    public function test_me_includes_all_membership_tenants(): void
    {
        // Create multiple tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        $tenant3 = Tenant::factory()->create(['name' => 'Tenant 3', 'slug' => 'tenant-3-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id, // Legacy tenant_id
            'email_verified_at' => now(),
        ]);
        
        // Add user to multiple tenants via pivot
        $user->tenants()->attach($tenant1->id, ['role' => 'owner', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'member', 'is_default' => false]);
        $user->tenants()->attach($tenant3->id, ['role' => 'member', 'is_default' => false]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check tenants_summary includes all memberships
        $this->assertEquals(3, $response['tenants_summary']['count']);
        $this->assertCount(3, $response['tenants_summary']['items']);
        
        // Verify all tenants are present
        $tenantIds = collect($response['tenants_summary']['items'])->pluck('id')->toArray();
        $this->assertContains((string)$tenant1->id, $tenantIds);
        $this->assertContains((string)$tenant2->id, $tenantIds);
        $this->assertContains((string)$tenant3->id, $tenantIds);
    }

    public function test_me_uses_session_selected_tenant_as_active(): void
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
        
        // Create a mock request with session
        $request = \Illuminate\Http\Request::create('/api/v1/me');
        $request->setLaravelSession(app('session.store'));
        $request->session()->put('selected_tenant_id', $tenant2->id);
        
        $response = $this->meService->buildMeResponse($user, $request);
        
        // Check that active tenant is tenant2 (from session)
        $this->assertEquals($tenant2->id, $response['user']['tenant_id']);
        
        // But tenants_summary still includes all tenants
        $this->assertEquals(2, $response['tenants_summary']['count']);
    }

    public function test_me_falls_back_to_default_tenant_when_no_session(): void
    {
        // Create multiple tenants with unique slugs
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to both tenants, tenant2 is default
        $user->tenants()->attach($tenant1->id, ['role' => 'member', 'is_default' => false]);
        $user->tenants()->attach($tenant2->id, ['role' => 'owner', 'is_default' => true]);
        
        // Create a mock request without session selection
        $request = \Illuminate\Http\Request::create('/api/v1/me');
        $request->setLaravelSession(app('session.store'));
        
        $response = $this->meService->buildMeResponse($user, $request);
        
        // Check that active tenant is tenant2 (default from pivot)
        $this->assertEquals($tenant2->id, $response['user']['tenant_id']);
    }

    public function test_get_tenants_summary_handles_legacy_tenant_without_pivot(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create([
            'name' => 'Legacy Tenant',
            'slug' => 'legacy-' . uniqid(),
        ]);
        
        // Create a user with tenant_id but no pivot row (legacy data)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Ensure no pivot row exists
        // getMembershipTenants() will fallback to tenant relationship
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check tenants_summary handles legacy fallback
        $this->assertEquals(1, $response['tenants_summary']['count']);
        $this->assertCount(1, $response['tenants_summary']['items']);
        
        $tenantItem = $response['tenants_summary']['items'][0];
        $this->assertEquals($tenant->id, $tenantItem['id']);
        // Legacy tenant without pivot should have is_default = false
        $this->assertEquals(false, $tenantItem['is_default']);
        // Legacy tenant without pivot should have role = null
        $this->assertNull($tenantItem['role']);
    }

    public function test_me_includes_current_tenant_role_and_roles_in_summary(): void
    {
        // Create multiple tenants with unique slugs
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
        
        // No session selected_tenant_id → active = tenant1 (default)
        $response = $this->meService->buildMeResponse($user);
        
        // Check current_tenant_role is included
        $this->assertArrayHasKey('current_tenant_role', $response);
        $this->assertEquals('owner', $response['current_tenant_role']);
        
        // Check tenants_summary includes roles
        $this->assertEquals(2, $response['tenants_summary']['count']);
        
        $tenant1Item = collect($response['tenants_summary']['items'])
            ->firstWhere('id', $tenant1->id);
        $this->assertNotNull($tenant1Item);
        $this->assertEquals('owner', $tenant1Item['role']);
        $this->assertEquals(true, $tenant1Item['is_default']);
        
        $tenant2Item = collect($response['tenants_summary']['items'])
            ->firstWhere('id', $tenant2->id);
        $this->assertNotNull($tenant2Item);
        $this->assertEquals('member', $tenant2Item['role']);
        $this->assertEquals(false, $tenant2Item['is_default']);
    }

    public function test_abilities_tenant_based_on_membership_not_tenant_id(): void
    {
        // Case 1: User with pivot membership but tenant_id = null
        $tenant1 = Tenant::factory()->create(['name' => 'Tenant 1', 'slug' => 'tenant-1-' . uniqid()]);
        
        $user1 = User::factory()->create([
            'tenant_id' => null, // No legacy tenant_id
            'email_verified_at' => now(),
        ]);
        
        // Add user to tenant via pivot
        $user1->tenants()->attach($tenant1->id, ['role' => 'member', 'is_default' => true]);
        
        $response1 = $this->meService->buildMeResponse($user1);
        
        // Should have 'tenant' ability based on membership
        $this->assertContains('tenant', $response1['abilities']);
        $this->assertNotNull($response1['current_tenant_role']);
        $this->assertEquals('member', $response1['current_tenant_role']);
        
        // Case 2: User with tenant_id but no pivot (backward-compatible)
        $tenant2 = Tenant::factory()->create(['name' => 'Tenant 2', 'slug' => 'tenant-2-' . uniqid()]);
        
        $user2 = User::factory()->create([
            'tenant_id' => $tenant2->id, // Legacy tenant_id
            'email_verified_at' => now(),
        ]);
        
        // No pivot membership - should fallback to tenant_id
        $response2 = $this->meService->buildMeResponse($user2);
        
        // Should still have 'tenant' ability (backward-compatible)
        $this->assertContains('tenant', $response2['abilities']);
    }

    public function test_me_includes_current_tenant_permissions_for_owner_role(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-' . uniqid(),
        ]);
        
        // Create a user
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Add user to tenant via pivot with owner role
        $user->tenants()->attach($tenant->id, ['role' => 'owner', 'is_default' => true]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check current_tenant_permissions is included
        $this->assertArrayHasKey('current_tenant_permissions', $response);
        $this->assertIsArray($response['current_tenant_permissions']);
        
        // Check permissions match config for owner role
        $expectedPermissions = config('permissions.tenant_roles.owner', []);
        $this->assertEquals($expectedPermissions, $response['current_tenant_permissions']);
        $this->assertNotEmpty($response['current_tenant_permissions']);
    }

    public function test_me_includes_empty_permissions_for_user_without_tenant_role(): void
    {
        // Create a user without tenant
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check current_tenant_permissions is included but empty
        $this->assertArrayHasKey('current_tenant_permissions', $response);
        $this->assertIsArray($response['current_tenant_permissions']);
        $this->assertEmpty($response['current_tenant_permissions']);
        $this->assertNull($response['current_tenant_role']);
    }

    public function test_me_includes_empty_permissions_for_legacy_tenant_without_pivot(): void
    {
        // Create a tenant with unique slug
        $tenant = Tenant::factory()->create([
            'name' => 'Legacy Tenant',
            'slug' => 'legacy-' . uniqid(),
        ]);
        
        // Create a user with tenant_id but no pivot row (legacy data)
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);
        
        // Ensure no pivot row exists
        // getMembershipTenants() will fallback to tenant relationship
        
        $response = $this->meService->buildMeResponse($user);
        
        // Check current_tenant_permissions is included but empty (no role from pivot)
        $this->assertArrayHasKey('current_tenant_permissions', $response);
        $this->assertIsArray($response['current_tenant_permissions']);
        $this->assertEmpty($response['current_tenant_permissions']);
        $this->assertNull($response['current_tenant_role']);
    }

    public function test_me_includes_permissions_for_different_tenant_roles(): void
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
        $user->tenants()->attach($tenant1->id, ['role' => 'admin', 'is_default' => true]);
        $user->tenants()->attach($tenant2->id, ['role' => 'viewer', 'is_default' => false]);
        
        // No session selected_tenant_id → active = tenant1 (default)
        $response = $this->meService->buildMeResponse($user);
        
        // Check current_tenant_permissions match admin role
        $expectedAdminPermissions = config('permissions.tenant_roles.admin', []);
        $this->assertEquals($expectedAdminPermissions, $response['current_tenant_permissions']);
        $this->assertEquals('admin', $response['current_tenant_role']);
    }
}

