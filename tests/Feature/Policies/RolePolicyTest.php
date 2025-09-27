<?php

namespace Tests\Feature\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $projectManager;
    protected User $member;
    protected Tenant $tenant;
    protected Tenant $otherTenant;
    protected Role $role;
    protected Role $otherTenantRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        $this->admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->admin->assignRole('admin');

        $this->projectManager = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->projectManager->assignRole('project_manager');

        $this->member = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->member->assignRole('member');

        $this->role = Role::factory()->create([
            'name' => 'test_role',
            'tenant_id' => $this->tenant->id
        ]);

        $this->otherTenantRole = Role::factory()->create([
            'name' => 'other_tenant_role',
            'tenant_id' => $this->otherTenant->id
        ]);
    }

    public function test_admin_and_project_manager_can_view_any_roles()
    {
        $this->assertTrue($this->admin->can('viewAny', Role::class));
        $this->assertTrue($this->projectManager->can('viewAny', Role::class));
        $this->assertFalse($this->member->can('viewAny', Role::class));
    }

    public function test_admin_can_view_any_role()
    {
        $this->assertTrue($this->admin->can('view', $this->role));
        $this->assertTrue($this->admin->can('view', $this->otherTenantRole));
    }

    public function test_project_manager_can_view_tenant_roles()
    {
        $this->assertTrue($this->projectManager->can('view', $this->role));
        $this->assertFalse($this->projectManager->can('view', $this->otherTenantRole));
    }

    public function test_member_cannot_view_roles()
    {
        $this->assertFalse($this->member->can('view', $this->role));
        $this->assertFalse($this->member->can('view', $this->otherTenantRole));
    }

    public function test_only_admin_can_create_roles()
    {
        $this->assertTrue($this->admin->can('create', Role::class));
        $this->assertFalse($this->projectManager->can('create', Role::class));
        $this->assertFalse($this->member->can('create', Role::class));
    }

    public function test_admin_can_update_any_role()
    {
        $this->assertTrue($this->admin->can('update', $this->role));
        $this->assertTrue($this->admin->can('update', $this->otherTenantRole));
    }

    public function test_project_manager_can_update_tenant_roles()
    {
        $this->assertTrue($this->projectManager->can('update', $this->role));
        $this->assertFalse($this->projectManager->can('update', $this->otherTenantRole));
    }

    public function test_member_cannot_update_roles()
    {
        $this->assertFalse($this->member->can('update', $this->role));
        $this->assertFalse($this->member->can('update', $this->otherTenantRole));
    }

    public function test_only_admin_can_delete_roles()
    {
        $this->assertTrue($this->admin->can('delete', $this->role));
        $this->assertTrue($this->admin->can('delete', $this->otherTenantRole));
        $this->assertFalse($this->projectManager->can('delete', $this->role));
        $this->assertFalse($this->member->can('delete', $this->role));
    }

    public function test_only_admin_can_restore_roles()
    {
        $this->assertTrue($this->admin->can('restore', $this->role));
        $this->assertFalse($this->projectManager->can('restore', $this->role));
        $this->assertFalse($this->member->can('restore', $this->role));
    }

    public function test_only_admin_can_force_delete_roles()
    {
        $this->assertTrue($this->admin->can('forceDelete', $this->role));
        $this->assertFalse($this->projectManager->can('forceDelete', $this->role));
        $this->assertFalse($this->member->can('forceDelete', $this->role));
    }

    public function test_admin_and_project_manager_can_assign_roles()
    {
        $this->assertTrue($this->admin->can('assign', Role::class));
        $this->assertTrue($this->projectManager->can('assign', Role::class));
        $this->assertFalse($this->member->can('assign', Role::class));
    }
}
