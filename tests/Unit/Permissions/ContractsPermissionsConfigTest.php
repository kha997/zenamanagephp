<?php declare(strict_types=1);

namespace Tests\Unit\Permissions;

use Tests\TestCase;

/**
 * Contracts Permissions Config Test
 * 
 * Round 35: Contracts RBAC Wiring & Test Stabilization
 * 
 * Locks in the contracts permissions configuration to ensure
 * tenant.view_contracts and tenant.manage_contracts are properly
 * mapped to all tenant roles.
 * 
 * @group permissions
 * @group contracts
 */
class ContractsPermissionsConfigTest extends TestCase
{
    /**
     * Test that owner and admin have manage_contracts permission
     */
    public function test_owner_and_admin_have_manage_contracts_permission(): void
    {
        $roles = config('permissions.tenant_roles');

        $this->assertIsArray($roles, 'tenant_roles config should be an array');
        $this->assertArrayHasKey('owner', $roles, 'tenant_roles should have owner role');
        $this->assertArrayHasKey('admin', $roles, 'tenant_roles should have admin role');

        $this->assertContains(
            'tenant.manage_contracts',
            $roles['owner'],
            'owner role should have tenant.manage_contracts permission'
        );

        $this->assertContains(
            'tenant.manage_contracts',
            $roles['admin'],
            'admin role should have tenant.manage_contracts permission'
        );
    }

    /**
     * Test that standard roles have view_contracts permission
     */
    public function test_standard_roles_have_view_contracts_permission(): void
    {
        $roles = config('permissions.tenant_roles');

        $this->assertIsArray($roles, 'tenant_roles config should be an array');

        foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
            $this->assertArrayHasKey(
                $role,
                $roles,
                "tenant_roles should have {$role} role"
            );

            $this->assertContains(
                'tenant.view_contracts',
                $roles[$role],
                "{$role} role should have tenant.view_contracts permission"
            );
        }
    }

    /**
     * Test that member and viewer do NOT have manage_contracts permission
     */
    public function test_member_and_viewer_do_not_have_manage_contracts_permission(): void
    {
        $roles = config('permissions.tenant_roles');

        $this->assertIsArray($roles, 'tenant_roles config should be an array');

        foreach (['member', 'viewer'] as $role) {
            $this->assertArrayHasKey(
                $role,
                $roles,
                "tenant_roles should have {$role} role"
            );

            $this->assertNotContains(
                'tenant.manage_contracts',
                $roles[$role],
                "{$role} role should NOT have tenant.manage_contracts permission"
            );
        }
    }

    /**
     * Test that contracts permissions are defined in tenant_permissions list
     * 
     * This ensures the permissions exist in the system and can be referenced.
     */
    public function test_contracts_permissions_are_defined(): void
    {
        $roles = config('permissions.tenant_roles');

        // Collect all permissions from all roles
        $allPermissions = [];
        foreach ($roles as $rolePermissions) {
            if (is_array($rolePermissions)) {
                $allPermissions = array_merge($allPermissions, $rolePermissions);
            }
        }

        $allPermissions = array_unique($allPermissions);

        $this->assertContains(
            'tenant.view_contracts',
            $allPermissions,
            'tenant.view_contracts should be defined in tenant_roles'
        );

        $this->assertContains(
            'tenant.manage_contracts',
            $allPermissions,
            'tenant.manage_contracts should be defined in tenant_roles'
        );
    }
}

