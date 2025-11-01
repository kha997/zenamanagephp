<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'project_id' => 'required|string|exists:projects,id',
            'assignee_id' => 'nullable|string|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled,on_hold',
            'due_date' => 'nullable|date|after_or_equal:today',
            'estimated_hours' => 'nullable|numeric|min:0|max:9999.99',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'string|exists:tasks,id',
            'is_milestone' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Task title is required.',
            'title.max' => 'Task title cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'project_id.required' => 'Project is required.',
            'project_id.exists' => 'Selected project does not exist.',
            'assignee_id.exists' => 'Selected assignee does not exist.',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'status.in' => 'Status must be one of: pending, in_progress, completed, cancelled, on_hold.',
            'due_date.date' => 'Due date must be a valid date.',
            'due_date.after_or_equal' => 'Due date cannot be in the past.',
            'estimated_hours.numeric' => 'Estimated hours must be a valid number.',
            'estimated_hours.min' => 'Estimated hours cannot be negative.',
            'estimated_hours.max' => 'Estimated hours cannot exceed 9999.99.',
            'progress_percent.numeric' => 'Progress must be a valid number.',
            'progress_percent.min' => 'Progress cannot be negative.',
            'progress_percent.max' => 'Progress cannot exceed 100%.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'dependencies.array' => 'Dependencies must be an array.',
            'dependencies.*.exists' => 'One or more dependency tasks do not exist.',
            'is_milestone.boolean' => 'Milestone flag must be true or false.',
            'requires_approval.boolean' => 'Approval requirement must be true or false.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'title' => 'task title',
            'description' => 'task description',
            'project_id' => 'project',
            'assignee_id' => 'assignee',
            'priority' => 'task priority',
            'status' => 'task status',
            'due_date' => 'due date',
            'estimated_hours' => 'estimated hours',
            'progress_percent' => 'progress percentage',
            'tags' => 'task tags',
            'dependencies' => 'task dependencies',
            'is_milestone' => 'milestone flag',
            'requires_approval' => 'approval requirement'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Authorization and tenant checks should be handled in the controller
        // This method should only prepare data for validation
    }
}
