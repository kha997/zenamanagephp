<?php declare(strict_types=1);

namespace App\Events;

use App\Enums\TaskStatus;
use App\Models\Task;
use Src\Foundation\Events\BaseEvent;

/**
 * Task Moved Event
 * 
 * Fired when a task is moved between status columns (Kanban)
 */
class TaskMoved extends BaseEvent
{
    /**
     * Task model instance
     * 
     * @var Task
     */
    public Task $task;

    /**
     * Old status
     * 
     * @var string
     */
    public string $oldStatus;

    /**
     * New status
     * 
     * @var TaskStatus
     */
    public TaskStatus $newStatus;

    /**
     * Reason for move (if provided)
     * 
     * @var string|null
     */
    public ?string $reason;

    /**
     * Old position
     * 
     * @var float
     */
    public float $oldPosition;

    /**
     * New position
     * 
     * @var float
     */
    public float $newPosition;

    /**
     * Constructor
     * 
     * @param Task $task
     * @param string $oldStatus
     * @param TaskStatus $newStatus
     * @param string|null $reason
     * @param float $oldPosition
     * @param float $newPosition
     */
    public function __construct(
        Task $task,
        string $oldStatus,
        TaskStatus $newStatus,
        ?string $reason = null,
        float $oldPosition = 0.0,
        float $newPosition = 0.0
    ) {
        $this->task = $task;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
        $this->reason = $reason;
        $this->oldPosition = $oldPosition;
        $this->newPosition = $newPosition;

        // Initialize BaseEvent properties
        parent::__construct(
            entityId: $task->id,
            projectId: $task->project_id,
            actorId: $this->resolveActorId(),
            changedFields: [
                'status' => [
                    'old' => $oldStatus,
                    'new' => $newStatus->value
                ],
                'order' => [
                    'old' => $oldPosition,
                    'new' => $newPosition
                ],
                'progress_percent' => [
                    'old' => $task->getOriginal('progress_percent'),
                    'new' => $task->progress_percent
                ]
            ]
        );
    }

    /**
     * Get event name following Domain.Entity.Action format
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Task.Task.Moved';
    }

    /**
     * Convert event to array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'task_id' => $this->task->id,
            'task_name' => $this->task->name,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus->value,
            'reason' => $this->reason,
            'old_position' => $this->oldPosition,
            'new_position' => $this->newPosition,
        ]);
    }
}

