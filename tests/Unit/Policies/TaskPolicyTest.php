<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Policies\TaskPolicy;
use Illuminate\Support\Facades\Hash;

/**
 * Unit tests for TaskPolicy
 * 
 * Tests tenant isolation, role-based access, and owner/creator permissions
 * 
 * @group tasks
 * @group policies
 */
class TaskPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1; // Creator/Owner
    private User $user2; // Assignee
    private User $user3; // Different tenant
    private Project $project1;
    private Project $project2;
    private Task $task1;
    private Task $task2;
    private TaskPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(12345);
        $this->setDomainName('tasks');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->user1 = TestDataSeeder::createUser($this->tenant1, [
            'name' => 'User 1',
            'email' => 'user1@test.com',
            'role' => 'pm',
        ]);
        
        $this->user2 = TestDataSeeder::createUser($this->tenant1, [
            'name' => 'User 2',
            'email' => 'user2@test.com',
            'role' => 'member',
        ]);
        
        $this->user3 = TestDataSeeder::createUser($this->tenant2, [
            'name' => 'User 3',
            'email' => 'user3@test.com',
            'role' => 'pm',
        ]);
        
        // Create projects
        $this->project1 = Project::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'owner_id' => $this->user1->id,
            'name' => 'Project 1',
        ]);
        
        $this->project2 = Project::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'owner_id' => $this->user3->id,
            'name' => 'Project 2',
        ]);
        
        // Create tasks
        $this->task1 = Task::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
            'assignee_id' => $this->user2->id,
            'name' => 'Task 1',
            'status' => 'in_progress',
        ]);
        
        $this->task2 = Task::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'project_id' => $this->project2->id,
            'created_by' => $this->user3->id,
            'assignee_id' => null,
            'name' => 'Task 2',
            'status' => 'todo',
        ]);
        
        $this->policy = new TaskPolicy();
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
     * Test view policy - same tenant can view
     */
    public function test_view_policy_same_tenant(): void
    {
        // Creator can view
        $this->assertTrue($this->policy->view($this->user1, $this->task1));
        
        // Assignee can view
        $this->assertTrue($this->policy->view($this->user2, $this->task1));
        
        // Any tenant user can view (policy allows all tenant users)
        $user4 = TestDataSeeder::createUser($this->tenant1, ['role' => 'member']);
        $this->assertTrue($this->policy->view($user4, $this->task1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->task2));
        $this->assertFalse($this->policy->view($this->user3, $this->task1));
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
     * Test create policy - user without tenant_id cannot create
     */
    public function test_create_policy_without_tenant(): void
    {
        $userNoTenant = User::factory()->create(['tenant_id' => null]);
        $this->assertFalse($this->policy->create($userNoTenant));
    }

    /**
     * Test update policy - creator can update
     */
    public function test_update_policy_creator_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->task1));
    }

    /**
     * Test update policy - assignee can update
     */
    public function test_update_policy_assignee_can_update(): void
    {
        $this->assertTrue($this->policy->update($this->user2, $this->task1));
    }

    /**
     * Test update policy - other user cannot update
     */
    public function test_update_policy_other_user_cannot_update(): void
    {
        $user4 = TestDataSeeder::createUser($this->tenant1, ['role' => 'member']);
        $this->assertFalse($this->policy->update($user4, $this->task1));
    }

    /**
     * Test update policy - different tenant cannot update
     */
    public function test_update_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->update($this->user1, $this->task2));
        $this->assertFalse($this->policy->update($this->user3, $this->task1));
    }

    /**
     * Test delete policy - creator can delete
     */
    public function test_delete_policy_creator_can_delete(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->task1));
    }

    /**
     * Test delete policy - assignee cannot delete
     */
    public function test_delete_policy_assignee_cannot_delete(): void
    {
        $this->assertFalse($this->policy->delete($this->user2, $this->task1));
    }

    /**
     * Test delete policy - other user cannot delete
     */
    public function test_delete_policy_other_user_cannot_delete(): void
    {
        $user4 = TestDataSeeder::createUser($this->tenant1, ['role' => 'member']);
        $this->assertFalse($this->policy->delete($user4, $this->task1));
    }

    /**
     * Test delete policy - different tenant cannot delete
     */
    public function test_delete_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->delete($this->user1, $this->task2));
        $this->assertFalse($this->policy->delete($this->user3, $this->task1));
    }

    /**
     * Test tenant isolation - comprehensive
     */
    public function test_tenant_isolation(): void
    {
        // User from tenant1 cannot access task from tenant2
        $this->assertFalse($this->policy->view($this->user1, $this->task2));
        $this->assertFalse($this->policy->update($this->user1, $this->task2));
        $this->assertFalse($this->policy->delete($this->user1, $this->task2));
        
        // User from tenant2 cannot access task from tenant1
        $this->assertFalse($this->policy->view($this->user3, $this->task1));
        $this->assertFalse($this->policy->update($this->user3, $this->task1));
        $this->assertFalse($this->policy->delete($this->user3, $this->task1));
        
        // Users from same tenant can access
        $this->assertTrue($this->policy->view($this->user1, $this->task1));
        $this->assertTrue($this->policy->view($this->user3, $this->task2));
    }

    /**
     * Test edge case - task without created_by
     */
    public function test_task_without_created_by(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => null,
            'assignee_id' => $this->user2->id,
        ]);
        
        // Assignee can still view and update
        $this->assertTrue($this->policy->view($this->user2, $task));
        $this->assertTrue($this->policy->update($this->user2, $task));
        
        // But cannot delete (no creator)
        $this->assertFalse($this->policy->delete($this->user2, $task));
    }

    /**
     * Test edge case - task without assignee
     */
    public function test_task_without_assignee(): void
    {
        $task = Task::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'project_id' => $this->project1->id,
            'created_by' => $this->user1->id,
            'assignee_id' => null,
        ]);
        
        // Creator can view, update, delete
        $this->assertTrue($this->policy->view($this->user1, $task));
        $this->assertTrue($this->policy->update($this->user1, $task));
        $this->assertTrue($this->policy->delete($this->user1, $task));
        
        // Other user can view but not update/delete
        $user4 = TestDataSeeder::createUser($this->tenant1, ['role' => 'member']);
        $this->assertTrue($this->policy->view($user4, $task));
        $this->assertFalse($this->policy->update($user4, $task));
        $this->assertFalse($this->policy->delete($user4, $task));
    }
}

