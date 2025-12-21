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

        $flatCodes = $this->flattenPermissionStrings($cfg);
        $flatCodes = array_values(array_unique(array_filter($flatCodes)));

        foreach ($codes as $code) {
            if (!in_array($code, $flatCodes, true)) {
                throw new RuntimeException("Non-canonical permission code requested in GrantsCostPermissionsTrait: {$code}");
            }
        }
    }

    /**
     * Recursively collect permission strings from config/permissions.php.
     *
     * Supports common shapes:
     * - ['roles' => ['pm' => ['projects.cost.view', ...], ...]]
     * - ['modules' => [ 'projects' => [ ['code'=>'...'], ... ]]]
     * - ['permissions' => [ ['code'=>'...'], ... ]]
     * - or any nested mixed arrays.
     */
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

        // If an item has ['code' => '...'] include it
        if (isset($node['code']) && is_string($node['code'])) {
            $out[] = $node['code'];
        }

        foreach ($node as $k => $v) {
            // Ignore comments / non-data keys if any
            $out = array_merge($out, $this->flattenPermissionStrings($v));
        }

        return $out;
    }
}
