<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để xác thực dữ liệu khi cập nhật Document
 * 
 * @package Src\DocumentManagement\Requests
 */
class UpdateDocumentRequest extends FormRequest
{
    /**
     * Xác định user có quyền thực hiện request này không
     */
    public function authorize(): bool
    {
        return true; // Authorization được xử lý bởi RBAC middleware
    }

    /**
     * Các quy tắc validation cho request
     */
    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'required',
                'string',
                'max:255'
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000'
            ],
            'linked_entity_type' => [
                'nullable',
                Rule::in(['task', 'diary', 'cr'])
            ],
            'linked_entity_id' => [
                'nullable',
                'integer',
                'required_with:linked_entity_type'
            ],
            'tags' => [
                'nullable',
                'array'
            ],
            'tags.*' => [
                'string',
                'max:50'
            ],
            'visibility' => [
                'sometimes',
                'required',
                Rule::in(['internal', 'client'])
            ]
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Document title is required',
            'title.max' => 'Document title cannot exceed 255 characters',
            'linked_entity_id.required_with' => 'Entity ID is required when entity type is specified',
            'visibility.in' => 'Visibility must be either internal or client'
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Convert tags string to array if needed
        if ($this->has('tags') && is_string($this->tags)) {
            $this->merge([
                'tags' => array_filter(array_map('trim', explode(',', $this->tags)))
            ]);
        }
    }
}