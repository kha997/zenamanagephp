<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use App\Services\MeService;
use App\Services\TenancyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Tenant Controller
 * 
 * Handles tenant-related API endpoints
 */
class TenantController extends Controller
{
    /**
     * Get list of tenants for current user
     * GET /api/v1/me/tenants
     * 
     * Returns all tenants the user has access to via pivot membership.
     * Falls back to legacy tenant_id for backward compatibility.
     */
    public function getTenants(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'UNAUTHORIZED'
                );
            }
            
            // Get tenants from membership pivot (or legacy fallback)
            $tenancyService = app(TenancyService::class);
            $membershipTenants = $tenancyService->getMembershipTenants($user);
            
            // Get active tenant using TenancyService
            $activeTenant = $tenancyService->resolveActiveTenant($user, $request);
            $activeTenantId = $activeTenant?->id;
            
            // Map tenants with metadata
            $tenants = $membershipTenants->map(function ($tenant) use ($activeTenantId) {
                return [
                    'id' => $tenant->id,
                    'name' => $tenant->name ?? 'Unknown Tenant',
                    'slug' => $tenant->slug ?? null,
                    'is_active' => $tenant->is_active ?? true,
                    'is_current' => $activeTenantId && $activeTenantId === $tenant->id,
                    'is_default' => optional($tenant->pivot)->is_default ?? false,
                    'role' => optional($tenant->pivot)->role,
                ];
            })->values()->all();
            
            return ApiResponse::success([
                'tenants' => $tenants,
                'count' => count($tenants),
                'current_tenant_id' => $activeTenantId,
            ]);
        } catch (\Exception $e) {
            Log::error('Get tenants failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            
            return ApiResponse::error(
                'Failed to fetch tenants',
                500,
                null,
                'TENANTS_FETCH_FAILED'
            );
        }
    }
    
    /**
     * Select/switch tenant for current user
     * POST /api/v1/me/tenants/{tenantId}/select
     * 
     * Sets the selected tenant for the current session.
     * Validates membership via user_tenants pivot table.
     */
    public function selectTenant(Request $request, string $tenantId): JsonResponse
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'UNAUTHORIZED'
                );
            }
            
            // Verify tenant exists
            $tenant = \App\Models\Tenant::find($tenantId);
            if (!$tenant) {
                return ApiResponse::error(
                    'Tenant not found',
                    404,
                    null,
                    'TENANT_NOT_FOUND'
                );
            }
            
            // Check if user has membership via pivot table
            $isMember = $user->tenants()
                ->where('tenants.id', $tenantId)
                ->exists();
            
            // Fallback: check legacy tenant_id for backward compatibility
            if (!$isMember && $user->tenant_id === $tenantId) {
                $isMember = true;
            }
            
            if (!$isMember) {
                return ApiResponse::error(
                    'You do not have access to this tenant',
                    403,
                    null,
                    'TENANT_ACCESS_DENIED'
                );
            }
            
            // Update is_default flags in pivot table
            // This ensures the selected tenant becomes the default for future sessions/devices
            DB::transaction(function () use ($user, $tenantId) {
                // Get all tenant IDs for this user (excluding soft-deleted)
                $userTenantIds = $user->tenants()
                    ->wherePivotNull('deleted_at')
                    ->pluck('tenants.id')
                    ->all();
                
                if (!empty($userTenantIds)) {
                    // Clear default for all user's tenant memberships
                    DB::table('user_tenants')
                        ->where('user_id', $user->id)
                        ->whereIn('tenant_id', $userTenantIds)
                        ->whereNull('deleted_at')
                        ->update(['is_default' => false]);
                    
                    // Set default = true for selected tenant membership
                    DB::table('user_tenants')
                        ->where('user_id', $user->id)
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->update(['is_default' => true]);
                }
            });
            
            // Set tenant in session
            $request->session()->put('selected_tenant_id', $tenantId);
            
            Log::info('Tenant selected', [
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            
            $responseData = [
                'tenant_id' => $tenantId,
                'tenant_name' => $tenant->name,
                'message' => 'Tenant selected successfully',
            ];
            
            // Optionally include fresh Me payload if requested
            if ($request->query('include_me') === 'true') {
                $meService = app(MeService::class);
                $responseData['me'] = $meService->buildMeResponse($user, $request);
            }
            
            return ApiResponse::success($responseData);
        } catch (\Exception $e) {
            Log::error('Select tenant failed', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'tenant_id' => $tenantId,
                'request_id' => $request->header('X-Request-Id'),
            ]);
            
            return ApiResponse::error(
                'Failed to select tenant',
                500,
                null,
                'TENANT_SELECT_FAILED'
            );
        }
    }
}

