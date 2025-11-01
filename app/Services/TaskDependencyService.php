<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskDependency;
use Illuminate\Support\Collection;

class TaskDependencyService
{
    /**
     * Add a dependency between two tasks
     */
    public function addDependency(string $taskId, string $dependsOnTaskId, string $tenantId): array
    {
        // Check if tasks exist and belong to the same tenant
        $task = Task::where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->first();

        $dependsOnTask = Task::where('id', $dependsOnTaskId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }

        if (!$dependsOnTask) {
            // Check if task exists but belongs to different tenant
            $existsInOtherTenant = Task::where('id', $dependsOnTaskId)
                ->where('tenant_id', '!=', $tenantId)
                ->exists();
                
            if ($existsInOtherTenant) {
                return ['success' => false, 'message' => 'Cannot create dependency across tenants'];
            }
            
            return ['success' => false, 'message' => 'Dependent task not found'];
        }

        // Prevent self-dependency
        if ($taskId === $dependsOnTaskId) {
            return ['success' => false, 'message' => 'Task cannot depend on itself'];
        }

        // Check for cross-tenant access
        if ($task->tenant_id !== $dependsOnTask->tenant_id) {
            return ['success' => false, 'message' => 'Cannot create dependency across tenants'];
        }

        // Check if dependency already exists
        $existingDependency = TaskDependency::where('task_id', $taskId)
            ->where('dependency_id', $dependsOnTaskId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($existingDependency) {
            return ['success' => false, 'message' => 'Dependency already exists'];
        }

        // Check for circular dependency using DFS
        $wouldCreateCircular = $this->wouldCreateCircularDependency($taskId, $dependsOnTaskId, $tenantId);
        
        if ($wouldCreateCircular) {
            return ['success' => false, 'message' => 'Circular dependency detected'];
        }

        // Create the dependency
        TaskDependency::create([
            'task_id' => $taskId,
            'dependency_id' => $dependsOnTaskId,
            'tenant_id' => $tenantId
        ]);

        return ['success' => true, 'message' => 'Dependency added successfully'];
    }

    /**
     * Remove a dependency between two tasks
     */
    public function removeDependency(string $taskId, string $dependsOnTaskId, string $tenantId): array
    {
        $dependency = TaskDependency::where('task_id', $taskId)
            ->where('dependency_id', $dependsOnTaskId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$dependency) {
            return ['success' => false, 'message' => 'Dependency not found'];
        }

        $dependency->delete();

        return ['success' => true, 'message' => 'Dependency removed successfully'];
    }

    /**
     * Get all dependencies for a task
     */
    public function getDependencies(string $taskId, string $tenantId): Collection
    {
        $dependencyIds = TaskDependency::where('task_id', $taskId)
            ->where('tenant_id', $tenantId)
            ->pluck('dependency_id');

        return Task::whereIn('id', $dependencyIds)
            ->where('tenant_id', $tenantId)
            ->get();
    }

    /**
     * Get all tasks that depend on a given task
     */
    public function getDependents(string $taskId, string $tenantId): Collection
    {
        $dependentIds = TaskDependency::where('dependency_id', $taskId)
            ->where('tenant_id', $tenantId)
            ->pluck('task_id');

        return Task::whereIn('id', $dependentIds)
            ->where('tenant_id', $tenantId)
            ->get();
    }

    /**
     * Validate if a task status update is allowed based on dependencies
     */
    public function validateStatusUpdate(string $taskId, string $newStatus, string $tenantId): array
    {
        $task = Task::where('id', $taskId)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$task) {
            return ['success' => false, 'message' => 'Task not found'];
        }

        // If completing a task, check if all dependencies are completed
        if (in_array($newStatus, ['completed', 'closed'])) {
            $dependencies = $this->getDependencies($taskId, $tenantId);
            
            $incompleteDependencies = $dependencies->filter(function ($dependency) {
                return !in_array($dependency->status, ['completed', 'closed']);
            });

            if ($incompleteDependencies->isNotEmpty()) {
                return [
                    'success' => false, 
                    'message' => 'Cannot complete task: dependencies are not completed'
                ];
            }
        }

        return ['success' => true, 'message' => 'Status update is valid'];
    }

