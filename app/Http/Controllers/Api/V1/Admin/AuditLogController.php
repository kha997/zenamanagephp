<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * AuditLogController
 * 
 * Round 235: Audit Log Framework
 * 
 * Admin API for viewing audit logs with filtering and pagination
 */
class AuditLogController extends BaseApiV1Controller
{
    /**
     * Constructor - Check permission
     */
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $user = Auth::user();
            
            if (!$user) {
                return $this->errorResponse('Unauthenticated', 401, null, 'UNAUTHENTICATED');
            }
            
            // Check if user has system.audit.view permission
            if (!$this->hasPermission($user, 'system.audit.view')) {
                Log::warning('User attempted to access audit logs without permission', [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'url' => $request->url(),
                ]);
                
                return $this->errorResponse(
                    'Insufficient permissions',
                    403,
                    ['details' => 'system.audit.view permission required'],
                    'PERMISSION_DENIED'
                );
            }
            
            return $next($request);
        });
    }
    
    /**
     * Check if user has specific permission
     */
    private function hasPermission(User $user, string $permission): bool
    {
        // Super admin has all permissions
        if ($user->role === 'super_admin') {
            return true;
        }
        
        $role = $user->role ?? 'member';
        $permissions = config('permissions.roles.' . $role, []);
        
        if (in_array('*', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }

    /**
     * List audit logs with filtering and pagination
     * 
     * GET /api/v1/admin/audit-logs
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = Auth::user()?->tenant_id;
            
            if (!$tenantId) {
                return $this->errorResponse('Tenant not found', 404, null, 'TENANT_NOT_FOUND');
            }

            // Build query with tenant isolation
            $query = AuditLog::where('tenant_id', $tenantId)
                ->with(['user:id,name,email']);

            // Apply filters
            if ($request->has('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            if ($request->has('action')) {
                $query->where('action', $request->input('action'));
            }

            if ($request->has('entity_type')) {
                $query->where('entity_type', $request->input('entity_type'));
            }

            if ($request->has('entity_id')) {
                $query->where('entity_id', $request->input('entity_id'));
            }

            if ($request->has('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->has('date_from')) {
                $query->where('created_at', '>=', $request->input('date_from'));
            }

            if ($request->has('date_to')) {
                $query->where('created_at', '<=', $request->input('date_to'));
            }

            // Round 238: Module filter (RBAC, Cost, Documents, Tasks)
            if ($request->has('module')) {
                $module = $request->input('module');
                if ($module === 'RBAC') {
                    $query->where(function ($q) {
                        $q->where('action', 'like', 'role.%')
                          ->orWhere('action', 'like', 'user.roles_%');
                    });
                } elseif ($module === 'Cost') {
                    $query->where(function ($q) {
                        $q->where('action', 'like', 'co.%')
                          ->orWhere('action', 'like', 'certificate.%')
                          ->orWhere('action', 'like', 'payment.%')
                          ->orWhere('action', 'like', 'contract.%');
                    });
                } elseif ($module === 'Documents') {
                    $query->where('action', 'like', 'document.%');
                } elseif ($module === 'Tasks') {
                    $query->where('action', 'like', 'task.%');
                }
            }

            // Round 238: Text search filter
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($q) use ($search) {
                    $q->where('action', 'like', "%{$search}%")
                      ->orWhere('entity_type', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = min((int) $request->input('per_page', 15), 100);
            $logs = $query->orderBy('created_at', 'desc')
                ->paginate($perPage);

            // Format response
            $data = $logs->map(function ($log) {
                return [
                    'id' => $log->id,
                    'user' => $log->user ? [
                        'id' => $log->user->id,
                        'name' => $log->user->name,
                        'email' => $log->user->email,
                    ] : null,
                    'action' => $log->action,
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'project_id' => $log->project_id,
                    'payload_before' => $log->payload_before,
                    'payload_after' => $log->payload_after,
                    'ip_address' => $log->ip_address,
                    'user_agent' => $log->user_agent,
                    'created_at' => $log->created_at?->toISOString(),
                ];
            });

            return $this->successResponse([
                'data' => $data,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                ],
            ], 'Audit logs retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['method' => 'index']);
            return $this->errorResponse('Failed to retrieve audit logs', 500, null, 'AUDIT_LOGS_FETCH_ERROR');
        }
    }
}
