<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\Project;
use Src\Notification\Services\NotificationService;
use Src\Notification\Services\NotificationRuleService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Listener xử lý gửi thông báo cho các sự kiện quan trọng
 */
class NotificationListener
{
    private NotificationService $notificationService;
    private NotificationRuleService $notificationRuleService;

    public function __construct(
        NotificationService $notificationService,
        NotificationRuleService $notificationRuleService
    ) {
        $this->notificationService = $notificationService;
        $this->notificationRuleService = $notificationRuleService;
    }

    /**
     * Xử lý sự kiện TaskCompleted
     *
     * @param array $payload
     * @return void
     */
    public function handleTaskCompleted(array $payload): void
    {
        try {
            $taskId = $payload['entityId'];
            $task = Task::with(['assignments.user', 'project'])->find($taskId);
            
            if (!$task) {
                return;
            }

            // Gửi thông báo cho project manager
            $this->notifyProjectManager($task, 'task_completed');
            
            // Gửi thông báo cho team members được assign
            $this->notifyAssignedUsers($task, 'task_completed');
            
            Log::info("Task completion notifications sent", [
                'task_id' => $taskId,
                'project_id' => $task->project_id
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error sending task completion notifications", [
                'task_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý sự kiện TaskReady
     *
     * @param array $payload
     * @return void
     */
    public function handleTaskReady(array $payload): void
    {
        try {
            $taskId = $payload['entityId'];
            $task = Task::with(['assignments.user'])->find($taskId);
            
            if (!$task) {
                return;
            }

            // Gửi thông báo cho users được assign task này
            $this->notifyAssignedUsers($task, 'task_ready');
            
            Log::info("Task ready notifications sent", [
                'task_id' => $taskId
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error sending task ready notifications", [
                'task_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý sự kiện ProjectProgressUpdated
     *
     * @param array $payload
     * @return void
     */
    public function handleProjectProgressUpdated(array $payload): void
    {
        try {
            $projectId = $payload['entityId'];
            $project = Project::find($projectId);
            
            if (!$project) {
                return;
            }

            // Gửi thông báo milestone nếu đạt được progress quan trọng
            $this->checkAndNotifyMilestones($project, $payload['changedFields']);
            
        } catch (\Exception $e) {
            Log::error("Error processing project progress notifications", [
                'project_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi thông báo cho project manager
     *
     * @param Task $task
     * @param string $eventType
     * @return void
     */
    private function notifyProjectManager(Task $task, string $eventType): void
    {
        try {
            // Lấy project manager từ project roles
            $projectManagers = DB::table('project_user_roles')
                ->join('roles', 'project_user_roles.role_id', '=', 'roles.id')
                ->join('users', 'project_user_roles.user_id', '=', 'users.id')
                ->where('project_user_roles.project_id', $task->project_id)
                ->where('roles.name', 'Project Manager')
                ->select('users.id', 'users.name', 'users.email')
                ->get();

            foreach ($projectManagers as $manager) {
                $eventKey = $this->mapEventTypeToKey($eventType);
                
                // Kiểm tra notification rules của manager
                $applicableRules = $this->notificationRuleService->getApplicableRules(
                    $manager->id,
                    $eventKey,
                    $task->project_id,
                    'normal',
                    [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id,
                        'task_name' => $task->name
                    ]
                );

                if ($applicableRules->isNotEmpty()) {
                    // Lấy tất cả channels từ các rules
                    $channels = $applicableRules->pluck('channels')->flatten()->unique();
                    
                    foreach ($channels as $channel) {
                        $this->notificationService->createNotification([
                            'user_id' => $manager->id,
                            'priority' => 'normal',
                            'title' => $this->getNotificationTitle($eventType, 'manager'),
                            'body' => $this->getNotificationBody($eventType, $task, 'manager'),
                            'link_url' => "/projects/{$task->project_id}/tasks/{$task->id}",
                            'channel' => $channel,
                            'event_key' => $eventKey,
                            'project_id' => $task->project_id,
                            'metadata' => [
                                'task_id' => $task->id,
                                'task_name' => $task->name,
                                'project_name' => $task->project->name ?? null
                            ]
                        ]);
                    }
                }
            }
            
            Log::info("Project manager notifications processed", [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'event_type' => $eventType,
                'managers_count' => $projectManagers->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error notifying project manager", [
                'task_id' => $task->id,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi thông báo cho users được assign
     *
     * @param Task $task
     * @param string $eventType
     * @return void
     */
    private function notifyAssignedUsers(Task $task, string $eventType): void
    {
        try {
            foreach ($task->assignments as $assignment) {
                if (!$assignment->user) {
                    continue;
                }

                $user = $assignment->user;
                $eventKey = $this->mapEventTypeToKey($eventType);
                
                // Kiểm tra notification rules của user
                $applicableRules = $this->notificationRuleService->getApplicableRules(
                    $user->id,
                    $eventKey,
                    $task->project_id,
                    'normal',
                    [
                        'task_id' => $task->id,
                        'project_id' => $task->project_id,
                        'task_name' => $task->name,
                        'assignment_percentage' => $assignment->split_percentage
                    ]
                );

                if ($applicableRules->isNotEmpty()) {
                    // Lấy tất cả channels từ các rules
                    $channels = $applicableRules->pluck('channels')->flatten()->unique();
                    
                    foreach ($channels as $channel) {
                        $this->notificationService->createNotification([
                            'user_id' => $user->id,
                            'priority' => $eventType === 'task_overdue' ? 'critical' : 'normal',
                            'title' => $this->getNotificationTitle($eventType, 'assignee'),
                            'body' => $this->getNotificationBody($eventType, $task, 'assignee'),
                            'link_url' => "/projects/{$task->project_id}/tasks/{$task->id}",
                            'channel' => $channel,
                            'event_key' => $eventKey,
                            'project_id' => $task->project_id,
                            'metadata' => [
                                'task_id' => $task->id,
                                'task_name' => $task->name,
                                'project_name' => $task->project->name ?? null,
                                'assignment_percentage' => $assignment->split_percentage
                            ]
                        ]);
                    }
                }
            }
            
            Log::info("Assigned user notifications processed", [
                'task_id' => $task->id,
                'event_type' => $eventType,
                'assignments_count' => $task->assignments->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error notifying assigned users", [
                'task_id' => $task->id,
                'event_type' => $eventType,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Kiểm tra và gửi thông báo milestone
     *
     * @param Project $project
     * @param array $changedFields
     * @return void
     */
    private function checkAndNotifyMilestones(Project $project, array $changedFields): void
    {
        try {
            $oldProgress = $changedFields['progress']['old'] ?? 0;
            $newProgress = $changedFields['progress']['new'] ?? 0;
            
            $milestones = [25, 50, 75, 100];
            
            foreach ($milestones as $milestone) {
                if ($oldProgress < $milestone && $newProgress >= $milestone) {
                    Log::info("Project milestone reached", [
                        'project_id' => $project->id,
                        'milestone' => $milestone,
                        'progress' => $newProgress
                    ]);
                    
                    // Gửi thông báo milestone cho tất cả thành viên dự án
                    $this->notifyProjectMilestone($project, $milestone, $newProgress);
                    break;
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Error checking milestones", [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Gửi thông báo milestone cho project members
     *
     * @param Project $project
     * @param int $milestone
     * @param float $currentProgress
     * @return void
     */
    private function notifyProjectMilestone(Project $project, int $milestone, float $currentProgress): void
    {
        try {
            // Lấy tất cả thành viên dự án
            $projectMembers = DB::table('project_user_roles')
                ->join('users', 'project_user_roles.user_id', '=', 'users.id')
                ->where('project_user_roles.project_id', $project->id)
                ->select('users.id', 'users.name', 'users.email')
                ->distinct()
                ->get();

            $eventKey = 'Project.MilestoneReached';
            $priority = $milestone === 100 ? 'critical' : 'normal';
            
            foreach ($projectMembers as $member) {
                // Kiểm tra notification rules của member
                $applicableRules = $this->notificationRuleService->getApplicableRules(
                    $member->id,
                    $eventKey,
                    $project->id,
                    $priority,
                    [
                        'project_id' => $project->id,
                        'milestone' => $milestone,
                        'progress' => $currentProgress
                    ]
                );

                if ($applicableRules->isNotEmpty()) {
                    // Lấy tất cả channels từ các rules
                    $channels = $applicableRules->pluck('channels')->flatten()->unique();
                    
                    foreach ($channels as $channel) {
                        $this->notificationService->createNotification([
                            'user_id' => $member->id,
                            'priority' => $priority,
                            'title' => $this->getMilestoneNotificationTitle($milestone),
                            'body' => $this->getMilestoneNotificationBody($project, $milestone, $currentProgress),
                            'link_url' => "/projects/{$project->id}",
                            'channel' => $channel,
                            'event_key' => $eventKey,
                            'project_id' => $project->id,
                            'metadata' => [
                                'project_name' => $project->name,
                                'milestone' => $milestone,
                                'progress' => $currentProgress
                            ]
                        ]);
                    }
                }
            }
            
            Log::info("Milestone notifications sent", [
                'project_id' => $project->id,
                'milestone' => $milestone,
                'members_count' => $projectMembers->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error sending milestone notifications", [
                'project_id' => $project->id,
                'milestone' => $milestone,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Map event type to notification event key
     *
     * @param string $eventType
     * @return string
     */
    private function mapEventTypeToKey(string $eventType): string
    {
        $mapping = [
            'task_completed' => 'Task.Completed',
            'task_ready' => 'Task.Ready',
            'task_assigned' => 'Task.Assigned',
            'task_overdue' => 'Task.Overdue',
        ];

        return $mapping[$eventType] ?? 'Task.Updated';
    }

    /**
     * Lấy title cho notification
     *
     * @param string $eventType
     * @param string $recipientType
     * @return string
     */
    private function getNotificationTitle(string $eventType, string $recipientType): string
    {
        $titles = [
            'task_completed' => [
                'manager' => 'Task đã hoàn thành',
                'assignee' => 'Task của bạn đã hoàn thành'
            ],
            'task_ready' => [
                'manager' => 'Task sẵn sàng thực hiện',
                'assignee' => 'Task mới sẵn sàng cho bạn'
            ],
            'task_assigned' => [
                'manager' => 'Task đã được giao',
                'assignee' => 'Bạn được giao task mới'
            ],
            'task_overdue' => [
                'manager' => 'Task quá hạn',
                'assignee' => 'Task của bạn đã quá hạn'
            ]
        ];

        return $titles[$eventType][$recipientType] ?? 'Thông báo task';
    }

    /**
     * Lấy body cho notification
     *
     * @param string $eventType
     * @param Task $task
     * @param string $recipientType
     * @return string
     */
    private function getNotificationBody(string $eventType, Task $task, string $recipientType): string
    {
        $projectName = $task->project->name ?? 'Dự án';
        
        $bodies = [
            'task_completed' => [
                'manager' => "Task '{$task->name}' trong dự án '{$projectName}' đã được hoàn thành.",
                'assignee' => "Task '{$task->name}' của bạn trong dự án '{$projectName}' đã hoàn thành."
            ],
            'task_ready' => [
                'manager' => "Task '{$task->name}' trong dự án '{$projectName}' đã sẵn sàng để thực hiện.",
                'assignee' => "Task '{$task->name}' trong dự án '{$projectName}' đã sẵn sàng cho bạn thực hiện."
            ],
            'task_assigned' => [
                'manager' => "Task '{$task->name}' trong dự án '{$projectName}' đã được giao.",
                'assignee' => "Bạn được giao task '{$task->name}' trong dự án '{$projectName}'."
            ],
            'task_overdue' => [
                'manager' => "Task '{$task->name}' trong dự án '{$projectName}' đã quá hạn.",
                'assignee' => "Task '{$task->name}' của bạn trong dự án '{$projectName}' đã quá hạn. Vui lòng kiểm tra và cập nhật."
            ]
        ];

        return $bodies[$eventType][$recipientType] ?? "Cập nhật về task '{$task->name}' trong dự án '{$projectName}'.";
    }

    /**
     * Lấy title cho milestone notification
     *
     * @param int $milestone
     * @return string
     */
    private function getMilestoneNotificationTitle(int $milestone): string
    {
        if ($milestone === 100) {
            return '🎉 Dự án hoàn thành!';
        }
        
        return "🎯 Dự án đạt {$milestone}%";
    }

    /**
     * Lấy body cho milestone notification
     *
     * @param Project $project
     * @param int $milestone
     * @param float $currentProgress
     * @return string
     */
    private function getMilestoneNotificationBody(Project $project, int $milestone, float $currentProgress): string
    {
        if ($milestone === 100) {
            return "Chúc mừng! Dự án '{$project->name}' đã hoàn thành với tiến độ {$currentProgress}%.";
        }
        
        return "Dự án '{$project->name}' đã đạt mốc {$milestone}% với tiến độ hiện tại là {$currentProgress}%.";
    }
}