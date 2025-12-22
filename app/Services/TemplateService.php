<?php declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\ProjectPhase;
use Src\CoreProject\Models\ProjectTask;
use Src\Foundation\Helpers\AuthHelper;
use Src\WorkTemplate\Models\Template;

/**
 * Template Service for managing work templates and applying them to projects
 */
class TemplateService
{
    /**
     * Apply a template to a project
     *
     * @param string $templateId
     * @param string $projectId
     * @param array $options
     * @return array
     */
    public function applyTemplate(string $templateId, string $projectId, array $options = []): array
    {
        $template = Template::findOrFail($templateId);
        $project = Project::findOrFail($projectId);
        
        $conditionalTags = $options['conditional_tags'] ?? [];
        $partialSync = $options['partial_sync'] ?? false;
        $baseStartDate = isset($options['start_date']) 
            ? Carbon::parse($options['start_date']) 
            : Carbon::now();
        
        $templateData = $template->template_data;
        
        if (!isset($templateData['phases']) || !is_array($templateData['phases'])) {
            throw new \InvalidArgumentException('Template data must contain phases array');
        }
        
        $createdPhases = [];
        $createdTasks = [];
        
        foreach ($templateData['phases'] as $phaseIndex => $phaseData) {
            // Create phase
            $phase = $this->createPhaseFromTemplate(
                $project,
                $template,
                $phaseData,
                $phaseIndex + 1,
                $partialSync
            );
            
            $createdPhases[] = $phase;
            
            // Create tasks for this phase
            if (isset($phaseData['tasks']) && is_array($phaseData['tasks'])) {
                foreach ($phaseData['tasks'] as $taskIndex => $taskData) {
                    $task = $this->createTaskFromTemplate(
                        $project,
                        $phase,
                        $template,
                        $taskData,
                        $taskIndex + 1,
                        $conditionalTags,
                        $baseStartDate,
                        $partialSync
                    );
                    
                    $createdTasks[] = $task;
                }
            }
        }
        
        return [
            'template_id' => $templateId,
            'project_id' => $projectId,
            'phases' => $createdPhases,
            'tasks' => $createdTasks,
            'conditional_tags' => $conditionalTags
        ];
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
                    'updated_by' => $this->resolveActorId() // Sử dụng resolveActorId()
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
            'created_by' => $this->resolveActorId(), // Sử dụng resolveActorId()
            'updated_by' => $this->resolveActorId() // Sử dụng resolveActorId()
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
                    'updated_by' => $this->resolveActorId() // Sử dụng resolveActorId()
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
            'created_by' => $this->resolveActorId(), // Sử dụng resolveActorId()
            'updated_by' => $this->resolveActorId() // Sử dụng resolveActorId()
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
        // If task has specific start offset, apply it explicitly
        if (!empty($taskData['start_offset_days'])) {
            return $baseStartDate->copy()->addDays((int) $taskData['start_offset_days']);
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
        return $startDate->copy()->addDays(max(1, $duration) - 1);
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
                'updated_by' => $this->resolveActorId(), // Sử dụng resolveActorId()
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
        $tasks = ProjectTask::where('project_id', $projectId)
            ->whereNotNull('conditional_tag')
            ->get();
            
        $stats = [];
        
        foreach ($tasks as $task) {
            $tag = $task->conditional_tag;
            
            if (!isset($stats[$tag])) {
                $stats[$tag] = [
                    'tag' => $tag,
                    'total_tasks' => 0,
                    'visible_tasks' => 0,
                    'hidden_tasks' => 0
                ];
            }
            
            $stats[$tag]['total_tasks']++;
            
            if ($task->is_hidden) {
                $stats[$tag]['hidden_tasks']++;
            } else {
                $stats[$tag]['visible_tasks']++;
            }
        }
        
        return array_values($stats);
    }

    /**
     * Apply template to project (wrapper for backward compatibility)
     *
     * @param Template $template
     * @param string $projectId
     * @param string $mode
     * @param array $conditionalTags
     * @param array|null $phaseMapping
     * @param array|null $selectedItems
     * @param string $userId
     * @return array
     */
    public function applyToProject(
        Template $template,
        string $projectId,
        string $mode,
        array $conditionalTags = [],
        ?array $phaseMapping = null,
        ?array $selectedItems = null,
        string $userId = 'system'
    ): array {
        $options = [
            'conditional_tags' => $conditionalTags,
            'mode' => $mode,
            'phase_mapping' => $phaseMapping,
            'selected_items' => $selectedItems,
            'user_id' => $userId
        ];
        
        $result = $this->applyTemplate($template->id, $projectId, $options);
        
        // Transform result to match expected format
        return [
            'project_id' => $projectId,
            'template_id' => $template->id,
            'mode' => $mode,
            'phases_created' => count($result['phases']),
            'tasks_created' => count(array_filter($result['tasks'], fn($task) => !$task->is_hidden)),
            'tasks_hidden' => count(array_filter($result['tasks'], fn($task) => $task->is_hidden)),
            'conditional_tags' => $conditionalTags
        ];
    }
    
    /**
     * Validate conditional tags against template data
     *
     * @param array $jsonBody
     * @param array $validTags
     * @return bool
     */
    public function validateConditionalTags(array $jsonBody, array $validTags): bool
    {
        $templateTags = $this->extractConditionalTags($jsonBody);
        
        foreach ($templateTags as $tag) {
            if (!in_array($tag, $validTags)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extract all conditional tags from template JSON body
     *
     * @param array $jsonBody
     * @return array
     */
    public function extractConditionalTags(array $jsonBody): array
    {
        $tags = [];
        
        if (!isset($jsonBody['phases']) || !is_array($jsonBody['phases'])) {
            return $tags;
        }
        
        foreach ($jsonBody['phases'] as $phase) {
            if (!isset($phase['tasks']) || !is_array($phase['tasks'])) {
                continue;
            }
            
            foreach ($phase['tasks'] as $task) {
                if (!empty($task['conditional_tag'])) {
                    $tags[] = $task['conditional_tag'];
                }
            }
        }
        
        return array_unique($tags);
    }
    
    /**
     * Calculate estimated duration from template JSON body
     *
     * @param array $jsonBody
     * @return int Total duration in days
     */
    public function calculateEstimatedDuration(array $jsonBody): int
    {
        $totalDuration = 0;
        
        if (!isset($jsonBody['phases']) || !is_array($jsonBody['phases'])) {
            return $totalDuration;
        }
        
        foreach ($jsonBody['phases'] as $phase) {
            if (!isset($phase['tasks']) || !is_array($phase['tasks'])) {
                continue;
            }
            
            $phaseDuration = 0;
            foreach ($phase['tasks'] as $task) {
                $taskDuration = $task['duration_days'] ?? 1;
                $phaseDuration = max($phaseDuration, $taskDuration);
            }
            
            $totalDuration += $phaseDuration;
        }
        
        return $totalDuration;
    }
    
    /**
     * Check if project has active tasks
     *
     * @param string $projectId
     * @return bool
     */
    public function isProjectBusy(string $projectId): bool
    {
        return ProjectTask::where('project_id', $projectId)
            ->where('status', 'in_progress')
            ->exists();
    }
    
    /**
     * Resolve actor ID từ Auth facade với fallback an toàn
     * 
     * @return string|int
     */
    private function resolveActorId()
    {
        try {
            if (AuthHelper::check()) {
                return AuthHelper::id();
            }
            return 'system';
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from Auth facade', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }
}
