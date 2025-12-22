<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Services\ComprehensiveLoggingService;
use App\Http\Requests\ValidationRules;
use App\Services\InputSanitizationService;

/**
 * Enhanced Validation Service
 * 
 * Comprehensive validation service that integrates sanitization,
 * validation rules, and security checks
 */
class EnhancedValidationService
{
    private InputSanitizationService $sanitizationService;
    private array $validationCache = [];
    private array $securityPatterns = [];

    public function __construct(InputSanitizationService $sanitizationService)
    {
        $this->sanitizationService = $sanitizationService;
        $this->initializeSecurityPatterns();
    }

    /**
     * Initialize security patterns for validation
     */
    private function initializeSecurityPatterns(): void
    {
        $this->securityPatterns = [
            'sql_injection' => [
                '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
                '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
                '/(\b(OR|AND)\s+\'\s*=\s*\')/i',
                '/(\b(OR|AND)\s+"\s*=\s*")/i',
                '/(\b(OR|AND)\s+1\s*=\s*1)/i',
                '/(\b(OR|AND)\s+\'\s*=\s*\')/i',
            ],
            'xss' => [
                '/<script[^>]*>.*?<\/script>/i',
                '/javascript:/i',
                '/on\w+\s*=/i',
                '/<iframe[^>]*>.*?<\/iframe>/i',
                '/<object[^>]*>.*?<\/object>/i',
                '/<embed[^>]*>.*?<\/embed>/i',
                '/<link[^>]*>.*?<\/link>/i',
                '/<meta[^>]*>.*?<\/meta>/i',
            ],
            'command_injection' => [
                '/[;&|`$()]/',
                '/\b(cat|ls|pwd|whoami|id|uname|ps|netstat|ifconfig|wget|curl)\b/i',
                '/\b(rm|del|mkdir|rmdir|chmod|chown)\b/i',
            ],
            'path_traversal' => [
                '/\.\.\//',
                '/\.\.\\\\/',
                '/%2e%2e%2f/i',
                '/%2e%2e%5c/i',
                '/\.\.%2f/i',
                '/\.\.%5c/i',
            ],
            'ldap_injection' => [
                '/[()=*!&|]/',
                '/\b(uid=|cn=|ou=|dc=)/i',
            ],
            'nosql_injection' => [
                '/\$where/i',
                '/\$ne/i',
                '/\$gt/i',
                '/\$lt/i',
                '/\$regex/i',
                '/\$exists/i',
                '/\$in/i',
                '/\$nin/i',
            ],
        ];
    }

    /**
     * Validate and sanitize input data
     */
    public function validateAndSanitize(array $data, array $rules, array $customMessages = []): array
    {
        // Step 1: Security validation
        $securityResult = $this->validateSecurity($data);
        if (!$securityResult['valid']) {
            ComprehensiveLoggingService::logSecurity('suspicious_input_detected', [
                'patterns' => $securityResult['patterns'],
                'data' => $this->sanitizeForLogging($data),
            ]);
            
            throw new \InvalidArgumentException('Suspicious input detected: ' . implode(', ', $securityResult['patterns']));
        }

        // Step 2: Validate original data first
        $validationResult = $this->validateData($data, $rules, $customMessages);
        if (!$validationResult['valid']) {
            ComprehensiveLoggingService::logSecurity('validation_failed', [
                'errors' => $validationResult['errors'],
                'data' => $this->sanitizeForLogging($data),
            ]);
            
            throw new \InvalidArgumentException('Validation failed: ' . implode(', ', $validationResult['errors']));
        }

        // Step 3: Sanitize validated data
        $sanitizedData = $this->sanitizeData($data, $rules);

        // Step 4: Log successful validation
        ComprehensiveLoggingService::logAudit('input_validated', 'Validation', null, [
            'rules_count' => count($rules),
            'data_keys' => array_keys($sanitizedData),
        ]);

        return [
            'valid' => true,
            'data' => $sanitizedData,
            'sanitized_data' => $sanitizedData,
        ];
    }

