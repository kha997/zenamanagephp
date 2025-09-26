<?php

declare(strict_types=1);

namespace App\Services;
use Illuminate\Support\Facades\Auth;


use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * User Management Service.
 *
 * Handles user-related business logic and operations
 */
class UserManagementService
{
    /**
     * Get filtered and paginated users.
     */
    public function getUsers(Request $request): array
    {
        $query = User::with(['tenant']);

        // Apply tenant filter
        $tenantId = $this->getCurrentTenantId($request);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Apply status filter
        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        // Apply search filter
        if ($request->has('search')) {
            $this->applySearchFilter($query, $request->get('search'));
        }

        // Apply sorting
        $this->applySorting($query, $request);

        // Apply pagination
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return [
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ];
    }

    /**
     * Create a new user.
     */
    public function createUser(Request $request): array
    {
        $validator = $this->getCreateUserValidator($request);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . $validator->errors()->first());
        }

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'tenant_id' => $request->input('tenant_id'),
            'status' => 'active',
        ]);

        return [
            'user' => $user->load('tenant'),
            'message' => 'User đã được tạo thành công',
        ];
    }

    /**
     * Get user by ID with tenant access check.
     */
    public function getUserById(Request $request, string $id): User
    {
        $user = User::with(['tenant'])->findOrFail($id);

        // Check tenant access
        $tenantId = $this->getCurrentTenantId($request);
        if ($tenantId && $user->tenant_id !== $tenantId) {
            throw new \UnauthorizedHttpException('', 'Không có quyền truy cập user này');
        }

        return $user;
    }

    /**
     * Update user with validation and tenant access check.
     */
    public function updateUser(Request $request, string $id): array
    {
        $user = User::findOrFail($id);

        // Check tenant access
        $tenantId = $this->getCurrentTenantId($request);
        if ($tenantId && $user->tenant_id !== $tenantId) {
            throw new \UnauthorizedHttpException('', 'Không có quyền cập nhật user này');
        }

        $validator = $this->getUpdateUserValidator($request, $id);

        if ($validator->fails()) {
            throw new \InvalidArgumentException('Validation failed: ' . $validator->errors()->first());
        }

        $updateData = $this->prepareUpdateData($request);

        if ($request->has('password')) {
            $updateData['password'] = Hash::make($request->input('password'));
        }

        $user->update($updateData);

        return [
            'user' => $user->load('tenant'),
            'message' => 'User đã được cập nhật thành công',
        ];
    }

    /**
     * Delete user with tenant access check.
     */
    public function deleteUser(Request $request, string $id): array
    {
        $user = User::findOrFail($id);

        // Check tenant access
        $tenantId = $this->getCurrentTenantId($request);
        if ($tenantId && $user->tenant_id !== $tenantId) {
            throw new \UnauthorizedHttpException('', 'Không có quyền xóa user này');
        }

        $user->delete();

        return [
            'message' => 'User đã được xóa thành công',
        ];
    }

    /**
     * Apply search filter to query.
     */
    private function applySearchFilter($query, string $search): void
    {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting($query, Request $request): void
    {
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
    }

    /**
     * Get validator for user creation.
     */
    private function getCreateUserValidator(Request $request): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'tenant_id' => 'required|exists:tenants,id',
        ]);
    }

    /**
     * Get validator for user update.
     */
    private function getUpdateUserValidator(Request $request, string $id): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'password' => 'sometimes|required|string|min:8|confirmed',
            'status' => 'sometimes|required|in:active,inactive,suspended',
        ]);
    }

    /**
     * Prepare update data from request.
     */
    private function prepareUpdateData(Request $request): array
    {
        return $request->only(['name', 'email', 'status']);
    }

    /**
     * Get current tenant ID from request.
     */
    private function getCurrentTenantId(Request $request): ?string
    {
        // This method should be implemented based on your tenant resolution logic
        return $request->get('tenant_context') ?? Auth::user()?->tenant_id;
    }
}
