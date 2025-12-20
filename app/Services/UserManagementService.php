<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Role;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * UserManagementService
 * 
 * Unified service for all user management operations
 * Replaces multiple user controllers and services
 */
class UserManagementService
{
    use ServiceBaseTrait;

    protected string $modelClass = User::class;

    /**
     * Get users with pagination and filtering
     */
    public function getUsers(
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc',
        string|int|null $tenantId = null
    ): LengthAwarePaginator {
        $this->validateTenantAccess($tenantId);
        
        $query = User::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['tenant']);

        // Apply filters
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['role']) && $filters['role']) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Get user by ID with tenant isolation
     */
    public function getUserById(string|int $id, string|int|null $tenantId = null): ?User
    {
        $this->validateTenantAccess($tenantId);
        
        return User::with(['tenant'])
            ->where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Create new user
     */
    public function createUser(array $data, string|int|null $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);
        
        $this->validateUserData($data, 'create');
        
        $data['tenant_id'] = $tenantId ?? Auth::user()?->tenant_id;
        $data['password'] = Hash::make($data['password']);
        
        $user = User::create($data);
        
        $this->logCrudOperation('created', $user);
        
        return $user->load('tenant');
    }

    /**
     * Update user
     */
    public function updateUser(string|int $id, array $data, string|int|null $tenantId = null): User
    {
        $user = $this->resolveUserForAction($id, $tenantId);

        $updatedUser = $this->applyUserUpdate($user, $data);

        return $updatedUser->fresh()->load('tenant');
    }

    /**
     * Update user by flexible identifier (ULID or numeric)
     */
    public function updateUserByIdentifier(string|int $identifier, array $data, string|int|null $tenantId = null): User
    {
        $user = $this->resolveUserForAction($identifier, $tenantId);

        $updatedUser = $this->applyUserUpdate($user, $data);

        return $updatedUser->fresh()->load('tenant');
    }

    /**
     * Delete user
     */
    public function deleteUser(string|int $id, string|int|null $tenantId = null): bool
    {
        $user = $this->resolveUserForAction($id, $tenantId);

        // Prevent self-deletion
        if ($user->id === Auth::id()) {
            $this->logError('Self-deletion attempt', null, ['user_id' => $id]);
            abort(403, 'Cannot delete your own account');
        }
        
        $deleted = $user->delete();
        
        if ($deleted) {
            $this->logCrudOperation('deleted', $user);
        }
        
        return $deleted;
    }

    /**
     * Delete user by flexible identifier (ULID or numeric)
     */
    public function deleteUserByIdentifier(string|int $identifier, string|int|null $tenantId = null): bool
    {
        $user = $this->resolveUserForAction($identifier, $tenantId);

        if ($user->id === Auth::id()) {
            $this->logError('Self-deletion attempt', null, ['user_id' => $identifier]);
            abort(403, 'Cannot delete your own account');
        }
        
        $deleted = $user->delete();
        
        if ($deleted) {
            $this->logCrudOperation('deleted', $user);
        }
        
        return $deleted;
    }

    /**
     * Bulk delete users
     */
    public function bulkDeleteUsers(array $ids, string|int|null $tenantId = null): int
    {
        $this->validateTenantAccess($tenantId);
        
        // Prevent self-deletion
        $currentUserId = Auth::id();
        $ids = array_filter($ids, fn($id) => $id != $currentUserId);
        
        if (empty($ids)) {
            return 0;
        }
        
        $count = User::whereIn('id', $ids)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->delete();
        
        $this->logBulkOperation('deleted', User::class, $count);
        
        return $count;
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(string|int $id, string|int|null $tenantId = null): User
    {
        $user = $this->resolveUserForAction($id, $tenantId);

        $user->update(['is_active' => !$user->is_active]);
        
        $this->logCrudOperation('status_toggled', $user, [
            'new_status' => $user->is_active ? 'active' : 'inactive'
        ]);
        
        return $user->fresh()->load('tenant');
    }

    /**
     * Update user role
     */
    public function updateUserRole(string|int $id, string $role, string|int|null $tenantId = null): User
    {
        $user = $this->resolveUserForAction($id, $tenantId);

        $this->validateRole($role);
        
        $user->update(['role' => $role]);
        
        $this->logCrudOperation('role_updated', $user, [
            'new_role' => $role,
            'old_role' => $user->getOriginal('role')
        ]);
        
        return $user->fresh()->load('tenant');
    }

    /**
     * Get user statistics
     */
    public function getUserStats(string|int|null $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        return $this->getCached("user_stats", function() use ($tenantId) {
            $query = User::query()->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
            
            return [
                'total' => $query->count(),
                'active' => $query->where('is_active', true)->count(),
                'inactive' => $query->where('is_active', false)->count(),
                'by_role' => $query->groupBy('role')->selectRaw('role, count(*) as count')->pluck('count', 'role'),
                'created_this_month' => $query->whereMonth('created_at', now()->month)->count(),
                'last_login_today' => $query->whereDate('last_login_at', today())->count()
            ];
        }, 300);
    }

    /**
     * Search users
     */
    public function searchUsers(
        string $search,
        int $limit = 10,
        string|int|null $tenantId = null
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        return User::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Validate user data
     */
    protected function validateUserData(array $data, string $action, ?User $user = null): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user?->id)
            ],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_active' => ['sometimes', 'boolean']
        ];

        if ($action === 'create') {
            $rules['role'] = ['required', 'string', 'in:admin,member,client'];
        } else {
            $rules['role'] = ['sometimes', 'string', 'in:admin,member,client'];
            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['first_name'] = ['sometimes', 'string', 'max:255'];
            $rules['last_name'] = ['sometimes', 'string', 'max:255'];
            $rules['email'] = [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user?->id)
            ];
        }

        if ($action === 'create') {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        } elseif ($action === 'update' && isset($data['password'])) {
            $rules['password'] = ['string', 'min:8', 'confirmed'];
        }

        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            $this->logError('User validation failed', null, [
                'action' => $action,
                'errors' => $validator->errors()->toArray()
            ]);
            
            abort(422, 'Validation failed: ' . $validator->errors()->first());
        }
    }

    /**
     * Validate role
     */
    protected function validateRole(string $role): void
    {
        $validRoles = ['admin', 'member', 'client'];
        
        if (!in_array($role, $validRoles)) {
            $this->logError('Invalid role', null, ['role' => $role]);
            abort(422, 'Invalid role');
        }
    }

    /**
     * Apply sanitized updates to a user.
     */
    private function applyUserUpdate(User $user, array $data): User
    {
        $this->guardDisallowedUpdateFields($data);
        $this->validateUserData($data, 'update', $user);

        $allowedPayload = $this->filterAllowedUserUpdateFields($data);
        $roleIds = $this->resolveRoleIdsForSync($data);

        if (!empty($allowedPayload)) {
            $user->fill($allowedPayload);
            $user->save();
        }

        if (!empty($roleIds)) {
            $user->roles()->sync($roleIds);
        }

        $this->logCrudOperation('updated', $user);

        return $user;
    }

    /**
     * Prevent updates to sensitive fields.
     */
    private function guardDisallowedUpdateFields(array $data): void
    {
        $blocked = [
            'tenant_id',
            'id',
            'created_at',
            'updated_at',
            'deleted_at',
            'email_verified_at',
            'remember_token',
            'password',
            'password_confirmation',
            'current_password',
            'role',
        ];

        foreach ($blocked as $field) {
            if (array_key_exists($field, $data)) {
                $this->logError('Blocked sensitive field update', null, [
                    'field' => $field,
                    'payload' => $data[$field] ?? null,
                ]);

                throw new \InvalidArgumentException(sprintf('Field "%s" is not updatable via this action.', $field));
            }
        }
    }

    /**
     * Keep only the permitted user fields for updates.
     */
    private function filterAllowedUserUpdateFields(array $data): array
    {
        $allowed = [
            'name',
            'email',
            'first_name',
            'last_name',
            'is_active',
            'status',
            'phone',
            'avatar',
            'timezone',
            'language',
            'preferences',
            'department',
            'job_title',
            'manager',
        ];

        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * Normalize role IDs based on role_ids (IDs) or roles (names).
     */
    private function resolveRoleIdsForSync(array $data): array
    {
        if (isset($data['role_ids']) && is_array($data['role_ids'])) {
            $ids = array_values(array_filter($data['role_ids'], fn($value) => is_scalar($value)));
            if (!empty($ids)) {
                $uniqueIds = array_unique($ids);
                $existing = Role::whereIn('id', $uniqueIds)->pluck('id')->toArray();

                if (count($existing) !== count($uniqueIds)) {
                    throw new \InvalidArgumentException('Some requested roles do not exist.');
                }

                return $existing;
            }
        }

        if (isset($data['roles']) && is_array($data['roles'])) {
            $names = array_values(array_filter($data['roles'], fn($value) => is_scalar($value)));
            if (!empty($names)) {
                $uniqueNames = array_unique($names);
                $existing = Role::whereIn('name', $uniqueNames)->pluck('id')->toArray();

                if (count($existing) !== count($uniqueNames)) {
                    throw new \InvalidArgumentException('Some requested roles do not exist.');
                }

                return $existing;
            }
        }

        return [];
    }

    /**
     * Resolve a user for administrative actions while bypassing tenant scopes.
     */
    private function resolveUserForAction(string|int $identifier, string|int|null $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);

        $user = $this->getModel()
            ->newQueryWithoutScopes()
            ->with(['tenant'])
            ->where('id', $identifier)
            ->first();

        if (!$user) {
            $this->logError('User not found for action', null, [
                'identifier' => $identifier,
                'tenant_id' => $tenantId,
            ]);

            abort(404, 'Resource not found');
        }

        $this->validateModelOwnership($user, $tenantId);

        return $user;
    }

    /**
     * Get user preferences
     */
    public function getUserPreferences(string|int $userId, string|int|null $tenantId = null): array
    {
        $user = $this->resolveUserForAction($userId, $tenantId);
        
        return $user->preferences ?? [];
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(string|int $userId, array $preferences, string|int|null $tenantId = null): User
    {
        $user = $this->resolveUserForAction($userId, $tenantId);
        
        $user->update(['preferences' => $preferences]);
        
        $this->logCrudOperation('preferences_updated', $user);
        
        return $user->fresh();
    }
}
