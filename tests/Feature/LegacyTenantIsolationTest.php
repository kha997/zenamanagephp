<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Rfi;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
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

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/zena/rfis/{$rfi->id}");

        $response->assertNotFound();
        $response->assertJsonFragment([
            'message' => 'RFI not found',
        ]);
    }
}
