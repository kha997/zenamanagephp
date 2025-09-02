<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Illuminate\Support\Facades\Hash;

class NotificationApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $user;
    protected $tenant;
    protected $token;

    /**
     * Setup method
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create([
            'id' => 1,
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->createRolesAndPermissions();
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Tạo roles và permissions cho test
     */
    private function createRolesAndPermissions()
    {
        $permissions = [
            'notification.read',
            'notification.update',
            'notification.delete',
        ];
        
        foreach ($permissions as $permissionCode) {
            Permission::create([
                'code' => $permissionCode,
                'module' => 'notification',
                'action' => explode('.', $permissionCode)[1],
                'description' => 'Permission for ' . $permissionCode
            ]);
        }
        
        $adminRole = Role::create([
            'name' => 'Admin',
            'scope' => 'system',
            'description' => 'System Administrator'
        ]);
        
        $adminRole->permissions()->attach(
            Permission::whereIn('code', $permissions)->pluck('id')
        );
        
        $this->user->systemRoles()->attach($adminRole->id);
    }

    /**
     * Test get all notifications
     */
    public function test_can_get_all_notifications()
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notifications');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'notifications' => [
                             '*' => [
                                 'id',
                                 'title',
                                 'body',
                                 'priority',
                                 'channel',
                                 'link_url',
                                 'read_at',
                                 'created_at',
                                 'updated_at'
                             ]
                         ],
                         'pagination'
                     ]
                 ]);
    }

    /**
     * Test mark notification as read
     */
    public function test_can_mark_notification_as_read()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Thông báo đã được đánh dấu là đã đọc'
                     ]
                 ]);

        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test mark all notifications as read
     */
    public function test_can_mark_all_notifications_as_read()
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->patchJson('/api/v1/notifications/mark-all-read');

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Tất cả thông báo đã được đánh dấu là đã đọc'
                     ]
                 ]);

        $unreadCount = Notification::where('user_id', $this->user->id)
                                 ->whereNull('read_at')
                                 ->count();
        $this->assertEquals(0, $unreadCount);
    }

    /**
     * Test delete notification
     */
    public function test_can_delete_notification()
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->deleteJson("/api/v1/notifications/{$notification->id}");

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'message' => 'Thông báo đã được xóa'
                     ]
                 ]);

        $this->assertDatabaseMissing('notifications', [
            'id' => $notification->id
        ]);
    }

    /**
     * Test get notification statistics
     */
    public function test_can_get_notification_statistics()
    {
        // Tạo notifications với các trạng thái khác nhau
        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'priority' => 'critical',
            'read_at' => null
        ]);
        
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'priority' => 'normal',
            'read_at' => now()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notifications/statistics');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'total',
                         'unread',
                         'critical',
                         'today'
                     ]
                 ]);
    }

    /**
     * Test create notification rule
     */
    public function test_can_create_notification_rule()
    {
        $ruleData = [
            'event_key' => 'task.deadline_approaching',
            'min_priority' => 'normal',
            'channels' => ['inapp', 'email'],
            'is_enabled' => true
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->postJson('/api/v1/notification-rules', $ruleData);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'rule' => [
                             'id',
                             'user_id',
                             'event_key',
                             'min_priority',
                             'channels',
                             'is_enabled',
                             'created_at',
                             'updated_at'
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('notification_rules', [
            'user_id' => $this->user->id,
            'event_key' => 'task.deadline_approaching'
        ]);
    }

    /**
     * Test get notification rules
     */
    public function test_can_get_notification_rules()
    {
        NotificationRule::factory()->count(3)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->getJson('/api/v1/notification-rules');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'rules' => [
                             '*' => [
                                 'id',
                                 'event_key',
                                 'min_priority',
                                 'channels',
                                 'is_enabled',
                                 'created_at',
                                 'updated_at'
                             ]
                         ]
                     ]
                 ]);
    }

    /**
     * Test update notification rule
     */
    public function test_can_update_notification_rule()
    {
        $rule = NotificationRule::factory()->create([
            'user_id' => $this->user->id,
            'is_enabled' => true
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
        ])->putJson("/api/v1/notification-rules/{$rule->id}", [
            'is_enabled' => false,
            'channels' => ['inapp']
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'status' => 'success',
                     'data' => [
                         'rule' => [
                             'id' => $rule->id,
                             'is_enabled' => false
                         ]
                     ]
                 ]);

        $this->assertDatabaseHas('notification_rules', [
            'id' => $rule->id,
            'is_enabled' => false
        ]);
    }
}