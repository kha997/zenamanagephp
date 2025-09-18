<?php

namespace App\Http\Requests;

/**
 * Centralized Validation Rules
 * 
 * Provides consistent validation rules across the application
 */
class ValidationRules
{
    /**
     * Common validation patterns
     */
    public static function email(): string
    {
        return 'required|email:rfc,dns|max:255';
    }

    public static function password(): string
    {
        return 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/';
    }

    public static function passwordConfirmation(): string
    {
        return 'required|string|min:8|confirmed';
    }

    public static function phone(): string
    {
        return 'nullable|string|max:20|regex:/^[\+]?[1-9][\d]{0,15}$/';
    }

    public static function ulid(): string
    {
        return 'required|string|regex:/^[0-9A-HJKMNP-TV-Z]{26}$/';
    }

    public static function ulidOptional(): string
    {
        return 'nullable|string|regex:/^[0-9A-HJKMNP-TV-Z]{26}$/';
    }

    public static function tenantId(): string
    {
        return 'nullable|string|regex:/^[0-9A-HJKMNP-TV-Z]{26}$/';
    }

    public static function name(): string
    {
        return 'required|string|min:2|max:255';
    }

    public static function nameOptional(): string
    {
        return 'nullable|string|min:2|max:255';
    }

    public static function description(): string
    {
        return 'required|string|min:10|max:1000';
    }

    public static function descriptionOptional(): string
    {
        return 'nullable|string|min:10|max:1000';
    }

    public static function url(): string
    {
        return 'nullable|string|url|max:500';
    }

    public static function date(): string
    {
        return 'nullable|date|after_or_equal:today';
    }

    public static function datePast(): string
    {
        return 'nullable|date';
    }

    public static function positiveNumber(): string
    {
        return 'nullable|numeric|min:0';
    }

    public static function boolean(): string
    {
        return 'nullable|boolean';
    }

