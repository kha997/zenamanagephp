<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteOwnershipReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_project_show_is_html_owned(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Ownership HTML Project',
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response
            ->assertOk()
            ->assertViewIs('projects.show')
            ->assertSee('Ownership HTML Project')
            ->assertSee('Project Details');
    }

    public function test_legacy_project_and_task_get_routes_redirect_to_app_namespace(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)->get('/projects')->assertRedirect('/app/projects');
        $this->actingAs($user)->get('/projects/create')->assertRedirect('/app/projects/create');
        $this->actingAs($user)->get('/tasks')->assertRedirect('/app/tasks');
        $this->actingAs($user)->get('/tasks/create')->assertRedirect('/app/tasks/create');
    }

    public function test_project_show_renders_canonical_app_links(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Canonical Links Project',
        ]);

        $response = $this->actingAs($user)->get("/app/projects/{$project->id}");

        $response
            ->assertOk()
            ->assertSee("/app/projects/{$project->id}/edit", false)
            ->assertSee("/app/tasks/create?project_id={$project->id}", false);
    }

    public function test_app_task_and_project_indexes_render_canonical_app_navigation_links(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)
            ->get('/app/tasks')
            ->assertOk()
            ->assertSee('/app/tasks/create', false);

        $this->actingAs($user)
            ->get('/app/projects')
            ->assertOk()
            ->assertSee('/app/tasks', false);
    }

    public function test_app_settings_leaf_routes_redirect_to_settings_index(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $this->actingAs($user)->get('/app/settings/general')->assertRedirect('/app/settings');
        $this->actingAs($user)->get('/app/settings/security')->assertRedirect('/app/settings');
        $this->actingAs($user)->get('/app/settings/notifications')->assertRedirect('/app/settings');
    }

    public function test_app_task_show_renders_canonical_project_and_task_links(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $project = Project::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $task = Task::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
        ]);

        $response = $this->actingAs($user)->get("/app/tasks/{$task->id}");

        $response
            ->assertOk()
            ->assertViewIs('tasks.show')
            ->assertSee('/app/projects', false)
            ->assertSee("/app/tasks/{$task->id}/edit", false)
            ->assertDontSee('/dashboard/projects', false);
    }
}
