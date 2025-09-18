<?php declare(strict_types=1);

namespace Src\CoreProject\Requests;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Form Request để validate parameters cho listing projects
 */
class IndexProjectRequest extends BaseApiRequest
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
            'status' => [
                'nullable',
                'string',
                Rule::in(['planning', 'active', 'on_hold', 'completed', 'cancelled'])
            ],
            'visibility' => [
                'nullable',
                'string', 
                Rule::in(['internal', 'client'])
            ],
            'start_date_from' => [
                'nullable',
                'date'
            ],
            'start_date_to' => [
                'nullable',
                'date',
                'after_or_equal:start_date_from'
            ],
            'progress_min' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ],
            'progress_max' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'gte:progress_min'
            ],
            'search' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[\p{L}\p{N}\s\-_\.]+$/u' // Chỉ cho phép letters, numbers, spaces, hyphens, underscores, dots
            ],
            'sort_by' => [
                'nullable',
                'string',
                Rule::in(['name', 'created_at', 'start_date', 'end_date', 'progress', 'status'])
            ],
            'sort_direction' => [
                'nullable',
                'string',
                Rule::in(['asc', 'desc'])
            ],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100'
            ]
        ];
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'search.regex' => 'Search term contains invalid characters.',
            'progress_max.gte' => 'Maximum progress must be greater than or equal to minimum progress.',
            'start_date_to.after_or_equal' => 'End date must be after or equal to start date.'
        ];
    }

    /**
     * Sanitize search input để prevent XSS
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => strip_tags($this->input('search'))
            ]);
        }
    }
}