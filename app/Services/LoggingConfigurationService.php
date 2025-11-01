<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Logging Configuration Service
 * 
 * Manages logging configuration and feature flags
 * Provides dynamic log level adjustment and feature toggles
 */
class LoggingConfigurationService
{
    /**
     * Feature flags for logging
     */
    private array $features = [
        'structured_logging' => true,
        'request_id_propagation' => true,
        'pii_redaction' => true,
        'performance_tracking' => true,
        'audit_logging' => true,
        'query_logging' => true,
        'error_tracking' => true,
        'security_logging' => true,
    ];

    /**
     * Log levels for different environments
     */
    private array $logLevels = [
        'local' => 'debug',
        'development' => 'debug',
        'staging' => 'info',
        'production' => 'warning',
        'testing' => 'error',
    ];

    /**
     * Initialize logging configuration
     */
    public function __construct()
    {
        $this->loadConfiguration();
        $this->applyEnvironmentSettings();
    }

    /**
     * Load configuration from config files
     */
    private function loadConfiguration(): void
    {
        $this->features = array_merge($this->features, config('logging.features', []));
    }

    /**
     * Apply environment-specific settings
     */
    private function applyEnvironmentSettings(): void
    {
        $environment = app()->environment();
        
        // Set log level based on environment
        $logLevel = $this->logLevels[$environment] ?? 'info';
        Config::set('logging.channels.single.level', $logLevel);
        Config::set('logging.channels.structured.level', $logLevel);
        
        // Disable certain features in production
        if ($environment === 'production') {
            $this->features['structured_logging'] = true; // Keep structured logging
            $this->features['performance_tracking'] = true; // Keep performance tracking
            $this->features['query_logging'] = false; // Disable detailed query logging
        }
        
        // Disable debug features in testing
        if ($environment === 'testing') {
            $this->features['query_logging'] = false;
            $this->features['performance_tracking'] = false;
        }
    }

    /**
     * Check if a logging feature is enabled
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return $this->features[$feature] ?? false;
    }

    /**
     * Enable a logging feature
     */
    public function enableFeature(string $feature): void
    {
        $this->features[$feature] = true;
        $this->updateConfig();
    }

    /**
     * Disable a logging feature
     */
    public function disableFeature(string $feature): void
    {
        $this->features[$feature] = false;
        $this->updateConfig();
    }

    /**
     * Get current log level for environment
     */
    public function getLogLevel(): string
    {
        $environment = app()->environment();
        return $this->logLevels[$environment] ?? 'info';
    }

    /**
     * Set log level for current environment
     */
    public function setLogLevel(string $level): void
    {
        $environment = app()->environment();
        $this->logLevels[$environment] = $level;
        
        Config::set('logging.channels.single.level', $level);
        Config::set('logging.channels.structured.level', $level);
    }

    /**
     * Get all enabled features
     */
    public function getEnabledFeatures(): array
    {
        return array_filter($this->features, fn($enabled) => $enabled);
    }

    /**
     * Get all disabled features
     */
    public function getDisabledFeatures(): array
    {
        return array_filter($this->features, fn($enabled) => !$enabled);
    }

    /**
     * Update configuration
     */
    private function updateConfig(): void
    {
        Config::set('logging.features', $this->features);
    }

    /**
     * Get logging statistics
     */
    public function getLoggingStats(): array
    {
        $logPath = storage_path('logs');
        $stats = [];
        
        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $stats[$filename] = [
                    'size' => filesize($file),
                    'size_human' => $this->formatBytes(filesize($file)),
                    'modified' => date('Y-m-d H:i:s', filemtime($file)),
                    'lines' => $this->countLines($file),
                ];
            }
        }
        
        return $stats;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Count lines in a file
     */
    private function countLines(string $file): int
    {
        $count = 0;
        $handle = fopen($file, 'r');
        
        if ($handle) {
            while (($line = fgets($handle)) !== false) {
                $count++;
            }
            fclose($handle);
        }
        
        return $count;
    }

    /**
     * Clean up old log files
     */
    public function cleanupLogs(int $daysToKeep = 30): array
    {
        $logPath = storage_path('logs');
        $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
        $cleaned = [];
        
        if (is_dir($logPath)) {
            $files = glob($logPath . '/*.log');
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoffTime) {
                    $cleaned[] = basename($file);
                    unlink($file);
                }
            }
        }
        
        return $cleaned;
    }

    /**
     * Get logging configuration summary
     */
    public function getConfigurationSummary(): array
    {
        return [
            'environment' => app()->environment(),
            'log_level' => $this->getLogLevel(),
            'features' => $this->features,
            'enabled_features' => $this->getEnabledFeatures(),
            'disabled_features' => $this->getDisabledFeatures(),
            'log_stats' => $this->getLoggingStats(),
        ];
    }
}
