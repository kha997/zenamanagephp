<?php declare(strict_types=1);

namespace Src\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Src\RBAC\Services\RBACManager;

/**
 * Middleware để tự động inject project context vào request
 * Sử dụng cho các endpoint cần project_id
 */
class ProjectContextMiddleware
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
     * @param string $projectParam Parameter name containing project_id
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $projectParam = 'project_id')
    {
        $projectId = $request->route($projectParam) ?? $request->input($projectParam);
        
        if (!$projectId) {
            return response()->json([
                'status' => 'error',
                'message' => "Missing required parameter: {$projectParam}"
            ], 400);
        }
        
        // Verify project exists and user has access
        $user = $request->get('auth_user');
        if ($user && !$this->rbacManager->hasProjectAccess($user['user_id'], $projectId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found or access denied'
            ], 404);
        }
        
        // Add project context to request
        $request->merge(['project_context' => $projectId]);
        
        return $next($request);
    }
}