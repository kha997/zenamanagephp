<?php declare(strict_types=1);

namespace App\Http\Requests\Unified;

use App\Http\Requests\Base\BaseProjectRequest;
use Illuminate\Validation\Rule;

/**
 * Unified Project Management Request
 * Replaces multiple project request classes
 */
class ProjectManagementRequest extends BaseProjectRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        
        return match($action) {
            'getProjects' => $this->getFilterRules(),
            'getProject' => $this->getIdRules(),
            'createProject' => $this->getCreateRules(),
            'updateProject' => $this->getUpdateRules(),
            'deleteProject' => $this->getIdRules(),
            'bulkDeleteProjects' => $this->getBulkRules(),
            'updateProjectStatus' => $this->getStatusUpdateRules(),
            'updateProjectProgress' => $this->getProgressUpdateRules(),
            'assignProject' => $this->getAssignmentRules(),
            'getProjectStats' => $this->getStatsRules(),
            'searchProjects' => $this->getSearchRules(),
            'getRecentProjects' => $this->getRecentRules(),
            'getProjectDashboardData' => $this->getDashboardRules(),
            default => []
        };
    }

    /**
     * Get validation rules for project creation
     */
    protected function getCreateRules(): array
    {
        return array_merge(
            $this->getProjectRules(),
            [
                'code' => ['required', 'string', 'max:50', 'unique:projects,code'],
            ]
        );
    }

    /**
     * Get validation rules for project update
     */
    protected function getUpdateRules(): array
    {
        $projectId = $this->route('id');
        
        return array_merge(
            $this->getProjectUpdateRules(),
            [
                'code' => ['sometimes', 'string', 'max:50', Rule::unique('projects', 'code')->ignore($projectId)],
            ]
        );
    }

    /**
     * Get validation rules for recent projects
     */
    protected function getRecentRules(): array
    {
        return [
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    /**
     * Get validation rules for ID parameter
     */
    protected function getIdRules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:projects,id'],
        ];
    }
}
