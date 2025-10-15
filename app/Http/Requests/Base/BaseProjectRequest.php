<?php declare(strict_types=1);

namespace App\Http\Requests\Base;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Base Project Request với common validation rules
 */
abstract class BaseProjectRequest extends BaseApiRequest
{
    /**
     * Get validation rules for project fields
     */
    protected function getProjectRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:50'],
            'status' => ['required', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'category' => ['sometimes', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['sometimes', 'string', 'max:2000'],
        ];
    }

    /**
     * Get validation rules for project update
     */
    protected function getProjectUpdateRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'code' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after:start_date'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'category' => ['sometimes', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'notes' => ['sometimes', 'string', 'max:2000'],
        ];
    }

    /**
     * Get validation rules for project status update
     */
    protected function getStatusUpdateRules(): array
    {
        return [
            'status' => ['required', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'reason' => ['sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * Get validation rules for project progress update
     */
    protected function getProgressUpdateRules(): array
    {
        return [
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'notes' => ['sometimes', 'string', 'max:500'],
        ];
    }

    /**
     * Get validation rules for project assignment
     */
    protected function getAssignmentRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['sometimes', 'string', 'in:owner,manager,member,observer'],
        ];
    }

    /**
     * Get validation rules for bulk operations
     */
    protected function getBulkRules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:projects,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,delete,change_status,assign'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * Get validation rules for project search
     */
    protected function getSearchRules(): array
    {
        return [
            'search' => ['required', 'string', 'min:2', 'max:255'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'start_date_from' => ['sometimes', 'date'],
            'start_date_to' => ['sometimes', 'date'],
            'end_date_from' => ['sometimes', 'date'],
            'end_date_to' => ['sometimes', 'date'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get validation rules for project statistics
     */
    protected function getStatsRules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,week,month,quarter,year'],
            'group_by' => ['sometimes', 'string', 'in:status,priority,owner,created_at'],
        ];
    }

    /**
     * Get validation rules for project dashboard
     */
    protected function getDashboardRules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,week,month,quarter,year'],
            'include_charts' => ['sometimes', 'boolean'],
            'include_activities' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation rules for project filtering
     */
    protected function getFilterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id'],
            'client_id' => ['sometimes', 'integer', 'exists:clients,id'],
            'category' => ['sometimes', 'string', 'max:100'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'start_date_from' => ['sometimes', 'date'],
            'start_date_to' => ['sometimes', 'date'],
            'end_date_from' => ['sometimes', 'date'],
            'end_date_to' => ['sometimes', 'date'],
            'sort_by' => ['sometimes', 'string', 'in:name,status,priority,progress,start_date,end_date,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
