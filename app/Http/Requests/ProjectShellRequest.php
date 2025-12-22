<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;

/**
 * Unified ProjectShellRequest
 * 
 * Consolidates validation rules from:
 * - ProjectUpdateRequest.php
 * - ProjectStoreRequest.php
 * - ProjectCreateRequest.php
 * - StoreProjectRequest.php
 * - ProjectBulkCreateRequest.php
 * - ProjectFormRequest.php
 * - IndexProjectRequest.php
 * - CreateBaselineFromProjectRequest.php
 * - UpdateProjectRequest.php
 */
class ProjectShellRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }

        $context = $this->determineContext();
        
        switch ($context) {
            case 'admin':
                return $user->hasRole('super_admin');
            case 'app':
                return $user->hasRole(['admin', 'project_manager', 'super_admin']);
            default:
                return true;
        }
    }

    /**
     * Get the validation rules that apply to the request
     */
    public function rules(): array
    {
        $method = $this->method();
        $context = $this->determineContext();
        
        switch ($method) {
            case 'POST':
                return $this->getCreateRules($context);
            case 'PUT':
            case 'PATCH':
                return $this->getUpdateRules($context);
            case 'GET':
                return $this->getIndexRules($context);
            default:
                return [];
        }
    }

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required',
            'name.max' => 'Project name cannot exceed 255 characters',
            'name.unique' => 'Project name already exists',
            'description.required' => 'Project description is required',
            'description.max' => 'Project description cannot exceed 1000 characters',
            'code.required' => 'Project code is required',
            'code.unique' => 'Project code already exists',
            'code.regex' => 'Project code must contain only letters, numbers, and hyphens',
            'status.required' => 'Project status is required',
            'status.in' => 'Invalid project status',
            'priority.required' => 'Project priority is required',
            'priority.in' => 'Invalid project priority',
            'start_date.required' => 'Start date is required',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.required' => 'End date is required',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after' => 'End date must be after start date',
            'budget_total.required' => 'Total budget is required',
            'budget_total.numeric' => 'Total budget must be a number',
            'budget_total.min' => 'Total budget must be at least 0',
            'owner_id.required' => 'Project owner is required',
            'owner_id.exists' => 'Selected owner does not exist',
            'tenant_id.required' => 'Tenant is required',
            'tenant_id.exists' => 'Selected tenant does not exist',
            'template_id.exists' => 'Selected template does not exist',
            'team_members.array' => 'Team members must be an array',
            'team_members.*.exists' => 'One or more team members do not exist',
            'tags.array' => 'Tags must be an array',
            'tags.*.string' => 'Each tag must be a string',
            'tags.*.max' => 'Each tag cannot exceed 50 characters',
            'custom_fields.array' => 'Custom fields must be an array',
            'custom_fields.*.key.required' => 'Custom field key is required',
            'custom_fields.*.value.required' => 'Custom field value is required',
            'search.string' => 'Search term must be a string',
            'search.max' => 'Search term cannot exceed 255 characters',
            'status_filter.in' => 'Invalid status filter',
            'priority_filter.in' => 'Invalid priority filter',
            'owner_filter.exists' => 'Selected owner filter does not exist',
            'sort_by.in' => 'Invalid sort field',
            'sort_direction.in' => 'Sort direction must be asc or desc',
            'per_page.integer' => 'Per page must be a number',
            'per_page.min' => 'Per page must be at least 1',
            'per_page.max' => 'Per page cannot exceed 100',
            'bulk_action.required' => 'Bulk action is required',
            'bulk_action.in' => 'Invalid bulk action',
            'project_ids.required' => 'Project IDs are required',
            'project_ids.array' => 'Project IDs must be an array',
            'project_ids.min' => 'At least one project must be selected',
            'project_ids.*.exists' => 'One or more projects do not exist',
            'new_status.in' => 'Invalid new status',
            'new_owner.exists' => 'Selected new owner does not exist',
            'new_priority.in' => 'Invalid new priority',
            'template_id.required' => 'Template is required',
            'template_id.exists' => 'Selected template does not exist',
            'baseline_name.required' => 'Baseline name is required',
            'baseline_name.max' => 'Baseline name cannot exceed 255 characters',
            'baseline_description.max' => 'Baseline description cannot exceed 1000 characters',
        ];
    }

    /**
     * Get validation rules for creating projects
     */
    private function getCreateRules(string $context): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'status' => ['required', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['required', 'string', 'in:low,medium,high,critical'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'budget_total' => ['required', 'numeric', 'min:0'],
            'owner_id' => ['required', 'exists:users,id'],
            'team_members' => ['sometimes', 'array'],
            'team_members.*' => ['exists:users,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'custom_fields' => ['sometimes', 'array'],
            'custom_fields.*.key' => ['required', 'string', 'max:100'],
            'custom_fields.*.value' => ['required', 'string', 'max:500'],
        ];

        // Context-specific rules
        if ($context === 'admin') {
            $rules['tenant_id'] = ['required', 'exists:tenants,id'];
        }

        if ($context === 'app') {
            // For app context, tenant_id is set automatically
            unset($rules['tenant_id']);
        }

        // Template-based creation
        if ($this->has('template_id')) {
            $rules['template_id'] = ['required', 'exists:project_templates,id'];
            // Some fields might be optional when using template
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['description'] = ['sometimes', 'string', 'max:1000'];
        }

        return $rules;
    }

    /**
     * Get validation rules for updating projects
     */
    private function getUpdateRules(string $context): array
    {
        $projectId = $this->route('project');
        
        $rules = [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'status' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
            'progress' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'owner_id' => ['sometimes', 'exists:users,id'],
            'team_members' => ['sometimes', 'array'],
            'team_members.*' => ['exists:users,id'],
            'tags' => ['sometimes', 'array'],
            'tags.*' => ['string', 'max:50'],
            'custom_fields' => ['sometimes', 'array'],
            'custom_fields.*.key' => ['required', 'string', 'max:100'],
            'custom_fields.*.value' => ['required', 'string', 'max:500'],
        ];

        // Add unique validation for code if it's being updated
        if ($this->has('code')) {
            $rules['code'][] = "unique:projects,code,{$projectId}";
        }

        // Add unique validation for name if it's being updated
        if ($this->has('name')) {
            $rules['name'][] = "unique:projects,name,{$projectId}";
        }

        // Validate end_date is after start_date if both are provided
        if ($this->has('start_date') && $this->has('end_date')) {
            $rules['end_date'][] = 'after:start_date';
        }

        // Context-specific rules
        if ($context === 'admin') {
            $rules['tenant_id'] = ['sometimes', 'exists:tenants,id'];
        }

        return $rules;
    }

    /**
     * Get validation rules for project index/listing
     */
    private function getIndexRules(string $context): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status_filter' => ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority_filter' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'owner_filter' => ['sometimes', 'exists:users,id'],
            'tenant_filter' => ['sometimes', 'exists:tenants,id'],
            'sort_by' => ['sometimes', 'string', 'in:name,status,priority,start_date,end_date,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    /**
     * Get validation rules for bulk actions
     */
    public function getBulkActionRules(): array
    {
        return [
            'bulk_action' => ['required', 'string', 'in:activate,deactivate,delete,archive,change_status,assign_owner,change_priority'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['exists:projects,id'],
            'new_status' => ['required_if:bulk_action,change_status', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'new_owner' => ['required_if:bulk_action,assign_owner', 'exists:users,id'],
            'new_priority' => ['required_if:bulk_action,change_priority', 'string', 'in:low,medium,high,critical'],
        ];
    }

    /**
     * Get validation rules for template-based creation
     */
    public function getTemplateCreationRules(): array
    {
        return [
            'template_id' => ['required', 'exists:project_templates,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'code' => ['sometimes', 'string', 'max:50', 'regex:/^[A-Z0-9-]+$/'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'owner_id' => ['sometimes', 'exists:users,id'],
            'team_members' => ['sometimes', 'array'],
            'team_members.*' => ['exists:users,id'],
            'custom_fields' => ['sometimes', 'array'],
            'custom_fields.*.key' => ['required', 'string', 'max:100'],
            'custom_fields.*.value' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * Get validation rules for baseline creation
     */
    public function getBaselineCreationRules(): array
    {
        return [
            'baseline_name' => ['required', 'string', 'max:255'],
            'baseline_description' => ['sometimes', 'string', 'max:1000'],
            'include_tasks' => ['sometimes', 'boolean'],
            'include_milestones' => ['sometimes', 'boolean'],
            'include_budget' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Determine the context of the request
     */
    private function determineContext(): string
    {
        $route = $this->route();
        $routeName = $route ? $route->getName() : '';
        
        if (str_contains($routeName, 'admin')) {
            return 'admin';
        } elseif (str_contains($routeName, 'app')) {
            return 'app';
        } elseif (str_contains($routeName, 'api')) {
            return 'api';
        }
        
        return 'web';
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate code if not provided
        if (!$this->has('code') && $this->has('name')) {
            $this->merge([
                'code' => strtoupper(str_replace(' ', '-', $this->input('name')))
            ]);
        }

        // Set default values
        if (!$this->has('status')) {
            $this->merge(['status' => 'planning']);
        }

        if (!$this->has('priority')) {
            $this->merge(['priority' => 'medium']);
        }

        // Set tenant_id for app context
        if ($this->determineContext() === 'app' && auth()->user()) {
            $this->merge(['tenant_id' => auth()->user()->tenant_id]);
        }

        // Set owner_id if not provided
        if (!$this->has('owner_id') && auth()->user()) {
            $this->merge(['owner_id' => auth()->user()->id]);
        }
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'name' => 'project name',
            'description' => 'project description',
            'code' => 'project code',
            'status' => 'project status',
            'priority' => 'project priority',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'budget_total' => 'total budget',
            'budget_actual' => 'actual budget',
            'progress' => 'project progress',
            'owner_id' => 'project owner',
            'tenant_id' => 'tenant',
            'template_id' => 'project template',
            'team_members' => 'team members',
            'tags' => 'project tags',
            'custom_fields' => 'custom fields',
            'search' => 'search term',
            'status_filter' => 'status filter',
            'priority_filter' => 'priority filter',
            'owner_filter' => 'owner filter',
            'tenant_filter' => 'tenant filter',
            'sort_by' => 'sort field',
            'sort_direction' => 'sort direction',
            'per_page' => 'items per page',
            'bulk_action' => 'bulk action',
            'project_ids' => 'project IDs',
            'new_status' => 'new status',
            'new_owner' => 'new owner',
            'new_priority' => 'new priority',
            'baseline_name' => 'baseline name',
            'baseline_description' => 'baseline description',
        ];
    }
}
