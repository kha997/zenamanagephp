<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Material;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MaterialReceiptLineApiTest extends TestCase
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
            'material.view',
            'material-receipt.view',
            'material-receipt.create',
            'material-receipt-line.view',
            'material-receipt-line.create',
        ];

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], $permissions);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], $permissions);
    }

    public function test_material_receipt_line_create_list_and_show_round_trip_on_canonical_child_path(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-001', 'Concrete');

        $create = $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'material_id' => $material->id,
            'quantity_received' => 12.5,
            'unit_cost' => 45.67,
            'notes' => 'Verified against truck manifest',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.material_receipt_id', $receipt->id)
            ->assertJsonPath('data.project_id', $receipt->project_id)
            ->assertJsonPath('data.material_id', $material->id)
            ->assertJsonPath('data.quantity_received', 12.5)
            ->assertJsonPath('data.unit_cost', 45.67)
            ->assertJsonPath('data.notes', 'Verified against truck manifest')
            ->assertJsonPath('data.material.id', $material->id)
            ->assertJsonPath('data.material.code', 'MAT-001')
            ->assertJsonPath('data.material.unit', 'm3');

        $lineId = (string) $create->json('data.id');

        $this->getJson($this->route('index', ['receipt' => $receipt->id]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $lineId)
            ->assertJsonPath('data.0.material.id', $material->id)
            ->assertJsonPath('data.0.unit_cost', 45.67);

        $this->getJson($this->route('show', ['receipt' => $receipt->id, 'line' => $lineId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.id', $lineId)
            ->assertJsonPath('data.material.id', $material->id)
            ->assertJsonPath('data.quantity_received', 12.5)
            ->assertJsonPath('data.unit_cost', 45.67);

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => $lineId,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 12.500,
            'unit_cost' => 45.67,
            'notes' => 'Verified against truck manifest',
        ]);
    }

    public function test_material_receipt_line_create_succeeds_without_unit_cost(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-NO-COST');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-NO-COST', 'No Cost Material');

        $create = $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'material_id' => $material->id,
            'quantity_received' => 6,
            'notes' => 'No price available yet',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.quantity_received', 6)
            ->assertJsonPath('data.unit_cost', null)
            ->assertJsonPath('data.notes', 'No price available yet');

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => (string) $create->json('data.id'),
            'tenant_id' => (string) $this->tenantA->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 6.000,
            'unit_cost' => null,
        ]);
    }

    public function test_material_receipt_line_create_rejects_negative_unit_cost(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-NEG-COST');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-NEG-COST', 'Negative Cost Material');

        $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'material_id' => $material->id,
            'quantity_received' => 2,
            'unit_cost' => -1,
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipt_lines', [
            'tenant_id' => (string) $this->tenantA->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 2.000,
        ]);
    }

    public function test_material_receipt_line_requires_same_tenant_material(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $foreignMaterial = $this->createMaterialForTenant($this->tenantB, 'MAT-B', 'Foreign Material');

        $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'material_id' => $foreignMaterial->id,
            'quantity_received' => 3,
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipt_lines', [
            'tenant_id' => (string) $this->tenantA->id,
            'material_id' => $foreignMaterial->id,
        ]);
    }

    public function test_material_receipt_line_show_returns_404_for_cross_tenant_access(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-CROSS', 'Cross Tenant Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 4.25,
            'unit_cost' => 9.75,
            'notes' => null,
        ]);

        $this->getJson(
            $this->route('show', ['receipt' => $receipt->id, 'line' => $line->id]),
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_receipt_line_show_returns_404_when_line_is_requested_under_wrong_receipt(): void
    {
        $receiptA = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-001');
        $receiptB = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-002');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-WRONG', 'Wrong Parent Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receiptA->project_id,
            'material_receipt_id' => $receiptA->id,
            'material_id' => $material->id,
            'quantity_received' => 8,
            'unit_cost' => 11.50,
            'notes' => 'Belongs to receipt A',
        ]);

        $this->getJson(
            $this->route('show', ['receipt' => $receiptB->id, 'line' => $line->id]),
            $this->headersFor($this->userA)
        )->assertStatus(404);
    }

    public function test_material_receipt_line_update_corrects_unit_cost_and_notes_on_canonical_child_path(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-UPD-001');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-UPD', 'Update Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 5.5,
            'unit_cost' => null,
            'notes' => 'Keep unchanged',
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]),
            [
                'unit_cost' => 12.34,
                'quantity_received' => 999,
            ],
            $this->headersFor($this->userA)
        )
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.id', $line->id)
            ->assertJsonPath('data.unit_cost', 12.34)
            ->assertJsonPath('data.quantity_received', 5.5)
            ->assertJsonPath('data.notes', 'Keep unchanged')
            ->assertJsonPath('data.material_id', $material->id)
            ->assertJsonMissingPath('data.tenant_id');

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => $line->id,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 5.500,
            'unit_cost' => 12.34,
            'notes' => 'Keep unchanged',
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]),
            [
                'notes' => 'Corrected note',
                'quantity_received' => 777,
            ],
            $this->headersFor($this->userA)
        )
            ->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('data.id', $line->id)
            ->assertJsonPath('data.unit_cost', 12.34)
            ->assertJsonPath('data.quantity_received', 5.5)
            ->assertJsonPath('data.notes', 'Corrected note')
            ->assertJsonPath('data.material_id', $material->id)
            ->assertJsonMissingPath('data.tenant_id');

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => $line->id,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 5.500,
            'unit_cost' => 12.34,
            'notes' => 'Corrected note',
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]),
            [
                'unit_cost' => null,
                'notes' => null,
            ],
            $this->headersFor($this->userA)
        )
            ->assertOk()
            ->assertJsonPath('data.unit_cost', null)
            ->assertJsonPath('data.quantity_received', 5.5)
            ->assertJsonPath('data.notes', null)
            ->assertJsonMissingPath('data.tenant_id');

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => $line->id,
            'unit_cost' => null,
            'quantity_received' => 5.500,
            'notes' => null,
        ]);
    }

    public function test_material_receipt_line_update_returns_404_for_cross_tenant_access(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-UPD-404');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-UPD-404', 'Update 404 Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 3,
            'unit_cost' => null,
            'notes' => null,
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]),
            ['unit_cost' => 7.5],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_receipt_line_update_returns_404_under_wrong_receipt(): void
    {
        $receiptA = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-UPD-A');
        $receiptB = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-UPD-B');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-UPD-WRONG', 'Wrong Parent Update Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receiptA->project_id,
            'material_receipt_id' => $receiptA->id,
            'material_id' => $material->id,
            'quantity_received' => 2,
            'unit_cost' => null,
            'notes' => null,
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receiptB->id, 'line' => $line->id]),
            ['unit_cost' => 7.5],
            $this->headersFor($this->userA)
        )->assertStatus(404);
    }

    public function test_material_receipt_line_update_rejects_negative_unit_cost(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA, 'MR-LINE-UPD-NEG');
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-UPD-NEG', 'Negative Update Material');

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 4,
            'unit_cost' => 8.25,
            'notes' => null,
        ]);

        $this->putJson(
            $this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]),
            ['unit_cost' => -1],
            $this->headersFor($this->userA)
        )->assertStatus(422);

        $this->assertDatabaseHas('material_receipt_lines', [
            'id' => $line->id,
            'unit_cost' => 8.25,
            'quantity_received' => 4.000,
        ]);
    }

    public function test_material_receipt_line_rbac_denies_without_canonical_permissions(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $material = $this->createMaterialForTenant($this->tenantA, 'MAT-NOPE', 'Restricted Material');
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material-receipt.view']);
        $headers = $this->headersFor($restricted);

        $this->getJson($this->route('index', ['receipt' => $receipt->id]), $headers)->assertStatus(403);

        $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'material_id' => $material->id,
            'quantity_received' => 1,
        ], $headers)->assertStatus(403);

        $line = MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 1,
            'unit_cost' => null,
            'notes' => null,
        ]);

        $this->putJson($this->route('update', ['receipt' => $receipt->id, 'line' => $line->id]), [
            'unit_cost' => 1.25,
        ], $headers)->assertStatus(403);
    }

    private function createReceiptForTenant(Tenant $tenant, string $receiptNumber = 'MR-LINE-BASE'): MaterialReceipt
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        return MaterialReceipt::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => $project->id,
            'receipt_number' => $receiptNumber,
            'receipt_date' => '2026-03-30',
        ]);
    }

    private function createMaterialForTenant(Tenant $tenant, string $code, string $name): Material
    {
        return Material::query()->create([
            'tenant_id' => (string) $tenant->id,
            'code' => $code,
            'name' => $name,
            'category' => 'concrete',
            'unit' => 'm3',
            'description' => $name . ' description',
            'is_active' => true,
        ]);
    }

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.material-receipts.lines.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('material-receipt-line-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
