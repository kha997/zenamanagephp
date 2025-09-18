<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TemplateTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'phase_key',
        'name',
        'description',
        'duration_days',
        'priority',
        'dependencies',
        'deliverables',
        'skills_required',
        'tools_required',
        'checklist',
        'approval_workflow',
        'estimated_cost',
        'team_size',
        'is_milestone',
        'sort_order'
    ];

    protected $casts = [
        'dependencies' => 'array',
        'deliverables' => 'array',
        'skills_required' => 'array',
        'tools_required' => 'array',
        'checklist' => 'array',
        'approval_workflow' => 'array',
        'estimated_cost' => 'decimal:2',
        'is_milestone' => 'boolean'
    ];

    /**
     * Get the template that owns this task
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class);
    }

    /**
     * Get tasks by phase
     */
    public function scopeByPhase($query, $phaseKey)
    {
        return $query->where('phase_key', $phaseKey);
    }

    /**
     * Get milestone tasks
     */
    public function scopeMilestones($query)
    {
        return $query->where('is_milestone', true);
    }

    /**
     * Get high priority tasks
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    /**
     * Calculate total duration for phase
     */
    public static function getPhaseDuration($templateId, $phaseKey)
    {
        return self::where('template_id', $templateId)
            ->where('phase_key', $phaseKey)
            ->sum('duration_days');
    }

    /**
     * Get task dependencies
     */
    public function getDependencies()
    {
        if (!$this->dependencies) {
            return collect();
        }

        return self::whereIn('id', $this->dependencies)->get();
    }

    /**
     * Check if task can start (all dependencies completed)
     */
    public function canStart($completedTasks = [])
    {
        if (!$this->dependencies) {
            return true;
        }

        return collect($this->dependencies)->every(function ($depId) use ($completedTasks) {
            return in_array($depId, $completedTasks);
        });
    }

    /**
     * Get critical path tasks
     */
    public static function getCriticalPath($templateId)
    {
        $tasks = self::where('template_id', $templateId)
            ->orderBy('duration_days', 'desc')
            ->get();

        $criticalPath = collect();
        $visited = [];

        foreach ($tasks as $task) {
            if (!in_array($task->id, $visited)) {
                $path = self::findLongestPath($task, $visited);
                if ($path->sum('duration_days') > $criticalPath->sum('duration_days')) {
                    $criticalPath = $path;
                }
            }
        }

        return $criticalPath;
    }

    /**
     * Find longest path from a task
     */
    private static function findLongestPath($task, &$visited)
    {
        $visited[] = $task->id;
        $path = collect([$task]);

        $dependencies = $task->getDependencies();
        foreach ($dependencies as $dep) {
            if (!in_array($dep->id, $visited)) {
                $depPath = self::findLongestPath($dep, $visited);
                if ($depPath->sum('duration_days') > $path->sum('duration_days')) {
                    $path = $depPath->merge([$task]);
                }
            }
        }

        return $path;
    }
}
