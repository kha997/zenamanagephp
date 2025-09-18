<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Project Bulk Create Request
 * 
 * Validates bulk project creation with consistent rules
 */
class ProjectBulkCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('projects.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ValidationRules::projectBulkCreate();
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
            'projects.*.name' => 'project name',
            'projects.*.description' => 'project description',
            'projects.*.status' => 'project status',
            'projects.*.start_date' => 'project start date',
            'projects.*.end_date' => 'project end date',
            'projects.*.budget' => 'project budget',
            'projects.*.priority' => 'project priority',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set tenant_id from authenticated user if not provided
        if (!$this->has('tenant_id') && auth()->check()) {
            $this->merge(['tenant_id' => auth()->user()->tenant_id]);
        }
    }
}
