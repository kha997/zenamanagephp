<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
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
        ?int $tenantId = null
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
    public function getUserById(int $id, ?int $tenantId = null): ?User
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
    public function createUser(array $data, ?int $tenantId = null): User
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
    public function updateUser(int $id, array $data, ?int $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
        $this->validateUserData($data, 'update', $user);
        
        // Handle password update
        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        
        $user->update($data);
        
        $this->logCrudOperation('updated', $user);
        
        return $user->fresh()->load('tenant');
    }

    /**
     * Delete user
     */
    public function deleteUser(int $id, ?int $tenantId = null): bool
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
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
     * Bulk delete users
     */
    public function bulkDeleteUsers(array $ids, ?int $tenantId = null): int
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
    public function toggleUserStatus(int $id, ?int $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
        $user->update(['is_active' => !$user->is_active]);
        
        $this->logCrudOperation('status_toggled', $user, [
            'new_status' => $user->is_active ? 'active' : 'inactive'
        ]);
        
        return $user->fresh()->load('tenant');
    }

    /**
     * Update user role
     */
    public function updateUserRole(int $id, string $role, ?int $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
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
    public function getUserStats(?int $tenantId = null): array
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
        ?int $tenantId = null
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
            'role' => ['required', 'string', 'in:admin,member,client'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
            'is_active' => ['sometimes', 'boolean']
        ];

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
     * Get user preferences
     */
    public function getUserPreferences(int $userId, ?int $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($userId, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
        return $user->preferences ?? [];
    }

    /**
     * Update user preferences
     */
    public function updateUserPreferences(int $userId, array $preferences, ?int $tenantId = null): User
    {
        $this->validateTenantAccess($tenantId);
        
        $user = $this->findByIdOrFail($userId, $tenantId);
        $this->validateModelOwnership($user, $tenantId);
        
        $user->update(['preferences' => $preferences]);
        
        $this->logCrudOperation('preferences_updated', $user);
        
        return $user->fresh();
    }
}