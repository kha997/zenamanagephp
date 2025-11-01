<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;
use App\Support\ApiResponse;

/**
 * Base API Request với standardized validation và error handling
 */
abstract class BaseApiRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Override in child classes if needed
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                'Validation failed',
                422,
                $validator->errors()->toArray(),
                'VALIDATION_ERROR'
            )
        );
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'required' => 'The :attribute field is required.',
            'string' => 'The :attribute field must be a string.',
            'email' => 'The :attribute field must be a valid email address.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'min' => 'The :attribute field must be at least :min characters.',
            'max' => 'The :attribute field may not be greater than :max characters.',
            'numeric' => 'The :attribute field must be a number.',
            'integer' => 'The :attribute field must be an integer.',
            'boolean' => 'The :attribute field must be true or false.',
            'date' => 'The :attribute field must be a valid date.',
            'date_format' => 'The :attribute field must match the format :format.',
            'in' => 'The selected :attribute is invalid.',
            'array' => 'The :attribute field must be an array.',
            'json' => 'The :attribute field must be valid JSON.',
            'confirmed' => 'The :attribute confirmation does not match.',
            'same' => 'The :attribute and :other must match.',
            'different' => 'The :attribute and :other must be different.',
            'before' => 'The :attribute field must be a date before :date.',
            'after' => 'The :attribute field must be a date after :date.',
            'alpha' => 'The :attribute field may only contain letters.',
            'alpha_num' => 'The :attribute field may only contain letters and numbers.',
            'regex' => 'The :attribute field format is invalid.',
            'size' => 'The :attribute field must be :size.',
            'between' => 'The :attribute field must be between :min and :max.',
            'digits' => 'The :attribute field must be :digits digits.',
            'digits_between' => 'The :attribute field must be between :min and :max digits.',
            'file' => 'The :attribute field must be a file.',
            'image' => 'The :attribute field must be an image.',
            'mimes' => 'The :attribute field must be a file of type: :values.',
            'mimetypes' => 'The :attribute field must be a file of type: :values.',
            'uploaded' => 'The :attribute failed to upload.',
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'email' => 'email',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'description' => 'description',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'status' => 'status',
            'priority' => 'priority',
            'progress' => 'progress',
            'budget_total' => 'total budget',
            'budget_actual' => 'actual budget',
            'project_id' => 'project ID',
            'user_id' => 'user ID',
            'tenant_id' => 'tenant ID',
            'role' => 'role',
            'is_active' => 'active status',
        ];
    }

    /**
     * Get validation rules for common fields
     */
    protected function getCommonRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:1000'],
            'status' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation rules for date fields
     */
    protected function getDateRules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
        ];
    }

    /**
     * Get validation rules for numeric fields
     */
    protected function getNumericRules(): array
    {
        return [
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}