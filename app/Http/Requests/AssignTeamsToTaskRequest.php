<?php declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * Request validation for assigning teams to a task
 */
class AssignTeamsToTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware and policies
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        return [
            'teams' => 'required|array|min:1|max:50',
            'teams.*.team_id' => [
                'required',
                'string',
                'ulid',
                function ($attribute, $value, $fail) use ($tenantId) {
                    $team = \App\Models\Team::where('id', $value)
                        ->where('tenant_id', $tenantId)
                        ->first();
                    
                    if (!$team) {
                        $fail('The selected team does not exist or does not belong to your tenant.');
                    }
                }
            ],
            'teams.*.role' => [
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
            'teams.required' => 'The teams field is required.',
            'teams.array' => 'The teams must be an array.',
            'teams.min' => 'At least one team must be provided.',
            'teams.max' => 'Maximum 50 teams can be assigned at once.',
            'teams.*.team_id.required' => 'Each team must have a team_id.',
            'teams.*.team_id.string' => 'The team_id must be a string.',
            'teams.*.team_id.ulid' => 'The team_id must be a valid ULID.',
            'teams.*.role.string' => 'The role must be a string.',
            'teams.*.role.in' => 'The role must be one of: assignee, reviewer, watcher.',
        ];
    }
}

