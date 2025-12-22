<?php

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

trait GrantsCostPermissionsTrait
{
    /**
     * Grant canonical projects.cost.* permissions to a user for tests.
     *
     * Usage:
     *   $this->grantCostPermissions($user); // view+edit
     *   $this->grantCostPermissions($user, ['projects.cost.view']); // custom
     */
    protected function grantCostPermissions(User $user, array $codes = ['projects.cost.view', 'projects.cost.edit']): User
    {
        $codes = array_values(array_unique(array_filter($codes)));

        $this->assertPermissionCodesExistInConfig($codes);

        // Always create a unique helper role so sqlite cannot hit UNIQUE(name) collisions within tests.
        $tenantKey = (string) ($user->tenant_id ?? 'global');
        $uniqueId = (string) Str::ulid();
        $roleCode = 'test.cost.grants.' . $tenantKey . '.' . $uniqueId;
        $roleName = 'Test Cost Grants (' . $tenantKey . ') ' . $uniqueId;

        /** @var Role $role */
        $role = Role::query()->create([
            'code' => $roleCode,
            'name' => $roleName,
            'description' => 'Test-only helper role for granting cost permissions',
        ]);

        // Ensure permission rows exist (idempotent)
        $permissionIds = [];
        foreach ($codes as $code) {
            /** @var Permission $perm */
            $perm = Permission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'module' => 'projects',
                    'action' => $code,
                    'description' => 'Test-generated permission: ' . $code,
                ]
            );

            $permissionIds[] = $perm->id;
        }

        // Attach permissions to role, and role to user
        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);

        Cache::flush();

        return $user->fresh();
    }

    private function assertPermissionCodesExistInConfig(array $codes): void
    {
        $cfg = config('permissions');

        $flatCodes = $this->flattenPermissionStrings($cfg);
        $flatCodes = array_values(array_unique(array_filter($flatCodes)));

        foreach ($codes as $code) {
            if (!in_array($code, $flatCodes, true)) {
                throw new RuntimeException("Non-canonical permission code requested in GrantsCostPermissionsTrait: {$code}");
            }
        }
    }

    private function flattenPermissionStrings(mixed $node): array
    {
        $out = [];

        if (is_string($node)) {
            $out[] = $node;
            return $out;
        }

        if (!is_array($node)) {
            return $out;
        }

        if (isset($node['code']) && is_string($node['code'])) {
            $out[] = $node['code'];
        }

        foreach ($node as $v) {
            $out = array_merge($out, $this->flattenPermissionStrings($v));
        }

        return $out;
    }
}
