<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StructuredLogger
{
    /**
     * Create a custom Monolog instance.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('structured');
        
        $handler = new StreamHandler($config['path'], $config['level']);
        $handler->setFormatter(new JsonFormatter());
        
        $logger->pushHandler($handler);
        
        // Add request context processor
        $logger->pushProcessor(function ($record) {
            return $this->addRequestContext($record);
        });
        
        return $logger;
    }

    /**
     * Add request context to log records.
     */
    private function addRequestContext(array $record): array
    {
        try {
            $request = request();
            $user = Auth::user();
        } catch (\Exception $e) {
            // If request context is not available (e.g., in CLI), use defaults
            $request = null;
            $user = null;
        }
        
        $context = [
            'timestamp' => now()->toISOString(),
            'level' => $record['level_name'],
            'message' => $record['message'],
            'context' => $record['context'],
            'extra' => array_merge($record['extra'], [
                'request_id' => $this->getRequestId($request),
                'tenant_id' => $user ? $user->tenant_id : null,
                'user_id' => $user ? $user->id : null,
                'route' => $request ? $request->route()?->getName() : null,
                'method' => $request ? $request->method() : null,
                'url' => $request ? $request->fullUrl() : null,
                'ip' => $request ? $request->ip() : null,
                'user_agent' => $request ? $request->userAgent() : null,
                'latency' => $this->getLatency(),
                'memory_usage' => memory_get_usage(true),
                'environment' => app()->environment(),
            ]),
        ];

        // Redact PII if enabled
        if (config('logging.features.pii_redaction', true)) {
            $context = $this->redactPII($context);
        }

        return $context;
    }

    /**
     * Get or generate request ID.
     */
    private function getRequestId(?Request $request): string
    {
        if (!$request) {
            return uniqid('req_', true);
        }

        $requestId = $request->header('X-Request-Id') 
            ?? $request->header('X-Correlation-Id')
            ?? session('request_id');

        if (!$requestId) {
            $requestId = uniqid('req_', true);
            session(['request_id' => $requestId]);
        }

        return $requestId;
    }

    /**
     * Calculate request latency.
     */
    private function getLatency(): ?int
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : null;
        
        if ($startTime) {
            return (int) ((microtime(true) - $startTime) * 1000);
        }

        return null;
    }

    /**
     * Redact PII from log data.
     */
    private function redactPII(array $data): array
    {
        $patterns = config('logging.redaction.patterns', []);
        $replacement = config('logging.redaction.replacement', '[REDACTED]');

        return $this->recursiveRedact($data, $patterns, $replacement);
    }

    /**
     * Recursively redact PII from nested arrays.
     */
    private function recursiveRedact(array $data, array $patterns, string $replacement): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->recursiveRedact($value, $patterns, $replacement);
            } elseif (is_string($value)) {
                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $key)) {
                        $data[$key] = $replacement;
                        break;
                    }
                }
            }
        }

        return $data;
    }
}
