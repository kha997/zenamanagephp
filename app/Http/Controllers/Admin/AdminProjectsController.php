<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin Projects Controller
 * 
 * Read-only portfolio view for admin with force actions (freeze, archive, suspend).
 * Applies tenant scoping for Org Admin.
 */
class AdminProjectsController extends Controller
{
    /**
     * Display read-only projects list
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        // Apply tenant scoping based on admin access level
        $query = Project::query();
        
        // Super Admin sees all, Org Admin sees only their tenant
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Apply status filter
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }
        
        // Apply tenant filter (only for Super Admin)
        if ($user->isSuperAdmin() && $tenantId = $request->input('tenant_id')) {
            $query->where('tenant_id', $tenantId);
        }
        
        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        // Apply pagination
        $perPage = min($request->input('per_page', 20), 100);
        $projects = $query->with(['tenant', 'owner'])->paginate($perPage);
        
        // Check if user has force ops permission
        $canForceOps = $user->can('admin.projects.force_ops');
        
        // If request expects JSON (API call), return JSON response
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'projects' => $projects->items(),
                    'pagination' => [
                        'current_page' => $projects->currentPage(),
                        'last_page' => $projects->lastPage(),
                        'per_page' => $projects->perPage(),
                        'total' => $projects->total(),
                    ],
                    'can_force_ops' => $canForceOps,
                ]
            ]);
        }
        
        // Return Blade view
        return view('admin.projects.index', [
            'projects' => $projects,
            'canForceOps' => $canForceOps,
            'filters' => $request->only(['search', 'status', 'tenant_id', 'sort_by', 'sort_direction', 'per_page']),
        ]);
    }
    
    /**
     * Display read-only project detail
     */
    public function show(string $id, Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        $query = Project::query();
        
        // Apply tenant scoping
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $project = $query->with(['tenant', 'owner', 'tasks', 'teamMembers'])->findOrFail($id);
        
        // Check if user has force ops permission
        $canForceOps = $user->can('admin.projects.force_ops');
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'project' => $project,
                    'can_force_ops' => $canForceOps,
                ]
            ]);
        }
        
        return view('admin.projects.show', [
            'project' => $project,
            'canForceOps' => $canForceOps,
        ]);
    }
    
    /**
     * Force freeze a project
     */
    public function freeze(string $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->can('admin.projects.force_ops')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'code' => 'PERMISSION_DENIED'
            ], 403);
        }
        
        $query = Project::query();
        
        // Apply tenant scoping
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $project = $query->findOrFail($id);
        
        // Freeze the project
        $project->update([
            'status' => 'frozen',
            'settings' => array_merge($project->settings ?? [], [
                'frozen_at' => now()->toIso8601String(),
                'frozen_by' => $user->id,
                'frozen_reason' => $request->input('reason', 'Admin freeze action'),
            ]),
        ]);
        
        Log::info('Project frozen by admin', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'tenant_id' => $project->tenant_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Project frozen successfully',
            'data' => ['project' => $project]
        ]);
    }
    
    /**
     * Force archive a project
     */
    public function archive(string $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->can('admin.projects.force_ops')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'code' => 'PERMISSION_DENIED'
            ], 403);
        }
        
        $query = Project::query();
        
        // Apply tenant scoping
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $project = $query->findOrFail($id);
        
        // Archive the project
        $project->update([
            'status' => 'archived',
            'settings' => array_merge($project->settings ?? [], [
                'archived_at' => now()->toIso8601String(),
                'archived_by' => $user->id,
                'archived_reason' => $request->input('reason', 'Admin archive action'),
            ]),
        ]);
        
        Log::info('Project archived by admin', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'tenant_id' => $project->tenant_id,
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Project archived successfully',
            'data' => ['project' => $project]
        ]);
    }
    
    /**
     * Emergency suspend a project
     */
    public function emergencySuspend(string $id, Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Check permission
        if (!$user->can('admin.projects.force_ops')) {
            return response()->json([
                'success' => false,
                'error' => 'Permission denied',
                'code' => 'PERMISSION_DENIED'
            ], 403);
        }
        
        $query = Project::query();
        
        // Apply tenant scoping
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $project = $query->findOrFail($id);
        
        // Emergency suspend the project
        $project->update([
            'status' => 'suspended',
            'settings' => array_merge($project->settings ?? [], [
                'suspended_at' => now()->toIso8601String(),
                'suspended_by' => $user->id,
                'suspended_reason' => $request->input('reason', 'Emergency suspension'),
                'suspension_type' => 'emergency',
            ]),
        ]);
        
        Log::warning('Project emergency suspended by admin', [
            'project_id' => $project->id,
            'user_id' => $user->id,
            'tenant_id' => $project->tenant_id,
            'reason' => $request->input('reason'),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Project emergency suspended successfully',
            'data' => ['project' => $project]
        ]);
    }
}
