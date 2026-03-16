<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WorkTemplateBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkTemplateBaselineSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_database_seeder_seeds_three_published_baseline_templates_per_tenant_with_expected_structure(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenants = Tenant::query()->orderBy('created_at')->get();
        $this->assertNotEmpty($tenants);

        foreach ($tenants as $tenant) {
            $templates = WorkTemplate::query()
                ->where('tenant_id', (string) $tenant->id)
                ->whereIn('code', array_column(WorkTemplateBaselineSeeder::TEMPLATE_DEFINITIONS, 'code'))
                ->orderBy('code')
                ->get();

            $this->assertCount(3, $templates, 'Expected exactly 3 baseline templates per tenant.');

            foreach (WorkTemplateBaselineSeeder::TEMPLATE_DEFINITIONS as $definition) {
                $template = $templates->firstWhere('code', $definition['code']);

                $this->assertNotNull($template, 'Missing baseline template ' . $definition['code']);
                $this->assertSame('published', $template->status);
                $this->assertSame($definition['name'], $template->name);

                $version = WorkTemplateVersion::query()
                    ->with('steps.fields')
                    ->where('tenant_id', (string) $tenant->id)
                    ->where('work_template_id', (string) $template->id)
                    ->where('semver', WorkTemplateBaselineSeeder::VERSION)
                    ->whereNotNull('published_at')
                    ->first();

                $this->assertNotNull($version, 'Missing published baseline version for ' . $definition['code']);
                $this->assertTrue((bool) $version->is_immutable);

                $expectedStepKeys = array_column($definition['steps'], 'key');
                $actualStepKeys = $version->steps->sortBy('step_order')->pluck('step_key')->values()->all();
                $this->assertSame($expectedStepKeys, $actualStepKeys);

                foreach ($definition['steps'] as $stepIndex => $stepDefinition) {
                    $step = $version->steps->sortBy('step_order')->values()->get($stepIndex);
                    $this->assertNotNull($step);
                    $this->assertSame($stepDefinition['name'], $step->name);
                    $this->assertSame($stepDefinition['depends_on'], $step->depends_on ?? []);

                    $config = $step->config_json ?? [];
                    $this->assertSame($stepDefinition['config']['phase_key'], $config['phase_key'] ?? null);
                    $this->assertSame($stepDefinition['config']['phase_name'], $config['phase_name'] ?? null);
                    $this->assertCount(count($stepDefinition['config']['checklist_items']), $config['checklist_items'] ?? []);
                    $this->assertCount(count($stepDefinition['config']['required_docs']), $config['required_docs'] ?? []);

                    $expectedFieldKeys = array_column($stepDefinition['fields'], 'key');
                    $actualFieldKeys = $step->fields->sortBy('field_key')->pluck('field_key')->values()->all();
                    sort($expectedFieldKeys);
                    $this->assertSame($expectedFieldKeys, $actualFieldKeys);
                }
            }
        }
    }

    public function test_seeded_baseline_templates_support_preview_dry_run_and_apply(): void
    {
        $this->seed(DatabaseSeeder::class);

        $tenant = Tenant::query()->orderBy('created_at')->firstOrFail();
        $actor = $this->createActorWithPermissions($tenant, ['template.view', 'template.apply', 'work.view']);

        foreach (WorkTemplateBaselineSeeder::TEMPLATE_DEFINITIONS as $index => $definition) {
            $project = Project::factory()->create([
                'tenant_id' => (string) $tenant->id,
                'created_by' => (string) $actor->id,
                'pm_id' => (string) $actor->id,
            ]);

            $template = WorkTemplate::query()
                ->where('tenant_id', (string) $tenant->id)
                ->where('code', $definition['code'])
                ->firstOrFail();

            $countsBeforeDryRun = $this->workflowCounts();

            $this->postJson($this->workTemplatePreviewRoute((string) $template->id), [
                'project_id' => (string) $project->id,
            ], $this->authHeaders($actor))
                ->assertOk()
                ->assertJsonPath('data.summary.phases', $this->expectedPhaseCount($definition))
                ->assertJsonPath('data.summary.tasks', count($definition['steps']));

            $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
                'work_template_id' => (string) $template->id,
                'dry_run' => true,
            ], $this->authHeaders($actor))
                ->assertOk()
                ->assertJsonPath('data.dry_run', true)
                ->assertJsonPath('data.summary.tasks', count($definition['steps']));

            $this->assertSame($countsBeforeDryRun, $this->workflowCounts(), 'Dry-run must not write records.');

            $apply = $this->postJson($this->projectApplyTemplateRoute((string) $project->id), [
                'work_template_id' => (string) $template->id,
            ], $this->authHeaders($actor));

            $apply->assertCreated()
                ->assertJsonPath('data.tasks_created', count($definition['steps']))
                ->assertJsonPath('data.scope_type', 'project')
                ->assertJsonPath('data.project_id', (string) $project->id);

            $instanceId = (string) $apply->json('data.id');
            $this->assertNotSame('', $instanceId);

            $instance = WorkInstance::query()->with('steps')->findOrFail($instanceId);
            $this->assertCount(count($definition['steps']), $instance->steps);
        }

        $this->assertSame(3, WorkInstance::query()->count());
        $this->assertSame(11, WorkInstanceStep::query()->count());
        $this->assertSame(11, Task::query()->count());
        $this->assertSame(11, TaskAssignment::query()->count());
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('baseline-template-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createActorWithPermissions(Tenant $tenant, array $permissions): User
    {
        $user = User::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);

        $role = Role::query()->create([
            'name' => 'baseline-seeder-test-' . Str::lower(Str::random(8)),
            'scope' => Role::SCOPE_SYSTEM,
            'description' => 'Baseline seeder test role',
            'allow_override' => false,
            'is_active' => true,
        ]);

        $permissionIds = Permission::query()
            ->whereIn('code', $permissions)
            ->orWhereIn('name', $permissions)
            ->pluck('id')
            ->values()
            ->all();

        $this->assertCount(count($permissions), $permissionIds, 'Expected canonical permissions to exist after DatabaseSeeder.');

        $role->permissions()->sync($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    /**
     * @return array<string, int>
     */
    private function workflowCounts(): array
    {
        return [
            'work_instances' => WorkInstance::query()->count(),
            'work_instance_steps' => WorkInstanceStep::query()->count(),
            'tasks' => Task::query()->count(),
            'task_assignments' => TaskAssignment::query()->count(),
        ];
    }

    /**
     * @param array<string, mixed> $definition
     */
    private function expectedPhaseCount(array $definition): int
    {
        $phaseKeys = [];

        foreach (($definition['steps'] ?? []) as $step) {
            if (!is_array($step)) {
                continue;
            }

            $config = $step['config'] ?? [];
            if (!is_array($config)) {
                continue;
            }

            $phaseKey = $config['phase_key'] ?? null;
            if (is_string($phaseKey) && $phaseKey !== '') {
                $phaseKeys[] = $phaseKey;
            }
        }

        return count(array_values(array_unique($phaseKeys)));
    }

    private function workTemplatePreviewRoute(string $templateId): string
    {
        return route('api.zena.work-templates.preview', ['id' => $templateId], false);
    }

    private function projectApplyTemplateRoute(string $projectId): string
    {
        return route('api.zena.projects.apply-template', ['id' => $projectId], false);
    }
}
