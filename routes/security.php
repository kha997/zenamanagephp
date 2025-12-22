<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SecurityController;

/*
|--------------------------------------------------------------------------
| Security Routes
|--------------------------------------------------------------------------
|
| Routes for security-related functionality including CSP violation
| reporting, security headers testing, and security configuration.
|
*/

// CSP violation reporting
Route::post('/_security/csp-report', [SecurityController::class, 'cspReport'])
    ->name('security.csp-report');

// Security headers testing and validation
Route::get('/_security/test-headers', [SecurityController::class, 'testSecurityHeaders'])
    ->name('security.test-headers');

Route::get('/_security/validate-headers', [SecurityController::class, 'validateSecurityHeaders'])
    ->name('security.validate-headers');

Route::get('/_security/config', [SecurityController::class, 'securityConfig'])
    ->name('security.config');

// Security headers documentation
Route::get('/_security/docs', function () {
    return response()->json([
        'title' => 'Security Headers Documentation',
        'description' => 'Comprehensive security headers implementation for ZenaManage',
        'endpoints' => [
            'csp-report' => [
                'method' => 'POST',
                'url' => '/_security/csp-report',
                'description' => 'Report CSP violations',
            ],
            'test-headers' => [
                'method' => 'GET',
                'url' => '/_security/test-headers',
                'description' => 'Test security headers for current request',
            ],
            'validate-headers' => [
                'method' => 'GET',
                'url' => '/_security/validate-headers',
                'description' => 'Validate security headers configuration',
            ],
            'config' => [
                'method' => 'GET',
                'url' => '/_security/config',
                'description' => 'Get security headers configuration',
            ],
        ],
        'headers' => [
            'Content-Security-Policy' => 'Prevents XSS attacks by controlling resource loading',
            'X-Frame-Options' => 'Prevents clickjacking attacks',
            'X-Content-Type-Options' => 'Prevents MIME type sniffing',
            'X-XSS-Protection' => 'Enables XSS filtering in browsers',
            'Referrer-Policy' => 'Controls referrer information',
            'Permissions-Policy' => 'Controls browser features and APIs',
            'Strict-Transport-Security' => 'Enforces HTTPS connections',
            'Cross-Origin-Embedder-Policy' => 'Controls cross-origin embedding',
            'Cross-Origin-Opener-Policy' => 'Controls cross-origin window access',
            'Cross-Origin-Resource-Policy' => 'Controls cross-origin resource access',
        ],
    ]);
})->name('security.docs');
