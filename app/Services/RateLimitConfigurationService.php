<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Rate Limiting Configuration Service
 * 
 * Manages dynamic rate limiting configurations with:
 * - Environment-based configurations
 * - User role-based adjustments
 * - Endpoint-specific rules
 * - Automatic scaling based on system load
 */
class RateLimitConfigurationService
{
    private array $defaultConfigs = [
        'auth' => [
            'requests_per_minute' => 10,
            'burst_limit' => 20,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 2.0, // Penalty for failed attempts
            'success_reduction' => 0.1,   // Reduce limits after successful auth
        ],
        'api' => [
            'requests_per_minute' => 100,
            'burst_limit' => 200,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.5,
            'success_reduction' => 0.05,
        ],
        'upload' => [
            'requests_per_minute' => 5,
            'burst_limit' => 10,
            'window_size' => 60,
            'strategy' => 'token_bucket',
            'penalty_multiplier' => 3.0,
            'success_reduction' => 0.2,
        ],
        'admin' => [
            'requests_per_minute' => 500,
            'burst_limit' => 1000,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.2,
            'success_reduction' => 0.02,
        ],
        'public' => [
            'requests_per_minute' => 30,
            'burst_limit' => 50,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 2.5,
            'success_reduction' => 0.15,
        ],
        'default' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
            'window_size' => 60,
            'strategy' => 'sliding_window',
            'penalty_multiplier' => 1.8,
            'success_reduction' => 0.08,
        ],
    ];
    
    private array $roleMultipliers = [
        'super_admin' => 2.0,
        'admin' => 1.5,
        'pm' => 1.2,
        'member' => 1.0,
        'client' => 0.8,
        'guest' => 0.5,
    ];
    
    private array $endpointMultipliers = [
        'auth' => 0.5,
        'upload' => 0.3,
        'api' => 1.0,
        'admin' => 1.5,
        'public' => 0.7,
        'default' => 1.0,
    ];
    
    /**
     * Get configuration for specific endpoint and context
     */
    public function getConfig(string $endpoint, array $context = []): array
    {
        $baseConfig = $this->getBaseConfig($endpoint);
        
        // If no context provided, return base config without multipliers
        if (empty($context)) {
            return $baseConfig;
        }
        
        $userRole = $context['user_role'] ?? 'guest';
        $isAuthenticated = $context['is_authenticated'] ?? false;
        $systemLoad = $context['system_load'] ?? 1.0;
        
        // Apply role multiplier
        $roleMultiplier = $this->roleMultipliers[$userRole] ?? 1.0;
        $baseConfig['requests_per_minute'] = (int) ($baseConfig['requests_per_minute'] * $roleMultiplier);
        $baseConfig['burst_limit'] = (int) ($baseConfig['burst_limit'] * $roleMultiplier);
        
        // Apply endpoint multiplier
        $endpointMultiplier = $this->endpointMultipliers[$endpoint] ?? 1.0;
        $baseConfig['requests_per_minute'] = (int) ($baseConfig['requests_per_minute'] * $endpointMultiplier);
        $baseConfig['burst_limit'] = (int) ($baseConfig['burst_limit'] * $endpointMultiplier);
        
        // Apply system load adjustment
        $loadMultiplier = $this->calculateLoadMultiplier($systemLoad);
        $baseConfig['requests_per_minute'] = (int) ($baseConfig['requests_per_minute'] * $loadMultiplier);
        $baseConfig['burst_limit'] = (int) ($baseConfig['burst_limit'] * $loadMultiplier);
        
        // Apply authentication bonus
        if ($isAuthenticated) {
            $baseConfig['requests_per_minute'] = (int) ($baseConfig['requests_per_minute'] * 1.2);
            $baseConfig['burst_limit'] = (int) ($baseConfig['burst_limit'] * 1.2);
        }
        
        // Apply time-based adjustments
        $timeMultiplier = $this->getTimeBasedMultiplier();
        $baseConfig['requests_per_minute'] = (int) ($baseConfig['requests_per_minute'] * $timeMultiplier);
        $baseConfig['burst_limit'] = (int) ($baseConfig['burst_limit'] * $timeMultiplier);
        
        // Ensure minimum values
        $baseConfig['requests_per_minute'] = max(1, $baseConfig['requests_per_minute']);
        $baseConfig['burst_limit'] = max(1, $baseConfig['burst_limit']);
        
        return $baseConfig;
    }
    
    /**
     * Get base configuration for endpoint
     */
    private function getBaseConfig(string $endpoint): array
    {
        $configKey = "rate_limit_config:{$endpoint}";
        $cachedConfig = Cache::get($configKey);
        
        if ($cachedConfig) {
            return array_merge($this->defaultConfigs[$endpoint] ?? $this->defaultConfigs['default'], $cachedConfig);
        }
        
        return $this->defaultConfigs[$endpoint] ?? $this->defaultConfigs['default'];
    }
    
    /**
     * Calculate load multiplier based on system performance
     */
    private function calculateLoadMultiplier(float $systemLoad): float
    {
        // systemLoad: 0.0 = no load, 1.0 = normal load, >1.0 = high load
        if ($systemLoad <= 0.5) {
            return 1.2; // Increase limits when system is idle
        } elseif ($systemLoad <= 1.0) {
            return 1.0; // Normal limits
        } elseif ($systemLoad <= 1.5) {
            return 0.8; // Reduce limits under moderate load
        } else {
            return 0.6; // Significantly reduce limits under high load
        }
    }
    
    /**
     * Get time-based multiplier (peak hours vs off-peak)
     */
    private function getTimeBasedMultiplier(): float
    {
        $hour = (int) date('H');
        
        // Peak hours (9 AM - 5 PM)
        if ($hour >= 9 && $hour <= 17) {
            return 0.9; // Slightly reduce during business hours
        }
        
        // Off-peak hours
        return 1.1; // Slightly increase during off-peak
    }
    
    /**
     * Update configuration for specific endpoint
     */
    public function updateConfig(string $endpoint, array $config): bool
    {
        $configKey = "rate_limit_config:{$endpoint}";
        Cache::put($configKey, $config, 3600); // Cache for 1 hour
        
        Log::info('Rate limit configuration updated', [
            'endpoint' => $endpoint,
            'config' => $config,
        ]);
        
        return true;
    }
    
    /**
     * Get penalty configuration for failed requests
     */
    public function getPenaltyConfig(string $endpoint): array
    {
        $baseConfig = $this->getBaseConfig($endpoint);
        
        return [
            'multiplier' => $baseConfig['penalty_multiplier'] ?? 2.0,
            'duration' => 300, // 5 minutes penalty duration
            'escalation_factor' => 1.5, // Increase penalty for repeated violations
        ];
    }
    
    /**
     * Get success reduction configuration
     */
    public function getSuccessReductionConfig(string $endpoint): array
    {
        $baseConfig = $this->getBaseConfig($endpoint);
        
        return [
            'reduction_factor' => $baseConfig['success_reduction'] ?? 0.1,
            'duration' => 60, // 1 minute reduction duration
            'max_reduction' => 0.5, // Maximum 50% reduction
        ];
    }
    
    /**
     * Get all configurations for monitoring
     */
    public function getAllConfigs(): array
    {
        $configs = [];
        
        foreach (array_keys($this->defaultConfigs) as $endpoint) {
            $configs[$endpoint] = $this->getBaseConfig($endpoint);
        }
        
        return $configs;
    }
    
    /**
     * Reset configuration to defaults
     */
    public function resetConfig(string $endpoint): bool
    {
        $configKey = "rate_limit_config:{$endpoint}";
        Cache::forget($configKey);
        
        Log::info('Rate limit configuration reset to defaults', [
            'endpoint' => $endpoint,
        ]);
        
        return true;
    }
    
    /**
     * Get configuration statistics
     */
    public function getConfigStats(): array
    {
        $stats = [
            'total_endpoints' => count($this->defaultConfigs),
            'total_strategies' => count(array_unique(array_column($this->defaultConfigs, 'strategy'))),
            'role_multipliers' => $this->roleMultipliers,
            'endpoint_multipliers' => $this->endpointMultipliers,
            'configurations' => $this->getAllConfigs(),
        ];
        
        return $stats;
    }
    
    /**
     * Validate configuration
     */
    public function validateConfig(array $config): array
    {
        $errors = [];
        
        if (!isset($config['requests_per_minute']) || $config['requests_per_minute'] <= 0) {
            $errors[] = 'requests_per_minute must be a positive integer';
        }
        
        if (!isset($config['burst_limit']) || $config['burst_limit'] <= 0) {
            $errors[] = 'burst_limit must be a positive integer';
        }
        
        if (!isset($config['window_size']) || $config['window_size'] <= 0) {
            $errors[] = 'window_size must be a positive integer';
        }
        
        $validStrategies = ['sliding_window', 'token_bucket', 'fixed_window'];
        if (!isset($config['strategy']) || !in_array($config['strategy'], $validStrategies)) {
            $errors[] = 'strategy must be one of: ' . implode(', ', $validStrategies);
        }
        
        if ($config['burst_limit'] < $config['requests_per_minute']) {
            $errors[] = 'burst_limit should be greater than or equal to requests_per_minute';
        }
        
        return $errors;
    }
}
