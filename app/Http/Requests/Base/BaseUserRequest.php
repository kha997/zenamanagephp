<?php declare(strict_types=1);

namespace App\Http\Requests\Base;

use App\Http\Requests\BaseApiRequest;
use Illuminate\Validation\Rule;

/**
 * Base User Request với common validation rules
 */
abstract class BaseUserRequest extends BaseApiRequest
{
    /**
     * Get validation rules for user fields
     */
    protected function getUserRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:admin,member,client'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_active' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'avatar' => ['sometimes', 'string', 'max:500'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'language' => ['sometimes', 'string', 'max:10'],
            'preferences' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get validation rules for password fields
     */
    protected function getPasswordRules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
        ];
    }

    /**
     * Get validation rules for user update (without password)
     */
    protected function getUserUpdateRules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,member,client'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_active' => ['sometimes', 'boolean'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'avatar' => ['sometimes', 'string', 'max:500'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'language' => ['sometimes', 'string', 'max:10'],
            'preferences' => ['sometimes', 'array'],
        ];
    }

    /**
     * Get validation rules for password update
     */
    protected function getPasswordUpdateRules(): array
    {
        return [
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['sometimes', 'string', 'min:8'],
            'current_password' => ['sometimes', 'string'],
        ];
    }

    /**
     * Get validation rules for bulk operations
     */
    protected function getBulkRules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
            'action' => ['required', 'string', 'in:activate,deactivate,delete,change_role'],
            'role' => ['sometimes', 'string', 'in:admin,member,client'],
        ];
    }

    /**
     * Get validation rules for user search
     */
    protected function getSearchRules(): array
    {
        return [
            'search' => ['required', 'string', 'min:2', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'role' => ['sometimes', 'string', 'in:admin,member,client'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Get validation rules for user preferences
     */
    protected function getPreferencesRules(): array
    {
        return [
            'theme' => ['sometimes', 'string', 'in:light,dark'],
            'notifications_enabled' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'language' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:50'],
            'date_format' => ['sometimes', 'string', 'max:20'],
            'time_format' => ['sometimes', 'string', 'in:12,24'],
            'items_per_page' => ['sometimes', 'integer', 'min:5', 'max:100'],
        ];
    }

    /**
     * Get validation rules for user statistics
     */
    protected function getStatsRules(): array
    {
        return [
            'period' => ['sometimes', 'string', 'in:today,week,month,quarter,year'],
            'group_by' => ['sometimes', 'string', 'in:role,status,created_at'],
        ];
    }
}
