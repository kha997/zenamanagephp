<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, array $data = []): void
    {
        $this->log('security', $event, $data);
    }

    /**
     * Log authentication events
     */
    public function logAuthEvent(string $event, array $data = []): void
    {
        $this->log('auth', $event, $data);
    }

    /**
     * Log data access events
     */
    public function logDataAccess(string $resource, string $action, array $data = []): void
    {
        $this->log('data_access', $action, array_merge([
            'resource' => $resource,
        ], $data));
    }

    /**
     * Log administrative actions
     */
    public function logAdminAction(string $action, array $data = []): void
    {
        $this->log('admin', $action, $data);
    }

    /**
     * Log system events
     */
    public function logSystemEvent(string $event, array $data = []): void
    {
        $this->log('system', $event, $data);
    }

    /**
     * Log user actions
     */
    public function logUserAction(string $action, array $data = []): void
    {
        $this->log('user', $action, $data);
    }

    /**
     * Log API access
     */
    public function logApiAccess(string $endpoint, string $method, array $data = []): void
    {
        $this->log('api_access', 'request', array_merge([
            'endpoint' => $endpoint,
            'method' => $method,
        ], $data));
    }

    /**
     * Log failed attempts
     */
    public function logFailedAttempt(string $type, array $data = []): void
    {
        $this->log('failed_attempt', $type, $data);
    }

    /**
     * Log configuration changes
     */
    public function logConfigChange(string $config, string $oldValue, string $newValue): void
    {
        $this->log('config_change', 'update', [
            'config' => $config,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /**
     * Log permission changes
     */
    public function logPermissionChange(string $user, string $permission, bool $granted): void
    {
        $this->log('permission_change', 'update', [
            'target_user' => $user,
            'permission' => $permission,
            'granted' => $granted,
        ]);
    }

    /**
     * Core logging method
     */
    private function log(string $category, string $event, array $data = []): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'category' => $category,
            'event' => $event,
            'user_id' => Auth::id(),
            'user_email' => Auth::user()?->email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'request_id' => request()->header('X-Request-Id'),
            'data' => $this->sanitizeData($data),
        ];

        // Log to different channels based on category
        switch ($category) {
            case 'security':
            case 'auth':
            case 'failed_attempt':
                Log::channel('security')->info('Audit Log', $logData);
                break;
            case 'admin':
            case 'permission_change':
            case 'config_change':
                Log::channel('admin')->info('Audit Log', $logData);
                break;
            case 'data_access':
                Log::channel('data')->info('Audit Log', $logData);
                break;
            case 'api_access':
                Log::channel('api')->info('Audit Log', $logData);
                break;
            default:
                Log::info('Audit Log', $logData);
        }
    }

    /**
     * Sanitize sensitive data
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'secret', 'key',
            'api_key', 'access_token', 'refresh_token', 'ssn', 'credit_card'
        ];

        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }

        return $data;
    }

    /**
     * Get audit logs for a specific user
     */
    public function getUserAuditLogs(int $userId, int $limit = 100): array
    {
        // This would typically query a database or log storage
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Get audit logs for a specific category
     */
    public function getCategoryAuditLogs(string $category, int $limit = 100): array
    {
        // This would typically query a database or log storage
        // For now, return empty array as placeholder
        return [];
    }

    /**
     * Export audit logs
     */
    public function exportAuditLogs(string $category = null, string $startDate = null, string $endDate = null): array
    {
        // This would typically generate a CSV or JSON export
        // For now, return empty array as placeholder
        return [];
    }
}
