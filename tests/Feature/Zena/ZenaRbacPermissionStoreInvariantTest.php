<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Models\Permission as AppPermission;
use App\Models\Role as AppRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaRbacPermissionStoreInvariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_rbac_checks_permission_name_field(): void
    {
        $permission = AppPermission::create([
            'code' => 'rfi.view',
            'name' => 'rfi.view',
            'module' => 'rfi',
            'action' => 'view',
            'description' => 'Smoke guard for RBAC permission name',
        ]);

        $role = AppRole::create([
            'name' => 'RBAC Guard Role',
            'scope' => AppRole::SCOPE_SYSTEM,
            'description' => 'Role created by RBAC invariant test',
            'allow_override' => false,
        ]);

        $role->permissions()->attach($permission->id);

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->assertTrue(
            $user->hasPermission('rfi.view'),
            'Expected hasPermission to rely on permission.name for rfi.view'
        );
    }
}
