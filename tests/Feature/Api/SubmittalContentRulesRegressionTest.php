<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Role;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

class SubmittalContentRulesRegressionTest extends TestCase
{
    use RefreshDatabase;
    use RouteNameTrait;

    private User $user;
    private Submittal $submittal;
    private array $authHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->syncZenaProjectRecord($project);
        $this->assignSuperAdminRole($this->user);
        $token = $this->loginZenaUser($this->user);
        $this->authHeaders = [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];

        $this->submittal = Submittal::factory()->create([
            'project_id' => $project->id,
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'status' => 'draft',
        ]);
    }

    public function test_valid_payload_is_accepted(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['title' => 'Updated title', 'description' => 'Updated description']
        );

        $response->assertStatus(200);
    }

    public function test_partial_payload_missing_title_is_accepted_because_title_is_sometimes_not_required(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['description' => 'Only description changes']
        );

        $response->assertStatus(200);
    }

    public function test_invalid_submittal_type_enum_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['submittal_type' => 'not_a_real_type']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('submittal_type', $response->json('error.details.data', []));
    }

    public function test_specification_section_over_255_chars_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['specification_section' => str_repeat('x', 256)]
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('specification_section', $response->json('error.details.data', []));
    }

    public function test_non_date_due_date_is_rejected(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['due_date' => 'not-a-date']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('due_date', $response->json('error.details.data', []));
    }

    public function test_status_field_is_still_prohibited_after_refactor(): void
    {
        $response = $this->withHeaders($this->authHeaders)->putJson(
            $this->zena('submittals.update', ['id' => $this->submittal->id]),
            ['title' => 'x', 'status' => 'approved']
        );

        $response->assertStatus(422);
        $this->assertArrayHasKey('status', $response->json('error.details.data', []));
        $this->assertDatabaseHas('submittals', ['id' => $this->submittal->id, 'status' => 'draft']);
    }

    private function syncZenaProjectRecord(Project $project): void
    {
        DB::table('zena_projects')->updateOrInsert(
            ['id' => $project->id],
            [
                'tenant_id' => $project->tenant_id,
                'code' => $project->code,
                'name' => $project->name,
                'description' => $project->description,
                'client_id' => $project->client_id,
                'status' => 'planning',
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate(['name' => 'super_admin'], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $permissionNames = [
            'submittal.view', 'submittal.create', 'submittal.edit', 'submittal.delete',
            'submittal.submit', 'submittal.review', 'submittal.approve', 'submittal.reject',
        ];

        foreach ($permissionNames as $permissionName) {
            $permission = \App\Models\Permission::firstOrCreate(['name' => $permissionName], [
                'code' => $permissionName,
                'module' => 'submittal',
                'action' => explode('.', $permissionName)[1] ?? '*',
                'description' => ucfirst(str_replace('.', ' ', $permissionName)),
            ]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        $user->roles()->syncWithoutDetaching($role->id);
    }

    private function loginZenaUser(User $user): string
    {
        $response = $this->postJson($this->zena('auth.login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);

        return (string) $response->json('data.token');
    }
}
