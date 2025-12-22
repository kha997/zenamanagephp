<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Notification;
use App\Models\ProjectTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * TaskReminderService - Round 254: Task Due-Date Reminder Notifications
 * 
 * Service for sending scheduled reminders for tasks that are due soon or overdue.
 * 
 * Business Rules:
 * - task.due_soon: sent once when due_date = tomorrow
 * - task.overdue: sent once when due_date < today (first day only)
 * - Only for tasks with assignee_id and not completed
 * - Prevents duplicate notifications by checking existing notifications
 */
class TaskReminderService
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Send due reminders for a specific tenant
     * 
     * @param string $tenantId Tenant ID
     * @return void
     */
    public function sendDueRemindersForTenant(string $tenantId): void
    {
        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();

        Log::info('TaskReminderService: Starting reminder process for tenant', [
            'tenant_id' => $tenantId,
            'today' => $today->toDateString(),
            'tomorrow' => $tomorrow->toDateString(),
        ]);

        $this->sendDueSoonReminders($tenantId, $today, $tomorrow);
        $this->sendOverdueReminders($tenantId, $today);

        Log::info('TaskReminderService: Completed reminder process for tenant', [
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Send reminders for tasks due tomorrow
     * 
     * @param string $tenantId Tenant ID
     * @param Carbon $today Today's date
     * @param Carbon $tomorrow Tomorrow's date
     * @return void
     */
    private function sendDueSoonReminders(string $tenantId, Carbon $today, Carbon $tomorrow): void
    {
        // Query tasks due tomorrow, not completed, with assignee
        $tasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $tomorrow->toDateString())
            ->where(function ($query) {
                $query->where('is_completed', false)
                    ->orWhereNull('is_completed');
            })
            ->where(function ($query) {
                $query->where('status', '!=', ProjectTask::STATUS_COMPLETED)
                    ->orWhereNull('status');
            })
            ->with('project')
            ->get();

        Log::info('TaskReminderService: Found tasks due soon', [
            'tenant_id' => $tenantId,
            'count' => $tasks->count(),
        ]);

        foreach ($tasks as $task) {
            // Check if notification already exists
            $exists = Notification::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $task->assignee_id)
                ->where('module', Notification::MODULE_TASKS)
                ->where('entity_type', 'task')
                ->where('entity_id', $task->id)
                ->where('type', 'task.due_soon')
                ->exists();

            if ($exists) {
                Log::debug('TaskReminderService: Skipping duplicate due_soon notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                ]);
                continue;
            }

            // Send notification
            try {
                $this->notificationService->notifyUser(
                    userId: $task->assignee_id,
                    module: Notification::MODULE_TASKS,
                    type: 'task.due_soon',
                    title: 'Công việc sắp đến hạn',
                    message: sprintf(
                        'Task "%s" trong dự án "%s" có hạn vào %s.',
                        $task->name,
                        optional($task->project)->name ?? 'N/A',
                        $task->due_date?->format('d/m/Y') ?? ''
                    ),
                    entityType: 'task',
                    entityId: $task->id,
                    metadata: [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id ?? null,
                        'project_name' => optional($task->project)->name ?? null,
                        'due_date' => $task->due_date?->toDateString(),
                        'is_overdue' => false,
                    ],
                    tenantId: $tenantId,
                );

                Log::info('TaskReminderService: Sent due_soon notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                ]);
            } catch (\Exception $e) {
                Log::error('TaskReminderService: Failed to send due_soon notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send reminders for overdue tasks
     * 
     * @param string $tenantId Tenant ID
     * @param Carbon $today Today's date
     * @return void
     */
    private function sendOverdueReminders(string $tenantId, Carbon $today): void
    {
        // Query tasks overdue (due_date < today), not completed, with assignee
        $tasks = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('assignee_id')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->where(function ($query) {
                $query->where('is_completed', false)
                    ->orWhereNull('is_completed');
            })
            ->where(function ($query) {
                $query->where('status', '!=', ProjectTask::STATUS_COMPLETED)
                    ->orWhereNull('status');
            })
            ->with('project')
            ->get();

        Log::info('TaskReminderService: Found overdue tasks', [
            'tenant_id' => $tenantId,
            'count' => $tasks->count(),
        ]);

        foreach ($tasks as $task) {
            // Check if notification already exists
            $exists = Notification::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $task->assignee_id)
                ->where('module', Notification::MODULE_TASKS)
                ->where('entity_type', 'task')
                ->where('entity_id', $task->id)
                ->where('type', 'task.overdue')
                ->exists();

            if ($exists) {
                Log::debug('TaskReminderService: Skipping duplicate overdue notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                ]);
                continue;
            }

            // Send notification
            try {
                $this->notificationService->notifyUser(
                    userId: $task->assignee_id,
                    module: Notification::MODULE_TASKS,
                    type: 'task.overdue',
                    title: 'Công việc đã quá hạn',
                    message: sprintf(
                        'Task "%s" trong dự án "%s" đã quá hạn từ %s.',
                        $task->name,
                        optional($task->project)->name ?? 'N/A',
                        $task->due_date?->format('d/m/Y') ?? ''
                    ),
                    entityType: 'task',
                    entityId: $task->id,
                    metadata: [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id ?? null,
                        'project_name' => optional($task->project)->name ?? null,
                        'due_date' => $task->due_date?->toDateString(),
                        'is_overdue' => true,
                    ],
                    tenantId: $tenantId,
                );

                Log::info('TaskReminderService: Sent overdue notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                ]);
            } catch (\Exception $e) {
                Log::error('TaskReminderService: Failed to send overdue notification', [
                    'tenant_id' => $tenantId,
                    'task_id' => $task->id,
                    'user_id' => $task->assignee_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
