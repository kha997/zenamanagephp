<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;

class RealTimeNotificationsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->token = $this->generateJwtToken($this->user);
    }

    /**
     * Test notification creation
     */
    public function test_can_create_notification()
    {
        $notificationData = [
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'title' => 'New Task Assigned',
            'message' => 'You have been assigned a new task',
            'priority' => 'medium',
            'data' => ['task_id' => '123']
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/notifications', $notificationData);

        $response->assertStatus(201)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'type',
                        'title',
                        'message',
                        'priority',
                        'status'
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
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'data' => [
                            '*' => [
                                'id',
                                'type',
                                'title',
                                'message',
                                'priority',
                                'status',
                                'created_at'
                            ]
                        ]
                    ]
                ]);

        $notifications = $response->json('data.data');
        $this->assertCount(5, $notifications);
    }

    /**
     * Test marking notification as read
     */
    public function test_can_mark_notification_as_read()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'unread'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/zena/notifications/{$notification->id}/read");

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'id',
                        'status',
                        'read_at'
                    ]
                ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'status' => 'read'
        ]);
    }

    /**
     * Test marking all notifications as read
     */
    public function test_can_mark_all_notifications_as_read()
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'unread'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson('/api/zena/notifications/read-all');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message'
                ]);

        $unreadCount = Notification::where('user_id', $this->user->id)
            ->whereNull('read_at')
            ->count();

        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test getting unread notification count
     */
    public function test_can_get_unread_notification_count()
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'unread'
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'read'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications/stats/count');

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
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'priority' => 'high',
            'status' => 'unread'
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'rfi_submitted',
            'priority' => 'medium',
            'status' => 'read'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications/stats/summary');

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
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'type' => 'task_assigned'
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'type' => 'rfi_submitted'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications?type=task_assigned');

        $response->assertStatus(200);

        $notifications = $response->json('data.data');
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
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'status' => 'unread'
        ]);

        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'status' => 'read'
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/zena/notifications?status=unread');

        $response->assertStatus(200);

        $notifications = $response->json('data.data');
        $this->assertCount(2, $notifications);
        
        foreach ($notifications as $notification) {
            $this->assertEquals('unread', $notification['status']);
        }
    }

    /**
     * Test notification deletion
     */
    public function test_can_delete_notification()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/zena/notifications/{$notification->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    /**
     * Test notification validation
     */
    public function test_notification_creation_requires_valid_data()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/notifications', []);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['user_id', 'type', 'title', 'message', 'priority']);
    }

    /**
     * Test notification type validation
     */
    public function test_notification_type_validation()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/notifications', [
            'user_id' => $this->user->id,
            'type' => 'invalid_type',
            'title' => 'Test',
            'message' => 'Test message',
            'priority' => 'medium'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['type']);
    }

    /**
     * Test notification priority validation
     */
    public function test_notification_priority_validation()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/zena/notifications', [
            'user_id' => $this->user->id,
            'type' => 'task_assigned',
            'title' => 'Test',
            'message' => 'Test message',
            'priority' => 'invalid_priority'
        ]);

        $response->assertStatus(422)
                ->assertJsonValidationErrors(['priority']);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access_returns_401()
    {
        $response = $this->getJson('/api/zena/notifications');
        $response->assertStatus(401);
    }

    /**
     * Test notification expiration
     */
    public function test_notification_expiration()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'expires_at' => now()->subHour()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson("/api/zena/notifications/{$notification->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertTrue($data['is_expired']);
    }

    /**
     * Generate JWT token for testing
     */
    private function generateJwtToken(User $user): string
    {
        return 'test-jwt-token-' . $user->id;
    }
}
