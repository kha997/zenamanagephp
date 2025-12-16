<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Tests\Helpers\PolicyTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Component;
use App\Policies\ComponentPolicy;

/**
 * Unit tests for ComponentPolicy
 * 
 * Tests tenant isolation, role-based access, and owner permissions
 * 
 * @group components
 * @group policies
 */
class ComponentPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Creator/Owner
    private User $user2; // PM (can create/update)
    private User $user3; // Member (cannot create/update)
    private User $user4; // Different tenant
    private User $admin; // Admin (can delete)
    private Project $project1;
    private Component $component1;
    private Component $component2;
    private ComponentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(22222);
        $this->setDomainName('components');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users with roles
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'designer', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'project_manager', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 3',
            'email' => 'user3@test.com',
        ]);
        
        $this->user4 = PolicyTestHelper::createUserWithRole($this->tenant2, 'project_manager', [
            'name' => 'User 4',
            'email' => 'user4@test.com',
        ]);
        
        $this->admin = PolicyTestHelper::createUserWithRole($this->tenant1, 'admin', [
            'name' => 'Admin',
            'email' => 'admin@test.com',
        ]);
        
        // Create project
        $this->project1 = Project::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'owner_id' => $this->user2->id,
            'name' => 'Project 1',
        ]);
        
        // Create project for tenant2
        $project2 = Project::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'owner_id' => $this->user4->id,
            'name' => 'Project 2',
        ]);
        
        // Create components
        $this->component1 = Component::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
            'name' => 'Component 1',
        ]);
        
        $this->component2 = Component::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $project2->id,
            'created_by' => $this->user4->id,
            'name' => 'Component 2',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->component1->refresh();
        $this->component2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        $this->user3->refresh();
        $this->admin->refresh();
        
        $this->policy = new ComponentPolicy();
    }

    /**
     * Test viewAny policy - user with tenant_id can view
     */
    public function test_view_any_policy_with_tenant(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user1));
        $this->assertTrue($this->policy->viewAny($this->user2));
    }

    /**
     * Test viewAny policy - user without tenant_id cannot view
     */
    public function test_view_any_policy_without_tenant(): void
    {
        $userNoTenant = User::factory()->create(['tenant_id' => null]);
        $this->assertFalse($this->policy->viewAny($userNoTenant));
    }

    /**
     * Test view policy - same tenant with proper role can view
     */
    public function test_view_policy_same_tenant_with_role(): void
    {
        // Users with proper roles can view
        $this->assertTrue($this->policy->view($this->user1, $this->component1));
        $this->assertTrue($this->policy->view($this->user2, $this->component1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->component2));
        $this->assertFalse($this->policy->view($this->user4, $this->component1));
    }

    /**
     * Test create policy - users with proper roles can create
     */
    public function test_create_policy_with_proper_roles(): void
    {
        $this->assertTrue($this->policy->create($this->user1));
        $this->assertTrue($this->policy->create($this->user2));
        $this->assertTrue($this->policy->create($this->admin));
    }

    /**
     * Test create policy - member cannot create
     */
    public function test_create_policy_member_cannot_create(): void
    {
        $this->assertFalse($this->policy->create($this->user3));
    }

    /**
     * Test update policy - owner can update
     */
    public function test_update_policy_owner_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->component1));
    }

    /**
     * Test update policy - PM can update
     */
    public function test_update_policy_pm_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->component1));
    }

    /**
     * Test update policy - member cannot update
     */
    public function test_update_policy_member_cannot_update(): void
    {
        $this->assertFalse($this->policy->update($this->user3, $this->component1));
    }

    /**
     * Test delete policy - owner can delete
     */
    public function test_delete_policy_owner_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->component1));
    }

    /**
     * Test delete policy - admin can delete
     */
    public function test_delete_policy_admin_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->component1));
    }

    /**
     * Test delete policy - PM cannot delete
     */
    public function test_delete_policy_pm_cannot_delete(): void
    {
        $this->assertFalse($this->policy->delete($this->user2, $this->component1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->component2));
        $this->assertFalse($this->policy->update($this->user1, $this->component2));
        $this->assertFalse($this->policy->delete($this->user1, $this->component2));
    }
}

