<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Tenant Isolation Middleware
 * 
 * Middleware này sẽ:
 * - Kiểm tra tenant_id từ JWT payload
 * - Set tenant context cho toàn bộ request
 * - Validate user có quyền truy cập tenant không
 * - Apply global scope cho Eloquent queries
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
        $tenantId = $request->get('auth_tenant_id');
        $userId = $request->get('auth_user_id');
        
        if (!$tenantId) {
            return $this->forbiddenResponse('Missing tenant context');
        }
        
        // Validate user belongs to tenant
        if (!$this->validateUserTenantAccess($userId, $tenantId)) {
            Log::warning('Tenant Access Violation', [
                'user_id' => $userId,
                'attempted_tenant_id' => $tenantId,
                'ip_address' => $request->ip(),
                'endpoint' => $request->getPathInfo()
            ]);
            
            return $this->forbiddenResponse('Access denied to tenant');
        }
        
        // Set tenant context for the request
        $this->setTenantContext($tenantId);
        
        $response = $next($request);
        
        // Clear tenant context after request
        $this->clearTenantContext();
        
        return $response;
    }
    
    /**
     * Validate user has access to tenant
     * 
     * @param string $userId
     * @param string $tenantId
     * @return bool
     */
    private function validateUserTenantAccess(string $userId, string $tenantId): bool
    {
        try {
            // Check if user belongs to the tenant
            $userExists = DB::table('users')
                ->where('id', $userId)
                ->where('tenant_id', $tenantId)
                ->exists();
                
            return $userExists;
        } catch (\Exception $e) {
            Log::error('Tenant Validation Error', [
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }
    
    /**
     * Set tenant context for current request
     * 
     * @param string $tenantId
     */
    private function setTenantContext(string $tenantId): void
    {
        // Store tenant ID in app container
        app()->instance('current_tenant_id', $tenantId);
        
        // Set session variable for Eloquent global scopes
        config(['app.current_tenant_id' => $tenantId]);
    }
    
    /**
     * Clear tenant context after request
     */
    private function clearTenantContext(): void
    {
        app()->forgetInstance('current_tenant_id');
        config(['app.current_tenant_id' => null]);
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
}