<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\AuthenticationTrait;

class LegacyTenantIsolationTest extends TestCase
{
    use RefreshDatabase, AuthenticationTrait;

    public function test_rfis_are_blocked_for_other_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = $this->createTenantUser($tenantA, [], null, ['rfi.view']);
        $userB = $this->createTenantUser($tenantB, [], null, ['rfi.view']);

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

        $missingTenantResponse->assertNotFound();
        $missingTenantResponse->assertJsonFragment([
            'message' => 'RFI not found',
        ]);

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
