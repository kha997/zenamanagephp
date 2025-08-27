<?php declare(strict_types=1);

namespace Src\WorkTemplate\Services;

use Src\WorkTemplate\Models\Template;
use Src\WorkTemplate\Models\TemplateVersion;
use Src\WorkTemplate\Models\ProjectPhase;
use Src\WorkTemplate\Models\ProjectTask;
use Src\CoreProject\Models\Project;
use Src\WorkTemplate\Events\TemplateApplied;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Service class for handling template application logic
 * Manages partial sync and conditional tags functionality
 */
class TemplateService
{
    /**
     * Apply a template to a project with partial sync support
     *
     * @param string $templateId Template ULID
     * @param string $projectId Project ULID
     * @param array $options Application options
     * @return array Applied phases and tasks data
     * @throws \Exception
     */
    public function applyTemplate(string $templateId, string $projectId, array $options = []): array
    {
        return DB::transaction(function () use ($templateId, $projectId, $options) {
            // Load template with latest version
            $template = Template::with('latestVersion')->findOrFail($templateId);
            $project = Project::findOrFail($projectId);
            
            // Get template data from latest version or template itself
            $templateData = $template->latestVersion?->json_body ?? $template->json_body;
            
            if (empty($templateData) || !isset($templateData['phases'])) {
                throw new \Exception('Template data is invalid or missing phases');
            }
            
            // Parse options
            $partialSync = $options['partial_sync'] ?? false;
            $conditionalTags = $options['conditional_tags'] ?? [];
            $startDate = isset($options['start_date']) ? Carbon::parse($options['start_date']) : Carbon::now();
            
            $appliedPhases = [];
            $appliedTasks = [];
            
            // Apply phases from template
            foreach ($templateData['phases'] as $phaseIndex => $phaseData) {
                $phase = $this->createPhaseFromTemplate(
                    $project,
                    $template,
                    $phaseData,
                    $phaseIndex,
                    $partialSync
                );
                
                $appliedPhases[] = $phase;
                
                // Apply tasks for this phase
                if (isset($phaseData['tasks']) && is_array($phaseData['tasks'])) {
                    foreach ($phaseData['tasks'] as $taskIndex => $taskData) {
                        $task = $this->createTaskFromTemplate(
                            $project,
                            $phase,
                            $template,
                            $taskData,
                            $taskIndex,
                            $conditionalTags,
                            $startDate,
                            $partialSync
                        );
                        
                        $appliedTasks[] = $task;
                    }
                }
            }
            
            // Dispatch template applied event
            Event::dispatch(new TemplateApplied(
                $template,
                $project,
                $appliedPhases,
                $appliedTasks,
                $options
            ));
            
            return [
                'phases' => $appliedPhases,
                'tasks' => $appliedTasks,
                'template' => $template,
                'project' => $project
            ];
        });
    }
    
