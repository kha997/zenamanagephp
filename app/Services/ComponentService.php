<?php

namespace App\Services;

use App\Models\Component;
use App\Models\Project;
use App\Models\Task;
use App\Models\InteractionLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComponentService
{
    /**
     * Create a new component
     */
    public function createComponent(array $data): Component
    {
        DB::beginTransaction();
        
        try {
            $component = Component::create([
                'id' => \Str::ulid(),
                'tenant_id' => $data['tenant_id'],
                'project_id' => $data['project_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? 'general',
                'status' => $data['status'] ?? 'planning',
                'priority' => $data['priority'] ?? 'medium',
                'progress' => 0,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'budget' => $data['budget'] ?? 0,
                'actual_cost' => 0,
                'dependencies' => $data['dependencies'] ?? [],
                'metadata' => $data['metadata'] ?? [],
                'created_by' => $data['created_by'],
            ]);

            // Log the creation
            $this->logInteraction([
                'tenant_id' => $component->tenant_id,
                'user_id' => $data['created_by'],
                'project_id' => $component->project_id,
                'component_id' => $component->id,
                'type' => 'component_created',
                'content' => "Component '{$component->name}' was created",
                'metadata' => ['component_name' => $component->name],
                'is_internal' => false,
            ]);

            DB::commit();
            return $component;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create component: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update component
     */
    public function updateComponent(string $componentId, array $data): Component
    {
        DB::beginTransaction();
        
        try {
            $component = Component::findOrFail($componentId);
            $oldData = $component->toArray();
            
            $component->update($data);
            
            // Log the update
            $this->logInteraction([
                'tenant_id' => $component->tenant_id,
                'user_id' => $data['updated_by'] ?? auth()->id(),
                'project_id' => $component->project_id,
                'component_id' => $component->id,
                'type' => 'component_updated',
                'content' => "Component '{$component->name}' was updated",
                'metadata' => [
                    'old_data' => $oldData,
                    'new_data' => $component->toArray(),
                    'changes' => array_diff_assoc($component->toArray(), $oldData)
                ],
                'is_internal' => false,
            ]);

            DB::commit();
            return $component;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update component: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Delete component
     */
    public function deleteComponent(string $componentId, int $deletedBy): bool
    {
        DB::beginTransaction();
        
        try {
            $component = Component::findOrFail($componentId);
            $componentName = $component->name;
            
            // Check if component has tasks
            $taskCount = Task::where('component_id', $componentId)->count();
            if ($taskCount > 0) {
                throw new \Exception("Cannot delete component with {$taskCount} tasks. Please reassign or delete tasks first.");
            }
            
            $component->delete();
            
            // Log the deletion
            $this->logInteraction([
                'tenant_id' => $component->tenant_id,
                'user_id' => $deletedBy,
                'project_id' => $component->project_id,
                'component_id' => $component->id,
                'type' => 'component_deleted',
                'content' => "Component '{$componentName}' was deleted",
                'metadata' => ['component_name' => $componentName],
                'is_internal' => false,
            ]);

            DB::commit();
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete component: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate component progress based on tasks
     */
    public function calculateProgress(string $componentId): float
    {
        $component = Component::findOrFail($componentId);
        
        $tasks = Task::where('component_id', $componentId)->get();
        
        if ($tasks->isEmpty()) {
            return 0;
        }
        
        $totalWeight = 0;
        $completedWeight = 0;
        
        foreach ($tasks as $task) {
            $weight = $task->weight ?? 1; // Default weight is 1
            $totalWeight += $weight;
            
            if ($task->status === 'completed') {
                $completedWeight += $weight;
            } elseif ($task->status === 'in_progress') {
                $completedWeight += $weight * ($task->progress / 100);
            }
        }
        
        $progress = $totalWeight > 0 ? ($completedWeight / $totalWeight) * 100 : 0;
        
        // Update component progress
        $component->update(['progress' => round($progress, 2)]);
        
        return $progress;
    }

    /**
     * Get component dependencies
     */
    public function getDependencies(string $componentId): Collection
    {
        $component = Component::findOrFail($componentId);
        $dependencyIds = $component->dependencies ?? [];
        
        if (empty($dependencyIds)) {
            return collect();
        }
        
        return Component::whereIn('id', $dependencyIds)->get();
    }

    /**
     * Check if component can be started (dependencies completed)
     */
    public function canStart(string $componentId): bool
    {
        $dependencies = $this->getDependencies($componentId);
        
        foreach ($dependencies as $dependency) {
            if ($dependency->status !== 'completed') {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get component statistics
     */
    public function getStatistics(string $componentId): array
    {
        $component = Component::findOrFail($componentId);
        
        $tasks = Task::where('component_id', $componentId)->get();
        
        $stats = [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'pending_tasks' => $tasks->where('status', 'pending')->count(),
            'overdue_tasks' => $tasks->where('end_date', '<', now())->where('status', '!=', 'completed')->count(),
            'progress_percentage' => $component->progress,
            'budget_utilization' => $component->budget > 0 ? ($component->actual_cost / $component->budget) * 100 : 0,
            'estimated_completion' => $this->estimateCompletion($component),
        ];
        
        return $stats;
    }

    /**
     * Estimate component completion date
     */
    public function estimateCompletion(Component $component): ?string
    {
        if ($component->status === 'completed') {
            return $component->updated_at->format('Y-m-d');
        }
        
        $tasks = Task::where('component_id', $component->id)
            ->where('status', '!=', 'completed')
            ->whereNotNull('end_date')
            ->orderBy('end_date', 'desc')
            ->get();
        
        if ($tasks->isEmpty()) {
            return $component->end_date?->format('Y-m-d');
        }
        
        $latestTaskDate = $tasks->first()->end_date;
        return $latestTaskDate?->format('Y-m-d');
    }

    /**
     * Get components by project
     */
    public function getComponentsByProject(string $projectId, array $filters = []): Collection
    {
        $query = Component::where('project_id', $projectId);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get components with filters
     */
    public function getComponents(array $filters = []): Collection
    {
        $query = Component::query();
        
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        
        if (isset($filters['parent_id'])) {
            $query->where('parent_id', $filters['parent_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get component by ID with includes
     */
    public function getComponentById(string $componentId, array $includes = []): ?Component
    {
        $query = Component::query();
        
        if (in_array('tasks', $includes)) {
            $query->with('tasks');
        }
        
        if (in_array('project', $includes)) {
            $query->with('project');
        }
        
        if (in_array('parent', $includes)) {
            $query->with('parent');
        }
        
        if (in_array('children', $includes)) {
            $query->with('children');
        }
        
        return $query->find($componentId);
    }

    /**
     * Get component tree structure
     */
    public function getComponentTree(string $projectId): array
    {
        $components = Component::where('project_id', $projectId)
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();
        
        return $this->buildTree($components->toArray());
    }

    /**
     * Build tree structure from flat array
     */
    private function buildTree(array $components, $parentId = null): array
    {
        $tree = [];
        
        foreach ($components as $component) {
            if ($component['parent_id'] == $parentId) {
                $children = $this->buildTree($components, $component['id']);
                if (!empty($children)) {
                    $component['children'] = $children;
                }
                $tree[] = $component;
            }
        }
        
        return $tree;
    }

    /**
     * Update component status
     */
    public function updateStatus(string $componentId, string $status, int $updatedBy): Component
    {
        $component = Component::findOrFail($componentId);
        $oldStatus = $component->status;
        
        $component->update(['status' => $status]);
        
        // Log status change
        $this->logInteraction([
            'tenant_id' => $component->tenant_id,
            'user_id' => $updatedBy,
            'project_id' => $component->project_id,
            'component_id' => $component->id,
            'type' => 'status_change',
            'content' => "Component '{$component->name}' status changed from '{$oldStatus}' to '{$status}'",
            'metadata' => [
                'old_status' => $oldStatus,
                'new_status' => $status,
                'component_name' => $component->name
            ],
            'is_internal' => false,
        ]);
        
        return $component;
    }

    /**
     * Log interaction
     */
    private function logInteraction(array $data): void
    {
        InteractionLog::create([
            'id' => \Str::ulid(),
            'tenant_id' => $data['tenant_id'],
            'user_id' => $data['user_id'],
            'project_id' => $data['project_id'] ?? null,
            'component_id' => $data['component_id'] ?? null,
            'type' => $data['type'],
            'content' => $data['content'],
            'metadata' => $data['metadata'] ?? [],
            'is_internal' => $data['is_internal'] ?? false,
        ]);
    }
}