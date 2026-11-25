<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait TenantUserFactoryTrait
{
    protected function createTenantUser(Tenant $tenant, array $attributes = [], ?array $roles = null, array $permissions = []): User
    {
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attributes));

        $this->assignApiRoles(
            $user,
            $roles ?? ['super_admin', 'Admin'],
            $permissions
        );

        return $user;
    }

    private function assignApiRoles(User $user, array $roles = [], array $permissions = []): void
    {
        $defaultPermissions = ['project.read', 'project.write'];
        $permissions = array_unique(array_merge($defaultPermissions, $permissions));

        foreach ($roles as $roleName) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                [
                    'scope' => 'system',
                    'description' => Str::title(str_replace('_', ' ', $roleName)),
                    'is_active' => true,
                ]
            );

            $user->roles()->syncWithoutDetaching($role->id);
            $this->ensurePermissionAttached($role, $permissions);

            if (method_exists($user, 'systemRoles')) {
                $user->systemRoles()->syncWithoutDetaching($role->id);
            }
        }
    }

    /**
     * OWN-2026-024 H3: give $user exactly $codes through a test-owned role (never the role named super_admin), for codes the Owner
     * role must not hold under Gate 2 D3 (task.assign, change_request.create, component.*) or that a test needs in isolation.
     *
     * @param list<string> $codes
     */
    protected function grantThroughTestRole(User $user, array $codes, string $roleName = 'o17h-h3-route-fixture'): Role
    {
        $role = Role::firstOrCreate(['name' => $roleName], ['scope' => 'custom', 'is_active' => true, 'description' => 'H3 test fixture']);
        $this->ensurePermissionAttached($role, $codes);
        $user->roles()->syncWithoutDetaching($role->id);

        return $role;
    }

    /**
     * OWN-2026-024 H3: make $user a Platform Operator for exactly $platformCodes — an explicit platform.* grant through a
     * test-owned role PLUS an active operator ledger row (both are required since H2b/H3; in real life the ledger row is
     * written only by the server-console `platform:operator grant`). Never the role named super_admin.
     *
     * @param list<string> $platformCodes
     */
    protected function provisionTestPlatformOperator(User $user, array $platformCodes): void
    {
        foreach ($platformCodes as $code) {
            if (!\App\Support\PlatformPermissions::isPlatformCode($code)) {
                throw new \InvalidArgumentException("{$code} is not a platform code");
            }
        }
        $this->grantThroughTestRole($user, $platformCodes, 'o17h-h3-platform-operator-fixture');
        \Illuminate\Support\Facades\DB::table('platform_operators')->insertOrIgnore([
            'id' => (string) Str::ulid(), 'user_id' => (string) $user->id, 'granted_by_actor' => 'test-fixture', 'reason' => 'H3 test operator',
            'approval_reference' => 'test', 'granted_at' => now(), 'active_key' => (string) $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function ensurePermissionAttached(Role $role, array $permissionNames): void
    {
        foreach ($permissionNames as $permissionName) {
            $parts = explode('.', $permissionName);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionName],
                [
                    'name' => $permissionName,
                    'module' => $parts[0] ?? $permissionName,
                    'action' => $parts[1] ?? '*',
                    'description' => ucfirst(str_replace('.', ' ', $permissionName)),
                ]
            );

            $role->permissions()->syncWithoutDetaching($permission->id);
        }
    }
}
