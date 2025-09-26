<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Services\NotificationService;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Test Notification System
 * 
 * Kịch bản: Test hệ thống thông báo với các chức năng chính
 * - Tạo và gửi thông báo
 * - Quản lý trạng thái đọc/chưa đọc
 * - Lọc và phân trang thông báo
 * - Xử lý các kênh thông báo khác nhau
 * - Multi-tenant isolation
 * - Bulk operations
 */
class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    private $tenant;
    private $user;
    private $project;
    private $task;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Set cache driver to array for testing
        config(['cache.default' => 'array']);
        Cache::flush();
        
        // Disable foreign key constraints for testing
        \DB::statement('PRAGMA foreign_keys=OFF;');
        
        // Tạo tenant
        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'status' => 'active',
        ]);

        // Tạo user
        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        // Tạo project
        $this->project = Project::create([
            'name' => 'Test Project',
            'code' => 'TEST001',
            'description' => 'Test project description',
            'status' => 'active',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        // Tạo task
        $this->task = Task::create([
            'name' => 'Test Task',
            'description' => 'Test task description',
            'status' => 'open',
            'priority' => 'medium',
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'assigned_to' => $this->user->id,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Test tạo notification cơ bản
     */
    public function test_can_create_basic_notification(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned a new task',
            'channel' => 'inapp',
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned a new task',
            'channel' => 'inapp',
        ]);

        $this->assertNull($notification->read_at);
        $this->assertFalse($notification->isRead());
    }

    /**
     * Test tạo notification với các priority khác nhau
     */
    public function test_can_create_notifications_with_different_priorities(): void
    {
        $priorities = ['critical', 'normal', 'low'];
        
        foreach ($priorities as $priority) {
            $notification = Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'system_alert',
                'priority' => $priority,
                'title' => ucfirst($priority) . ' Alert',
                'body' => 'This is a ' . $priority . ' priority notification',
                'channel' => 'inapp',
            ]);

            $this->assertEquals($priority, $notification->priority);
            
            if ($priority === 'critical') {
                $this->assertTrue($notification->isCritical());
            } else {
                $this->assertFalse($notification->isCritical());
            }
        }
    }

    /**
     * Test tạo notification với các channel khác nhau
     */
    public function test_can_create_notifications_with_different_channels(): void
    {
        $channels = ['inapp', 'email', 'webhook'];
        
        foreach ($channels as $channel) {
            $notification = Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'project_update',
                'priority' => 'normal',
                'title' => 'Project Update',
                'body' => 'Project has been updated',
                'channel' => $channel,
                'link_url' => 'https://example.com/project/' . $this->project->id,
            ]);

            $this->assertEquals($channel, $notification->channel);
            $this->assertNotNull($notification->link_url);
        }
    }

    /**
     * Test đánh dấu notification là đã đọc
     */
    public function test_can_mark_notification_as_read(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned a new task',
            'channel' => 'inapp',
        ]);

        $this->assertFalse($notification->isRead());
        
        $result = $notification->markAsRead();
        $this->assertTrue($result);
        
        $notification->refresh();
        $this->assertTrue($notification->isRead());
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test đánh dấu notification là chưa đọc
     */
    public function test_can_mark_notification_as_unread(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned a new task',
            'channel' => 'inapp',
            'read_at' => now(),
        ]);

        $this->assertTrue($notification->isRead());
        
        $result = $notification->markAsUnread();
        $this->assertTrue($result);
        
        $notification->refresh();
        $this->assertFalse($notification->isRead());
        $this->assertNull($notification->read_at);
    }

    /**
     * Test lấy số lượng notification chưa đọc
     */
    public function test_can_get_unread_notification_count(): void
    {
        // Tạo 5 notifications, 3 chưa đọc, 2 đã đọc
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "Task {$i}",
                'body' => "Task {$i} description",
                'channel' => 'inapp',
                'read_at' => $i > 3 ? now() : null, // 3 chưa đọc, 2 đã đọc
            ]);
        }

        $unreadCount = Notification::getUnreadCount($this->user->id);
        $this->assertEquals(3, $unreadCount);
    }

    /**
     * Test đánh dấu tất cả notifications là đã đọc
     */
    public function test_can_mark_all_notifications_as_read(): void
    {
        // Tạo 5 notifications chưa đọc
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "Task {$i}",
                'body' => "Task {$i} description",
                'channel' => 'inapp',
            ]);
        }

        $updatedCount = Notification::markAllAsReadForUser($this->user->id);
        $this->assertEquals(5, $updatedCount);

        $unreadCount = Notification::getUnreadCount($this->user->id);
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test lọc notifications theo user
     */
    public function test_can_filter_notifications_by_user(): void
    {
        // Tạo user khác
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        // Tạo notifications cho cả 2 users
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "User 1 Task {$i}",
                'body' => "Task {$i} for user 1",
                'channel' => 'inapp',
            ]);

            Notification::create([
                'user_id' => $otherUser->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "User 2 Task {$i}",
                'body' => "Task {$i} for user 2",
                'channel' => 'inapp',
            ]);
        }

        $user1Notifications = Notification::forUser($this->user->id)->get();
        $user2Notifications = Notification::forUser($otherUser->id)->get();

        $this->assertCount(3, $user1Notifications);
        $this->assertCount(3, $user2Notifications);

        foreach ($user1Notifications as $notification) {
            $this->assertEquals($this->user->id, $notification->user_id);
        }

        foreach ($user2Notifications as $notification) {
            $this->assertEquals($otherUser->id, $notification->user_id);
        }
    }

    /**
     * Test lọc notifications theo priority
     */
    public function test_can_filter_notifications_by_priority(): void
    {
        $priorities = ['critical', 'normal', 'low'];
        
        foreach ($priorities as $priority) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'system_alert',
                'priority' => $priority,
                'title' => ucfirst($priority) . ' Alert',
                'body' => 'This is a ' . $priority . ' priority notification',
                'channel' => 'inapp',
            ]);
        }

        $criticalNotifications = Notification::critical()->get();
        $this->assertCount(1, $criticalNotifications);
        $this->assertEquals('critical', $criticalNotifications->first()->priority);

        $normalNotifications = Notification::withPriority('normal')->get();
        $this->assertCount(1, $normalNotifications);
        $this->assertEquals('normal', $normalNotifications->first()->priority);
    }

    /**
     * Test lọc notifications theo channel
     */
    public function test_can_filter_notifications_by_channel(): void
    {
        $channels = ['inapp', 'email', 'webhook'];
        
        foreach ($channels as $channel) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'project_update',
                'priority' => 'normal',
                'title' => 'Project Update',
                'body' => 'Project has been updated',
                'channel' => $channel,
            ]);
        }

        $inappNotifications = Notification::withChannel('inapp')->get();
        $this->assertCount(1, $inappNotifications);
        $this->assertEquals('inapp', $inappNotifications->first()->channel);

        $emailNotifications = Notification::withChannel('email')->get();
        $this->assertCount(1, $emailNotifications);
        $this->assertEquals('email', $emailNotifications->first()->channel);
    }

    /**
     * Test lọc notifications theo trạng thái đọc/chưa đọc
     */
    public function test_can_filter_notifications_by_read_status(): void
    {
        // Tạo 5 notifications, 3 chưa đọc, 2 đã đọc
        for ($i = 1; $i <= 5; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "Task {$i}",
                'body' => "Task {$i} description",
                'channel' => 'inapp',
                'read_at' => $i > 3 ? now() : null, // 3 chưa đọc, 2 đã đọc
            ]);
        }

        $unreadNotifications = Notification::unread()->get();
        $readNotifications = Notification::read()->get();

        $this->assertCount(3, $unreadNotifications);
        $this->assertCount(2, $readNotifications);

        foreach ($unreadNotifications as $notification) {
            $this->assertNull($notification->read_at);
        }

        foreach ($readNotifications as $notification) {
            $this->assertNotNull($notification->read_at);
        }
    }

    /**
     * Test sắp xếp notifications theo priority
     */
    public function test_can_order_notifications_by_priority(): void
    {
        $priorities = ['low', 'normal', 'critical'];
        
        foreach ($priorities as $priority) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'system_alert',
                'priority' => $priority,
                'title' => ucfirst($priority) . ' Alert',
                'body' => 'This is a ' . $priority . ' priority notification',
                'channel' => 'inapp',
            ]);
        }

        $orderedNotifications = Notification::orderByPriority()->get();
        
        $this->assertEquals('critical', $orderedNotifications->first()->priority);
        $this->assertEquals('normal', $orderedNotifications->skip(1)->first()->priority);
        $this->assertEquals('low', $orderedNotifications->last()->priority);
    }

    /**
     * Test multi-tenant isolation
     */
    public function test_notifications_are_isolated_by_tenant(): void
    {
        // Tạo tenant khác
        $otherTenant = Tenant::create([
            'name' => 'Other Company',
            'slug' => 'other-company',
            'status' => 'active',
        ]);

        // Tạo user cho tenant khác
        $otherUser = User::create([
            'name' => 'Other User',
            'email' => 'other@example.com',
            'password' => bcrypt('password'),
            'tenant_id' => $otherTenant->id,
            'status' => 'active',
        ]);

        // Tạo notifications cho cả 2 tenants
        Notification::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Tenant 1 Notification',
            'body' => 'Notification for tenant 1',
            'channel' => 'inapp',
        ]);

        Notification::create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherTenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Tenant 2 Notification',
            'body' => 'Notification for tenant 2',
            'channel' => 'inapp',
        ]);

        // Kiểm tra tenant isolation
        $tenant1Notifications = Notification::where('tenant_id', $this->tenant->id)->get();
        $tenant2Notifications = Notification::where('tenant_id', $otherTenant->id)->get();

        $this->assertCount(1, $tenant1Notifications);
        $this->assertCount(1, $tenant2Notifications);

        $this->assertEquals($this->tenant->id, $tenant1Notifications->first()->tenant_id);
        $this->assertEquals($otherTenant->id, $tenant2Notifications->first()->tenant_id);
    }

    /**
     * Test notification với metadata và data
     */
    public function test_can_create_notification_with_metadata_and_data(): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_assigned',
            'priority' => 'normal',
            'title' => 'Task Assigned',
            'body' => 'You have been assigned a new task',
            'channel' => 'inapp',
            'data' => [
                'task_id' => $this->task->id,
                'project_id' => $this->project->id,
                'action' => 'view',
            ],
            'metadata' => [
                'source' => 'system',
                'tags' => ['task', 'assignment'],
                'priority_score' => 5,
            ],
            'event_key' => 'task.assigned',
            'project_id' => $this->project->id,
        ]);

        $this->assertIsArray($notification->data);
        $this->assertEquals($this->task->id, $notification->data['task_id']);
        $this->assertEquals($this->project->id, $notification->data['project_id']);

        $this->assertIsArray($notification->metadata);
        $this->assertEquals('system', $notification->metadata['source']);
        $this->assertEquals(['task', 'assignment'], $notification->metadata['tags']);

        $this->assertEquals('task.assigned', $notification->event_key);
        $this->assertEquals($this->project->id, $notification->project_id);
    }

    /**
     * Test cleanup old notifications
     */
    public function test_can_cleanup_old_notifications(): void
    {
        // Tạo notifications cũ (đã đọc và quá 30 ngày)
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "Old Task {$i}",
                'body' => "Old task {$i} description",
                'channel' => 'inapp',
                'read_at' => now()->subDays(35), // Quá 30 ngày
            ]);
        }

        // Tạo notifications mới (chưa đọc hoặc mới đọc)
        for ($i = 1; $i <= 2; $i++) {
            Notification::create([
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'task_assigned',
                'priority' => 'normal',
                'title' => "New Task {$i}",
                'body' => "New task {$i} description",
                'channel' => 'inapp',
                'read_at' => $i === 1 ? now()->subDays(10) : null, // 1 đã đọc gần đây, 1 chưa đọc
            ]);
        }

        $deletedCount = Notification::cleanupOldNotifications();
        $this->assertEquals(3, $deletedCount);

        $remainingNotifications = Notification::count();
        $this->assertEquals(2, $remainingNotifications);
    }

    /**
     * Test bulk notification creation
     */
    public function test_can_create_bulk_notifications(): void
    {
        // Tạo multiple users
        $users = [];
        for ($i = 1; $i <= 3; $i++) {
            $users[] = User::create([
                'name' => "User {$i}",
                'email' => "user{$i}@example.com",
                'password' => bcrypt('password'),
                'tenant_id' => $this->tenant->id,
                'status' => 'active',
            ]);
        }

        // Tạo bulk notifications
        $notifications = [];
        foreach ($users as $user) {
            $notifications[] = Notification::create([
                'user_id' => $user->id,
                'tenant_id' => $this->tenant->id,
                'type' => 'project_update',
                'priority' => 'normal',
                'title' => 'Project Update',
                'body' => 'Project has been updated',
                'channel' => 'inapp',
                'project_id' => $this->project->id,
            ]);
        }

        $this->assertCount(3, $notifications);

        foreach ($notifications as $notification) {
            $this->assertDatabaseHas('notifications', [
                'id' => $notification->id,
                'type' => 'project_update',
                'priority' => 'normal',
                'title' => 'Project Update',
                'project_id' => $this->project->id,
            ]);
        }
    }
}
