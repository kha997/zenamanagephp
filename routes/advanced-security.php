<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Security\AdvancedSecurityController;

/*
|--------------------------------------------------------------------------
| Advanced Security Features Routes
|--------------------------------------------------------------------------
|
| These routes handle advanced security features including:
| - Threat Detection and Prevention
| - Intrusion Detection System (IDS)
| - Security Analytics and Monitoring
| - Advanced Authentication Security
| - Data Protection and Encryption
| - Security Incident Response
| - Vulnerability Assessment
| - Security Compliance Monitoring
|
*/

Route::prefix('security')->group(function () {
    // Threat Detection
    Route::post('/detect-threats', [AdvancedSecurityController::class, 'detectThreats'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.detect-threats');

    // Intrusion Detection
    Route::post('/detect-intrusion', [AdvancedSecurityController::class, 'detectIntrusion'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.detect-intrusion');

    // Security Analytics
    Route::get('/analytics', [AdvancedSecurityController::class, 'getSecurityAnalytics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.analytics');

    // Authentication Security
    Route::post('/enhance-auth', [AdvancedSecurityController::class, 'enhanceAuthenticationSecurity'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('security.enhance-auth');

    // Data Protection
    Route::post('/protect-data', [AdvancedSecurityController::class, 'protectSensitiveData'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('security.protect-data');

    // Incident Response
    Route::post('/handle-incident', [AdvancedSecurityController::class, 'handleSecurityIncident'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.handle-incident');

    // Vulnerability Assessment
    Route::post('/vulnerability-assessment', [AdvancedSecurityController::class, 'performVulnerabilityAssessment'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.vulnerability-assessment');

    // Compliance Monitoring
    Route::get('/compliance', [AdvancedSecurityController::class, 'monitorCompliance'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.compliance');

    // Security Dashboard
    Route::get('/dashboard', [AdvancedSecurityController::class, 'getSecurityDashboard'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.dashboard');

    // Security Statistics
    Route::get('/statistics', [AdvancedSecurityController::class, 'getSecurityStatistics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.statistics');

    // Security Health Status
    Route::get('/health', [AdvancedSecurityController::class, 'getSecurityHealthStatus'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.health');

    // Security Connectivity Test
    Route::get('/test-connectivity', [AdvancedSecurityController::class, 'testSecurityConnectivity'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('security.test-connectivity');

    // Security Capabilities
    Route::get('/capabilities', [AdvancedSecurityController::class, 'getSecurityCapabilities'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('security.capabilities');
});
