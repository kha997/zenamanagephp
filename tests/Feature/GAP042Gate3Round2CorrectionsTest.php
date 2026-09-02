<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission as AppPermission;
use App\Models\Project;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Src\Foundation\EventBus;
use Src\RBAC\Models\Permission;
use Src\RBAC\Models\Role;
use Tests\TestCase;

/**
 * GAP-042 Gate-3 Owner Round-2 CHANGES REQUESTED corrections 10-12 —
 * discriminating RED/GREEN evidence for each. Setup mirrors
 * GAP042Gate3Round1CorrectionsTest.php exactly (same grant pattern).
 */
class GAP042Gate3Round2CorrectionsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected User $userA;
    protected User $userB;
    protected string $tokenA;
    protected string $tokenB;

    /** @var array<int, array{event: string, payload: array}> */
    protected array $capturedEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = User::factory()->create(['tenant_id' => $this->tenantA->id]);
        $this->userB = User::factory()->create(['tenant_id' => $this->tenantB->id]);

        $this->grantFullRbacGateAccess($this->userA);
        $this->grantFullRbacGateAccess($this->userB);

        $this->tokenA = $this->userA->createToken('t')->plainTextToken;
        $this->tokenB = $this->userB->createToken('t')->plainTextToken;
    }

    protected function tearDown(): void
    {
        EventBus::unsubscribeAll('*');
        $this->capturedEvents = [];
        parent::tearDown();
    }

    private function captureEvents(): void
    {
        $this->capturedEvents = [];
        EventBus::subscribe('*', function (string $eventName, $payload) {
            $this->capturedEvents[] = [
                'event' => $eventName,
                'payload' => (array) $payload,
            ];
        });
    }

    private function grantFullRbacGateAccess(User $user): void
    {
        $codes = [
            'role.view', 'role.create', 'role.edit', 'role.delete', 'role.assign',
            'permission.view', 'permission.create', 'permission.edit', 'permission.delete',
            'permission.export', 'permission.import',
            'user.view', 'project.view', 'audit.view',
        ];

        $ids = collect($codes)->map(function (string $code) {
            [$module, $action] = explode('.', $code, 2);

            return AppPermission::firstOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => $module, 'action' => $action, 'description' => $code]
            )->id;
        })->all();

        $role = AppRole::firstOrCreate(
            ['name' => 'gap042_full_access', 'scope' => 'system'],
            ['description' => 'GAP-042 test role', 'allow_override' => true]
        );

        $role->permissions()->syncWithoutDetaching($ids);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function headers(string $token, string $tenantId): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => $tenantId,
            'Accept' => 'application/json',
        ];
    }

    // ------------------------------------------------------------------
    // Correction 10 — Permission Matrix EventBus non-project events must
    // use the literal 'system' projectId convention, never the tenant id.
    // ------------------------------------------------------------------
    public function test_correction10_export_event_projectId_is_system_not_tenant_id(): void
    {
        Sanctum::actingAs($this->userA);
        $this->captureEvents();

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->getJson('/api/v1/rbac/permission-matrix/export');

        $response->assertStatus(200);

        $exported = collect($this->capturedEvents)
            ->first(fn (array $e) => $e['event'] === 'rbac.permissionMatrix.exported');

        $this->assertNotNull($exported, 'rbac.permissionMatrix.exported was not published');
        $this->assertSame('system', $exported['payload']['projectId']);
        $this->assertNotSame($this->tenantA->id, $exported['payload']['projectId']);
    }

    public function test_correction10_import_event_projectId_is_system_not_tenant_id(): void
    {
        Sanctum::actingAs($this->userA);

        $ownRole = Role::create(['name' => 'C10ImportOwn-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        Permission::create(['code' => 'c10i.action', 'module' => 'c10i', 'action' => 'action']);

        $csv = "role_name,module,action,permission_code,allow\n"
            . "{$ownRole->name},c10i,action,c10i.action,true\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('matrix.csv', $csv);

        $this->captureEvents();

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->post('/api/v1/rbac/permission-matrix/import', ['csv_file' => $file], $this->headers($this->tokenA, $this->tenantA->id));

        $response->assertStatus(200);

        // Controller-level 'rbac.permissionMatrix.imported' event.
        $imported = collect($this->capturedEvents)
            ->first(fn (array $e) => $e['event'] === 'rbac.permissionMatrix.imported');
        $this->assertNotNull($imported, 'rbac.permissionMatrix.imported was not published');
        $this->assertSame('system', $imported['payload']['projectId']);
        $this->assertNotSame($this->tenantA->id, $imported['payload']['projectId']);

        // Service-level per-role 'rbac.role.permissionsImported' event.
        $perRole = collect($this->capturedEvents)
            ->first(fn (array $e) => $e['event'] === 'rbac.role.permissionsImported');
        $this->assertNotNull($perRole, 'rbac.role.permissionsImported was not published');
        $this->assertSame('system', $perRole['payload']['projectId']);
        $this->assertNotSame($this->tenantA->id, $perRole['payload']['projectId']);
    }

    public function test_correction10_no_permission_matrix_tenant_id_as_projectid_pattern_in_source(): void
    {
        $controllerSource = file_get_contents(base_path('src/RBAC/Controllers/PermissionMatrixController.php'));
        $serviceSource = file_get_contents(base_path('src/RBAC/Services/PermissionMatrixService.php'));

        // The exact defect patterns the Owner flagged.
        $this->assertStringNotContainsString("'projectId' => (string) (\$tenantId ?? 'system')", $controllerSource);
        $this->assertStringNotContainsString("'projectId' => (string) (\$tenantId ?? 'system')", $serviceSource);
        $this->assertStringNotContainsString("'projectId' => \$tenantId", $controllerSource);
        $this->assertStringNotContainsString("'projectId' => \$tenantId", $serviceSource);
    }

    // ------------------------------------------------------------------
    // Correction 11 — rejected Permission Matrix imports must not create
    // global permission rows before role visibility/ownership/global-
    // read-only validation.
    // ------------------------------------------------------------------
    public function test_correction11_cross_tenant_target_role_import_creates_no_new_permission_or_role_permission_row(): void
    {
        Sanctum::actingAs($this->userA);

        $bRole = Role::create(['name' => 'C11CrossTenant-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantB->id]);
        $newCode = 'gap042.round2crosstenantnew';

        $this->assertDatabaseMissing('permissions', ['code' => $newCode]);

        $csv = "role_name,module,action,permission_code,allow\n"
            . "{$bRole->name},gap042,round2crosstenantnew,{$newCode},true\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('matrix.csv', $csv);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->post('/api/v1/rbac/permission-matrix/import', ['csv_file' => $file], $this->headers($this->tokenA, $this->tenantA->id));

        $response->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['code' => $newCode]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $bRole->id]);
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $bRole->id)->count());
    }

    public function test_correction11_global_readonly_target_role_import_creates_no_new_permission_or_role_permission_row(): void
    {
        Sanctum::actingAs($this->userA);

        $globalRole = Role::create(['name' => 'C11Global-' . uniqid(), 'scope' => 'system']);
        $newCode = 'gap042.round2globalreadonlynew';

        $this->assertDatabaseMissing('permissions', ['code' => $newCode]);

        $csv = "role_name,module,action,permission_code,allow\n"
            . "{$globalRole->name},gap042,round2globalreadonlynew,{$newCode},true\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('matrix.csv', $csv);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->post('/api/v1/rbac/permission-matrix/import', ['csv_file' => $file], $this->headers($this->tokenA, $this->tenantA->id));

        $response->assertStatus(200);

        $this->assertDatabaseMissing('permissions', ['code' => $newCode]);
        $this->assertDatabaseMissing('role_permissions', ['role_id' => $globalRole->id]);
    }

    public function test_correction11_control_own_tenant_role_new_permission_still_created_and_synced(): void
    {
        Sanctum::actingAs($this->userA);

        $ownRole = Role::create(['name' => 'C11Own-' . uniqid(), 'scope' => 'custom', 'tenant_id' => $this->tenantA->id]);
        $newCode = 'gap042.round2owntenantnew';

        $this->assertDatabaseMissing('permissions', ['code' => $newCode]);

        $csv = "role_name,module,action,permission_code,allow\n"
            . "{$ownRole->name},gap042,round2owntenantnew,{$newCode},true\n";

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('matrix.csv', $csv);

        $response = $this->withHeaders($this->headers($this->tokenA, $this->tenantA->id))
            ->post('/api/v1/rbac/permission-matrix/import', ['csv_file' => $file], $this->headers($this->tokenA, $this->tenantA->id));

        $response->assertStatus(200);

        $this->assertDatabaseHas('permissions', ['code' => $newCode]);
        $permId = Permission::where('code', $newCode)->value('id');
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $ownRole->id,
            'permission_id' => $permId,
        ]);
    }

    // ------------------------------------------------------------------
    // Correction 12 — complete the security matrix: check-permission x
    // cross-tenant project must also fail closed (non-disclosing).
    // ------------------------------------------------------------------
    public function test_correction12_check_permission_cross_tenant_project_fails_closed(): void
    {
        Sanctum::actingAs($this->userA);
        $h = $this->headers($this->tokenA, $this->tenantA->id);

        $bProject = Project::factory()->create(['tenant_id' => $this->tenantB->id]);

        $response = $this->withHeaders($h)
            ->postJson("/api/v1/rbac/users/{$this->userA->id}/check-permission?project_id={$bProject->id}", [
                'permission_code' => 'role.view',
            ]);

        $this->assertNotEquals(200, $response->status());
        $response->assertStatus(404);
        $body = $response->json();
        $this->assertArrayNotHasKey('has_permission', $body['data'] ?? []);
    }
}
