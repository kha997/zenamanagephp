<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserNotificationPreference;
use App\Services\NotificationService;
use App\Services\TaskReminderService;
use App\Models\ProjectTask;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Carbon\Carbon;

/**
 * NotificationPreferencesApiTest - Round 255: Notification Preferences
 * 
 * Comprehensive tests for notification preferences API endpoints and integration:
 * - GET /api/v1/app/notification-preferences (get preferences)
 * - PUT /api/v1/app/notification-preferences (update preferences)
 * - Integration with NotificationService (preferences prevent notifications)
 * 
 * @group notifications
 * @group notification-preferences
 * @group api-v1
 */
class NotificationPreferencesApiTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected Project $projectA;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(255001);
        $this->setDomainName('notification-preferences');
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

        // Create a project for task tests
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Test Project',
        ]);
    }

    /**
     * Test user can get default notification preferences
     */
    public function test_can_get_default_notification_preferences_for_current_user(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->getJson('/api/v1/app/notification-preferences');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'preferences' => [
                    '*' => [
                        'type',
                        'is_enabled',
                    ],
                ],
            ],
        ]);

        $preferences = $response->json('data.preferences');
        $this->assertNotEmpty($preferences);

        // All types should be enabled by default (no preference rows exist)
        foreach ($preferences as $pref) {
            $this->assertTrue($pref['is_enabled'], "Type {$pref['type']} should be enabled by default");
        }
    }

    /**
     * Test user can update notification preferences
     */
    public function test_can_update_notification_preferences_for_current_user(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Update preferences: disable task.due_soon and task.overdue
        $response = $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.due_soon', 'is_enabled' => false],
                ['type' => 'task.overdue', 'is_enabled' => false],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'preferences' => [
                    '*' => [
                        'type',
                        'is_enabled',
                    ],
                ],
            ],
        ]);

        $preferences = $response->json('data.preferences');
        
        // Find the updated preferences
        $dueSoonPref = collect($preferences)->firstWhere('type', 'task.due_soon');
        $overduePref = collect($preferences)->firstWhere('type', 'task.overdue');
        
        $this->assertNotNull($dueSoonPref);
        $this->assertNotNull($overduePref);
        $this->assertFalse($dueSoonPref['is_enabled']);
        $this->assertFalse($overduePref['is_enabled']);

        // Other types should still be enabled
        $assignedPref = collect($preferences)->firstWhere('type', 'task.assigned');
        $this->assertNotNull($assignedPref);
        $this->assertTrue($assignedPref['is_enabled']);

        // Verify in database
        $pref = UserNotificationPreference::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.due_soon')
            ->first();
        
        $this->assertNotNull($pref);
        $this->assertFalse($pref->is_enabled);
    }

    /**
     * Test cannot set preferences for unknown type
     */
    public function test_cannot_set_preferences_for_unknown_type(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        $response = $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'unknown.type', 'is_enabled' => false],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'code' => 'UNKNOWN_NOTIFICATION_TYPE',
        ]);
    }

    /**
     * Test tenant isolation for preferences
     */
    public function test_tenant_isolation_for_preferences(): void
    {
        // Set preference in Tenant A
        Sanctum::actingAs($this->userA, [], 'sanctum');
        
        $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.due_soon', 'is_enabled' => false],
            ],
        ])->assertStatus(200);

        // Verify in Tenant A context
        $response = $this->getJson('/api/v1/app/notification-preferences');
        $preferences = $response->json('data.preferences');
        $dueSoonPref = collect($preferences)->firstWhere('type', 'task.due_soon');
        $this->assertFalse($dueSoonPref['is_enabled']);

        // Switch to Tenant B (userB)
        Sanctum::actingAs($this->userB, [], 'sanctum');
        
        // Verify Tenant B has defaults (no preference row)
        $response = $this->getJson('/api/v1/app/notification-preferences');
        $preferences = $response->json('data.preferences');
        $dueSoonPref = collect($preferences)->firstWhere('type', 'task.due_soon');
        $this->assertTrue($dueSoonPref['is_enabled'], 'Tenant B should have default (enabled)');
    }

    /**
     * Test disabled type prevents notifications from being created
     */
    public function test_disabled_type_prevents_notifications_from_being_created(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Disable task.due_soon for userA
        $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.due_soon', 'is_enabled' => false],
            ],
        ])->assertStatus(200);

        // Create a task with due_date = tomorrow
        $task = ProjectTask::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->userA->id,
            'due_date' => Carbon::tomorrow(),
            'is_completed' => false,
            'status' => 'in_progress',
            'name' => 'Test Task',
        ]);

        // Count notifications before
        $beforeCount = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.due_soon')
            ->count();

        // Run reminder service
        $reminderService = app(TaskReminderService::class);
        $reminderService->sendDueRemindersForTenant((string) $this->tenantA->id);

        // Count notifications after
        $afterCount = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.due_soon')
            ->count();

        // Should be the same (no new notification created)
        $this->assertEquals($beforeCount, $afterCount, 'Notification should not be created when preference is disabled');
    }

    /**
     * Test enabled type allows notifications by default
     */
    public function test_enabled_type_allows_notifications_by_default(): void
    {
        // Ensure no preference rows exist (default enabled)
        UserNotificationPreference::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->delete();

        // Create a task with due_date = tomorrow
        $task = ProjectTask::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->userA->id,
            'due_date' => Carbon::tomorrow(),
            'is_completed' => false,
            'status' => 'in_progress',
            'name' => 'Test Task',
        ]);

        // Count notifications before
        $beforeCount = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.due_soon')
            ->count();

        // Run reminder service
        $reminderService = app(TaskReminderService::class);
        $reminderService->sendDueRemindersForTenant((string) $this->tenantA->id);

        // Count notifications after
        $afterCount = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.due_soon')
            ->count();

        // Should have increased by 1
        $this->assertEquals($beforeCount + 1, $afterCount, 'Notification should be created when preference is enabled (default)');
    }

    /**
     * Test preferences affect direct NotificationService calls
     */
    public function test_preferences_affect_direct_notification_service_calls(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Disable task.assigned for userA
        $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.assigned', 'is_enabled' => false],
            ],
        ])->assertStatus(200);

        // Try to create notification directly via NotificationService
        $notificationService = app(NotificationService::class);
        $result = $notificationService->notifyUser(
            userId: (string) $this->userA->id,
            module: 'tasks',
            type: 'task.assigned',
            title: 'Task assigned',
            message: 'You have been assigned to a task',
            tenantId: (string) $this->tenantA->id
        );

        // Should return null (notification skipped)
        $this->assertNull($result);

        // Verify no notification was created
        $count = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.assigned')
            ->count();

        $this->assertEquals(0, $count, 'Notification should not be created when preference is disabled');
    }

    /**
     * Test re-enabled preference allows notifications again immediately
     * 
     * This test proves that preference changes take effect immediately without
     * requiring process restart, even when using the same service instance.
     */
    public function test_reenabled_preference_allows_notifications_again(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Get the same NotificationService instance that will be reused
        $notificationService = app(NotificationService::class);

        // Step 1: Disable task.assigned
        $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.assigned', 'is_enabled' => false],
            ],
        ])->assertStatus(200);

        // Step 2: Try to create notification - should be skipped
        $result1 = $notificationService->notifyUser(
            userId: (string) $this->userA->id,
            module: 'tasks',
            type: 'task.assigned',
            title: 'Task assigned (first attempt)',
            message: 'You have been assigned to a task',
            tenantId: (string) $this->tenantA->id
        );

        $this->assertNull($result1, 'Notification should be skipped when preference is disabled');

        // Verify no notification was created
        $count1 = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.assigned')
            ->count();
        $this->assertEquals(0, $count1, 'No notification should exist after disabled preference');

        // Step 3: Re-enable task.assigned via API
        $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.assigned', 'is_enabled' => true],
            ],
        ])->assertStatus(200);

        // Step 4: Try to create notification again using the SAME service instance
        // This should now succeed because we read from DB directly (no stale cache)
        $result2 = $notificationService->notifyUser(
            userId: (string) $this->userA->id,
            module: 'tasks',
            type: 'task.assigned',
            title: 'Task assigned (after re-enable)',
            message: 'You have been assigned to a task',
            tenantId: (string) $this->tenantA->id
        );

        // Should return a Notification object (not null)
        $this->assertNotNull($result2, 'Notification should be created after preference is re-enabled');
        $this->assertInstanceOf(Notification::class, $result2);

        // Verify notification was created
        $count2 = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->userA->id)
            ->where('type', 'task.assigned')
            ->count();
        $this->assertEquals(1, $count2, 'Notification should be created after re-enabling preference');
    }

    /**
     * Test requires authentication
     */
    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/app/notification-preferences');
        $response->assertStatus(401);

        $response = $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [],
        ]);
        $response->assertStatus(401);
    }

    /**
     * Test validation errors for invalid request
     */
    public function test_validation_errors_for_invalid_request(): void
    {
        Sanctum::actingAs($this->userA, [], 'sanctum');

        // Missing preferences
        $response = $this->putJson('/api/v1/app/notification-preferences', []);
        $response->assertStatus(422);

        // Invalid structure
        $response = $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.assigned'], // missing is_enabled
            ],
        ]);
        $response->assertStatus(422);

        // Invalid is_enabled type
        $response = $this->putJson('/api/v1/app/notification-preferences', [
            'preferences' => [
                ['type' => 'task.assigned', 'is_enabled' => 'not-a-boolean'],
            ],
        ]);
        $response->assertStatus(422);
    }
}
