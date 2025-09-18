<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectTemplate extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'name',
        'category',
        'description',
        'phases',
        'default_settings',
        'is_active',
        'is_default',
        'version',
        'created_by',
        'tags',
        'complexity_level',
        'estimated_duration',
        'estimated_cost',
        'team_size_min',
        'team_size_max'
    ];

    protected $casts = [
        'phases' => 'array',
        'default_settings' => 'array',
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'tags' => 'array',
        'estimated_duration' => 'integer',
        'estimated_cost' => 'decimal:2',
        'team_size_min' => 'integer',
        'team_size_max' => 'integer'
    ];

    /**
     * Get templates by category
     */
    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get active templates
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get default template
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Get template tasks
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(TemplateTask::class);
    }

    /**
     * Get tasks by phase
     */
    public function getTasksByPhase($phaseKey)
    {
        return $this->tasks()->byPhase($phaseKey)->orderBy('sort_order')->get();
    }

    /**
     * Get milestone tasks
     */
    public function getMilestoneTasks()
    {
        return $this->tasks()->milestones()->orderBy('sort_order')->get();
    }

    /**
     * Calculate total estimated duration
     */
    public function getTotalDuration()
    {
        return $this->tasks()->sum('duration_days');
    }

    /**
     * Calculate total estimated cost
     */
    public function getTotalCost()
    {
        return $this->tasks()->sum('estimated_cost');
    }

    /**
     * Get critical path
     */
    public function getCriticalPath()
    {
        return TemplateTask::getCriticalPath($this->id);
    }

    /**
     * Get template statistics
     */
    public function getStatistics()
    {
        $tasks = $this->tasks;
        
        return [
            'total_tasks' => $tasks->count(),
            'total_duration' => $tasks->sum('duration_days'),
            'total_cost' => $tasks->sum('estimated_cost'),
            'milestone_count' => $tasks->where('is_milestone', true)->count(),
            'high_priority_tasks' => $tasks->whereIn('priority', ['high', 'critical'])->count(),
            'phases_count' => $tasks->pluck('phase_key')->unique()->count(),
            'critical_path_duration' => $this->getCriticalPath()->sum('duration_days')
        ];
    }

    /**
     * Apply template to project with advanced options
     */
    public function applyToProject($project, $options = [])
    {
        $selectedPhases = $options['phases'] ?? null;
        $includeTasks = $options['include_tasks'] ?? true;
        $includeDependencies = $options['include_dependencies'] ?? true;
        $includeDeliverables = $options['include_deliverables'] ?? true;

        $phases = $selectedPhases ? 
            array_intersect_key($this->phases, array_flip($selectedPhases)) : 
            $this->phases;

        $result = [
            'phases' => $phases,
            'settings' => $this->default_settings,
            'applied_template' => $this->name,
            'template_version' => $this->version ?? '1.0'
        ];

        if ($includeTasks) {
            $tasks = $this->tasks;
            if ($selectedPhases) {
                $tasks = $tasks->whereIn('phase_key', $selectedPhases);
            }
            
            $result['tasks'] = $tasks->map(function ($task) use ($includeDependencies, $includeDeliverables) {
                $taskData = [
                    'name' => $task->name,
                    'description' => $task->description,
                    'duration_days' => $task->duration_days,
                    'priority' => $task->priority,
                    'phase_key' => $task->phase_key,
                    'estimated_cost' => $task->estimated_cost,
                    'team_size' => $task->team_size,
                    'is_milestone' => $task->is_milestone,
                    'sort_order' => $task->sort_order
                ];

                if ($includeDependencies && $task->dependencies) {
                    $taskData['dependencies'] = $task->dependencies;
                }

                if ($includeDeliverables && $task->deliverables) {
                    $taskData['deliverables'] = $task->deliverables;
                    $taskData['skills_required'] = $task->skills_required;
                    $taskData['tools_required'] = $task->tools_required;
                    $taskData['checklist'] = $task->checklist;
                }

                return $taskData;
            })->toArray();
        }

        return $result;
    }

    /**
     * Duplicate template
     */
    public function duplicate($newName = null)
    {
        $newTemplate = $this->replicate();
        $newTemplate->name = $newName ?? $this->name . ' (Copy)';
        $newTemplate->version = '1.0';
        $newTemplate->is_default = false;
        $newTemplate->save();

        // Duplicate tasks
        foreach ($this->tasks as $task) {
            $newTask = $task->replicate();
            $newTask->template_id = $newTemplate->id;
            $newTask->save();
        }

        return $newTemplate;
    }

    /**
     * Validate template completeness
     */
    public function validateCompleteness()
    {
        $errors = [];
        
        if (!$this->phases || empty($this->phases)) {
            $errors[] = 'Template must have at least one phase';
        }

        if ($this->tasks()->count() === 0) {
            $errors[] = 'Template must have at least one task';
        }

        // Check for orphaned tasks (tasks without phases)
        $orphanedTasks = $this->tasks()->whereNotIn('phase_key', array_keys($this->phases ?? []))->count();
        if ($orphanedTasks > 0) {
            $errors[] = "Found {$orphanedTasks} tasks without valid phases";
        }

        // Check for circular dependencies
        $circularDeps = $this->checkCircularDependencies();
        if (!empty($circularDeps)) {
            $errors[] = 'Circular dependencies found: ' . implode(', ', $circularDeps);
        }

        return $errors;
    }

    /**
     * Check for circular dependencies
     */
    private function checkCircularDependencies()
    {
        $tasks = $this->tasks;
        $circular = [];

        foreach ($tasks as $task) {
            if ($task->dependencies) {
                $visited = [];
                if ($this->hasCircularDependency($task, $visited)) {
                    $circular[] = $task->name;
                }
            }
        }

        return $circular;
    }

    /**
     * Check if task has circular dependency
     */
    private function hasCircularDependency($task, &$visited)
    {
        if (in_array($task->id, $visited)) {
            return true;
        }

        $visited[] = $task->id;

        if ($task->dependencies) {
            foreach ($task->dependencies as $depId) {
                $depTask = $this->tasks->find($depId);
                if ($depTask && $this->hasCircularDependency($depTask, $visited)) {
                    return true;
                }
            }
        }

        return false;
    }
}