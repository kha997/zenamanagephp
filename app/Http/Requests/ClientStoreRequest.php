<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->tenant_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('clients', 'email')->where(function ($query) {
                    return $query->where('tenant_id', auth()->user()->tenant_id);
                })
            ],
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'lifecycle_stage' => 'nullable|in:lead,prospect,customer,inactive',
            'notes' => 'nullable|string|max:2000',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_vip' => 'nullable|boolean',
            'credit_limit' => 'nullable|numeric|min:0|max:999999999.99',
            'payment_terms' => 'nullable|string|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Client name is required.',
            'name.max' => 'Client name cannot exceed 255 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'email.unique' => 'This email address is already registered.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'company.max' => 'Company name cannot exceed 255 characters.',
            'address.max' => 'Address cannot exceed 1000 characters.',
            'city.max' => 'City name cannot exceed 100 characters.',
            'state.max' => 'State name cannot exceed 100 characters.',
            'country.max' => 'Country name cannot exceed 100 characters.',
            'postal_code.max' => 'Postal code cannot exceed 20 characters.',
            'lifecycle_stage.in' => 'Lifecycle stage must be one of: lead, prospect, customer, inactive.',
            'notes.max' => 'Notes cannot exceed 2000 characters.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.',
            'is_vip.boolean' => 'VIP status must be true or false.',
            'credit_limit.numeric' => 'Credit limit must be a valid number.',
            'credit_limit.min' => 'Credit limit cannot be negative.',
            'credit_limit.max' => 'Credit limit cannot exceed 999,999,999.99.',
            'payment_terms.max' => 'Payment terms cannot exceed 100 characters.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'client name',
            'email' => 'email address',
            'phone' => 'phone number',
            'company' => 'company name',
            'address' => 'address',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
            'postal_code' => 'postal code',
            'lifecycle_stage' => 'lifecycle stage',
            'notes' => 'notes',
            'tags' => 'client tags',
            'is_vip' => 'VIP status',
            'credit_limit' => 'credit limit',
            'payment_terms' => 'payment terms'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure user has permission to create clients
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['super_admin', 'admin', 'project_manager'])) {
            // Authorization should be handled in the controller
        }
    }
}
