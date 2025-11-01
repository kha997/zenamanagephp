<?php

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Repositories\TaskRepository;
use App\Services\AuditService;
use App\Services\PermissionService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

class TaskService
{
    protected $taskRepository;
    protected $auditService;
    protected $permissionService;
    
    public function __construct(TaskRepository $taskRepository, AuditService $auditService, PermissionService $permissionService)
    {
        $this->taskRepository = $taskRepository;
        $this->auditService = $auditService;
        $this->permissionService = $permissionService;
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
            'name' => $data['title'],
            'description' => $data['description'],
            'status' => $data['status'] ?? 'pending',
            'priority' => $data['priority'] ?? 'medium',
            'project_id' => $data['project_id'] ?? null,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'due_date' => $data['due_date'] ?? null,
            'progress_percent' => $data['progress_percent'] ?? 0,
            'estimated_hours' => $data['estimated_hours'] ?? 0,
        ]);
        
        // Fire events for side effects
        Event::dispatch('task.created', $task);
        
        // Audit logging
        $this->auditService->log('task_created', $userId, $tenantId, [
            'task_id' => $task->id,
            'task_name' => $task->name
        ]);
        
        return $task;
    }
    
    /**
     * Move task to different status
     */
    public function moveTask(string $taskId, string $newStatus, string $userId, string $tenantId): Task
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Business logic validation
        $this->validateTaskMove($task, $newStatus, $userId);
        
        $oldStatus = $task->status;
        $task = $this->taskRepository->update($taskId, ['status' => $newStatus], $tenantId);
        
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
    public function archiveTask(string $taskId, string $userId, string $tenantId): Task
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Business logic validation
        $this->validateTaskArchive($task, $userId);
        
        $task = $this->taskRepository->update($taskId, ['archived_at' => now()], $tenantId);
        
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
     * Get tasks list with pagination
     */
    public function getTasksList(array $filters, string $userId, string $tenantId): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $filters['tenant_id'] = $tenantId;
        return $this->taskRepository->getAll($filters);
    }

    /**
     * Update task
     */
    public function updateTask(string $taskId, array $data, string $userId, string $tenantId): Task
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        if (!$task) {
            throw new \Exception('Task not found');
        }
        
        // Validate access
        $this->validateTaskAccess($task, $userId, $tenantId);
        
        $updatedTask = $this->taskRepository->update($taskId, $data, $tenantId);
        
        // Fire events
        Event::dispatch('task.updated', $updatedTask);
        
        // Audit logging
        $this->auditService->log('task_updated', $userId, $tenantId, [
            'task_id' => $taskId,
            'changes' => $data
        ]);
        
        return $updatedTask;
    }

    /**
     * Delete task
     */
    public function deleteTask(string $taskId, string $userId, string $tenantId): bool
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Validate access
        $this->validateTaskAccess($task, $userId, $tenantId);
        
        $result = $this->taskRepository->delete($taskId, $tenantId);
        
        // Fire events
        Event::dispatch('task.deleted', $task);
        
        // Audit logging
        $this->auditService->log('task_deleted', $userId, $tenantId, [
            'task_id' => $taskId
        ]);
        
        return $result;
    }

    /**
     * Assign task to user
     */
    public function assignTask(string $taskId, string $assigneeId, string $userId, string $tenantId): bool
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Validate access
        $this->validateTaskAccess($task, $userId, $tenantId);
        
        $result = $this->taskRepository->assignToUser($taskId, $assigneeId, $tenantId);
        
        // Fire events
        Event::dispatch('task.assigned', [
            'task' => $task,
            'assignee_id' => $assigneeId,
            'assigned_by' => $userId
        ]);
        
        // Audit logging
        $this->auditService->log('task_assigned', $userId, $tenantId, [
            'task_id' => $taskId,
            'assignee_id' => $assigneeId
        ]);
        
        return $result;
    }

    /**
     * Unassign task from user
     */
    public function unassignTask(string $taskId, string $userId, string $tenantId): bool
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Validate access
        $this->validateTaskAccess($task, $userId, $tenantId);
        
        $result = $this->taskRepository->assignToUser($taskId, null, $tenantId);
        
        // Fire events
        Event::dispatch('task.unassigned', [
            'task' => $task,
            'unassigned_by' => $userId
        ]);
        
        // Audit logging
        $this->auditService->log('task_unassigned', $userId, $tenantId, [
            'task_id' => $taskId
        ]);
        
        return $result;
    }

    /**
     * Update task progress
     */
    public function updateTaskProgress(string $taskId, array $data, string $userId, string $tenantId): Task
    {
        $task = $this->taskRepository->getById($taskId, $tenantId);
        
        // Validate access
        $this->validateTaskAccess($task, $userId, $tenantId);
        
        $updatedTask = $this->taskRepository->update($taskId, $data, $tenantId);
        
        // Fire events
        Event::dispatch('task.progress_updated', [
            'task' => $updatedTask,
            'progress_percent' => $data['progress_percent'] ?? $task->progress_percent,
            'updated_by' => $userId
        ]);
        
        // Audit logging
        $this->auditService->log('task_progress_updated', $userId, $tenantId, [
            'task_id' => $taskId,
            'progress_percent' => $data['progress_percent'] ?? $task->progress_percent
        ]);
        
        return $updatedTask;
    }

    /**
     * Validate task access
     */
    private function validateTaskAccess(Task $task, string $userId, string $tenantId): void
    {
        if (!$this->permissionService->canUserAccessTask($task, $userId)) {
            throw new \Exception('User cannot access this task');
        }
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
            throw new \Exception('User cannot create tasks in this tenant');
        }
        
        // Check project access if project_id is provided
        if (isset($data['project_id'])) {
            $this->validateProjectAccess($data['project_id'], $userId, $tenantId);
        }
    }
    
    /**
     * Validate task move
     */
    private function validateTaskMove(Task $task, string $newStatus, string $userId): void
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
            throw new \Exception('User cannot move this task');
        }
    }
    
    /**
     * Validate task archive
     */
    private function validateTaskArchive(Task $task, string $userId): void
    {
        // Only completed or cancelled tasks can be archived
        if (!in_array($task->status, ['completed', 'cancelled'])) {
            throw new \InvalidArgumentException('Only completed or cancelled tasks can be archived');
        }
        
        // Check if user can archive this task
        if (!$this->canUserArchiveTask($task, $userId)) {
            throw new \Exception('User cannot archive this task');
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
    private function canUserMoveTask(Task $task, string $userId): bool
    {
        // Business logic to check user permissions
        return $task->user_id === $userId || $this->isUserAdmin($userId);
    }
    
    /**
     * Check if user can archive task
     */
    private function canUserArchiveTask(Task $task, string $userId): bool
    {
        // Business logic to check user permissions
        return $task->user_id === $userId || $this->isUserAdmin($userId);
    }
    
    /**
     * Check if user is admin
     */
    private function isUserAdmin(string $userId): bool
    {
        // Business logic to check admin status
        return false; // Simplified for demo
    }
    
    private function validateProjectAccess(string $projectId, string $userId, string $tenantId): void
    {
        // Business logic to validate project access
        // This would check if user has access to the project
    }
}