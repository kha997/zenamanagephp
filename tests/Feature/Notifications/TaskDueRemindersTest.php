<?php declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Console\Commands\SendTaskDueRemindersCommand;
use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;

/**
 * TaskDueRemindersTest - Round 254: Task Due-Date Reminder Notifications
 * 
 * Feature tests for scheduled task reminder notifications:
 * - Due soon reminders (due_date = tomorrow)
 * - Overdue reminders (due_date < today)
 * - Duplicate prevention
 * - Completed task exclusion
 * - Task without assignee exclusion
 * - Tenant isolation
 * 
 * @group notifications
 * @group feature
 * @group scheduled
 */
class TaskDueRemindersTest extends TestCase
{
    use RefreshDatabase, DomainTestIsolation;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected User $assigneeA;
    protected User $assigneeB;
    protected Project $projectA;
    protected Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup domain isolation
        $this->setDomainSeed(254001);
        $this->setDomainName('task-due-reminders');
        $this->setupDomainIsolation();

        // Create tenants
        $this->tenantA = Tenant::factory()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a-' . uniqid(),
            'status' => 'active',
        ]);
        
        $this->tenantB = Tenant::factory()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b-' . uniqid(),
            'status' => 'active',
        ]);

        // Create users for tenant A
        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'pm',
        ]);

        $this->assigneeA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'member',
        ]);

        // Create users for tenant B
        $this->userB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'pm',
        ]);

        $this->assigneeB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'member',
        ]);

        // Create projects
        $this->projectA = Project::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Project A',
        ]);

        $this->projectB = Project::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Project B',
        ]);
    }

    /**
     * Test: Due soon task gets single notification
     */
    public function test_due_soon_task_gets_single_notification(): void
    {
        $tomorrow = Carbon::today()->addDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Test Task Due Soon',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: Exactly 1 notification created
        $notifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.due_soon')
            ->get();

        $this->assertCount(1, $notifications, 'Should have exactly 1 due_soon notification');

        $notification = $notifications->first();
        $this->assertEquals('Công việc sắp đến hạn', $notification->title);
        $this->assertStringContainsString($task->name, $notification->message);
        $this->assertStringContainsString($this->projectA->name, $notification->message);
        $this->assertEquals(Notification::MODULE_TASKS, $notification->module);
        
        // Assert metadata includes task_id
        $this->assertArrayHasKey('task_id', $notification->metadata);
        $this->assertEquals($task->id, $notification->metadata['task_id']);
        $this->assertArrayHasKey('project_id', $notification->metadata);
        $this->assertArrayHasKey('due_date', $notification->metadata);
        $this->assertArrayHasKey('is_overdue', $notification->metadata);
        $this->assertFalse($notification->metadata['is_overdue']);

        // Run command again
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: Still only 1 notification (no duplicate)
        $notificationsAfter = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.due_soon')
            ->get();

        $this->assertCount(1, $notificationsAfter, 'Should still have exactly 1 notification after second run');
    }

    /**
     * Test: Overdue task gets single notification
     */
    public function test_overdue_task_gets_single_notification(): void
    {
        $yesterday = Carbon::today()->subDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $yesterday,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Test Task Overdue',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: Exactly 1 notification created
        $notifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.overdue')
            ->get();

        $this->assertCount(1, $notifications, 'Should have exactly 1 overdue notification');

        $notification = $notifications->first();
        $this->assertEquals('Công việc đã quá hạn', $notification->title);
        $this->assertStringContainsString($task->name, $notification->message);
        $this->assertStringContainsString($this->projectA->name, $notification->message);
        $this->assertEquals(Notification::MODULE_TASKS, $notification->module);
        
        // Assert metadata includes task_id
        $this->assertArrayHasKey('task_id', $notification->metadata);
        $this->assertEquals($task->id, $notification->metadata['task_id']);
        $this->assertArrayHasKey('project_id', $notification->metadata);
        $this->assertArrayHasKey('due_date', $notification->metadata);
        $this->assertArrayHasKey('is_overdue', $notification->metadata);
        $this->assertTrue($notification->metadata['is_overdue']);

        // Run command again
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: Still only 1 notification (no duplicate)
        $notificationsAfter = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.overdue')
            ->get();

        $this->assertCount(1, $notificationsAfter, 'Should still have exactly 1 notification after second run');
    }

    /**
     * Test: Completed task does not get reminder
     */
    public function test_completed_task_does_not_get_reminder(): void
    {
        $tomorrow = Carbon::today()->addDay();

        // Task with is_completed = true
        $completedTask1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => true,
            'status' => ProjectTask::STATUS_COMPLETED,
            'name' => 'Completed Task 1',
        ]);

        // Task with status = completed
        $completedTask2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_COMPLETED,
            'name' => 'Completed Task 2',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: No notifications for completed tasks
        $notifications1 = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_id', $completedTask1->id)
            ->get();

        $notifications2 = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_id', $completedTask2->id)
            ->get();

        $this->assertCount(0, $notifications1, 'Completed task (is_completed=true) should not get notification');
        $this->assertCount(0, $notifications2, 'Completed task (status=completed) should not get notification');
    }

    /**
     * Test: Task without assignee does not get reminder
     */
    public function test_task_without_assignee_does_not_get_reminder(): void
    {
        $tomorrow = Carbon::today()->addDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => null,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task Without Assignee',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: No notifications
        $notifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('entity_id', $task->id)
            ->get();

        $this->assertCount(0, $notifications, 'Task without assignee should not get notification');
    }

    /**
     * Test: Tenant isolation for reminders
     */
    public function test_tenant_isolation_for_reminders(): void
    {
        $tomorrow = Carbon::today()->addDay();

        // Task in tenant A
        $taskA = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task A',
        ]);

        // Task in tenant B
        $taskB = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'project_id' => $this->projectB->id,
            'assignee_id' => $this->assigneeB->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task B',
        ]);

        // Run command for all tenants
        Artisan::call('tasks:send-due-reminders');

        // Assert: Each tenant only has notification for their own task
        $notificationsA = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_id', $taskA->id)
            ->where('type', 'task.due_soon')
            ->get();

        $notificationsB = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantB->id)
            ->where('user_id', $this->assigneeB->id)
            ->where('entity_id', $taskB->id)
            ->where('type', 'task.due_soon')
            ->get();

        $this->assertCount(1, $notificationsA, 'Tenant A should have notification for their task');
        $this->assertCount(1, $notificationsB, 'Tenant B should have notification for their task');

        // Assert: Tenant A should not see Tenant B's notifications
        $tenantANotificationsForB = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('entity_id', $taskB->id)
            ->get();

        $this->assertCount(0, $tenantANotificationsForB, 'Tenant A should not have notifications for Tenant B tasks');
    }

    /**
     * Test: Overdue reminder not sent after completion
     */
    public function test_overdue_reminder_not_sent_after_completion(): void
    {
        $yesterday = Carbon::today()->subDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $yesterday,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Overdue Task Before Completion',
        ]);

        // Run command - should send overdue notification
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        $notificationsBefore = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_id', $task->id)
            ->where('type', 'task.overdue')
            ->count();

        $this->assertEquals(1, $notificationsBefore, 'Should have 1 overdue notification');

        // Mark task as completed
        $task->update([
            'is_completed' => true,
            'status' => ProjectTask::STATUS_COMPLETED,
        ]);

        // Run command again
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: Still only 1 notification (no new notification after completion)
        $notificationsAfter = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_id', $task->id)
            ->where('type', 'task.overdue')
            ->count();

        $this->assertEquals(1, $notificationsAfter, 'Should still have only 1 notification after task completion');
    }

    /**
     * Test: Task without due_date does not get reminder
     */
    public function test_task_without_due_date_does_not_get_reminder(): void
    {
        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => null,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task Without Due Date',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: No notifications
        $notifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('entity_id', $task->id)
            ->get();

        $this->assertCount(0, $notifications, 'Task without due_date should not get notification');
    }

    /**
     * Test: Multiple tasks for same user get separate notifications
     */
    public function test_multiple_tasks_for_same_user_get_separate_notifications(): void
    {
        $tomorrow = Carbon::today()->addDay();

        $task1 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task 1',
        ]);

        $task2 = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Task 2',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: 2 separate notifications
        $notifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('type', 'task.due_soon')
            ->get();

        $this->assertCount(2, $notifications, 'Should have 2 separate notifications for 2 tasks');

        $taskIds = $notifications->pluck('entity_id')->toArray();
        $this->assertContains($task1->id, $taskIds);
        $this->assertContains($task2->id, $taskIds);
    }

    /**
     * Test: Other module notifications do not block task reminders
     */
    public function test_other_module_notifications_do_not_block_task_reminders(): void
    {
        $tomorrow = Carbon::today()->addDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Test Task Due Soon',
        ]);

        // Create a pre-existing notification with same task/user/type but different module
        Notification::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->assigneeA->id,
            'module' => Notification::MODULE_SYSTEM,
            'type' => 'task.due_soon',
            'title' => 'System Notification',
            'message' => 'Test message',
            'entity_type' => 'task',
            'entity_id' => $task->id,
            'is_read' => false,
            'metadata' => [],
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Assert: A new notification IS created for module=tasks
        $taskNotifications = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.due_soon')
            ->get();

        // Should have 2 notifications: 1 for system module, 1 for tasks module
        $this->assertCount(2, $taskNotifications, 'Should have 2 notifications (system + tasks)');

        // Verify the tasks module notification exists
        $tasksModuleNotification = $taskNotifications->firstWhere('module', Notification::MODULE_TASKS);
        $this->assertNotNull($tasksModuleNotification, 'Should have notification with module=tasks');
        $this->assertEquals(Notification::MODULE_TASKS, $tasksModuleNotification->module);
        $this->assertEquals('task.due_soon', $tasksModuleNotification->type);

        // Verify the system module notification still exists
        $systemModuleNotification = $taskNotifications->firstWhere('module', Notification::MODULE_SYSTEM);
        $this->assertNotNull($systemModuleNotification, 'Should still have notification with module=system');
        $this->assertEquals(Notification::MODULE_SYSTEM, $systemModuleNotification->module);
    }

    /**
     * Test: Notification metadata includes task_id
     */
    public function test_notification_metadata_includes_task_id(): void
    {
        $tomorrow = Carbon::today()->addDay();

        $task = ProjectTask::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'assignee_id' => $this->assigneeA->id,
            'due_date' => $tomorrow,
            'is_completed' => false,
            'status' => ProjectTask::STATUS_IN_PROGRESS,
            'name' => 'Test Task For Metadata',
        ]);

        // Run command
        Artisan::call('tasks:send-due-reminders', [
            '--tenant' => $this->tenantA->id,
        ]);

        // Fetch the created notification
        $notification = Notification::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenantA->id)
            ->where('user_id', $this->assigneeA->id)
            ->where('entity_type', 'task')
            ->where('entity_id', $task->id)
            ->where('type', 'task.due_soon')
            ->where('module', Notification::MODULE_TASKS)
            ->first();

        $this->assertNotNull($notification, 'Notification should exist');

        // Assert metadata structure
        $this->assertIsArray($notification->metadata, 'Metadata should be an array');
        $this->assertArrayHasKey('task_id', $notification->metadata, 'Metadata should have task_id');
        $this->assertEquals($task->id, $notification->metadata['task_id'], 'task_id should match task ID');
        $this->assertArrayHasKey('project_id', $notification->metadata, 'Metadata should have project_id');
        $this->assertArrayHasKey('due_date', $notification->metadata, 'Metadata should have due_date');
        $this->assertArrayHasKey('is_overdue', $notification->metadata, 'Metadata should have is_overdue');
        $this->assertFalse($notification->metadata['is_overdue'], 'is_overdue should be false for due_soon');
    }
}
