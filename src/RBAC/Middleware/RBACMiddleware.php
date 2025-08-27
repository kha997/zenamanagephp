<?php declare(strict_types=1);

namespace Src\RBAC\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * RBAC Middleware để kiểm tra quyền truy cập cho các API endpoints
 * 
 * Middleware này sẽ:
 * - Kiểm tra JWT token và extract user info
 * - Tính toán effective permissions dựa trên 3 lớp quyền
 * - Cho phép hoặc từ chối truy cập dựa trên permission required
 * - Ghi log audit cho các hành động bị từ chối
 */
class RBACMiddleware
{
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $permission Permission code required (e.g., 'task.create')
     * @param string|null $projectParam Request parameter name containing project_id (optional)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission, ?string $projectParam = null)
    {
        // Extract user info from JWT token
        $user = $this->extractUserFromToken($request);
        if (!$user) {
            return $this->unauthorizedResponse('Invalid or missing authentication token');
        }
        
        // Validate token expiration
        if ($user['exp'] < time()) {
            return $this->unauthorizedResponse('Token has expired');
        }
        
        // Get project_id if specified
        $projectId = null;
        if ($projectParam) {
            $projectId = $request->route($projectParam) ?? $request->input($projectParam);
            if (!$projectId) {
                return $this->badRequestResponse("Missing required parameter: {$projectParam}");
            }
        }
        
        // Implement RBAC permission checking
        $hasPermission = $this->checkUserPermission($user['user_id'], $permission, $projectId);
        
        if (!$hasPermission) {
            // Log access denied event
            $this->logAccessDenied($user, $permission, $projectId, $request);
            return $this->forbiddenResponse('Insufficient permissions');
        }
        
        // Add user and permission info to request for controllers to use
        $request->merge([
            'auth_user' => $user,
            'required_permission' => $permission,
            'project_context' => $projectId
        ]);
        
        return $next($request);
    }
    
    /**
     * Check if user has required permission
     * 
     * @param int $userId
     * @param string $permission
     * @param int|null $projectId
     * @return bool
     */
    private function checkUserPermission(int $userId, string $permission, ?int $projectId = null): bool
    {
        try {
            // Get RBACManager from service container
            $rbacManager = app(\Src\RBAC\Services\RBACManager::class);
            
            // Use RBACManager to check permission
            return $rbacManager->hasPermission($userId, $permission, $projectId);
        } catch (\Exception $e) {
            // Log error and deny access for safety
            Log::error('RBAC Permission Check Failed', [
                'user_id' => $userId,
                'permission' => $permission,
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return false;
        }
    }
    
    /**
     * Extract user information from JWT token
     * 
     * @param Request $request
     * @return array|null
     */
    private function extractUserFromToken(Request $request): ?array
    {
        $token = $request->bearerToken();
        if (!$token) {
            return null;
        }
        
        try {
            // Simplified JWT decode - in real implementation, use proper JWT library
            $payload = json_decode(base64_decode(explode('.', $token)[1]), true);
            
            return [
                'user_id' => $payload['user_id'] ?? null,
                'tenant_id' => $payload['tenant_id'] ?? null,
                'system_roles' => $payload['system_roles'] ?? [],
                'exp' => $payload['exp'] ?? 0
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Log access denied event for audit
     * 
     * @param array $user
     * @param string $permission
     * @param string|null $projectId
     * @param Request $request
     */
    private function logAccessDenied(array $user, string $permission, ?string $projectId, Request $request): void
    {
        // Use Laravel's built-in logging instead of custom EventBus for now
        Log::warning('RBAC Access Denied', [
            'user_id' => $user['user_id'],
            'permission_required' => $permission,
            'project_id' => $projectId,
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString()
        ]);
    }
    
    /**
     * Return unauthorized response
     * 
     * @param string $message
     * @return JsonResponse
     */
    private function unauthorizedResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 401);
    }
    
    /**
     * Return forbidden response
     * 
     * @param string $message
     * @return JsonResponse
     */
    private function forbiddenResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 403);
    }
    
    /**
     * Return bad request response
     * 
     * @param string $message
     * @return JsonResponse
     */
    private function badRequestResponse(string $message): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message
        ], 400);
    }
}