<?php declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Enterprise\EnterpriseController;

/*
|--------------------------------------------------------------------------
| Enterprise Features Routes
|--------------------------------------------------------------------------
|
| These routes handle enterprise features including:
| - SAML SSO Integration
| - LDAP Integration
| - Enterprise Audit Trails
| - Compliance Reporting
| - Enterprise Analytics
| - Advanced User Management
| - Enterprise Settings
| - Multi-tenant Management
| - Enterprise Security
| - Advanced Reporting
|
*/

Route::prefix('enterprise')->group(function () {
    // SAML SSO
    Route::post('/saml/sso', [EnterpriseController::class, 'processSamlSSO'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('enterprise.saml.sso');

    // LDAP Integration
    Route::post('/ldap/authenticate', [EnterpriseController::class, 'authenticateLdapUser'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('enterprise.ldap.authenticate');

    // Enterprise Audit Trails
    Route::post('/audit/log', [EnterpriseController::class, 'logAuditEvent'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('enterprise.audit.log');

    // Compliance Reporting
    Route::post('/compliance/report', [EnterpriseController::class, 'generateComplianceReport'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.compliance.report');

    // Enterprise Analytics
    Route::get('/analytics', [EnterpriseController::class, 'getEnterpriseAnalytics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.analytics');

    // Advanced User Management
    Route::get('/users', [EnterpriseController::class, 'manageEnterpriseUsers'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.users');

    // Enterprise Settings
    Route::post('/settings', [EnterpriseController::class, 'updateEnterpriseSettings'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.settings');

    // Multi-tenant Management
    Route::get('/tenants', [EnterpriseController::class, 'manageTenants'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.tenants');

    // Enterprise Security
    Route::get('/security/status', [EnterpriseController::class, 'getEnterpriseSecurityStatus'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.security.status');

    // Advanced Reporting
    Route::post('/reports/generate', [EnterpriseController::class, 'generateAdvancedReport'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.reports.generate');

    // Enterprise Capabilities
    Route::get('/capabilities', [EnterpriseController::class, 'getEnterpriseCapabilities'])
        ->middleware(['auth:sanctum', 'ability:tenant'])
        ->name('enterprise.capabilities');

    // Enterprise Statistics
    Route::get('/statistics', [EnterpriseController::class, 'getEnterpriseStatistics'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.statistics');

    // Enterprise Connectivity Test
    Route::get('/test-connectivity', [EnterpriseController::class, 'testEnterpriseConnectivity'])
        ->middleware(['auth:sanctum', 'ability:admin'])
        ->name('enterprise.test-connectivity');
});
