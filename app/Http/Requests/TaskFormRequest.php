<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request cho Task validation
 * 
 * @package App\Http\Requests
 */
class TaskFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'status' => ['required', Rule::in(['pending', 'in_progress', 'completed', 'cancelled'])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'assignee_id' => ['nullable', 'string'],
            'watchers' => ['sometimes', 'array'],
            'watchers.*' => ['integer', 'exists:users,id'],
            'notifications' => ['sometimes', 'boolean'],
            'time_tracking' => ['sometimes', 'boolean'],
            'subtasks' => ['sometimes', 'boolean'],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'project_id.required' => 'Project is required.',
            'project_id.exists' => 'Project does not exist.',
            'name.required' => 'Task name is required.',
            'name.max' => 'Task name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'priority.required' => 'Priority is required.',
            'priority.in' => 'Invalid priority level.',
            'status.required' => 'Status is required.',
            'status.in' => 'Invalid status.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.required' => 'End date is required.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'estimated_hours.numeric' => 'Estimated hours must be a number.',
            'estimated_hours.min' => 'Estimated hours cannot be negative.',
            'assignee_id.exists' => 'Assignee does not exist.',
            'watchers.array' => 'Watchers must be an array.',
            'watchers.*.integer' => 'Watcher ID must be an integer.',
            'watchers.*.exists' => 'Watcher does not exist.',
            'tags.max' => 'Tags cannot exceed 500 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Kiểm tra component thuộc cùng project
            if ($this->component_id && $this->project_id) {
                $component = \App\Models\Component::find($this->component_id);
                if ($component && $component->project_id !== (int)$this->project_id) {
                    $validator->errors()->add('component_id', 'Component phải thuộc cùng dự án.');
                }
            }

            // Kiểm tra circular dependencies
            if ($this->dependencies && is_array($this->dependencies)) {
                $taskId = $this->route('task');
                if ($taskId && in_array($taskId, $this->dependencies)) {
                    $validator->errors()->add('dependencies', 'Task không thể phụ thuộc vào chính nó.');
                }
            }

            // Validate assignee_id if not empty
            if (!empty($this->assignee_id) && !\App\Models\User::find($this->assignee_id)) {
                $validator->errors()->add('assignee_id', 'Selected assignee does not exist.');
            }
        });
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert dependencies JSON string to array if needed
        if ($this->has('dependencies') && is_string($this->dependencies)) {
            $this->merge([
                'dependencies' => json_decode($this->dependencies, true) ?: [],
            ]);
        }
    }
}