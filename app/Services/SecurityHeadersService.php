<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\ComprehensiveLoggingService;

/**
 * Security Headers Service
 * 
 * Provides comprehensive security headers management with:
 * - Dynamic CSP generation based on environment and features
 * - Advanced security policies
 * - Environment-specific configurations
 * - Security monitoring and reporting
 */
class SecurityHeadersService
{
    private ComprehensiveLoggingService $loggingService;
    
    public function __construct(ComprehensiveLoggingService $loggingService)
    {
        $this->loggingService = $loggingService;
    }
    
    /**
     * Generate comprehensive security headers for a request
     */
    public function generateSecurityHeaders(Request $request): array
    {
        $headers = [];
        
        // Content Security Policy
        $headers['Content-Security-Policy'] = $this->generateCSP($request);
        $headers['Content-Security-Policy-Report-Only'] = $this->generateCSPReportOnly($request);
        
        // Basic security headers
        $headers['X-Content-Type-Options'] = 'nosniff';
        $headers['X-Frame-Options'] = $this->getFrameOptions($request);
        $headers['X-XSS-Protection'] = '1; mode=block';
        $headers['Referrer-Policy'] = $this->getReferrerPolicy($request);
        
        // Modern security headers
        $headers['Permissions-Policy'] = $this->generatePermissionsPolicy($request);
        $headers['Cross-Origin-Embedder-Policy'] = $this->getCOEP($request);
        $headers['Cross-Origin-Opener-Policy'] = $this->getCOOP($request);
        $headers['Cross-Origin-Resource-Policy'] = $this->getCORP($request);
        
        // HSTS (only for HTTPS)
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = $this->getHSTS($request);
        }
        
        // Cache control for sensitive pages
        $cacheHeaders = $this->getCacheControlHeaders($request);
        $headers = array_merge($headers, $cacheHeaders);
        
        // Additional security headers
        $headers['X-Permitted-Cross-Domain-Policies'] = 'none';
        $headers['X-Download-Options'] = 'noopen';
        $headers['X-DNS-Prefetch-Control'] = 'off';
        
        // Feature Policy (legacy, for older browsers)
        $headers['Feature-Policy'] = $this->generateFeaturePolicy($request);
        
