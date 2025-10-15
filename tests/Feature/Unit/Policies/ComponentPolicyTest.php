<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\Component;
use App\Models\Tenant;
use App\Policies\ComponentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComponentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $component;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new ComponentPolicy();
        
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
        
        $this->component = Component::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Component'
        ]);
    }

    public function test_user_can_view_component_in_same_tenant()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->view($this->user, $this->component));
    }

    public function test_user_cannot_view_component_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-' . uniqid()]);
        $otherComponent = Component::factory()->create(['tenant_id' => $otherTenant->id]);
        
        $this->user->assignRole('pm');
        $this->assertFalse($this->policy->view($this->user, $otherComponent));
    }

    public function test_user_can_create_component_with_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->create($this->user));
    }

    public function test_user_cannot_create_component_without_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'guest'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Guest - Limited access',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertFalse($this->policy->create($this->user));
    }

    public function test_user_can_update_component_with_proper_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'project_manager'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Project Manager - Project management',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->update($this->user, $this->component));
    }

    public function test_user_can_delete_component_with_admin_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'project',
                'allow_override' => false,
                'description' => 'Admin - Administrative access',
            ]
        );
        
        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->assertTrue($this->policy->delete($this->user, $this->component));
    }

    public function test_user_cannot_delete_component_without_admin_role()
    {
        $this->user->assignRole('pm');
        $this->assertFalse($this->policy->delete($this->user, $this->component));
    }
}