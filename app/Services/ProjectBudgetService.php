<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * ProjectBudgetService
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Handles tenant- and project-scoped CRUD operations for budget lines
 */
class ProjectBudgetService
{
    use ServiceBaseTrait;

    /**
     * List budget lines for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return Collection
     */
    public function listBudgetLinesForProject(string $tenantId, Project $project): Collection
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        return ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create budget line for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param array $data Budget line data
     * @return ProjectBudgetLine
     */
    public function createBudgetLineForProject(string $tenantId, Project $project, array $data): ProjectBudgetLine
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        $data['tenant_id'] = $tenantId;
        $data['project_id'] = $project->id;
        $data['created_by'] = Auth::id();

        $budgetLine = ProjectBudgetLine::create($data);

        $this->logCrudOperation('created', $budgetLine, $data);

        return $budgetLine->fresh();
    }

    /**
     * Update budget line for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param string $budgetLineId Budget line ID
     * @param array $data Update data
     * @return ProjectBudgetLine
     */
    public function updateBudgetLineForProject(string $tenantId, Project $project, string $budgetLineId, array $data): ProjectBudgetLine
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        $budgetLine = ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('id', $budgetLineId)
            ->firstOrFail();

        $data['updated_by'] = Auth::id();

        $budgetLine->update($data);

        $this->logCrudOperation('updated', $budgetLine, $data);

        return $budgetLine->fresh();
    }

    /**
     * Delete budget line for a project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @param string $budgetLineId Budget line ID
     * @return void
     */
    public function deleteBudgetLineForProject(string $tenantId, Project $project, string $budgetLineId): void
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        $budgetLine = ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->where('id', $budgetLineId)
            ->firstOrFail();

        $this->logCrudOperation('deleted', $budgetLine, ['id' => $budgetLineId]);

        $budgetLine->delete();
    }
}
