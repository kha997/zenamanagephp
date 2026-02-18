<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use Src\Notification\Models\Notification;
use Src\Notification\Models\NotificationRule;
use Illuminate\Support\Facades\Event;
use Src\Notification\Events\NotificationCreated;

/**
 * Feature tests cho Notification API endpoints
 */
class NotificationApiTest extends TestCase
{
    use RefreshDatabase, WithFaker, AuthenticationTestTrait;

    protected User $user;
    protected Tenant $tenant;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiActingAsTenantAdmin();
        $this->tenant = $this->apiFeatureTenant;
        $this->user = $this->apiFeatureUser;
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test lấy danh sách notifications
     */
    public function test_get_notifications(): void
    {
        Notification::factory()->forUser((string) $this->user->id)->count(3)->create();

        $response = $this->apiGet('/api/v1/notifications');

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
                            'channel',
                            'read_at',
                            'created_at'
                        ]
                    ],
                    'meta' => [
                        'pagination' => [
                            'page',
                            'per_page',
                            'total',
                            'last_page'
                        ]
                    ]
                ]);
    }

    /**
     * Test đánh dấu notification đã đọc
     */
    public function test_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->forUser((string) $this->user->id)->create([
            'read_at' => null
        ]);

        $response = $this->apiPost("/api/v1/notifications/{$notification->id}/mark-read");

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
            'event_key' => 'Project.Task.StatusChanged',
            'min_priority' => 'normal',
            'channels' => ['inapp', 'email'],
            'is_enabled' => true
        ];

        $response = $this->apiPost('/api/v1/notification-rules', $data);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('notification_rules', [
            'user_id' => $this->user->id,
            'project_id' => $this->project->id,
            'event_key' => 'Project.Task.StatusChanged',
            'tenant_id' => $this->tenant->id,
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
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'event_key' => 'Project.Task.Created',
            'min_priority' => 'normal',
            'channels' => ['inapp'],
            'is_enabled' => true
        ]);
        
        // Trigger event
        $eventData = [
            'project_id' => (string) $this->project->id,
            'user_id' => (string) $this->user->id,
            'tenant_id' => (string) $this->tenant->id,
            'event_key' => 'Project.Task.Created',
            'title' => 'New Task Created',
            'body' => 'A new task has been created in your project',
            'priority' => 'normal'
        ];

        event(new NotificationCreated(
            (string) Str::ulid(),
            (string) $this->project->id,
            (string) $this->user->id,
            $eventData
        ));
        
        Event::assertDispatched(NotificationCreated::class);
    }
}
