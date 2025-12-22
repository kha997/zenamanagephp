<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AdminUsersController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $tenantFilter = $request->input('tenant_id');
        
        // Log request for debugging
        Log::info('Admin users request', [
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'role' => $user->role,
            'request_id' => $request->header('X-Request-Id'),
            'url' => $request->url(),
            'query_params' => $request->query(),
            'tenant_filter' => $tenantFilter
        ]);

        // Build query with tenant isolation
        $query = User::select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        if (!$user->isSuperAdmin()) {
            $query->where('tenant_id', $tenantId);
        }

        if ($tenantFilter && $user->isSuperAdmin()) {
            $query->where('tenant_id', $tenantFilter);
        }

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

        // Get all tenants for filter options (only if super admin)
        $tenants = collect();
        if ($user->role === 'super_admin') {
            $tenants = Tenant::select(['id', 'name'])->get();
        }

        // Prepare filters for view
        $filters = $request->only(['search', 'role', 'status', 'sort_by', 'sort_direction', 'per_page', 'tenant_id']);

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
                    'tenants' => $tenants
                ]
            ]);
        }

        // Return view for web requests
        return view('admin.users.index', compact('users', 'tenants', 'filters'));
    }

    /**
     * Debug method for testing
     */
    public function debug(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = User::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->with('tenant')->paginate($perPage);

        $tableData = collect($users->items() ?? [])->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'member',
                'status' => $user->is_active ? 'active' : 'inactive',
                'tenant' => $user->tenant->name ?? 'No Tenant',
                'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
                'created_at' => $user->created_at->format('M d, Y'),
                'updated_at' => $user->updated_at->format('M d, Y')
            ];
        });

        return view('admin.users.debug', compact('users', 'tableData'));
    }

    /**
     * Test component method
     */
    public function testComponent(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = User::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->with('tenant')->paginate($perPage);

        $tableData = collect($users->items() ?? [])->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'member',
                'status' => $user->is_active ? 'active' : 'inactive',
                'tenant' => $user->tenant->name ?? 'No Tenant',
                'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
                'created_at' => $user->created_at->format('M d, Y'),
                'updated_at' => $user->updated_at->format('M d, Y')
            ];
        });

        return view('admin.users.test-component', compact('users', 'tableData'));
    }

    /**
     * Debug component method
     */
    public function debugComponent(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = User::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->with('tenant')->paginate($perPage);

        $tableData = collect($users->items() ?? [])->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'member',
                'status' => $user->is_active ? 'active' : 'inactive',
                'tenant' => $user->tenant->name ?? 'No Tenant',
                'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
                'created_at' => $user->created_at->format('M d, Y'),
                'updated_at' => $user->updated_at->format('M d, Y')
            ];
        });

        return view('admin.users.debug-component', compact('users', 'tableData'));
    }

    /**
     * Fixed test method
     */
    public function fixedTest(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $query = User::where('tenant_id', $tenantId)
            ->select(['id', 'name', 'email', 'role', 'is_active', 'tenant_id', 'last_login_at', 'created_at', 'updated_at']);

        $perPage = min($request->input('per_page', 20), 100);
        $users = $query->with('tenant')->paginate($perPage);

        $tableData = collect($users->items() ?? [])->map(function($user) {
            return [
                'id' => $user->id,
                'name' => $user->name ?? 'Unknown',
                'email' => $user->email ?? '',
                'role' => $user->role ?? 'member',
                'status' => $user->is_active ? 'active' : 'inactive',
                'tenant' => $user->tenant->name ?? 'No Tenant',
                'last_login' => $user->last_login_at ? $user->last_login_at->format('M d, Y') : 'Never',
                'created_at' => $user->created_at->format('M d, Y'),
                'updated_at' => $user->updated_at->format('M d, Y')
            ];
        });

        return view('admin.users.fixed-test', compact('users', 'tableData'));
    }
}
