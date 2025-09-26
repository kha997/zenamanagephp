<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\InputSanitizationService;

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
            
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input detected',
                'code' => 'SUSPICIOUS_INPUT'
            ], 400);
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
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION|SCRIPT)\b)/i',
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
            '/\b(cat|ls|pwd|whoami|id|uname|ps|netstat|ifconfig)\b/i',
            
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
            $request->request->all(),
            $request->headers->all()
        );

        foreach ($allInput as $key => $value) {
            $inputString = is_array($value) ? json_encode($value) : (string)$value;
            
            foreach ($suspiciousPatterns as $pattern) {
                if (preg_match($pattern, $inputString)) {
                    return true;
                }
            }
        }

        return false;
    }
}