<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthenticationService;
use App\Services\BulkOperationsService;
use App\Services\ImportExportService;
use App\Services\SecurityMonitoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Src\RBAC\Services\AuthService as RbacAuthService;
use Tests\TestCase;

class AuthSensitiveRoutesProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $baseUser;
    private Project $project;
    private Task $task;
    private string $downloadFilename = 'protected-test.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->baseUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->baseUser->id]);
        $this->task = Task::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'created_by' => $this->baseUser->id,
            'assigned_to' => $this->baseUser->id,
        ]);

        Storage::makeDirectory('exports');
        Storage::put('exports/' . $this->downloadFilename, "id,name\n1,test");

        $this->mock(AuthenticationService::class, function ($mock): void {
            $mock->shouldReceive('logout')->andReturn(['success' => true, 'message' => 'ok']);
            $mock->shouldReceive('refreshToken')->andReturn(['success' => true, 'token' => 'new-token']);
        });

        $this->mock(RbacAuthService::class, function ($mock): void {
            $mock->shouldReceive('checkPermission')->andReturn(true);
        });

        $this->mock(BulkOperationsService::class, function ($mock): void {
            $mock->shouldReceive('bulkCreateUsers')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkUpdateUsers')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkDeleteUsers')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkCreateProjects')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkUpdateProjects')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkCreateTasks')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkUpdateTaskStatus')->andReturn(['ok' => true]);
            $mock->shouldReceive('bulkAssignUsersToProjects')->andReturn(['ok' => true]);
            $mock->shouldReceive('queueBulkOperation')->andReturn('op-1');
            $mock->shouldReceive('getBulkOperationStatus')->andReturn(['state' => 'queued']);
        });

        $this->mock(ImportExportService::class, function ($mock): void {
            $mock->shouldReceive('exportUsers')->andReturn(storage_path('app/exports/users.csv'));
            $mock->shouldReceive('exportProjects')->andReturn(storage_path('app/exports/projects.csv'));
            $mock->shouldReceive('exportTasks')->andReturn(storage_path('app/exports/tasks.csv'));
            $mock->shouldReceive('importUsers')->andReturn(['ok' => true]);
            $mock->shouldReceive('importProjects')->andReturn(['ok' => true]);
            $mock->shouldReceive('importTasks')->andReturn(['ok' => true]);
            $mock->shouldReceive('getImportTemplate')->andReturn(storage_path('app/exports/template.csv'));
        });

        $this->mock(SecurityMonitoringService::class, function ($mock): void {
            $mock->shouldReceive('getSecurityEventsTimeline')->andReturn([]);
            $mock->shouldReceive('getFailedLoginAttempts')->andReturn([]);
            $mock->shouldReceive('getSuspiciousActivities')->andReturn([]);
            $mock->shouldReceive('getUserSecurityStatus')->andReturn([]);
            $mock->shouldReceive('getSecurityRecommendations')->andReturn([]);
            $mock->shouldReceive('getSecurityAlerts')->andReturn([]);
            $mock->shouldReceive('generateSecurityReport')->andReturn([]);
        });
    }

    /**
     * @dataProvider protectedAuthEndpointsProvider
     */
    public function test_protected_endpoints_return_401_when_unauthenticated(array $case): void
    {
        $response = $this->dispatch($case, null, (string) $this->tenant->id);
        $response->assertStatus(401);
    }

    /**
     * @dataProvider protectedAuthEndpointsProvider
     */
    public function test_protected_endpoints_return_403_when_permission_missing(array $case): void
    {
        $user = $this->createUserWithRoleOnly($this->tenant);
        $response = $this->dispatch($case, $user, (string) $this->tenant->id);
        $response->assertStatus(403);
    }

    /**
     * @dataProvider protectedAuthEndpointsProvider
     */
    public function test_protected_endpoints_return_200_with_valid_auth_tenant_and_permission(array $case): void
    {
        $user = $this->createUserWithPermission($this->tenant, $case['permission']);
        $response = $this->dispatch($case, $user, (string) $this->tenant->id);
        $response->assertStatus(200);
    }

    public function test_protected_endpoint_returns_403_on_tenant_mismatch_contract(): void
    {
        $otherTenant = Tenant::factory()->create();
        $user = $this->createUserWithPermission($this->tenant, 'auth.me');

        $response = $this->dispatch([
            'method' => 'GET',
            'uri' => '/api/auth/me',
            'permission' => 'auth.me',
        ], $user, (string) $otherTenant->id);

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
    }

    public static function protectedAuthEndpointsProvider(): array
    {
        $importFile = UploadedFile::fake()->create('import.csv', 1, 'text/csv');

        return [
            'auth.me' => [[
                'method' => 'GET',
                'uri' => '/api/auth/me',
                'permission' => 'auth.me',
            ]],
            'auth.logout' => [[
                'method' => 'POST',
                'uri' => '/api/auth/logout',
                'permission' => 'auth.logout',
                'json' => [],
            ]],
            'auth.refresh' => [[
                'method' => 'POST',
                'uri' => '/api/auth/refresh',
                'permission' => 'auth.refresh',
                'json' => [],
            ]],
            'auth.check-permission' => [[
                'method' => 'POST',
                'uri' => '/api/auth/check-permission',
                'permission' => 'auth.check-permission',
                'json' => ['permission' => 'auth.me'],
            ]],
            'auth.permissions' => [[
                'method' => 'GET',
                'uri' => '/api/auth/permissions',
                'permission' => 'auth.permissions',
            ]],
            'bulk.users.create' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/users/create',
                'permission' => 'auth.bulk.manage',
                'json' => ['users' => [['name' => 'u', 'email' => 'u1@example.com']]],
            ]],
            'bulk.users.update' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/users/update',
                'permission' => 'auth.bulk.manage',
                'json' => ['updates' => [['id' => 'u1', 'data' => ['name' => 'U']]]],
            ]],
            'bulk.users.delete' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/users/delete',
                'permission' => 'auth.bulk.manage',
                'json' => ['user_ids' => ['u1']],
            ]],
            'bulk.projects.create' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/projects/create',
                'permission' => 'auth.bulk.manage',
                'json' => ['projects' => [['name' => 'P', 'description' => 'D']]],
            ]],
            'bulk.projects.update' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/projects/update',
                'permission' => 'auth.bulk.manage',
                'json' => ['updates' => [['id' => 'p1', 'data' => ['name' => 'P2']]]],
            ]],
            'bulk.tasks.create' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/tasks/create',
                'permission' => 'auth.bulk.manage',
                'json' => ['tasks' => [['title' => 'T', 'description' => 'D']], 'project_id' => 'p1'],
            ]],
            'bulk.tasks.update-status' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/tasks/update-status',
                'permission' => 'auth.bulk.manage',
                'json' => ['task_ids' => ['t1'], 'status' => 'completed'],
            ]],
            'bulk.assign-users-to-projects' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/assign-users-to-projects',
                'permission' => 'auth.bulk.manage',
                'json' => ['assignments' => [['user_id' => 'u1', 'project_id' => 'p1']]],
            ]],
            'bulk.export.users' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/export/users',
                'permission' => 'auth.bulk.manage',
            ]],
            'bulk.export.projects' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/export/projects',
                'permission' => 'auth.bulk.manage',
            ]],
            'bulk.export.tasks' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/export/tasks',
                'permission' => 'auth.bulk.manage',
            ]],
            'bulk.import.users' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/import/users',
                'permission' => 'auth.bulk.manage',
                'multipart' => ['file' => $importFile],
            ]],
            'bulk.import.projects' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/import/projects',
                'permission' => 'auth.bulk.manage',
                'multipart' => ['file' => $importFile],
            ]],
            'bulk.import.tasks' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/import/tasks',
                'permission' => 'auth.bulk.manage',
                'multipart' => ['file' => $importFile, 'project_id' => 'p1'],
            ]],
            'bulk.template' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/template/users?type=users',
                'permission' => 'auth.bulk.manage',
            ]],
            'bulk.download' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/download/protected-test.csv',
                'permission' => 'auth.bulk.manage',
            ]],
            'bulk.queue' => [[
                'method' => 'POST',
                'uri' => '/api/auth/bulk/queue',
                'permission' => 'auth.bulk.manage',
                'json' => ['operation' => 'bulk_update_users', 'data' => ['ids' => ['u1']]],
            ]],
            'bulk.status' => [[
                'method' => 'GET',
                'uri' => '/api/auth/bulk/status/op-1?operation_id=op-1',
                'permission' => 'auth.bulk.manage',
            ]],
            'security.overview' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/overview',
                'permission' => 'auth.security.read',
            ]],
            'security.timeline' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/events/timeline',
                'permission' => 'auth.security.read',
            ]],
            'security.failed-logins' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/failed-logins',
                'permission' => 'auth.security.read',
            ]],
            'security.suspicious' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/suspicious-activities',
                'permission' => 'auth.security.read',
            ]],
            'security.user-status' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/user-status',
                'permission' => 'auth.security.read',
            ]],
            'security.recommendations' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/recommendations',
                'permission' => 'auth.security.read',
            ]],
            'security.alerts' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/alerts',
                'permission' => 'auth.security.read',
            ]],
            'security.metrics' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/metrics',
                'permission' => 'auth.security.read',
            ]],
            'security.export-report' => [[
                'method' => 'GET',
                'uri' => '/api/auth/security/export-report',
                'permission' => 'auth.security.read',
            ]],
        ];
    }

    private function dispatch(array $case, ?User $user, string $tenantHeader)
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenantHeader,
            'Authorization' => 'Bearer test-token',
        ];

        if ($user !== null) {
            Sanctum::actingAs($user);
        }

        $request = $this->withHeaders($headers);
        $method = strtoupper($case['method']);

        if (isset($case['multipart'])) {
            return $request->post($case['uri'], $case['multipart']);
        }

        if ($method === 'GET') {
            return $request->getJson($case['uri']);
        }

        return $request->postJson($case['uri'], $case['json'] ?? []);
    }

    private function createUserWithRoleOnly(Tenant $tenant): User
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $role = Role::firstOrCreate(
            ['name' => 'admin'],
            ['scope' => Role::SCOPE_SYSTEM, 'allow_override' => true, 'is_active' => true]
        );
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }

    private function createUserWithPermission(Tenant $tenant, string $permissionCode): User
    {
        $user = $this->createUserWithRoleOnly($tenant);
        $permission = Permission::firstOrCreate(
            ['code' => $permissionCode],
            [
                'name' => $permissionCode,
                'module' => explode('.', $permissionCode)[0],
                'action' => explode('.', $permissionCode)[1] ?? 'access',
                'description' => $permissionCode,
            ]
        );
        if (empty($permission->name)) {
            $permission->name = $permissionCode;
            $permission->save();
        }

        $role = $user->roles()->where('name', 'admin')->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);

        return $user;
    }
}
