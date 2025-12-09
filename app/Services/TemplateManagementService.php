<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Template;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

/**
 * TemplateManagementService
 * 
 * Service for managing templates with tenant isolation
 * Follows the same pattern as ProjectManagementService
 */
class TemplateManagementService
{
    use ServiceBaseTrait;

    protected string $modelClass = Template::class;

    /**
     * List templates for tenant with filtering
     * 
     * @param string $tenantId
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDirection
     * @return LengthAwarePaginator
     */
    public function listTemplatesForTenant(
        string $tenantId,
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'updated_at',
        string $sortDirection = 'desc'
    ): LengthAwarePaginator {
        $this->validateTenantAccess($tenantId);
        
        // Use withoutGlobalScope to avoid double filtering when tenantId is explicitly provided
        // The scope filters by Auth::user()->tenant_id, but we want to filter by the provided tenantId
        // Cast tenantId to string to ensure consistent comparison
        $query = Template::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $tenantId);

        // Filter by type (mapped from category for backward compatibility)
        if (isset($filters['type']) && $filters['type']) {
            // Map type to category: project->project, task->task, document->document, checklist->workflow
            $categoryMap = [
                'project' => 'project',
                'task' => 'task',
                'document' => 'document',
                'checklist' => 'workflow',
            ];
            $category = $categoryMap[$filters['type']] ?? $filters['type'];
            $query->where('category', $category);
        }

        // Filter by is_active
        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        // Search on name and description
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Create template for tenant
     * 
     * @param string $tenantId
     * @param array $data
     * @return Template
     */
    public function createTemplateForTenant(string $tenantId, array $data): Template
    {
        $this->validateTenantAccess($tenantId);
        
        // Map type to category
        $categoryMap = [
            'project' => 'project',
            'task' => 'task',
            'document' => 'document',
            'checklist' => 'workflow',
        ];
        
        $category = $categoryMap[$data['type']] ?? $data['type'] ?? 'general';
        
        $templateData = [
            'tenant_id' => (string) $tenantId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'category' => $category,
            'is_active' => $data['is_active'] ?? true,
            'metadata' => $data['metadata'] ?? [],
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'status' => Template::STATUS_DRAFT,
            'version' => 1,
        ];

        // Use withoutGlobalScope to ensure tenant_id is set correctly
        // even if there's no authenticated user (e.g., in tests)
        $template = Template::withoutGlobalScope('tenant')->create($templateData);
        
        $this->logCrudOperation('created', $template);
        
        return $template->fresh();
    }

    /**
     * Update template for tenant
     * 
     * @param string $tenantId
     * @param string $templateId
     * @param array $data
     * @return Template
     */
    public function updateTemplateForTenant(string $tenantId, string $templateId, array $data): Template
    {
        $this->validateTenantAccess($tenantId);
        
        // Use withoutGlobalScope to avoid double filtering, but still filter explicitly
        // Cast tenantId to string to ensure consistent comparison
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }

        // Map type to category if provided
        if (isset($data['type'])) {
            $categoryMap = [
                'project' => 'project',
                'task' => 'task',
                'document' => 'document',
                'checklist' => 'workflow',
            ];
            $data['category'] = $categoryMap[$data['type']] ?? $data['type'];
            unset($data['type']);
        }

        $updateData = array_filter($data, fn($key) => in_array($key, [
            'name', 'description', 'category', 'is_active', 'metadata'
        ]), ARRAY_FILTER_USE_KEY);

        $updateData['updated_by'] = Auth::id();

        $template->update($updateData);
        
        $this->logCrudOperation('updated', $template);
        
        return $template->fresh();
    }

    /**
     * Delete template for tenant (soft delete)
     * 
     * @param string $tenantId
     * @param string $templateId
     * @return void
     */
    public function deleteTemplateForTenant(string $tenantId, string $templateId): void
    {
        $this->validateTenantAccess($tenantId);
        
        // Use withoutGlobalScope to avoid double filtering, but still filter explicitly
        // Cast tenantId to string to ensure consistent comparison
        $template = Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$template) {
            abort(404, 'Template not found');
        }

        $template->delete();
        
        $this->logCrudOperation('deleted', $template);
    }

    /**
     * Get template by ID for tenant
     * 
     * @param string $tenantId
     * @param string $templateId
     * @return Template|null
     */
    public function getTemplateById(string $tenantId, string $templateId): ?Template
    {
        $this->validateTenantAccess($tenantId);
        
        // Use withoutGlobalScope to avoid double filtering, but still filter explicitly
        // Cast tenantId to string to ensure consistent comparison
        return Template::withoutGlobalScope('tenant')
            ->where('id', $templateId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
    }
}

