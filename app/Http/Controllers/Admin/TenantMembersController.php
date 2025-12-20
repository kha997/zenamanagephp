<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TenantMembersController extends Controller
{
    /**
     * Display a listing of tenant members (tenant-scoped)
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            abort(403, 'Super admin must use admin/users for system-wide management.');
        }
        
        // Use TenancyService to resolve active tenant (consistent with /api/v1/me)
        $tenancyService = app(\App\Services\TenancyService::class);
        $tenantId = $tenancyService->resolveActiveTenantId($user, $request);
        
        // Fallback to legacy tenant_id if TenancyService returns null
        if (!$tenantId) {
            $tenantId = $user->tenant_id;
        }

        // Log request for debugging
        Log::info('Admin members request', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'role' => $user->role,
            'request_id' => $request->header('X-Request-Id'),
            'url' => $request->url(),
            'query_params' => $request->query()
        ]);

        // Build query with tenant isolation (tenant-scoped)
        $query = User::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply role filter
        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        // Apply status filter
        if ($status = $request->input('status')) {
            if ($status === 'active') {
                $query->where('is_active', true);
            } elseif ($status === 'inactive') {
                $query->where('is_active', false);
            }
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);

        // Apply pagination
        $perPage = min($request->input('per_page', 20), 100); // Max 100 per page
        $users = $query->with('tenant')->paginate($perPage);

        // Prepare filters for view
        $filters = $request->only(['search', 'role', 'status', 'sort_by', 'sort_direction', 'per_page']);

        // If request expects JSON (API call), return JSON response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'users' => $users->items(),
                    'pagination' => [
                        'current_page' => $users->currentPage(),
                        'last_page' => $users->lastPage(),
                        'per_page' => $users->perPage(),
                        'total' => $users->total(),
                        'from' => $users->firstItem(),
                        'to' => $users->lastItem()
                    ],
                    'filters' => $filters,
                ]
            ]);
        }

        // Return view for web requests
        return view('admin.members.index', compact('users', 'filters'));
    }

    /**
     * Invite a new member to the tenant
     * POST /api/admin/members/invite
     */
    public function invite(Request $request): JsonResponse
    {
        // TODO: Implement invite member functionality
        // This will be implemented in a future phase
        return response()->json([
            'success' => false,
            'error' => 'Invite functionality not yet implemented',
            'code' => 'NOT_IMPLEMENTED'
        ], 501);
    }

    /**
     * Update member role
     * PATCH /api/admin/members/{id}/role
     */
    public function updateRole(Request $request, $id): JsonResponse
    {
        // TODO: Implement update role functionality
        // This will be implemented in a future phase
        return response()->json([
            'success' => false,
            'error' => 'Update role functionality not yet implemented',
            'code' => 'NOT_IMPLEMENTED'
        ], 501);
    }

    /**
     * Remove member from tenant
     * DELETE /api/admin/members/{id}
     */
    public function remove(Request $request, $id): JsonResponse
    {
        // TODO: Implement remove member functionality
        // This will be implemented in a future phase
        return response()->json([
            'success' => false,
            'error' => 'Remove member functionality not yet implemented',
            'code' => 'NOT_IMPLEMENTED'
        ], 501);
    }
}
