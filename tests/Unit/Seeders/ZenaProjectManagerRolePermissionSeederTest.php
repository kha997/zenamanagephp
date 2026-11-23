<?php

declare(strict_types=1);

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

    public function test_seeded_project_manager_and_member_roles_receive_rfi_grants(): void
    {
        $this->seed(\Database\Seeders\ZenaPermissionsSeeder::class);
        $pmRole = Role::firstOrCreate(['name' => 'Project Manager'], ['scope' => 'system', 'description' => 'Project Manager', 'is_active' => true]);
        $memberRole = Role::firstOrCreate(['name' => 'Project Member'], ['scope' => 'system', 'description' => 'Project Member', 'is_active' => true]);

        $this->seed(\Database\Seeders\ZenaProjectManagerRolePermissionSeeder::class);

        $codes = static fn (Role $role): array => $role->fresh()->permissions->pluck('code')->sort()->values()->all();

        // O-18 (Owner decision 2026-11-24, Option D incl. Project Manager): both roles also receive the six
        // operational codes in O18_OPERATIONAL_PERMISSION_CODES; nothing else was added.
        $o18 = ['design-item.view', 'material-receipt.view', 'material.read', 'material.view', 'site_diary.view', 'task.view'];
        $expectedPm = array_merge(['rfi.cancel', 'rfi.escalate', 'rfi.resolve-escalation', 'work.approve', 'work.export', 'work.update', 'work.view'], $o18);
        $expectedMember = array_merge(['rfi.resolve-escalation', 'work.update', 'work.view'], $o18);
        sort($expectedPm);
        sort($expectedMember);

        $this->assertSame($expectedPm, $codes($pmRole));
        $this->assertSame($expectedMember, $codes($memberRole));
    }
}
