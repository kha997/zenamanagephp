<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\ChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ChangeRequestPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $admin;
    protected $pm;
    protected $designer;
    protected $engineer;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->superAdmin = User::factory()->create(['role' => 'super_admin', 'tenant_id' => 1]);
        $this->admin = User::factory()->create(['role' => 'admin', 'tenant_id' => 1]);
        $this->pm = User::factory()->create(['role' => 'pm', 'tenant_id' => 1]);
        $this->designer = User::factory()->create(['role' => 'designer', 'tenant_id' => 1]);
        $this->engineer = User::factory()->create(['role' => 'engineer', 'tenant_id' => 1]);
        $this->regularUser = User::factory()->create(['role' => 'user', 'tenant_id' => 1]);
    }

    /** @test */
    public function change_request_policy_allows_proper_access()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $changeRequest));
        $this->assertTrue($this->superAdmin->can('create', ChangeRequest::class));
        $this->assertTrue($this->superAdmin->can('update', $changeRequest));
        $this->assertTrue($this->superAdmin->can('delete', $changeRequest));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $changeRequest));
        $this->assertTrue($this->admin->can('create', ChangeRequest::class));
        $this->assertTrue($this->admin->can('update', $changeRequest));
        $this->assertFalse($this->admin->can('delete', $changeRequest));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $changeRequest));
        $this->assertTrue($this->pm->can('create', ChangeRequest::class));
        $this->assertTrue($this->pm->can('update', $changeRequest));
        $this->assertFalse($this->pm->can('delete', $changeRequest));
        
        // Designer can view and create
        $this->assertTrue($this->designer->can('view', $changeRequest));
        $this->assertTrue($this->designer->can('create', ChangeRequest::class));
        $this->assertTrue($this->designer->can('update', $changeRequest));
        $this->assertTrue($this->designer->can('delete', $changeRequest)); // Creator can delete
        
        // Engineer can view and create
        $this->assertTrue($this->engineer->can('view', $changeRequest));
        $this->assertTrue($this->engineer->can('create', ChangeRequest::class));
        $this->assertFalse($this->engineer->can('update', $changeRequest));
        $this->assertFalse($this->engineer->can('delete', $changeRequest));
    }

    /** @test */
    public function creator_can_update_and_delete_their_change_request()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // Creator can update and delete
        $this->assertTrue($this->designer->can('update', $changeRequest));
        $this->assertTrue($this->designer->can('delete', $changeRequest));
        
        // Non-creator cannot update or delete
        $this->assertFalse($this->engineer->can('update', $changeRequest));
        $this->assertFalse($this->engineer->can('delete', $changeRequest));
    }

    /** @test */
    public function management_can_approve_and_reject_change_requests()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // Management can approve and reject
        $this->assertTrue($this->superAdmin->can('approve', $changeRequest));
        $this->assertTrue($this->superAdmin->can('reject', $changeRequest));
        $this->assertTrue($this->admin->can('approve', $changeRequest));
        $this->assertTrue($this->admin->can('reject', $changeRequest));
        $this->assertTrue($this->pm->can('approve', $changeRequest));
        $this->assertTrue($this->pm->can('reject', $changeRequest));
        
        // Non-management cannot approve or reject
        $this->assertFalse($this->designer->can('approve', $changeRequest));
        $this->assertFalse($this->designer->can('reject', $changeRequest));
        $this->assertFalse($this->engineer->can('approve', $changeRequest));
        $this->assertFalse($this->engineer->can('reject', $changeRequest));
    }

    /** @test */
    public function team_members_can_comment_on_change_requests()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // All team members can comment
        $this->assertTrue($this->superAdmin->can('comment', $changeRequest));
        $this->assertTrue($this->admin->can('comment', $changeRequest));
        $this->assertTrue($this->pm->can('comment', $changeRequest));
        $this->assertTrue($this->designer->can('comment', $changeRequest));
        $this->assertTrue($this->engineer->can('comment', $changeRequest));
        
        // Regular users cannot comment
        $this->assertFalse($this->regularUser->can('comment', $changeRequest));
    }

    /** @test */
    public function management_can_assign_change_requests()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // Management can assign
        $this->assertTrue($this->superAdmin->can('assign', $changeRequest));
        $this->assertTrue($this->admin->can('assign', $changeRequest));
        $this->assertTrue($this->pm->can('assign', $changeRequest));
        
        // Non-management cannot assign
        $this->assertFalse($this->designer->can('assign', $changeRequest));
        $this->assertFalse($this->engineer->can('assign', $changeRequest));
    }

    /** @test */
    public function management_can_close_change_requests()
    {
        $changeRequest = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        // Management can close
        $this->assertTrue($this->superAdmin->can('close', $changeRequest));
        $this->assertTrue($this->admin->can('close', $changeRequest));
        $this->assertTrue($this->pm->can('close', $changeRequest));
        
        // Non-management cannot close
        $this->assertFalse($this->designer->can('close', $changeRequest));
        $this->assertFalse($this->engineer->can('close', $changeRequest));
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_change_request_access()
    {
        $changeRequest1 = ChangeRequest::factory()->create([
            'tenant_id' => 1,
            'created_by' => $this->designer->id
        ]);
        
        $changeRequest2 = ChangeRequest::factory()->create([
            'tenant_id' => 2,
            'created_by' => $this->designer->id
        ]);
        
        $user1 = User::factory()->create(['tenant_id' => 1, 'role' => 'admin']);
        $user2 = User::factory()->create(['tenant_id' => 2, 'role' => 'admin']);
        
        // User 1 can access tenant 1 change requests
        $this->assertTrue($user1->can('view', $changeRequest1));
        $this->assertTrue($user1->can('update', $changeRequest1));
        
        // User 1 cannot access tenant 2 change requests
        $this->assertFalse($user1->can('view', $changeRequest2));
        $this->assertFalse($user1->can('update', $changeRequest2));
        
        // User 2 can access tenant 2 change requests
        $this->assertTrue($user2->can('view', $changeRequest2));
        $this->assertTrue($user2->can('update', $changeRequest2));
        
        // User 2 cannot access tenant 1 change requests
        $this->assertFalse($user2->can('view', $changeRequest1));
        $this->assertFalse($user2->can('update', $changeRequest1));
    }
}
