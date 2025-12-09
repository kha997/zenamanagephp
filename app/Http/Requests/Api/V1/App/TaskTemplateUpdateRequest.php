<?php declare(strict_types=1);

namespace App\Http\Requests\Api\V1\App;

use Illuminate\Foundation\Http\FormRequest;

/**
 * TaskTemplateUpdateRequest
 * 
 * Validation for updating a task template
 */
class TaskTemplateUpdateRequest extends FormRequest
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
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'phase_code' => ['nullable', 'string', 'max:50'],
            'phase_label' => ['nullable', 'string', 'max:100'],
            'group_label' => ['nullable', 'string', 'max:100'],
            'estimated_hours' => ['nullable', 'numeric', 'min:0'],
            'is_required' => ['nullable', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Task template name must not exceed 255 characters.',
            'order_index.integer' => 'Order index must be an integer.',
            'order_index.min' => 'Order index must be greater than or equal to 0.',
            'estimated_hours.numeric' => 'Estimated hours must be a number.',
            'estimated_hours.min' => 'Estimated hours must be greater than or equal to 0.',
            'is_required.boolean' => 'Is required must be a boolean value.',
            'metadata.array' => 'Metadata must be an array.',
        ];
    }
}
