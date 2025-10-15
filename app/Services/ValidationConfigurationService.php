<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

/**
 * Validation Configuration Service
 * 
 * Manages validation configuration and settings
 * Provides dynamic validation rule management and caching
 */
class ValidationConfigurationService
{
    /**
     * Validation settings
     */
    private array $settings = [
        'enable_security_validation' => true,
        'enable_input_sanitization' => true,
        'enable_file_validation' => true,
        'enable_bulk_validation' => true,
        'max_bulk_items' => 1000,
        'max_file_size_kb' => 10240,
        'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'txt'],
        'validation_cache_ttl' => 3600, // 1 hour
        'strict_mode' => false,
        'log_validation_failures' => true,
        'log_security_violations' => true,
    ];

    /**
     * Custom validation rules
     */
    private array $customRules = [];

    /**
     * Validation rule cache
     */
    private array $ruleCache = [];

    /**
     * Initialize validation configuration
     */
    public function __construct()
    {
        $this->loadConfiguration();
        $this->loadCustomRules();
    }

    /**
     * Load configuration from config files
     */
    private function loadConfiguration(): void
    {
        $configSettings = config('validation.settings', []);
        $this->settings = array_merge($this->settings, $configSettings);
    }

    /**
     * Load custom validation rules
     */
    private function loadCustomRules(): void
    {
        $this->customRules = config('validation.custom_rules', []);
    }

    /**
     * Get validation setting
     */
    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set validation setting
     */
    public function setSetting(string $key, mixed $value): void
    {
        $this->settings[$key] = $value;
        $this->updateConfig();
    }

    /**
     * Check if validation feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return $this->settings[$feature] ?? false;
    }

    /**
     * Enable validation feature
     */
    public function enableFeature(string $feature): void
    {
        $this->settings[$feature] = true;
        $this->updateConfig();
    }

    /**
     * Disable validation feature
     */
    public function disableFeature(string $feature): void
    {
        $this->settings[$feature] = false;
        $this->updateConfig();
    }

    /**
     * Get custom validation rule
     */
    public function getCustomRule(string $name): ?array
    {
        return $this->customRules[$name] ?? null;
    }

    /**
     * Set custom validation rule
     */
    public function setCustomRule(string $name, array $rules): void
    {
        $this->customRules[$name] = $rules;
        $this->updateConfig();
    }

    /**
     * Get validation rules for endpoint
     */
    public function getValidationRules(string $endpoint, string $method = 'POST'): array
    {
        $cacheKey = "validation_rules:{$method}:{$endpoint}";
        
        if (isset($this->ruleCache[$cacheKey])) {
            return $this->ruleCache[$cacheKey];
        }

        $rules = $this->buildValidationRules($endpoint, $method);
        
        // Cache rules if caching is enabled
        if ($this->getSetting('validation_cache_ttl', 0) > 0) {
            $this->ruleCache[$cacheKey] = $rules;
        }

        return $rules;
    }

    /**
     * Build validation rules for endpoint
     */
    private function buildValidationRules(string $endpoint, string $method): array
    {
        $rules = [];

        // Get base rules for endpoint
        $baseRules = $this->getBaseRules($endpoint, $method);
        $rules = array_merge($rules, $baseRules);

        // Apply custom rules
        $customRules = $this->getCustomRules($endpoint);
        $rules = array_merge($rules, $customRules);

        // Apply security rules if enabled
        if ($this->isFeatureEnabled('enable_security_validation')) {
            $securityRules = $this->getSecurityRules($endpoint);
            $rules = array_merge($rules, $securityRules);
        }

        return $rules;
    }

    /**
     * Get base validation rules
     */
    private function getBaseRules(string $endpoint, string $method): array
    {
        $rulesMap = [
            'users' => [
                'POST' => [
                    'name' => 'required|string|min:2|max:255',
                    'email' => 'required|email:rfc,dns|max:255',
                    'password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                    'phone' => 'nullable|string|max:20|regex:/^[\+]?[1-9][\d]{0,15}$/',
                ],
                'PUT' => [
                    'name' => 'nullable|string|min:2|max:255',
                    'email' => 'nullable|email:rfc,dns|max:255',
                    'phone' => 'nullable|string|max:20|regex:/^[\+]?[1-9][\d]{0,15}$/',
                ],
            ],
            'projects' => [
                'POST' => [
                    'name' => 'required|string|min:2|max:255',
                    'description' => 'required|string|min:10|max:1000',
                    'status' => 'nullable|string|in:planning,active,completed,cancelled',
                    'priority' => 'nullable|string|in:low,medium,high,urgent',
                ],
                'PUT' => [
                    'name' => 'nullable|string|min:2|max:255',
                    'description' => 'nullable|string|min:10|max:1000',
                    'status' => 'nullable|string|in:planning,active,completed,cancelled',
                    'priority' => 'nullable|string|in:low,medium,high,urgent',
                ],
            ],
            'tasks' => [
                'POST' => [
                    'title' => 'required|string|min:2|max:255',
                    'description' => 'required|string|min:10|max:1000',
                    'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
                    'priority' => 'nullable|string|in:low,medium,high,urgent',
                ],
                'PUT' => [
                    'title' => 'nullable|string|min:2|max:255',
                    'description' => 'nullable|string|min:10|max:1000',
                    'status' => 'nullable|string|in:pending,in_progress,completed,cancelled',
                    'priority' => 'nullable|string|in:low,medium,high,urgent',
                ],
            ],
        ];

        return $rulesMap[$endpoint][$method] ?? [];
    }

