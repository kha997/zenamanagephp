<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Src\Notification\Models\Notification;
use Tests\TestCase;
use Tests\Traits\DatabaseTrait;
use Tests\Traits\AuthenticationTrait;
use Tests\Traits\RouteNameTrait;

/**
 * Feature tests cho Notification API endpoints
 */
class NotificationApiTest extends TestCase
{
    use DatabaseTrait, AuthenticationTrait, RouteNameTrait;

    protected User $user;
    protected Tenant $tenant;
    protected array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant);
        $token = $this->apiLoginToken($this->user, $this->tenant);
        $this->authHeaders = $this->authHeadersForUser($this->user, $token);
    }
    
    /**
     * Test get user notifications
     */
    public function test_can_get_user_notifications(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now()
        ]);

        $response = $this->withHeaders($this->authHeaders)->getJson($this->namedRoute('notifications.index'));
        
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
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now()
        ]);

        $response = $this->withHeaders($this->authHeaders)->getJson($this->namedRoute('notifications.index', query: ['unread_only' => 'true']));
        
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
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null
        ]);

        $response = $this->withHeaders($this->authHeaders)->putJson($this->namedRoute('notifications.mark-read', ['id' => $notification->id]));
        
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
        $ruleData = [
            'event_key' => 'task.status.updated',
            'min_priority' => 'normal',
            'channels' => ['inapp', 'email'],
            'is_enabled' => true,
            'tenant_id' => $this->tenant->id
        ];

        $response = $this->withHeaders($this->authHeaders)->postJson($this->namedRoute('notification-rules.store'), $ruleData);

        $response->assertStatus(201)
                ->assertJson([
                    'status' => 'success',
                    'data' => [
                        'event_key' => 'task.status.updated',
                        'user_id' => $this->user->id,
                        'is_enabled' => true
                    ]
                ]);
        
        $this->assertDatabaseHas('notification_rules', [
            'user_id' => $this->user->id,
            'event_key' => 'task.status.updated',
        ]);
    }
}
