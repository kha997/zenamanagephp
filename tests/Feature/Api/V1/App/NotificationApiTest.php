<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * NotificationApiTest - Round 251: Notifications Center Phase 1
 * 
 * Comprehensive tests for notifications API endpoints:
 * - GET /api/v1/app/notifications (list with filters and pagination)
 * - PUT /api/v1/app/notifications/{id}/read (mark single as read)
 * - PUT /api/v1/app/notifications/read-all (mark all as read)
 * 
 * @group notifications
 * @group api-v1
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(251001);
        $this->setDomainName('notification-api');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
        ]);

        // Create users
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        // Attach users to tenants
        $this->userA->tenants()->attach($this->tenantA->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);
        
        $this->userB->tenants()->attach($this->tenantB->id, [
            'role' => 'pm',
            'is_default' => true,
        ]);

        // Refresh users
        $this->userA->refresh();
        $this->userB->refresh();
    }

    /**
     * Test user can get notifications
     */
    public function test_user_can_get_notifications(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create notifications for userA
        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Task assigned',
            'message' => 'You have been assigned to a task',
            'is_read' => false,
        ]);

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'documents',
            'type' => 'document.uploaded',
            'title' => 'Document uploaded',
            'message' => 'A new document has been uploaded',
            'is_read' => true,
        ]);

        $response = $this->getJson('/api/v1/app/notifications');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'tenant_id',
                    'user_id',
                    'module',
                    'type',
                    'title',
                    'message',
                    'entity_type',
                    'entity_id',
                    'is_read',
                    'metadata',
                    'created_at',
                    'updated_at',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
                'last_page',
                'from',
                'to',
                'unread_count',
            ],
        ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(2, $response->json('meta.total'));
        $this->assertEquals(1, $response->json('meta.unread_count'));
    }

    /**
     * Test user only sees their notifications
     */
    public function test_user_only_sees_their_notifications(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create notification for userA
        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Task assigned to userA',
            'message' => 'You have been assigned',
            'is_read' => false,
        ]);

        // Create notification for userB (should not appear)
        Notification::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Task assigned to userB',
            'message' => 'You have been assigned',
            'is_read' => false,
        ]);

        $response = $this->getJson('/api/v1/app/notifications');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->userA->id, $data[0]['user_id']);
        $this->assertEquals('Task assigned to userA', $data[0]['title']);
    }

    /**
     * Test user can mark notification as read
     */
    public function test_user_can_mark_notification_as_read(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $notification = Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Task assigned',
            'message' => 'You have been assigned',
            'is_read' => false,
        ]);

        $response = $this->putJson("/api/v1/app/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $notification->id,
                'is_read' => true,
            ],
        ]);

        $notification->refresh();
        $this->assertTrue($notification->is_read);
    }

    /**
     * Test user can mark all notifications as read
     */
    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create 3 unread notifications
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'tenant_id' => $this->tenantA->id,
                'user_id' => $this->userA->id,
                'module' => 'tasks',
                'type' => 'task.assigned',
                'title' => "Task {$i} assigned",
                'message' => 'You have been assigned',
                'is_read' => false,
            ]);
        }

        $response = $this->putJson('/api/v1/app/notifications/read-all');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'count' => 3,
            ],
        ]);

        // Verify all are read
        $unreadCount = Notification::where('user_id', $this->userA->id)
            ->where('is_read', false)
            ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test filter by is_read
     */
    public function test_filter_by_is_read(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create read and unread notifications
        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Unread notification',
            'message' => 'Unread',
            'is_read' => false,
        ]);

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Read notification',
            'message' => 'Read',
            'is_read' => true,
        ]);

        // Test unread filter
        $response = $this->getJson('/api/v1/app/notifications?is_read=false');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertFalse($data[0]['is_read']);
        $this->assertEquals('Unread notification', $data[0]['title']);

        // Test read filter
        $response = $this->getJson('/api/v1/app/notifications?is_read=true');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_read']);
        $this->assertEquals('Read notification', $data[0]['title']);
    }

    /**
     * Test filter by module
     */
    public function test_filter_by_module(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create notifications for different modules
        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Task notification',
            'message' => 'Task related',
            'is_read' => false,
        ]);

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'documents',
            'type' => 'document.uploaded',
            'title' => 'Document notification',
            'message' => 'Document related',
            'is_read' => false,
        ]);

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'cost',
            'type' => 'co.needs_approval',
            'title' => 'Cost notification',
            'message' => 'Cost related',
            'is_read' => false,
        ]);

        // Test tasks filter
        $response = $this->getJson('/api/v1/app/notifications?module=tasks');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('tasks', $data[0]['module']);

        // Test documents filter
        $response = $this->getJson('/api/v1/app/notifications?module=documents');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('documents', $data[0]['module']);

        // Test cost filter
        $response = $this->getJson('/api/v1/app/notifications?module=cost');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('cost', $data[0]['module']);
    }

    /**
     * Test requires authentication
     */
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/notifications');
        $response->assertStatus(401);

        // Create notification with proper schema
        $notification = Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Test notification',
            'message' => 'Test message',
            'is_read' => false,
        ]);

        $response = $this->putJson("/api/v1/app/notifications/{$notification->id}/read");
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/app/notifications/read-all');
        $response->assertStatus(401);
    }

    /**
     * Test respects tenant isolation
     */
    public function test_respects_tenant_isolation(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create notification for tenantA
        $notificationA = Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Tenant A notification',
            'message' => 'For tenant A',
            'is_read' => false,
        ]);

        // Create notification for tenantB (should not appear)
        Notification::create([
            'tenant_id' => $this->tenantB->id,
            'user_id' => $this->userB->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Tenant B notification',
            'message' => 'For tenant B',
            'is_read' => false,
        ]);

        $response = $this->getJson('/api/v1/app/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->tenantA->id, $data[0]['tenant_id']);

        // Try to mark tenantB's notification as read (should fail)
        Sanctum::actingAs($this->userB, [], 'sanctum');
        $response = $this->putJson("/api/v1/app/notifications/{$notificationA->id}/read");
        $response->assertStatus(404); // Not found because it belongs to different tenant
    }

    /**
     * Test notifications sorted by created_at desc
     */
    public function test_notifications_sorted_by_created_at_desc(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create notifications with delays to ensure different timestamps
        $first = Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'First notification',
            'message' => 'First',
            'is_read' => false,
        ]);

        sleep(1); // Ensure different timestamp

        $second = Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Second notification',
            'message' => 'Second',
            'is_read' => false,
        ]);

        $response = $this->getJson('/api/v1/app/notifications');
        $response->assertStatus(200);
        $data = $response->json('data');
        
        // Should be sorted by created_at DESC (newest first)
        $this->assertCount(2, $data);
        $this->assertEquals($second->id, $data[0]['id']); // Newest first
        $this->assertEquals($first->id, $data[1]['id']); // Oldest last
    }

    /**
     * Test search filter by title or message
     */
    public function test_search_filter_by_title_or_message(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'tasks',
            'type' => 'task.assigned',
            'title' => 'Important task assigned',
            'message' => 'You need to complete this task',
            'is_read' => false,
        ]);

        Notification::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'module' => 'documents',
            'type' => 'document.uploaded',
            'title' => 'Document uploaded',
            'message' => 'A new document is available',
            'is_read' => false,
        ]);

        // Search by title
        $response = $this->getJson('/api/v1/app/notifications?search=Important');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Important', $data[0]['title']);

        // Search by message
        $response = $this->getJson('/api/v1/app/notifications?search=complete');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('complete', strtolower($data[0]['message']));

        // Search that matches nothing
        $response = $this->getJson('/api/v1/app/notifications?search=Nonexistent');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    /**
     * Test pagination works
     */
    public function test_pagination_works(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create 25 notifications
        for ($i = 1; $i <= 25; $i++) {
            Notification::create([
                'tenant_id' => $this->tenantA->id,
                'user_id' => $this->userA->id,
                'module' => 'tasks',
                'type' => 'task.assigned',
                'title' => "Task {$i} assigned",
                'message' => "Task {$i}",
                'is_read' => false,
            ]);
        }

        // Test first page
        $response = $this->getJson('/api/v1/app/notifications?page=1&per_page=20');
        $response->assertStatus(200);
        $data = $response->json('data');
        $meta = $response->json('meta');
        $this->assertCount(20, $data);
        $this->assertEquals(1, $meta['current_page']);
        $this->assertEquals(20, $meta['per_page']);
        $this->assertEquals(25, $meta['total']);
        $this->assertEquals(2, $meta['last_page']);

        // Test second page
        $response = $this->getJson('/api/v1/app/notifications?page=2&per_page=20');
        $response->assertStatus(200);
        $data = $response->json('data');
        $meta = $response->json('meta');
        $this->assertCount(5, $data); // Remaining 5 items
        $this->assertEquals(2, $meta['current_page']);
    }

    /**
     * Test unread count in response
     */
    public function test_unread_count_in_response(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Create 3 unread and 2 read notifications
        for ($i = 1; $i <= 3; $i++) {
            Notification::create([
                'tenant_id' => $this->tenantA->id,
                'user_id' => $this->userA->id,
                'module' => 'tasks',
                'type' => 'task.assigned',
                'title' => "Unread notification {$i}",
                'message' => 'Unread',
                'is_read' => false,
            ]);
        }

        for ($i = 1; $i <= 2; $i++) {
            Notification::create([
                'tenant_id' => $this->tenantA->id,
                'user_id' => $this->userA->id,
                'module' => 'tasks',
                'type' => 'task.assigned',
                'title' => "Read notification {$i}",
                'message' => 'Read',
                'is_read' => true,
            ]);
        }

        $response = $this->getJson('/api/v1/app/notifications');
        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertEquals(3, $meta['unread_count']);
    }
}
