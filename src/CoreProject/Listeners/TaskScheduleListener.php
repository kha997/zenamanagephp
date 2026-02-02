<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Services\TaskService;
use Src\Foundation\Events\EventBus;
use Illuminate\Support\Facades\Log;

/**
 * Listener xử lý cập nhật lịch trình task khi có thay đổi
 */
class TaskScheduleListener
{
    private TaskService $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }
    
    /**
     * Giải quyết ID của actor hiện tại
     *
     * @return string
     */
    private function resolveActorId(): string
    {
        try {
            return AuthHelper::idOrSystem();
        } catch (\Throwable $e) {
            Log::warning('Could not resolve actor ID in TaskScheduleListener', [
                'error' => $e->getMessage()
            ]);
            return 'system';
        }
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
            $projectId = $payload['projectId'];
            
            // Tìm các task phụ thuộc vào task vừa hoàn thành
            $dependentTasks = Task::where('project_id', $projectId)
                ->whereJsonContains('dependencies_json', $taskId)
                ->get();

            foreach ($dependentTasks as $task) {
                // Kiểm tra xem tất cả dependencies đã hoàn thành chưa
                if ($this->areAllDependenciesCompleted($task)) {
                    // Cập nhật trạng thái task thành ready
                    $task->update(['status' => 'ready']);
                    
                    // Phát sự kiện TaskReady
                    EventBus::publish('Project.Task.Ready', [
                        'entityId' => $task->id,
                        'projectId' => $projectId,
                        'actorId' => $payload['actorId'],
                        'changedFields' => ['status' => ['old' => $task->getOriginal('status'), 'new' => 'ready']],
                        'timestamp' => now()->toISOString(),
                        'eventId' => uniqid('event_', true)
                    ]);
                }
            }
            
            // Tính toán lại critical path của project
            $this->recalculateCriticalPath($projectId);
            
            Log::info("Task completion processed", [
                'task_id' => $taskId,
                'project_id' => $projectId,
                'dependent_tasks_updated' => $dependentTasks->count()
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error processing task completion", [
                'task_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý sự kiện ChangeRequestApproved để cập nhật lịch trình
     *
     * @param array $payload
     * @return void
     */
    public function handleChangeRequestApproved(array $payload): void
    {
        try {
            $projectId = $payload['projectId'];
            $impactData = $payload['impactData'] ?? [];
            
            if (isset($impactData['impact_days']) && $impactData['impact_days'] > 0) {
                // Cập nhật lịch trình các task bị ảnh hưởng
                $this->updateTaskScheduleForDelay($projectId, $impactData['impact_days']);
            }
            
            Log::info("Change request impact applied to schedule", [
                'project_id' => $projectId,
                'impact_days' => $impactData['impact_days'] ?? 0
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error applying change request to schedule", [
                'project_id' => $payload['projectId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Kiểm tra xem tất cả dependencies của task đã hoàn thành chưa
     *
     * @param Task $task
     * @return bool
     */
    private function areAllDependenciesCompleted(Task $task): bool
    {
        $dependencies = $task->dependencies_json ?? [];
        
        if (empty($dependencies)) {
            return true;
        }

        $completedCount = Task::whereIn('id', $dependencies)
            ->where('status', 'completed')
            ->count();
            
        return $completedCount === count($dependencies);
    }

    /**
     * Tính toán lại critical path của project
     *
     * @param string $projectId
     * @return void
     */
    private function recalculateCriticalPath(string $projectId): void
    {
        try {
            $criticalPath = $this->taskService->getCriticalPath($projectId);
            
            // Phát sự kiện CriticalPathUpdated
            EventBus::publish('Project.Schedule.CriticalPathUpdated', [
                'entityId' => $projectId,
                'projectId' => $projectId,
                'actorId' => $this->resolveActorId(),
                'changedFields' => ['critical_path' => $criticalPath],
                'timestamp' => now()->toISOString(),
                'eventId' => uniqid('event_', true)
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error recalculating critical path", [
                'project_id' => $projectId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cập nhật lịch trình task khi có delay
     *
     * @param string $projectId
     * @param int $delayDays
     * @return void
     */
    private function updateTaskScheduleForDelay(string $projectId, int $delayDays): void
    {
        // Lấy các task chưa bắt đầu hoặc đang thực hiện
        $affectedTasks = Task::where('project_id', $projectId)
            ->whereIn('status', ['pending', 'in_progress'])
            ->get();

        foreach ($affectedTasks as $task) {
            $newStartDate = $task->start_date ? 
                date('Y-m-d', strtotime($task->start_date . " +{$delayDays} days")) : 
                null;
            $newEndDate = $task->end_date ? 
                date('Y-m-d', strtotime($task->end_date . " +{$delayDays} days")) : 
                null;
                
            $task->update([
                'start_date' => $newStartDate,
                'end_date' => $newEndDate
            ]);
        }
    }
}
