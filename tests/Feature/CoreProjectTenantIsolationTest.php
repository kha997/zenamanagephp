<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Src\CoreProject\Models\Project;
use Tests\TestCase;

class CoreProjectTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_show_isolation_returns_not_found_for_other_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);

        $tenantB = Tenant::factory()->create();
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $permission = Permission::firstOrCreate(
            ['code' => 'project.view'],
            [
                'name' => 'project.view',
                'module' => 'project',
                'action' => 'view',
                'description' => 'View projects',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => 'project_viewer'],
            [
                'scope' => 'system',
                'description' => 'Tenant project viewer role',
                'allow_override' => false,
                'is_active' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $userB->roles()->syncWithoutDetaching($role->id);

        $loginResponse = $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $tenantB->id,
        ])->postJson('/api/auth/login', [
            'email' => $userB->email,
            'password' => 'password',
        ]);

        $loginResponse->assertStatus(200);

        $token = $loginResponse->json('data.token');
        $this->assertIsString($token);

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $tenantB->id,
            'Authorization' => 'Bearer ' . $token,
        ])->getJson("/api/v1/projects/{$projectA->id}");

        $response->assertStatus(404)
            ->assertJsonStructure(['status', 'message'])
            ->assertJson([
                'status' => 'error',
                'message' => 'Dự án không tồn tại.',
            ]);
    }
}
