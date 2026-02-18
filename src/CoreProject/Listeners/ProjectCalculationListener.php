<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Events\ComponentCostUpdated;
use Src\CoreProject\Events\ProjectProgressUpdated;
use Src\CoreProject\Events\ProjectCostUpdated;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\Foundation\EventBus;

/**
 * ProjectCalculationListener
 * 
 * Xử lý tính toán lại progress và cost của project khi component thay đổi
 * Sử dụng EventBus để dispatch events mới
 */
class ProjectCalculationListener
{
    protected EventBus $eventBus;
    
    public function __construct(EventBus $eventBus)
    {
        $this->eventBus = $eventBus;
    }

    /**
     * Xử lý event ComponentProgressUpdated
     * 
     * @param ComponentProgressUpdated $event
     * @return void
     */
    public function handleComponentProgressUpdated(ComponentProgressUpdated $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                $this->recalculateProjectProgress($event->projectId, $event->actorId);
            });
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project progress', [
                'project_id' => $event->projectId,
                'component_id' => $event->entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Xử lý event ComponentCostUpdated
     * 
     * @param ComponentCostUpdated $event
     * @return void
     */
    public function handleComponentCostUpdated(ComponentCostUpdated $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                $this->recalculateProjectCost($event->projectId, $event->actorId);
            });
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project cost', [
                'project_id' => $event->projectId,
                'component_id' => $event->entityId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Tính toán lại progress của project
     * 
     * @param int $projectId
     * @param int $actorId
     * @return void
     */
    protected function recalculateProjectProgress(string $projectId, string $actorId): void
    {
        $project = Project::findOrFail($projectId);
        $oldProgress = $project->progress;
        
        // Lấy tất cả root components (parent_component_id = null)
        $rootComponents = Component::where('project_id', $projectId)
            ->whereNull('parent_component_id')
            ->get();
        
        if ($rootComponents->isEmpty()) {
            return;
        }
        
        // Tính weighted average progress
        $totalWeight = $rootComponents->sum('planned_cost');
        
        if ($totalWeight <= 0) {
            return;
        }
        
        $weightedProgress = $rootComponents->sum(function ($component) {
            return $component->progress_percent * $component->planned_cost;
        });
        
        $newProgress = round($weightedProgress / $totalWeight, 2);
        
        // Cập nhật nếu có thay đổi
        if ($oldProgress !== $newProgress) {
            $project->update(['progress' => $newProgress]);
            
            // Dispatch ProjectProgressUpdated event
            $this->eventBus->publish('Project.Progress.Updated', [
                'entityId' => $projectId,
                'projectId' => $projectId,
                'actorId' => $actorId,
                'changedFields' => [
                    'progress' => [
                        'old' => $oldProgress,
                        'new' => $newProgress
                    ]
                ],
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Tính toán lại cost của project
     * 
     * @param int $projectId
     * @param int $actorId
     * @return void
     */
    protected function recalculateProjectCost(string $projectId, string $actorId): void
    {
        $project = Project::findOrFail($projectId);
        $oldCost = $project->actual_cost;
        
        // Tính tổng actual_cost của tất cả root components
        $newCost = Component::where('project_id', $projectId)
            ->whereNull('parent_component_id')
            ->sum('actual_cost');
        
        // Cập nhật nếu có thay đổi
        if ($oldCost !== $newCost) {
            $project->forceFill([
                'budget_actual' => $newCost,
                'actual_cost' => $newCost,
            ])->save();
            
            // Dispatch ProjectCostUpdated event
            $this->eventBus->publish('Project.Cost.Updated', [
                'entityId' => $projectId,
                'projectId' => $projectId,
                'actorId' => $actorId,
                'changedFields' => [
                    'actual_cost' => [
                        'old' => $oldCost,
                        'new' => $newCost
                    ]
                ],
                'timestamp' => now()->toISOString()
            ]);
        }
    }

    /**
     * Generic handle method for EventBus compatibility
     * 
     * @param mixed $payload Event payload
     * @return void
     */
    public function handle($payload): void
    {
        // Determine event type and route to appropriate handler
        if ($payload instanceof ComponentProgressUpdated) {
            $this->handleComponentProgressUpdated($payload);
        } elseif ($payload instanceof ComponentCostUpdated) {
            $this->handleComponentCostUpdated($payload);
        } else {
            Log::warning('Unknown event type in ProjectCalculationListener', [
                'payload_type' => get_class($payload),
                'payload' => $payload
            ]);
        }
    }
}
