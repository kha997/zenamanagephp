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
use App\Models\Document;
use App\Models\Role;
use App\Policies\DocumentPolicy;
use Illuminate\Support\Facades\Hash;

/**
 * Unit tests for DocumentPolicy
 * 
 * Tests tenant isolation, role-based access, and owner permissions
 * 
 * @group documents
 * @group policies
 */
class DocumentPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Owner/Uploader
    private User $user2; // PM (can create/update)
    private User $user3; // Member (can view only)
    private User $user4; // Different tenant
    private User $admin; // Admin (can delete others' documents)
    private Project $project1;
    private Document $document1;
    private Document $document2;
    private DocumentPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        // Note: This is a workaround - in production, FK constraints should be enabled
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(11111);
        $this->setDomainName('documents');
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
        
        // Refresh projects to ensure they're saved
        $this->project1->refresh();
        $project2->refresh();
        
        // Create documents
        $this->document1 = Document::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'uploaded_by' => $this->user1->id,
            'name' => 'Document 1',
        ]);
        
        $this->document2 = Document::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $project2->id,
            'uploaded_by' => $this->user4->id,
            'name' => 'Document 2',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->document1->refresh();
        $this->document2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        $this->user3->refresh();
        $this->admin->refresh();
        
        $this->policy = new DocumentPolicy();
    }

    /**
     * Test viewAny policy - user with tenant_id can view
     */
    public function test_view_any_policy_with_tenant(): void
    {
        $this->assertTrue($this->policy->viewAny($this->user1));
        $this->assertTrue($this->policy->viewAny($this->user2));
        $this->assertTrue($this->policy->viewAny($this->user3));
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
     * Test view policy - same tenant can view
     */
    public function test_view_policy_same_tenant(): void
    {
        // Owner can view
        $this->assertTrue($this->policy->view($this->user1, $this->document1));
        
        // Any tenant user can view (policy allows all tenant users)
        $this->assertTrue($this->policy->view($this->user2, $this->document1));
        $this->assertTrue($this->policy->view($this->user3, $this->document1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->document2));
        $this->assertFalse($this->policy->view($this->user4, $this->document1));
    }

    /**
     * Test create policy - users with proper roles can create
     */
    public function test_create_policy_with_proper_roles(): void
    {
        // PM can create
        $this->assertTrue($this->policy->create($this->user2));
        
        // Designer can create
        $this->assertTrue($this->policy->create($this->user1));
        
        // Admin can create
        $this->assertTrue($this->policy->create($this->admin));
    }

    /**
     * Test create policy - member cannot create
     */
    public function test_create_policy_member_cannot_create(): void
    {
        // Member cannot create (needs proper role)
        // hasAnyRole checks roles relationship
        // Member role is not in the allowed list
        $this->assertFalse($this->policy->create($this->user3));
    }

    /**
     * Test create policy - user without tenant_id cannot create
     */
    public function test_create_policy_without_tenant(): void
    {
        $userNoTenant = User::factory()->create(['tenant_id' => null]);
        $this->assertFalse($this->policy->create($userNoTenant));
    }

    /**
     * Test update policy - owner can update
     */
    public function test_update_policy_owner_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->document1));
    }

    /**
     * Test update policy - PM can update
     */
    public function test_update_policy_pm_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->document1));
    }

    /**
     * Test update policy - member cannot update
     */
    public function test_update_policy_member_cannot_update(): void
    {
        // Member without proper role cannot update
        // hasAnyRole checks roles relationship
        // Member role is not in the allowed list
        $this->assertFalse($this->policy->update($this->user3, $this->document1));
    }

    /**
     * Test update policy - different tenant cannot update
     */
    public function test_update_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->update($this->user1, $this->document2));
        $this->assertFalse($this->policy->update($this->user4, $this->document1));
    }

    /**
     * Test delete policy - owner can delete
     */
    public function test_delete_policy_owner_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->document1));
    }

    /**
     * Test delete policy - admin can delete others' documents
     */
    public function test_delete_policy_admin_can_delete_others(): void
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->document1));
    }

    /**
     * Test delete policy - PM cannot delete others' documents
     */
    public function test_delete_policy_pm_cannot_delete_others(): void
    {
        // PM cannot delete others' documents (only admin can)
        // hasAnyRole checks roles relationship
        // project_manager is not in the allowed list for delete (only super_admin, admin)
        $this->assertFalse($this->policy->delete($this->user2, $this->document1));
    }

    /**
     * Test delete policy - different tenant cannot delete
     */
    public function test_delete_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->delete($this->user1, $this->document2));
        $this->assertFalse($this->policy->delete($this->user4, $this->document1));
    }

    /**
     * Test approve policy - management roles can approve
     */
    public function test_approve_policy_management_can_approve(): void
    {
        $this->assertTrue($this->policy->approve($this->user2, $this->document1));
        $this->assertTrue($this->policy->approve($this->admin, $this->document1));
    }

    /**
     * Test approve policy - different tenant cannot approve
     */
    public function test_approve_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->approve($this->user4, $this->document1));
    }

    /**
     * Test tenant isolation - comprehensive
     */
    public function test_tenant_isolation(): void
    {
        // User from tenant1 cannot access document from tenant2
        $this->assertFalse($this->policy->view($this->user1, $this->document2));
        $this->assertFalse($this->policy->update($this->user1, $this->document2));
        $this->assertFalse($this->policy->delete($this->user1, $this->document2));
        
        // User from tenant2 cannot access document from tenant1
        $this->assertFalse($this->policy->view($this->user4, $this->document1));
        $this->assertFalse($this->policy->update($this->user4, $this->document1));
        $this->assertFalse($this->policy->delete($this->user4, $this->document1));
        
        // Users from same tenant can access
        $this->assertTrue($this->policy->view($this->user1, $this->document1));
        $this->assertTrue($this->policy->view($this->user4, $this->document2));
    }

    /**
     * Test download policy - same as view
     */
    public function test_download_policy(): void
    {
        $this->assertTrue($this->policy->download($this->user1, $this->document1));
        $this->assertFalse($this->policy->download($this->user1, $this->document2));
    }

    /**
     * Test share policy - same as update
     */
    public function test_share_policy(): void
    {
        $this->assertTrue($this->policy->share($this->user1, $this->document1));
        $this->assertTrue($this->policy->share($this->user2, $this->document1));
    }

    /**
     * Test restore policy - same as update
     */
    public function test_restore_policy(): void
    {
        $this->assertTrue($this->policy->restore($this->user1, $this->document1));
        $this->assertTrue($this->policy->restore($this->user2, $this->document1));
    }

    /**
     * Test forceDelete policy - same as delete
     */
    public function test_force_delete_policy(): void
    {
        $this->assertTrue($this->policy->forceDelete($this->user1, $this->document1));
        $this->assertTrue($this->policy->forceDelete($this->admin, $this->document1));
    }
}

