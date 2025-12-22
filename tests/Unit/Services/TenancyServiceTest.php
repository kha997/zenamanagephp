<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Services\TenancyService;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Unit tests for TenancyService
 * 
 * @group services
 * @group tenancy
 */
class TenancyServiceTest extends TestCase
{
    use RefreshDatabase, DatabaseTrait, DomainTestIsolation;

    private TenancyService $tenancyService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('tenancy-service');
        $this->setupDomainIsolation();
        
        $this->tenancyService = app(TenancyService::class);
    }

    /**
     * Test getCurrentTenantPermissions returns empty array for user without tenant
     */
    public function test_get_current_tenant_permissions_returns_empty_for_user_without_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => null,
            'email_verified_at' => now(),
        ]);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user);

        $this->assertIsArray($permissions);
        $this->assertEmpty($permissions);
    }

    /**
     * Test getCurrentTenantPermissions returns permissions for owner role
     */
    public function test_get_current_tenant_permissions_returns_permissions_for_owner_role(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with owner role
        $user->tenants()->attach($tenant->id, [
            'role' => 'owner',
            'is_default' => true,
        ]);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user);

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('tenant.manage_members', $permissions);
        $this->assertContains('tenant.manage_projects', $permissions);
        $this->assertContains('tenant.view_billing', $permissions);
    }

    /**
     * Test getCurrentTenantPermissions returns permissions for admin role
     */
    public function test_get_current_tenant_permissions_returns_permissions_for_admin_role(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with admin role
        $user->tenants()->attach($tenant->id, [
            'role' => 'admin',
            'is_default' => true,
        ]);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user);

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('tenant.manage_members', $permissions);
        $this->assertContains('tenant.manage_projects', $permissions);
        $this->assertNotContains('tenant.view_billing', $permissions); // Admin doesn't have billing access
    }

    /**
     * Test getCurrentTenantPermissions returns permissions for member role
     */
    public function test_get_current_tenant_permissions_returns_permissions_for_member_role(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with member role
        $user->tenants()->attach($tenant->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user);

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('tenant.view_projects', $permissions);
        $this->assertContains('tenant.update_own_tasks', $permissions);
        $this->assertContains('tenant.create_tasks', $permissions);
        $this->assertNotContains('tenant.manage_members', $permissions);
        $this->assertNotContains('tenant.manage_projects', $permissions);
    }

    /**
     * Test getCurrentTenantPermissions returns permissions for viewer role
     */
    public function test_get_current_tenant_permissions_returns_permissions_for_viewer_role(): void
    {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-' . uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email_verified_at' => now(),
        ]);

        // Add user to tenant via pivot with viewer role
        $user->tenants()->attach($tenant->id, [
            'role' => 'viewer',
            'is_default' => true,
        ]);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user);

        $this->assertIsArray($permissions);
        $this->assertNotEmpty($permissions);
        $this->assertContains('tenant.view_projects', $permissions);
        $this->assertNotContains('tenant.manage_members', $permissions);
        $this->assertNotContains('tenant.manage_projects', $permissions);
        $this->assertNotContains('tenant.create_tasks', $permissions);
    }

    /**
     * Test getCurrentTenantPermissions uses session selected tenant when available
     */
    public function test_get_current_tenant_permissions_uses_session_selected_tenant(): void
    {
        $tenant1 = Tenant::factory()->create([
            'name' => 'Tenant 1',
            'slug' => 'tenant-1-' . uniqid(),
        ]);

        $tenant2 = Tenant::factory()->create([
            'name' => 'Tenant 2',
            'slug' => 'tenant-2-' . uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant1->id,
            'email_verified_at' => now(),
        ]);

        // Add user to both tenants with different roles
        $user->tenants()->attach($tenant1->id, [
            'role' => 'member',
            'is_default' => true,
        ]);

        $user->tenants()->attach($tenant2->id, [
            'role' => 'admin',
            'is_default' => false,
        ]);

        // Create request with session
        $request = \Illuminate\Http\Request::create('/test');
        $session = $this->app['session'];
        $session->put('selected_tenant_id', $tenant2->id);
        $request->setLaravelSession($session);

        $permissions = $this->tenancyService->getCurrentTenantPermissions($user, $request);

        // Should return admin permissions (from tenant2, not member permissions from tenant1)
        $this->assertContains('tenant.manage_members', $permissions);
        $this->assertContains('tenant.manage_projects', $permissions);
    }
}
