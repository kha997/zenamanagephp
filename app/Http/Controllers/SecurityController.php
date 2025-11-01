<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\SecurityHeadersService;
use App\Services\ComprehensiveLoggingService;

/**
 * Security Controller
 * 
 * Handles security-related endpoints including:
 * - CSP violation reporting
 * - Security headers testing
 * - Security configuration
 */
class SecurityController extends Controller
{
    private SecurityHeadersService $securityHeadersService;
    private ComprehensiveLoggingService $loggingService;
    
    public function __construct(
        SecurityHeadersService $securityHeadersService,
        ComprehensiveLoggingService $loggingService
    ) {
        $this->securityHeadersService = $securityHeadersService;
        $this->loggingService = $loggingService;
    }
    
    /**
     * Handle CSP violation reports
     */
    public function cspReport(Request $request): JsonResponse
    {
        $violation = $request->all();
        
        // Log the CSP violation
        $this->securityHeadersService->logSecurityViolation($request, $violation);
        
        // Return success response
        return response()->json([
            'success' => true,
            'message' => 'CSP violation reported successfully'
        ]);
    }
    
    /**
     * Get security headers configuration
     */
    public function securityConfig(): JsonResponse
    {
        $config = $this->securityHeadersService->getSecurityConfig();
        
        return response()->json([
            'success' => true,
            'config' => $config
        ]);
    }
    
    /**
     * Test security headers endpoint
     */
    public function testSecurityHeaders(Request $request): JsonResponse
    {
        $securityHeaders = $this->securityHeadersService->generateSecurityHeaders($request);
        
        return response()->json([
            'success' => true,
            'headers' => $securityHeaders,
            'test_info' => [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'environment' => app()->environment(),
                'https' => $request->isSecure(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]
        ]);
    }
    
    /**
     * Security headers validation endpoint
     */
    public function validateSecurityHeaders(Request $request): JsonResponse
    {
        $securityHeaders = $this->securityHeadersService->generateSecurityHeaders($request);
        $validation = $this->validateHeaders($securityHeaders);
        
        return response()->json([
            'success' => true,
            'validation' => $validation,
            'headers' => $securityHeaders
        ]);
    }
    
    /**
     * Validate security headers
     */
    private function validateHeaders(array $headers): array
    {
        $validation = [
            'csp_present' => isset($headers['Content-Security-Policy']),
            'frame_options_present' => isset($headers['X-Frame-Options']),
            'content_type_options_present' => isset($headers['X-Content-Type-Options']),
            'xss_protection_present' => isset($headers['X-XSS-Protection']),
            'referrer_policy_present' => isset($headers['Referrer-Policy']),
            'permissions_policy_present' => isset($headers['Permissions-Policy']),
            'hsts_present' => isset($headers['Strict-Transport-Security']),
            'coep_present' => isset($headers['Cross-Origin-Embedder-Policy']),
            'coop_present' => isset($headers['Cross-Origin-Opener-Policy']),
            'corp_present' => isset($headers['Cross-Origin-Resource-Policy']),
        ];
        
        $validation['score'] = array_sum($validation);
        $validation['total'] = count($validation) - 1; // Exclude score
        $validation['percentage'] = round(($validation['score'] / $validation['total']) * 100, 2);
        
        return $validation;
    }
}
