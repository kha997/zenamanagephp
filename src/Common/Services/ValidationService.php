<?php declare(strict_types=1);

namespace Src\Common\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

/**
 * ValidationService - Centralized validation service for ZenaManage
 * 
 * Provides unified validation interface with support for:
 * - Project validation rules
 * - Task validation with dependencies
 * - Business rule validation
 * - Custom validation rules
 * - Validation error handling
 */
class ValidationService
{
    /**
     * Validate project data
     * 
     * @param array $data Project data
     * @param array $rules Custom rules (optional)
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateProject(array $data, array $rules = []): array
    {
        $defaultRules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'budget_planned' => 'nullable|numeric|min:0',
            'budget_actual' => 'nullable|numeric|min:0',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:planning,active,in_progress,on_hold,completed,cancelled',
            'client_id' => 'nullable|string|max:255',
            'pm_id' => 'nullable|string|max:255',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'settings' => 'nullable|array',
        ];

        $validationRules = array_merge($defaultRules, $rules);
        
        return $this->validate($data, $validationRules, 'project');
    }

    /**
     * Validate task data
     * 
     * @param array $data Task data
     * @param array $rules Custom rules (optional)
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateTask(array $data, array $rules = []): array
    {
        $defaultRules = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'project_id' => 'required|string|max:255',
            'assignee_id' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'estimated_hours' => 'nullable|numeric|min:0',
            'actual_hours' => 'nullable|numeric|min:0',
            'progress' => 'nullable|integer|min:0|max:100',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:pending,in_progress,completed,cancelled,on_hold',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'visibility' => 'required|in:public,team,private',
            'is_hidden' => 'nullable|boolean',
            'client_approved' => 'nullable|boolean',
        ];

        $validationRules = array_merge($defaultRules, $rules);
        
        return $this->validate($data, $validationRules, 'task');
    }

    /**
     * Validate task with dependencies
     * 
     * @param array $data Task data
     * @param array $dependencies Dependencies data
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateTaskWithDependencies(array $data, array $dependencies = []): array
    {
        // First validate basic task data
        $validatedData = $this->validateTask($data);
        
        // Then validate dependencies
        if (!empty($dependencies)) {
            $dependencyRules = [
                'dependencies' => 'array',
                'dependencies.*' => 'string|max:255|different:task_id',
            ];
            
            $dependencyData = ['dependencies' => $dependencies];
            $this->validate($dependencyData, $dependencyRules, 'task_dependencies');
            
            $validatedData['dependencies'] = $dependencies;
        }
        
        return $validatedData;
    }

    /**
     * Validate user data
     * 
     * @param array $data User data
     * @param array $rules Custom rules (optional)
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateUser(array $data, array $rules = []): array
    {
        $defaultRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'job_title' => 'nullable|string|max:100',
            'is_active' => 'nullable|boolean',
        ];

        $validationRules = array_merge($defaultRules, $rules);
        
        return $this->validate($data, $validationRules, 'user');
    }

    /**
     * Validate change request data
     * 
     * @param array $data Change request data
     * @param array $rules Custom rules (optional)
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateChangeRequest(array $data, array $rules = []): array
    {
        $defaultRules = [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:2000',
            'project_id' => 'required|string|max:255',
            'task_id' => 'nullable|string|max:255',
            'change_type' => 'required|in:scope,schedule,cost,quality,risk,resource',
            'priority' => 'required|in:low,medium,high,urgent',
            'impact_level' => 'required|in:low,medium,high',
            'requested_by' => 'required|string|max:255',
            'assigned_to' => 'nullable|string|max:255',
            'due_date' => 'nullable|date|after:today',
            'estimated_cost' => 'nullable|numeric|min:0',
            'estimated_days' => 'nullable|integer|min:0',
            'impact_analysis' => 'nullable|array',
            'risk_assessment' => 'nullable|array',
        ];

        $validationRules = array_merge($defaultRules, $rules);
        
        return $this->validate($data, $validationRules, 'change_request');
    }

    /**
     * Validate task dependencies
     * 
     * @param string $taskId Task ID
     * @param array $dependencies Dependencies array
     * @param array $existingDependencies Existing dependencies map
     * @return array Validation result
     */
    public function validateTaskDependencies(string $taskId, array $dependencies, array $existingDependencies = []): array
    {
        $errors = [];
        
        // Check for self-dependency
        if (in_array($taskId, $dependencies)) {
            $errors[] = 'Task cannot depend on itself';
        }
        
        // Check for circular dependencies
        foreach ($dependencies as $dependency) {
            if ($this->hasCircularDependency($taskId, $dependency, $existingDependencies)) {
                $errors[] = "Circular dependency detected: {$taskId} -> {$dependency}";
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for circular dependency
     * 
     * @param string $taskId Task ID
     * @param string $dependency Dependency ID
     * @param array $existingDependencies Existing dependencies map
     * @return bool
     */
    private function hasCircularDependency(string $taskId, string $dependency, array $existingDependencies): bool
    {
        if ($taskId === $dependency) {
            return true;
        }
        
        if (!isset($existingDependencies[$dependency])) {
            return false;
        }
        
        foreach ($existingDependencies[$dependency] as $dep) {
            if ($this->hasCircularDependency($taskId, $dep, $existingDependencies)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Business rule validation
     */

    /**
     * Validate project budget constraints
     * 
     * @param array $data Project data
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateProjectBudget(array $data): array
    {
        $rules = [
            'budget_planned' => 'required|numeric|min:1000', // Minimum budget
            'budget_actual' => 'nullable|numeric|min:0|lte:budget_planned',
        ];

        return $this->validate($data, $rules, 'project_budget');
    }

    /**
     * Validate task timeline constraints
     * 
     * @param array $data Task data
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateTaskTimeline(array $data): array
    {
        $rules = [
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'estimated_hours' => 'required|numeric|min:1|max:480', // Max 60 days * 8 hours
        ];

        return $this->validate($data, $rules, 'task_timeline');
    }

    /**
     * Validate user role assignment
     * 
     * @param array $data User role data
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateUserRoleAssignment(array $data): array
    {
        $rules = [
            'user_id' => 'required|string|max:255',
            'role_id' => 'required|string|max:255',
            'project_id' => 'nullable|string|max:255',
            'assigned_by' => 'required|string|max:255',
        ];

        return $this->validate($data, $rules, 'user_role_assignment');
    }

    /**
     * Validate file upload
     * 
     * @param array $data File data
     * @return array Validated data
     * @throws ValidationException
     */
    public function validateFileUpload(array $data): array
    {
        $rules = [
            'file' => 'required|file|max:10240', // Max 10MB
            'name' => 'required|string|max:255',
            'type' => 'required|in:drawing,contract,specification,report,photo,other',
            'category' => 'required|in:architectural,structural,mep,civil,landscape,other',
            'project_id' => 'nullable|string|max:255',
            'task_id' => 'nullable|string|max:255',
        ];

        return $this->validate($data, $rules, 'file_upload');
    }

    /**
     * Core validation method
     * 
     * @param array $data Data to validate
     * @param array $rules Validation rules
     * @param string $context Validation context
     * @return array Validated data
     * @throws ValidationException
     */
    private function validate(array $data, array $rules, string $context): array
    {
        try {
            $validator = Validator::make($data, $rules);
            
            if ($validator->fails()) {
                Log::warning('Validation failed', [
                    'context' => $context,
                    'errors' => $validator->errors()->toArray(),
                    'data' => $this->sanitizeDataForLogging($data),
                ]);
                
                throw new ValidationException($validator);
            }
            
            return $validator->validated();
            
        } catch (\Exception $e) {
            Log::error('Validation error', [
                'context' => $context,
                'error' => $e->getMessage(),
                'data' => $this->sanitizeDataForLogging($data),
            ]);
            
            throw $e;
        }
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     * 
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    private function sanitizeDataForLogging(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key'];
        $sanitized = $data;
        
        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }
        
        return $sanitized;
    }

    /**
     * Custom validation rules
     */

    /**
     * Validate ULID format
     * 
     * @param string $value Value to validate
     * @return bool
     */
    public function validateUlid(string $value): bool
    {
        return preg_match('/^[0-9A-Za-z]{26}$/', $value) === 1;
    }

    /**
     * Validate project code format
     * 
     * @param string $value Value to validate
     * @return bool
     */
    public function validateProjectCode(string $value): bool
    {
        return preg_match('/^PRJ-[A-Z0-9]{8}$/', $value) === 1;
    }

    /**
     * Validate change request number format
     * 
     * @param string $value Value to validate
     * @return bool
     */
    public function validateChangeRequestNumber(string $value): bool
    {
        return preg_match('/^CR-[A-Z0-9]{8}$/', $value) === 1;
    }

    /**
     * Validate email domain
     * 
     * @param string $email Email to validate
     * @param array $allowedDomains Allowed domains
     * @return bool
     */
    public function validateEmailDomain(string $email, array $allowedDomains = []): bool
    {
        if (empty($allowedDomains)) {
            return true; // No restriction
        }
        
        $domain = substr(strrchr($email, "@"), 1);
        return in_array($domain, $allowedDomains);
    }

    /**
     * Validate business hours
     * 
     * @param string $time Time to validate
     * @return bool
     */
    public function validateBusinessHours(string $time): bool
    {
        $hour = (int) date('H', strtotime($time));
        return $hour >= 8 && $hour <= 18; // 8 AM to 6 PM
    }

    /**
     * Validate working days
     * 
     * @param string $date Date to validate
     * @return bool
     */
    public function validateWorkingDays(string $date): bool
    {
        $dayOfWeek = date('N', strtotime($date));
        return $dayOfWeek >= 1 && $dayOfWeek <= 5; // Monday to Friday
    }

    /**
     * Get validation error messages
     * 
     * @param ValidationException $exception Validation exception
     * @return array Error messages
     */
    public function getErrorMessages(ValidationException $exception): array
    {
        return $exception->errors();
    }

    /**
     * Get first validation error message
     * 
     * @param ValidationException $exception Validation exception
     * @return string|null First error message
     */
    public function getFirstErrorMessage(ValidationException $exception): ?string
    {
        $errors = $this->getErrorMessages($exception);
        
        if (empty($errors)) {
            return null;
        }
        
        $firstField = array_key_first($errors);
        return $errors[$firstField][0] ?? null;
    }
}
