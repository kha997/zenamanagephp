<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\User;
use App\Models\Project;
use App\Models\Submittal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\RouteNameTrait;

class SubmittalShowApiTest extends TestCase
{
    use RefreshDatabase;
    use RouteNameTrait;

    private User $user;
    private Project $project;
    private string $token;
    private array $zenaAuthHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create([
            'created_by' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
        ]);
        $this->syncZenaProjectRecord($this->project);

        $this->assignSuperAdminRole($this->user);
        $this->token = $this->loginZenaUser($this->user);
        $this->zenaAuthHeaders = $this->buildZenaAuthHeaders();
    }

    public function test_show_returns_attachments_as_casted_payload_data(): void
    {
        $attachments = [
            [
                'name' => 'shop-drawing-a1.pdf',
                'url' => 'https://example.test/files/shop-drawing-a1.pdf',
            ],
            [
                'name' => 'material-sample.jpg',
                'url' => 'https://example.test/files/material-sample.jpg',
            ],
        ];

        $submittal = Submittal::factory()->create([
            'project_id' => $this->project->id,
            'tenant_id' => $this->user->tenant_id,
            'created_by' => $this->user->id,
            'submitted_by' => $this->user->id,
            'attachments' => $attachments,
        ]);

        $response = $this->withZenaAuth()->getJson($this->zena('submittals.show', ['id' => $submittal->id]));

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $submittal->id)
            ->assertJsonPath('data.attachments.0.name', 'shop-drawing-a1.pdf')
            ->assertJsonPath('data.attachments.1.url', 'https://example.test/files/material-sample.jpg')
            ->assertJsonMissingPath('data.attachments.data');
    }

    private function withZenaAuth()
    {
        return $this->withHeaders($this->zenaAuthHeaders);
    }

    private function buildZenaAuthHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'X-Tenant-ID' => (string) $this->user->tenant_id,
            'Accept' => 'application/json',
        ];
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

    private function assignSuperAdminRole(User $user): void
    {
        $role = Role::firstOrCreate([
            'name' => 'super_admin',
        ], [
            'scope' => Role::SCOPE_SYSTEM,
            'allow_override' => true,
            'is_active' => true,
        ]);

        $permissionNames = [
            'submittal.view', 'submittal.create', 'submittal.edit', 'submittal.delete',
            'submittal.submit', 'submittal.review', 'submittal.approve', 'submittal.reject',
        ];

        foreach ($permissionNames as $permissionName) {
            $parts = explode('.', $permissionName);
            $permission = \App\Models\Permission::firstOrCreate(['name' => $permissionName], [
                'code' => $permissionName,
                'module' => $parts[0] ?? $permissionName,
                'action' => $parts[1] ?? '*',
                'description' => ucfirst(str_replace('.', ' ', $permissionName)),
            ]);
            $role->permissions()->syncWithoutDetaching($permission->id);
        }

        $user->roles()->syncWithoutDetaching($role->id);
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
                'status' => $this->mapProjectStatusToZenaStatus($project->status),
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget' => $project->budget_total ?? 0,
                'settings' => json_encode($project->settings ?? []),
                'created_at' => $project->created_at,
                'updated_at' => $project->updated_at,
            ]
        );
    }

    private function mapProjectStatusToZenaStatus(string $status): string
    {
        return match ($status) {
            'planning' => 'planning',
            'active', 'in_progress' => 'active',
            'on_hold' => 'on_hold',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'planning',
        };
    }
}
