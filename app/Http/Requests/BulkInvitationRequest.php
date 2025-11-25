<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class BulkInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization will be handled by InvitationPolicy
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        $isSuperAdmin = $user && $user->hasPermission('admin.access');

        return [
            'emails' => ['required', 'array', 'min:1', 'max:50'],
            'emails.*' => ['nullable', 'string', 'max:255'], // Allow invalid emails, will be filtered in service
            'role' => ['required', 'string', Rule::in(['super_admin', 'admin', 'project_manager', 'member', 'client'])],
            'tenant_id' => [
                $isSuperAdmin ? 'required' : 'nullable',
                'string',
                'exists:tenants,id',
            ],
            'project_id' => ['nullable', 'string', 'exists:projects,id'],
            'message' => ['nullable', 'string', 'max:1000'],
            'note' => ['nullable', 'string', 'max:500'],
            'send_email' => ['boolean'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'emails.required' => 'At least one email is required.',
            'emails.array' => 'Emails must be an array.',
            'emails.min' => 'At least one email is required.',
            'emails.max' => 'Maximum 50 emails allowed per request.',
            'emails.*.email' => 'Each email must be a valid email address.',
            'role.required' => 'Role is required.',
            'role.in' => 'Invalid role selected.',
            'tenant_id.required' => 'Tenant is required.',
            'tenant_id.exists' => 'Selected tenant does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $user = Auth::user();
        
        // Auto-set tenant_id for non-super-admin users
        if (!$user->hasPermission('admin.access') && empty($this->tenant_id)) {
            $this->merge([
                'tenant_id' => $user->tenant_id,
            ]);
        }
    }
}
