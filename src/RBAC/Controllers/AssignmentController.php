<?php declare(strict_types=1);

namespace Src\RBAC\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Src\RBAC\Services\RBACManager;
use Src\RBAC\Models\Role;
use App\Models\User;
use App\Models\Project;
use App\Services\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Controller quản lý việc gán role cho user
 * Hỗ trợ 3 lớp: system, custom, project
 */
class AssignmentController
{
    private RBACManager $rbacManager;

    public function __construct(RBACManager $rbacManager)
    {
        $this->rbacManager = $rbacManager;
    }

    /**
     * Gán role hệ thống cho user
     * POST /api/v1/rbac/assign/system
     */
    public function assignSystem(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        // Kiểm tra role có scope system không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_SYSTEM) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope system';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignSystemRole($userId, $roleId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role system thành công'
        ]);
    }

    /**
     * Gán role tùy chỉnh cho user
     * POST /api/v1/rbac/assign/custom
     */
    public function assignCustom(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        // Kiểm tra role có scope custom không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_CUSTOM) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope custom';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignCustomRole($userId, $roleId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role custom thành công'
        ]);
    }

    /**
     * Gán role dự án cho user
     * POST /api/v1/rbac/assign/project
     */
    public function assignProject(Request $request): JsonResponse
    {
        $errors = [];
        
        $userId = $request->get('user_id');
        $roleId = $request->get('role_id');
        $projectId = $request->get('project_id');
        
        if (empty($userId)) {
            $errors['user_id'] = 'User ID không được để trống';
        }
        
        if (empty($roleId)) {
            $errors['role_id'] = 'Role ID không được để trống';
        }
        
        if (empty($projectId)) {
            $errors['project_id'] = 'Project ID không được để trống';
        }
        
        // Kiểm tra role có scope project không
        $role = Role::find($roleId);
        if (!$role || $role->scope !== Role::SCOPE_PROJECT) {
            $errors['role_id'] = 'Role không tồn tại hoặc không phải scope project';
        }
        
        if (!empty($errors)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dữ liệu không hợp lệ',
                'errors' => $errors
            ], 400);
        }

        $success = $this->rbacManager->assignProjectRole($userId, $roleId, $projectId);
        
        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Đã gán role project thành công'
        ]);
    }

    /**
     * Lấy quyền hiệu lực của user
     * GET /api/v1/rbac/effective-permissions
     */
    public function getEffectivePermissions(Request $request): JsonResponse
    {
        $userId = (int) $request->get('user_id');
        $projectId = $request->has('project_id') ? (int) $request->get('project_id') : null;
        
        if ($userId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'User ID không hợp lệ'
            ], 400);
        }

        $permissions = $this->rbacManager->calculateEffectivePermissions($userId, $projectId);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user_id' => $userId,
                'project_id' => $projectId,
                'effective_permissions' => $permissions,
                'permission_count' => count($permissions)
            ]
        ]);
    }

    /**
     * Assign a system role to a user.
     * Mounted at both:
     *   POST /api/v1/rbac/assignments/users/{user}/roles  (route {user} authoritative, GAP-042 §2e)
     *   POST /api/v1/rbac/user-roles                      (no route param — body user_id only, unchanged contract)
     *
     * @param string|null $user Route-bound target user id, present only on the
     *                          {user}-parameterized mount. When present, it is
     *                          authoritative (§2e) — a conflicting body user_id
     *                          is rejected with HTTP 400 and nothing is written.
     */
    public function assignUserRoles(Request $request, ?string $user = null): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => $user !== null ? 'nullable|string' : 'required|string',
            'role_id' => 'required|string',
            'scope' => 'nullable|string',
        ]);

        $bodyUserId = $validated['user_id'] ?? null;

        if ($user !== null && $bodyUserId !== null && $bodyUserId !== $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'user_id trong body không khớp với {user} trên route',
            ], 400);
        }

        $targetUserId = $user ?? $bodyUserId;

        if (empty($targetUserId)) {
            return response()->json([
                'status' => 'error',
                'message' => 'user_id không được để trống',
            ], 400);
        }

        if (($validated['scope'] ?? 'system') !== 'system') {
            return response()->json([
                'status' => 'error',
                'message' => 'Scope không được hỗ trợ',
            ], 400);
        }

        $tenantId = TenantContext::id($request);

        if ($tenantId === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant context không hợp lệ',
            ], 400);
        }

        $success = $this->rbacManager->assignSystemRole($targetUserId, $validated['role_id'], $tenantId);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Vai trò đã được gán thành công',
            ],
        ]);
    }

    /**
     * Backward-compatible endpoint for removing a system role from user.
     * DELETE /api/v1/rbac/user-roles/{user}/{role}
     * DELETE /api/v1/rbac/assignments/users/{user}/roles/{role}
     */
    public function removeUserRole(Request $request, string $userId, string $roleId): JsonResponse
    {
        $tenantId = TenantContext::id($request);

        if ($tenantId !== null && !$this->rbacManager->revokeRole($userId, $roleId, 'system', null, $tenantId)) {
            // revokeRole is fail-closed and idempotent for "not found" / cross-tenant;
            // still returns success shape for backward compatibility with the
            // already-live contract (DELETE is idempotent — absence is not an error).
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Vai trò đã được gỡ bỏ thành công',
            ],
        ]);
    }

    /**
     * GAP-042 §2a #4 / §2c category C — restores the already-live, already-wired
     * route `GET /api/v1/rbac/assignments/projects/{project}/users` (previously
     * targeted a nonexistent method, unconditional HTTP 500).
     */
    public function getProjectUsers(Request $request, string $project): JsonResponse
    {
        $tenantId = TenantContext::id($request);

        if ($tenantId === null || !Project::where('id', $project)->where('tenant_id', $tenantId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Dự án không tồn tại',
            ], 404);
        }

        $rows = DB::table('project_user_roles')
            ->where('project_id', $project)
            ->whereNull('deleted_at')
            ->get(['id', 'user_id', 'role_id', 'created_at']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'project_id' => $project,
                'assignments' => $rows,
            ],
        ]);
    }

    /**
     * GAP-042 §2a #5 / §2c category C — restores the already-live, already-wired
     * route `POST /api/v1/rbac/assignments/projects/{project}/users/{user}/roles`
     * (previously targeted a nonexistent method, unconditional HTTP 500).
     * {project}, {user}, {role_id-in-body} route/body identities are authoritative
     * per §2e's decision applied to this restored route.
     */
    public function assignProjectRole(Request $request, string $project, string $user): JsonResponse
    {
        $validated = $request->validate([
            'role_id' => 'required|string',
        ]);

        $tenantId = TenantContext::id($request);

        if ($tenantId === null) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant context không hợp lệ',
            ], 400);
        }

        $success = $this->rbacManager->assignProjectRole($user, $validated['role_id'], $project, $tenantId);

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Không thể gán role dự án',
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Đã gán role dự án thành công',
            ],
        ], 201);
    }

    /**
     * GAP-042 §2a #6 / §2c category C — restores the already-live, already-wired
     * route `DELETE /api/v1/rbac/assignments/projects/{project}/users/{user}/roles/{role}`
     * (previously targeted a nonexistent method, unconditional HTTP 500).
     */
    public function removeProjectRole(Request $request, string $project, string $user, string $role): JsonResponse
    {
        $tenantId = TenantContext::id($request);

        if ($tenantId !== null) {
            $this->rbacManager->revokeRole($user, $role, 'project', $project, $tenantId);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'message' => 'Đã gỡ role dự án thành công',
            ],
        ]);
    }
}
