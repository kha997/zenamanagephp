<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Component;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class BoqApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();
        $permissions = [
            'project.view',
            'boq.view',
            'boq.create',
            'boq.update',
            'boq.delete',
        ];

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], $permissions);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], $permissions);
    }

    public function test_boq_and_nested_line_item_crud_round_trip_on_canonical_zena_path(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $component = Component::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
        ]);

        $createBoq = $this->postJson($this->boqRoute('store'), [
            'project_id' => $project->id,
            'code' => 'boq-001',
            'name' => 'Structural BOQ',
            'description' => 'Owner contract proof',
        ], $this->headersFor($this->userA));

        $createBoq->assertStatus(201)
            ->assertJsonPath('data.code', 'BOQ-001')
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.name', 'Structural BOQ');

        $boqId = (string) $createBoq->json('data.id');

        $this->getJson($this->boqRoute('index'), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $boqId);

        $createLineItem = $this->postJson($this->lineItemRoute('store', ['boq' => $boqId]), [
            'code' => 'LI-001',
            'name' => 'Concrete footing',
            'description' => 'Nested owner contract proof',
            'quantity' => 12.5,
            'unit' => 'm3',
            'component_id' => $component->id,
        ], $this->headersFor($this->userA));

        $createLineItem->assertStatus(201)
            ->assertJsonPath('data.component_id', (string) $component->id)
            ->assertJsonPath('data.quantity', 12.5)
            ->assertJsonPath('data.unit', 'm3');

        $lineItemId = (string) $createLineItem->json('data.id');

        $this->getJson($this->lineItemRoute('index', ['boq' => $boqId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $lineItemId);

        $this->getJson($this->boqRoute('show', ['id' => $boqId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.line_items.0.id', $lineItemId);

        $this->putJson($this->lineItemRoute('update', ['boq' => $boqId, 'lineItem' => $lineItemId]), [
            'quantity' => 15,
            'unit' => 'm2',
            'component_id' => null,
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.quantity', 15)
            ->assertJsonPath('data.unit', 'm2')
            ->assertJsonPath('data.component_id', null);

        $this->putJson($this->boqRoute('update', ['id' => $boqId]), [
            'name' => 'Structural BOQ Updated',
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.name', 'Structural BOQ Updated');

        $this->assertDatabaseHas('boqs', [
            'id' => $boqId,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'name' => 'Structural BOQ Updated',
        ]);

        $this->assertDatabaseHas('boq_line_items', [
            'id' => $lineItemId,
            'tenant_id' => (string) $this->tenantA->id,
            'boq_id' => $boqId,
            'quantity' => 15.000,
            'unit' => 'm2',
            'component_id' => null,
        ]);

        $this->deleteJson($this->lineItemRoute('destroy', ['boq' => $boqId, 'lineItem' => $lineItemId]), [], $this->headersFor($this->userA))
            ->assertOk();

        $this->assertDatabaseMissing('boq_line_items', [
            'id' => $lineItemId,
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $this->deleteJson($this->boqRoute('destroy', ['id' => $boqId]), [], $this->headersFor($this->userA))
            ->assertOk();

        $this->assertDatabaseMissing('boqs', [
            'id' => $boqId,
            'tenant_id' => (string) $this->tenantA->id,
        ]);
    }

    public function test_boq_requires_project_id_and_line_item_component_must_match_parent_project(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $otherProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $otherProjectComponent = Component::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
        ]);

        $response = $this->postJson($this->boqRoute('store'), [
            'code' => 'boq-no-project',
            'name' => 'Missing Project',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'BOQ-VALID-001',
            'name' => 'Valid Parent BOQ',
        ]);

        $this->postJson($this->lineItemRoute('store', ['boq' => $boq->id]), [
            'name' => 'Wrong Component Link',
            'quantity' => 1,
            'component_id' => $otherProjectComponent->id,
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('boq_line_items', [
            'tenant_id' => (string) $this->tenantA->id,
            'boq_id' => $boq->id,
            'component_id' => (string) $otherProjectComponent->id,
        ]);
    }

    public function test_boq_and_line_items_are_tenant_safe_with_404_anti_enumeration(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'BOQ-CROSS-001',
            'name' => 'Tenant A BOQ',
        ]);

        $lineItem = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'boq_id' => $boq->id,
            'name' => 'Tenant A line item',
            'quantity' => 2,
        ]);

        $this->getJson($this->boqRoute('show', ['id' => $boq->id]), $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->getJson($this->lineItemRoute('show', ['boq' => $boq->id, 'lineItem' => $lineItem->id]), $this->headersFor($this->userB))
            ->assertStatus(404);

        $this->deleteJson($this->boqRoute('destroy', ['id' => $boq->id]), [], $this->headersFor($this->userB))
            ->assertStatus(404);
    }

    public function test_boq_rbac_denies_without_canonical_permissions(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);
        $headers = $this->headersFor($restricted);

        $this->getJson($this->boqRoute('index'), $headers)->assertStatus(403);
        $this->postJson($this->boqRoute('store'), [
            'project_id' => 'missing',
            'code' => 'BOQ-NOPE-001',
            'name' => 'No Permission',
        ], $headers)->assertStatus(403);
    }

    private function boqRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.boqs.' . $name, $parameters, false);
    }

    private function lineItemRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.boqs.line-items.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('boq-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
