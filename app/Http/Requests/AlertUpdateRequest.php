<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => [
                'sometimes',
                'string',
                'min:3',
                'max:255'
            ],
            'description' => [
                'sometimes',
                'string',
                'min:10',
                'max:1000'
            ],
            'type' => [
                'sometimes',
                'string',
                'in:system,security,performance,maintenance,user'
            ],
            'severity' => [
                'sometimes',
                'string',
                'in:info,warning,critical'
            ],
            'status' => [
                'sometimes',
                'string',
                'in:active,resolved'
            ],
            'reason' => [
                'sometimes',
                'string',
                'max:500'
            ]
        ];
    }

    /**
     * Get custom error messages
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'title.min' => 'Alert title must be at least 3 characters.',
            'title.max' => 'Alert title cannot exceed 255 characters.',
            'description.min' => 'Alert description must be at least 10 characters.',
            'description.max' => 'Alert description cannot exceed 1000 characters.',
            'type.in' => 'Alert type must be one of: system, security, performance, maintenance, user.',
            'severity.in' => 'Alert severity must be one of: info, warning, critical.',
            'status.in' => 'Alert status must be one of: active, resolved.',
            'reason.max' => 'Reason cannot exceed 500 characters.',
        ];
    }
}
