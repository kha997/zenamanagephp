<?php declare(strict_types=1);

namespace App\Http\Requests\Unified;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

/**
 * Request validation for storing project documents
 */
class ProjectDocumentStoreRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:102400', // 100MB max
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar'])
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
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
            'file.required' => 'A file is required.',
            'file.file' => 'The uploaded file is not valid.',
            'file.max' => 'The file size cannot exceed 100MB.',
            'name.max' => 'The name cannot exceed 255 characters.',
            'description.max' => 'The description cannot exceed 1000 characters.',
            'category.in' => 'The category must be one of: general, contract, drawing, specification, report, other.',
            'status.in' => 'The status must be one of: active, archived, draft.',
        ];
    }
}

