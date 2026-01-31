<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Document;
use App\Models\Permission as AppPermission;
use App\Models\Project;
use App\Models\Role as AppRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaApiContractPhase2InvariantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedDocumentPermissions();
        $this->seedSystemAdminRole();
    }

    public function test_document_show_mismatch_header_returns_tenant_invalid(): void
    {
        [$tenantA, $userA, $passwordA] = $this->createTenantWithUser();
        $tokenA = $this->loginAndReturnToken($tenantA, $userA->email, $passwordA);

        [$tenantB, $userB, $passwordB] = $this->createTenantWithUser();
        $documentB = $this->createDocumentForTenant($tenantB, $userB);

        $response = $this->withHeaders(
            $this->contractHeaders($tenantA, $tokenA, (string) $tenantB->id)
        )->getJson("/api/zena/documents/{$documentB->id}");

        $response->assertStatus(403);
        $this->assertSame('TENANT_INVALID', $response->json('error.code'));
    }

    public function test_document_show_returns_not_found_for_scoped_cross_tenant_resource(): void
    {
        [$tenantA, $userA, $passwordA] = $this->createTenantWithUser();
        $tokenA = $this->loginAndReturnToken($tenantA, $userA->email, $passwordA);

        [$tenantB, $userB] = $this->createTenantWithUser();
        $documentB = $this->createDocumentForTenant($tenantB, $userB);

        $this->grantPermissionToUser($userA, 'document.view');

        $response = $this->withHeaders($this->contractHeaders($tenantA, $tokenA))
            ->getJson("/api/zena/documents/{$documentB->id}");

        $response->assertStatus(404);
        $this->assertSame('E404.NOT_FOUND', $response->json('error.code'));
    }

    public function test_documents_index_follows_contract_pagination_and_tenant_isolation(): void
    {
        [$tenantA, $userA, $passwordA] = $this->createTenantWithUser();
        [$tenantB, $userB, $passwordB] = $this->createTenantWithUser();
        $tokenA = $this->loginAndReturnToken($tenantA, $userA->email, $passwordA);

        $this->grantPermissionToUser($userA, 'document.view');

        $this->createDocumentForTenant($tenantA, $userA);
        $this->createDocumentForTenant($tenantA, $userA);
        $this->createDocumentForTenant($tenantB, $userB);
        $this->createDocumentForTenant($tenantB, $userB);

        $response = $this->withHeaders($this->contractHeaders($tenantA, $tokenA))
            ->getJson('/api/zena/documents');

        $response->assertStatus(200);
        $this->assertTrue($response->json('success'));
        $this->assertSame('success', $response->json('status'));
        $this->assertSame('success', $response->json('status_text'));
        $this->assertIsArray($response->json('data'));
        $this->assertNotEmpty($response->json('data'));

        $pagination = $response->json('meta.pagination');
        $this->assertIsArray($pagination);
        $this->assertIsInt($pagination['page']);
        $this->assertIsInt($pagination['per_page']);
        $this->assertIsInt($pagination['total']);
        $this->assertIsInt($pagination['last_page']);

        $tenantIds = array_column($response->json('data'), 'tenant_id');
        $this->assertNotContains($tenantB->id, $tenantIds);

        $mismatchResponse = $this->withHeaders(
            $this->contractHeaders($tenantA, $tokenA, (string) $tenantB->id)
        )->getJson('/api/zena/documents');

        $mismatchResponse->assertStatus(403);
        $this->assertSame('TENANT_INVALID', $mismatchResponse->json('error.code'));
    }

    public function test_documents_store_follows_contract_and_tenant_anchoring(): void
    {
        [$tenantA, $userA, $passwordA] = $this->createTenantWithUser();
        [$tenantB, $userB] = $this->createTenantWithUser();
        $tokenA = $this->loginAndReturnToken($tenantA, $userA->email, $passwordA);

        $this->grantPermissionToUser($userA, 'document.create');
        $this->grantPermissionToUser($userA, 'document.view');
        $this->grantPermissionToUser($userB, 'document.view');

        $project = Project::factory()->create([
            'tenant_id' => $tenantA->id,
            'status' => 'planning',
        ]);

        DB::table('zena_projects')->insert([
            'id' => $project->id,
            'tenant_id' => $project->tenant_id,
            'code' => $project->code ?? 'ZP-' . Str::upper(Str::random(8)),
            'name' => $project->name,
            'description' => $project->description ?? 'Contract helper project',
            'status' => $project->status ?? 'planning',
            'created_at' => $project->created_at,
            'updated_at' => $project->updated_at,
        ]);

        $payload = [
            'name' => 'Contract Upload',
            'file_path' => '/contracts/uploaded.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'project_id' => $project->id,
        ];

        $headers = $this->contractHeaders($tenantA, $tokenA);

        $response = $this->withHeaders($headers)
            ->postJson('/api/zena/documents', $payload);

        $response->assertStatus(201);
        $this->assertTrue($response->json('success'));
        $this->assertSame('success', $response->json('status'));
        $this->assertSame((string) $tenantA->id, $response->json('data.tenant_id'));

        $documentId = $response->json('data.id');

        $mismatchResponse = $this->withHeaders($this->contractHeaders($tenantB, $tokenA))
            ->getJson("/api/zena/documents/{$documentId}");

        $mismatchResponse->assertStatus(403);
        $this->assertSame('TENANT_INVALID', $mismatchResponse->json('error.code'));

        $foreignDocument = $this->createDocumentForTenant($tenantB, $userB);

        $crossTenantResponse = $this->withHeaders($this->contractHeaders($tenantA, $tokenA))
            ->getJson("/api/zena/documents/{$foreignDocument->id}");

        $crossTenantResponse->assertStatus(404);
        $this->assertSame('E404.NOT_FOUND', $crossTenantResponse->json('error.code'));
    }

    private function contractHeaders(Tenant $tenant, string $token, ?string $overrideTenantId = null): array
    {
        $tenantId = $overrideTenantId ?? (string) $tenant->id;

        return $this->zenaHeaders($tenantId, $token);
    }

    private function zenaHeaders(string $tenantId, string $bearerToken): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenantId,
            'Authorization' => 'Bearer ' . $bearerToken,
        ];
    }

    private function buildHeaders(string $tenantId, string $token, bool $includeToken = true): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => $tenantId,
        ];

        if ($includeToken && $token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    private function createDocumentForTenant(Tenant $tenant, User $creator, array $attributes = []): Document
    {
        $projectId = Str::ulid();

        DB::table('zena_projects')->insert([
            'id' => $projectId,
            'tenant_id' => $tenant->id,
            'code' => 'ZP-' . Str::upper(Str::random(8)),
            'name' => 'Contract Project',
            'description' => 'Backfill project for contract tests',
            'status' => 'planning',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $defaults = [
            'id' => Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $projectId,
            'uploaded_by' => $creator->id,
            'name' => 'Contract Document',
            'original_name' => 'Contract Document',
            'file_path' => '/contracts/' . Str::ulid() . '.pdf',
            'file_type' => 'document',
            'mime_type' => 'application/pdf',
            'file_size' => 2048,
            'file_hash' => Str::ulid(),
            'category' => 'contract',
            'description' => 'Contract invariants smoke document',
            'metadata' => [],
            'status' => 'active',
            'version' => 1,
            'is_current_version' => true,
            'parent_document_id' => null,
        ];

        return Document::create(array_merge($defaults, $attributes));
    }

    private function createTenantWithUser(string $password = 'Secret123!'): array
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make($password),
        ]);

        return [$tenant, $user, $password];
    }

    private function loginAndReturnToken(Tenant $tenant, string $email, string $password): string
    {
        Auth::shouldUse('web');

        $response = $this->zenaPost('api/zena/auth/login', $tenant, [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
        $token = $response->json('data.token');
        $this->assertIsString($token, 'Login must return a string token.');

        return $token;
    }

    private function zenaPost(string $uri, Tenant $tenant, array $payload = [], ?string $token = null): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->buildHeaders((string) $tenant->id, $token ?? '', false))
            ->postJson($uri, $payload);
    }

    private function seedDocumentPermissions(): void
    {
        $permissions = [
            ['code' => 'document.view', 'module' => 'document', 'action' => 'view', 'description' => 'View documents'],
            ['code' => 'document.create', 'module' => 'document', 'action' => 'create', 'description' => 'Upload documents'],
        ];

        foreach ($permissions as $permissionDefinition) {
            AppPermission::updateOrCreate(
                ['code' => $permissionDefinition['code']],
                [
                    'name' => $permissionDefinition['code'],
                    'module' => $permissionDefinition['module'],
                    'action' => $permissionDefinition['action'],
                    'description' => $permissionDefinition['description'],
                ]
            );
        }
    }

    private function seedSystemAdminRole(): void
    {
        AppRole::firstOrCreate(
            ['name' => 'System Admin'],
            [
                'scope' => AppRole::SCOPE_SYSTEM,
                'description' => 'System administrator',
                'allow_override' => true,
            ]
        );
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
                'description' => "Invariant grant for {$permissionKey}",
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

        $this->assertTrue(
            $user->hasPermission($permission->name),
            sprintf('Failed to grant %s to user %s', $permission->name, $user->id)
        );

        return $permission;
    }
}
