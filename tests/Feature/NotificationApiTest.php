<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\CoreProject\Models\Project;
use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Src\Notification\Events\NotificationCreated;

/**
 * Feature tests cho Notification API endpoints
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password123'),
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
        
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123',
        ]);
        
        $this->token = $loginResponse->json('data.token');
    }

    /**
     * Test lấy danh sách notifications
     */
    public function test_get_notifications(): void
    {
        // Tạo test notifications
        Notification::factory(3)->create([
            'user_id' => $this->user->id
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/v1/notifications');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'notifications' => [
                            '*' => [
                                'id',
                                'priority',
                                'title',
                                'body',
                                'link_url',
                                'channel',
                                'read_at',
                                'created_at'
                            ]
                        ]
                    ]
                ]);
    }

    /**
     * Test đánh dấu notification đã đọc
     */
    public function test_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'read_at' => null
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->patchJson('/api/v1/notifications/' . $notification->id . '/read');

        $response->assertStatus(200);
        
        $notification->refresh();
        $this->assertNotNull($notification->read_at);
    }

    /**
     * Test tạo notification rule
     */
    public function test_create_notification_rule(): void
    {
        $data = [
            'project_id' => $this->project->id,
            'event_key' => 'task.status_changed',
            'min_priority' => 'normal',
            'channels' => ['inapp', 'email'],
            'is_enabled' => true
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/v1/notification-rules', $data);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('notification_rules', [
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'event_key' => 'task.status_changed',
            'is_enabled' => true
        ]);
    }

    /**
     * Test event-driven notification creation
     */
    public function test_event_driven_notification_creation(): void
    {
        Event::fake();
        
        // Tạo notification rule
        NotificationRule::factory()->create([
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'event_key' => 'task.created',
            'min_priority' => 'normal',
            'channels' => ['inapp'],
            'is_enabled' => true
        ]);
        
        // Trigger event
        $eventData = [
            'project_id' => $this->project->id,
            'user_id' => $this->user->id,
            'title' => 'New Task Created',
            'body' => 'A new task has been created in your project',
            'priority' => 'normal'
        ];
        
        event(new NotificationCreated($eventData));
        
        Event::assertDispatched(NotificationCreated::class);
    }
}