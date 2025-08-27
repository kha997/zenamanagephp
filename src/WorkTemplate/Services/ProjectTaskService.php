<?php declare(strict_types=1);

namespace Src\WorkTemplate\Services;

use Src\WorkTemplate\Models\ProjectTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Carbon\Carbon;

/**
 * Service class for handling project task operations
 * Manages task updates, conditional visibility, and progress tracking
 */
class ProjectTaskService
{
    /**
     * Update task with validation and event dispatching
     *
     * @param ProjectTask $task
     * @param array $data
     * @param string $userId
     * @return ProjectTask
     * @throws \Exception
     */
    public function updateTask(ProjectTask $task, array $data, string $userId): ProjectTask
    {
        return DB::transaction(function () use ($task, $data, $userId) {
            // Validate progress percent
            if (isset($data['progress_percent'])) {
                $data['progress_percent'] = max(0, min(100, $data['progress_percent']));
            }
            
            // Set updated_by
            $data['updated_by'] = $userId;
            
            // Update task
            $task->update($data);
            
            // Dispatch event for progress tracking
            if (isset($data['progress_percent']) || isset($data['status'])) {
                Event::dispatch('task.progress.updated', [
                    'task_id' => $task->id,
                    'project_id' => $task->project_id,
                    'progress_percent' => $task->progress_percent,
                    'status' => $task->status,
                    'updated_by' => $userId
                ]);
            }
            
            return $task->fresh();
        });
    }
    
    /**
     * Toggle conditional tag visibility for a task
     *
     * @param ProjectTask $task
     * @param bool $isVisible
     * @param string $userId
     * @return array
     * @throws \Exception
     */
    public function toggleConditionalVisibility(ProjectTask $task, bool $isVisible, string $userId): array
    {
        if (!$task->hasConditionalTag()) {
            throw new \Exception('Task does not have conditional tag');
        }
        
        return DB::transaction(function () use ($task, $isVisible, $userId) {
            $oldVisibility = !$task->is_hidden;
            
            $task->update([
                'is_hidden' => !$isVisible,
                'updated_by' => $userId
            ]);
            
            // Dispatch event
            Event::dispatch('task.visibility.toggled', [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'conditional_tag' => $task->conditional_tag,
                'old_visibility' => $oldVisibility,
                'new_visibility' => $isVisible,
                'updated_by' => $userId
            ]);
            
            return [
                'task_id' => $task->id,
                'conditional_tag' => $task->conditional_tag,
                'is_visible' => $isVisible,
                'updated_at' => $task->updated_at
            ];
        });
    }
    
    /**
     * Update task progress percentage
     *
     * @param ProjectTask $task
     * @param float $progressPercent
     * @param string $userId
     * @return ProjectTask
     * @throws \Exception
     */
    public function updateProgress(ProjectTask $task, float $progressPercent, string $userId): ProjectTask
    {
        // Validate progress range
        $progressPercent = max(0, min(100, $progressPercent));
        
        return DB::transaction(function () use ($task, $progressPercent, $userId) {
            $oldProgress = $task->progress_percent;
            
            $task->update([
                'progress_percent' => $progressPercent,
                'updated_by' => $userId
            ]);
            
            // Auto-update status based on progress
            if ($progressPercent == 0 && $task->status !== ProjectTask::STATUS_PENDING) {
                $task->update(['status' => ProjectTask::STATUS_PENDING]);
            } elseif ($progressPercent > 0 && $progressPercent < 100 && $task->status === ProjectTask::STATUS_PENDING) {
                $task->update(['status' => ProjectTask::STATUS_IN_PROGRESS]);
            } elseif ($progressPercent == 100 && $task->status !== ProjectTask::STATUS_COMPLETED) {
                $task->update(['status' => ProjectTask::STATUS_COMPLETED]);
            }
            
            // Dispatch progress update event
            Event::dispatch('task.progress.updated', [
                'task_id' => $task->id,
                'project_id' => $task->project_id,
                'old_progress' => $oldProgress,
                'new_progress' => $progressPercent,
                'status' => $task->status,
                'updated_by' => $userId
            ]);
            
            return $task->fresh();
        });
    }
    
    /**
     * Bulk update task visibility based on conditional tags
     *
     * @param string $projectId
     * @param array $activeTags
     * @param string $userId
     * @return array
     */
    public function updateConditionalVisibility(string $projectId, array $activeTags, string $userId): array
    {
        return DB::transaction(function () use ($projectId, $activeTags, $userId) {
            $tasks = ProjectTask::where('project_id', $projectId)
                ->whereNotNull('conditional_tag')
                ->get();
            
            $updated = [];
            
            foreach ($tasks as $task) {
                $shouldBeVisible = in_array($task->conditional_tag, $activeTags);
                $currentlyVisible = !$task->is_hidden;
                
                if ($shouldBeVisible !== $currentlyVisible) {
                    $task->update([
                        'is_hidden' => !$shouldBeVisible,
                        'updated_by' => $userId
                    ]);
                    
                    $updated[] = [
                        'task_id' => $task->id,
                        'name' => $task->name,
                        'conditional_tag' => $task->conditional_tag,
                        'visibility_changed' => true,
                        'now_visible' => $shouldBeVisible
                    ];
                }
            }
            
            // Dispatch bulk update event
            if (!empty($updated)) {
                Event::dispatch('tasks.conditional.bulk_updated', [
                    'project_id' => $projectId,
                    'active_tags' => $activeTags,
                    'updated_tasks' => $updated,
                    'updated_by' => $userId
                ]);
            }
            
            return [
                'project_id' => $projectId,
                'active_tags' => $activeTags,
                'updated_tasks' => $updated,
                'total_updated' => count($updated)
            ];
        });
    }
}