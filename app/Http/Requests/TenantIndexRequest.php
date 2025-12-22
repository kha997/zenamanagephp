<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'status' => 'nullable|string', // Will be validated in controller
            'plan' => 'nullable|string', // Will be validated in controller
            'range' => 'nullable|in:7d,30d,90d,all,this_month,last_month',
            'region' => 'nullable|string', // Will be validated in controller
            'from' => 'nullable|date|before_or_equal:to',
            'to' => 'nullable|date|after_or_equal:from',
            'sort' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'status.in' => 'Status must be one of: trial, active, suspended, archived',
            'plan.in' => 'Plan must be one of: free, pro, enterprise',
            'range.in' => 'Range must be one of: 7d, 30d, 90d',
            'from.before_or_equal' => 'From date must be before or equal to To date',
            'to.after_or_equal' => 'To date must be after or equal to From date',
            'per_page.max' => 'Per page cannot exceed 100 items'
        ];
    }
}
