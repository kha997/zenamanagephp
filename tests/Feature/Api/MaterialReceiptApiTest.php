<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MaterialReceiptApiTest extends TestCase
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
            'vendor.view',
            'material-receipt.view',
            'material-receipt.create',
        ];

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], $permissions);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], $permissions);
    }

    public function test_material_receipt_header_create_list_and_show_round_trip_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Project');

        $contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CTR-MR-001',
            'title' => 'Receipt Contract',
        ]);

        $vendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'VENDOR-001',
            'name' => 'Concrete Supply Co',
        ]);

        $create = $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'contract_id' => $contract->id,
            'receipt_number' => 'mr-001',
            'receipt_date' => '2026-03-29',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.vendor_id', $vendor->id)
            ->assertJsonPath('data.contract_id', $contract->id)
            ->assertJsonPath('data.material_request_id', null)
            ->assertJsonPath('data.receipt_number', 'MR-001')
            ->assertJsonPath('data.receipt_date', '2026-03-29T00:00:00.000000Z')
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.vendor.id', $vendor->id);

        $receiptId = (string) $create->json('data.id');

        $this->getJson($this->route('index'), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.0.id', $receiptId)
            ->assertJsonPath('data.0.material_request_id', null)
            ->assertJsonPath('data.0.project.id', $project->id)
            ->assertJsonPath('data.0.vendor.id', $vendor->id);

        $this->getJson($this->route('show', ['id' => $receiptId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.id', $receiptId)
            ->assertJsonPath('data.material_request_id', null)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.vendor.id', $vendor->id);

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receiptId,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'contract_id' => $contract->id,
            'material_request_id' => null,
            'receipt_number' => 'MR-001',
            'receipt_date' => '2026-03-29 00:00:00',
        ]);
    }

    public function test_material_receipt_create_accepts_optional_same_project_material_request_linkage(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MRQ-LINK-001',
            'description' => 'Linked request',
            'status' => 'submitted',
            'estimated_cost' => 1200.00,
            'required_date' => '2026-04-20',
            'requested_by' => $this->userA->id,
        ]);

        $create = $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-LINKED-001',
            'receipt_date' => '2026-04-13',
        ], $this->headersFor($this->userA));

        $create->assertStatus(201)
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.material_request_id', $materialRequest->id)
            ->assertJsonPath('data.receipt_number', 'MR-LINKED-001');

        $receiptId = (string) $create->json('data.id');

        $this->getJson($this->route('show', ['id' => $receiptId]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.material_request_id', $materialRequest->id);

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receiptId,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-LINKED-001',
        ]);
    }

    public function test_material_receipt_create_rejects_contract_from_different_project_in_same_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Valid Project');

        $otherProject = $this->createProjectPair($this->tenantA, 'Receipt Link Other Project');

        $foreignProjectContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'code' => 'CTR-WRONG-PROJECT',
            'title' => 'Wrong Project Contract',
        ]);

        $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'contract_id' => $foreignProjectContract->id,
            'receipt_number' => 'MR-WRONG-PROJECT-001',
            'receipt_date' => '2026-04-03',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipts', [
            'tenant_id' => (string) $this->tenantA->id,
            'receipt_number' => 'MR-WRONG-PROJECT-001',
        ]);
    }

    public function test_material_receipt_create_rejects_cross_tenant_contract_reference(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Local Project');

        $foreignProject = $this->createProjectPair($this->tenantB, 'Receipt Link Foreign Project');

        $foreignContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => $foreignProject->id,
            'code' => 'CTR-FOREIGN-TENANT',
            'title' => 'Foreign Tenant Contract',
        ]);

        $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'contract_id' => $foreignContract->id,
            'receipt_number' => 'MR-FOREIGN-CONTRACT-001',
            'receipt_date' => '2026-04-03',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipts', [
            'tenant_id' => (string) $this->tenantA->id,
            'receipt_number' => 'MR-FOREIGN-CONTRACT-001',
        ]);
    }

    public function test_material_receipt_create_rejects_material_request_from_different_project_in_same_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Local Request Project');

        $otherProject = $this->createProjectPair($this->tenantA, 'Receipt Link Wrong Request Project');

        $foreignProjectRequest = MaterialRequest::query()->create([
            'project_id' => $otherProject->id,
            'request_number' => 'MRQ-WRONG-PROJECT',
            'description' => 'Wrong project request',
            'status' => 'approved',
            'estimated_cost' => 450.00,
            'required_date' => '2026-04-21',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'material_request_id' => $foreignProjectRequest->id,
            'receipt_number' => 'MR-WRONG-REQUEST-PROJECT-001',
            'receipt_date' => '2026-04-13',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipts', [
            'tenant_id' => (string) $this->tenantA->id,
            'receipt_number' => 'MR-WRONG-REQUEST-PROJECT-001',
        ]);
    }

    public function test_material_receipt_create_rejects_cross_tenant_material_request_reference(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Link Tenant A Project');

        $foreignProject = $this->createProjectPair($this->tenantB, 'Receipt Link Tenant B Project');

        $foreignRequest = MaterialRequest::query()->create([
            'project_id' => $foreignProject->id,
            'request_number' => 'MRQ-FOREIGN-TENANT',
            'description' => 'Foreign tenant request',
            'status' => 'approved',
            'estimated_cost' => 800.00,
            'required_date' => '2026-04-22',
            'requested_by' => $this->userB->id,
        ]);

        $this->postJson($this->route('store'), [
            'project_id' => $project->id,
            'material_request_id' => $foreignRequest->id,
            'receipt_number' => 'MR-FOREIGN-REQUEST-001',
            'receipt_date' => '2026-04-13',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipts', [
            'tenant_id' => (string) $this->tenantA->id,
            'receipt_number' => 'MR-FOREIGN-REQUEST-001',
        ]);
    }

    public function test_material_receipt_create_requires_same_tenant_project_and_vendor(): void
    {
        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenantB->id,
        ]);

        $foreignVendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'code' => 'VENDOR-B',
            'name' => 'Other Tenant Vendor',
        ]);

        $this->postJson($this->route('store'), [
            'project_id' => $foreignProject->id,
            'vendor_id' => $foreignVendor->id,
            'receipt_number' => 'MR-FOREIGN-001',
            'receipt_date' => '2026-03-29',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseMissing('material_receipts', [
            'tenant_id' => (string) $this->tenantA->id,
            'receipt_number' => 'MR-FOREIGN-001',
        ]);
    }

    public function test_material_receipt_index_supports_same_tenant_contract_filter_without_cross_tenant_enumeration(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Update Link Project');

        $otherProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $matchingContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CTR-MATCH',
            'title' => 'Matching Contract',
        ]);

        $otherContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'code' => 'CTR-OTHER',
            'title' => 'Other Contract',
        ]);

        $foreignProject = Project::factory()->create([
            'tenant_id' => (string) $this->tenantB->id,
        ]);

        $foreignContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => $foreignProject->id,
            'code' => 'CTR-FOREIGN',
            'title' => 'Foreign Contract',
        ]);

        $matchingReceipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'contract_id' => $matchingContract->id,
            'receipt_number' => 'MR-FILTER-MATCH',
            'receipt_date' => '2026-04-13',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'contract_id' => $otherContract->id,
            'receipt_number' => 'MR-FILTER-OTHER',
            'receipt_date' => '2026-04-14',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'contract_id' => null,
            'receipt_number' => 'MR-FILTER-NULL',
            'receipt_date' => '2026-04-15',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => $foreignProject->id,
            'contract_id' => $foreignContract->id,
            'receipt_number' => 'MR-FILTER-FOREIGN',
            'receipt_date' => '2026-04-16',
        ]);

        $this->getJson($this->route('index', ['contract_id' => $matchingContract->id]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $matchingReceipt->id)
            ->assertJsonPath('data.0.contract_id', (string) $matchingContract->id);

        $this->getJson($this->route('index', ['contract_id' => $foreignContract->id]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_material_receipt_index_supports_same_tenant_material_request_filter_without_cross_tenant_enumeration(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Filter Request Project');
        $otherProject = $this->createProjectPair($this->tenantA, 'Receipt Filter Other Project');
        $foreignProject = $this->createProjectPair($this->tenantB, 'Receipt Filter Foreign Project');

        $matchingRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MRQ-FILTER-MATCH',
            'description' => 'Matching request',
            'status' => 'approved',
            'estimated_cost' => 300.00,
            'required_date' => '2026-04-20',
            'requested_by' => $this->userA->id,
        ]);

        $otherRequest = MaterialRequest::query()->create([
            'project_id' => $otherProject->id,
            'request_number' => 'MRQ-FILTER-OTHER',
            'description' => 'Other request',
            'status' => 'submitted',
            'estimated_cost' => 400.00,
            'required_date' => '2026-04-21',
            'requested_by' => $this->userA->id,
        ]);

        $foreignRequest = MaterialRequest::query()->create([
            'project_id' => $foreignProject->id,
            'request_number' => 'MRQ-FILTER-FOREIGN',
            'description' => 'Foreign request',
            'status' => 'approved',
            'estimated_cost' => 500.00,
            'required_date' => '2026-04-22',
            'requested_by' => $this->userB->id,
        ]);

        $matchingReceipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => $matchingRequest->id,
            'receipt_number' => 'MR-REQ-FILTER-MATCH',
            'receipt_date' => '2026-04-13',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'material_request_id' => $otherRequest->id,
            'receipt_number' => 'MR-REQ-FILTER-OTHER',
            'receipt_date' => '2026-04-14',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => null,
            'receipt_number' => 'MR-REQ-FILTER-NULL',
            'receipt_date' => '2026-04-15',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'project_id' => $foreignProject->id,
            'material_request_id' => $foreignRequest->id,
            'receipt_number' => 'MR-REQ-FILTER-FOREIGN',
            'receipt_date' => '2026-04-16',
        ]);

        $this->getJson($this->route('index', ['material_request_id' => $matchingRequest->id]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $matchingReceipt->id)
            ->assertJsonPath('data.0.material_request_id', (string) $matchingRequest->id);

        $this->getJson($this->route('index', ['material_request_id' => $foreignRequest->id]), $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_material_receipt_update_corrects_vendor_contract_and_date_before_any_lines_exist(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Update Link Project');

        $originalVendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'VENDOR-OLD',
            'name' => 'Original Vendor',
        ]);

        $updatedVendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'VENDOR-NEW',
            'name' => 'Updated Vendor',
        ]);

        $originalContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CTR-UPD-OLD',
            'title' => 'Original Contract',
        ]);

        $updatedContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'code' => 'CTR-UPD-NEW',
            'title' => 'Updated Contract',
        ]);

        $updatedRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MRQ-UPD-NEW',
            'description' => 'Updated request linkage',
            'status' => 'submitted',
            'estimated_cost' => 650.00,
            'required_date' => '2026-04-18',
            'requested_by' => $this->userA->id,
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $originalVendor->id,
            'contract_id' => $originalContract->id,
            'receipt_number' => 'MR-UPD-001',
            'receipt_date' => '2026-04-13',
        ]);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'vendor_id' => $updatedVendor->id,
            'contract_id' => $updatedContract->id,
            'material_request_id' => $updatedRequest->id,
            'receipt_date' => '2026-04-14',
            'project_id' => 'ignored-project-change',
            'receipt_number' => 'IGNORED-CHANGE',
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.id', $receipt->id)
            ->assertJsonPath('data.vendor_id', $updatedVendor->id)
            ->assertJsonPath('data.contract_id', $updatedContract->id)
            ->assertJsonPath('data.material_request_id', $updatedRequest->id)
            ->assertJsonPath('data.receipt_date', '2026-04-14T00:00:00.000000Z')
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.receipt_number', 'MR-UPD-001')
            ->assertJsonPath('data.vendor.id', $updatedVendor->id);

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receipt->id,
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $updatedVendor->id,
            'contract_id' => $updatedContract->id,
            'material_request_id' => $updatedRequest->id,
            'receipt_number' => 'MR-UPD-001',
            'receipt_date' => '2026-04-14 00:00:00',
        ]);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'vendor_id' => null,
            'contract_id' => null,
            'material_request_id' => null,
        ], $this->headersFor($this->userA))
            ->assertOk()
            ->assertJsonPath('data.vendor_id', null)
            ->assertJsonPath('data.contract_id', null)
            ->assertJsonPath('data.material_request_id', null)
            ->assertJsonPath('data.receipt_number', 'MR-UPD-001');

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receipt->id,
            'vendor_id' => null,
            'contract_id' => null,
            'material_request_id' => null,
            'receipt_number' => 'MR-UPD-001',
            'receipt_date' => '2026-04-14 00:00:00',
        ]);
    }

    public function test_material_receipt_update_returns_404_for_cross_tenant_access(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-UPD-404',
            'receipt_date' => '2026-04-13',
        ]);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'receipt_date' => '2026-04-14',
        ], $this->headersFor($this->userB))->assertStatus(404);
    }

    public function test_material_receipt_update_rejects_invalid_same_tenant_vendor_contract_and_request_relations(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Update Relation Project');

        $otherProject = $this->createProjectPair($this->tenantA, 'Receipt Update Wrong Relation Project');

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-UPD-422',
            'receipt_date' => '2026-04-13',
        ]);

        $foreignVendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantB->id,
            'code' => 'VENDOR-FOREIGN',
            'name' => 'Foreign Vendor',
        ]);

        $wrongProjectContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'code' => 'CTR-UPD-WRONG',
            'title' => 'Wrong Project Contract',
        ]);

        $wrongProjectRequest = MaterialRequest::query()->create([
            'project_id' => $otherProject->id,
            'request_number' => 'MRQ-UPD-WRONG',
            'description' => 'Wrong project request',
            'status' => 'approved',
            'estimated_cost' => 500.00,
            'required_date' => '2026-04-25',
            'requested_by' => $this->userA->id,
        ]);

        $foreignProject = $this->createProjectPair($this->tenantB, 'Receipt Update Foreign Relation Project');

        $foreignRequest = MaterialRequest::query()->create([
            'project_id' => $foreignProject->id,
            'request_number' => 'MRQ-UPD-FOREIGN',
            'description' => 'Foreign request',
            'status' => 'approved',
            'estimated_cost' => 700.00,
            'required_date' => '2026-04-26',
            'requested_by' => $this->userB->id,
        ]);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'vendor_id' => $foreignVendor->id,
            'contract_id' => $wrongProjectContract->id,
            'material_request_id' => $wrongProjectRequest->id,
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'material_request_id' => $foreignRequest->id,
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receipt->id,
            'vendor_id' => null,
            'contract_id' => null,
            'material_request_id' => null,
            'receipt_date' => '2026-04-13 00:00:00',
        ]);
    }

    public function test_material_receipt_update_rejects_correction_once_lines_exist(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-UPD-LINES',
            'receipt_date' => '2026-04-13',
        ]);

        $material = Material::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'MAT-UPD-LINES',
            'name' => 'Update Lock Material',
            'category' => 'concrete',
            'unit' => 'm3',
            'description' => 'Update lock material',
            'is_active' => true,
        ]);

        MaterialReceiptLine::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_receipt_id' => $receipt->id,
            'material_id' => $material->id,
            'quantity_received' => 1,
        ]);

        $this->putJson($this->route('update', ['id' => $receipt->id]), [
            'receipt_date' => '2026-04-14',
        ], $this->headersFor($this->userA))
            ->assertStatus(422);

        $this->assertDatabaseHas('material_receipts', [
            'id' => $receipt->id,
            'receipt_date' => '2026-04-13 00:00:00',
        ]);
    }

    public function test_material_receipt_material_request_projection_returns_linked_canonical_request_payload(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Linked Request Projection Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MRQ-PROJECTION-001',
            'description' => 'Receipt owned linked request',
            'status' => 'approved',
            'estimated_cost' => 1800.00,
            'required_date' => '2026-04-27',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-14 08:30:00',
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-LINK-PROJ-001',
            'receipt_date' => '2026-04-14',
        ]);

        $response = $this->getJson(
            $this->route('material-request', ['id' => (string) $receipt->id]),
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MRQ-PROJECTION-001')
            ->assertJsonPath('data.description', 'Receipt owned linked request');

        $projectionItem = $response->json('data');
        $requestViewer = $this->createTenantUser($this->tenantA, [], ['admin'], ['material.read']);
        $canonicalItem = $this->getJson(
            route('api.zena.material-requests.show', ['id' => (string) $materialRequest->id], false),
            $this->headersFor($requestViewer)
        )->assertOk()->json('data');

        $this->assertIsArray($projectionItem);
        $this->assertIsArray($canonicalItem);
        $this->assertSame(array_keys($canonicalItem), array_keys($projectionItem));
        $this->assertSame($canonicalItem['id'], $projectionItem['id']);
        $this->assertSame($canonicalItem['request_number'], $projectionItem['request_number']);
        $this->assertSame($canonicalItem['status'], $projectionItem['status']);
        $this->assertSame($canonicalItem['required_date'], $projectionItem['required_date']);
    }

    public function test_material_receipt_material_request_projection_returns_404_for_cross_tenant_receipt(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Linked Request Hidden Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MRQ-PROJECTION-404',
            'description' => 'Hidden request',
            'status' => 'submitted',
            'estimated_cost' => 900.00,
            'required_date' => '2026-04-28',
            'requested_by' => $this->userA->id,
        ]);

        $linkedReceipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-LINK-PROJ-404',
            'receipt_date' => '2026-04-14',
        ]);

        $this->getJson(
            $this->route('material-request', ['id' => (string) $linkedReceipt->id]),
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_receipt_material_request_projection_returns_404_for_unlinked_receipt(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Receipt Linked Request Unlinked Project');

        $unlinkedReceipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'material_request_id' => null,
            'receipt_number' => 'MR-LINK-PROJ-NULL',
            'receipt_date' => '2026-04-14',
        ]);

        $receiptViewer = $this->createTenantUser(
            $this->tenantA,
            [],
            ['admin'],
            ['project.view', 'vendor.view', 'material-receipt.view', 'material.read']
        );

        $this->getJson(
            $this->route('material-request', ['id' => (string) $unlinkedReceipt->id]),
            $this->headersFor($receiptViewer)
        )->assertStatus(404);
    }

    public function test_material_receipt_show_returns_404_cross_tenant(): void
    {
        $project = Project::factory()->create([
            'tenant_id' => (string) $this->tenantA->id,
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'receipt_number' => 'MR-CROSS-001',
            'receipt_date' => '2026-03-29',
        ]);

        $this->getJson($this->route('show', ['id' => $receipt->id]), $this->headersFor($this->userB))
            ->assertStatus(404);
    }

    public function test_material_receipt_rbac_denies_without_canonical_permissions(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);
        $headers = $this->headersFor($restricted);

        $this->getJson($this->route('index'), $headers)->assertStatus(403);
        $this->postJson($this->route('store'), [
            'project_id' => 'missing',
            'receipt_number' => 'MR-NOPE-001',
            'receipt_date' => '2026-03-29',
        ], $headers)->assertStatus(403);
    }

    private function route(string $name, array $parameters = [], array $query = []): string
    {
        $url = route('api.zena.material-receipts.' . $name, $parameters, false);

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
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
        $token = $user->createToken('material-receipt-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
