<?php declare(strict_types=1);

namespace App\Services;

use App\Models\TaskTemplate;
use App\Models\Template;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * TaskTemplateManagementService
 * 
 * Service for managing task templates with tenant isolation
 * Task templates belong to a Template (project template) and define checklist items
 */
class TaskTemplateManagementService
{
    use ServiceBaseTrait;

    /**
     * List task templates for a template (scoped by tenant and template)
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDirection
     * @return LengthAwarePaginator|Collection
     */
    public function listTaskTemplatesForTemplate(
        string $tenantId,
        string $templateId,
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'order_index',
        string $sortDirection = 'asc'
    ) {
        $this->validateTenantAccess($tenantId);
        
        // Verify template belongs to tenant
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }
        
        // Query task templates scoped by tenant and template
        // Use $template->id to ensure we use the exact ID from the verified template
        // Note: SoftDeletes trait automatically filters out soft-deleted records
        $query = TaskTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $tenantId)
            ->where('template_id', $template->id);

        // Filter by is_required
        if (isset($filters['is_required'])) {
            $query->where('is_required', (bool) $filters['is_required']);
        }

        // Search on name and description
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Default sorting by order_index, then name
        if ($sortBy === 'order_index') {
            $query->orderBy('order_index', $sortDirection)
                  ->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Create task template for a template
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param array $data
     * @return TaskTemplate
     */
    public function createTaskTemplateForTemplate(string $tenantId, string $templateId, array $data): TaskTemplate
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify template belongs to tenant
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }
        
        $taskTemplateData = [
            'tenant_id' => (string) $tenantId,
            'template_id' => $template->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'order_index' => $data['order_index'] ?? null,
            'phase_code' => $data['phase_code'] ?? null,
            'phase_label' => $data['phase_label'] ?? null,
            'group_label' => $data['group_label'] ?? null,
            'estimated_hours' => $data['estimated_hours'] ?? null,
            'is_required' => $data['is_required'] ?? true,
            'metadata' => $data['metadata'] ?? [],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ];

        // Use withoutGlobalScope to ensure tenant_id is set correctly
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')->create($taskTemplateData);
        
        $this->logCrudOperation('created', $taskTemplate);
        
        return $taskTemplate->fresh();
    }

    /**
     * Update task template for a template
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param string $taskTemplateId
     * @param array $data
     * @return TaskTemplate
     */
    public function updateTaskTemplateForTemplate(
        string $tenantId,
        string $templateId,
        string $taskTemplateId,
        array $data
    ): TaskTemplate {
        $this->validateTenantAccess($tenantId);
        
        // Verify template belongs to tenant
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }
        
        // Find task template with explicit tenant and template scoping
        // Use $template->id to ensure we use the exact ID from the verified template
        // Note: We need to use withTrashed() temporarily to check if record exists but is soft-deleted
        // Then filter it out if it's already deleted
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')
            ->withTrashed()
            ->where('id', $taskTemplateId)
            ->where('tenant_id', (string) $tenantId)
            ->where('template_id', $template->id)
            ->first();
        
        if (!$taskTemplate) {
            abort(404, 'Task template not found');
        }
        
        // If already soft-deleted, return 404 (can't update deleted record)
        if ($taskTemplate->trashed()) {
            abort(404, 'Task template not found');
        }

        $updateData = array_filter($data, fn($key) => in_array($key, [
            'name', 'description', 'order_index', 'phase_code', 'phase_label', 'group_label', 'estimated_hours', 'is_required', 'metadata'
        ]), ARRAY_FILTER_USE_KEY);

        $updateData['updated_by'] = Auth::id();

        $taskTemplate->update($updateData);
        
        $this->logCrudOperation('updated', $taskTemplate);
        
        return $taskTemplate->fresh();
    }

    /**
     * Delete task template for a template (soft delete)
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param string $taskTemplateId
     * @return void
     */
    public function deleteTaskTemplateForTemplate(
        string $tenantId,
        string $templateId,
        string $taskTemplateId
    ): void {
        $this->validateTenantAccess($tenantId);
        
        // Verify template belongs to tenant
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }
        
        // Find task template with explicit tenant and template scoping
        // Use $template->id to ensure we use the exact ID from the verified template
        // SoftDeletes trait automatically filters out soft-deleted records
        $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')
            ->where('id', $taskTemplateId)
            ->where('tenant_id', (string) $tenantId)
            ->where('template_id', $template->id)
            ->first();
        
        // If not found as active, check with withTrashed() to see if it's already soft-deleted
        if (!$taskTemplate) {
            $taskTemplate = TaskTemplate::withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('id', $taskTemplateId)
                ->where('tenant_id', (string) $tenantId)
                ->where('template_id', $template->id)
                ->first();
            
            // If found but already soft-deleted, return 404 (can't delete twice)
            if ($taskTemplate && $taskTemplate->trashed()) {
                abort(404, 'Task template not found');
            }
        }
        
        if (!$taskTemplate) {
            abort(404, 'Task template not found');
        }
        
        // If already soft-deleted, return 404 (can't delete twice)
        if ($taskTemplate->trashed()) {
            abort(404, 'Task template not found');
        }

        $taskTemplate->delete();
        
        $this->logCrudOperation('deleted', $taskTemplate);
    }

    /**
     * Get task template by ID for tenant and template
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param string $taskTemplateId
     * @return TaskTemplate|null
     */
    public function getTaskTemplateById(
        string $tenantId,
        string $templateId,
        string $taskTemplateId
    ): ?TaskTemplate {
        $this->validateTenantAccess($tenantId);
        
        return TaskTemplate::withoutGlobalScope('tenant')
            ->where('id', $taskTemplateId)
            ->where('tenant_id', (string) $tenantId)
            ->where('template_id', $templateId)
            ->first();
    }
}


