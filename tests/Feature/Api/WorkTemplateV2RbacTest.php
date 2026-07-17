<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;
use Tests\TestCase;

class WorkTemplateV2RbacTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithWorkTemplateV2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWorkTemplateV2Routes();
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson($this->workTemplateRoute('index'), [
            'Accept' => 'application/json',
            'X-Tenant-ID' => 'missing-auth',
        ])->assertStatus(401);
    }

    public function test_create_and_delete_require_specific_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $creator = $this->createTenantUser($tenant, [], ['member'], [
            'template.view',
            'template.edit_draft',
            'template.publish',
            'template.delete',
        ]);
        $deleter = $this->createTenantUser($tenant, [], ['deleter_only'], ['template.view', 'template.delete']);
        $create = $this->postJson($this->workTemplateRoute('store'), $this->workTemplateV2Payload('WT-V2-RBAC'), $this->authHeaders($creator));
        $create->assertStatus(201);

        $templateId = (string) $create->json('data.id');

        $this->deleteJson($this->workTemplateRoute('destroy', ['id' => $templateId]), [], $this->authHeaders($deleter))
            ->assertStatus(200);
    }
}
