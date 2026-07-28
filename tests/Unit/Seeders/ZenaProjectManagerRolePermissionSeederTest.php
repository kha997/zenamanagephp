<?php declare(strict_types=1);

namespace Tests\Unit\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZenaProjectManagerRolePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_role_receives_rfi_escalate_and_cancel_permissions(): void
    {
        $this->seed(\Database\Seeders\ZenaPermissionsSeeder::class);
        Role::firstOrCreate(['name' => 'project_manager'], ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true]);

        $this->seed(\Database\Seeders\ZenaProjectManagerRolePermissionSeeder::class);

        $pmRole = Role::where('name', 'project_manager')->first();
        $escalate = Permission::where('code', 'rfi.escalate')->first();
        $cancel = Permission::where('code', 'rfi.cancel')->first();

        $this->assertTrue($pmRole->permissions->contains($escalate->id));
        $this->assertTrue($pmRole->permissions->contains($cancel->id));
    }
}
