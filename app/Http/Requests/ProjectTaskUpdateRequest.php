<?php declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ProjectTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * ProjectTaskUpdateRequest
 * 
 * Round 206: Form request for updating project tasks
 * Round 213: Added assignee_id for task assignment
 * 
 * Allows updating: name, description, status, due_date, sort_order, is_milestone, assignee_id
 * Note: is_completed is NOT allowed here - use /complete or /incomplete endpoints
 */
class ProjectTaskUpdateRequest extends FormRequest
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
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:65535'],
            'status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(ProjectTask::getAvailableStatuses())
            ],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_milestone' => ['sometimes', 'boolean'],
            'phase_code' => ['sometimes', 'nullable', 'string', 'max:50'],
            'phase_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'group_label' => ['sometimes', 'nullable', 'string', 'max:100'],
            'assignee_id' => [
                'sometimes',
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value) {
                        $user = \App\Models\User::where('id', $value)
                            ->where('tenant_id', $tenantId)
                            ->first();
                        
                        if (!$user) {
                            $fail('The selected assignee does not exist or does not belong to your tenant.');
                        }
                    }
                }
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [
            'status.in' => 'The status must be one of: ' . implode(', ', ProjectTask::getAvailableStatuses()),
        ];
    }
}
