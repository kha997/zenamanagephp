<?php declare(strict_types=1);

namespace Src\RBAC\Traits;

use Illuminate\Http\Request;

/**
 * Trait cung cấp helper methods để truy cập RBAC context trong controllers
 */
trait HasRBACContext
{
    /**
     * Get authenticated user from request
     * 
     * @param Request $request
     * @return array|null
     */
    protected function getAuthUser(Request $request): ?array
    {
        return $request->get('auth_user');
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
        return $user['user_id'] ?? null;
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
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return false;
        }
        
        // Trong method hasPermission, cập nhật:
        $rbacManager = app(\Src\RBAC\Services\RBACManager::class);
        return $rbacManager->hasPermission($userId, $permission, $projectId);
    }
}