    /**
     * Get custom validation rules
     */
    private function getCustomRules(string $endpoint): array
    {
        return $this->customRules[$endpoint] ?? [];
    }

    /**
     * Get security validation rules
     */
    private function getSecurityRules(string $endpoint): array
    {
        $securityRules = [];

        // Add security rules for all text fields
        $textFields = ['name', 'title', 'description', 'content', 'comment'];
        foreach ($textFields as $field) {
            $securityRules[$field] = 'string|max:10000'; // Prevent DoS
        }

        // Add security rules for file uploads
        if ($this->isFeatureEnabled('enable_file_validation')) {
            $securityRules['file'] = 'file|max:' . $this->getSetting('max_file_size_kb', 10240);
        }

        return $securityRules;
    }

    /**
     * Get file validation settings
     */
    public function getFileValidationSettings(): array
    {
        return [
            'max_size_kb' => $this->getSetting('max_file_size_kb', 10240),
            'allowed_types' => $this->getSetting('allowed_file_types', ['jpg', 'jpeg', 'png', 'pdf']),
            'enable_validation' => $this->isFeatureEnabled('enable_file_validation'),
        ];
    }

    /**
     * Get bulk validation settings
     */
    public function getBulkValidationSettings(): array
    {
        return [
            'max_items' => $this->getSetting('max_bulk_items', 1000),
            'enable_validation' => $this->isFeatureEnabled('enable_bulk_validation'),
        ];
    }

    /**
     * Get security validation settings
     */
    public function getSecurityValidationSettings(): array
    {
        return [
            'enable_validation' => $this->isFeatureEnabled('enable_security_validation'),
            'strict_mode' => $this->getSetting('strict_mode', false),
            'log_violations' => $this->isFeatureEnabled('log_security_violations'),
        ];
    }

    /**
     * Update configuration
     */
    private function updateConfig(): void
    {
        Config::set('validation.settings', $this->settings);
        Config::set('validation.custom_rules', $this->customRules);
    }

    /**
     * Clear validation cache
     */
    public function clearCache(): void
    {
        $this->ruleCache = [];
        
        // Clear Laravel cache if using cache driver
        if ($this->getSetting('validation_cache_ttl', 0) > 0) {
            Cache::tags(['validation'])->flush();
        }
    }

    /**
     * Get validation statistics
     */
    public function getValidationStats(): array
    {
        return [
            'settings' => $this->settings,
            'custom_rules_count' => count($this->customRules),
            'cached_rules_count' => count($this->ruleCache),
            'features_enabled' => array_filter($this->settings, fn($value) => $value === true),
            'features_disabled' => array_filter($this->settings, fn($value) => $value === false),
        ];
    }

    /**
     * Validate configuration
     */
    public function validateConfiguration(): array
    {
        $errors = [];

        // Validate settings
        if ($this->getSetting('max_bulk_items', 0) <= 0) {
            $errors[] = 'max_bulk_items must be greater than 0';
        }

        if ($this->getSetting('max_file_size_kb', 0) <= 0) {
            $errors[] = 'max_file_size_kb must be greater than 0';
        }

        if ($this->getSetting('validation_cache_ttl', 0) < 0) {
            $errors[] = 'validation_cache_ttl must be non-negative';
        }

        // Validate custom rules
        foreach ($this->customRules as $name => $rules) {
            if (!is_array($rules)) {
                $errors[] = "Custom rule '{$name}' must be an array";
            }
        }

        return $errors;
    }

    /**
     * Get validation configuration summary
     */
    public function getConfigurationSummary(): array
    {
        return [
            'settings' => $this->settings,
            'custom_rules' => $this->customRules,
            'file_settings' => $this->getFileValidationSettings(),
            'bulk_settings' => $this->getBulkValidationSettings(),
            'security_settings' => $this->getSecurityValidationSettings(),
            'validation_errors' => $this->validateConfiguration(),
            'stats' => $this->getValidationStats(),
        ];
    }
}
