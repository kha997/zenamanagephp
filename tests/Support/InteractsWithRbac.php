<?php declare(strict_types=1);

namespace Tests\Support;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

trait InteractsWithRbac
{
    /**
     * Minimal role catalog used by button tests.
     */
    protected array $rbacRoleDefinitions = [
        'super_admin' => [
            'name' => 'super_admin',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Super Administrator - full system access',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'admin' => [
            'name' => 'admin',
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'description' => 'Administrator - system access',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'project_manager' => [
            'name' => 'project_manager',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => true,
            'description' => 'Project Manager - tenant scoped',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'pm' => [
            'name' => 'pm',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'PM alias',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'designer' => [
            'name' => 'designer',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Designer - creative staff',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'site_engineer' => [
            'name' => 'site_engineer',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Site engineer',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'engineer' => [
            'name' => 'engineer',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Engineer alias',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'guest' => [
            'name' => 'guest',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Guest / viewer',
            'is_active' => true,
            'tenant_id' => null,
        ],
        'viewer' => [
            'name' => 'viewer',
            'scope' => Role::SCOPE_PROJECT,
            'allow_override' => false,
            'description' => 'Viewer - read only',
            'is_active' => true,
            'tenant_id' => null,
        ],
    ];

    /**
     * Role names that must always exist for the button tests.
     */
    protected array $requiredRoleNames = [
        'super_admin',
        'admin',
        'pm',
        'designer',
        'engineer',
        'guest',
    ];

    /**
     * Alias to name map for role assignments.
     */
    protected array $rbacRoleAssignment = [
        'super_admin' => ['super_admin'],
        'admin' => ['admin'],
        'pm' => ['pm', 'project_manager'],
        'project_manager' => ['project_manager'],
        'designer' => ['designer'],
        'engineer' => ['engineer', 'site_engineer'],
        'guest' => ['guest', 'viewer'],
    ];

    /**
     * Ensure required roles exist in the database.
     */
    protected function seedRolesAndPermissions(): void
    {
        foreach ($this->rbacRoleDefinitions as $definition) {
            Role::updateOrCreate(
                ['name' => $definition['name']],
                $definition
            );
        }

        foreach ($this->requiredRoleNames as $name) {
            if (Role::where('name', $name)->exists()) {
                continue;
            }

            $definition = $this->rbacRoleDefinitions[$name] ?? [
                'name' => $name,
                'scope' => Role::SCOPE_PROJECT,
                'allow_override' => false,
                'description' => Str::title(str_replace('_', ' ', $name)),
                'is_active' => true,
                'tenant_id' => null,
            ];

            Role::create($definition);
        }

        $this->seedTestPermissions();
    }

    /**
     * Permissions needed to satisfy button tests.
     *
     * @var array<string, string[]>
     */
    protected array $testPermissionAssignments = [
        'super_admin' => ['project.read', 'project.write', 'task.read', 'task.write'],
        'admin' => ['project.read', 'project.write', 'task.read', 'task.write'],
        'pm' => ['project.read', 'project.write', 'task.read', 'task.write'],
        'designer' => ['project.read', 'task.read'],
        'engineer' => ['project.read', 'task.read'],
        'guest' => ['project.read', 'task.read'],
    ];

    /**
     * Make sure the permissions needed for tests exist and are assigned.
     */
    protected function seedTestPermissions(): void
    {
        foreach ($this->testPermissionAssignments as $roleName => $permissionCodes) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                continue;
            }

            $permissionIds = [];

            foreach ($permissionCodes as $permissionCode) {
                $permissionIds[] = $this->ensurePermission($permissionCode)->id;
            }

            if ($permissionIds) {
                $role->permissions()->syncWithoutDetaching($permissionIds);
            }
        }
    }

    /**
     * Ensure a permission row exists for the given code.
     */
    protected function ensurePermission(string $permissionCode): Permission
    {
        [$module, $action] = array_pad(explode('.', $permissionCode, 2), 2, '');

        return Permission::firstOrCreate(
            ['code' => $permissionCode],
            [
                'module' => $module ?: 'general',
                'action' => $action ?: 'default',
                'description' => "Auto seeded permission for tests: {$permissionCode}",
            ]
        );
    }

    /**
     * Assign a single role by explicit role name to ensure exact mapping.
     */
    protected function assignRoleByName(User $user, string $roleName): void
    {
        $this->seedRolesAndPermissions();

        $role = Role::where('name', $roleName)->first();

        if (!$role) {
            throw new \RuntimeException("Role not found for key: {$roleName}");
        }

        $user->roles()->sync([$role->id]);
    }

    /**
     * Assign one or more roles to a user using the alias map.
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function assignRole(User $user, string $roleAlias): void
    {
        $this->seedRolesAndPermissions();

        if (!isset($this->rbacRoleAssignment[$roleAlias])) {
            throw new \InvalidArgumentException("Unknown RBAC alias: {$roleAlias}");
        }

        $roleNames = $this->rbacRoleAssignment[$roleAlias];
        $roleIds = Role::whereIn('name', $roleNames)->pluck('id');

        if ($roleIds->isEmpty()) {
            throw new \RuntimeException("No roles found for alias: {$roleAlias}");
        }

        $user->roles()->syncWithoutDetaching($roleIds->toArray());
    }

    /**
     * Create a user that belongs to the provided tenant and attach roles.
     */
    protected function createUserWithRole(string $roleAlias, Tenant $tenant, array $attributes = []): User
    {
        $password = $attributes['password'] ?? 'password';
        unset($attributes['password']);

        $defaults = [
            'name' => Str::title(str_replace('_', ' ', $roleAlias)) . ' User',
            'email' => "{$roleAlias}@test-" . uniqid() . ".example",
            'password' => Hash::make($password),
            'tenant_id' => $tenant->id,
        ];

        $user = User::create(array_merge($defaults, $attributes));

        $this->assignRoleByName($user, $roleAlias);

        return $user;
    }

    /**
     * Create the tenant context and authenticate via Sanctum for API requests.
     */
    protected function actingAsRole(string $roleAlias, ?Tenant $tenant = null, array $abilities = ['*'], array $attributes = []): User
    {
        $tenant = $tenant ?? Tenant::create([
            'name' => 'Temporary Tenant',
            'slug' => 'tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $user = $this->createUserWithRole($roleAlias, $tenant, $attributes);
        Sanctum::actingAs($user, $abilities);

        return $user;
    }
}
