<?php declare(strict_types=1);

namespace App\Services;

use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * TaskTemplateService
 * 
 * Service for managing WBS-style task template sets with phases, disciplines, tasks, and dependencies.
 * 
 * Round 95: Task Template Library – Backend v1
 */
class TaskTemplateService
{
    use ServiceBaseTrait;

    protected string $modelClass = TemplateSet::class;

    /**
     * Get all template sets for a tenant
     * 
     * @param string|null $tenantId Tenant ID (defaults to current user's tenant)
     * @param array $filters Optional filters (search, is_active)
     * @param int $perPage Pagination per page
     * @return LengthAwarePaginator|Collection
     */
    public function getTemplateSets(?string $tenantId = null, array $filters = [], int $perPage = 15)
    {
        $tenantId = $tenantId ?? (string) (Auth::user()?->tenant_id ?? '');
        
        $query = TemplateSet::query()
            ->where(function ($q) use ($tenantId) {
                // Tenant-specific templates
                $q->where('tenant_id', $tenantId)
                  // Global templates (tenant_id is null)
                  ->orWhereNull('tenant_id');
            })
            ->with(['creator'])
            ->orderBy('created_at', 'desc');

        // Apply filters
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get a template set with full tree (phases, disciplines, tasks, dependencies)
     * 
     * @param string $setId Template set ID
     * @param string|null $tenantId Tenant ID
     * @return TemplateSet|null
     */
    public function getTemplateSetWithTree(string $setId, ?string $tenantId = null): ?TemplateSet
    {
        $tenantId = $tenantId ?? (string) (Auth::user()?->tenant_id ?? '');
        
        $templateSet = TemplateSet::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            })
            ->where('id', $setId)
            ->with([
                'phases' => function ($query) {
                    $query->orderBy('order_index');
                },
                'disciplines' => function ($query) {
                    $query->orderBy('order_index');
                },
                'tasks' => function ($query) {
                    $query->orderBy('order_index')
                          ->with(['phase', 'discipline', 'dependencies.dependsOn']);
                },
                'creator'
            ])
            ->first();

