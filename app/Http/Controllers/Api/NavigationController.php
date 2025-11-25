<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NavigationService;
use App\Services\RbacSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NavigationController extends Controller
{
    public function __construct(
        private RbacSyncService $rbacSyncService
    ) {}

    /**
     * Get navigation menu items based on user permissions
     * 
     * Unified endpoint for both React and Blade to read the same navigation schema.
     * Includes permissions and abilities for frontend route guards.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getNavigation(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'ok' => false,
                'code' => 'UNAUTHORIZED',
                'message' => 'User not authenticated',
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ], 401);
        }
        
        // Get navigation from centralized service
        $navigation = NavigationService::getNavigation($user);
        
        // Get user permissions via RbacSyncService
        $permissionsData = $this->rbacSyncService->getUserPermissions($user);
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'role' => $permissionsData['role'],
            ],
            'permissions' => $permissionsData['permissions'],
            'abilities' => $permissionsData['abilities'],
            'navigation' => $navigation,
            'admin_access' => [
                'is_super_admin' => in_array('admin', $permissionsData['abilities']),
                'is_org_admin' => in_array('tenant', $permissionsData['abilities']),
            ],
        ]);
    }
}

