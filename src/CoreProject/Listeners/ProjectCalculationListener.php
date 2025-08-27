<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\Foundation\EventBus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Listener tổng hợp để xử lý tính toán progress và cost của project
 * Thay thế các listener trùng lặp khác
 */
class ProjectCalculationListener
{
    /**
     * Xử lý các sự kiện liên quan đến component progress/cost
     *
     * @param array $payload
     * @return void
     */
    public function handle(array $payload): void
    {
        $eventName = $payload['eventName'] ?? '';
        $projectId = $payload['projectId'] ?? null;
        
        if (!$projectId) {
            Log::warning('Missing projectId in event payload', ['event' => $eventName]);
            return;
        }
        
        try {
            DB::transaction(function () use ($projectId, $eventName, $payload) {
                $this->recalculateProject($projectId, $eventName, $payload);
            });
            
            Log::info('Project calculations completed', [
                'project_id' => $projectId,
                'event' => $eventName,
                'actor' => $payload['actorId'] ?? 'system'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project metrics', [
                'project_id' => $projectId,
                'event' => $eventName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Tính toán lại các metrics của project
     *
     * @param int $projectId
     * @param string $eventName
     * @param array $payload
     * @return void
     */
    private function recalculateProject(int $projectId, string $eventName, array $payload): void
    {
        $project = Project::find($projectId);
        if (!$project) {
            Log::warning("Project not found: {$projectId}");
            return;
        }
        
        // Cache key để tránh tính toán trùng lặp trong cùng request
        $cacheKey = "project_calc_{$projectId}_" . md5($eventName . serialize($payload));
        
        if (Cache::has($cacheKey)) {
            return; // Đã tính toán trong request này
        }
        
        $oldProgress = $project->progress;
        $oldCost = $project->actual_cost;
        
        // Tính toán progress và cost
        $newProgress = $this->calculateProjectProgress($project);
        $newCost = $this->calculateProjectCost($project);
        
        // Cập nhật nếu có thay đổi
        $updates = [];
        if (abs($oldProgress - $newProgress) > 0.01) { // Tolerance cho floating point
            $updates['progress'] = $newProgress;
        }
        if (abs($oldCost - $newCost) > 0.01) {
            $updates['actual_cost'] = $newCost;
        }
        
        if (!empty($updates)) {
            $project->update($updates);
            
            // Phát các sự kiện tương ứng
            if (isset($updates['progress'])) {
                EventBus::publish('Project.Project.ProgressUpdated', [
                    'entityId' => $project->id,
                    'projectId' => $project->id,
                    'actorId' => $payload['actorId'] ?? 'system',
                    'changedFields' => ['progress' => ['old' => $oldProgress, 'new' => $newProgress]],
                    'triggeredBy' => $eventName
                ]);
            }
            
            if (isset($updates['actual_cost'])) {
                EventBus::publish('Project.Project.CostUpdated', [
                    'entityId' => $project->id,
                    'projectId' => $project->id,
                    'actorId' => $payload['actorId'] ?? 'system',
                    'changedFields' => ['actual_cost' => ['old' => $oldCost, 'new' => $newCost]],
                    'triggeredBy' => $eventName
                ]);
            }
        }
        
        // Cache để tránh tính toán trùng lặp
        Cache::put($cacheKey, true, 60); // Cache 1 phút
    }
    
    /**
     * Tính toán progress của project dựa trên weighted average
     *
     * @param Project $project
     * @return float
     */
    private function calculateProjectProgress(Project $project): float
    {
        $rootComponents = $project->rootComponents;
        
        if ($rootComponents->isEmpty()) {
            return 0.0;
        }
        
        $totalWeight = 0;
        $weightedProgress = 0;
        
        foreach ($rootComponents as $component) {
            $weight = max($component->planned_cost ?? 0, 1); // Tối thiểu weight = 1
            $progress = $component->progress_percent ?? 0;
            
            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }
        
        return $totalWeight > 0 ? round($weightedProgress / $totalWeight, 2) : 0.0;
    }
    
    /**
     * Tính toán actual cost của project
     *
     * @param Project $project
     * @return float
     */
    private function calculateProjectCost(Project $project): float
    {
        return $project->rootComponents()->sum('actual_cost') ?? 0.0;
    }
    
    /**
     * Xử lý sự kiện ComponentProgressUpdated (backward compatibility)
     *
     * @param \Src\CoreProject\Events\ComponentProgressUpdated $event
     * @return void
     */
    public function handleComponentProgressUpdated($event): void
    {
        $payload = [
            'eventName' => 'Project.Component.ProgressUpdated',
            'entityId' => $event->componentId,
            'projectId' => $event->projectId,
            'actorId' => $event->actorId,
            'changedFields' => $event->changedFields ?? []
        ];
        
        $this->handle($payload);
    }
}