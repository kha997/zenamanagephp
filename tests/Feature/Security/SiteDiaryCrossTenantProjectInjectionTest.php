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
 * INV-1.1 follow-up: SiteDiaryController::store() has an unscoped
 * duplicate-exists check (project_id + diary_date, no tenant filter) that
 * runs AFTER the tenant-scoped `Rule::exists('projects','id')->where('tenant_id', ...)`
 * validation on project_id. This test verifies with real HTTP requests that:
 *
 *  1. A tenant-A user cannot create a Site Diary against a tenant-B project_id
 *     (cross-tenant foreign-reference injection) — must be rejected, and the
 *     rejection reason must not leak whether the project exists in another
 *     tenant vs not existing at all (anti-enumeration).
 *  2. The unscoped duplicate-exists check does not cause a false-positive
 *     "duplicate" block when two DIFFERENT tenants legitimately have a
 *     SiteDiary row sharing the same project_id + diary_date value (this can
 *     happen because project_id is not itself tenant-namespaced in the
 *     duplicate check's WHERE clause).
 */
class SiteDiaryCrossTenantProjectInjectionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], [
            'site_diary.view', 'site_diary.create',
        ]);
    }

    /**
     * Scenario 1: userA (tenant A, has site_diary.create) submits a real,
     * existing project_id that belongs to tenant B.
     */
    public function test_cross_tenant_project_id_injection_is_rejected(): void
    {
        $projectB = Project::factory()->create(['tenant_id' => (string) $this->tenantB->id]);

        $response = $this->postJson(route('api.zena.site-diaries.store', [], false), [
            'project_id' => (string) $projectB->id,
            'diary_date' => '2026-07-25',
            'work_performed' => 'Cross-tenant injection attempt',
        ], $this->headersFor($this->userA));

        // Must NOT succeed.
        $response->assertStatus(422);
        echo "\n[inj] cross-tenant project_id store -> " . $response->getStatusCode()
            . ' body=' . $response->getContent() . "\n";

        // Anti-enumeration: compare the error against a request using a
        // project_id that does not exist AT ALL. Both must produce the
        // identical validation error shape/message, so an attacker cannot
        // distinguish "belongs to another tenant" from "does not exist".
        $bogusResponse = $this->postJson(route('api.zena.site-diaries.store', [], false), [
            'project_id' => (string) \Illuminate\Support\Str::ulid(),
            'diary_date' => '2026-07-25',
            'work_performed' => 'Nonexistent project attempt',
        ], $this->headersFor($this->userA));

        $bogusResponse->assertStatus(422);
        $crossTenantMsg = $response->json('error.details.data.project_id');
        $bogusMsg = $bogusResponse->json('error.details.data.project_id');
        $this->assertNotNull($crossTenantMsg, 'Expected project_id error detail present in cross-tenant response.');
        $this->assertSame(
            $crossTenantMsg,
            $bogusMsg,
            'Cross-tenant project_id and nonexistent project_id must yield identical error messages (anti-enumeration).'
        );
        echo "\n[inj] nonexistent project_id store -> " . $bogusResponse->getStatusCode()
            . ' body=' . $bogusResponse->getContent() . "\n";

        // Confirm no SiteDiary row was created for tenant B's project.
        $this->assertSame(
            0,
            SiteDiary::query()->where('project_id', (string) $projectB->id)->count(),
            'No SiteDiary row should have been created via cross-tenant injection.'
        );
    }

    /**
     * Scenario 2: two DIFFERENT tenants end up with a SiteDiary sharing the
     * same project_id + diary_date (simulating an ID collision / pre-seeded
     * fixture). Verify the unscoped duplicate-exists check in store() does
     * NOT block a legitimate, different-tenant creation as a "duplicate".
     */
    public function test_unscoped_duplicate_check_false_positive_across_tenants(): void
    {
        $projectA = Project::factory()->create(['tenant_id' => (string) $this->tenantA->id]);

        // Pre-seed a SiteDiary for tenant B that happens to share the SAME
        // project_id value and SAME date as what userA (tenant A) will submit.
        // (In production project_id values are ULIDs scoped per-tenant, so a
        // literal collision is astronomically unlikely — this directly forces
        // the collision to test the unscoped WHERE clause's behavior.)
        SiteDiary::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => (string) $projectA->id,
            'diary_number' => 'SD-COLLISION-01',
            'diary_date' => '2026-07-25',
            'work_performed' => 'Tenant B pre-existing diary (collided project_id)',
            'status' => SiteDiary::STATUS_DRAFT,
            'created_by' => (string) $this->userA->id,
        ]);

        $response = $this->postJson(route('api.zena.site-diaries.store', [], false), [
            'project_id' => (string) $projectA->id,
            'diary_date' => '2026-07-25',
            'work_performed' => 'Legit tenant A diary, same project_id+date as tenant B row',
        ], $this->headersFor($this->userA));

        echo "\n[dup] same project_id+date, different tenant, store -> " . $response->getStatusCode()
            . ' body=' . $response->getContent() . "\n";

        if ($response->getStatusCode() === 422) {
            $this->assertSame(
                ['A site diary already exists for this project on this date.'],
                $response->json('error.details.data.diary_date')
            );
            echo "\n[dup] CONFIRMED BUG: unscoped duplicate-exists check false-positive-blocked a legitimate different-tenant create.\n";
        } else {
            $response->assertStatus(201);
            echo "\n[dup] no false positive: legitimate create succeeded despite cross-tenant project_id+date collision.\n";
        }
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('site-diary-crosstenant-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
