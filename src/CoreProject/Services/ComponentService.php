<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\Foundation\Helpers\AuthHelper;

use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Events\ComponentProgressUpdated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Auth; // Thêm import Auth facade
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service xử lý business logic cho Components
 * Quản lý CRUD operations, progress calculation và roll-up logic
 */
class ComponentService
{
    /**
     * Resolve actor ID từ auth helper với fallback an toàn
     * 
     * @return string|int
     */
    private function resolveActorId()
    {
        try {
            return AuthHelper::idOrSystem();
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from auth helper', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }
    
    /**
     * Tạo component mới cho project
     *
     * @param string $projectId
     * @param array $data
     * @return Component
     * @throws Exception
     */
    public function createComponent(string $projectId, array $data): Component
    {
        return DB::transaction(function () use ($projectId, $data) {
            // Validate project exists
            $project = Project::findOrFail($projectId);
            
            // Validate parent component if provided
            if (!empty($data['parent_component_id'])) {
                $parentComponent = Component::where('id', $data['parent_component_id'])
                    ->where('project_id', $projectId)
                    ->firstOrFail();
            }
            
            // Create component
            $component = Component::create([
                'project_id' => $projectId,
                'parent_component_id' => $data['parent_component_id'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'progress_percent' => $data['progress_percent'] ?? 0,
                'planned_cost' => $data['planned_cost'] ?? 0,
                'actual_cost' => $data['actual_cost'] ?? 0,
                'tags' => $data['tags'] ?? null,
                'visibility' => $data['visibility'] ?? 'internal',
                'client_approved' => $data['client_approved'] ?? false,
                'created_by' => $this->resolveActorId(),
                'updated_by' => $this->resolveActorId(),
            ]);
            
            $tenantId = (string) (session('tenant_id') ?? 'system');

            // Dispatch event for component creation
            Event::dispatch(new ComponentProgressUpdated(
                $component->id,
                $projectId,
                $this->resolveActorId(),
                $tenantId,
                0, // old progress
                $component->progress_percent, // new progress
                0, // old cost
                $component->actual_cost, // new cost
                ['progress_percent', 'actual_cost'], // changed fields
                now()
            ));
            
            return $component;
        });
    }
    
    /**
     * Cập nhật component và trigger roll-up calculation
     *
     * @param string $componentId
     * @param array $data
     * @return Component
     * @throws Exception
     */
    public function updateComponent(string $componentId, array $data): Component
    {
        return DB::transaction(function () use ($componentId, $data) {
            $component = Component::findOrFail($componentId);
            
            // Store old values for event
            $oldProgress = $component->progress_percent;
            $oldCost = $component->actual_cost;
            
            // Update component
            $component->update(array_merge($data, [
                'updated_by' => $this->resolveActorId(),
            ]));
            
            // Check if progress or cost changed
            $changedFields = [];
            if ($oldProgress != $component->progress_percent) {
                $changedFields[] = 'progress_percent';
            }
            if ($oldCost != $component->actual_cost) {
                $changedFields[] = 'actual_cost';
            }
            
            $tenantId = (string) (session('tenant_id') ?? 'system');

            // Dispatch event if progress or cost changed
            if (!empty($changedFields)) {
                Event::dispatch(new ComponentProgressUpdated(
                    $component->id,
                    $component->project_id,
                    $this->resolveActorId(),
                    $tenantId,
                    $oldProgress,
                    $component->progress_percent,
                    $oldCost,
                    $component->actual_cost,
                    $changedFields,
                    now()
                ));
            }
            
            return $component->fresh();
        });
    }
    
    /**
     * Xóa component và tất cả children
     *
     * @param string $componentId
     * @return bool
     * @throws Exception
     */
    public function deleteComponent(string $componentId): bool
    {
        return DB::transaction(function () use ($componentId) {
            $component = Component::findOrFail($componentId);
            
            // Check for child components
            $childCount = Component::where('parent_component_id', $componentId)->count();
            if ($childCount > 0) {
                throw new Exception('Cannot delete component with child components. Delete children first.');
            }
            
            // Check for linked tasks
            $taskCount = $component->tasks()->count();
            if ($taskCount > 0) {
                throw new Exception('Cannot delete component with linked tasks. Unlink tasks first.');
            }
            
            $projectId = $component->project_id;
            
            // Delete component
            $deleted = $component->delete();
            
            // Trigger roll-up calculation for project
            if ($deleted) {
                $tenantId = (string) (session('tenant_id') ?? 'system');

                Event::dispatch(new ComponentProgressUpdated(
                    $componentId,
                    $projectId,
                    $this->resolveActorId(), // Thay đổi từ auth()->id()
                    $tenantId,
                    $component->progress_percent,
                    0, // component deleted
                    $component->actual_cost,
                    0, // cost removed
                    ['deleted'],
                    now()
                ));
            }
            
            return $deleted;
        });
    }
    
    /**
     * Lấy cây phân cấp components của project
     *
     * @param string $projectId
     * @return Collection
     */
    public function getComponentTree(string $projectId): Collection
    {
        // Lấy tất cả root components và load nested children
        return Component::where('project_id', $projectId)
            ->whereNull('parent_component_id')
            ->with(['children' => function ($query) {
                $query->with('children.children.children'); // Support up to 4 levels deep
            }])
            ->orderBy('name')
            ->get();
    }
    
    /**
     * Lấy danh sách components với filter và pagination
     *
     * @param string $projectId
     * @param array $filters
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getComponentsList(string $projectId, array $filters = [])
    {
        $query = Component::where('project_id', $projectId);
        
        // Filter by parent component
        if (isset($filters['parent_id'])) {
            $query->where('parent_component_id', $filters['parent_id']);
        }
        
        // Filter root components only
        if (isset($filters['root_only']) && $filters['root_only']) {
            $query->whereNull('parent_component_id');
        }
        
        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }
        
        // Filter by visibility
        if (isset($filters['visibility'])) {
            $query->where('visibility', $filters['visibility']);
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'name';
        $sortDirection = $filters['sort_direction'] ?? 'asc';
        $query->orderBy($sortBy, $sortDirection);
        
        // Load relationships
        $query->with(['parent', 'children', 'tasks']);
        
        return $query->paginate($filters['per_page'] ?? 15);
    }
    
    /**
     * Tính toán lại progress và cost cho component dựa trên children
     *
     * @param string $componentId
     * @return Component
     */
    public function recalculateFromChildren(string $componentId): Component
    {
        return DB::transaction(function () use ($componentId) {
            $component = Component::with('children')->findOrFail($componentId);
            
            if ($component->children->isEmpty()) {
                return $component; // No children, nothing to recalculate
            }
            
            $oldProgress = $component->progress_percent;
            $oldCost = $component->actual_cost;
            
            // Recalculate using model method
            $component->recalculateFromChildren();
            
            // Check if values changed
            $changedFields = [];
            if ($oldProgress != $component->progress_percent) {
                $changedFields[] = 'progress_percent';
            }
            if ($oldCost != $component->actual_cost) {
                $changedFields[] = 'actual_cost';
            }
            
            // Dispatch event if values changed
            if (!empty($changedFields)) {
                Event::dispatch(new ComponentProgressUpdated(
                    $component->id,
                    $component->project_id,
                    $this->resolveActorId(), // Thay đổi từ auth()->id()
                    session('tenant_id'),
                    $oldProgress,
                    $component->progress_percent,
                    $oldCost,
                    $component->actual_cost,
                    $changedFields,
                    now()
                ));
            }
            
            return $component->fresh();
        });
    }
    
    /**
     * Bulk update progress cho nhiều components
     *
     * @param array $updates Array of ['component_id' => progress_percent]
     * @return array Updated components
     */
    public function bulkUpdateProgress(array $updates): array
    {
        return DB::transaction(function () use ($updates) {
            $updatedComponents = [];
            
            foreach ($updates as $componentId => $progressPercent) {
                $component = Component::findOrFail($componentId);
                $oldProgress = $component->progress_percent;
                
                $component->update([
                    'progress_percent' => $progressPercent,
                    'updated_by' => $this->resolveActorId(),
                ]);
                
                // Dispatch event
                Event::dispatch(new ComponentProgressUpdated(
                    $component->id,
                    $component->project_id,
                    $this->resolveActorId(),
                    session('tenant_id'),
                    $oldProgress,
                    $progressPercent,
                    $component->actual_cost,
                    $component->actual_cost,
                    ['progress_percent'],
                    now()
                ));
                
                $updatedComponents[] = $component->fresh();
            }
            
            return $updatedComponents;
        });
    }
    
    /**
     * Lấy thống kê components của project
     *
     * @param string $projectId
     * @return array
     */
    public function getProjectComponentStats(string $projectId): array
    {
        $components = Component::where('project_id', $projectId)->get();
        
        return [
            'total_components' => $components->count(),
            'root_components' => $components->whereNull('parent_component_id')->count(),
            'completed_components' => $components->where('progress_percent', 100)->count(),
            'in_progress_components' => $components->where('progress_percent', '>', 0)
                                                 ->where('progress_percent', '<', 100)->count(),
            'not_started_components' => $components->where('progress_percent', 0)->count(),
            'total_planned_cost' => $components->sum('planned_cost'),
            'total_actual_cost' => $components->sum('actual_cost'),
            'average_progress' => $components->avg('progress_percent'),
            'cost_variance' => $components->sum('actual_cost') - $components->sum('planned_cost'),
        ];
    }
}
