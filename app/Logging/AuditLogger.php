<?php declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Audit Logger
 * 
 * Custom logger for audit trail events with structured JSON output
 * Includes user context, request correlation, and PII redaction
 */
class AuditLogger
{
    /**
     * Create a custom Monolog instance for audit logging.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('audit');
        
        // Create handler with JSON formatting
        $handler = new StreamHandler($config['path'], $config['level']);
        $handler->setFormatter(new JsonFormatter());
        
        $logger->pushHandler($handler);
        
        // Add processors for context enrichment
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor([$this, 'addAuditContext']);
        $logger->pushProcessor([$this, 'redactPII']);
        
        return $logger;
    }

    /**
     * Add audit-specific context to log records.
     */
    public function addAuditContext(array $record): array
    {
        try {
            $request = request();
            $user = Auth::user();
            
            $record['extra']['audit'] = [
                'timestamp' => now()->toISOString(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'tenant_id' => $user?->tenant_id ?? session('user')['tenant_id'] ?? null,
                'request_id' => $request?->header('X-Request-Id'),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->header('User-Agent'),
                'route' => $request?->route()?->getName(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'session_id' => session()->getId(),
            ];
            
            // Add entity context if available
            if (isset($record['context']['entity_type'])) {
                $record['extra']['audit']['entity_type'] = $record['context']['entity_type'];
                $record['extra']['audit']['entity_id'] = $record['context']['entity_id'] ?? null;
            }
            
            // Add action context if available
            if (isset($record['context']['action'])) {
                $record['extra']['audit']['action'] = $record['context']['action'];
            }
            
        } catch (\Exception $e) {
            // Don't let logging errors break the application
            $record['extra']['audit_error'] = $e->getMessage();
        }
        
        return $record;
    }

    /**
     * Redact PII from log records.
     */
    public function redactPII(array $record): array
    {
        $redactionPatterns = config('logging.redaction.patterns', []);
        $replacement = config('logging.redaction.replacement', '[REDACTED]');
        
        if (empty($redactionPatterns)) {
            return $record;
        }
        
        // Redact sensitive data in context
        if (isset($record['context'])) {
            $record['context'] = $this->redactArray($record['context'], $redactionPatterns, $replacement);
        }
        
        // Redact sensitive data in extra
        if (isset($record['extra'])) {
            $record['extra'] = $this->redactArray($record['extra'], $redactionPatterns, $replacement);
        }
        
        return $record;
    }

    /**
     * Recursively redact sensitive data from arrays.
     */
    private function redactArray(array $data, array $patterns, string $replacement): array
    {
        foreach ($data as $key => $value) {
            // Check if key matches any redaction pattern
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $key)) {
                    $data[$key] = $replacement;
                    continue 2; // Skip to next key
                }
            }
            
            // Recursively process arrays
            if (is_array($value)) {
                $data[$key] = $this->redactArray($value, $patterns, $replacement);
            }
        }
        
        return $data;
    }
}
