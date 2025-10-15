<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectStoreRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'budget_total' => 'nullable|numeric|min:0|max:999999999.99',
            'start_date' => 'nullable|date|after_or_equal:today',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
            'priority' => 'nullable|in:low,medium,high',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Project name is required.',
            'name.max' => 'Project name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 2000 characters.',
            'budget_total.numeric' => 'Budget must be a valid number.',
            'budget_total.min' => 'Budget cannot be negative.',
            'budget_total.max' => 'Budget cannot exceed 999,999,999.99.',
            'start_date.date' => 'Start date must be a valid date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'status.in' => 'Status must be one of: planning, active, on_hold, completed, cancelled.',
            'priority.in' => 'Priority must be one of: low, medium, high.',
            'category.max' => 'Category cannot exceed 100 characters.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'is_public.boolean' => 'Public visibility must be true or false.',
            'requires_approval.boolean' => 'Approval requirement must be true or false.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'project name',
            'description' => 'project description',
            'budget_total' => 'total budget',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'status' => 'project status',
            'priority' => 'project priority',
            'category' => 'project category',
            'tags' => 'project tags',
            'is_public' => 'public visibility',
            'requires_approval' => 'approval requirement'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Authorization should be handled in the controller, not in FormRequest
        // This method should only prepare data for validation
    }
}
