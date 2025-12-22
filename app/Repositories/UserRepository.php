<?php

namespace App\Repositories;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserRepository
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    /**
     * Get all users with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
            });
        }

        return $query->with(['roles', 'tenant'])->paginate($perPage);
    }

    /**
     * Get user by ID.
     */
    public function getById(string $id): ?User
    {
        return $this->model->with(['roles', 'tenant'])->find($id);
    }

    /**
     * Get user by email.
     */
    public function getByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Get users by tenant ID.
     */
    public function getByTenantId(string $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['roles', 'tenant'])
                          ->get();
    }

    /**
     * Get users by role.
     */
    public function getByRole(string $role): Collection
    {
        return $this->model->whereHas('roles', function ($q) use ($role) {
            $q->where('name', $role);
        })->with(['roles', 'tenant'])->get();
    }

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user = $this->model->create($data);

        // Assign roles if provided
        if (isset($data['roles'])) {
            $user->roles()->sync($data['roles']);
        }

        Log::info('User created', [
            'user_id' => $user->id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        return $user->load(['roles', 'tenant']);
    }

    /**
     * Update user.
     */
    public function update(string $id, array $data): ?User
    {
        $user = $this->model->find($id);

        if (!$user) {
            return null;
        }

        // Hash password if provided
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        // Update roles if provided
        if (isset($data['roles'])) {
            $user->roles()->sync($data['roles']);
        }

        Log::info('User updated', [
            'user_id' => $user->id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        return $user->load(['roles', 'tenant']);
    }

    /**
     * Delete user.
     */
    public function delete(string $id): bool
    {
        $user = $this->model->find($id);

        if (!$user) {
            return false;
        }

        $user->delete();

        Log::info('User deleted', [
            'user_id' => $id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        return true;
    }

    /**
     * Soft delete user.
     */
    public function softDelete(string $id): bool
    {
        $user = $this->model->find($id);

        if (!$user) {
            return false;
        }

        $user->delete();

        Log::info('User soft deleted', [
            'user_id' => $id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted user.
     */
    public function restore(string $id): bool
    {
        $user = $this->model->withTrashed()->find($id);

        if (!$user) {
            return false;
        }

        $user->restore();

        Log::info('User restored', [
            'user_id' => $id,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id
        ]);

        return true;
    }

    /**
     * Get active users.
     */
    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')
                          ->with(['roles', 'tenant'])
                          ->get();
    }

    /**
     * Get inactive users.
     */
    public function getInactive(): Collection
    {
        return $this->model->where('status', 'inactive')
                          ->with(['roles', 'tenant'])
                          ->get();
    }

    /**
     * Get users with last login date.
     */
    public function getWithLastLogin(): Collection
    {
        return $this->model->whereNotNull('last_login_at')
                          ->with(['roles', 'tenant'])
                          ->orderBy('last_login_at', 'desc')
                          ->get();
    }

    /**
     * Get users without login for specified days.
     */
    public function getWithoutLogin(int $days = 30): Collection
    {
        $date = now()->subDays($days);

        return $this->model->where(function ($q) use ($date) {
            $q->whereNull('last_login_at')
              ->orWhere('last_login_at', '<', $date);
        })->with(['roles', 'tenant'])->get();
    }

    /**
     * Update last login.
     */
    public function updateLastLogin($id, string $ip = null): bool
    {
        $user = $this->model->find($id);

        if (!$user) {
            return false;
        }

        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => $ip
        ]);

        return true;
    }

    /**
     * Change user password.
     */
    public function changePassword($id, string $newPassword): bool
    {
        $user = $this->model->find($id);

        if (!$user) {
            return false;
        }

        $user->update([
            'password' => Hash::make($newPassword),
            'password_updated_at' => now()
        ]);

        Log::info('User password changed', [
            'user_id' => $id,
            'email' => $user->email
        ]);

        return true;
    }

    /**
     * Verify user password.
     */
    public function verifyPassword($id, string $password): bool
    {
        $user = $this->model->find($id);

        if (!$user) {
            return false;
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Get user statistics.
     */
    public function getStatistics($tenantId = null): array
    {
        $query = $this->model->query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return [
            'total_users' => $query->count(),
            'active_users' => $query->where('status', 'active')->count(),
            'inactive_users' => $query->where('status', 'inactive')->count(),
            'users_with_login' => $query->whereNotNull('last_login_at')->count(),
            'users_without_login' => $query->whereNull('last_login_at')->count(),
            'recent_logins' => $query->where('last_login_at', '>=', now()->subDays(7))->count()
        ];
    }

    /**
     * Search users.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('email', 'like', '%' . $term . '%');
        })->with(['roles', 'tenant'])
          ->limit($limit)
          ->get();
    }

    /**
     * Get users by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['roles', 'tenant'])
                          ->get();
    }

    /**
     * Bulk update users.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Users bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete users.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Users bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get user permissions.
     */
    public function getPermissions($id): array
    {
        $user = $this->model->with('roles.permissions')->find($id);

        if (!$user) {
            return [];
        }

        $permissions = [];

        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $permissions[] = $permission->name;
            }
        }

        return array_unique($permissions);
    }

    /**
     * Check if user has permission.
     */
    public function hasPermission($id, string $permission): bool
    {
        $permissions = $this->getPermissions($id);
        return in_array($permission, $permissions);
    }

    /**
     * Get user roles.
     */
    public function getRoles($id): array
    {
        $user = $this->model->with('roles')->find($id);

        if (!$user) {
            return [];
        }

        return $user->roles->pluck('name')->toArray();
    }

    /**
     * Check if user has role.
     */
    public function hasRole($id, string $role): bool
    {
        $roles = $this->getRoles($id);
        return in_array($role, $roles);
    }
}
