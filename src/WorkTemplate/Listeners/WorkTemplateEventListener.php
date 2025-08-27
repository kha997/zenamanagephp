<?php declare(strict_types=1);

namespace Src\WorkTemplate\Listeners;

use Src\WorkTemplate\Events\TemplateApplied;
use Src\WorkTemplate\Events\TaskConditionalToggled;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Listener xử lý các events của WorkTemplate module
 * Bao gồm việc áp dụng template và toggle conditional tasks
 */
class WorkTemplateEventListener
{
    /**
     * Xử lý event khi template được áp dụng vào project
     * 
     * @param TemplateApplied $event
     * @return void
     */
    public function handleTemplateApplied(TemplateApplied $event): void
    {
        $statistics = $event->getStatistics();
        
        Log::info('Template applied to project', [
            'template_id' => $event->template->id,
            'template_name' => $event->template->name,
            'project_id' => $event->project->id,
            'project_name' => $event->project->name,
            'phases_created' => $statistics['phases_created'],
            'tasks_created' => $statistics['total_tasks_created'],
            'visible_tasks' => $statistics['visible_tasks'],
            'hidden_tasks' => $statistics['hidden_tasks'],
            'is_partial_sync' => $statistics['is_partial_sync'],
            'applied_by' => $event->actorId
        ]);

        // Xóa cache liên quan đến project structure
        $this->clearProjectCache($event->project->id);
        
        // TODO: Có thể thêm logic gửi notification cho project team
        // TODO: Có thể thêm logic cập nhật project timeline
        // TODO: Có thể thêm logic tính toán lại project progress nếu cần
    }

    /**
     * Xử lý event khi toggle conditional task visibility
     * 
     * @param TaskConditionalToggled $event
     * @return void
     */
    public function handleTaskConditionalToggled(TaskConditionalToggled $event): void
    {
        $action = $event->newVisibility ? 'shown' : 'hidden';
        
        Log::info('Task conditional visibility toggled', [
            'task_id' => $event->task->id,
            'task_name' => $event->task->name,
            'project_id' => $event->project->id,
            'conditional_tag' => $event->conditionalTag,
            'action' => $action,
            'previous_visibility' => $event->previousVisibility,
            'new_visibility' => $event->newVisibility,
            'affected_tasks_count' => $event->affectedTasksCount,
            'toggled_by' => $event->actorId
        ]);

        // Xóa cache liên quan đến project tasks
        $this->clearProjectCache($event->project->id);
        
        // TODO: Có thể thêm logic cập nhật project progress khi tasks được show/hide
        // TODO: Có thể thêm logic gửi notification cho assigned users
        // TODO: Có thể thêm logic cập nhật project timeline nếu cần
    }

    /**
     * Xóa cache liên quan đến project
     * 
     * @param int $projectId
     * @return void
     */
    private function clearProjectCache(int $projectId): void
    {
        $cacheKeys = [
            "project.{$projectId}.tasks",
            "project.{$projectId}.phases",
            "project.{$projectId}.structure",
            "project.{$projectId}.progress"
        ];
        
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }

    /**
     * Đăng ký các event listeners
     * 
     * @param $events
     * @return void
     */
    public function subscribe($events): void
    {
        $events->listen(
            TemplateApplied::class,
            [WorkTemplateEventListener::class, 'handleTemplateApplied']
        );

        $events->listen(
            TaskConditionalToggled::class,
            [WorkTemplateEventListener::class, 'handleTaskConditionalToggled']
        );
    }
}