<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;

/**
 * Feature tests cho Notification API endpoints
 */
class NotificationApiTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait;
    
    /**
     * Test get user notifications
     */
    public function test_can_get_user_notifications(): void
    {
        $user = $this->actingAsUser();
        
        // Tạo notifications cho user
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null
        ]);
        
        // Tạo read notifications
        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/notifications');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        '*' => [
                            'id',
                            'priority',
                            'title',
                            'body',
                            'link_url',
                            'read_at',
                            'created_at'
                        ]
                    ]
                ]);
        
        $this->assertCount(5, $response->json('data'));
    }
    
    /**
     * Test get only unread notifications
     */
    public function test_can_get_unread_notifications_only(): void
    {
        $user = $this->actingAsUser();
        
        Notification::factory()->count(3)->create([
            'user_id' => $user->id,
            'read_at' => null
        ]);
        
        Notification::factory()->count(2)->create([
            'user_id' => $user->id,
            'read_at' => now()
        ]);
        
        $response = $this->getJson('/api/v1/notifications?unread_only=true');
        
        $response->assertStatus(200);
        
        $this->assertCount(3, $response->json('data'));
        
        // Verify tất cả notifications đều unread
        foreach ($response->json('data') as $notification) {
            $this->assertNull($notification['read_at']);
        }
    }
    
    /**
     * Test mark notification as read
     */
    public function test_can_mark_notification_as_read(): void
    {
        $user = $this->actingAsUser();
        
        $notification = Notification::factory()->create([
            'user_id' => $user->id,
            'read_at' => null
        ]);
        
        $response = $this->putJson("/api/v1/notifications/{$notification->id}/read");
        
        $response->assertStatus(200)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'message' => 'Notification marked as read'
                    ]
                ]);
        
        // Verify notification được mark as read
        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id,
            'read_at' => null
        ]);
    }
    
    /**
     * Test create notification rule
     */
    public function test_can_create_notification_rule(): void
    {
        $user = $this->actingAsUser();
        
        $ruleData = [
            'event_key' => 'task.status.updated',
            'min_priority' => 'normal',
            'channels' => ['inapp', 'email'],
            'is_enabled' => true
        ];
        
        $response = $this->postJson('/api/v1/notification-rules', $ruleData);
        
        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'event_key' => 'task.status.updated',
                        'user_id' => $user->id,
                        'is_enabled' => true
                    ]
                ]);
        
        $this->assertDatabaseHas('notification_rules', [
            'user_id' => $user->id,
            'event_key' => 'task.status.updated'
        ]);
    }
}