    /**
     * User validation rules
     */
    public static function userCreate(): array
    {
        return [
            'name' => self::name(),
            'email' => self::email(),
            'password' => self::passwordConfirmation(),
            'phone' => self::phone(),
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function userUpdate(): array
    {
        return [
            'name' => self::nameOptional(),
            'email' => 'nullable|email:rfc,dns|max:255',
            'phone' => self::phone(),
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'manager' => 'nullable|string|max:255',
            'avatar' => self::url(),
            'preferences' => 'nullable|array',
        ];
    }

    public static function userChangePassword(): array
    {
        return [
            'current_password' => 'required|string',
            'new_password' => self::password(),
            'new_password_confirmation' => 'required|string|min:8|same:new_password',
        ];
    }

    public static function userBulkCreate(): array
    {
        return [
            'users' => 'required|array|min:1|max:1000',
            'users.*.name' => self::name(),
            'users.*.email' => self::email(),
            'users.*.password' => 'nullable|string|min:8',
            'users.*.phone' => self::phone(),
            'users.*.first_name' => 'nullable|string|max:255',
            'users.*.last_name' => 'nullable|string|max:255',
            'users.*.department' => 'nullable|string|max:255',
            'users.*.job_title' => 'nullable|string|max:255',
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function userBulkUpdate(): array
    {
        return [
            'updates' => 'required|array|min:1|max:1000',
            'updates.*.id' => self::ulid(),
            'updates.*.data' => 'required|array',
        ];
    }

    public static function userBulkDelete(): array
    {
        return [
            'user_ids' => 'required|array|min:1|max:1000',
            'user_ids.*' => self::ulid(),
        ];
    }

    /**
     * Project validation rules
     */
    public static function projectCreate(): array
    {
        return [
            'name' => self::name(),
            'description' => self::description(),
            'status' => 'nullable|string|in:planning,active,completed,cancelled',
            'start_date' => self::datePast(),
            'end_date' => 'nullable|date|after:start_date',
            'budget' => self::positiveNumber(),
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function projectUpdate(): array
    {
        return [
            'name' => self::nameOptional(),
            'description' => self::descriptionOptional(),
            'status' => 'nullable|string|in:planning,active,completed,cancelled',
            'start_date' => self::datePast(),
            'end_date' => 'nullable|date|after:start_date',
            'budget' => self::positiveNumber(),
            'priority' => 'nullable|string|in:low,medium,high,urgent',
        ];
    }

    public static function projectBulkCreate(): array
    {
        return [
            'projects' => 'required|array|min:1|max:1000',
            'projects.*.name' => self::name(),
            'projects.*.description' => self::description(),
            'projects.*.status' => 'nullable|string|in:planning,active,completed,cancelled',
            'projects.*.start_date' => self::datePast(),
            'projects.*.end_date' => 'nullable|date|after:start_date',
            'projects.*.budget' => self::positiveNumber(),
            'projects.*.priority' => 'nullable|string|in:low,medium,high,urgent',
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function projectBulkUpdate(): array
    {
        return [
            'updates' => 'required|array|min:1|max:1000',
            'updates.*.id' => self::ulid(),
            'updates.*.data' => 'required|array',
        ];
    }

    /**
     * Task validation rules
     */
    public static function taskCreate(): array
    {
        return [
            'title' => self::name(),
            'description' => self::description(),
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => self::date(),
            'assignee_id' => self::ulidOptional(),
            'project_id' => self::ulid(),
            'estimated_hours' => self::positiveNumber(),
            'actual_hours' => 'nullable|numeric|min:0',
            'dependencies' => 'nullable|array',
            'dependencies.*' => self::ulid(),
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function taskUpdate(): array
    {
        return [
            'title' => self::nameOptional(),
            'description' => self::descriptionOptional(),
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'due_date' => self::date(),
            'assignee_id' => self::ulidOptional(),
            'estimated_hours' => self::positiveNumber(),
            'actual_hours' => 'nullable|numeric|min:0',
            'dependencies' => 'nullable|array',
            'dependencies.*' => self::ulid(),
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ];
    }

    public static function taskBulkCreate(): array
    {
        return [
            'tasks' => 'required|array|min:1|max:1000',
            'tasks.*.title' => self::name(),
            'tasks.*.description' => self::description(),
            'tasks.*.status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'tasks.*.priority' => 'nullable|string|in:low,medium,high,urgent',
            'tasks.*.due_date' => self::date(),
            'tasks.*.assignee_id' => self::ulidOptional(),
            'tasks.*.estimated_hours' => self::positiveNumber(),
            'tasks.*.actual_hours' => 'nullable|numeric|min:0',
            'tasks.*.dependencies' => 'nullable|array',
            'tasks.*.dependencies.*' => self::ulid(),
            'tasks.*.tags' => 'nullable|array',
            'tasks.*.tags.*' => 'string|max:50',
            'project_id' => self::ulid(),
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function taskBulkUpdateStatus(): array
    {
        return [
            'task_ids' => 'required|array|min:1|max:1000',
            'task_ids.*' => self::ulid(),
            'status' => 'required|string|in:pending,in_progress,completed,cancelled',
        ];
    }

    /**
     * Document validation rules
     */
    public static function documentUpload(): array
    {
        return [
            'title' => self::name(),
            'description' => self::descriptionOptional(),
            'document_type' => 'required|string|in:contract,proposal,report,invoice,other',
            'project_id' => self::ulid(),
            'file' => 'required|file|mimes:pdf,doc,docx,txt|max:10240', // 10MB
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => self::boolean(),
            'tenant_id' => self::tenantId(),
        ];
    }

    public static function documentUpdate(): array
    {
        return [
            'title' => self::nameOptional(),
            'description' => self::descriptionOptional(),
            'document_type' => 'nullable|string|in:contract,proposal,report,invoice,other',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'is_public' => self::boolean(),
        ];
    }

    /**
     * SSO validation rules
     */
    public static function oidcInitiate(): array
    {
        return [
            'provider' => 'required|string|in:google,microsoft,azure_ad,okta,auth0',
            'state' => 'nullable|string|max:255',
        ];
    }

    public static function oidcCallback(): array
    {
        return [
            'code' => 'required|string',
            'state' => 'required|string',
        ];
    }

    public static function samlInitiate(): array
    {
        return [
            'provider' => 'required|string|in:azure_ad,okta,adfs',
        ];
    }

    public static function samlCallback(): array
    {
        return [
            'SAMLResponse' => 'required|string',
            'RelayState' => 'nullable|string',
        ];
    }

    public static function samlLogout(): array
    {
        return [
            'name_id' => 'required|string',
            'session_index' => 'nullable|string',
        ];
    }

    /**
     * Import/Export validation rules
     */
    public static function exportFilters(): array
    {
        return [
            'tenant_id' => self::tenantId(),
            'status' => 'nullable|string',
            'date_from' => self::datePast(),
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'project_id' => self::ulidOptional(),
            'assignee_id' => self::ulidOptional(),
        ];
    }

    public static function importOptions(): array
    {
        return [
            'tenant_id' => self::tenantId(),
            'skip_duplicates' => self::boolean(),
            'update_existing' => self::boolean(),
            'validate_data' => self::boolean(),
        ];
    }

    public static function importFile(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB
            'options' => 'nullable|array',
            'options.tenant_id' => self::tenantId(),
            'options.skip_duplicates' => self::boolean(),
            'options.update_existing' => self::boolean(),
            'options.validate_data' => self::boolean(),
        ];
    }

    /**
     * Search and filter validation rules
     */
    public static function search(): array
    {
        return [
            'query' => 'required|string|min:1|max:255',
            'type' => 'nullable|string|in:users,projects,tasks,documents',
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:0',
        ];
    }

    public static function userFilters(): array
    {
        return [
            'tenant_id' => self::tenantId(),
            'status' => 'nullable|string|in:active,inactive',
            'role' => 'nullable|string',
            'department' => 'nullable|string',
            'date_from' => self::datePast(),
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];
    }

    public static function projectFilters(): array
    {
        return [
            'tenant_id' => self::tenantId(),
            'status' => 'nullable|string|in:planning,active,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'date_from' => self::datePast(),
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];
    }

    public static function taskFilters(): array
    {
        return [
            'project_id' => self::ulidOptional(),
            'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
            'priority' => 'nullable|string|in:low,medium,high,urgent',
            'assignee_id' => self::ulidOptional(),
            'due_date_from' => self::datePast(),
            'due_date_to' => 'nullable|date|after_or_equal:due_date_from',
        ];
    }

    /**
     * MFA validation rules
     */
    public static function mfaEnable(): array
    {
        return [
            'secret' => 'required|string',
            'code' => 'required|string|size:6|regex:/^\d{6}$/',
        ];
    }

    public static function mfaVerify(): array
    {
        return [
            'code' => 'required|string|size:6|regex:/^\d{6}$/',
        ];
    }

    public static function mfaRecovery(): array
    {
        return [
            'code' => 'required|string|min:10',
        ];
    }

    /**
     * Email verification validation rules
     */
    public static function emailSendVerification(): array
    {
        return [
            'email' => 'nullable|email:rfc,dns|max:255',
        ];
    }

    public static function emailVerify(): array
    {
        return [
            'token' => 'required|string',
        ];
    }

    public static function emailChange(): array
    {
        return [
            'new_email' => self::email(),
            'password' => 'required|string',
        ];
    }

    public static function emailConfirmChange(): array
    {
        return [
            'token' => 'required|string',
        ];
    }

    /**
     * Session management validation rules
     */
    public static function sessionRevoke(): array
    {
        return [
            'session_id' => 'required|string',
        ];
    }

    public static function sessionMarkTrusted(): array
    {
        return [
            'session_id' => 'required|string',
            'trusted' => 'required|boolean',
        ];
    }

    /**
     * Bulk assignment validation rules
     */
    public static function bulkAssignUsersToProjects(): array
    {
        return [
            'assignments' => 'required|array|min:1|max:1000',
            'assignments.*.user_id' => self::ulid(),
            'assignments.*.project_id' => self::ulid(),
            'assignments.*.role' => 'nullable|string|max:50',
        ];
    }

    /**
     * Custom validation messages
     */
    public static function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'phone.regex' => 'Please enter a valid phone number.',
            'ulid.regex' => 'Invalid ID format.',
            'tenant_id.regex' => 'Invalid tenant ID format.',
            'file.mimes' => 'File type not allowed. Allowed types: :values',
            'file.max' => 'File size must be less than :max KB.',
            'date.after' => 'End date must be after start date.',
            'date.after_or_equal' => 'End date must be after or equal to start date.',
            'confirmed' => 'Password confirmation does not match.',
            'same' => 'Password confirmation does not match.',
            'in' => 'The selected :attribute is invalid.',
            'min' => 'The :attribute must be at least :min characters.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'required' => 'The :attribute field is required.',
            'email' => 'Please enter a valid email address.',
            'string' => 'The :attribute must be a string.',
            'array' => 'The :attribute must be an array.',
            'boolean' => 'The :attribute must be true or false.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'date' => 'The :attribute must be a valid date.',
            'url' => 'The :attribute must be a valid URL.',
        ];
    }
}
