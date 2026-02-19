<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class TaskService
{
    protected $taskRepository;
    protected $auditService;
    
    public function __construct(TaskRepository $taskRepository, AuditService $auditService)
    {
        $this->taskRepository = $taskRepository;
        $this->auditService = $auditService;
    }
    
    /**
     * Create a new task with business logic
     */
    public function createTask(array $data, string $userId, string $tenantId): Task
    {
        // Business logic validation
        $this->validateTaskCreation($data, $userId, $tenantId);
        
        // Create task
        $task = $this->taskRepository->create([
            'title' => $data['title'],
            'description' => $data['description'],
            'status' => 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'project_id' => $data['project_id'] ?? null,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'due_date' => $data['due_date'] ?? null
        ]);
        
        // Fire events for side effects
        Event::dispatch('task.created', $task);
        
        // Audit logging
        $this->auditService->log('task_created', $userId, $tenantId, [
            'task_id' => $task->id,
            'task_title' => $task->title
        ]);
        
        return $task;
    }
    
    /**
     * Move task to different status
     */
    public function moveTask(int $taskId, string $newStatus, int $userId, int $tenantId): Task
    {
        $task = $this->taskRepository->findById($taskId);
        
        // Business logic validation
        $this->validateTaskMove($task, $newStatus, $userId);
        
        $oldStatus = $task->status;
        $task = $this->taskRepository->update($taskId, ['status' => $newStatus]);
        
        // Fire events for side effects
        Event::dispatch('task.moved', [
            'task' => $task,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $userId
        ]);
        
        // Audit logging
        $this->auditService->log('task_moved', $userId, $tenantId, [
            'task_id' => $taskId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        ]);
        
        return $task;
    }
    
    /**
     * Archive task
     */
    public function archiveTask(int $taskId, int $userId, int $tenantId): Task
    {
        $task = $this->taskRepository->findById($taskId);
        
        // Business logic validation
        $this->validateTaskArchive($task, $userId);
        
        $task = $this->taskRepository->update($taskId, ['archived_at' => now()]);
        
        // Fire events for side effects
        Event::dispatch('task.archived', [
            'task' => $task,
            'user_id' => $userId
        ]);
        
        // Audit logging
        $this->auditService->log('task_archived', $userId, $tenantId, [
            'task_id' => $taskId
        ]);
        
        return $task;
    }
    
    /**
     * Get tasks with business logic filters
     */
    public function getTasks(array $filters, string $userId, string $tenantId): array
    {
        // Apply business logic filters
        $query = $this->taskRepository->getQuery();
        
        // Tenant isolation
        $query->where('tenant_id', $tenantId);
        
        // User-specific filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        // Status filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Priority filters
        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        // Project filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        
        return $query->get()->toArray();
    }
    
    /**
     * Validate task creation
     */
    private function validateTaskCreation(array $data, string $userId, string $tenantId): void
    {
        // Business rules validation
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Task title is required');
        }
        
        // Check if user can create tasks in this tenant
        if (!$this->canUserCreateTasks($userId, $tenantId)) {
            throw new \UnauthorizedException('User cannot create tasks in this tenant');
        }
        
        // Check project access if project_id is provided
        if (isset($data['project_id'])) {
            $this->validateProjectAccess($data['project_id'], $userId, $tenantId);
        }
    }
    
    /**
     * Validate task move
     */
    private function validateTaskMove(Task $task, string $newStatus, int $userId): void
    {
        // Business rules for status transitions
        $allowedTransitions = [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'pending'],
            'completed' => ['in_progress'],
            'cancelled' => ['pending']
        ];
        
        if (!in_array($newStatus, $allowedTransitions[$task->status] ?? [])) {
            throw new \InvalidArgumentException('Invalid status transition');
        }
        
        // Check if user can move this task
        if (!$this->canUserMoveTask($task, $userId)) {
            throw new \UnauthorizedException('User cannot move this task');
        }
    }
    
    /**
     * Validate task archive
     */
    private function validateTaskArchive(Task $task, int $userId): void
    {
        // Only completed or cancelled tasks can be archived
        if (!in_array($task->status, ['completed', 'cancelled'])) {
            throw new \InvalidArgumentException('Only completed or cancelled tasks can be archived');
        }
        
        // Check if user can archive this task
        if (!$this->canUserArchiveTask($task, $userId)) {
            throw new \UnauthorizedException('User cannot archive this task');
        }
    }
    
    /**
     * Check if user can create tasks
     */
    private function canUserCreateTasks(string $userId, string $tenantId): bool
    {
        // Business logic to check user permissions
        return true; // Simplified for demo
    }
    
    /**
     * Check if user can move task
     */
    private function canUserMoveTask(Task $task, int $userId): bool
    {
        // Business logic to check user permissions
        return $task->user_id === $userId || $this->isUserAdmin($userId);
    }
    
    /**
     * Check if user can archive task
     */
    private function canUserArchiveTask(Task $task, int $userId): bool
    {
        // Business logic to check user permissions
        return $task->user_id === $userId || $this->isUserAdmin($userId);
    }
    
    /**
     * Check if user is admin
     */
    private function isUserAdmin(int $userId): bool
    {
        // Business logic to check admin status
        return false; // Simplified for demo
    }
    
    /**
     * Validate project access
     */
    private function validateProjectAccess(string $projectId, string $userId, string $tenantId): void
    {
        // Business logic to validate project access
        // This would check if user has access to the project
    }
}
