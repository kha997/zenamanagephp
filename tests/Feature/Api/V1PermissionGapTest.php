<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\V1PermissionGapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;

/**
 * GAP-098 — seeded roles (not super admins) reach the /api/v1 routes whose
 * permission codes were renamed or are now seeded.
 */
class V1PermissionGapTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, V1PermissionGapSeeder::class]);
        $this->tenant = Tenant::factory()->create();
    }

    public function test_project_manager_can_list_project_baselines(): void
    {
        $pm = $this->userWithRole('Project Manager');
        $project = $this->projectFor($pm);

        $this->apiGet("/api/v1/projects/{$project->id}/baselines", $this->headersFor($pm)) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk();
    }

    public function test_system_admin_can_list_roles_but_a_project_member_cannot_create_one(): void
    {
        $admin = $this->userWithRole('System Admin');
        $member = $this->userWithRole('Project Member');

        $this->apiGet('/api/v1/rbac/roles', $this->headersFor($admin)) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertOk();
        $this->apiPost('/api/v1/rbac/roles', ['name' => 'gap098-probe', 'scope' => 'custom'], $this->headersFor($member)) // ssot-allow-hardcode (unnamed v1 compatibility route)
            ->assertForbidden();
    }

    private function userWithRole(string $roleName): User
    {
        return $this->createTenantUser($this->tenant, [], [$roleName]);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        return array_merge(
            $this->apiHeadersForTenant((string) $this->tenant->id),
            ['Authorization' => 'Bearer ' . $this->apiLoginToken($user, $this->tenant)]
        );
    }

    private function projectFor(User $user): Project
    {
        return Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'pm_id' => $user->id,
            'created_by' => $user->id,
            'status' => 'active',
        ]);
    }
}
