<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\ZenaNotification;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\Traits\RouteNameTrait;

class RealTimeNotificationsTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait, RouteNameTrait;

    protected $user;
    protected $tenantId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->user = $this->apiFeatureUser;
        $this->tenantId = $this->apiFeatureTenant->id;
    }

    /**
     * Test notification creation
     */
    public function test_can_create_notification()
    {
        $notificationData = [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => 'task_assigned',
            'title' => 'New Task Assigned',
            'message' => 'You have been assigned a new task',
            'priority' => 'normal',
            'data' => ['task_id' => '123']
        ];

        $response = $this->apiPost($this->zena('notifications.store'), $notificationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'status_text',
                    'message',
                    'data' => [
                        'id',
                        'tenant_id',
                        'user_id',
                        'type',
                        'title',
                        'data',
                        'priority',
                        'created_at',
                        'updated_at',
                    ]
                ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'title' => 'New Task Assigned'
        ]);
    }

    /**
     * Test notification listing
     */
    public function test_can_get_notifications()
    {
        ZenaNotification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
        ]);

        $response = $this->apiGet($this->zena('notifications.index'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'status_text',
                    'success',
                    'data',
                    'meta' => [
                        'pagination' => [
                            'page',
                            'per_page',
                            'total',
                            'last_page'
                        ]
                    ]
                ]);

        $notifications = $response->json('data');
        $this->assertCount(5, $notifications);
    }

    /**
     * Test marking notification as read
     */
    public function test_can_mark_notification_as_read()
    {
        $notification = ZenaNotification::factory()->create($this->notificationAttributes());

        $response = $this->apiPut($this->zena('notifications.mark-read', ['id' => $notification->id]));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'status_text',
                    'success',
                    'data' => [
                        'id',
                        'tenant_id',
                        'user_id',
                        'type',
                        'title',
                        'body',
                        'read_at',
                        'created_at',
                        'updated_at',
                    ]
                ]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test marking all notifications as read
     */
    public function test_can_mark_all_notifications_as_read()
    {
        ZenaNotification::factory()->count(3)->create($this->notificationAttributes());

        $response = $this->apiPut($this->zena('notifications.mark-all-read'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);

        $unreadCount = ZenaNotification::where('user_id', $this->user->id)
            ->where('tenant_id', $this->tenantId)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test getting unread notification count
     */
    public function test_can_get_unread_notification_count()
    {
        ZenaNotification::factory()->count(3)->create($this->notificationAttributes());

        ZenaNotification::factory()->count(2)->create($this->notificationAttributes([
            'read_at' => now()
        ]));

        $response = $this->apiGet($this->zena('notifications.unread-count'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'count'
                    ]
                ]);

        $this->assertEquals(3, $response->json('data.count'));
    }

    /**
     * Test getting notification statistics
     */
    public function test_can_get_notification_statistics()
    {
        ZenaNotification::factory()->count(2)->create($this->notificationAttributes([
            'type' => 'task_assigned',
            'priority' => 'critical',
            'read_at' => null
        ]));

        ZenaNotification::factory()->count(3)->create($this->notificationAttributes([
            'type' => 'rfi_submitted',
            'priority' => 'normal',
            'read_at' => now()
        ]));

        $response = $this->apiGet($this->zena('notifications.stats'));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'total',
                        'unread',
                        'read',
                        'by_type',
                        'by_priority'
                    ]
                ]);

        $stats = $response->json('data');
        $this->assertEquals(5, $stats['total']);
        $this->assertEquals(2, $stats['unread']);
        $this->assertEquals(3, $stats['read']);
    }

    /**
     * Test notification filtering by type
     */
    public function test_can_filter_notifications_by_type()
    {
        ZenaNotification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => 'task_assigned'
        ]);

        ZenaNotification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => 'rfi_submitted'
        ]);

        $response = $this->apiGet($this->zena('notifications.index', query: ['type' => 'task_assigned']));

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'status_text',
                    'success',
                    'data',
                    'meta' => [
                        'pagination' => [
                            'page',
                            'per_page',
                            'total',
                            'last_page'
                        ]
                    ]
                ]);

        $notifications = $response->json('data');
        $this->assertCount(2, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertEquals('task_assigned', $notification['type']);
        }
    }

    /**
     * Test notification filtering by status
     */
    public function test_can_filter_notifications_by_status()
    {
        ZenaNotification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'read_at' => null
        ]);

        ZenaNotification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'read_at' => now()
        ]);

        $response = $this->apiGet($this->zena('notifications.index', query: ['status' => 'unread']));

        $response->assertStatus(200);

        $notifications = $response->json('data');
        $this->assertCount(2, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertNull($notification['read_at']);
        }
    }

    /**
     * Test notification deletion
     */
    public function test_can_delete_notification()
    {
        $notification = ZenaNotification::factory()->create($this->notificationAttributes());

        $response = $this->apiDelete($this->zena('notifications.destroy', ['id' => $notification->id]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['success']);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    /**
     * Test notification validation
     */
    public function test_notification_creation_requires_valid_data()
    {
        $response = $this->apiPost($this->zena('notifications.store'), []);

        $response->assertStatus(422);

        $errors = $response->json('error.details.data');

        foreach (['user_id', 'type', 'title', 'message', 'priority'] as $field) {
            $this->assertArrayHasKey($field, $errors);
        }
    }

    /**
     * Test notification type validation
     */
    public function test_notification_type_validation()
    {
        $response = $this->apiPost($this->zena('notifications.store'), [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => 'invalid_type',
            'title' => 'Test',
            'message' => 'Test message',
            'priority' => 'normal'
        ]);

        $response->assertStatus(422);

        $errors = $response->json('error.details.data');
        $this->assertArrayHasKey('type', $errors);
    }

    /**
     * Test notification priority validation
     */
    public function test_notification_priority_validation()
    {
        $response = $this->apiPost($this->zena('notifications.store'), [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => 'task_assigned',
            'title' => 'Test',
            'message' => 'Test message',
            'priority' => 'invalid_priority'
        ]);

        $response->assertStatus(422);

        $errors = $response->json('error.details.data');
        $this->assertArrayHasKey('priority', $errors);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->withHeaders($this->tenantHeaders())->getJson($this->zena('notifications.index'));
        $response->assertStatus(401);
    }

    /**
     * Test notification expiration
     */
    public function test_notification_expiration()
    {
        $notification = ZenaNotification::factory()->create($this->notificationAttributes([
            'metadata' => ['expires_at' => now()->subHour()->toISOString()]
        ]));

        $response = $this->apiGet($this->zena('notifications.show', ['id' => $notification->id]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertTrue($data['is_expired']);
    }

    private function notificationAttributes(array $attributes = []): array
    {
        return array_merge([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenantId,
            'type' => $this->faker->randomElement(['task_assigned', 'rfi_submitted']),
            'title' => 'Sample Notification',
            'body' => 'This is a sample notification.',
            'priority' => 'normal',
            'read_at' => null,
            'data' => [],
        ], $attributes);
    }
}
