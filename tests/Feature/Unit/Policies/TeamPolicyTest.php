<?php

namespace Tests\Feature\Unit\Policies;

use App\Models\User;
use App\Models\Team;
use App\Models\Tenant;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeamPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected $policy;
    protected $tenant;
    protected $user;
    protected $team;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->policy = new TeamPolicy();
        
        $this->tenant = Tenant::factory()->create([
            'slug' => 'test-tenant-' . uniqid(),
            'name' => 'Test Tenant'
        ]);
        
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example-' . uniqid() . '.com'
        ]);
        
        $this->team = Team::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Team'
        ]);
    }

    public function test_user_can_view_team_with_proper_role()
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

        $this->assertTrue($this->policy->view($this->user, $this->team));
    }

    public function test_user_cannot_view_team_in_different_tenant()
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant-' . uniqid()]);
        $otherTeam = Team::factory()->create(['tenant_id' => $otherTenant->id]);
        
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

        $this->assertFalse($this->policy->view($this->user, $otherTeam));
    }

    public function test_user_can_create_team_with_proper_role()
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

    public function test_user_can_invite_members_with_proper_role()
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

        $this->assertTrue($this->policy->invite($this->user, $this->team));
    }

    public function test_user_can_delete_team_with_admin_role()
    {
        // Create role if it doesn't exist
        $role = \App\Models\Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'system',
                'allow_override' => true,
                'description' => 'Admin - System administration',
            ]
        );

        // Manually insert role assignment
        \DB::table('user_roles')->insert([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->assertTrue($this->policy->delete($this->user, $this->team));
    }
}