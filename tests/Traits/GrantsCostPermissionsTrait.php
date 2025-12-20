<?php

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
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

        // Deterministic test role
        /** @var Role $role */
        $role = Role::query()->updateOrCreate(
            ['code' => 'test.cost.grants'],
            [
                'name' => 'Test Cost Grants',
                'description' => 'Test-only helper role for granting cost permissions',
            ]
        );

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

        // Clear any cached RBAC results between assertions
        Cache::flush();

        return $user->fresh();
    }

    /**
     * Verify permission codes exist in config/permissions.php.
     * This prevents helpers from silently inventing new permission strings.
     */
    private function assertPermissionCodesExistInConfig(array $codes): void
    {
        $cfg = config('permissions');
        $flatCodes = [];

        if (is_array($cfg)) {
            $iter = [];

            // common shapes:
            // - ['permissions' => [ ['code' => '...'], ... ]]
            // - ['modules' => [ 'projects' => [ ['code' => '...'], ... ]]]
            if (isset($cfg['permissions']) && is_array($cfg['permissions'])) {
                $iter = $cfg['permissions'];
            } elseif (isset($cfg['modules']) && is_array($cfg['modules'])) {
                foreach ($cfg['modules'] as $modulePerms) {
                    if (is_array($modulePerms)) {
                        $iter = array_merge($iter, $modulePerms);
                    }
                }
            } else {
                $iter = $cfg;
            }

            foreach ($iter as $item) {
                if (is_array($item) && isset($item['code'])) {
                    $flatCodes[] = (string) $item['code'];
                }
            }
        }

        $flatCodes = array_values(array_unique(array_filter($flatCodes)));

        foreach ($codes as $code) {
            if (!in_array($code, $flatCodes, true)) {
                throw new RuntimeException("Non-canonical permission code requested in GrantsCostPermissionsTrait: {$code}");
            }
        }
    }
}

