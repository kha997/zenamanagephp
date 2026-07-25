<?php declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\SiteDiary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * INV-1 follow-up: verify the LIVE route "site-diaries" (plural) is actually
 * protected end-to-end (auth -> permission -> tenant scoping), executed
 * against real HTTP requests, not the dead singular "site-diary" route.
 */
class SiteDiaryAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userBNoPerm;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], [
            'site_diary.view', 'site_diary.create', 'site_diary.approve',
        ]);

        // Tenant B user WITHOUT any site_diary permission.
        $this->userBNoPerm = $this->createTenantUser($this->tenantB, [], ['member'], []);
    }

    /** Scenario (a): no token at all -> 401 */
    public function test_a_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('api.zena.site-diaries.index', [], false), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $this->tenantA->id,
        ]);

        $response->assertStatus(401);
        echo "\n[a] unauthenticated index -> " . $response->getStatusCode() . "\n";
    }

    /** Scenario (b): authenticated, but missing site_diary.view permission -> 403 */
    public function test_b_authenticated_without_permission_is_forbidden(): void
    {
        $response = $this->getJson(route('api.zena.site-diaries.index', [], false), $this->headersFor($this->userBNoPerm));

        $response->assertStatus(403);
        echo "\n[b] no-permission index -> " . $response->getStatusCode() . "\n";
    }

    /** Scenario (c): correct permission, but cross-tenant target id (show + update) -> not leaked/mutated */
    public function test_c_cross_tenant_show_and_update_are_blocked(): void
    {
        $projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);

        $userBWithPerm = $this->createTenantUser($this->tenantB, [], ['admin'], ['site_diary.view', 'site_diary.create']);

        $diaryB = SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => (string) $projectB->id,
            'diary_number' => 'SD-CROSSTENANT-01',
            'diary_date' => '2026-07-20',
            'manpower_count' => 5,
            'work_performed' => 'Secret tenant B work',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $userBWithPerm->id,
        ]);

        // userA (tenant A, has permission) tries to read tenant B's diary by raw ID.
        $showResponse = $this->getJson(
            route('api.zena.site-diaries.show', ['id' => (string) $diaryB->id], false),
            $this->headersFor($this->userA)
        );
        $showResponse->assertStatus(404);
        $this->assertStringNotContainsString('Secret tenant B work', $showResponse->getContent() ?: '');
        echo "\n[c] cross-tenant show -> " . $showResponse->getStatusCode() . ' body=' . $showResponse->getContent() . "\n";

        // userA tries to update tenant B's diary by raw ID.
        $updateResponse = $this->putJson(
            route('api.zena.site-diaries.update', ['id' => (string) $diaryB->id], false),
            ['work_performed' => 'Hijacked by tenant A'],
            $this->headersFor($this->userA)
        );
        $updateResponse->assertStatus(404);
        echo "\n[c] cross-tenant update -> " . $updateResponse->getStatusCode() . "\n";

        // Confirm DB row untouched.
        $this->assertSame('Secret tenant B work', $diaryB->fresh()->work_performed);
    }

    /** Scenario (d): correct tenant + correct permission -> success */
    public function test_d_same_tenant_with_permission_succeeds(): void
    {
        $project = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);

        $storeResponse = $this->postJson(route('api.zena.site-diaries.store', [], false), [
            'project_id' => (string) $project->id,
            'diary_date' => '2026-07-25',
            'work_performed' => 'Legit same-tenant work',
        ], $this->headersFor($this->userA));

        $storeResponse->assertStatus(201);
        echo "\n[d] same-tenant store -> " . $storeResponse->getStatusCode() . "\n";

        $id = $storeResponse->json('data.id');

        $showResponse = $this->getJson(
            route('api.zena.site-diaries.show', ['id' => $id], false),
            $this->headersFor($this->userA)
        );
        $showResponse->assertStatus(200)->assertJsonPath('data.work_performed', 'Legit same-tenant work');
        echo "\n[d] same-tenant show -> " . $showResponse->getStatusCode() . "\n";
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('site-diary-authz-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