    /**
     * Validate data against rules
     */
    public function validateData(array $data, array $rules, array $customMessages = []): array
    {
        $validator = Validator::make($data, $rules, array_merge(ValidationRules::messages(), $customMessages));

        if ($validator->fails()) {
            return [
                'valid' => false,
                'errors' => $validator->errors()->all(),
                'detailed_errors' => $validator->errors()->toArray(),
            ];
        }

        return [
            'valid' => true,
            'data' => $data,
        ];
    }

    /**
     * Sanitize data based on rules
     */
    public function sanitizeData(array $data, array $rules): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $rule = $rules[$key] ?? 'string';
            $sanitized[$key] = $this->sanitizeByRule($value, $rule);
        }

        return $sanitized;
    }

    /**
     * Sanitize value based on validation rule
     */
    private function sanitizeByRule($value, string $rule): mixed
    {
        if ($value === null) {
            return null;
        }

        // Extract rule type from Laravel validation rule string
        $ruleType = $this->extractRuleType($rule);

        switch ($ruleType) {
            case 'email':
                return $this->sanitizationService->sanitizeEmail($this->convertToString($value));
            
            case 'url':
                return $this->sanitizationService->sanitizeUrl($this->convertToString($value));
            
            case 'integer':
            case 'numeric':
                return $this->sanitizationService->sanitizeInteger($value);
            
            case 'float':
                return $this->sanitizationService->sanitizeFloat($value);
            
            case 'boolean':
                return $this->sanitizationService->sanitizeBoolean($value);
            
            case 'file':
                return $this->sanitizeFile($value);
            
            case 'json':
                // For JSON validation, don't sanitize the JSON string as it might break the format
                return is_string($value) ? $value : $this->convertToString($value);
            
            case 'phone':
                return $this->sanitizationService->sanitizePhoneNumber($this->convertToString($value));
            
            case 'textarea':
                return $this->sanitizationService->sanitizeTextarea($this->convertToString($value));
            
            case 'search':
                return $this->sanitizationService->sanitizeSearchQuery($this->convertToString($value));
            
            case 'html':
                return $this->sanitizationService->sanitizeString($this->convertToString($value), true);
            
            case 'array':
                // For array validation, don't convert to string
                return is_array($value) ? $this->sanitizationService->sanitizeArray($value) : $value;
            
            default:
                return $this->sanitizationService->sanitizeString($this->convertToString($value));
        }
    }

    /**
     * Convert value to string for sanitization
     */
    private function convertToString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        if (is_array($value)) {
            return json_encode($value);
        }
        
        if (is_object($value)) {
            return json_encode($value);
        }
        
        return (string) $value;
    }

    /**
     * Extract rule type from Laravel validation rule string
     */
    private function extractRuleType(string $rule): string
    {
        $rule = explode('|', $rule)[0]; // Get first rule
        
        // Handle common patterns
        if (str_contains($rule, 'email')) return 'email';
        if (str_contains($rule, 'url')) return 'url';
        if (str_contains($rule, 'integer')) return 'integer';
        if (str_contains($rule, 'numeric')) return 'numeric';
        if (str_contains($rule, 'boolean')) return 'boolean';
        if (str_contains($rule, 'file')) return 'file';
        if (str_contains($rule, 'json')) return 'json';
        if (str_contains($rule, 'phone')) return 'phone';
        if (str_contains($rule, 'textarea')) return 'textarea';
        if (str_contains($rule, 'search')) return 'search';
        if (str_contains($rule, 'html')) return 'html';
        if (str_contains($rule, 'array')) return 'array';
        
        return 'string';
    }

    /**
     * Validate for security threats
     */
    public function validateSecurity(array $data): array
    {
        $detectedPatterns = [];

        foreach ($data as $key => $value) {
            $inputString = is_array($value) ? json_encode($value) : (string)$value;
            
            foreach ($this->securityPatterns as $category => $patterns) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $inputString)) {
                        $detectedPatterns[] = "{$category}: {$pattern}";
                    }
                }
            }
        }

        return [
            'valid' => empty($detectedPatterns),
            'patterns' => $detectedPatterns,
        ];
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload($file, array $allowedTypes = [], int $maxSize = 10240): array
    {
        if (!$file || !$file->isValid()) {
            return [
                'valid' => false,
                'error' => 'Invalid file upload',
            ];
        }

        // Check file size (in KB)
        if ($file->getSize() > $maxSize * 1024) {
            return [
                'valid' => false,
                'error' => "File size exceeds maximum allowed size of {$maxSize}KB",
            ];
        }

        // Check file type
        if (!empty($allowedTypes)) {
            $extension = strtolower($file->getClientOriginalExtension());
            if (!in_array($extension, $allowedTypes)) {
                return [
                    'valid' => false,
                    'error' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes),
                ];
            }
        }

        // Sanitize filename
        $sanitizedFilename = $this->sanitizationService->sanitizeFileName($file->getClientOriginalName());

        return [
            'valid' => true,
            'file' => $file,
            'sanitized_filename' => $sanitizedFilename,
        ];
    }

    /**
     * Validate bulk operations
     */
    public function validateBulkOperation(array $data, array $rules, int $maxItems = 1000): array
    {
        if (count($data) > $maxItems) {
            return [
                'valid' => false,
                'error' => "Bulk operation exceeds maximum allowed items of {$maxItems}",
            ];
        }

        $errors = [];
        $validItems = [];

        foreach ($data as $index => $item) {
            try {
                $validatedItem = $this->validateAndSanitize($item, $rules);
                $validItems[] = $validatedItem;
            } catch (\Exception $e) {
                $errors[] = "Item {$index}: " . $e->getMessage();
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_items' => $validItems,
            'total_items' => count($data),
            'valid_count' => count($validItems),
        ];
    }

    /**
     * Validate API request
     */
    public function validateApiRequest(array $data, string $endpoint, string $method = 'POST'): array
    {
        $rules = $this->getApiValidationRules($endpoint, $method);
        
        if (empty($rules)) {
            return [
                'valid' => true,
                'data' => $this->sanitizationService->sanitizeArray($data),
            ];
        }

        return $this->validateAndSanitize($data, $rules);
    }

    /**
     * Get API validation rules for specific endpoint
     */
    private function getApiValidationRules(string $endpoint, string $method): array
    {
        $cacheKey = "{$method}:{$endpoint}";
        
        if (isset($this->validationCache[$cacheKey])) {
            return $this->validationCache[$cacheKey];
        }

        $rules = [];

        // Map endpoints to validation rules
        switch ($endpoint) {
            case 'users':
                $rules = $method === 'POST' ? ValidationRules::userCreate() : ValidationRules::userUpdate();
                break;
            case 'projects':
                $rules = $method === 'POST' ? ValidationRules::projectCreate() : ValidationRules::projectUpdate();
                break;
            case 'tasks':
                $rules = $method === 'POST' ? ValidationRules::taskCreate() : ValidationRules::taskUpdate();
                break;
            case 'documents':
                $rules = ValidationRules::documentUpload();
                break;
            case 'search':
                $rules = ValidationRules::search();
                break;
        }

        $this->validationCache[$cacheKey] = $rules;
        return $rules;
    }

    /**
     * Sanitize data for logging (remove sensitive information)
     */
    private function sanitizeForLogging(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'secret', 'key', 'ssn', 'credit_card'];
        $sanitized = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($sanitized[$field])) {
                $sanitized[$field] = '[REDACTED]';
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize file upload
     */
    private function sanitizeFile($file): mixed
    {
        if (!$file) {
            return null;
        }

        // For file uploads, we mainly sanitize the filename
        if (method_exists($file, 'getClientOriginalName')) {
            $sanitizedFilename = $this->sanitizationService->sanitizeFileName($file->getClientOriginalName());
            // Note: We can't actually change the filename here, but we log it
            Log::info('File upload sanitized', [
                'original_filename' => $file->getClientOriginalName(),
                'sanitized_filename' => $sanitizedFilename,
            ]);
        }

        return $file;
    }

    /**
     * Get validation statistics
     */
    public function getValidationStats(): array
    {
        return [
            'cache_size' => count($this->validationCache),
            'security_patterns' => array_sum(array_map('count', $this->securityPatterns)),
            'pattern_categories' => array_keys($this->securityPatterns),
        ];
    }

    /**
     * Clear validation cache
     */
    public function clearCache(): void
    {
        $this->validationCache = [];
    }
}
