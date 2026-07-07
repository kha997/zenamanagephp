<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptChecklist;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MaterialReceiptChecklistApiTest extends TestCase
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
            'material-receipt.view',
            'material-receipt.create',
            'material-receipt-checklist.view',
            'material-receipt-checklist.create',
        ];

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], $permissions);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], $permissions);
    }

    public function test_material_receipt_checklist_create_and_show_round_trip_on_canonical_child_path(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);

        $create = $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'acceptance_summary' => 'Accepted with one visual defect noted',
            'items' => [
                [
                    'item_key' => 'verify_quantity',
                    'label' => 'Verify delivered quantity',
                    'result' => 'pass',
                    'notes' => 'Count matched receipt note',
                ],
                [
                    'item_key' => 'verify_condition',
                    'label' => 'Verify material condition',
                    'result' => 'fail',
                    'notes' => 'Corner damage observed',
                ],
            ],
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.material_receipt_id', $receipt->id)
            ->assertJsonPath('data.project_id', $receipt->project_id)
            ->assertJsonPath('data.acceptance_summary', 'Accepted with one visual defect noted')
            ->assertJsonPath('data.items.0.item_key', 'verify_quantity')
            ->assertJsonPath('data.items.0.label', 'Verify delivered quantity')
            ->assertJsonPath('data.items.0.result', 'pass')
            ->assertJsonPath('data.items.1.result', 'fail');

        $checklistId = (string) $create->json('data.id');

        $this->getJson($this->route('show', ['receipt' => $receipt->id, 'checklist' => $checklistId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.id', $checklistId)
            ->assertJsonPath('data.material_receipt_id', $receipt->id)
            ->assertJsonPath('data.items.1.label', 'Verify material condition')
            ->assertJsonPath('data.items.1.notes', 'Corner damage observed');

        $this->assertDatabaseHas('material_receipt_checklists', [
            'id' => $checklistId,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'acceptance_summary' => 'Accepted with one visual defect noted',
        ]);

        $stored = MaterialReceiptChecklist::query()->findOrFail($checklistId);
        $this->assertSame('verify_quantity', $stored->items[0]['item_key'] ?? null);
        $this->assertSame('Verify material condition', $stored->items[1]['label'] ?? null);
        $this->assertSame('fail', $stored->items[1]['result'] ?? null);
    }

    public function test_material_receipt_checklist_show_returns_404_for_cross_tenant_access(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $checklist = MaterialReceiptChecklist::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receipt->project_id,
            'material_receipt_id' => $receipt->id,
            'acceptance_summary' => null,
            'items' => [
                [
                    'item_key' => 'verify_quantity',
                    'label' => 'Verify delivered quantity',
                    'result' => 'pass',
                    'notes' => null,
                ],
            ],
        ]);

        $this->getJson(
            $this->route('show', ['receipt' => $receipt->id, 'checklist' => $checklist->id]),
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_receipt_checklist_show_returns_404_when_checklist_is_requested_under_wrong_receipt(): void
    {
        $receiptA = $this->createReceiptForTenant($this->tenantA, 'MR-CHK-001');
        $receiptB = $this->createReceiptForTenant($this->tenantA, 'MR-CHK-002');

        $checklist = MaterialReceiptChecklist::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $receiptA->project_id,
            'material_receipt_id' => $receiptA->id,
            'acceptance_summary' => 'Accepted',
            'items' => [
                [
                    'item_key' => 'verify_condition',
                    'label' => 'Verify material condition',
                    'result' => 'pass',
                    'notes' => null,
                ],
            ],
        ]);

        $this->getJson(
            $this->route('show', ['receipt' => $receiptB->id, 'checklist' => $checklist->id]),
            $this->headersFor($this->userA)
        )->assertStatus(404);
    }

    public function test_material_receipt_checklist_rbac_denies_without_canonical_permissions(): void
    {
        $receipt = $this->createReceiptForTenant($this->tenantA);
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material-receipt.view']);
        $headers = $this->headersFor($restricted);

        $this->postJson($this->route('store', ['receipt' => $receipt->id]), [
            'items' => [
                [
                    'item_key' => 'verify_quantity',
                    'label' => 'Verify delivered quantity',
                    'result' => 'pass',
                ],
            ],
        ], $headers)->assertStatus(403);
    }

    private function createReceiptForTenant(Tenant $tenant, string $receiptNumber = 'MR-CHK-BASE'): MaterialReceipt
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

    private function route(string $name, array $parameters = []): string
    {
        return route('api.zena.material-receipts.checklists.' . $name, $parameters, false);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('material-receipt-checklist-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
