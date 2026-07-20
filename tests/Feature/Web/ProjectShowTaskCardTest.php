<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectShowTaskCardTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_card_shows_canonical_task_not_legacy_relation(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $role = Role::factory()->create(['name' => 'Test Viewer Role ' . uniqid()]);
        $permission = Permission::where('code', 'project.view')->first()
            ?? Permission::factory()->create(['code' => 'project.view', 'name' => 'project.view']);
        $role->permissions()->sync([$permission->id]);
        UserRole::query()->create(['user_id' => (string) $user->id, 'role_id' => (string) $role->id]);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        // Use raw DB insert to bypass factory's nested Tenant/Project resolution
        \Illuminate\Support\Facades\DB::table('tasks')->insert([
            'id' => \Illuminate\Support\Str::ulid()->toString(),
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Task Canonical Xuất Hiện',
            'title' => 'Task Canonical Xuất Hiện',
            'status' => 'pending',
            'priority' => 'medium',
            'progress_percent' => 0,
            'is_hidden' => false,
            'visibility' => 'public',
            'client_approved' => false,
            'estimated_hours' => 0,
            'actual_hours' => 0,
            'estimated_cost' => 0,
            'actual_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response->assertOk();
        $response->assertSee('Task Canonical Xuất Hiện');
    }
}
