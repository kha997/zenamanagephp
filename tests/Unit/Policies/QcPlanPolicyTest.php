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
use App\Models\QcPlan;
use App\Policies\QcPlanPolicy;

/**
 * Unit tests for QcPlanPolicy
 * 
 * Tests tenant isolation, role-based access, and creator permissions
 * 
 * @group qc-plans
 * @group policies
 */
class QcPlanPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Creator
    private User $user2; // PM (can approve)
    private User $user3; // Different tenant
    private Project $project1;
    private QcPlan $qcPlan1;
    private QcPlan $qcPlan2;
    private QcPlanPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(77777);
        $this->setDomainName('qc-plans');
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
        
        // Create QC plans
        $this->qcPlan1 = QcPlan::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
        ]);
        
        $this->qcPlan2 = QcPlan::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $project2->id,
            'created_by' => $this->user3->id,
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->qcPlan1->refresh();
        $this->qcPlan2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        
        $this->policy = new QcPlanPolicy();
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
        $this->qcPlan1->load('project');
        $this->assertTrue($this->policy->view($this->user1, $this->qcPlan1));
    }

    /**
     * Test view policy - project members can view
     */
    public function test_view_policy_project_members_can_view(): void
    {
        // Load project relationship if needed
        $this->qcPlan1->load('project');
        $this->assertTrue($this->policy->view($this->user2, $this->qcPlan1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->qcPlan2));
        $this->assertFalse($this->policy->view($this->user3, $this->qcPlan1));
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
     * Test update policy - creator can update
     */
    public function test_update_policy_creator_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->qcPlan1));
    }

    /**
     * Test update policy - PM can update
     */
    public function test_update_policy_pm_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->qcPlan1));
    }

    /**
     * Test approve policy - PM can approve
     */
    public function test_approve_policy_pm_can_approve(): void
    {
        $this->assertTrue($this->policy->approve($this->user2, $this->qcPlan1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->qcPlan2));
        $this->assertFalse($this->policy->update($this->user1, $this->qcPlan2));
        $this->assertFalse($this->policy->delete($this->user1, $this->qcPlan2));
    }
}

