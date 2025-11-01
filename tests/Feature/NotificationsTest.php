<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Notification;
use App\Models\Task;
use App\Models\Project;
use App\Models\Client;
use App\Models\Quote;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class NotificationsTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Tenant $tenant;
    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_notifications_enabled' => true,
        ]);
        
        $this->notificationService = app(NotificationService::class);
    }

    /** @test */
    public function it_can_create_notification(): void
    {
        $notificationData = [
            'type' => 'task_completed',
            'title' => 'Task Completed',
            'message' => 'Task "Test Task" has been completed',
            'priority' => 'medium',
            'data' => [
                'task_id' => 1,
                'project_id' => 1,
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/notifications', $notificationData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'type',
                    'title',
                    'message',
                    'priority',
                    'data',
                    'read_at',
                    'created_at',
                ]
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_completed',
            'title' => 'Task Completed',
        ]);
    }

    /** @test */
    public function it_can_list_notifications(): void
    {
        Notification::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/notifications');

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
                            'read_at',
                            'created_at',
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page',
                    ]
                ]
            ]);

        $this->assertEquals(5, $response->json('data.data.total'));
    }

    /** @test */
    public function it_can_mark_notification_as_read(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/app/notifications/{$notification->id}/read");

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'read_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_mark_all_notifications_as_read(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/notifications/mark-all-read');

        $response->assertStatus(200);

        $this->assertEquals(0, Notification::where('user_id', $this->user->id)
            ->whereNull('read_at')
            ->count());
    }

    /** @test */
    public function it_can_get_unread_count(): void
    {
        Notification::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => null,
        ]);

        Notification::factory()->count(2)->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'read_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'unread_count' => 3,
                ]
            ]);
    }

    /** @test */
    public function it_sends_task_completed_notification(): void
    {
        Mail::fake();

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'completed_by' => $this->user->id,
            'status' => 'completed',
        ]);

        $this->notificationService->sendTaskCompletedNotification($task, $this->user);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_completed',
        ]);

        Mail::assertSent(\App\Mail\TaskCompletedMail::class);
    }

    /** @test */
    public function it_sends_quote_sent_notification(): void
    {
        Mail::fake();

        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->notificationService->sendQuoteSentNotification($quote, $client);

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'type' => 'quote_sent',
        ]);

        Mail::assertSent(\App\Mail\QuoteSentMail::class);
    }

    /** @test */
    public function it_sends_client_created_notification(): void
    {
        Mail::fake();

        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->notificationService->sendClientCreatedNotification($client, $this->user);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'client_created',
        ]);

        Mail::assertSent(\App\Mail\ClientCreatedMail::class);
    }

    /** @test */
    public function it_enforces_tenant_isolation_for_notifications(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $otherNotification = Notification::factory()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/app/notifications/{$otherNotification->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_can_filter_notifications_by_type(): void
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'task_completed',
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'quote_sent',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/notifications?type=task_completed');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.data.total'));
    }

    /** @test */
    public function it_can_filter_notifications_by_priority(): void
    {
        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'priority' => 'high',
        ]);

        Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
            'priority' => 'low',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/notifications?priority=high');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.data.total'));
    }

    /** @test */
    public function it_can_delete_notification(): void
    {
        $notification = Notification::factory()->create([
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/app/notifications/{$notification->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('notifications', [
            'id' => $notification->id,
        ]);
    }

    /** @test */
    public function it_validates_notification_creation_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/notifications', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'title', 'message']);
    }

    /** @test */
    public function it_respects_user_email_notification_preferences(): void
    {
        Mail::fake();

        $userWithoutEmail = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email_notifications_enabled' => false,
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $task = Task::factory()->create([
            'project_id' => $project->id,
            'tenant_id' => $this->tenant->id,
            'completed_by' => $userWithoutEmail->id,
            'status' => 'completed',
        ]);

        $this->notificationService->sendTaskCompletedNotification($task, $userWithoutEmail);

        // Should create in-app notification but not send email
        $this->assertDatabaseHas('notifications', [
            'user_id' => $userWithoutEmail->id,
            'type' => 'task_completed',
        ]);

        Mail::assertNotSent(\App\Mail\TaskCompletedMail::class);
    }
}
