<?php declare(strict_types=1);

namespace Src\WorkTemplate\Events;

use Src\Foundation\Helpers\AuthHelper;

use Src\WorkTemplate\Models\Template;
use Src\CoreProject\Models\Project;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Event fired when a template is successfully applied to a project
 * Contains information about the applied template, target project, and created entities
 */
class TemplateApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The template that was applied
     *
     * @var Template
     */
    public Template $template;

    /**
     * The project that received the template
     *
     * @var Project
     */
    public Project $project;

    /**
     * Array of created project phases
     *
     * @var array
     */
    public array $appliedPhases;

    /**
     * Array of created project tasks
     *
     * @var array
     */
    public array $appliedTasks;

    /**
     * Application options used
     *
     * @var array
     */
    public array $options;

    /**
     * User who applied the template
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
     * Create a new event instance
     *
     * @param Template $template
     * @param Project $project
     * @param array $appliedPhases
     * @param array $appliedTasks
     * @param array $options
     */
    public function __construct(
        WorkTemplate $template,
        Project $project,
        array $appliedPhases,
        array $appliedTasks,
        array $options = []
    ) {
        $this->template = $template;
        $this->project = $project;
        $this->appliedPhases = $appliedPhases;
        $this->appliedTasks = $appliedTasks;
        $this->options = $options;
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
            Log::warning('Failed to resolve actor ID in TemplateApplied', [
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
            'entityId' => $this->template->id,
            'projectId' => $this->project->id,
            'actorId' => $this->actorId,
            'changedFields' => [
                'template_id' => $this->template->id,
                'phases_count' => count($this->appliedPhases),
                'tasks_count' => count($this->appliedTasks),
                'partial_sync' => $this->options['partial_sync'] ?? false,
                'conditional_tags' => $this->options['conditional_tags'] ?? []
            ],
            'timestamp' => $this->timestamp->toISOString()
        ];
    }

    /**
     * Get statistics about the applied template
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $visibleTasks = collect($this->appliedTasks)->where('is_hidden', false)->count();
        $hiddenTasks = collect($this->appliedTasks)->where('is_hidden', true)->count();
        
        return [
            'phases_created' => count($this->appliedPhases),
            'total_tasks_created' => count($this->appliedTasks),
            'visible_tasks' => $visibleTasks,
            'hidden_tasks' => $hiddenTasks,
            'template_name' => $this->template->name,
            'template_category' => $this->template->category,
            'project_name' => $this->project->name,
            'is_partial_sync' => $this->options['partial_sync'] ?? false
        ];
    }
}