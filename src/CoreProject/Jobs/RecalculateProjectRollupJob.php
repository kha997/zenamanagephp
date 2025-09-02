<?php declare(strict_types=1);

namespace Src\CoreProject\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Events\ProjectRollupUpdated;

/**
 * Job để tính toán lại progress và cost của project
 * dựa trên các components con (roll-up calculation)
 * 
 * Job này được dispatch khi có thay đổi trong component
 * để đảm bảo tính nhất quán của dữ liệu project
 */
class RecalculateProjectRollupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Số lần retry tối đa
     */
    public int $tries = 3;

    /**
     * Timeout cho job (seconds)
     */
    public int $timeout = 120;

    /**
     * @param int $projectId ID của project cần tính toán lại
     * @param int $triggerComponentId ID của component trigger job này
     * @param int $actorId ID của user thực hiện thay đổi
     * @param int $tenantId ID của tenant
     */
    public function __construct(
        public readonly int $projectId,
        public readonly int $triggerComponentId,
        public readonly int $actorId,
        public readonly int $tenantId
    ) {
        // Set queue name dựa trên tenant để phân tải
        $this->onQueue('project-rollup-' . $tenantId);
    }

    /**
     * Thực thi job
     */
    public function handle(): void
    {
        try {
            DB::beginTransaction();

            $project = Project::lockForUpdate()->find($this->projectId);
            
            if (!$project) {
                Log::warning('Project not found for rollup calculation', [
                    'project_id' => $this->projectId,
                    'trigger_component_id' => $this->triggerComponentId
                ]);
                return;
            }

            // Lưu giá trị cũ để so sánh
            $oldProgress = $project->progress;
            $oldCost = $project->actual_cost;

            // Tính toán progress và cost mới
            $rollupData = $this->calculateProjectRollup($project);
            
            // Cập nhật project nếu có thay đổi
            $hasChanges = false;
            if (abs($rollupData['progress'] - $oldProgress) >= 0.01) {
                $project->progress = $rollupData['progress'];
                $hasChanges = true;
            }
            
            if (abs($rollupData['cost'] - $oldCost) >= 0.01) {
                $project->actual_cost = $rollupData['cost'];
                $hasChanges = true;
            }

            if ($hasChanges) {
                $project->save();
                
                // Dispatch event để thông báo về thay đổi
                ProjectRollupUpdated::dispatch(
                    $this->projectId,
                    $this->actorId,
                    $this->tenantId,
                    $oldProgress,
                    $rollupData['progress'],
                    $oldCost,
                    $rollupData['cost'],
                    $this->triggerComponentId,
                    $rollupData['affected_components'],
                    new \DateTime()
                );

                Log::info('Project rollup calculation completed', [
                    'project_id' => $this->projectId,
                    'old_progress' => $oldProgress,
                    'new_progress' => $rollupData['progress'],
                    'old_cost' => $oldCost,
                    'new_cost' => $rollupData['cost'],
                    'affected_components_count' => count($rollupData['affected_components'])
                ]);
            }

            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to calculate project rollup', [
                'project_id' => $this->projectId,
                'trigger_component_id' => $this->triggerComponentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Tính toán roll-up progress và cost cho project
     */
    private function calculateProjectRollup(Project $project): array
    {
        // Lấy tất cả root components (không có parent)
        $rootComponents = Component::where('project_id', $project->id)
            ->whereNull('parent_component_id')
            ->get();

        if ($rootComponents->isEmpty()) {
            return [
                'progress' => 0.0,
                'cost' => 0.0,
                'affected_components' => []
            ];
        }

        $totalPlannedCost = 0;
        $weightedProgress = 0;
        $totalActualCost = 0;
        $affectedComponents = [];

        foreach ($rootComponents as $component) {
            // Tính toán recursive cho component tree
            $componentData = $this->calculateComponentRollup($component);
            
            $plannedCost = $componentData['planned_cost'] ?: 0;
            $progress = $componentData['progress'] ?: 0;
            $actualCost = $componentData['actual_cost'] ?: 0;
            
            $totalPlannedCost += $plannedCost;
            $weightedProgress += ($progress * $plannedCost);
            $totalActualCost += $actualCost;
            
            $affectedComponents[] = [
                'id' => $component->id,
                'name' => $component->name,
                'progress' => $progress,
                'planned_cost' => $plannedCost,
                'actual_cost' => $actualCost
            ];
        }

        // Tính progress trung bình có trọng số
        $averageProgress = $totalPlannedCost > 0 
            ? $weightedProgress / $totalPlannedCost 
            : 0;

        return [
            'progress' => round($averageProgress, 2),
            'cost' => round($totalActualCost, 2),
            'affected_components' => $affectedComponents
        ];
    }

    /**
     * Tính toán recursive cho component và các con của nó
     */
    private function calculateComponentRollup(Component $component): array
    {
        $children = Component::where('parent_component_id', $component->id)->get();
        
        if ($children->isEmpty()) {
            // Leaf component - trả về giá trị trực tiếp
            return [
                'progress' => $component->progress_percent ?: 0,
                'planned_cost' => $component->planned_cost ?: 0,
                'actual_cost' => $component->actual_cost ?: 0
            ];
        }
        
        // Parent component - tính toán từ children
        $totalPlannedCost = 0;
        $weightedProgress = 0;
        $totalActualCost = 0;
        
        foreach ($children as $child) {
            $childData = $this->calculateComponentRollup($child);
            
            $plannedCost = $childData['planned_cost'] ?: 0;
            $progress = $childData['progress'] ?: 0;
            $actualCost = $childData['actual_cost'] ?: 0;
            
            $totalPlannedCost += $plannedCost;
            $weightedProgress += ($progress * $plannedCost);
            $totalActualCost += $actualCost;
        }
        
        $averageProgress = $totalPlannedCost > 0 
            ? $weightedProgress / $totalPlannedCost 
            : 0;
            
        return [
            'progress' => round($averageProgress, 2),
            'planned_cost' => $totalPlannedCost,
            'actual_cost' => $totalActualCost
        ];
    }

    /**
     * Xử lý khi job failed
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('RecalculateProjectRollupJob failed permanently', [
            'project_id' => $this->projectId,
            'trigger_component_id' => $this->triggerComponentId,
            'actor_id' => $this->actorId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);
    }

    /**
     * Unique ID cho job để tránh duplicate
     */
    public function uniqueId(): string
    {
        return "project-rollup-{$this->projectId}-{$this->triggerComponentId}";
    }
}