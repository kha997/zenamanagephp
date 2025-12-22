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
use App\Models\Rfi;
use App\Policies\RfiPolicy;

/**
 * Unit tests for RfiPolicy
 * 
 * Tests tenant isolation, role-based access, and creator permissions
 * 
 * @group rfi
 * @group policies
 */
class RfiPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Creator
    private User $user2; // PM (can answer)
    private User $user3; // Different tenant
    private Project $project1;
    private Rfi $rfi1;
    private Rfi $rfi2;
    private RfiPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(66666);
        $this->setDomainName('rfi');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'project_manager', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant2, 'member', [
            'name' => 'User 3',
            'email' => 'user3@test.com',
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
            'owner_id' => $this->user3->id,
            'name' => 'Project 2',
        ]);
        
        // Create RFIs
        $this->rfi1 = Rfi::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
            'status' => 'open',
        ]);
        
        $this->rfi2 = Rfi::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $project2->id,
            'created_by' => $this->user3->id,
            'status' => 'open',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->rfi1->refresh();
        $this->rfi2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        
        $this->policy = new RfiPolicy();
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
     * Test view policy - creator can view
     */
    public function test_view_policy_creator_can_view(): void
    {
        // Load project relationship if needed
        $this->rfi1->load('project');
        $this->assertTrue($this->policy->view($this->user1, $this->rfi1));
    }

    /**
     * Test view policy - project members can view
     */
    public function test_view_policy_project_members_can_view(): void
    {
        // Load project relationship if needed
        $this->rfi1->load('project');
        $this->assertTrue($this->policy->view($this->user2, $this->rfi1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->rfi2));
        $this->assertFalse($this->policy->view($this->user3, $this->rfi1));
    }

    /**
     * Test create policy - user with tenant_id can create
     */
    public function test_create_policy_with_tenant(): void
    {
        $this->assertTrue($this->policy->create($this->user1));
        $this->assertTrue($this->policy->create($this->user2));
    }

    /**
     * Test update policy - creator can update if not answered
     */
    public function test_update_policy_creator_can_update_if_not_answered(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->rfi1));
    }

    /**
     * Test update policy - creator cannot update if answered
     */
    public function test_update_policy_creator_cannot_update_if_answered(): void
    {
        $this->rfi1->update(['status' => 'answered']);
        $this->rfi1->refresh();
        $this->assertFalse($this->policy->update($this->user1, $this->rfi1));
    }

    /**
     * Test answer policy - PM can answer
     */
    public function test_answer_policy_pm_can_answer(): void
    {
        $this->assertTrue($this->policy->answer($this->user2, $this->rfi1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->rfi2));
        $this->assertFalse($this->policy->update($this->user1, $this->rfi2));
        $this->assertFalse($this->policy->delete($this->user1, $this->rfi2));
    }
}

