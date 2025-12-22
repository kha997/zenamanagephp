<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

/**
 * Task Status Transition Service
 * 
 * Single source of truth for task status transitions.
 * Centralizes all business rules for status changes.
 */
class TaskStatusTransitionService
{
    /**
     * Error code constants for structured error handling
     * 
     * @var array<string, string>
     */
    private const ERROR_CODES = [
        'INVALID_TRANSITION' => 'invalid_transition',
        'PROJECT_STATUS_RESTRICTED' => 'project_status_restricted',
        'DEPENDENCIES_INCOMPLETE' => 'dependencies_incomplete',
        'DEPENDENTS_ACTIVE' => 'dependents_active',
        'REASON_REQUIRED' => 'reason_required',
    ];

    /**
     * Single source of truth - Transition matrix
     * 
     * Defines which status transitions are allowed
     * 
     * @var array<string, array<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        TaskStatus::BACKLOG->value => [
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::CANCELED->value,
        ],
        TaskStatus::IN_PROGRESS->value => [
            TaskStatus::DONE->value,
            TaskStatus::BLOCKED->value,
            TaskStatus::CANCELED->value,
            TaskStatus::BACKLOG->value, // Optional rollback
        ],
        TaskStatus::BLOCKED->value => [
            TaskStatus::IN_PROGRESS->value,
            TaskStatus::CANCELED->value,
        ],
        TaskStatus::DONE->value => [
            TaskStatus::IN_PROGRESS->value, // Reopen
        ],
        TaskStatus::CANCELED->value => [
            TaskStatus::BACKLOG->value, // Reactivate
        ],
    ];

    /**
     * Check if a status transition is allowed
     * 
     * @param TaskStatus $from
     * @param TaskStatus $to
     * @return bool
     */
    public function canTransition(TaskStatus $from, TaskStatus $to): bool
    {
        // Same status is always allowed (no-op)
        if ($from === $to) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];
        return in_array($to->value, $allowed, true);
    }

    /**
     * Validate transition with all business rules
     * 
     * @param Task $task
     * @param TaskStatus $newStatus
     * @param string|null $reason Optional reason for blocked/canceled
     * @return ValidationResult
     */
    public function validateTransition(
        Task $task,
        TaskStatus $newStatus,
        ?string $reason = null
    ): ValidationResult {
        $currentStatus = $task->status instanceof TaskStatus 
            ? $task->status 
            : TaskStatus::from($task->status);

        // 1. Check transition validity
        if (!$this->canTransition($currentStatus, $newStatus)) {
            return ValidationResult::error(
                "Cannot transition from '{$currentStatus->value}' to '{$newStatus->value}'. " .
                "Allowed transitions from '{$currentStatus->value}': " .
                implode(', ', self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? []),
                self::ERROR_CODES['INVALID_TRANSITION'],
                [
                    'from_status' => $currentStatus->value,
                    'to_status' => $newStatus->value,
                    'allowed_transitions' => self::ALLOWED_TRANSITIONS[$currentStatus->value] ?? []
                ]
            );
        }

        // 2. Load project if not already loaded
        $project = $task->relationLoaded('project') ? $task->project : $task->project()->first();
        
        if (!$project) {
            return ValidationResult::error('Task must belong to a project');
        }

        // 3. Check project status
        if (!$this->isProjectStatusAllowed($project, $task, $currentStatus, $newStatus)) {
            $projectStatus = $project->status;
            return ValidationResult::error(
                "Cannot perform this operation when project is in '{$projectStatus}' status. " .
                "Project must be in 'planning' or 'active' status for most task operations.",
                self::ERROR_CODES['PROJECT_STATUS_RESTRICTED'],
                [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'project_status' => $projectStatus,
                    'required_statuses' => ['planning', 'active']
                ]
            );
        }

        // 4. Check dependencies (if moving to in_progress)
        if ($newStatus === TaskStatus::IN_PROGRESS) {
            if (!$task->canStart()) {
                // Get incomplete dependencies
                $dependencies = $task->dependencies ?? [];
                $incompleteDeps = [];
                if (!empty($dependencies)) {
                    // Task model uses ulid field for dependencies
                    $dependentTasks = Task::whereIn('id', $dependencies)->get();
                    foreach ($dependentTasks as $dep) {
                        $depStatus = $dep->status instanceof TaskStatus 
                            ? $dep->status 
                            : TaskStatus::tryFrom($dep->status);
                        if ($depStatus !== TaskStatus::DONE) {
                            $incompleteDeps[] = $dep->id;
                        }
                    }
                }
                
                return ValidationResult::error(
                    'Cannot start task: one or more dependencies are not completed. ' .
                    'All dependent tasks must be in "done" status before starting this task.',
                    self::ERROR_CODES['DEPENDENCIES_INCOMPLETE'],
                    [
                        'dependencies' => $incompleteDeps,
                        'message' => "All dependent tasks must be in 'done' status before starting this task."
                    ]
                );
            }
        }

        // 5. Check dependents (if canceling)
        if ($newStatus === TaskStatus::CANCELED) {
            $dependents = $task->getDependentTasks();
            $activeDependents = $dependents->filter(function ($dependent) {
                $status = $dependent->status instanceof TaskStatus 
                    ? $dependent->status 
                    : TaskStatus::tryFrom($dependent->status);
                return $status === TaskStatus::IN_PROGRESS;
            });

            if ($activeDependents->isNotEmpty()) {
                return ValidationResult::warning(
                    "Task has {$activeDependents->count()} active dependent task(s). " .
                    "Canceling this task may affect their progress.",
                    self::ERROR_CODES['DEPENDENTS_ACTIVE'],
                    [
                        'dependents' => $activeDependents->pluck('id')->toArray(),
                        'count' => $activeDependents->count()
                    ]
                );
            }
        }

        // 6. Require reason for blocked/canceled
        if (in_array($newStatus, [TaskStatus::BLOCKED, TaskStatus::CANCELED], true)) {
            if (empty($reason) || trim($reason) === '') {
                return ValidationResult::error(
                    "Reason is required when moving task to '{$newStatus->value}' status.",
                    self::ERROR_CODES['REASON_REQUIRED'],
                    [
                        'required_for_status' => $newStatus->value
                    ]
                );
            }
        }

        return ValidationResult::success();
    }

    /**
     * Check if project status allows task operation
     * 
     * @param Project $project
     * @param Task $task
     * @param TaskStatus $currentStatus
     * @param TaskStatus $newStatus
     * @return bool
     */
    private function isProjectStatusAllowed(
        Project $project,
        Task $task,
        TaskStatus $currentStatus,
        TaskStatus $newStatus
    ): bool {
        // Archived projects: read-only
        if ($project->status === Project::STATUS_ARCHIVED) {
            return false;
        }

        // Terminal states: no reactive operations
        if (in_array($project->status, [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED], true)) {
            // Allow only if not trying to reactive (canceled → backlog)
            if ($currentStatus === TaskStatus::CANCELED && $newStatus === TaskStatus::BACKLOG) {
                return false;
            }
            // Allow if not trying to reopen (done → in_progress)
            if ($currentStatus === TaskStatus::DONE && $newStatus === TaskStatus::IN_PROGRESS) {
                return false;
            }
            // Allow other operations (like viewing, but not status changes)
            // Actually, we should block all status changes in terminal project states
            return false;
        }

        // Planning/Active: allow most operations
        if (in_array($project->status, [Project::STATUS_PLANNING, Project::STATUS_ACTIVE], true)) {
            return true;
        }

        // On hold: allow viewing, limited updates
        if ($project->status === Project::STATUS_ON_HOLD) {
            // Allow blocking/unblocking, canceling
            return in_array($newStatus, [
                TaskStatus::BLOCKED,
                TaskStatus::CANCELED,
            ], true);
        }

        return false;
    }

    /**
     * Calculate new progress based on status transition
     * 
     * @param TaskStatus $newStatus
     * @param float $currentProgress
     * @return float
     */
    public function calculateProgress(
        TaskStatus $newStatus,
        float $currentProgress
    ): float {
        return match($newStatus) {
            TaskStatus::DONE => 100.0,
            TaskStatus::BACKLOG => 0.0,
            TaskStatus::IN_PROGRESS => max(0.0, min(99.0, $currentProgress)),
            default => $currentProgress, // Keep current for blocked/canceled
        };
    }

    /**
     * Get all allowed transitions from a given status
     * 
     * @param TaskStatus $from
     * @return array<TaskStatus>
     */
    public function getAllowedTransitions(TaskStatus $from): array
    {
        $allowed = self::ALLOWED_TRANSITIONS[$from->value] ?? [];
        return array_map(fn($value) => TaskStatus::from($value), $allowed);
    }

    /**
     * Check if status requires reason when transitioning
     * 
     * @param TaskStatus $status
     * @return bool
     */
    public function requiresReason(TaskStatus $status): bool
    {
        return in_array($status, [TaskStatus::BLOCKED, TaskStatus::CANCELED], true);
    }
}

