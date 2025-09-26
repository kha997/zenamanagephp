<?php

namespace App\Http\Requests;
use Illuminate\Support\Facades\Auth;


use Illuminate\Foundation\Http\FormRequest;

/**
 * Task Bulk Create Request
 * 
 * Validates bulk task creation with consistent rules
 */
class TaskBulkCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->hasPermission('tasks.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ValidationRules::taskBulkCreate();
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return ValidationRules::messages();
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'tasks.*.title' => 'task title',
            'tasks.*.description' => 'task description',
            'tasks.*.status' => 'task status',
            'tasks.*.priority' => 'task priority',
            'tasks.*.due_date' => 'task due date',
            'tasks.*.assignee_id' => 'task assignee',
            'tasks.*.estimated_hours' => 'task estimated hours',
            'tasks.*.actual_hours' => 'task actual hours',
            'tasks.*.dependencies' => 'task dependencies',
            'tasks.*.tags' => 'task tags',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set tenant_id from authenticated user if not provided
        if (!$this->has('tenant_id') && Auth::check()) {
            $this->merge(['tenant_id' => Auth::user()->tenant_id]);
        }
    }
}