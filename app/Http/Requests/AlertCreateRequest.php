<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertCreateRequest extends FormRequest
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
                'required',
                'string',
                'min:3',
                'max:255'
            ],
            'description' => [
                'required',
                'string',
                'min:10',
                'max:1000'
            ],
            'type' => [
                'required',
                'string',
                'in:system,security,performance,maintenance,user'
            ],
            'severity' => [
                'required',
                'string',
                'in:info,warning,critical'
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
            'title.required' => 'Alert title is required.',
            'title.min' => 'Alert title must be at least 3 characters.',
            'title.max' => 'Alert title cannot exceed 255 characters.',
            'description.required' => 'Alert description is required.',
            'description.min' => 'Alert description must be at least 10 characters.',
            'description.max' => 'Alert description cannot exceed 1000 characters.',
            'type.required' => 'Alert type is required.',
            'type.in' => 'Alert type must be one of: system, security, performance, maintenance, user.',
            'severity.required' => 'Alert severity is required.',
            'severity.in' => 'Alert severity must be one of: info, warning, critical.',
        ];
    }
}
