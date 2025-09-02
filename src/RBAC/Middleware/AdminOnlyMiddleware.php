<?php declare(strict_types=1);

namespace Src\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\RBAC\Services\RBACManager;

/**
 * Middleware chỉ cho phép System Admin truy cập
 * Sử dụng cho các endpoint quản trị hệ thống
 */
class AdminOnlyMiddleware
{
    private RBACManager $rbacManager;
    
    public function __construct(RBACManager $rbacManager)
    {
        $this->rbacManager = $rbacManager;
    }
    
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
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }
        
        // Check if user has system admin role
        if (!$this->rbacManager->hasSystemRole($user['user_id'], 'system_admin')) {
            return response()->json([
                'status' => 'error',
                'message' => 'System administrator access required'
            ], 403);
        }
        
        return $next($request);
    }
}