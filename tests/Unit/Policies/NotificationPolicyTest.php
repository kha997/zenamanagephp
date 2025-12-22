<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Tests\Helpers\TestDataSeeder;
use Tests\Helpers\PolicyTestHelper;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Notification;
use App\Policies\NotificationPolicy;

/**
 * Unit tests for NotificationPolicy
 * 
 * Tests tenant isolation and user ownership
 * 
 * @group notifications
 * @group policies
 */
class NotificationPolicyTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    private Tenant $tenant1;
    private Tenant $tenant2;
    private User $user1;
    private User $user2;
    private User $user3; // Different tenant
    private Notification $notification1;
    private Notification $notification2;
    private NotificationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(44444);
        $this->setDomainName('notifications');
        $this->setupDomainIsolation();
        
        // Create tenants
        $this->tenant1 = TestDataSeeder::createTenant(['name' => 'Tenant 1']);
        $this->tenant2 = TestDataSeeder::createTenant(['name' => 'Tenant 2']);
        
        // Create users
        $this->user1 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 1',
            'email' => 'user1@test.com',
        ]);
        
        $this->user2 = PolicyTestHelper::createUserWithRole($this->tenant1, 'member', [
            'name' => 'User 2',
            'email' => 'user2@test.com',
        ]);
        
        $this->user3 = PolicyTestHelper::createUserWithRole($this->tenant2, 'member', [
            'name' => 'User 3',
            'email' => 'user3@test.com',
        ]);
        
        // Create notifications
        $this->notification1 = Notification::factory()->create([
            'tenant_id' => $this->tenant1->id,
            'user_id' => $this->user1->id,
            'title' => 'Notification 1',
        ]);
        
        $this->notification2 = Notification::factory()->create([
            'tenant_id' => $this->tenant2->id,
            'user_id' => $this->user3->id,
            'title' => 'Notification 2',
        ]);
        
        // Refresh to ensure all relationships are loaded
        $this->notification1->refresh();
        $this->notification2->refresh();
        $this->user1->refresh();
        $this->user2->refresh();
        
        $this->policy = new NotificationPolicy();
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
     * Test view policy - user can view their own notifications
     */
    public function test_view_policy_user_can_view_own(): void
    {
        $this->assertTrue($this->policy->view($this->user1, $this->notification1));
    }

    /**
     * Test view policy - user cannot view others' notifications
     */
    public function test_view_policy_user_cannot_view_others(): void
    {
        $this->assertFalse($this->policy->view($this->user2, $this->notification1));
    }

    /**
     * Test view policy - different tenant cannot view
     */
    public function test_view_policy_different_tenant(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->notification2));
        $this->assertFalse($this->policy->view($this->user3, $this->notification1));
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
     * Test update policy - user can update their own notifications
     */
    public function test_update_policy_user_can_update_own(): void
    {
        $this->assertTrue($this->policy->update($this->user1, $this->notification1));
    }

    /**
     * Test update policy - user cannot update others' notifications
     */
    public function test_update_policy_user_cannot_update_others(): void
    {
        $this->assertFalse($this->policy->update($this->user2, $this->notification1));
    }

    /**
     * Test delete policy - user can delete their own notifications
     */
    public function test_delete_policy_user_can_delete_own(): void
    {
        $this->assertTrue($this->policy->delete($this->user1, $this->notification1));
    }

    /**
     * Test delete policy - user cannot delete others' notifications
     */
    public function test_delete_policy_user_cannot_delete_others(): void
    {
        $this->assertFalse($this->policy->delete($this->user2, $this->notification1));
    }

    /**
     * Test markAsRead policy - user can mark their own notifications as read
     */
    public function test_mark_as_read_policy(): void
    {
        $this->assertTrue($this->policy->markAsRead($this->user1, $this->notification1));
        $this->assertFalse($this->policy->markAsRead($this->user2, $this->notification1));
    }

    /**
     * Test markAllAsRead policy - user with tenant_id can mark all as read
     */
    public function test_mark_all_as_read_policy(): void
    {
        $this->assertTrue($this->policy->markAllAsRead($this->user1));
    }

    /**
     * Test tenant isolation
     */
    public function test_tenant_isolation(): void
    {
        $this->assertFalse($this->policy->view($this->user1, $this->notification2));
        $this->assertFalse($this->policy->update($this->user1, $this->notification2));
        $this->assertFalse($this->policy->delete($this->user1, $this->notification2));
    }
}

