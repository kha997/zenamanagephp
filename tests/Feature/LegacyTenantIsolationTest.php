<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegacyTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_rfis_are_blocked_for_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->forTenant((string) $tenantA->id)->create();
        $userB = User::factory()->forTenant((string) $tenantB->id)->create();

        $permission = Permission::factory()->create([
            'code' => 'rfi.view',
            'name' => 'rfi.view',
            'module' => 'rfi',
            'action' => 'view',
            'description' => 'View RFIs',
        ]);

        $role = Role::factory()->create([
            'name' => 'legacy-rfi-viewer',
            'scope' => 'system',
            'is_active' => true,
        ]);

        $role->permissions()->attach($permission->id);
        $userB->roles()->attach($role->id);

        $projectA = Project::factory()->create([
            'tenant_id' => $tenantA->id,
            'created_by' => $userA->id,
            'pm_id' => $userA->id,
        ]);

        $rfi = Rfi::query()->forceCreate([
            'tenant_id' => $tenantA->id,
            'project_id' => $projectA->id,
            'title' => 'Tenant A RFI',
            'subject' => 'Legacy isolation',
            'description' => 'Legacy request for information',
            'question' => 'Could you clarify the legacy isolation requirements?',
            'priority' => 'medium',
            'created_by' => $userA->id,
            'asked_by' => $userA->id,
            'rfi_number' => 'RFI-0001',
            'status' => 'open',
        ]);

        $this->actingAs($userB);
        $this->apiAs($userB, $tenantB);

        $response = $this
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenantB->id,
            ])
            ->getJson("/api/zena/rfis/{$rfi->id}");

        if ($response->status() === 403) {
            $code = $response->json('error.code');
            $this->assertTrue(
                in_array($code, ['TENANT_INVALID', 'E403.AUTHORIZATION'], true),
                sprintf(
                    'Cross-tenant RFI read returned 403 (%s); expected 404 with tenant isolation handling (TENANT_INVALID) or RBAC denial before enumeration.',
                    $code
                )
            );
        }

        $response->assertNotFound();
        $response->assertJsonFragment([
            'message' => 'RFI not found',
        ]);

        $missingTenantResponse = $this
            ->flushHeaders()
            ->withHeaders([
                'Accept' => 'application/json',
            ])
            ->getJson("/api/zena/rfis/{$rfi->id}");

        $missingTenantResponse->assertStatus(400);
        $this->assertSame(
            'TENANT_REQUIRED',
            $missingTenantResponse->json('error.code'),
            'Tenant header is required before tenant isolation runs.'
        );

        $tenantMismatchResponse = $this
            ->flushHeaders()
            ->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $tenantA->id,
            ])
            ->getJson("/api/zena/rfis/{$rfi->id}");

        $tenantMismatchResponse->assertStatus(403);
        $this->assertSame(
            'TENANT_INVALID',
            $tenantMismatchResponse->json('error.code'),
            'Tenant header must match the authenticated user.'
        );
    }
}
