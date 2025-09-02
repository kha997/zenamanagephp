<?php declare(strict_types=1);

namespace Src\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Middleware đảm bảo tenant isolation
 * Tự động filter data theo tenant_id của user
 */
class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->get('auth_user');
        
        if (!$user || !isset($user['tenant_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid tenant context'
            ], 400);
        }
        
        // Add tenant context to request
        $request->merge(['tenant_context' => $user['tenant_id']]);
        
        return $next($request);
    }
}