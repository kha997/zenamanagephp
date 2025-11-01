<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Policies\ProjectPolicy;
use Illuminate\Foundation\Testing\WithFaker;

class ProjectPolicyTest extends TestCase
{
    use WithFaker;

    protected ProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ProjectPolicy();
    }

    /**
     * Test viewAny policy with mock objects
     */
    public function test_view_any_policy(): void
    {
        // User with tenant_id can view projects
        $user = $this->createMockUser('tenant-1', true);
        $this->assertTrue($this->policy->viewAny($user));
        
        // User without tenant_id cannot view projects
        $userNoTenant = $this->createMockUser(null, false);
        $this->assertFalse($this->policy->viewAny($userNoTenant));
    }

    /**
     * Test view policy with mock objects
     */
    public function test_view_policy(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-1');
        
        // User can view project in their tenant
        $this->assertTrue($this->policy->view($user, $project));
        
        // User cannot view project from different tenant
        $otherProject = $this->createMockProject('tenant-2');
        $this->assertFalse($this->policy->view($user, $otherProject));
        
        // User without tenant_id cannot view
        $userNoTenant = $this->createMockUser(null, false);
        $this->assertFalse($this->policy->view($userNoTenant, $project));
    }

    /**
     * Test create policy with mock objects
     */
    public function test_create_policy(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        
        // User with tenant_id can create projects
        $this->assertTrue($this->policy->create($user));
        
        // User without tenant_id cannot create
        $userNoTenant = $this->createMockUser(null, false);
        $this->assertFalse($this->policy->create($userNoTenant));
    }

    /**
     * Test update policy with mock objects
     */
    public function test_update_policy(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-1');
        
        // User can update project in their tenant
        $this->assertTrue($this->policy->update($user, $project));
        
        // User cannot update project from different tenant
        $otherProject = $this->createMockProject('tenant-2');
        $this->assertFalse($this->policy->update($user, $otherProject));
        
        // User without tenant_id cannot update
        $userNoTenant = $this->createMockUser(null, false);
        $this->assertFalse($this->policy->update($userNoTenant, $project));
    }

    /**
     * Test delete policy with mock objects
     */
    public function test_delete_policy(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-1');
        
        // User can delete project in their tenant
        $this->assertTrue($this->policy->delete($user, $project));
        
        // User cannot delete project from different tenant
        $otherProject = $this->createMockProject('tenant-2');
        $this->assertFalse($this->policy->delete($user, $otherProject));
        
        // User without tenant_id cannot delete
        $userNoTenant = $this->createMockUser(null, false);
        $this->assertFalse($this->policy->delete($userNoTenant, $project));
    }

    /**
     * Test policy with user without tenant
     */
    public function test_policy_with_user_without_tenant(): void
    {
        $user = $this->createMockUser(null, true);
        $project = $this->createMockProject('tenant-1');
        
        // User without tenant cannot access projects
        $this->assertFalse($this->policy->view($user, $project));
        $this->assertFalse($this->policy->update($user, $project));
        $this->assertFalse($this->policy->delete($user, $project));
    }

    /**
     * Test policy with project without tenant
     */
    public function test_policy_with_project_without_tenant(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject(null);
        
        // Project without tenant cannot be accessed
        $this->assertFalse($this->policy->view($user, $project));
        $this->assertFalse($this->policy->update($user, $project));
        $this->assertFalse($this->policy->delete($user, $project));
    }

    /**
     * Test policy with same tenant different users
     */
    public function test_policy_with_same_tenant_different_users(): void
    {
        $user1 = $this->createMockUser('tenant-1', true);
        $user2 = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-1');
        
        // Both users can access project in same tenant
        $this->assertTrue($this->policy->view($user1, $project));
        $this->assertTrue($this->policy->view($user2, $project));
        $this->assertTrue($this->policy->update($user1, $project));
        $this->assertTrue($this->policy->update($user2, $project));
    }

    /**
     * Test policy with archived project
     */
    public function test_policy_with_archived_project(): void
    {
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-1', 'archived');
        
        // User can still view archived project in their tenant
        $this->assertTrue($this->policy->view($user, $project));
        
        // User can update archived project (Policy doesn't restrict by status)
        $this->assertTrue($this->policy->update($user, $project));
        
        // User can delete archived project
        $this->assertTrue($this->policy->delete($user, $project));
    }

    /**
     * Test policy edge cases
     */
    public function test_policy_edge_cases(): void
    {
        // Test with empty tenant IDs (empty strings match, so should return true)
        $userEmptyTenant = $this->createMockUser('', true);
        $projectEmptyTenant = $this->createMockProject('');
        $this->assertTrue($this->policy->view($userEmptyTenant, $projectEmptyTenant));
        
        // Test with null tenant IDs
        $userNullTenant = $this->createMockUser(null, true);
        $projectNullTenant = $this->createMockProject(null);
        $this->assertTrue($this->policy->view($userNullTenant, $projectNullTenant));
        
        // Test tenant ID mismatch
        $user = $this->createMockUser('tenant-1', true);
        $project = $this->createMockProject('tenant-2');
        $this->assertFalse($this->policy->view($user, $project));
        
        // Test empty vs null tenant IDs
        $userEmpty = $this->createMockUser('', true);
        $projectNull = $this->createMockProject(null);
        $this->assertFalse($this->policy->view($userEmpty, $projectNull));
    }

    /**
     * Test policy performance with multiple tenants
     */
    public function test_policy_performance_with_multiple_tenants(): void
    {
        $startTime = microtime(true);
        
        // Test multiple tenant scenarios
        for ($i = 0; $i < 100; $i++) {
            $user = $this->createMockUser('tenant-' . ($i % 5), true);
            $project = $this->createMockProject('tenant-' . ($i % 5));
            
            $this->assertTrue($this->policy->view($user, $project));
            
            $otherProject = $this->createMockProject('tenant-' . (($i + 1) % 5));
            $this->assertFalse($this->policy->view($user, $otherProject));
        }
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Should complete in less than 1 second
        $this->assertLessThan(1.0, $executionTime);
    }

    /**
     * Create mock user object
     */
    private function createMockUser(?string $tenantId, bool $hasPermission): User
    {
        $user = new User();
        $user->id = 'user-' . uniqid();
        $user->tenant_id = $tenantId;
        $user->name = 'Test User';
        $user->email = 'test@example.com';
        $user->role = 'member';
        $user->is_active = true;
        
        // Mock the hasPermission method
        $user = $this->createPartialMock(User::class, ['hasPermission']);
        $user->method('hasPermission')->willReturn($hasPermission);
        $user->tenant_id = $tenantId;
        
        return $user;
    }

    /**
     * Create mock project object
     */
    private function createMockProject(?string $tenantId, string $status = 'active'): Project
    {
        $project = new Project();
        $project->id = 'project-' . uniqid();
        $project->tenant_id = $tenantId;
        $project->name = 'Test Project';
        $project->code = 'TEST-001';
        $project->status = $status;
        $project->owner_id = 'user-1';
        $project->priority = 'normal';
        $project->progress_pct = 0;
        
        return $project;
    }
}