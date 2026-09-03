<?php declare(strict_types=1);

namespace Tests\Feature\Deployment;

use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-049 Gate-2 Round-2 Clarification 2, pre-release evidence half:
 * proves real negative cross-tenant isolation using at least two controlled
 * tenants exercising the real, live authorization/tenant-boundary code paths
 * (TenantIsolationMiddleware + RoleBasedAccessControlMiddleware +
 * App\Http\Controllers\Api\ProjectController's own tenant_id checks) —
 * executed in this disposable test-database environment only, never against
 * production, and never by manufacturing a fake production tenant.
 *
 * Route/model chosen: `App\Models\Project` over `/api/projects` (index/show/
 * update, registered as Route::apiResource('projects', ProjectController)
 * in routes/api.php under the `auth:sanctum`, `tenant.isolation`, `rbac`
 * middleware group). This is the same model/auth pattern already exercised
 * for tenant-scoped behavior by tests/Feature/GAP042RbacProductionFidelityTest.php
 * (Sanctum::actingAs(...) + a user created via
 * Tests\Traits\TenantUserFactoryTrait::createTenantUser(), which is also
 * used directly here) and by tests/Feature/MultiTenantIsolationTest.php,
 * which asserts Project tenant-scoping at the query level for the same
 * model. ProjectController::show()/update() independently re-check
 * `$project->tenant_id !== $user->tenant_id` and return 403/404 before any
 * mutation is applied, and ProjectController::index() filters by
 * `$user->tenant_id`, so this test exercises real production code paths,
 * not a re-implementation of tenant isolation.
 */
class TwoTenantIsolationEvidenceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_tenant_a_cannot_read_tenant_b_project_data(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B']);

        $userA = $this->createTenantUser($tenantA);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($userA);
        $response = $this->getJson("/api/projects/{$projectB->id}");

        $this->assertContains($response->getStatusCode(), [403, 404]);
    }

    public function test_tenant_a_cannot_mutate_tenant_b_project_data(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A2']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B2']);

        $userA = $this->createTenantUser($tenantA);
        $projectB = Project::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Original Name',
        ]);

        Sanctum::actingAs($userA);
        // ProjectFormRequest requires name/start_date/end_date to pass
        // validation before the controller's own tenant-ownership check
        // runs, so the payload must be otherwise-valid to prove the 403/404
        // comes from tenant isolation, not from a rejected form.
        $response = $this->putJson("/api/projects/{$projectB->id}", [
            'name' => 'Hijacked By Tenant A',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertSame('Original Name', $projectB->fresh()->name);
    }

    public function test_tenant_a_listing_never_includes_tenant_b_records(): void
    {
        $tenantA = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant A3']);
        $tenantB = Tenant::factory()->create(['name' => 'GAP-049 Evidence Tenant B3']);

        $userA = $this->createTenantUser($tenantA);
        $projectA = Project::factory()->create(['tenant_id' => $tenantA->id]);
        $projectB = Project::factory()->create(['tenant_id' => $tenantB->id]);

        Sanctum::actingAs($userA);
        $response = $this->getJson('/api/projects');

        $response->assertOk();
        $ids = collect($response->json('data') ?? [])->pluck('id')->all();
        $this->assertContains($projectA->id, $ids);
        $this->assertNotContains($projectB->id, $ids);
    }
}
