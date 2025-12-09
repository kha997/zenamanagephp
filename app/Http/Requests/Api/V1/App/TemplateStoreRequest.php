<?php declare(strict_types=1);

namespace App\Http\Requests\Api\V1\App;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * TemplateStoreRequest
 * 
 * Validation for creating a new template
 */
class TemplateStoreRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(['project', 'task', 'document', 'checklist'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'name.max' => 'Template name must not exceed 255 characters.',
            'type.required' => 'Template type is required.',
            'type.in' => 'Template type must be one of: project, task, document, checklist.',
            'metadata.array' => 'Metadata must be an array.',
        ];
    }
}

