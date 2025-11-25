<?php declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * Trait for handling web errors with structured logging
 * 
 * Provides helper methods for consistent error handling in web controllers
 * with X-Request-Id correlation and user-friendly error messages.
 */
trait HandlesWebErrors
{
    /**
     * Handle web error with structured logging
     * 
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $context Additional context for logging
     * @param Request|null $request Optional request instance
     * @return RedirectResponse|never
     */
    protected function handleWebError(
        string $message,
        int $statusCode = 500,
        array $context = [],
        ?Request $request = null
    ) {
        $request = $request ?? request();
        $requestId = $request->header('X-Request-Id', uniqid('req_', true));
        
        // Log error with correlation ID
        Log::error('Web Controller Error', array_merge([
            'X-Request-Id' => $requestId,
            'message' => $message,
            'status_code' => $statusCode,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
        ], $context));
        
        // For 404 errors, use abort (standard Laravel behavior)
        if ($statusCode === 404) {
            abort(404, $message);
        }
        
        // For other errors, redirect back with flash message
        return back()->with('error', $message);
    }
    
    /**
     * Handle tenant isolation violation
     * 
     * @param string $resourceType Type of resource (e.g., 'Task', 'Project')
     * @param string $resourceId Resource ID
     * @param string $resourceTenantId Tenant ID of the resource
     * @param string $userTenantId Tenant ID of the user
     * @param Request|null $request Optional request instance
     * @return never
     */
    protected function handleTenantIsolationViolation(
        string $resourceType,
        string $resourceId,
        string $resourceTenantId,
        string $userTenantId,
        ?Request $request = null
    ): never {
        $request = $request ?? request();
        $requestId = $request->header('X-Request-Id', uniqid('req_', true));
        
        Log::warning('Tenant Isolation Violation', [
            'X-Request-Id' => $requestId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_tenant_id' => $resourceTenantId,
            'user_tenant_id' => $userTenantId,
            'route' => $request->route()?->getName(),
            'user_id' => auth()->id(),
        ]);
        
        // Return 404 to prevent information leakage
        abort(404, $resourceType . ' not found');
    }
}

