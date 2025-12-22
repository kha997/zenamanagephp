<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\TemplateSet;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\TemplateApplyLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

/**
 * TemplateApplyService
 * 
 * Service for applying template sets to projects.
 * Handles preview, dependency resolution, task creation, mapping, and logging.
 */
class TemplateApplyService
{
    private const CHUNK_SIZE = 500;
    private const MAX_TASKS_FOR_QUEUE = 5000;

    /**
     * Preview template application
     * 
     * @param Project $project The project to apply template to
     * @param TemplateSet $set The template set to apply
     * @param string|null $presetCode Optional preset code
     * @param array $selections Selection filters (phases, disciplines, tasks)
     * @param array $options Application options
     * @return array Preview statistics
     */
    public function preview(
        Project $project,
        TemplateSet $set,
        ?string $presetCode = null,
        array $selections = [],
        array $options = []
    ): array {
        // Resolve which tasks to include
        $tasks = $this->resolveTasks($set, $presetCode, $selections);

        // Count dependencies
        $dependencyCount = 0;
        $taskIds = $tasks->pluck('id')->toArray();
        foreach ($tasks as $task) {
            $deps = TemplateTaskDependency::where('task_id', $task->id)
                ->whereIn('depends_on_task_id', $taskIds)
                ->count();
            $dependencyCount += $deps;
        }

        // Calculate estimated duration
        $estimatedDuration = $tasks->sum('est_duration_days') ?? 0;

        // Breakdown by phase
        $phaseBreakdown = [];
        foreach ($tasks->groupBy('phase_id') as $phaseId => $phaseTasks) {
            $phase = $phaseTasks->first()->phase ?? null;
            if ($phase) {
                $phaseBreakdown[$phase->code] = $phaseTasks->count();
            }
        }

        // Breakdown by discipline
        $disciplineBreakdown = [];
        foreach ($tasks->groupBy('discipline_id') as $disciplineId => $disciplineTasks) {
            $discipline = $disciplineTasks->first()->discipline ?? null;
            if ($discipline) {
                $disciplineBreakdown[$discipline->code] = $disciplineTasks->count();
            }
        }

        return [
            'total_tasks' => $tasks->count(),
            'total_dependencies' => $dependencyCount,
            'estimated_duration' => $estimatedDuration,
            'breakdown' => [
                'phase' => $phaseBreakdown,
                'discipline' => $disciplineBreakdown,
            ],
        ];
    }

