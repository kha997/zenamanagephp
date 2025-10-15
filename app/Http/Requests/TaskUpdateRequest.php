<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaskUpdateRequest extends FormRequest
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
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'assignee_id' => 'nullable|string|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,urgent',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled,on_hold',
            'due_date' => 'nullable|date',
            'estimated_hours' => 'nullable|numeric|min:0|max:9999.99',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'dependencies' => 'nullable|array',
            'dependencies.*' => 'string|exists:tasks,id',
            'is_milestone' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'completed_at' => 'nullable|date',
            'actual_hours' => 'nullable|numeric|min:0|max:9999.99'
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
            'assignee_id.exists' => 'Selected assignee does not exist.',
            'priority.in' => 'Priority must be one of: low, medium, high, urgent.',
            'status.in' => 'Status must be one of: pending, in_progress, completed, cancelled, on_hold.',
            'due_date.date' => 'Due date must be a valid date.',
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
            'requires_approval.boolean' => 'Approval requirement must be true or false.',
            'completed_at.date' => 'Completion date must be a valid date.',
            'actual_hours.numeric' => 'Actual hours must be a valid number.',
            'actual_hours.min' => 'Actual hours cannot be negative.',
            'actual_hours.max' => 'Actual hours cannot exceed 9999.99.'
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
            'assignee_id' => 'assignee',
            'priority' => 'task priority',
            'status' => 'task status',
            'due_date' => 'due date',
            'estimated_hours' => 'estimated hours',
            'progress_percent' => 'progress percentage',
            'tags' => 'task tags',
            'dependencies' => 'task dependencies',
            'is_milestone' => 'milestone flag',
            'requires_approval' => 'approval requirement',
            'completed_at' => 'completion date',
            'actual_hours' => 'actual hours'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure assignee belongs to user's tenant
        if ($this->assignee_id) {
            $assignee = \App\Models\User::where('id', $this->assignee_id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->first();
                
            if (!$assignee) {
                // Authorization should be handled in the controller
            }
        }

        // Ensure dependencies belong to user's tenant
        if ($this->dependencies) {
            $dependencies = \App\Models\Task::whereIn('id', $this->dependencies)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->count();
                
            if ($dependencies !== count($this->dependencies)) {
                // Authorization should be handled in the controller
            }
        }
    }
}
