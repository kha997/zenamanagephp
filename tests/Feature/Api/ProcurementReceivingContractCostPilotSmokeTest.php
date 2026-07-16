<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ProcurementReceivingContractCostPilotSmokeTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $operator;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();

        $this->operator = $this->createTenantUser($this->tenant, [], ['pilot_operator'], [
            'project.view',
            'material.view',
            'material.create',
            'vendor.view',
            'vendor.create',
            'contract.view',
            'contract.create',
            'material.read',
            'material.request',
            'material.approve',
            'material-receipt.view',
            'material-receipt.create',
            'material-receipt-checklist.view',
            'material-receipt-checklist.create',
            'material-receipt-line.view',
            'material-receipt-line.create',
        ]);

        $this->project = $this->createProjectPair($this->tenant, 'Pilot Procurement Project');
    }

    public function test_pilot_vertical_smoke_flow_runs_end_to_end_on_canonical_zena_routes(): void
    {
        $operatorHeaders = $this->headersFor($this->operator);

        $materialCreate = $this->postJson($this->materialRoute('store'), [
            'code' => 'mat-pilot-001',
            'name' => 'Pilot Cement',
            'category' => 'cement',
            'unit' => 'bag',
            'description' => 'Pilot receiving material',
        ], $operatorHeaders);

        $materialCreate->assertStatus(201)
            ->assertJsonPath('data.code', 'MAT-PILOT-001')
            ->assertJsonPath('data.name', 'Pilot Cement');

        $materialId = (string) $materialCreate->json('data.id');

        $vendorCreate = $this->postJson($this->vendorRoute('store'), [
            'code' => 'ven-pilot-001',
            'name' => 'Pilot Vendor Co',
            'contact_name' => 'Pilot Contact',
            'email' => 'pilot-vendor@example.com',
            'phone' => '0909111222',
            'address' => '123 Pilot Street',
        ], $operatorHeaders);

        $vendorCreate->assertStatus(201)
            ->assertJsonPath('data.code', 'VEN-PILOT-001')
            ->assertJsonPath('data.name', 'Pilot Vendor Co');

        $vendorId = (string) $vendorCreate->json('data.id');

        $contractCreate = $this->postJson($this->contractRoute('store', [
            'project' => (string) $this->project->id,
        ]), [
            'code' => 'ctr-pilot-001',
            'title' => 'Pilot Receiving Contract',
            'status' => 'active',
            'currency' => 'usd',
            'total_value' => 5000,
        ], $operatorHeaders);

        $contractCreate->assertStatus(201)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.code', 'CTR-PILOT-001')
            ->assertJsonPath('data.status', 'active');

        $contractId = (string) $contractCreate->json('data.id');

        $requestCreate = $this->postJson($this->requestRoute('store'), [
            'project_id' => (string) $this->project->id,
            'description' => 'Pilot request for cement delivery',
            'estimated_cost' => 100,
            'required_date' => '2026-04-30',
        ], $operatorHeaders);

        $requestCreate->assertStatus(201)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.description', 'Pilot request for cement delivery')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requested_by', (string) $this->operator->id);

        $materialRequestId = (string) $requestCreate->json('data.id');

        $this->postJson($this->requestRoute('submit', ['id' => $materialRequestId]), [], $operatorHeaders)
            ->assertOk()
            ->assertJsonPath('data.id', $materialRequestId)
            ->assertJsonPath('data.status', 'submitted');

        $approve = $this->postJson($this->requestRoute('approve', ['id' => $materialRequestId]), [], $operatorHeaders);

        $approve->assertOk()
            ->assertJsonPath('data.id', $materialRequestId)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', (string) $this->operator->id);

        $receiptCreate = $this->postJson($this->receiptRoute('store'), [
            'project_id' => (string) $this->project->id,
            'vendor_id' => $vendorId,
            'contract_id' => $contractId,
            'material_request_id' => $materialRequestId,
            'receipt_number' => 'mr-pilot-001',
            'receipt_date' => '2026-04-15',
        ], $operatorHeaders);

        $receiptCreate->assertStatus(201)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.vendor_id', $vendorId)
            ->assertJsonPath('data.contract_id', $contractId)
            ->assertJsonPath('data.material_request_id', $materialRequestId)
            ->assertJsonPath('data.receipt_number', 'MR-PILOT-001');

        $receiptId = (string) $receiptCreate->json('data.id');

        $this->postJson($this->receiptChecklistRoute('store', ['receipt' => $receiptId]), [
            'acceptance_summary' => 'Accepted for pilot smoke',
            'items' => [
                [
                    'item_key' => 'verify_quantity',
                    'label' => 'Verify delivered quantity',
                    'result' => 'pass',
                    'notes' => 'Quantity matches pilot delivery',
                ],
            ],
        ], $operatorHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.material_receipt_id', $receiptId)
            ->assertJsonPath('data.items.0.result', 'pass');

        $lineCreate = $this->postJson($this->receiptLineRoute('store', ['receipt' => $receiptId]), [
            'material_id' => $materialId,
            'quantity_received' => 4,
            'unit_cost' => 25,
            'notes' => 'Pilot smoke line',
        ], $operatorHeaders);

        $lineCreate->assertStatus(201)
            ->assertJsonPath('data.material_receipt_id', $receiptId)
            ->assertJsonPath('data.material_id', $materialId)
            ->assertJsonPath('data.quantity_received', 4)
            ->assertJsonPath('data.unit_cost', 25)
            ->assertJsonPath('data.notes', 'Pilot smoke line');

        $this->getJson($this->requestRoute('receipts', ['id' => $materialRequestId]), $operatorHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $receiptId)
            ->assertJsonPath('data.0.material_request_id', $materialRequestId)
            ->assertJsonPath('data.0.contract_id', $contractId);

        $this->getJson($this->receiptRoute('material-request', ['id' => $receiptId]), $operatorHeaders)
            ->assertOk()
            ->assertJsonPath('data.id', $materialRequestId)
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.status', 'approved');

        $this->getJson($this->contractRoute('material-receipts.index', [
            'project' => (string) $this->project->id,
            'contract' => $contractId,
        ]), $operatorHeaders)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $receiptId)
            ->assertJsonPath('data.0.contract_id', $contractId)
            ->assertJsonPath('data.0.material_request_id', $materialRequestId);

        $this->getJson($this->contractRoute('cost-summary.show', [
            'project' => (string) $this->project->id,
            'contract' => $contractId,
        ]), $operatorHeaders)
            ->assertOk()
            ->assertJsonPath('data.project_id', (string) $this->project->id)
            ->assertJsonPath('data.contract_id', $contractId)
            ->assertJsonPath('data.summary.mapped_receipt_count', 1)
            ->assertJsonPath('data.summary.line_count', 1)
            ->assertJsonPath('data.summary.priced_line_count', 1)
            ->assertJsonPath('data.summary.unpriced_line_count', 0)
            ->assertJsonPath('data.summary.priced_line_cost_total', 100);
    }

    private function materialRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.materials.' . $name, $parameters, false);
    }

    private function vendorRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.vendors.' . $name, $parameters, false);
    }

    private function requestRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.material-requests.' . $name, $parameters, false);
    }

    private function receiptRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.material-receipts.' . $name, $parameters, false);
    }

    private function receiptChecklistRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.material-receipts.checklists.' . $name, $parameters, false);
    }

    private function receiptLineRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.material-receipts.lines.' . $name, $parameters, false);
    }

    private function contractRoute(string $name, array $parameters = []): string
    {
        return route('api.zena.projects.contracts.' . $name, $parameters, false);
    }

    private function createProjectPair(Tenant $tenant, string $name): Project
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => $name,
        ]);

        DB::table('zena_projects')->insert([
            'id' => (string) $project->id,
            'tenant_id' => (string) $tenant->id,
            'code' => (string) $project->code,
            'name' => (string) $project->name,
            'description' => $project->description,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $project;
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('procurement-pilot-smoke-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
