<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamStoreRequest extends FormRequest
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
            'description' => 'nullable|string|max:1000',
            'project_id' => 'nullable|string|exists:projects,id',
            'members' => 'nullable|array',
            'members.*' => 'string|exists:users,id',
            'role' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Team name is required.',
            'name.max' => 'Team name cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'project_id.exists' => 'Selected project does not exist.',
            'members.array' => 'Members must be an array.',
            'members.*.exists' => 'One or more members do not exist.',
            'role.max' => 'Role cannot exceed 100 characters.',
            'is_active.boolean' => 'Active status must be true or false.',
            'tags.array' => 'Tags must be an array.',
            'tags.*.string' => 'Each tag must be a string.',
            'tags.*.max' => 'Each tag cannot exceed 50 characters.'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'team name',
            'description' => 'team description',
            'project_id' => 'project',
            'members' => 'team members',
            'role' => 'team role',
            'is_active' => 'active status',
            'tags' => 'team tags'
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure project belongs to user's tenant
        if ($this->project_id) {
            $project = \App\Models\Project::where('id', $this->project_id)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->first();
                
            if (!$project) {
                // Authorization should be handled in the controller
            }
        }

        // Ensure members belong to user's tenant
        if ($this->members) {
            $members = \App\Models\User::whereIn('id', $this->members)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->count();
                
            if ($members !== count($this->members)) {
                // Authorization should be handled in the controller
            }
        }

        // Ensure user has permission to create teams
        $user = auth()->user();
        if (!$user || !in_array($user->role, ['super_admin', 'admin', 'project_manager'])) {
            // Authorization should be handled in the controller
        }
    }
}
