<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class WorkTemplateV2TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkTemplateV2Routes();
    }

    public function test_cross_tenant_template_and_version_lookup_returns_not_found(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], ['template.view']);
        $actorB = $this->createTenantUser($tenantB, [], ['member'], ['template.view']);

        [$templateB, $versionB] = $this->seedV2Template($tenantB, $actorB, 'WT-V2-B');

        $this->getJson($this->workTemplateRoute('show', ['id' => $templateB->id]), $this->authHeaders($actorA))
            ->assertStatus(404);

        $this->getJson(
            $this->workTemplateRoute('versions.show', ['id' => $templateB->id, 'versionId' => $versionB->id]),
            $this->authHeaders($actorA)
        )->assertStatus(404);
    }

    public function test_header_tenant_mismatch_returns_tenant_invalid(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], ['template.view']);
        [$templateA] = $this->seedV2Template($tenantA, $actorA, 'WT-V2-HDR');

        $headers = $this->authHeaders($actorA);
        $headers['X-Tenant-ID'] = (string) $tenantB->id;

        $this->getJson($this->workTemplateRoute('show', ['id' => $templateA->id]), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'TENANT_INVALID');
    }
}
