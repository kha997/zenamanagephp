<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    public function test_user_can_create_project_in_their_tenant()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $permission = Permission::firstOrCreate(
            ['code' => 'project.create'],
            [
                'name' => 'project.create',
                'module' => 'project',
                'action' => 'create',
                'description' => 'Create projects',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            [
                'scope' => 'system',
                'description' => 'System administrator for projects',
                'allow_override' => true,
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);

        $response = $this->actingAs($user)
            ->post('/api/projects', [
                'name' => 'Test Project',
                'description' => 'Test Description',
                'start_date' => now()->addDay()->toDateString(),
                'end_date' => now()->addMonth()->toDateString(),
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('projects', [
            'name' => 'Test Project',
            'tenant_id' => $tenant->id,
        ]);
    }
}
