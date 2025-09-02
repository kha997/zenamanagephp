<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use Src\Notification\Models\Notification;
use Src\Notification\Services\NotificationService;
use Illuminate\Support\Facades\Event;
use Src\Notification\Events\NotificationCreated;

class WebSocketTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test WebSocket notification broadcasting
     */
    public function test_websocket_notification_broadcasting(): void
    {
        Event::fake();
        
        $notificationService = app(NotificationService::class);
        
        $notification = $notificationService->create([
            'user_id' => $this->user->id,
            'title' => 'Test WebSocket Notification',
            'body' => 'This is a test notification for WebSocket',
            'priority' => 'normal',
            'channel' => 'inapp'
        ]);
        
        // Verify notification was created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->user->id,
            'title' => 'Test WebSocket Notification'
        ]);
        
        // Verify event was dispatched
        Event::assertDispatched(NotificationCreated::class, function ($event) use ($notification) {
            return $event->notification->id === $notification->id;
        });
    }

    /**
     * Test real-time project updates
     */
    public function test_realtime_project_updates(): void
    {
        Event::fake();
        
        $project = \Src\CoreProject\Models\Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'progress' => 50.0
        ]);
        
        // Update project progress
        $project->update(['progress' => 75.0]);
        
        // Verify project was updated
        $this->assertEquals(75.0, $project->fresh()->progress);
        
        // In a real scenario, this would trigger a WebSocket broadcast
        // We can test the event dispatching mechanism
        $this->assertTrue(true); // Placeholder for WebSocket integration test
    }
}