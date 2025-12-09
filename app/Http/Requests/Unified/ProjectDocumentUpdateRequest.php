<?php declare(strict_types=1);

namespace App\Http\Requests\Unified;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for updating project document metadata
 */
class ProjectDocumentUpdateRequest extends FormRequest
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
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'nullable|string|in:general,contract,drawing,specification,report,other',
            'status' => 'nullable|string|in:active,archived,draft',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.max' => 'The name cannot exceed 255 characters.',
            'description.max' => 'The description cannot exceed 2000 characters.',
            'category.in' => 'The category must be one of: general, contract, drawing, specification, report, other.',
            'status.in' => 'The status must be one of: active, archived, draft.',
        ];
    }
}

