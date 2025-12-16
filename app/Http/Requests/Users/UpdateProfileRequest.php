<?php declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
            ],
            'first_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'last_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'department' => [
                'nullable',
                'string',
                'max:255',
            ],
            'job_title' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.max' => 'Name must not exceed 255 characters.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'first_name.max' => 'First name must not exceed 255 characters.',
            'last_name.max' => 'Last name must not exceed 255 characters.',
            'department.max' => 'Department must not exceed 255 characters.',
            'job_title.max' => 'Job title must not exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'name',
            'phone' => 'phone number',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'department' => 'department',
            'job_title' => 'job title',
        ];
    }
}