        return $templateSet;
    }

    /**
     * Create a new template set
     * 
     * @param array $data Template set data
     * @param string|null $tenantId Tenant ID
     * @return TemplateSet
     */
    public function createTemplateSet(array $data, ?string $tenantId = null): TemplateSet
    {
        $tenantId = $tenantId ?? (string) (Auth::user()?->tenant_id ?? '');
        
        DB::beginTransaction();
        try {
            $templateSet = TemplateSet::create([
                'tenant_id' => $tenantId ?: null,
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'version' => $data['version'] ?? '1.0',
                'is_active' => $data['is_active'] ?? true,
                'created_by' => Auth::id(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            // Create phases if provided
            if (isset($data['phases']) && is_array($data['phases'])) {
                foreach ($data['phases'] as $phaseData) {
                    $this->createPhase($templateSet->id, $phaseData);
                }
            }

            // Create disciplines if provided
            if (isset($data['disciplines']) && is_array($data['disciplines'])) {
                foreach ($data['disciplines'] as $disciplineData) {
                    $this->createDiscipline($templateSet->id, $disciplineData);
                }
            }

            // Create tasks if provided
            if (isset($data['tasks']) && is_array($data['tasks'])) {
                foreach ($data['tasks'] as $taskData) {
                    $this->createTask($templateSet->id, $taskData);
                }
            }

            DB::commit();
            
            return $templateSet->fresh(['phases', 'disciplines', 'tasks', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update template set metadata
     * 
     * @param string $setId Template set ID
     * @param array $data Update data
     * @param string|null $tenantId Tenant ID
     * @return TemplateSet
     */
    public function updateTemplateSet(string $setId, array $data, ?string $tenantId = null): TemplateSet
    {
        $tenantId = $tenantId ?? (string) (Auth::user()?->tenant_id ?? '');
        
        $templateSet = TemplateSet::query()
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                  ->orWhereNull('tenant_id');
            })
            ->where('id', $setId)
            ->firstOrFail();

        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        
        if (isset($data['description'])) {
            $updateData['description'] = $data['description'];
        }
        
        if (isset($data['version'])) {
            $updateData['version'] = $data['version'];
        }
        
        if (isset($data['is_active'])) {
            $updateData['is_active'] = (bool) $data['is_active'];
        }
        
        if (isset($data['metadata'])) {
            $updateData['metadata'] = $data['metadata'];
        }

        $templateSet->update($updateData);

        return $templateSet->fresh(['phases', 'disciplines', 'tasks', 'creator']);
    }

    /**
     * Duplicate a template set
     * 
     * @param string $setId Template set ID to duplicate
     * @param array $data Optional override data (name, code, etc.)
     * @param string|null $tenantId Tenant ID
     * @return TemplateSet
     */
    public function duplicateTemplateSet(string $setId, array $data = [], ?string $tenantId = null): TemplateSet
    {
        $tenantId = $tenantId ?? (string) (Auth::user()?->tenant_id ?? '');
        
        $sourceSet = $this->getTemplateSetWithTree($setId, $tenantId);
        
        if (!$sourceSet) {
            throw new \RuntimeException('Template set not found');
        }

        DB::beginTransaction();
        try {
            // Create new template set
            $newSet = TemplateSet::create([
                'tenant_id' => $tenantId ?: null,
                'code' => $data['code'] ?? $sourceSet->code . '_copy',
                'name' => $data['name'] ?? $sourceSet->name . ' (Copy)',
                'description' => $data['description'] ?? $sourceSet->description,
                'version' => $data['version'] ?? '1.0',
                'is_active' => $data['is_active'] ?? true,
                'created_by' => Auth::id(),
                'metadata' => $data['metadata'] ?? $sourceSet->metadata,
            ]);

            // Duplicate phases
            $phaseMap = [];
            foreach ($sourceSet->phases as $phase) {
                $newPhase = TemplatePhase::create([
                    'set_id' => $newSet->id,
                    'code' => $phase->code,
                    'name' => $phase->name,
                    'order_index' => $phase->order_index,
                    'metadata' => $phase->metadata,
                ]);
                $phaseMap[$phase->id] = $newPhase->id;
            }

            // Duplicate disciplines
            $disciplineMap = [];
            foreach ($sourceSet->disciplines as $discipline) {
                $newDiscipline = TemplateDiscipline::create([
                    'set_id' => $newSet->id,
                    'code' => $discipline->code,
                    'name' => $discipline->name,
                    'color_hex' => $discipline->color_hex,
                    'order_index' => $discipline->order_index,
                    'metadata' => $discipline->metadata,
                ]);
                $disciplineMap[$discipline->id] = $newDiscipline->id;
            }

            // Duplicate tasks
            $taskMap = [];
            foreach ($sourceSet->tasks as $task) {
                $newTask = TemplateTask::create([
                    'set_id' => $newSet->id,
                    'phase_id' => $phaseMap[$task->phase_id] ?? $task->phase_id,
                    'discipline_id' => $disciplineMap[$task->discipline_id] ?? $task->discipline_id,
                    'code' => $task->code,
                    'name' => $task->name,
                    'description' => $task->description,
                    'est_duration_days' => $task->est_duration_days,
                    'role_key' => $task->role_key,
                    'deliverable_type' => $task->deliverable_type,
                    'order_index' => $task->order_index,
                    'is_optional' => $task->is_optional,
                    'metadata' => $task->metadata,
                ]);
                $taskMap[$task->id] = $newTask->id;
            }

            // Duplicate dependencies
            foreach ($sourceSet->tasks as $task) {
                foreach ($task->dependencies as $dependency) {
                    if (isset($taskMap[$task->id]) && isset($taskMap[$dependency->depends_on_task_id])) {
                        TemplateTaskDependency::create([
                            'set_id' => $newSet->id,
                            'task_id' => $taskMap[$task->id],
                            'depends_on_task_id' => $taskMap[$dependency->depends_on_task_id],
                        ]);
                    }
                }
            }

            // Duplicate presets
            foreach ($sourceSet->presets as $preset) {
                TemplatePreset::create([
                    'set_id' => $newSet->id,
                    'code' => $preset->code,
                    'name' => $preset->name,
                    'description' => $preset->description,
                    'filters' => $preset->filters,
                ]);
            }

            DB::commit();
            
            return $newSet->fresh(['phases', 'disciplines', 'tasks', 'presets', 'creator']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create a phase
     * 
     * @param string $setId Template set ID
     * @param array $data Phase data
     * @return TemplatePhase
     */
    protected function createPhase(string $setId, array $data): TemplatePhase
    {
        return TemplatePhase::create([
            'set_id' => $setId,
            'code' => $data['code'],
            'name' => $data['name'],
            'order_index' => $data['order_index'] ?? 0,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Create a discipline
     * 
     * @param string $setId Template set ID
     * @param array $data Discipline data
     * @return TemplateDiscipline
     */
    protected function createDiscipline(string $setId, array $data): TemplateDiscipline
    {
        return TemplateDiscipline::create([
            'set_id' => $setId,
            'code' => $data['code'],
            'name' => $data['name'],
            'color_hex' => $data['color_hex'] ?? null,
            'order_index' => $data['order_index'] ?? 0,
            'metadata' => $data['metadata'] ?? null,
        ]);
    }

    /**
     * Create a task
     * 
     * @param string $setId Template set ID
     * @param array $data Task data
     * @return TemplateTask
     */
    protected function createTask(string $setId, array $data): TemplateTask
    {
        $task = TemplateTask::create([
            'set_id' => $setId,
            'phase_id' => $data['phase_id'],
            'discipline_id' => $data['discipline_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'est_duration_days' => $data['est_duration_days'] ?? null,
            'role_key' => $data['role_key'] ?? null,
            'deliverable_type' => $data['deliverable_type'] ?? null,
            'order_index' => $data['order_index'] ?? 0,
            'is_optional' => $data['is_optional'] ?? false,
            'metadata' => $data['metadata'] ?? null,
        ]);

        // Create dependencies if provided
        if (isset($data['dependencies']) && is_array($data['dependencies'])) {
            foreach ($data['dependencies'] as $dependsOnTaskId) {
                TemplateTaskDependency::create([
                    'set_id' => $setId,
                    'task_id' => $task->id,
                    'depends_on_task_id' => $dependsOnTaskId,
                ]);
            }
        }

        return $task;
    }
}

