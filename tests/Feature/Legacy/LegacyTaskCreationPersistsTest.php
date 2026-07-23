<?php declare(strict_types=1);

namespace Tests\Feature\Legacy;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LegacyTaskCreationPersistsTest extends TestCase
{
    use RefreshDatabase;

    private function makeUserWithTaskCreatePermission(Tenant $tenant): User
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password'),
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'project_manager', 'tenant_id' => $tenant->id],
            ['scope' => 'tenant', 'allow_override' => false, 'is_active' => true, 'description' => 'Test PM']
        );

        // Guard clause from ButtonCRUDTest to handle tenant-scoped role fixture reuse
        if ($role->tenant_id !== $tenant->id) {
            $role->fill(['tenant_id' => $tenant->id])->save();
        }

        $permission = Permission::firstOrCreate(
            ['code' => 'task.create'],
            ['name' => 'task.create', 'module' => 'task', 'action' => 'create']
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);

        return $user;
    }

    public function test_legacy_root_post_tasks_actually_creates_a_task(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->makeUserWithTaskCreatePermission($tenant);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Initialize session with CSRF token by GETting a page first
        $this->actingAs($user)->get('/app/tasks');

        $response = $this->actingAs($user)->post('/tasks', [
            'project_id' => $project->id,
            'name' => 'Legacy-route task',
            'description' => 'Created via the deprecated root /tasks endpoint',
        ]);

        $response->assertRedirect(route('app.tasks'));

        $this->assertDatabaseHas('tasks', [
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'name' => 'Legacy-route task',
        ]);
    }

    public function test_legacy_root_post_tasks_still_enforces_rbac(): void
    {
        $tenant = Tenant::factory()->create();
        // Deliberately no role/permission attached — this user has task.create denied.
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // Initialize session with CSRF token by GETting a page first
        $this->actingAs($user)->get('/app/tasks');

        $response = $this->actingAs($user)->post('/tasks', [
            'project_id' => $project->id,
            'name' => 'Should be blocked',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('tasks', [
            'name' => 'Should be blocked',
        ]);
    }
}
