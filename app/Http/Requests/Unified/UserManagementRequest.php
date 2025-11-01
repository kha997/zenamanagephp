<?php declare(strict_types=1);

namespace App\Http\Requests\Unified;

use App\Http\Requests\Base\BaseUserRequest;
use Illuminate\Validation\Rule;

/**
 * Unified User Management Request
 * Replaces multiple user request classes
 */
class UserManagementRequest extends BaseUserRequest
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
     */
    public function rules(): array
    {
        $action = $this->route()->getActionMethod();
        
        return match($action) {
            'getUsers' => $this->getFilterRules(),
            'getUser' => $this->getIdRules(),
            'createUser' => $this->getCreateRules(),
            'updateUser' => $this->getUpdateRules(),
            'deleteUser' => $this->getIdRules(),
            'bulkDeleteUsers' => $this->getBulkRules(),
            'toggleUserStatus' => $this->getIdRules(),
            'updateUserRole' => $this->getRoleUpdateRules(),
            'getUserStats' => $this->getStatsRules(),
            'searchUsers' => $this->getSearchRules(),
            'getUserPreferences' => $this->getIdRules(),
            'updateUserPreferences' => $this->getPreferencesUpdateRules(),
            default => []
        };
    }

    /**
     * Get validation rules for user creation
     */
    protected function getCreateRules(): array
    {
        return array_merge(
            $this->getUserRules(),
            $this->getPasswordRules(),
            [
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            ]
        );
    }

    /**
     * Get validation rules for user update
     */
    protected function getUpdateRules(): array
    {
        $userId = $this->route('id');
        
        return array_merge(
            $this->getUserUpdateRules(),
            $this->getPasswordUpdateRules(),
            [
                'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            ]
        );
    }

    /**
     * Get validation rules for role update
     */
    protected function getRoleUpdateRules(): array
    {
        return [
            'role' => ['required', 'string', 'in:admin,member,client'],
        ];
    }

    /**
     * Get validation rules for preferences update
     */
    protected function getPreferencesUpdateRules(): array
    {
        return [
            'preferences' => ['required', 'array'],
            'preferences.theme' => ['sometimes', 'string', 'in:light,dark'],
            'preferences.notifications_enabled' => ['sometimes', 'boolean'],
            'preferences.email_notifications' => ['sometimes', 'boolean'],
            'preferences.language' => ['sometimes', 'string', 'max:10'],
            'preferences.timezone' => ['sometimes', 'string', 'max:50'],
            'preferences.date_format' => ['sometimes', 'string', 'max:20'],
            'preferences.time_format' => ['sometimes', 'string', 'in:12,24'],
            'preferences.items_per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * Get validation rules for filtering
     */
    protected function getFilterRules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'role' => ['sometimes', 'string', 'in:admin,member,client'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_by' => ['sometimes', 'string', 'in:name,email,role,status,created_at,updated_at'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get validation rules for ID parameter
     */
    protected function getIdRules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
