<?php

namespace Tests\Feature\Policies;

use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;

class NotificationPolicyTest extends TestCase
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
    public function notification_policy_allows_proper_access()
    {
        $notification = Notification::factory()->create([
            'tenant_id' => 1,
            'user_id' => $this->designer->id
        ]);
        
        // Super admin can do everything
        $this->assertTrue($this->superAdmin->can('view', $notification));
        $this->assertTrue($this->superAdmin->can('create', Notification::class));
        $this->assertTrue($this->superAdmin->can('update', $notification));
        $this->assertTrue($this->superAdmin->can('delete', $notification));
        
        // Admin can do most things
        $this->assertTrue($this->admin->can('view', $notification));
        $this->assertTrue($this->admin->can('create', Notification::class));
        $this->assertTrue($this->admin->can('update', $notification));
        $this->assertTrue($this->admin->can('delete', $notification));
        
        // PM can view and create
        $this->assertTrue($this->pm->can('view', $notification));
        $this->assertTrue($this->pm->can('create', Notification::class));
        $this->assertTrue($this->pm->can('update', $notification));
        $this->assertTrue($this->pm->can('delete', $notification));
        
        // Designer can view and create
        $this->assertTrue($this->designer->can('view', $notification));
        $this->assertTrue($this->designer->can('create', Notification::class));
        $this->assertTrue($this->designer->can('update', $notification));
        $this->assertTrue($this->designer->can('delete', $notification));
        
        // Engineer can view and create
        $this->assertTrue($this->engineer->can('view', $notification));
        $this->assertTrue($this->engineer->can('create', Notification::class));
        $this->assertTrue($this->engineer->can('update', $notification));
        $this->assertTrue($this->engineer->can('delete', $notification));
    }

    /** @test */
    public function users_can_only_access_their_own_notifications()
    {
        $notification1 = Notification::factory()->create([
            'tenant_id' => 1,
            'user_id' => $this->designer->id
        ]);
        
        $notification2 = Notification::factory()->create([
            'tenant_id' => 1,
            'user_id' => $this->engineer->id
        ]);
        
        // Designer can access their own notification
        $this->assertTrue($this->designer->can('view', $notification1));
        $this->assertTrue($this->designer->can('update', $notification1));
        $this->assertTrue($this->designer->can('delete', $notification1));
        
        // Designer cannot access engineer's notification
        $this->assertFalse($this->designer->can('view', $notification2));
        $this->assertFalse($this->designer->can('update', $notification2));
        $this->assertFalse($this->designer->can('delete', $notification2));
        
        // Engineer can access their own notification
        $this->assertTrue($this->engineer->can('view', $notification2));
        $this->assertTrue($this->engineer->can('update', $notification2));
        $this->assertTrue($this->engineer->can('delete', $notification2));
        
        // Engineer cannot access designer's notification
        $this->assertFalse($this->engineer->can('view', $notification1));
        $this->assertFalse($this->engineer->can('update', $notification1));
        $this->assertFalse($this->engineer->can('delete', $notification1));
    }

    /** @test */
    public function tenant_isolation_prevents_cross_tenant_notification_access()
    {
        $notification1 = Notification::factory()->create([
            'tenant_id' => 1,
            'user_id' => $this->designer->id
        ]);
        
        $notification2 = Notification::factory()->create([
            'tenant_id' => 2,
            'user_id' => $this->designer->id
        ]);
        
        $user1 = User::factory()->create(['tenant_id' => 1, 'role' => 'admin']);
        $user2 = User::factory()->create(['tenant_id' => 2, 'role' => 'admin']);
        
        // User 1 can access tenant 1 notifications
        $this->assertTrue($user1->can('view', $notification1));
        $this->assertTrue($user1->can('update', $notification1));
        
        // User 1 cannot access tenant 2 notifications
        $this->assertFalse($user1->can('view', $notification2));
        $this->assertFalse($user1->can('update', $notification2));
        
        // User 2 can access tenant 2 notifications
        $this->assertTrue($user2->can('view', $notification2));
        $this->assertTrue($user2->can('update', $notification2));
        
        // User 2 cannot access tenant 1 notifications
        $this->assertFalse($user2->can('view', $notification1));
        $this->assertFalse($user2->can('update', $notification1));
    }

    /** @test */
    public function notification_mark_as_read_unread_permissions()
    {
        $notification = Notification::factory()->create([
            'tenant_id' => 1,
            'user_id' => $this->designer->id
        ]);
        
        // User can mark their own notifications as read/unread
        $this->assertTrue($this->designer->can('markAsRead', $notification));
        $this->assertTrue($this->designer->can('markAsUnread', $notification));
        
        // Other users cannot mark notifications as read/unread
        $this->assertFalse($this->engineer->can('markAsRead', $notification));
        $this->assertFalse($this.engineer->can('markAsUnread', $notification));
    }

    /** @test */
    public function notification_send_permissions()
    {
        // Only management can send notifications
        $this->assertTrue($this->superAdmin->can('send', Notification::class));
        $this->assertTrue($this->admin->can('send', Notification::class));
        $this->assertTrue($this->pm->can('send', Notification::class));
        
        // Regular users cannot send notifications
        $this->assertFalse($this->designer->can('send', Notification::class));
        $this->assertFalse($this->engineer->can('send', Notification::class));
        $this->assertFalse($this->regularUser->can('send', Notification::class));
    }

    /** @test */
    public function notification_manage_settings_permissions()
    {
        // Only super admin and admin can manage notification settings
        $this->assertTrue($this->superAdmin->can('manageSettings', Notification::class));
        $this->assertTrue($this->admin->can('manageSettings', Notification::class));
        
        // Others cannot manage settings
        $this->assertFalse($this->pm->can('manageSettings', Notification::class));
        $this->assertFalse($this->designer->can('manageSettings', Notification::class));
        $this->assertFalse($this->engineer->can('manageSettings', Notification::class));
        $this->assertFalse($this->regularUser->can('manageSettings', Notification::class));
    }
}
