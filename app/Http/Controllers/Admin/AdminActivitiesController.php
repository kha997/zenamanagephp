<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Admin Activities Controller
 * 
 * Audit log and activity tracking with tenant scoping.
 */
class AdminActivitiesController extends Controller
{
    /**
     * Display audit log
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        
        // Build query with tenant scoping
        $query = AuditLog::query();
        
        // Apply tenant scope for Org Admin
        if ($user->can('admin.access.tenant') && !$user->isSuperAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        // Apply filters
        if ($userId = $request->input('user_id')) {
            $query->where('user_id', $userId);
        }
        
        if ($action = $request->input('action')) {
            $query->where('action', $action);
        }
        
        if ($entityType = $request->input('entity_type')) {
            $query->where('entity_type', $entityType);
        }
        
        if ($startDate = $request->input('start_date')) {
            $query->where('created_at', '>=', $startDate);
        }
        
        if ($endDate = $request->input('end_date')) {
            $query->where('created_at', '<=', $endDate);
        }
        
        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        // Apply pagination
        $perPage = min($request->input('per_page', 50), 100);
        $activities = $query->with(['user', 'tenant', 'project'])->paginate($perPage);
        
        // Get filter options
        $filterOptions = [
            'users' => $query->distinct()->pluck('user_id')->map(function ($userId) {
                return \App\Models\User::find($userId);
            })->filter()->values(),
            'actions' => $query->distinct()->pluck('action')->filter()->values(),
            'entity_types' => $query->distinct()->pluck('entity_type')->filter()->values(),
        ];
        
        $data = [
            'activities' => $activities,
            'filter_options' => $filterOptions,
            'filters' => $request->only(['user_id', 'action', 'entity_type', 'start_date', 'end_date', 'sort_by', 'sort_direction', 'per_page']),
        ];
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }
        
        return view('admin.activities.index', $data);
    }
}