        return $headers;
    }
    
    /**
     * Generate Content Security Policy
     */
    private function generateCSP(Request $request): string
    {
        $environment = app()->environment();
        $isDevelopment = $environment === 'local' || $environment === 'testing';
        
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' https://fonts.gstatic.com",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "upgrade-insecure-requests"
        ];
        
        // Add development-specific sources
        if ($isDevelopment) {
            $csp[1] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com localhost:*";
            $csp[2] = "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net";
            $csp[5] = "connect-src 'self' localhost:* ws://localhost:* wss://localhost:*";
        } else {
            // Production CSP - more restrictive
            $csp[1] = "script-src 'self' 'nonce-" . $this->generateNonce() . "'";
            $csp[2] = "style-src 'self' 'nonce-" . $this->generateNonce() . "'";
        }
        
        // Add CDN sources for production
        if (!$isDevelopment) {
            $csp[1] .= " https://cdn.jsdelivr.net https://cdnjs.cloudflare.com";
            $csp[2] .= " https://fonts.googleapis.com";
        }
        
        return implode('; ', $csp);
    }
    
    /**
     * Generate CSP Report-Only policy for monitoring
     */
    private function generateCSPReportOnly(Request $request): string
    {
        $environment = app()->environment();
        
        if ($environment === 'production') {
            return "default-src 'self'; report-uri /_security/csp-report; report-to csp-endpoint";
        }
        
        return '';
    }
    
    /**
     * Get frame options based on request context
     */
    private function getFrameOptions(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // Allow embedding for specific public pages
        if (str_starts_with($path, '/embed/') || str_starts_with($path, '/widget/')) {
            return 'SAMEORIGIN';
        }
        
        return 'DENY';
    }
    
    /**
     * Get referrer policy based on request context
     */
    private function getReferrerPolicy(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // More permissive for public pages
        if (str_starts_with($path, '/public/') || str_starts_with($path, '/embed/')) {
            return 'strict-origin-when-cross-origin';
        }
        
        return 'strict-origin-when-cross-origin';
    }
    
    /**
     * Generate Permissions Policy
     */
    private function generatePermissionsPolicy(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // Default restrictive policy
        $permissions = [
            'camera' => '()',
            'microphone' => '()',
            'geolocation' => '()',
            'payment' => '()',
            'usb' => '()',
            'magnetometer' => '()',
            'gyroscope' => '()',
            'accelerometer' => '()',
            'ambient-light-sensor' => '()',
            'autoplay' => '()',
            'battery' => '()',
            'bluetooth' => '()',
            'display-capture' => '()',
            'document-domain' => '()',
            'encrypted-media' => '()',
            'fullscreen' => '()',
            'gamepad' => '()',
            'midi' => '()',
            'notifications' => '()',
            'picture-in-picture' => '()',
            'publickey-credentials-get' => '()',
            'screen-wake-lock' => '()',
            'sync-xhr' => '()',
            'web-share' => '()',
            'xr-spatial-tracking' => '()'
        ];
        
        // Allow specific permissions for admin pages
        if (str_starts_with($path, '/admin/')) {
            $permissions['fullscreen'] = '(self)';
            $permissions['notifications'] = '(self)';
        }
        
        // Allow camera/microphone for video conferencing pages
        if (str_starts_with($path, '/app/meetings/') || str_starts_with($path, '/app/video/')) {
            $permissions['camera'] = '(self)';
            $permissions['microphone'] = '(self)';
        }
        
        return implode(', ', array_map(function($permission, $value) {
            return "{$permission}={$value}";
        }, array_keys($permissions), $permissions));
    }
    
    /**
     * Get Cross-Origin Embedder Policy
     */
    private function getCOEP(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // More permissive for public pages
        if (str_starts_with($path, '/public/') || str_starts_with($path, '/embed/')) {
            return 'credentialless';
        }
        
        return 'require-corp';
    }
    
    /**
     * Get Cross-Origin Opener Policy
     */
    private function getCOOP(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // More permissive for public pages
        if (str_starts_with($path, '/public/') || str_starts_with($path, '/embed/')) {
            return 'unsafe-none';
        }
        
        return 'same-origin';
    }
    
    /**
     * Get Cross-Origin Resource Policy
     */
    private function getCORP(Request $request): string
    {
        $path = $request->getPathInfo();
        
        // More permissive for public pages
        if (str_starts_with($path, '/public/') || str_starts_with($path, '/embed/')) {
            return 'cross-origin';
        }
        
        return 'same-origin';
    }
    
    /**
     * Get HSTS header
     */
    private function getHSTS(Request $request): string
    {
        $environment = app()->environment();
        
        if ($environment === 'production') {
            return 'max-age=31536000; includeSubDomains; preload';
        }
        
        return 'max-age=86400'; // 1 day for non-production
    }
    
    /**
     * Get cache control headers for sensitive pages
     */
    private function getCacheControlHeaders(Request $request): array
    {
        $headers = [];
        
        if ($this->isSensitivePage($request)) {
            $headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0, private';
            $headers['Pragma'] = 'no-cache';
            $headers['Expires'] = '0';
        } else {
            // Set appropriate cache headers for public pages
            $headers['Cache-Control'] = 'public, max-age=3600'; // 1 hour
        }
        
        return $headers;
    }
    
    /**
     * Generate Feature Policy (legacy)
     */
    private function generateFeaturePolicy(Request $request): string
    {
        $path = $request->getPathInfo();
        
        $features = [
            'camera' => "'none'",
            'microphone' => "'none'",
            'geolocation' => "'none'",
            'payment' => "'none'",
            'usb' => "'none'",
            'magnetometer' => "'none'",
            'gyroscope' => "'none'",
            'accelerometer' => "'none'",
            'ambient-light-sensor' => "'none'",
            'autoplay' => "'none'",
            'battery' => "'none'",
            'bluetooth' => "'none'",
            'display-capture' => "'none'",
            'document-domain' => "'none'",
            'encrypted-media' => "'none'",
            'fullscreen' => "'none'",
            'gamepad' => "'none'",
            'midi' => "'none'",
            'notifications' => "'none'",
            'picture-in-picture' => "'none'",
            'publickey-credentials-get' => "'none'",
            'screen-wake-lock' => "'none'",
            'sync-xhr' => "'none'",
            'web-share' => "'none'",
            'xr-spatial-tracking' => "'none'"
        ];
        
        // Allow specific features for admin pages
        if (str_starts_with($path, '/admin/')) {
            $features['fullscreen'] = "'self'";
            $features['notifications'] = "'self'";
        }
        
        return implode('; ', array_map(function($feature, $value) {
            return "{$feature} {$value}";
        }, array_keys($features), $features));
    }
    
    /**
     * Check if the current page is sensitive and should not be cached
     */
    private function isSensitivePage(Request $request): bool
    {
        $sensitivePaths = [
            '/admin',
            '/app/profile',
            '/app/settings',
            '/app/team',
            '/app/dashboard',
            '/login',
            '/register',
            '/password',
            '/logout',
            '/app/account',
            '/app/billing',
            '/app/security',
        ];
        
        $path = $request->getPathInfo();
        
        foreach ($sensitivePaths as $sensitivePath) {
            if (str_starts_with($path, $sensitivePath)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate a nonce for CSP
     */
    private function generateNonce(): string
    {
        return base64_encode(random_bytes(16));
    }
    
    /**
     * Log security header violations
     */
    public function logSecurityViolation(Request $request, array $violation): void
    {
        $this->loggingService->logSecurity('csp_violation', [
            'violation' => $violation,
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip(),
            'url' => $request->fullUrl(),
            'referrer' => $request->header('referer'),
        ]);
    }
    
    /**
     * Get security headers configuration
     */
    public function getSecurityConfig(): array
    {
        return [
            'environment' => app()->environment(),
            'https_enabled' => request()->isSecure(),
            'csp_enabled' => true,
            'hsts_enabled' => request()->isSecure(),
            'frame_options' => 'DENY',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'permissions_policy_enabled' => true,
            'coep_enabled' => true,
            'coop_enabled' => true,
            'corp_enabled' => true,
        ];
    }
}
