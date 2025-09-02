<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\CoreProject\Models\Task;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Service TaskDependencyService - Xử lý dependencies giữa các tasks
 * 
 * Chức năng chính:
 * - Quản lý task dependencies
 * - Phát hiện circular dependencies
 * - Tính toán dependency graph
 * - Topological sorting
 */
class TaskDependencyService
{
    /**
     * Thêm dependency cho task
     */
    public function addDependency(Task $task, int $dependencyTaskId): void
    {
        $dependencies = $task->dependencies ?? [];
        
        if (!in_array($dependencyTaskId, $dependencies)) {
            $dependencies[] = $dependencyTaskId;
            
            // Validate trước khi save
            $this->validateDependencies($dependencies, $task->project_id, $task->id);
            
            $task->dependencies = $dependencies;
            $task->save();
        }
    }
    
    /**
     * Xóa dependency khỏi task
     */
    public function removeDependency(Task $task, int $dependencyTaskId): void
    {
        $dependencies = $task->dependencies ?? [];
        $dependencies = array_values(array_filter($dependencies, fn($id) => $id !== $dependencyTaskId));
        
        $task->dependencies = $dependencies;
        $task->save();
    }
    
    /**
     * Lấy dependency graph cho project
     */
    public function getDependencyGraph(int $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('is_hidden', false)
            ->get(['id', 'name', 'dependencies']);
            
        $graph = [];
        
        foreach ($tasks as $task) {
            $graph[$task->id] = [
                'name' => $task->name,
                'dependencies' => $task->dependencies ?? [],
                'dependents' => []
            ];
        }
        
        // Tính toán dependents (tasks phụ thuộc vào task này)
        foreach ($tasks as $task) {
            foreach ($task->dependencies ?? [] as $depId) {
                if (isset($graph[$depId])) {
                    $graph[$depId]['dependents'][] = $task->id;
                }
            }
        }
        
        return $graph;
    }
    
    /**
     * Topological sort để sắp xếp tasks theo thứ tự thực hiện
     */
    public function getExecutionOrder(int $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->where('is_hidden', false)
            ->get(['id', 'dependencies']);
            
        $graph = [];
        $inDegree = [];
        
        // Khởi tạo graph và in-degree
        foreach ($tasks as $task) {
            $graph[$task->id] = $task->dependencies ?? [];
            $inDegree[$task->id] = 0;
        }
        
        // Tính in-degree cho mỗi node
        foreach ($graph as $taskId => $dependencies) {
            foreach ($dependencies as $depId) {
                if (isset($inDegree[$depId])) {
                    $inDegree[$depId]++;
                }
            }
        }
        
        // Kahn's algorithm cho topological sort
        $queue = [];
        $result = [];
        
        // Thêm tất cả nodes có in-degree = 0 vào queue
        foreach ($inDegree as $taskId => $degree) {
            if ($degree === 0) {
                $queue[] = $taskId;
            }
        }
        
        while (!empty($queue)) {
            $current = array_shift($queue);
            $result[] = $current;
            
            // Giảm in-degree của các dependent nodes
            foreach ($graph as $taskId => $dependencies) {
                if (in_array($current, $dependencies)) {
                    $inDegree[$taskId]--;
                    if ($inDegree[$taskId] === 0) {
                        $queue[] = $taskId;
                    }
                }
            }
        }
        
        // Kiểm tra cycle
        if (count($result) !== count($tasks)) {
            throw new InvalidArgumentException('Dependency graph chứa cycle, không thể sắp xếp.');
        }
        
        return $result;
    }
    
