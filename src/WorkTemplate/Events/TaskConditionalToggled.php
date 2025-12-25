<?php declare(strict_types=1);

namespace Src\WorkTemplate\Events;

use Src\Foundation\Helpers\AuthHelper;

use Src\WorkTemplate\Models\ProjectTask;
use Src\CoreProject\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Carbon\Carbon;

/**
 * Event fired when a task's conditional visibility is toggled
 * Contains information about the affected task and visibility change
 */
class TaskConditionalToggled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The task that was toggled
     *
     * @var ProjectTask
     */
    public ProjectTask $task;

    /**
     * The project containing the task
     *
     * @var Project
     */
    public Project $project;

    /**
     * The conditional tag that was toggled
     *
     * @var string
     */
    public string $conditionalTag;

    /**
     * Previous visibility state
     *
     * @var bool
     */
    public bool $previousVisibility;

    /**
     * New visibility state
     *
     * @var bool
     */
    public bool $newVisibility;

    /**
     * User who performed the toggle
     *
     * @var int|null
     */
    public ?int $actorId;

    /**
     * Timestamp when the event occurred
     *
     * @var Carbon
     */
    public Carbon $timestamp;

    /**
     * Number of tasks affected by this toggle
     *
     * @var int
     */
    public int $affectedTasksCount;

    /**
     * Create a new event instance
     *
     * @param ProjectTask $task
     * @param Project $project
     * @param string $conditionalTag
     * @param bool $previousVisibility
     * @param bool $newVisibility
     * @param int $affectedTasksCount
     */
    public function __construct(
        ProjectTask $task,
        Project $project,
        string $conditionalTag,
        bool $previousVisibility,
        bool $newVisibility,
        int $affectedTasksCount = 1
    ) {
        $this->task = $task;
        $this->project = $project;
        $this->conditionalTag = $conditionalTag;
        $this->previousVisibility = $previousVisibility;
        $this->newVisibility = $newVisibility;
        $this->affectedTasksCount = $affectedTasksCount;
        $this->actorId = $this->resolveActorId();
        $this->timestamp = Carbon::now();
    }
    
    /**
     * Resolve actor ID với fallback an toàn
     * 
     * @return string
     */
    protected function resolveActorId(): string
    {
        try {
            return AuthHelper::idOrSystem();
        } catch (\Exception $e) {
            Log::warning('Failed to resolve actor ID in TaskConditionalToggled', [
                'error' => $e->getMessage()
            ]);
            return 'system';
        }
    }

    /**
     * Get the event payload for logging and auditing
     *
     * @return array
     */
    public function getPayload(): array
    {
        return [
            'entityId' => $this->task->id,
            'projectId' => $this->project->id,
            'actorId' => $this->actorId,
            'changedFields' => [
                'conditional_tag' => $this->conditionalTag,
                'is_hidden' => !$this->newVisibility,
                'visibility_changed_from' => $this->previousVisibility,
                'visibility_changed_to' => $this->newVisibility,
                'affected_tasks_count' => $this->affectedTasksCount
            ],
            'timestamp' => $this->timestamp->toISOString()
        ];
    }

    /**
     * Check if the task was hidden
     *
     * @return bool
     */
    public function wasHidden(): bool
    {
        return !$this->newVisibility;
    }

}
