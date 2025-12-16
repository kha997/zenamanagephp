<?php declare(strict_types=1);

namespace App\Http\Requests\Api\V1\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TemplateUpdateRequest
 * 
 * Validation for updating an existing template
 */
class TemplateUpdateRequest extends FormRequest
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
            'type' => ['sometimes', 'string', Rule::in(['project', 'task', 'document', 'checklist'])],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Template name must not exceed 255 characters.',
            'type.in' => 'Template type must be one of: project, task, document, checklist.',
            'metadata.array' => 'Metadata must be an array.',
        ];
    }
}

