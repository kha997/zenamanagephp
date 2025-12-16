<?php declare(strict_types=1);

namespace App\Services;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;

/**
 * Project Status Transition Service
 * 
 * Single source of truth for project status transitions.
 * Centralizes all business rules for project status changes.
 */
class ProjectStatusTransitionService
{
    /**
     * Error code constants for structured error handling
     * 
     * @var array<string, string>
     */
    private const ERROR_CODES = [
        'INVALID_TRANSITION' => 'invalid_transition',
        'HAS_ACTIVE_TASKS' => 'has_active_tasks',
        'HAS_UNFINISHED_TASKS' => 'has_unfinished_tasks',
    ];

    /**
     * Single source of truth - Transition matrix
     * 
     * Defines which status transitions are allowed
     * 
     * @var array<string, array<string>>
     */
    private const ALLOWED_TRANSITIONS = [
        Project::STATUS_PLANNING => [
            Project::STATUS_ACTIVE,
            Project::STATUS_COMPLETED, // Conditional: only if no unfinished tasks
            Project::STATUS_CANCELLED,
        ],
        Project::STATUS_ACTIVE => [
            Project::STATUS_PLANNING, // Conditional: only if no in_progress/done tasks
            Project::STATUS_ON_HOLD,
            Project::STATUS_COMPLETED,
            Project::STATUS_CANCELLED,
        ],
        Project::STATUS_ON_HOLD => [
            Project::STATUS_ACTIVE,
            Project::STATUS_COMPLETED,
            Project::STATUS_CANCELLED,
        ],
        Project::STATUS_COMPLETED => [
            Project::STATUS_ARCHIVED,
        ],
        Project::STATUS_CANCELLED => [
            Project::STATUS_ARCHIVED,
        ],
        Project::STATUS_ARCHIVED => [], // Terminal state - no transitions allowed
    ];

    /**
     * Check if a status transition is allowed (without condition checks)
     * 
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function canTransition(string $from, string $to): bool
    {
        // Same status is always allowed (no-op)
        if ($from === $to) {
            return true;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    /**
     * Validate transition with all business rules
     * 
     * @param Model $project Project model instance (App\Models\Project)
     * @param string $newStatus
     * @return ValidationResult
     */
    public function validateTransition(
        Model $project,
        string $newStatus
    ): ValidationResult {
        $currentStatus = $project->status;

        // 1. Check transition validity
        if (!$this->canTransition($currentStatus, $newStatus)) {
            return ValidationResult::error(
                "Cannot transition from '{$currentStatus}' to '{$newStatus}'. " .
                "Allowed transitions from '{$currentStatus}': " .
                implode(', ', self::ALLOWED_TRANSITIONS[$currentStatus] ?? []),
                self::ERROR_CODES['INVALID_TRANSITION'],
                [
                    'from_status' => $currentStatus,
                    'to_status' => $newStatus,
                    'allowed_transitions' => self::ALLOWED_TRANSITIONS[$currentStatus] ?? []
                ]
            );
        }

        // 2. Check special conditions for conditional transitions
        
        // planning → completed: only valid if no unfinished tasks
        if ($currentStatus === Project::STATUS_PLANNING && $newStatus === Project::STATUS_COMPLETED) {
            if ($this->hasUnfinishedTasks($project)) {
                return ValidationResult::error(
                    "Cannot complete project from planning status: project has unfinished tasks. " .
                    "All tasks must be in backlog or canceled status before completing a planning project.",
                    self::ERROR_CODES['HAS_UNFINISHED_TASKS'],
                    [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'message' => "Project cannot be completed from planning status if it has unfinished tasks."
                    ]
                );
            }
        }

        // active → planning: only valid if no in_progress/done tasks
        if ($currentStatus === Project::STATUS_ACTIVE && $newStatus === Project::STATUS_PLANNING) {
            if ($this->hasActiveTasks($project)) {
                return ValidationResult::error(
                    "Cannot move project back to planning status: project has active tasks (in_progress or done). " .
                    "All tasks must be in backlog, blocked, or canceled status before moving project back to planning.",
                    self::ERROR_CODES['HAS_ACTIVE_TASKS'],
                    [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'message' => "Project cannot be moved back to planning status if it has active tasks (in_progress or done)."
                    ]
                );
            }
        }

        return ValidationResult::success();
    }

    /**
     * Check if project has active tasks (in_progress or done)
     * 
     * @param Model $project Project model instance
     * @return bool
     */
    public function hasActiveTasks(Model $project): bool
    {
        return $project->tasks()
            ->whereIn('status', [
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::DONE->value,
            ])
            ->exists();
    }

    /**
     * Check if project has in_progress tasks
     * 
     * @param Model $project Project model instance
     * @return bool
     */
    public function hasInProgressTasks(Model $project): bool
    {
        return $project->tasks()
            ->where('status', TaskStatus::IN_PROGRESS->value)
            ->exists();
    }

    /**
     * Check if project has unfinished tasks (in_progress, blocked, or done)
     * 
     * Unfinished tasks are those that have been started or completed,
     * excluding backlog and canceled tasks.
     * 
     * @param Model $project Project model instance
     * @return bool
     */
    public function hasUnfinishedTasks(Model $project): bool
    {
        return $project->tasks()
            ->whereIn('status', [
                TaskStatus::IN_PROGRESS->value,
                TaskStatus::BLOCKED->value,
                TaskStatus::DONE->value,
            ])
            ->exists();
    }

    /**
     * Get all allowed transitions from a given status
     * 
     * @param string $from
     * @return array<string>
     */
    public function getAllowedTransitions(string $from): array
    {
        return self::ALLOWED_TRANSITIONS[$from] ?? [];
    }

    /**
     * Check if a status is terminal (no transitions allowed)
     * 
     * @param string $status
     * @return bool
     */
    public function isTerminal(string $status): bool
    {
        return $status === Project::STATUS_ARCHIVED;
    }
}

