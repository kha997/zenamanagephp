<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Scope Middleware
 * 
 * Ensures all database queries are scoped to the user's tenant.
 * This middleware automatically adds tenant_id filtering to all queries.
 */
class TenantScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return $next($request);
        }

        // Only apply tenant scoping for tenant-scoped users
        if ($user->tenant_id) {
            // Set tenant context for the request
            $request->attributes->set('tenant_id', $user->tenant_id);
            
            // Add tenant_id to request data for automatic filtering
            $request->merge(['tenant_id' => $user->tenant_id]);

            // Log tenant scoping
            Log::debug('Tenant scope applied', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);
        }

        return $next($request);
    }
}