<?php declare(strict_types=1);

namespace App\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\Eloquent\Model;

/**
 * AuditableTrait
 * 
 * Provides audit logging and event firing capabilities for services and controllers
 */
trait AuditableTrait
{
    /**
     * Log activity with structured data
     */
    protected function logActivity(
        string $action,
        array $data = [],
        ?Model $model = null,
        ?string $level = 'info'
    ): void {
        $logData = [
            'action' => $action,
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-Id'),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'data' => $this->sanitizeData($data)
        ];

        if ($model) {
            $logData['model'] = [
                'type' => get_class($model),
                'id' => $model->getKey(),
                'changes' => $model->wasRecentlyCreated ? 'created' : ($model->getChanges() ?: 'no_changes')
            ];
        }

        Log::channel('audit')->{$level}('Activity logged', $logData);
    }

    /**
     * Fire event with structured data
     */
    protected function fireEvent(
        string $eventName,
        array $data = [],
        ?Model $model = null
    ): void {
        $eventData = [
            'event' => $eventName,
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-Id'),
            'data' => $this->sanitizeData($data)
        ];

        if ($model) {
            $eventData['model'] = [
                'type' => get_class($model),
                'id' => $model->getKey()
            ];
        }

        Event::dispatch($eventName, $eventData);
    }

    /**
     * Log error with context
     */
    protected function logError(
        string $message,
        \Throwable $exception = null,
        array $context = []
    ): void {
        $logData = [
            'message' => $message,
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-Id'),
            'context' => $this->sanitizeData($context)
        ];

        if ($exception) {
            $logData['exception'] = [
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }

        Log::channel('audit')->error('Error logged', $logData);
    }

    /**
     * Log performance metrics
     */
    protected function logPerformance(
        string $operation,
        float $duration,
        array $metrics = []
    ): void {
        $logData = [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'timestamp' => now()->toISOString(),
            'request_id' => request()->header('X-Request-Id'),
            'metrics' => $this->sanitizeData($metrics)
        ];

        Log::channel('audit')->info('Performance logged', $logData);
    }

    /**
     * Sanitize sensitive data before logging
     */
    protected function sanitizeData(array $data): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'token', 'secret', 'key',
            'api_key', 'access_token', 'refresh_token', 'ssn', 'credit_card',
            'bank_account', 'pin', 'otp', 'verification_code'
        ];

        return $this->recursiveSanitize($data, $sensitiveKeys);
    }

    /**
     * Recursively sanitize nested arrays
     */
    private function recursiveSanitize(array $data, array $sensitiveKeys): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveSanitize($value, $sensitiveKeys);
            } elseif (is_string($key) && in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Get audit context for current request
     */
    protected function getAuditContext(): array
    {
        return [
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'request_id' => request()->header('X-Request-Id'),
            'route' => request()->route()?->getName(),
            'method' => request()->method(),
            'url' => request()->url(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Log CRUD operations
     */
    protected function logCrudOperation(
        string $operation,
        Model $model,
        array $additionalData = []
    ): void {
        $this->logActivity(
            "model.{$operation}",
            array_merge([
                'model_type' => get_class($model),
                'model_id' => $model->getKey(),
                'operation' => $operation
            ], $additionalData),
            $model
        );

        $this->fireEvent(
            "model.{$operation}",
            array_merge([
                'model_type' => get_class($model),
                'model_id' => $model->getKey()
            ], $additionalData),
            $model
        );
    }

    /**
     * Log bulk operations
     */
    protected function logBulkOperation(
        string $operation,
        string $modelType,
        int $count,
        array $additionalData = []
    ): void {
        $this->logActivity(
            "bulk.{$operation}",
            array_merge([
                'model_type' => $modelType,
                'count' => $count,
                'operation' => $operation
            ], $additionalData)
        );

        $this->fireEvent(
            "bulk.{$operation}",
            array_merge([
                'model_type' => $modelType,
                'count' => $count
            ], $additionalData)
        );
    }

    /**
     * Log permission checks
     */
    protected function logPermissionCheck(
        string $permission,
        bool $granted,
        ?string $resource = null
    ): void {
        $this->logActivity(
            'permission.check',
            [
                'permission' => $permission,
                'granted' => $granted,
                'resource' => $resource
            ],
            level: $granted ? 'info' : 'warning'
        );
    }

    /**
     * Log tenant operations
     */
    protected function logTenantOperation(
        string $operation,
        array $data = []
    ): void {
        $this->logActivity(
            "tenant.{$operation}",
            array_merge([
                'tenant_id' => Auth::user()?->tenant_id,
                'operation' => $operation
            ], $data)
        );
    }
}