    /**
     * Create a project phase from template data
     *
     * @param Project $project
     * @param Template $template
     * @param array $phaseData
     * @param int $order
     * @param bool $partialSync
     * @return ProjectPhase
     */
    private function createPhaseFromTemplate(
        Project $project,
        Template $template,
        array $phaseData,
        int $order,
        bool $partialSync = false
    ): ProjectPhase {
        // Check if phase already exists (for partial sync)
        if ($partialSync && isset($phaseData['id'])) {
            $existingPhase = ProjectPhase::where('project_id', $project->id)
                ->where('template_phase_id', $phaseData['id'])
                ->first();
                
            if ($existingPhase) {
                // Update existing phase if needed
                $existingPhase->update([
                    'name' => $phaseData['name'] ?? $existingPhase->name,
                    'order' => $order,
                    'updated_by' => auth()->id()
                ]);
                
                return $existingPhase;
            }
        }
        
        // Create new phase
        return ProjectPhase::create([
            'id' => Str::ulid(),
            'project_id' => $project->id,
            'name' => $phaseData['name'] ?? 'Unnamed Phase',
            'order' => $order,
            'template_id' => $template->id,
            'template_phase_id' => $phaseData['id'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id()
        ]);
    }
    
    /**
     * Create a project task from template data
     *
     * @param Project $project
     * @param ProjectPhase $phase
     * @param Template $template
     * @param array $taskData
     * @param int $order
     * @param array $conditionalTags
     * @param Carbon $baseStartDate
     * @param bool $partialSync
     * @return ProjectTask
     */
    private function createTaskFromTemplate(
        Project $project,
        ProjectPhase $phase,
        Template $template,
        array $taskData,
        int $order,
        array $conditionalTags,
        Carbon $baseStartDate,
        bool $partialSync = false
    ): ProjectTask {
        // Check if task already exists (for partial sync)
        if ($partialSync && isset($taskData['id'])) {
            $existingTask = ProjectTask::where('project_id', $project->id)
                ->where('template_task_id', $taskData['id'])
                ->first();
                
            if ($existingTask) {
                // Update existing task if needed
                $isHidden = $this->shouldHideTask($taskData, $conditionalTags);
                
                $existingTask->update([
                    'name' => $taskData['name'] ?? $existingTask->name,
                    'description' => $taskData['description'] ?? $existingTask->description,
                    'order' => $order,
                    'is_hidden' => $isHidden,
                    'conditional_tag' => $taskData['conditional_tag'] ?? null,
                    'updated_by' => auth()->id()
                ]);
                
                return $existingTask;
            }
        }
        
        // Calculate dates
        $startDate = $this->calculateTaskStartDate($baseStartDate, $taskData, $order);
        $endDate = $this->calculateTaskEndDate($startDate, $taskData);
        
        // Check if task should be hidden based on conditional tags
        $isHidden = $this->shouldHideTask($taskData, $conditionalTags);
        
        // Create new task
        return ProjectTask::create([
            'id' => Str::ulid(),
            'project_id' => $project->id,
            'phase_id' => $phase->id,
            'name' => $taskData['name'] ?? 'Unnamed Task',
            'description' => $taskData['description'] ?? null,
            'order' => $order,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => ProjectTask::STATUS_PENDING,
            'progress_percent' => 0,
            'estimated_hours' => $taskData['estimated_hours'] ?? null,
            'actual_hours' => 0,
            'dependencies' => json_encode($taskData['dependencies'] ?? []),
            'conditional_tag' => $taskData['conditional_tag'] ?? null,
            'is_hidden' => $isHidden,
            'template_id' => $template->id,
            'template_task_id' => $taskData['id'] ?? null,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id()
        ]);
    }
    
    /**
     * Determine if a task should be hidden based on conditional tags
     *
     * @param array $taskData
     * @param array $conditionalTags
     * @return bool
     */
    private function shouldHideTask(array $taskData, array $conditionalTags): bool
    {
        // If task has no conditional tag, it's always visible
        if (!isset($taskData['conditional_tag']) || empty($taskData['conditional_tag'])) {
            return false;
        }
        
        $taskTag = $taskData['conditional_tag'];
        
        // If conditional tag is not in the active tags list, hide the task
        return !in_array($taskTag, $conditionalTags);
    }
    
    /**
     * Calculate task start date based on template data and order
     *
     * @param Carbon $baseStartDate
     * @param array $taskData
     * @param int $order
     * @return Carbon
     */
    private function calculateTaskStartDate(Carbon $baseStartDate, array $taskData, int $order): Carbon
    {
        // If task has specific start offset, use it
        if (isset($taskData['start_offset_days'])) {
            return $baseStartDate->copy()->addDays($taskData['start_offset_days']);
        }
        
        // Otherwise, calculate based on order (each task starts 1 day after previous)
        return $baseStartDate->copy()->addDays($order);
    }
    
    /**
     * Calculate task end date based on start date and duration
     *
     * @param Carbon $startDate
     * @param array $taskData
     * @return Carbon
     */
    private function calculateTaskEndDate(Carbon $startDate, array $taskData): Carbon
    {
        $duration = $taskData['duration_days'] ?? 1;
        return $startDate->copy()->addDays($duration - 1); // -1 because same day tasks have 0 duration
    }
    
    /**
     * Perform partial sync of template changes to existing project
     *
     * @param string $templateId
     * @param string $projectId
     * @param array $options
     * @return array
     */
    public function partialSync(string $templateId, string $projectId, array $options = []): array
    {
        $options['partial_sync'] = true;
        return $this->applyTemplate($templateId, $projectId, $options);
    }
    
    /**
     * Toggle conditional tag visibility for project tasks
     *
     * @param string $projectId
     * @param string $conditionalTag
     * @param bool $isVisible
     * @return int Number of affected tasks
     */
    public function toggleConditionalTag(string $projectId, string $conditionalTag, bool $isVisible): int
    {
        return ProjectTask::where('project_id', $projectId)
            ->where('conditional_tag', $conditionalTag)
            ->update([
                'is_hidden' => !$isVisible,
                'updated_by' => auth()->id(),
                'updated_at' => now()
            ]);
    }
    
    /**
     * Get all conditional tags used in a project
     *
     * @param string $projectId
     * @return array
     */
    public function getProjectConditionalTags(string $projectId): array
    {
        return ProjectTask::where('project_id', $projectId)
            ->whereNotNull('conditional_tag')
            ->distinct()
            ->pluck('conditional_tag')
            ->filter()
            ->values()
            ->toArray();
    }
    
    /**
     * Get conditional tag statistics for a project
     *
     * @param string $projectId
     * @return array
     */
    public function getConditionalTagStats(string $projectId): array
    {
        $stats = ProjectTask::where('project_id', $projectId)
            ->whereNotNull('conditional_tag')
            ->selectRaw('conditional_tag, COUNT(*) as total_tasks, SUM(CASE WHEN is_hidden = 0 THEN 1 ELSE 0 END) as visible_tasks')
            ->groupBy('conditional_tag')
            ->get()
            ->keyBy('conditional_tag')
            ->toArray();
            
        return $stats;
    }
}