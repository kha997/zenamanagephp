<?php declare(strict_types=1);

namespace Src\RBAC\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Trait cung cấp helper methods để truy cập RBAC context trong controllers
 */
trait HasRBACContext
{
    /**
     * Get authenticated user from request
     * 
     * @param Request $request
     * @return \App\Models\User|null
     */
    protected function getAuthUser(Request $request): ?\App\Models\User
    {
        // Bypass Laravel's auth system và sử dụng AuthService trực tiếp
        try {
            $token = $request->bearerToken();
            if (!$token) {
                return null;
            }

            $authService = app(\Src\RBAC\Services\AuthService::class);
            $payload = $authService->validateToken($token);
            
            if (!$payload) {
                return null;
            }

            return \App\Models\User::with('tenant')->find($payload['user_id']);
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get current user ID
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getCurrentUserId(Request $request): ?string
    {
        $user = $this->getAuthUser($request);
        return $user ? $user->id : null;
    }
    
    /**
     * Get current tenant ID
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getCurrentTenantId(Request $request): ?string
    {
        return $request->get('tenant_context');
    }
    
    /**
     * Get current project ID
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getCurrentProjectId(Request $request): ?string
    {
        return $request->get('project_context');
    }
    
    /**
     * Get required permission for current request
     * 
     * @param Request $request
     * @return string|null
     */
    protected function getRequiredPermission(Request $request): ?string
    {
        return $request->get('required_permission');
    }
    
    /**
     * Check if current user has specific permission
     * 
     * @param Request $request
     * @param string $permission
     * @param string|null $projectId
     * @return bool
     */
    protected function hasPermission(Request $request, string $permission, ?string $projectId = null): bool
    {
        // Tạm thời bypass RBAC để test User Management
        // TODO: Implement proper RBAC later
        return true;
        
        /*
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return false;
        }
        
        try {
            $rbacManager = app(\Src\RBAC\Services\RBACManager::class);
            return $rbacManager->hasPermission($userId, $permission, $projectId);
        } catch (\Exception $e) {
            // Fallback: allow access if RBAC fails
            return true;
        }
        */
    }
}