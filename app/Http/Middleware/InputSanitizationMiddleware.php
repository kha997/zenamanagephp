<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\InputSanitizationService;
use App\Services\ErrorEnvelopeService;

/**
 * Input Sanitization Middleware
 * 
 * Sanitizes and validates all input data for security
 */
class InputSanitizationMiddleware
{
    private InputSanitizationService $sanitizationService;

    public function __construct(InputSanitizationService $sanitizationService)
    {
        $this->sanitizationService = $sanitizationService;
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
        // Sanitize input data using the service
        $this->sanitizeInput($request);
        
        // Check for suspicious patterns
        if ($this->detectSuspiciousInput($request)) {
            Log::warning('Suspicious input detected', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
                'method' => $request->method()
            ]);
            
            return ErrorEnvelopeService::error(
                'SUSPICIOUS_INPUT',
                'Invalid input detected',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        return $next($request);
    }

    /**
     * Sanitize input data
     */
    private function sanitizeInput(Request $request): void
    {
        // Sanitize query parameters
        $query = $request->query->all();
        $sanitizedQuery = $this->sanitizationService->sanitizeArray($query);
        $request->query->replace($sanitizedQuery);

        // Sanitize request data
        $data = $request->all();
        $sanitizedData = $this->sanitizationService->sanitizeArray($data);
        
        // Replace request data
        foreach ($sanitizedData as $key => $value) {
            $request->merge([$key => $value]);
        }
    }

    /**
     * Detect suspicious input patterns
     */
    private function detectSuspiciousInput(Request $request): bool
    {
        $suspiciousPatterns = [
            // SQL Injection patterns
            '/(\b(SELECT\s+.+\s+FROM|INSERT\s+INTO|UPDATE\s+\w+\s+SET|DELETE\s+FROM|DROP\s+TABLE|CREATE\s+TABLE|ALTER\s+TABLE|UNION\s+SELECT|EXEC\s*\()\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+\'\s*=\s*\')/i',
            '/(\b(OR|AND)\s+\"\s*=\s*\")/i',
            
            // XSS patterns
            '/<script[^>]*>.*?<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>.*?<\/iframe>/i',
            '/<object[^>]*>.*?<\/object>/i',
            '/<embed[^>]*>.*?<\/embed>/i',
            
            // Command injection patterns
            '/[;&|`$()]/',
            '/\b(cat|ls|pwd|whoami|uname|ps|netstat|ifconfig)\b/i',
            
            // Path traversal patterns
            '/\.\.\//',
            '/\.\.\\\\/',
            '/%2e%2e%2f/i',
            '/%2e%2e%5c/i',
            
            // LDAP injection patterns
            '/[()=*!&|]/',
            
            // NoSQL injection patterns
            '/\$where/i',
            '/\$ne/i',
            '/\$gt/i',
            '/\$lt/i',
            '/\$regex/i'
        ];

        $allInput = array_merge(
            $request->query->all(),
            $request->request->all()
        );

        foreach ($allInput as $key => $value) {
            $inputString = $this->normalizeInputForInspection($value);

            if ($inputString === null) {
                continue;
            }
            
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $inputString)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Convert request input to a safe string representation for pattern checks.
     */
    private function normalizeInputForInspection(mixed $value): ?string
    {
        if (is_array($value)) {
            $encoded = json_encode($value);

            return is_string($encoded) ? $encoded : null;
        }

        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        // Skip objects/resources (e.g. UploadedFile) to avoid casting crashes.
        return null;
    }
}
