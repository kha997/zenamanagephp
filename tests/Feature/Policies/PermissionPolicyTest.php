<?php

namespace Tests\Feature\Policies;

use App\Models\Permission;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $projectManager;
    protected User $member;
    protected Tenant $tenant;
    protected Permission $permission;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->projectManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projectManager->assignRole('project_manager');

        $this->member = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->member->assignRole('member');

        $this->permission = Permission::factory()->create([
            'code' => 'test.permission',
            'module' => 'test',
            'action' => 'permission'
        ]);
    }

    public function test_only_admin_can_view_any_permissions()
    {
        $this->assertTrue($this->admin->can('viewAny', Permission::class));
        $this->assertFalse($this->projectManager->can('viewAny', Permission::class));
        $this->assertFalse($this->member->can('viewAny', Permission::class));
    }

    public function test_only_admin_can_view_permission()
    {
        $this->assertTrue($this->admin->can('view', $this->permission));
        $this->assertFalse($this->projectManager->can('view', $this->permission));
        $this->assertFalse($this->member->can('view', $this->permission));
    }

    public function test_only_admin_can_create_permissions()
    {
        $this->assertTrue($this->admin->can('create', Permission::class));
        $this->assertFalse($this->projectManager->can('create', Permission::class));
        $this->assertFalse($this->member->can('create', Permission::class));
    }

    public function test_only_admin_can_update_permissions()
    {
        $this->assertTrue($this->admin->can('update', $this->permission));
        $this->assertFalse($this->projectManager->can('update', $this->permission));
        $this->assertFalse($this->member->can('update', $this->permission));
    }

    public function test_only_admin_can_delete_permissions()
    {
        $this->assertTrue($this->admin->can('delete', $this->permission));
        $this->assertFalse($this->projectManager->can('delete', $this->permission));
        $this->assertFalse($this->member->can('delete', $this->permission));
    }

    public function test_only_admin_can_restore_permissions()
    {
        $this->assertTrue($this->admin->can('restore', $this->permission));
        $this->assertFalse($this->projectManager->can('restore', $this->permission));
        $this->assertFalse($this->member->can('restore', $this->permission));
    }

    public function test_only_admin_can_force_delete_permissions()
    {
        $this->assertTrue($this->admin->can('forceDelete', $this->permission));
        $this->assertFalse($this->projectManager->can('forceDelete', $this->permission));
        $this->assertFalse($this->member->can('forceDelete', $this->permission));
    }

    public function test_only_admin_can_assign_permissions()
    {
        $this->assertTrue($this->admin->can('assign', Permission::class));
        $this->assertFalse($this->projectManager->can('assign', Permission::class));
        $this->assertFalse($this->member->can('assign', Permission::class));
    }
}