    /**
     * Get all blocked tasks (tasks that cannot proceed due to incomplete dependencies)
     */
    public function getBlockedTasks(string $tenantId): Collection
    {
        $allTasks = Task::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'closed', 'cancelled'])
            ->get();

        $blockedTasks = collect();

        foreach ($allTasks as $task) {
            $dependencies = $this->getDependencies($task->id, $tenantId);
            
            $incompleteDependencies = $dependencies->filter(function ($dependency) {
                return !in_array($dependency->status, ['completed', 'closed']);
            });

            if ($incompleteDependencies->isNotEmpty()) {
                $blockedTasks->push($task);
            }
        }

        return $blockedTasks;
    }

    /**
     * Get critical path for a project
     */
    public function getCriticalPath(string $projectId, string $tenantId): Collection
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('tenant_id', $tenantId)
            ->get();

        // Build dependency graph
        $graph = [];
        $inDegree = [];

        foreach ($tasks as $task) {
            $graph[$task->id] = [];
            $inDegree[$task->id] = 0;
        }

        $dependencies = TaskDependency::whereIn('task_id', $tasks->pluck('id'))
            ->where('tenant_id', $tenantId)
            ->get();

        foreach ($dependencies as $dependency) {
            $graph[$dependency->dependency_id][] = $dependency->task_id;
            $inDegree[$dependency->task_id]++;
        }

        // Topological sort to find critical path
        $queue = collect();
        $criticalPath = collect();

        foreach ($inDegree as $taskId => $degree) {
            if ($degree === 0) {
                $queue->push($taskId);
            }
        }

        while (!$queue->isEmpty()) {
            $currentTaskId = $queue->shift();
            $currentTask = $tasks->firstWhere('id', $currentTaskId);
            
            if ($currentTask) {
                $criticalPath->push($currentTask);
            }

            foreach ($graph[$currentTaskId] as $dependentTaskId) {
                $inDegree[$dependentTaskId]--;
                if ($inDegree[$dependentTaskId] === 0) {
                    $queue->push($dependentTaskId);
                }
            }
        }

        return $criticalPath;
    }

    /**
     * Check if adding a dependency would create a circular dependency
     */
    private function wouldCreateCircularDependency(string $taskId, string $dependsOnTaskId, string $tenantId): bool
    {
        // If task depends on itself, it's circular
        if ($taskId === $dependsOnTaskId) {
            return true;
        }

        // Check if adding this dependency would create a cycle
        // We need to check if there's already a path from dependsOnTaskId to taskId
        // OR if there's a path from taskId to dependsOnTaskId (which would create a cycle when we add the reverse)
        $hasPathFromDependsOnToTask = $this->hasPath($dependsOnTaskId, $taskId, $tenantId);
        $hasPathFromTaskToDependsOn = $this->hasPath($taskId, $dependsOnTaskId, $tenantId);
        
        // If there's already a path from dependsOnTaskId to taskId, adding taskId -> dependsOnTaskId creates a cycle
        return $hasPathFromDependsOnToTask || $hasPathFromTaskToDependsOn;
    }

    /**
     * Check if there's a path from source to target using DFS
     */
    private function hasPath(string $sourceTaskId, string $targetTaskId, string $tenantId): bool
    {
        if ($sourceTaskId === $targetTaskId) {
            return true;
        }

        $visited = [];
        $stack = [$sourceTaskId];

        while (!empty($stack)) {
            $currentTaskId = array_pop($stack);

            if ($currentTaskId === $targetTaskId) {
                return true;
            }

            if (isset($visited[$currentTaskId])) {
                continue;
            }

            $visited[$currentTaskId] = true;

            // Get all tasks that depend on current task
            $dependents = TaskDependency::where('dependency_id', $currentTaskId)
                ->where('tenant_id', $tenantId)
                ->pluck('task_id')
                ->toArray();

            foreach ($dependents as $dependentId) {
                if (!isset($visited[$dependentId])) {
                    $stack[] = $dependentId;
                }
            }
        }

        return false;
    }

    /**
     * Get dependency chain depth for a task
     */
    public function getDependencyDepth(string $taskId, string $tenantId): int
    {
        $maxDepth = 0;
        $dependencies = $this->getDependencies($taskId, $tenantId);

        foreach ($dependencies as $dependency) {
            $depth = $this->getDependencyDepth($dependency->id, $tenantId) + 1;
            $maxDepth = max($maxDepth, $depth);
        }

        return $maxDepth;
    }

    /**
     * Get all tasks that can be started (no incomplete dependencies)
     */
    public function getReadyTasks(string $tenantId): Collection
    {
        $allTasks = Task::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['completed', 'closed', 'cancelled'])
            ->get();

        $readyTasks = collect();

        foreach ($allTasks as $task) {
            $dependencies = $this->getDependencies($task->id, $tenantId);
            
            $incompleteDependencies = $dependencies->filter(function ($dependency) {
                return !in_array($dependency->status, ['completed', 'closed']);
            });

            if ($incompleteDependencies->isEmpty()) {
                $readyTasks->push($task);
            }
        }

        return $readyTasks;
    }
}