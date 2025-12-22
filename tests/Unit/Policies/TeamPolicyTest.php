<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Tests\Helpers\PolicyTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Team;
use App\Policies\TeamPolicy;

/**
 * Unit tests for TeamPolicy
 * 
 * Tests tenant isolation, role-based access, and leader/owner permissions
 * 
 * @group teams
 * @group policies
 */
class TeamPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Leader
    private User $user2; // Creator/Owner
    private User $user3; // PM (can create)
    private User $user4; // Different tenant
    private User $admin; // Admin (can delete)
    private Team $team1;
    private Team $team2;
    private TeamPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(33333);
        $this->setDomainName('teams');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users with roles
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'team_lead', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'project_manager', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant1, 'project_manager', [
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
        
        // Create teams (teams table uses team_lead_id, not leader_id)
        $this->team1 = Team::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'team_lead_id' => $this->user1->id,
            'created_by' => $this->user2->id,
            'name' => 'Team 1',
        ]);
        
        $this->team2 = Team::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'team_lead_id' => $this->user4->id,
            'created_by' => $this->user4->id,
            'name' => 'Team 2',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->team1->refresh();
        $this->team2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        $this->user3->refresh();
        $this->admin->refresh();
        
        $this->policy = new TeamPolicy();
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
     * Test view policy - same tenant with proper role can view
     */
    public function test_view_policy_same_tenant_with_role(): void
    {
        $this->assertTrue($this->policy->view($this->user1, $this->team1));
        $this->assertTrue($this->policy->view($this->user2, $this->team1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->team2));
        $this->assertFalse($this->policy->view($this->user4, $this->team1));
    }

    /**
     * Test create policy - users with proper roles can create
     */
    public function test_create_policy_with_proper_roles(): void
    {
        $this->assertTrue($this->policy->create($this->user2));
        $this->assertTrue($this->policy->create($this->user3));
        $this->assertTrue($this->policy->create($this->admin));
    }

    /**
     * Test update policy - leader can update
     */
    public function test_update_policy_leader_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->team1));
    }

    /**
     * Test update policy - owner can update
     */
    public function test_update_policy_owner_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->team1));
    }

    /**
     * Test update policy - other user cannot update
     */
    public function test_update_policy_other_user_cannot_update(): void
    {
        $this->assertFalse($this->policy->update($this->user3, $this->team1));
    }

    /**
     * Test delete policy - leader can delete
     */
    public function test_delete_policy_leader_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->team1));
    }

    /**
     * Test delete policy - owner can delete
     */
    public function test_delete_policy_owner_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user2, $this->team1));
    }

    /**
     * Test delete policy - admin can delete
     */
    public function test_delete_policy_admin_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->team1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->team2));
        $this->assertFalse($this->policy->update($this->user1, $this->team2));
        $this->assertFalse($this->policy->delete($this->user1, $this->team2));
    }
}