    /**
     * Apply template to project
     * 
     * @param Project $project The project to apply template to
     * @param TemplateSet $set The template set to apply
     * @param string|null $presetCode Optional preset code
     * @param array $selections Selection filters
     * @param array $options Application options
     * @param User $executor The user applying the template
     * @return array Application results
     */
    public function apply(
        Project $project,
        TemplateSet $set,
        User $executor,
        ?string $presetCode = null,
        array $selections = [],
        array $options = []
    ): array {
        $startTime = microtime(true);

        try {
            // Resolve tasks to apply
            $templateTasks = $this->resolveTasks($set, $presetCode, $selections);

            if ($templateTasks->isEmpty()) {
                throw new \Exception('No tasks to apply');
            }

            // Check if should queue for large templates
            $shouldQueue = $templateTasks->count() > self::MAX_TASKS_FOR_QUEUE;
            if ($shouldQueue) {
                // TODO: Queue job for large templates
                Log::warning('Template application exceeds queue threshold', [
                    'task_count' => $templateTasks->count(),
                    'project_id' => $project->id,
                ]);
            }

            // Build dependency graph and topological sort
            $sortedTasks = $this->topologicalSort($templateTasks);

            // Apply tasks in chunks, per phase
            $results = [
                'tasks_created' => 0,
                'dependencies_created' => 0,
                'warnings' => [],
                'errors' => [],
            ];

            $taskMapping = []; // Map template task ID to actual task ID
            $phaseGroups = $sortedTasks->groupBy('phase_id');

            foreach ($phaseGroups as $phaseId => $phaseTasks) {
                DB::transaction(function () use (
                    $project,
                    $phaseTasks,
                    $options,
                    &$taskMapping,
                    &$results
                ) {
                    $chunks = $phaseTasks->chunk(self::CHUNK_SIZE);

                    foreach ($chunks as $chunk) {
                        $tasksToInsert = []; // Array of ['template_task' => TemplateTask, 'data' => array]

                        foreach ($chunk as $templateTask) {
                            try {
                                // Check for conflicts
                                $conflictBehavior = $options['conflict_behavior'] ?? 'skip';
                                $existingTask = $this->checkTaskConflict($project, $templateTask, $conflictBehavior);

                                if ($existingTask && $conflictBehavior === 'skip') {
                                    $results['warnings'][] = "Task '{$templateTask->code}' already exists, skipped";
                                    continue;
                                }

                                // Map phase to status
                                $status = $this->mapPhaseToStatus($templateTask->phase, $options);

                                // Map discipline to tags
                                $tags = $this->mapDisciplineToTags($templateTask->discipline, $options);

                                // Map role to assignee
                                $assigneeId = $this->mapRoleToAssignee($project, $templateTask, $options);

                                // Prepare task data
                                $taskData = [
                                    'tenant_id' => $project->tenant_id,
                                    'project_id' => $project->id,
                                    'name' => $templateTask->name,
                                    'description' => $templateTask->description,
                                    'status' => $status,
                                    'priority' => 'normal',
                                    'estimated_hours' => $templateTask->est_duration_days ? ($templateTask->est_duration_days * 8) : null,
                                    'tags' => $tags,
                                    'assignee_id' => $assigneeId,
                                    'created_by' => $executor->id,
                                ];

                                if ($existingTask && $conflictBehavior === 'rename') {
                                    $taskData['name'] = $templateTask->name . ' (Copy)';
                                }

                                $tasksToInsert[] = [
                                    'template_task' => $templateTask,
                                    'data' => $taskData,
                                ];
                            } catch (\Exception $e) {
                                $results['errors'][] = "Error processing task '{$templateTask->code}': " . $e->getMessage();
                                Log::error('Template task processing error', [
                                    'task_code' => $templateTask->code,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        // Create tasks individually to get IDs
                        foreach ($tasksToInsert as $item) {
                            try {
                                $task = Task::create($item['data']);
                                $taskMapping[$item['template_task']->id] = $task->id;
                                $results['tasks_created']++;
                            } catch (\Exception $e) {
                                $results['errors'][] = "Failed to create task '{$item['template_task']->code}': " . $e->getMessage();
                            }
                        }
                    }
                });
            }

            // Create dependencies
            $results['dependencies_created'] = $this->createDependencies(
                $project,
                $templateTasks,
                $taskMapping,
                $results
            );

            // Create deliverable folders
            if ($options['create_deliverable_folders'] ?? false) {
                $this->createDeliverableFolders($project, $templateTasks, $results);
            }

            // Calculate duration
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Log application
            $this->logApplication(
                $project,
                $set,
                $presetCode,
                $selections,
                $results,
                $executor,
                $durationMs,
                null, // preset_id (not available in legacy apply method)
                $options
            );

            Log::info('Template applied successfully', [
                'project_id' => $project->id,
                'set_id' => $set->id,
                'tasks_created' => $results['tasks_created'],
                'dependencies_created' => $results['dependencies_created'],
            ]);

            return $results;
        } catch (\Exception $e) {
            Log::error('Template application failed', [
                'project_id' => $project->id,
                'set_id' => $set->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply template set to project (production endpoint)
     * 
     * Round 97: Apply Template Set → Create Project Tasks
     * 
     * This method wraps the entire apply operation in a single transaction
     * and accepts preset_id (ULID) instead of preset_code.
     * 
     * @param string $tenantId Tenant ID for validation
     * @param Project $project The project to apply template to
     * @param TemplateSet $set The template set to apply
     * @param TemplatePreset|null $preset Optional preset (by ID)
     * @param array $options Application options (include_dependencies, etc.)
     * @param User $executor The user applying the template
     * @return array Application results with created_tasks and created_dependencies
     */
    public function applyToProject(
        string $tenantId,
        Project $project,
        TemplateSet $set,
        ?TemplatePreset $preset = null,
        array $options = [],
        User $executor
    ): array {
        $startTime = microtime(true);

        // Validate tenant isolation
        if ($project->tenant_id !== $tenantId) {
            throw new \Exception('Project does not belong to tenant');
        }

        // Validate template set is accessible to tenant
        if ($set->tenant_id && $set->tenant_id !== $tenantId && !$set->is_global) {
            throw new \Exception('Template set is not accessible to tenant');
        }

        return DB::transaction(function () use (
            $project,
            $set,
            $preset,
            $options,
            $executor,
            $startTime
        ) {
            // Resolve preset code if preset provided
            $presetCode = $preset ? $preset->code : null;
            $selections = [];

            // Resolve tasks to apply
            $templateTasks = $this->resolveTasks($set, $presetCode, $selections);

            if ($templateTasks->isEmpty()) {
                throw new \Exception('No tasks to apply');
            }

            // Build dependency graph and topological sort
            $sortedTasks = $this->topologicalSort($templateTasks);

            // Initialize results
            $results = [
                'tasks_created' => 0,
                'dependencies_created' => 0,
                'warnings' => [],
                'errors' => [],
            ];

            $taskMapping = []; // Map template task ID to actual task ID
            $phaseGroups = $sortedTasks->groupBy('phase_id');

            // Create all tasks
            foreach ($phaseGroups as $phaseId => $phaseTasks) {
                $chunks = $phaseTasks->chunk(self::CHUNK_SIZE);

                foreach ($chunks as $chunk) {
                    foreach ($chunk as $templateTask) {
                        try {
                            // Prepare task data
                            $status = $this->mapPhaseToStatus($templateTask->phase ?? null, $options);
                            $tags = $this->mapDisciplineToTags($templateTask->discipline ?? null, $options);
                            $assigneeId = $this->mapRoleToAssignee($project, $templateTask, $options);

                            $taskData = [
                                'tenant_id' => $project->tenant_id,
                                'project_id' => $project->id,
                                'name' => $templateTask->name,
                                'description' => $templateTask->description,
                                'status' => $status,
                                'priority' => 'normal',
                                'estimated_hours' => $templateTask->est_duration_days ? ($templateTask->est_duration_days * 8) : null,
                                'tags' => $tags,
                                'assignee_id' => $assigneeId,
                                'created_by' => $executor->id,
                            ];

                            $task = Task::create($taskData);
                            $taskMapping[$templateTask->id] = $task->id;
                            $results['tasks_created']++;
                        } catch (\Exception $e) {
                            $results['errors'][] = "Failed to create task '{$templateTask->code}': " . $e->getMessage();
                            Log::error('Template task creation error', [
                                'task_code' => $templateTask->code,
                                'error' => $e->getMessage(),
                            ]);
                            throw $e; // Re-throw to rollback transaction
                        }
                    }
                }
            }

            // Create dependencies if enabled
            $includeDependencies = $options['include_dependencies'] ?? true;
            if ($includeDependencies) {
                $results['dependencies_created'] = $this->createDependencies(
                    $project,
                    $templateTasks,
                    $taskMapping,
                    $results
                );
            }

            // If any critical errors occurred (task creation failures), throw to rollback transaction
            // Dependency errors are non-critical and should not cause rollback
            $criticalErrors = array_filter($results['errors'], function ($error) {
                return strpos($error, 'Failed to create task') !== false;
            });
            
            if (!empty($criticalErrors)) {
                throw new \Exception('Template application failed: ' . implode('; ', $criticalErrors));
            }

            // Calculate duration
            $durationMs = (int)((microtime(true) - $startTime) * 1000);

            // Log application
            $this->logApplication(
                $project,
                $set,
                $presetCode,
                $selections,
                $results,
                $executor,
                $durationMs,
                $preset?->id, // preset_id (ULID)
                $options
            );

            Log::info('Template applied to project successfully', [
                'project_id' => $project->id,
                'set_id' => $set->id,
                'preset_id' => $preset?->id,
                'tasks_created' => $results['tasks_created'],
                'dependencies_created' => $results['dependencies_created'],
            ]);

            return [
                'project_id' => $project->id,
                'template_set_id' => $set->id,
                'preset_id' => $preset?->id,
                'created_tasks' => $results['tasks_created'],
                'created_dependencies' => $results['dependencies_created'],
            ];
        });
    }

    /**
     * Resolve which tasks to include based on selections and preset
     */
    private function resolveTasks(
        TemplateSet $set,
        ?string $presetCode,
        array $selections
    ): \Illuminate\Database\Eloquent\Collection {
        $query = $set->tasks()->with(['phase', 'discipline', 'dependencies']);

        // Apply preset filters if provided
        if ($presetCode) {
            $preset = $set->presets()->where('code', $presetCode)->first();
            if ($preset && !empty($preset->filters)) {
                $filters = $preset->filters;

                // Filter by phases
                if (!empty($filters['phases'])) {
                    $phaseIds = $set->phases()
                        ->whereIn('code', $filters['phases'])
                        ->pluck('id');
                    $query->whereIn('phase_id', $phaseIds);
                }

                // Filter by disciplines
                if (!empty($filters['disciplines'])) {
                    $disciplineIds = $set->disciplines()
                        ->whereIn('code', $filters['disciplines'])
                        ->pluck('id');
                    $query->whereIn('discipline_id', $disciplineIds);
                }

                // Filter by tasks
                if (!empty($filters['tasks'])) {
                    $query->whereIn('code', $filters['tasks']);
                }

                // Exclude tasks
                if (!empty($filters['exclude'])) {
                    $query->whereNotIn('code', $filters['exclude']);
                }
            }
        }

        // Apply manual selections
        if (!empty($selections['phases'])) {
            $phaseIds = $set->phases()
                ->whereIn('code', $selections['phases'])
                ->pluck('id');
            $query->whereIn('phase_id', $phaseIds);
        }

        if (!empty($selections['disciplines'])) {
            $disciplineIds = $set->disciplines()
                ->whereIn('code', $selections['disciplines'])
                ->pluck('id');
            $query->whereIn('discipline_id', $disciplineIds);
        }

        if (!empty($selections['tasks'])) {
            $query->whereIn('code', $selections['tasks']);
        }

        return $query->get();
    }

    /**
     * Topological sort of tasks based on dependencies
     */
    private function topologicalSort(\Illuminate\Database\Eloquent\Collection $tasks): \Illuminate\Database\Eloquent\Collection
    {
        $taskMap = $tasks->keyBy('id');
        $inDegree = [];
        $graph = [];

        // Initialize in-degree and build graph
        foreach ($tasks as $task) {
            $inDegree[$task->id] = 0;
            $graph[$task->id] = [];
        }

        // Build dependency graph
        foreach ($tasks as $task) {
            $dependencies = TemplateTaskDependency::where('task_id', $task->id)
                ->whereIn('depends_on_task_id', $tasks->pluck('id'))
                ->get();

            foreach ($dependencies as $dep) {
                $graph[$dep->depends_on_task_id][] = $task->id;
                $inDegree[$task->id]++;
            }
        }

        // Kahn's algorithm
        $queue = new \SplQueue();
        foreach ($inDegree as $taskId => $degree) {
            if ($degree === 0) {
                $queue->enqueue($taskId);
            }
        }

        $sorted = [];
        while (!$queue->isEmpty()) {
            $taskId = $queue->dequeue();
            $sorted[] = $taskId;

            foreach ($graph[$taskId] as $dependentId) {
                $inDegree[$dependentId]--;
                if ($inDegree[$dependentId] === 0) {
                    $queue->enqueue($dependentId);
                }
            }
        }

        // Return tasks in sorted order
        return $tasks->sortBy(function ($task) use ($sorted) {
            $index = array_search($task->id, $sorted);
            return $index !== false ? $index : 999999;
        })->values();
    }

    /**
     * Check for task conflicts
     */
    private function checkTaskConflict(Project $project, TemplateTask $templateTask, string $behavior): ?Task
    {
        $existing = Task::where('project_id', $project->id)
            ->where('name', $templateTask->name)
            ->first();

        return $existing;
    }

    /**
     * Map phase to task status
     */
    private function mapPhaseToStatus($phase, array $options): string
    {
        if (!($options['map_phase_to_kanban'] ?? false)) {
            return 'backlog';
        }

        // Simple mapping: phase code to status
        // Can be enhanced with a mapping table
        $phaseStatusMap = [
            'CONCEPT' => 'backlog',
            'DESIGN' => 'backlog',
            'CONSTRUCTION' => 'backlog',
            'QC' => 'backlog',
        ];

        return $phaseStatusMap[$phase->code] ?? 'backlog';
    }

    /**
     * Map discipline to task tags
     */
    private function mapDisciplineToTags($discipline, array $options): array
    {
        $tags = [];

        if ($discipline) {
            $tags[] = $discipline->code;
            if ($discipline->color_hex) {
                $tags[] = 'color:' . $discipline->color_hex;
            }
        }

        return $tags;
    }

    /**
     * Map role to assignee
     */
    private function mapRoleToAssignee(Project $project, TemplateTask $templateTask, array $options): ?string
    {
        if (!($options['auto_assign_by_role'] ?? false) || empty($templateTask->role_key)) {
            return null;
        }

        // Try to find user with matching role in project
        // This is a simplified implementation - can be enhanced
        try {
            $projectUsers = $project->users()->get();
            foreach ($projectUsers as $user) {
                if ($user->hasRole($templateTask->role_key)) {
                    return $user->id;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to map role to assignee', [
                'role_key' => $templateTask->role_key,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Create task dependencies
     */
    private function createDependencies(
        Project $project,
        \Illuminate\Database\Eloquent\Collection $templateTasks,
        array $taskMapping,
        array &$results
    ): int {
        $count = 0;

        foreach ($templateTasks as $templateTask) {
            $taskId = $taskMapping[$templateTask->id] ?? null;
            if (!$taskId) {
                continue;
            }

            $dependencies = TemplateTaskDependency::where('task_id', $templateTask->id)->get();

            foreach ($dependencies as $dep) {
                $dependsOnTaskId = $taskMapping[$dep->depends_on_task_id] ?? null;
                if (!$dependsOnTaskId) {
                    continue;
                }

                try {
                    TaskDependency::firstOrCreate([
                        'task_id' => $taskId,
                        'dependency_id' => $dependsOnTaskId,
                    ], [
                        'tenant_id' => $project->tenant_id,
                    ]);
                    $count++;
                } catch (\Exception $e) {
                    $results['warnings'][] = "Failed to create dependency for task '{$templateTask->code}': " . $e->getMessage();
                }
            }
        }

        return $count;
    }

    /**
     * Create deliverable folders
     */
    private function createDeliverableFolders(
        Project $project,
        \Illuminate\Database\Eloquent\Collection $templateTasks,
        array &$results
    ): void {
        $basePath = storage_path("app/projects/{$project->id}/deliverables");

        // Group by phase, then by discipline
        $grouped = [];
        foreach ($templateTasks as $task) {
            $phaseCode = $task->phase->code ?? 'UNKNOWN';
            $disciplineCode = $task->discipline->code ?? 'UNKNOWN';
            
            if (!isset($grouped[$phaseCode])) {
                $grouped[$phaseCode] = [];
            }
            $grouped[$phaseCode][$disciplineCode] = true;
        }

        foreach ($grouped as $phaseCode => $disciplines) {
            foreach (array_keys($disciplines) as $disciplineCode) {
                $folderPath = "{$basePath}/{$phaseCode}/{$disciplineCode}";

                try {
                    if (!File::exists($folderPath)) {
                        File::makeDirectory($folderPath, 0755, true);
                        Log::info('Created deliverable folder', [
                            'path' => $folderPath,
                            'project_id' => $project->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    $results['warnings'][] = "Failed to create folder {$phaseCode}/{$disciplineCode}: " . $e->getMessage();
                    Log::warning('Failed to create deliverable folder', [
                        'path' => $folderPath,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Log template application
     * 
     * Round 98: Added preset_id and options parameters
     */
    private function logApplication(
        Project $project,
        TemplateSet $set,
        ?string $presetCode,
        array $selections,
        array $results,
        User $executor,
        int $durationMs,
        ?string $presetId = null,
        array $options = []
    ): void {
        // Store resolved options (with defaults applied)
        $resolvedOptions = [
            'include_dependencies' => $options['include_dependencies'] ?? true,
        ];
        
        // Include any other options that were provided
        if (isset($options['create_deliverable_folders'])) {
            $resolvedOptions['create_deliverable_folders'] = $options['create_deliverable_folders'];
        }
        
        TemplateApplyLog::create([
            'project_id' => $project->id,
            'tenant_id' => $project->tenant_id,
            'set_id' => $set->id,
            'preset_code' => $presetCode,
            'preset_id' => $presetId,
            'selections' => $selections,
            'options' => $resolvedOptions,
            'counts' => [
                'tasks_created' => $results['tasks_created'],
                'dependencies_created' => $results['dependencies_created'],
                'warnings_count' => count($results['warnings']),
                'errors_count' => count($results['errors']),
            ],
            'executor_id' => $executor->id,
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }
}

