<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Move Task Request
 * 
 * Validates request to move a task between status columns (Kanban)
 */
class MoveTaskRequest extends FormRequest
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
            'to_status' => [
                'required',
                'string',
                Rule::in(TaskStatus::values())
            ],
            'before_id' => [
                'nullable',
                'string',
                'exists:tasks,id'
            ],
            'after_id' => [
                'nullable',
                'string',
                'exists:tasks,id'
            ],
            'reason' => [
                'nullable',
                'string',
                'max:500'
            ],
            'version' => [
                'nullable',
                'integer',
                'min:1'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'to_status.required' => 'Target status is required.',
            'to_status.in' => 'Invalid target status. Allowed values: ' . implode(', ', TaskStatus::values()),
            'before_id.exists' => 'The specified task (before_id) does not exist.',
            'after_id.exists' => 'The specified task (after_id) does not exist.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
            'version.integer' => 'Version must be an integer.',
            'version.min' => 'Version must be at least 1.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim reason if provided
        if ($this->has('reason')) {
            $this->merge([
                'reason' => trim($this->input('reason', ''))
            ]);
        }
    }
}

