<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Src\Foundation\Helpers\AuthHelper;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\Foundation\Events\EventBus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listener xử lý tính toán lại progress của project khi component thay đổi
 */
class ProjectProgressListener
{
    /**
     * Xử lý sự kiện ComponentProgressUpdated
     *
     * @param array $payload
     * @return void
     */
    public function handleComponentProgressUpdated(array $payload): void
    {
        try {
            $projectId = $payload['projectId'];
            $project = Project::find($projectId);
            
            if (!$project) {
                Log::warning("Project not found for progress calculation: {$projectId}");
                return;
            }

            // Tính toán lại progress của project dựa trên weighted average của components
            $this->recalculateProjectProgress($project);
            
            Log::info("Project progress recalculated", [
                'project_id' => $projectId,
                'new_progress' => $project->progress,
                'triggered_by' => $payload['actorId']
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error recalculating project progress", [
                'project_id' => $payload['projectId'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Xử lý sự kiện ComponentCostUpdated
     *
     * @param array $payload
     * @return void
     */
    public function handleComponentCostUpdated(array $payload): void
    {
        try {
            $projectId = $payload['projectId'];
            $project = Project::find($projectId);
            
            if (!$project) {
                Log::warning("Project not found for cost calculation: {$projectId}");
                return;
            }

            // Tính toán lại actual_cost của project
            $this->recalculateProjectCost($project);
            
            Log::info("Project cost recalculated", [
                'project_id' => $projectId,
                'new_actual_cost' => $project->actual_cost,
                'triggered_by' => $payload['actorId']
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error recalculating project cost", [
                'project_id' => $payload['projectId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tính toán lại progress của project dựa trên weighted average
     *
     * @param Project $project
     * @return void
     */
    private function recalculateProjectProgress(Project $project): void
    {
        $rootComponents = $project->rootComponents;
        
        if ($rootComponents->isEmpty()) {
            $project->update(['progress' => 0]);
            return;
        }

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($rootComponents as $component) {
            $weight = $component->planned_cost ?: 1; // Sử dụng planned_cost làm weight
            $totalWeight += $weight;
            $weightedProgress += ($component->progress_percent * $weight);
        }

        $newProgress = $totalWeight > 0 ? round($weightedProgress / $totalWeight, 2) : 0;
        
        $project->update(['progress' => $newProgress]);
        
        // Phát sự kiện ProjectProgressUpdated
        EventBus::publish('Project.Project.ProgressUpdated', [
            'entityId' => $project->id,
            'projectId' => $project->id,
            'actorId' => $this->resolveActorId(),
            'changedFields' => ['progress' => ['old' => $project->getOriginal('progress'), 'new' => $newProgress]],
            'timestamp' => now()->toISOString(),
            'eventId' => uniqid('event_', true)
        ]);
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
            Log::warning('Could not resolve actor ID in ProjectProgressListener', [
                'error' => $e->getMessage()
            ]);
            return 'system';
        }
    }

    /**
     * Tính toán lại actual_cost của project
     *
     * @param Project $project
     * @return void
     */
    private function recalculateProjectCost(Project $project): void
    {
        $totalActualCost = $project->rootComponents()->sum('actual_cost');
        
        $oldCost = $project->actual_cost;
        $project->update(['actual_cost' => $totalActualCost]);
        
        // Phát sự kiện ProjectCostUpdated
        EventBus::publish('Project.Project.CostUpdated', [
            'entityId' => $project->id,
            'projectId' => $project->id,
            'actorId' => $this->resolveActorId(),
            'changedFields' => ['actual_cost' => ['old' => $oldCost, 'new' => $totalActualCost]],
            'timestamp' => now()->toISOString(),
            'eventId' => uniqid('event_', true)
        ]);
    }
}