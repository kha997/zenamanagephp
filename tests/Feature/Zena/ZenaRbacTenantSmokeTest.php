<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Permission as AppPermission;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\QcPlan;
use App\Models\Role as AppRole;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Rfi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Src\RBAC\Services\AuthService;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaRbacTenantSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->seedPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_unauthenticated_cannot_access_rfis(): void
    {
        [$tenant] = $this->newTenantContext();

        $response = $this->zenaGet('/api/zena/rfis', $tenant);

        $response->assertStatus(401);
    }

    public function test_authenticated_without_permission_gets_403(): void
    {
        [$tenant, $project, $user] = $this->newTenantContext();
        $token = $this->createAuthToken($user);

        $response = $this->zenaGet('/api/zena/rfis', $tenant, $token);
        $this->assertSmokeStatus($response, 403);
    }

    public function test_admin_with_permission_sees_rfis(): void
    {
        [$tenant, $project, $user] = $this->newTenantContext();
        $this->assignSystemAdmin($user);
        $this->createRfi($tenant, $project, $user);
        $token = $this->createAuthToken($user);

        $response = $this->zenaGet('/api/zena/rfis', $tenant, $token);

        $this->assertSsotListShape($response);
        $response->assertJsonPath('data.0.title', 'Initial RFI');
    }

    public function test_admin_cant_read_other_tenant_rfis(): void
    {
        [$tenantA, $projectA, $admin] = $this->newTenantContext();
        $this->assignSystemAdmin($admin);
        $this->createRfi($tenantA, $projectA, $admin);

        [$tenantB, $projectB, $otherUser] = $this->newTenantContext();
        $tenantBRfi = $this->createRfi($tenantB, $projectB, $otherUser, [
            'title' => 'Tenant B RFI',
        ]);

        $token = $this->createAuthToken($admin);
        $response = $this->zenaGet("/api/zena/rfis/{$tenantBRfi->id}", $tenantA, $token);

        $this->assertSmokeStatus($response, 404);
        $this->assertZenaErrorEnvelope($response, 'E404.NOT_FOUND');

        $indexResponse = $this->zenaGet('/api/zena/rfis', $tenantA, $token);

        $this->assertSsotListShape($indexResponse);
        $ids = array_column($indexResponse->json('data', []), 'id');
        $this->assertNotContains($tenantBRfi->id, $ids);
    }

    public function test_admin_cant_read_other_tenant_submittals(): void
    {
        [$tenantA, $projectA, $admin] = $this->newTenantContext();
        $this->assignSystemAdmin($admin);
        $this->createSubmittal($tenantA, $projectA, $admin);

        [$tenantB, $projectB, $otherUser] = $this->newTenantContext();
        $tenantBSubmittal = $this->createSubmittal($tenantB, $projectB, $otherUser, [
            'title' => 'Tenant B Submittal',
            'package_no' => 'PKG-TB-001',
            'submittal_number' => 'SUB-TB-001',
        ]);

        $token = $this->createAuthToken($admin);
        $response = $this->zenaGet("/api/zena/submittals/{$tenantBSubmittal->id}", $tenantA, $token);

        $this->assertSmokeStatus($response, 404);
        $this->assertZenaErrorEnvelope($response, 'E404.NOT_FOUND');

        $indexResponse = $this->zenaGet('/api/zena/submittals', $tenantA, $token);

        $this->assertSsotListShape($indexResponse);
        $ids = array_column($indexResponse->json('data', []), 'id');
        $this->assertNotContains($tenantBSubmittal->id, $ids);
    }

    public function test_admin_cant_read_other_tenant_inspections(): void
    {
        [$tenantA, $projectA, $admin] = $this->newTenantContext();
        $this->assignSystemAdmin($admin);
        $this->createInspection($tenantA, $projectA, $admin);

        [$tenantB, $projectB, $otherUser] = $this->newTenantContext();
        $tenantBInspection = $this->createInspection($tenantB, $projectB, $otherUser, [
            'title' => 'Tenant B Inspection',
        ]);

        $token = $this->createAuthToken($admin);
        $response = $this->zenaGet("/api/zena/inspections/{$tenantBInspection->id}", $tenantA, $token);

        $this->assertSmokeStatus($response, 404);
        $this->assertZenaErrorEnvelope($response, 'E404.NOT_FOUND');

        $indexResponse = $this->zenaGet('/api/zena/inspections', $tenantA, $token);

        $this->assertSsotListShape($indexResponse);
        $ids = array_column($indexResponse->json('data', []), 'id');
        $this->assertNotContains($tenantBInspection->id, $ids);
    }

    private function seedPermissions(): void
    {
        $permissions = [
            ['code' => 'rfi.view', 'module' => 'rfi', 'action' => 'view', 'description' => 'View RFIs'],
            ['code' => 'submittal.view', 'module' => 'submittal', 'action' => 'view', 'description' => 'View submittals'],
            ['code' => 'inspection.view', 'module' => 'inspection', 'action' => 'view', 'description' => 'View inspections'],
        ];

        foreach ($permissions as $permissionDefinition) {
            AppPermission::updateOrCreate([
                'code' => $permissionDefinition['code'],
            ], array_merge($permissionDefinition, [
                'name' => $permissionDefinition['code'],
            ]));
        }
    }

    private function seedSystemAdminRole(): void
    {
        $role = AppRole::firstOrCreate([
            'name' => 'System Admin',
        ], [
            'scope' => AppRole::SCOPE_SYSTEM,
            'description' => 'System administrator',
            'allow_override' => true,
        ]);

        $codes = ['rfi.view', 'submittal.view', 'inspection.view'];
        $role->permissions()->sync(AppPermission::whereIn('code', $codes)->pluck('id')->toArray());
    }

    private function assignSystemAdmin(User $user): void
    {
        foreach (['rfi.view', 'submittal.view', 'inspection.view'] as $permission) {
            $this->grantPermissionToUser($user, $permission);
        }
    }

    /**
     * @return array{Tenant, Project, User}
     */
    private function newTenantContext(): array
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        return [$tenant, $project, $user];
    }

    private function withTenantHeader(Tenant $tenant, ?string $token = null): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ];

        if ($token) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function zenaGet(string $uri, Tenant $tenant, ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->getJson($uri);
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): TestResponse
    {
        return $this->withHeaders($this->withTenantHeader($tenant, $token))->postJson($uri, $payload);
    }

    private function createAuthToken(User $user): string
    {
        return app(AuthService::class)->createTokenForUser($user);
    }

    private function assertSmokeStatus(TestResponse $response, int $expected): void
    {
        if (in_array($expected, [403, 404], true) && $response->status() === 401 && env('DEBUG_SMOKE')) {
            $response->dump();
        }

        $response->assertStatus($expected);
    }

    private function assertSsotListShape(TestResponse $response): void
    {
        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonStructure([
            'meta' => [
                'pagination' => ['page', 'per_page', 'total', 'last_page'],
            ],
        ]);
    }

    private function assertZenaErrorEnvelope(TestResponse $response, string $expectedCode): void
    {
        $response->assertJsonStructure([
            'error' => [
                'id',
                'code',
                'message',
                'details',
            ],
        ]);

        $this->assertSame($expectedCode, $response->json('error.code'));
        $this->assertIsString($response->json('error.message'));
    }

    private function createSubmittal(Tenant $tenant, Project $project, User $creator, array $attributes = []): Submittal
    {
        $payload = array_merge([
            'id' => (string) Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'package_no' => 'PKG-' . Str::upper(Str::random(4)),
            'title' => 'Initial Submittal',
            'description' => 'Smoke test submittal',
            'submittal_type' => 'shop_drawing',
            'status' => 'draft',
            'submitted_by' => $creator->id,
            'submittal_number' => 'SUB-' . Str::upper(Str::random(4)),
        ], $attributes);

        return Submittal::create($payload);
    }

    private function createInspection(Tenant $tenant, Project $project, User $creator, array $attributes = []): QcInspection
    {
        $plan = QcPlan::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Smoke Plan',
            'description' => 'Plan for tenant smoke tests',
            'status' => 'draft',
            'created_by' => $creator->id,
            'checklist_items' => [],
        ]);

        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'qc_plan_id' => $plan->id,
            'title' => 'Initial Inspection',
            'description' => 'Smoke test inspection',
            'inspection_date' => now()->addDay()->toDateString(),
            'inspector_id' => $creator->id,
            'status' => 'scheduled',
        ], $attributes);

        return QcInspection::create($payload);
    }

    private function createRfi(Tenant $tenant, Project $project, User $creator, array $attributes = []): Rfi
    {
        $payload = array_merge([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'title' => 'Initial RFI',
            'subject' => 'Smoke test',
            'description' => 'Validation',
            'question' => 'Does it work?',
            'priority' => 'medium',
            'asked_by' => $creator->id,
            'created_by' => $creator->id,
            'rfi_number' => 'RFI-' . Str::upper(Str::random(6)),
            'status' => 'open',
        ], $attributes);

        return Rfi::create($payload);
    }

    private function grantPermissionToUser(User $user, string $permissionKey): AppPermission
    {
        [$module, $action] = array_pad(explode('.', $permissionKey, 2), 2, 'access');

        $permission = AppPermission::firstOrCreate(
            ['code' => $permissionKey],
            [
                'name' => $permissionKey,
                'module' => $module,
                'action' => $action,
                'description' => "Smoke test permission for {$permissionKey}",
            ]
        );

        $role = AppRole::firstOrCreate(
            ['name' => 'System Admin'],
            [
                'scope' => AppRole::SCOPE_SYSTEM,
                'description' => 'System administrator',
                'allow_override' => true,
            ]
        );

        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        $hasPermission = $user->hasPermission($permission->name);

        if (!$hasPermission) {
            $rolesWithPermissions = $user->roles()
                ->with('permissions')
                ->get()
                ->map(function (AppRole $role) {
                    return [
                        'role' => $role->name,
                        'permissions' => $role->permissions->pluck('name')->toArray(),
                    ];
                })
                ->toArray();

            dump([
                'user_id' => $user->id,
                'permission_key' => $permissionKey,
                'roles' => $rolesWithPermissions,
            ]);
        }

        $this->assertTrue(
            $hasPermission,
            sprintf(
                'User %s missing %s before request (roles=%s)',
                $user->id,
                $permission->name,
                implode(', ', $user->roles()->pluck('name')->toArray())
            )
        );

        return $permission;
    }
}
