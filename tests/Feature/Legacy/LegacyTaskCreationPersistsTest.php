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

        // A valid CSRF token requires an established session — GET a page
        // first (see tests/TestCase.php:70-72: auto-appended tokens must
        // come from a real session, not a synthesized one).
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
        // task.create must exist as a real Permission row so the middleware's
        // denial goes through the "user lacks this granted permission" path
        // (RoleBasedAccessControlMiddleware::checkPermission) rather than the
        // separate "permission code doesn't exist at all" default-deny path.
        Permission::firstOrCreate(
            ['code' => 'task.create'],
            ['name' => 'task.create', 'module' => 'task', 'action' => 'create']
        );
        // Deliberately no role attached — this user has task.create denied.
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        // A valid CSRF token requires an established session — GET a page
        // first (see tests/TestCase.php:70-72: auto-appended tokens must
        // come from a real session, not a synthesized one).
        $this->actingAs($user)->get('/app/tasks');

        $response = $this->actingAs($user)->post('/tasks', [
            'project_id' => $project->id,
            'name' => 'Should be blocked',
        ]);

        // RoleBasedAccessControlMiddleware::deny() sends non-JSON (web) requests
        // a redirect back with a flash error, not a raw 403 — see
        // app/Http/Middleware/RoleBasedAccessControlMiddleware.php.
        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('tasks', [
            'name' => 'Should be blocked',
        ]);
    }
}