    /**
     * Validate dependencies
     */
    private function validateDependencies(array $dependencies, int $projectId, ?int $excludeTaskId = null): void
    {
        if (empty($dependencies)) {
            return;
        }
        
        // Kiểm tra tất cả dependencies có tồn tại và thuộc cùng project
        $dependentTasks = Task::whereIn('id', $dependencies)
            ->where('project_id', $projectId)
            ->get();
            
        if ($dependentTasks->count() !== count($dependencies)) {
            throw new InvalidArgumentException('Một hoặc nhiều task dependencies không tồn tại hoặc không thuộc cùng project.');
        }
        
        // Kiểm tra circular dependencies nếu đang update task
        if ($excludeTaskId) {
            $this->detectCircularDependencies($excludeTaskId, $dependencies, $projectId);
        }
    }
    
    /**
     * Phát hiện circular dependencies
     */
    private function detectCircularDependencies(int $taskId, array $newDependencies, int $projectId): void
    {
        $visited = [];
        $recursionStack = [];
        
        // Tạo adjacency list
        $allTasks = Task::where('project_id', $projectId)
            ->whereNotNull('dependencies')
            ->get(['id', 'dependencies']);
            
        $adjacencyList = [];
        foreach ($allTasks as $task) {
            $adjacencyList[$task->id] = $task->dependencies ?? [];
        }
        
        $adjacencyList[$taskId] = $newDependencies;
        
        if ($this->hasCycleDFS($taskId, $adjacencyList, $visited, $recursionStack)) {
            throw new InvalidArgumentException('Dependencies tạo ra vòng lặp (circular dependency).');
        }
    }
    
    /**
     * DFS để phát hiện cycle
     */
    private function hasCycleDFS(int $node, array $adjacencyList, array &$visited, array &$recursionStack): bool
    {
        $visited[$node] = true;
        $recursionStack[$node] = true;
        
        $neighbors = $adjacencyList[$node] ?? [];
        foreach ($neighbors as $neighbor) {
            if (!isset($visited[$neighbor])) {
                if ($this->hasCycleDFS($neighbor, $adjacencyList, $visited, $recursionStack)) {
                    return true;
                }
            } elseif (isset($recursionStack[$neighbor]) && $recursionStack[$neighbor]) {
                return true;
            }
        }
        
        $recursionStack[$node] = false;
        return false;
    }
    
    /**
     * Lấy tất cả tasks có thể bắt đầu (không có dependencies hoặc dependencies đã hoàn thành)
     */
    public function getAvailableTasks(int $projectId): Collection
    {
        return Task::where('project_id', $projectId)
            ->where('status', 'pending')
            ->where('is_hidden', false)
            ->get()
            ->filter(function ($task) {
                return $task->canStart();
            });
    }
    
    /**
     * Lấy impact analysis khi task bị delay
     */
    public function getDelayImpact(Task $task, int $delayDays): array
    {
        $impactedTasks = [];
        $this->findImpactedTasks($task->id, $task->project_id, $delayDays, $impactedTasks);
        
        return $impactedTasks;
    }
    
    /**
     * Recursive tìm tasks bị impact bởi delay
     */
    private function findImpactedTasks(int $taskId, int $projectId, int $delayDays, array &$impactedTasks, array &$visited = []): void
    {
        if (isset($visited[$taskId])) {
            return;
        }
        
        $visited[$taskId] = true;
        
        // Tìm tasks phụ thuộc vào task này
        $dependentTasks = Task::where('project_id', $projectId)
            ->where('is_hidden', false)
            ->get()
            ->filter(function ($task) use ($taskId) {
                return in_array($taskId, $task->dependencies ?? []);
            });
            
        foreach ($dependentTasks as $dependentTask) {
            $impactedTasks[] = [
                'task_id' => $dependentTask->id,
                'task_name' => $dependentTask->name,
                'current_start_date' => $dependentTask->start_date,
                'new_start_date' => $dependentTask->start_date?->addDays($delayDays),
                'delay_days' => $delayDays
            ];
            
            // Recursive tìm tasks phụ thuộc tiếp theo
            $this->findImpactedTasks($dependentTask->id, $projectId, $delayDays, $impactedTasks, $visited);
        }
    }
}