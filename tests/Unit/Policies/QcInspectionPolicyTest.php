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
use App\Models\QcInspection;
use App\Policies\QcInspectionPolicy;

/**
 * Unit tests for QcInspectionPolicy
 * 
 * Tests tenant isolation, role-based access, and inspector permissions
 * 
 * @group qc-inspections
 * @group policies
 */
class QcInspectionPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Inspector
    private User $user2; // PM (can approve)
    private User $user3; // Different tenant
    private Project $project1;
    private QcInspection $qcInspection1;
    private QcInspection $qcInspection2;
    private QcInspectionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Temporarily disable foreign keys for SQLite to avoid FK constraint issues in tests
        $driver = \DB::getDriverName();
        if ($driver === 'sqlite') {
            \DB::statement('PRAGMA foreign_keys=OFF;');
        }
        
        // Setup domain isolation
        $this->setDomainSeed(88888);
        $this->setDomainName('qc-inspections');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'qc_engineer', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'project_manager', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant2, 'qc_engineer', [
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
        
        // Create QC plan for tenant1 (required for QC inspection)
        $qcPlan1 = QcPlan::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
        ]);
        
        // Create QC plan for tenant2
        $qcPlan2 = QcPlan::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $project2->id,
            'created_by' => $this->user3->id,
        ]);
        
        // Create QC inspections (qc_inspections table uses qc_plan_id, not project_id)
        $this->qcInspection1 = QcInspection::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'qc_plan_id' => $qcPlan1->id,
            'inspector_id' => $this->user1->id,
        ]);
        
        $this->qcInspection2 = QcInspection::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'qc_plan_id' => $qcPlan2->id,
            'inspector_id' => $this->user3->id,
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->qcInspection1->refresh();
        $this->qcInspection2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        
        $this->policy = new QcInspectionPolicy();
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
     * Test view policy - inspector can view
     */
    public function test_view_policy_inspector_can_view(): void
    {
        // Load qc_plan relationship if needed
        $this->qcInspection1->load('qcPlan.project');
        $this->assertTrue($this->policy->view($this->user1, $this->qcInspection1));
    }

    /**
     * Test view policy - project members can view
     */
    public function test_view_policy_project_members_can_view(): void
    {
        // Load qc_plan relationship if needed
        $this->qcInspection1->load('qcPlan.project');
        $this->assertTrue($this->policy->view($this->user2, $this->qcInspection1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->qcInspection2));
        $this->assertFalse($this->policy->view($this->user3, $this->qcInspection1));
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
     * Test update policy - inspector can update
     */
    public function test_update_policy_inspector_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->qcInspection1));
    }

    /**
     * Test update policy - PM can update
     */
    public function test_update_policy_pm_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->qcInspection1));
    }

    /**
     * Test approve policy - PM can approve
     */
    public function test_approve_policy_pm_can_approve(): void
    {
        $this->assertTrue($this->policy->approve($this->user2, $this->qcInspection1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->qcInspection2));
        $this->assertFalse($this->policy->update($this->user1, $this->qcInspection2));
        $this->assertFalse($this->policy->delete($this->user1, $this->qcInspection2));
    }
}

