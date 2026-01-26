<?php

namespace App\Http\Middleware;

use App\Services\TenancyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantScopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function __construct(private TenancyService $tenancyService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated
        if (!Auth::check()) {
            return redirect()->route('login')->with('error', 'Authentication required');
        }

        $user = Auth::user();

        // For admin users, allow access to all tenants
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // For regular users, ensure they have a tenant scope
        if (!$user->tenant_id) {
            abort(403, 'No tenant scope assigned to user');
        }

        // Set tenant context for the request
        $request->attributes->set('tenant_id', $user->tenant_id);
        
        // Add tenant scope to the request for easy access
        $this->tenancyService->setTenantContext($user->tenant_id, $user->tenant);

        return $next($request);
    }
}
