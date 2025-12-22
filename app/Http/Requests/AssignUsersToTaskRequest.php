<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request validation for assigning users to a task
 */
class AssignUsersToTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware and policies
    }

    /**
     * Normalize incoming payload by converting plain ids into objects so validation rules remain consistent.
     */
    protected function prepareForValidation(): void
    {
        $users = $this->input('users');

        if (!is_array($users)) {
            return;
        }

        $normalized = array_map(function ($entry) {
            if (is_string($entry)) {
                return ['user_id' => $entry];
            }

            return $entry;
        }, $users);

        $this->merge(['users' => $normalized]);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        return [
            'users' => 'required|array|min:1|max:100',
            'users.*.user_id' => [
                'required',
                'string',
                'ulid',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $user = \App\Models\User::where('id', $value)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    
                    if (!$user) {
                        $fail('The selected user does not exist or does not belong to your tenant.');
                    }
                }
            ],
            'users.*.role' => [
                'nullable',
                'string',
                'in:assignee,reviewer,watcher'
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'users.required' => 'The users field is required.',
            'users.array' => 'The users must be an array.',
            'users.min' => 'At least one user must be provided.',
            'users.max' => 'Maximum 100 users can be assigned at once.',
            'users.*.user_id.required' => 'Each user must have a user_id.',
            'users.*.user_id.string' => 'The user_id must be a string.',
            'users.*.user_id.ulid' => 'The user_id must be a valid ULID.',
            'users.*.role.string' => 'The role must be a string.',
            'users.*.role.in' => 'The role must be one of: assignee, reviewer, watcher.',
        ];
    }
}
