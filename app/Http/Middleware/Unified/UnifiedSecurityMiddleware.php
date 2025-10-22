<?php declare(strict_types=1);

namespace App\Http\Middleware\Unified;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unified Security Middleware
 * 
 * Consolidates all security functionality into a single middleware
 * Replaces: EnhancedSecurityHeadersMiddleware, ProductionSecurityMiddleware,
 *           SecurityHeadersMiddleware, AdvancedSecurityMiddleware
 */
class UnifiedSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Apply security checks before processing
        $this->applySecurityChecks($request);
        
        $response = $next($request);
        
        // Apply security headers
        $this->applySecurityHeaders($response, $request);
        
        // Log security events
        $this->logSecurityEvents($request, $response);
        
        return $response;
    }

    /**
     * Apply security checks to request
     */
    protected function applySecurityChecks(Request $request): void
    {
        // Check for suspicious patterns
        $this->checkSuspiciousPatterns($request);
        
        // Validate request size
        $this->validateRequestSize($request);
        
        // Check for malicious content
        $this->checkMaliciousContent($request);
    }

    /**
     * Check for suspicious patterns in request
     */
    protected function checkSuspiciousPatterns(Request $request): void
    {
        $suspiciousPatterns = [
            '/\.\./',           // Directory traversal
            '/<script/i',       // Script injection
            '/javascript:/i',   // JavaScript protocol
            '/vbscript:/i',     // VBScript protocol
            '/onload=/i',       // Event handlers
            '/onerror=/i',      // Event handlers
            '/eval\(/i',        // Code execution
            '/exec\(/i',        // Code execution
            '/system\(/i',      // System calls
            '/shell_exec/i',    // Shell execution
        ];
        
        $requestData = $request->all();
        $requestString = json_encode($requestData);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $requestString)) {
                Log::warning('Suspicious pattern detected', [
                    'pattern' => $pattern,
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'request_id' => $request->header('X-Request-Id')
                ]);
                
                // In production, you might want to block these requests
                if (app()->environment('production')) {
                    abort(400, 'Suspicious request detected');
                }
            }
        }
    }

    /**
     * Validate request size
     */
    protected function validateRequestSize(Request $request): void
    {
        $maxSize = config('security.max_request_size', 10485760); // 10MB default
        $currentSize = strlen($request->getContent());
        
        if ($currentSize > $maxSize) {
            Log::warning('Request size exceeded', [
                'current_size' => $currentSize,
                'max_size' => $maxSize,
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'route' => $request->route()?->getName(),
                'request_id' => $request->header('X-Request-Id')
            ]);
            
            abort(413, 'Request entity too large');
        }
    }

    /**
     * Check for malicious content
     */
    protected function checkMaliciousContent(Request $request): void
    {
        $maliciousKeywords = [
            'union select', 'drop table', 'delete from', 'insert into',
            'update set', 'alter table', 'create table', 'exec(',
            'xp_cmdshell', 'sp_executesql', 'load_file', 'into outfile'
        ];
        
        $requestData = $request->all();
        $requestString = strtolower(json_encode($requestData));
        
        foreach ($maliciousKeywords as $keyword) {
            if (str_contains($requestString, $keyword)) {
                Log::warning('Malicious content detected', [
                    'keyword' => $keyword,
                    'ip' => $request->ip(),
                    'user_id' => auth()->id(),
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'request_id' => $request->header('X-Request-Id')
                ]);
                
                // In production, block these requests
                if (app()->environment('production')) {
                    abort(400, 'Malicious content detected');
                }
            }
        }
    }

    /**
     * Apply security headers to response
     */
    protected function applySecurityHeaders(Response $response, Request $request): void
    {
        $headers = $this->generateSecurityHeaders($request);
        
        foreach ($headers as $header => $value) {
            if (!empty($value)) {
                $response->headers->set($header, $value);
            }
        }
    }

    /**
     * Generate security headers based on environment and request
     */
    protected function generateSecurityHeaders(Request $request): array
    {
        $isProduction = app()->environment('production');
        $isApi = $request->is('api/*');
        
        $headers = [
            // Content Security Policy
            'Content-Security-Policy' => $this->generateCSP($request),
            
            // HTTP Strict Transport Security
            'Strict-Transport-Security' => $isProduction ? 'max-age=31536000; includeSubDomains; preload' : null,
            
            // X-Frame-Options
            'X-Frame-Options' => 'DENY',
            
            // X-Content-Type-Options
            'X-Content-Type-Options' => 'nosniff',
            
            // X-XSS-Protection
            'X-XSS-Protection' => '1; mode=block',
            
            // Referrer Policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            
            // Permissions Policy
            'Permissions-Policy' => $this->generatePermissionsPolicy(),
            
            // Cross-Origin Policies
            'Cross-Origin-Embedder-Policy' => $isProduction ? 'require-corp' : null,
            'Cross-Origin-Opener-Policy' => $isProduction ? 'same-origin' : null,
            'Cross-Origin-Resource-Policy' => $isProduction ? 'same-origin' : null,
        ];
        
        // API-specific headers
        if ($isApi) {
            $headers['X-API-Version'] = '1.0';
            $headers['X-Content-Type-Options'] = 'nosniff';
        }
        
        return array_filter($headers, fn($value) => $value !== null);
    }

    /**
     * Generate Content Security Policy
     */
    protected function generateCSP(Request $request): string
    {
        $isProduction = app()->environment('production');
        $isApi = $request->is('api/*');
        
        if ($isApi) {
            return "default-src 'none'; frame-ancestors 'none';";
        }
        
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com",
            "img-src 'self' data: https:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];
        
        if (!$isProduction) {
            $csp[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' localhost:*";
            $csp[] = "connect-src 'self' localhost:* ws: wss:";
        }
        
        return implode('; ', $csp);
    }

    /**
     * Generate Permissions Policy
     */
    protected function generatePermissionsPolicy(): string
    {
        $policies = [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
            'magnetometer=()',
            'gyroscope=()',
            'accelerometer=()',
        ];
        
        return implode(', ', $policies);
    }

    /**
     * Log security events
     */
    protected function logSecurityEvents(Request $request, Response $response): void
    {
        $securityEvents = [];
        
        // Check for security-related headers in request
        $securityHeaders = [
            'X-Forwarded-For',
            'X-Real-IP',
            'X-Forwarded-Proto',
            'User-Agent',
            'Referer',
        ];
        
        foreach ($securityHeaders as $header) {
            if ($request->hasHeader($header)) {
                $securityEvents[$header] = $request->header($header);
            }
        }
        
        // Log if there are security events
        if (!empty($securityEvents)) {
            Log::info('Security event logged', [
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'security_events' => $securityEvents,
                'request_id' => $request->header('X-Request-Id')
            ]);
        }
    }
}
