<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Src\CoreProject\Events\ComponentProgressUpdated;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Listener để tự động tính toán lại progress và cost của project
 * khi component progress được cập nhật
 */
class ProgressCalculationListener
{
    /**
     * Xử lý event ComponentProgressUpdated
     */
    public function handle(ComponentProgressUpdated $event): void
    {
        try {
            DB::transaction(function () use ($event) {
                $this->recalculateProjectProgress($event->projectId);
                $this->recalculateProjectCost($event->projectId);
            });
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project progress/cost', [
                'project_id' => $event->projectId,
                'component_id' => $event->componentId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Tính toán lại progress của project dựa trên weighted average
     * của các root components, sử dụng planned_cost làm weight
     */
    private function recalculateProjectProgress(string $projectId): void
    {
        $project = Project::find($projectId);
        if (!$project) {
            return;
        }

        // Lấy tất cả root components (parent_component_id = null)
        $rootComponents = Component::where('project_id', $projectId)
            ->whereNull('parent_component_id')
            ->get();

        if ($rootComponents->isEmpty()) {
            $project->update(['progress' => 0]);
            return;
        }

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($rootComponents as $component) {
            $weight = $component->planned_cost ?? 0;
            $progress = $component->progress_percent ?? 0;
            
            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }

        // Tính weighted average, nếu không có weight thì dùng simple average
        if ($totalWeight > 0) {
            $newProgress = round($weightedProgress / $totalWeight, 2);
        } else {
            $newProgress = round($rootComponents->avg('progress_percent'), 2);
        }

        $project->update(['progress' => $newProgress]);
    }

    /**
     * Tính toán lại actual_cost của project
     * bằng tổng actual_cost của tất cả root components
     */
    private function recalculateProjectCost(string $projectId): void
    {
        $project = Project::find($projectId);
        if (!$project) {
            return;
        }

        $totalActualCost = Component::where('project_id', $projectId)
            ->whereNull('parent_component_id')
            ->sum('actual_cost');

        $project->update(['actual_cost' => $totalActualCost]);
    }
}
