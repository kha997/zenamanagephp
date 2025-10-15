<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\EnhancedValidationService;
use App\Services\ComprehensiveLoggingService;
use App\Http\Requests\ValidationRules;

/**
 * Enhanced Validation Middleware
 * 
 * Comprehensive validation middleware that handles input validation,
 * sanitization, and security checks for all requests
 */
class EnhancedValidationMiddleware
{
    private EnhancedValidationService $validationService;

    public function __construct(EnhancedValidationService $validationService)
    {
        $this->validationService = $validationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            // Get validation rules for the current request
            $rules = $this->getValidationRules($request);
            
            if (!empty($rules)) {
                // Validate and sanitize input data
                $validatedData = $this->validationService->validateAndSanitize(
                    $request->all(),
                    $rules
                );

                // Replace request data with validated and sanitized data
                $request->replace($validatedData);

                // Log successful validation
                ComprehensiveLoggingService::logAudit('request_validated', 'Validation', null, [
                    'endpoint' => $request->path(),
                    'method' => $request->method(),
                    'rules_count' => count($rules),
                ]);
            }

            return $next($request);

        } catch (\InvalidArgumentException $e) {
            // Log validation failure
            ComprehensiveLoggingService::logSecurity('validation_failed', [
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Return appropriate error response
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'id' => 'validation_failed',
                        'message' => 'Input validation failed',
                        'details' => $e->getMessage(),
                    ]
                ], 422);
            }

            return redirect()->back()
                ->withErrors(['validation' => $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Get validation rules for the current request
     */
    private function getValidationRules(Request $request): array
    {
        $path = $request->path();
        $method = $request->method();

        // API routes
        if (str_starts_with($path, 'api/')) {
            return $this->getApiValidationRules($path, $method);
        }

        // Web routes
        if (str_starts_with($path, 'app/')) {
            return $this->getAppValidationRules($path, $method);
        }

        // Admin routes
        if (str_starts_with($path, 'admin/')) {
            return $this->getAdminValidationRules($path, $method);
        }

        // Auth routes
        if (str_starts_with($path, 'login') || str_starts_with($path, 'register')) {
            return $this->getAuthValidationRules($path, $method);
        }

        return [];
    }

    /**
     * Get API validation rules
     */
    private function getApiValidationRules(string $path, string $method): array
    {
        $pathParts = explode('/', $path);
        $endpoint = $pathParts[1] ?? '';

        switch ($endpoint) {
            case 'users':
                return $method === 'POST' ? ValidationRules::userCreate() : ValidationRules::userUpdate();
            
            case 'projects':
                return $method === 'POST' ? ValidationRules::projectCreate() : ValidationRules::projectUpdate();
            
            case 'tasks':
                return $method === 'POST' ? ValidationRules::taskCreate() : ValidationRules::taskUpdate();
            
            case 'documents':
                return ValidationRules::documentUpload();
            
            case 'search':
                return ValidationRules::search();
            
            case 'export':
                return ValidationRules::exportFilters();
            
            case 'import':
                return ValidationRules::importFile();
            
            case 'bulk':
                return $this->getBulkValidationRules($pathParts, $method);
            
            default:
                return [];
        }
    }

    /**
     * Get app validation rules
     */
    private function getAppValidationRules(string $path, string $method): array
    {
        $pathParts = explode('/', $path);
        $endpoint = $pathParts[1] ?? '';

        switch ($endpoint) {
            case 'projects':
                return $method === 'POST' ? ValidationRules::projectCreate() : ValidationRules::projectUpdate();
            
            case 'tasks':
                return $method === 'POST' ? ValidationRules::taskCreate() : ValidationRules::taskUpdate();
            
            case 'documents':
                return ValidationRules::documentUpload();
            
            case 'search':
                return ValidationRules::search();
            
            default:
                return [];
        }
    }

    /**
     * Get admin validation rules
     */
    private function getAdminValidationRules(string $path, string $method): array
    {
        $pathParts = explode('/', $path);
        $endpoint = $pathParts[1] ?? '';

        switch ($endpoint) {
            case 'users':
                return $method === 'POST' ? ValidationRules::userCreate() : ValidationRules::userUpdate();
            
            case 'tenants':
                return $this->getTenantValidationRules($method);
            
            case 'settings':
                return $this->getSettingsValidationRules($method);
            
            default:
                return [];
        }
    }

    /**
     * Get authentication validation rules
     */
    private function getAuthValidationRules(string $path, string $method): array
    {
        switch ($path) {
            case 'login':
                return [
                    'email' => ValidationRules::email(),
                    'password' => 'required|string|min:8',
                    'remember' => ValidationRules::boolean(),
                ];
            
            case 'register':
                return ValidationRules::userCreate();
            
            case 'forgot-password':
                return [
                    'email' => ValidationRules::email(),
                ];
            
            case 'reset-password':
                return [
                    'token' => 'required|string',
                    'email' => ValidationRules::email(),
                    'password' => ValidationRules::password(),
                    'password_confirmation' => 'required|string|min:8|same:password',
                ];
            
            default:
                return [];
        }
    }

    /**
     * Get bulk operation validation rules
     */
    private function getBulkValidationRules(array $pathParts, string $method): array
    {
        $operation = $pathParts[2] ?? '';
        $entity = $pathParts[3] ?? '';

        switch ($operation) {
            case 'create':
                switch ($entity) {
                    case 'users':
                        return ValidationRules::userBulkCreate();
                    case 'projects':
                        return ValidationRules::projectBulkCreate();
                    case 'tasks':
                        return ValidationRules::taskBulkCreate();
                }
                break;
            
            case 'update':
                switch ($entity) {
                    case 'users':
                        return ValidationRules::userBulkUpdate();
                    case 'projects':
                        return ValidationRules::projectBulkUpdate();
                    case 'tasks':
                        return ValidationRules::taskBulkUpdateStatus();
                }
                break;
            
            case 'delete':
                switch ($entity) {
                    case 'users':
                        return ValidationRules::userBulkDelete();
                }
                break;
        }

        return [];
    }

    /**
     * Get tenant validation rules
     */
    private function getTenantValidationRules(string $method): array
    {
        return [
            'name' => ValidationRules::name(),
            'slug' => 'required|string|min:2|max:50|regex:/^[a-z0-9\-]+$/',
            'domain' => ValidationRules::url(),
            'is_active' => ValidationRules::boolean(),
            'preferences' => 'nullable|array',
        ];
    }

    /**
     * Get settings validation rules
     */
    private function getSettingsValidationRules(string $method): array
    {
        return [
            'setting_key' => 'required|string|max:255',
            'setting_value' => 'required|string|max:1000',
            'setting_type' => 'nullable|string|in:string,integer,boolean,array,json',
        ];
    }

    /**
     * Validate file uploads
     */
    private function validateFileUploads(Request $request): void
    {
        $files = $request->allFiles();
        
        foreach ($files as $fieldName => $file) {
            if (is_array($file)) {
                // Handle multiple file uploads
                foreach ($file as $index => $singleFile) {
                    $this->validateSingleFile($singleFile, $fieldName, $index);
                }
            } else {
                // Handle single file upload
                $this->validateSingleFile($file, $fieldName);
            }
        }
    }

    /**
     * Validate single file upload
     */
    private function validateSingleFile($file, string $fieldName, int $index = null): void
    {
        $allowedTypes = $this->getAllowedFileTypes($fieldName);
        $maxSize = $this->getMaxFileSize($fieldName);

        $validationResult = $this->validationService->validateFileUpload($file, $allowedTypes, $maxSize);
        
        if (!$validationResult['valid']) {
            $field = $index !== null ? "{$fieldName}.{$index}" : $fieldName;
            throw new \InvalidArgumentException("File validation failed for {$field}: {$validationResult['error']}");
        }
    }

    /**
     * Get allowed file types for field
     */
    private function getAllowedFileTypes(string $fieldName): array
    {
        $fileTypeMap = [
            'avatar' => ['jpg', 'jpeg', 'png', 'gif'],
            'document' => ['pdf', 'doc', 'docx', 'txt'],
            'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'file' => ['pdf', 'doc', 'docx', 'txt', 'xlsx', 'xls', 'csv'],
        ];

        return $fileTypeMap[$fieldName] ?? ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'];
    }

    /**
     * Get maximum file size for field (in KB)
     */
    private function getMaxFileSize(string $fieldName): int
    {
        $fileSizeMap = [
            'avatar' => 2048, // 2MB
            'document' => 10240, // 10MB
            'image' => 5120, // 5MB
            'file' => 10240, // 10MB
        ];

        return $fileSizeMap[$fieldName] ?? 10240; // Default 10MB
    }
}
