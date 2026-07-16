<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\MaterialRequest;
use App\Models\MaterialReceipt;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class MaterialRequestApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    /** @var list<string> */
    private array $expectedFields = [
        'id',
        'project_id',
        'request_number',
        'description',
        'status',
        'estimated_cost',
        'required_date',
        'requested_by',
        'approved_by',
        'approved_at',
        'created_at',
        'updated_at',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenantA = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->userA = $this->createTenantUser($this->tenantA, [], ['admin'], ['material.read', 'material.request', 'material.approve', 'material.receive']);
        $this->userB = $this->createTenantUser($this->tenantB, [], ['admin'], ['material.read', 'material.request', 'material.approve', 'material.receive']);
    }

    public function test_material_request_index_returns_only_approved_schema_backed_fields_on_canonical_zena_path(): void
    {
        $projectA = $this->createProjectPair($this->tenantA, 'Tenant A Project');
        $projectB = $this->createProjectPair($this->tenantB, 'Tenant B Project');

        $older = MaterialRequest::query()->create([
            'project_id' => $projectA->id,
            'request_number' => 'MR-A-001',
            'description' => 'Tenant A older request',
            'status' => 'submitted',
            'estimated_cost' => 1250.50,
            'required_date' => '2026-04-20',
            'requested_by' => $this->userA->id,
        ]);
        DB::table('zena_material_requests')
            ->where('id', (string) $older->id)
            ->update([
                'created_at' => now()->subMinute(),
                'updated_at' => now()->subMinute(),
            ]);

        $newer = MaterialRequest::query()->create([
            'project_id' => $projectA->id,
            'request_number' => 'MR-A-002',
            'description' => 'Tenant A newer request',
            'status' => 'approved',
            'estimated_cost' => 2222.00,
            'required_date' => '2026-04-22',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-12 08:00:00',
        ]);
        DB::table('zena_material_requests')
            ->where('id', (string) $newer->id)
            ->update([
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        MaterialRequest::query()->create([
            'project_id' => $projectB->id,
            'request_number' => 'MR-B-001',
            'description' => 'Tenant B hidden request',
            'status' => 'submitted',
            'estimated_cost' => 999.00,
            'required_date' => '2026-04-25',
            'requested_by' => $this->userB->id,
        ]);

        $response = $this->getJson($this->route('index'), $this->headersFor($this->userA));

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $newer->id)
            ->assertJsonPath('data.1.id', (string) $older->id)
            ->assertJsonPath('data.0.project_id', (string) $projectA->id)
            ->assertJsonPath('data.0.request_number', 'MR-A-002');

        $items = $response->json('data');

        $this->assertIsArray($items);
        $this->assertCount(2, $items);
        $this->assertSame($this->expectedFields, array_keys($items[0]));
        $this->assertSame($this->expectedFields, array_keys($items[1]));
        $this->assertSame(
            [(string) $newer->id, (string) $older->id],
            array_column($items, 'id')
        );
        $this->assertNotContains('Tenant B hidden request', array_column($items, 'description'));
        $this->assertArrayNotHasKey('title', $items[0]);
        $this->assertArrayNotHasKey('material_type', $items[0]);
        $this->assertArrayNotHasKey('quantity', $items[0]);
        $this->assertArrayNotHasKey('unit', $items[0]);
        $this->assertArrayNotHasKey('priority', $items[0]);
        $this->assertArrayNotHasKey('requested_date', $items[0]);
    }

    public function test_material_request_index_filters_are_tenant_safe_and_do_not_enumerate_foreign_projects(): void
    {
        $projectA = $this->createProjectPair($this->tenantA, 'Tenant A Filter Project');
        $projectB = $this->createProjectPair($this->tenantB, 'Tenant B Foreign Project');

        $approved = MaterialRequest::query()->create([
            'project_id' => $projectA->id,
            'request_number' => 'MR-FILTER-001',
            'description' => 'Approved local request',
            'status' => 'approved',
            'estimated_cost' => 500.00,
            'required_date' => '2026-04-18',
            'requested_by' => $this->userA->id,
        ]);

        MaterialRequest::query()->create([
            'project_id' => $projectA->id,
            'request_number' => 'MR-FILTER-002',
            'description' => 'Submitted local request',
            'status' => 'submitted',
            'estimated_cost' => 700.00,
            'required_date' => '2026-04-19',
            'requested_by' => $this->userA->id,
        ]);

        MaterialRequest::query()->create([
            'project_id' => $projectB->id,
            'request_number' => 'MR-FOREIGN-001',
            'description' => 'Foreign tenant request',
            'status' => 'approved',
            'estimated_cost' => 900.00,
            'required_date' => '2026-04-21',
            'requested_by' => $this->userB->id,
        ]);

        $filtered = $this->getJson($this->route('index', [], [
            'project_id' => (string) $projectA->id,
            'status' => 'approved',
        ]), $this->headersFor($this->userA));

        $filtered->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $approved->id);

        $foreignProjectScope = $this->getJson($this->route('index', [], [
            'project_id' => (string) $projectB->id,
        ]), $this->headersFor($this->userA));

        $foreignProjectScope->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_material_request_index_requires_canonical_read_permission(): void
    {
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);

        $this->getJson($this->route('index'), $this->headersFor($restricted))
            ->assertStatus(403);
    }

    public function test_material_request_show_returns_only_approved_schema_backed_fields_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Show Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-SHOW-001',
            'description' => 'Tenant A show request',
            'status' => 'approved',
            'estimated_cost' => 3333.00,
            'required_date' => '2026-04-24',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-12 09:30:00',
        ]);

        $response = $this->getJson(
            $this->route('show', ['id' => (string) $materialRequest->id]),
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-SHOW-001')
            ->assertJsonPath('data.description', 'Tenant A show request');

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);
    }

    public function test_material_request_show_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-HIDDEN-001',
            'description' => 'Hidden request',
            'status' => 'submitted',
            'estimated_cost' => 1200.00,
            'required_date' => '2026-04-26',
            'requested_by' => $this->userA->id,
        ]);

        $this->getJson(
            $this->route('show', ['id' => (string) $materialRequest->id]),
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_show_and_receipts_require_canonical_read_permission(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Read Permission Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-READ-PERM-001',
            'description' => 'Read permission gated request',
            'status' => 'approved',
            'estimated_cost' => 1500.00,
            'required_date' => '2026-04-29',
            'requested_by' => $this->userA->id,
        ]);

        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], []);

        $this->getJson(
            $this->route('show', ['id' => (string) $materialRequest->id]),
            $this->headersFor($restricted)
        )->assertStatus(403);

        $this->getJson(
            $this->route('receipts', ['id' => (string) $materialRequest->id]),
            $this->headersFor($restricted)
        )->assertStatus(403);
    }

    public function test_material_request_receipts_returns_only_linked_receipt_headers_with_canonical_payload_shape(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Request Receipts Project');
        $otherProject = $this->createProjectPair($this->tenantA, 'Tenant A Other Request Receipts Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REQ-REC-001',
            'description' => 'Request with linked receipts',
            'status' => 'approved',
            'estimated_cost' => 1000.00,
            'required_date' => '2026-04-25',
            'requested_by' => $this->userA->id,
        ]);

        $otherRequest = MaterialRequest::query()->create([
            'project_id' => $otherProject->id,
            'request_number' => 'MR-REQ-REC-002',
            'description' => 'Other request',
            'status' => 'submitted',
            'estimated_cost' => 2000.00,
            'required_date' => '2026-04-26',
            'requested_by' => $this->userA->id,
        ]);

        $vendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'code' => 'REQ-REC-VENDOR',
            'name' => 'Request Receipt Vendor',
        ]);

        $linkedOlder = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-REQ-REC-OLD',
            'receipt_date' => '2026-04-12',
        ]);

        $linkedNewer = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'material_request_id' => $materialRequest->id,
            'receipt_number' => 'MR-REQ-REC-NEW',
            'receipt_date' => '2026-04-13',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $otherProject->id,
            'vendor_id' => $vendor->id,
            'material_request_id' => $otherRequest->id,
            'receipt_number' => 'MR-REQ-REC-OTHER',
            'receipt_date' => '2026-04-14',
        ]);

        MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenantA->id,
            'project_id' => $project->id,
            'vendor_id' => $vendor->id,
            'material_request_id' => null,
            'receipt_number' => 'MR-REQ-REC-NULL',
            'receipt_date' => '2026-04-15',
        ]);

        $response = $this->getJson(
            $this->route('receipts', ['id' => (string) $materialRequest->id]),
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', (string) $linkedNewer->id)
            ->assertJsonPath('data.1.id', (string) $linkedOlder->id)
            ->assertJsonPath('data.0.material_request_id', (string) $materialRequest->id)
            ->assertJsonPath('data.1.material_request_id', (string) $materialRequest->id);

        $projectionItem = $response->json('data.0');
        $receiptViewer = $this->createTenantUser($this->tenantA, [], ['admin'], ['material.read', 'material-receipt.view']);
        $canonicalItem = $this->getJson(
            route('api.zena.material-receipts.show', ['id' => (string) $linkedNewer->id], false),
            $this->headersFor($receiptViewer)
        )->assertOk()->json('data');

        $this->assertIsArray($projectionItem);
        $this->assertIsArray($canonicalItem);
        $this->assertSame(array_keys($canonicalItem), array_keys($projectionItem));
        $this->assertSame($canonicalItem['id'], $projectionItem['id']);
        $this->assertSame($canonicalItem['material_request_id'], $projectionItem['material_request_id']);
        $this->assertSame($canonicalItem['receipt_number'], $projectionItem['receipt_number']);
        $this->assertSame($canonicalItem['project']['id'], $projectionItem['project']['id']);
        $this->assertSame($canonicalItem['vendor']['id'], $projectionItem['vendor']['id']);
    }

    public function test_material_request_receipts_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Request Receipts Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REQ-REC-HIDDEN-001',
            'description' => 'Hidden request receipts',
            'status' => 'approved',
            'estimated_cost' => 3000.00,
            'required_date' => '2026-04-27',
            'requested_by' => $this->userA->id,
        ]);

        $this->getJson(
            $this->route('receipts', ['id' => (string) $materialRequest->id]),
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_store_creates_draft_record_with_server_managed_fields_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Create Project');

        $response = $this->postJson(
            $this->route('store'),
            [
                'project_id' => (string) $project->id,
                'description' => 'Tenant A create request',
                'estimated_cost' => 4567.89,
                'required_date' => '2026-04-28',
                'request_number' => 'MR-SPOOF-001',
                'status' => 'approved',
                'requested_by' => (string) $this->userB->id,
                'approved_by' => (string) $this->userB->id,
                'approved_at' => '2026-04-12 10:00:00',
            ],
            $this->headersFor($this->userA)
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.description', 'Tenant A create request')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.approved_at', null);

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);
        $this->assertIsString($item['request_number']);
        $this->assertStringStartsWith('MR-', $item['request_number']);
        $this->assertNotSame('MR-SPOOF-001', $item['request_number']);

        $materialRequest = MaterialRequest::query()->findOrFail($item['id']);

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('Tenant A create request', $materialRequest->description);
        $this->assertSame('draft', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
        $this->assertSame('4567.89', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-04-28', optional($materialRequest->required_date)->toDateString());
    }

    public function test_material_request_store_rejects_foreign_tenant_project_lookup(): void
    {
        $foreignProject = $this->createProjectPair($this->tenantB, 'Tenant B Foreign Create Project');

        $this->postJson(
            $this->route('store'),
            [
                'project_id' => (string) $foreignProject->id,
                'description' => 'Foreign create attempt',
            ],
            $this->headersFor($this->userA)
        )->assertStatus(422);

        $this->assertDatabaseCount('zena_material_requests', 0);
    }

    public function test_material_request_store_requires_canonical_request_permission(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Restricted Create Project');
        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material.read']);

        $this->postJson(
            $this->route('store'),
            [
                'project_id' => (string) $project->id,
                'description' => 'Blocked create attempt',
            ],
            $this->headersFor($restricted)
        )->assertStatus(403);
    }

    public function test_material_request_store_accepts_project_created_via_canonical_zena_projects_owner(): void
    {
        $operator = $this->createTenantUser($this->tenantA, [], ['admin'], [
            'project.create',
            'material.read',
            'material.request',
            'material.approve',
            'material.receive',
        ]);

        $projectId = $this->createCanonicalProjectViaApi($operator, 'Canonical Owner Project');

        $response = $this->postJson(
            $this->route('store'),
            [
                'project_id' => $projectId,
                'description' => 'Created against canonical owner project',
                'estimated_cost' => 123.45,
                'required_date' => '2026-05-01',
            ],
            $this->headersFor($operator)
        );

        $response->assertStatus(201)
            ->assertJsonPath('data.project_id', $projectId)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requested_by', (string) $operator->id);

        $this->assertDatabaseHas('zena_material_requests', [
            'project_id' => $projectId,
            'description' => 'Created against canonical owner project',
            'requested_by' => (string) $operator->id,
        ]);
    }

    public function test_material_request_store_rejects_foreign_project_created_via_canonical_zena_projects_owner(): void
    {
        $foreignCreator = $this->createTenantUser($this->tenantB, [], ['admin'], [
            'project.create',
            'material.read',
            'material.request',
            'material.approve',
            'material.receive',
        ]);

        $foreignProjectId = $this->createCanonicalProjectViaApi($foreignCreator, 'Foreign Canonical Owner Project');

        $this->postJson(
            $this->route('store'),
            [
                'project_id' => $foreignProjectId,
                'description' => 'Should not attach to foreign canonical project',
                'estimated_cost' => 777,
                'required_date' => '2026-05-02',
            ],
            $this->headersFor($this->userA)
        )->assertStatus(403);

        $this->assertDatabaseMissing('zena_material_requests', [
            'project_id' => $foreignProjectId,
            'description' => 'Should not attach to foreign canonical project',
        ]);
    }

    public function test_material_request_submit_and_approve_continue_to_work_for_project_created_via_canonical_zena_projects_owner(): void
    {
        $operator = $this->createTenantUser($this->tenantA, [], ['admin'], [
            'project.create',
            'material.read',
            'material.request',
            'material.approve',
            'material.receive',
        ]);

        $projectId = $this->createCanonicalProjectViaApi($operator, 'Canonical Submit Approve Project');

        $create = $this->postJson(
            $this->route('store'),
            [
                'project_id' => $projectId,
                'description' => 'Submit approve canonical owner flow',
                'estimated_cost' => 888.88,
                'required_date' => '2026-05-03',
            ],
            $this->headersFor($operator)
        );

        $create->assertStatus(201);

        $requestId = (string) $create->json('data.id');

        $this->postJson($this->route('submit', ['id' => $requestId]), [], $this->headersFor($operator))
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId)
            ->assertJsonPath('data.status', 'submitted');

        $this->postJson($this->route('approve', ['id' => $requestId]), [], $this->headersFor($operator))
            ->assertOk()
            ->assertJsonPath('data.project_id', $projectId)
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approved_by', (string) $operator->id);

        $materialRequest = MaterialRequest::query()->findOrFail($requestId);

        $this->assertSame($projectId, (string) $materialRequest->project_id);
        $this->assertSame('approved', (string) $materialRequest->status);
        $this->assertSame((string) $operator->id, (string) $materialRequest->approved_by);
    }

    public function test_material_request_update_updates_only_draft_writable_fields_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Update Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-UPD-001',
            'description' => 'Draft before update',
            'status' => 'draft',
            'estimated_cost' => 100.00,
            'required_date' => '2026-04-28',
            'requested_by' => $this->userA->id,
        ]);

        $response = $this->putJson(
            $this->route('update', ['id' => (string) $materialRequest->id]),
            [
                'description' => 'Draft after update',
                'estimated_cost' => 999.50,
                'required_date' => '2026-05-02',
                'project_id' => (string) $this->createProjectPair($this->tenantA, 'Ignored Project')->id,
                'request_number' => 'MR-SPOOF-UPD',
                'status' => 'approved',
                'requested_by' => (string) $this->userB->id,
                'approved_by' => (string) $this->userB->id,
                'approved_at' => '2026-04-12 11:00:00',
            ],
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-UPD-001')
            ->assertJsonPath('data.description', 'Draft after update')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.approved_at', null);

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);

        $materialRequest->refresh();

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('MR-UPD-001', $materialRequest->request_number);
        $this->assertSame('Draft after update', $materialRequest->description);
        $this->assertSame('draft', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
        $this->assertSame('999.50', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-05-02', optional($materialRequest->required_date)->toDateString());
    }

    public function test_material_request_update_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Update Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-UPD-HIDDEN-001',
            'description' => 'Hidden draft',
            'status' => 'draft',
            'estimated_cost' => 200.00,
            'required_date' => '2026-04-30',
            'requested_by' => $this->userA->id,
        ]);

        $this->putJson(
            $this->route('update', ['id' => (string) $materialRequest->id]),
            [
                'description' => 'Foreign tenant update attempt',
            ],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_update_and_submit_require_canonical_request_permission(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Request Permission Project');

        $draftForUpdate = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REQ-PERM-UPD-001',
            'description' => 'Draft update gate',
            'status' => 'draft',
            'estimated_cost' => 210.00,
            'required_date' => '2026-05-01',
            'requested_by' => $this->userA->id,
        ]);

        $draftForSubmit = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REQ-PERM-SUB-001',
            'description' => 'Draft submit gate',
            'status' => 'draft',
            'estimated_cost' => 220.00,
            'required_date' => '2026-05-02',
            'requested_by' => $this->userA->id,
        ]);

        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material.read']);

        $this->putJson(
            $this->route('update', ['id' => (string) $draftForUpdate->id]),
            ['description' => 'Blocked'],
            $this->headersFor($restricted)
        )->assertStatus(403);

        $this->postJson(
            $this->route('submit', ['id' => (string) $draftForSubmit->id]),
            [],
            $this->headersFor($restricted)
        )->assertStatus(403);
    }

    public function test_material_request_update_returns_422_for_non_draft_record(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Non Draft Update Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-UPD-NONDRAFT-001',
            'description' => 'Submitted request',
            'status' => 'submitted',
            'estimated_cost' => 300.00,
            'required_date' => '2026-05-01',
            'requested_by' => $this->userA->id,
        ]);

        $this->putJson(
            $this->route('update', ['id' => (string) $materialRequest->id]),
            [
                'description' => 'Should not update',
                'estimated_cost' => 444.00,
            ],
            $this->headersFor($this->userA)
        )->assertStatus(422)
            ->assertJsonPath('error.details.data.status.0', 'Only draft material requests can be updated.');

        $materialRequest->refresh();

        $this->assertSame('Submitted request', $materialRequest->description);
        $this->assertSame('300.00', (string) $materialRequest->estimated_cost);
        $this->assertSame('submitted', $materialRequest->status);
    }

    public function test_material_request_submit_transitions_draft_to_submitted_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Submit Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-SUBMIT-001',
            'description' => 'Draft to submit',
            'status' => 'draft',
            'estimated_cost' => 1234.56,
            'required_date' => '2026-05-03',
            'requested_by' => $this->userA->id,
        ]);

        $response = $this->postJson(
            $this->route('submit', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-SUBMIT-001')
            ->assertJsonPath('data.description', 'Draft to submit')
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.approved_at', null);

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);

        $materialRequest->refresh();

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('MR-SUBMIT-001', $materialRequest->request_number);
        $this->assertSame('Draft to submit', $materialRequest->description);
        $this->assertSame('submitted', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
        $this->assertSame('1234.56', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-05-03', optional($materialRequest->required_date)->toDateString());
    }

    public function test_material_request_submit_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Submit Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-SUBMIT-HIDDEN-001',
            'description' => 'Hidden draft submit',
            'status' => 'draft',
            'estimated_cost' => 4321.00,
            'required_date' => '2026-05-04',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('submit', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_submit_returns_422_for_non_draft_record(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Non Draft Submit Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-SUBMIT-NONDRAFT-001',
            'description' => 'Already submitted request',
            'status' => 'submitted',
            'estimated_cost' => 555.00,
            'required_date' => '2026-05-05',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('submit', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        )->assertStatus(422)
            ->assertJsonPath('error.details.data.status.0', 'Only draft material requests can be submitted.');

        $materialRequest->refresh();

        $this->assertSame('submitted', $materialRequest->status);
        $this->assertSame('MR-SUBMIT-NONDRAFT-001', $materialRequest->request_number);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
    }

    public function test_material_request_approve_transitions_submitted_to_approved_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Approve Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-APPROVE-001',
            'description' => 'Submitted to approve',
            'status' => 'submitted',
            'estimated_cost' => 6789.10,
            'required_date' => '2026-05-06',
            'requested_by' => $this->userA->id,
        ]);

        $response = $this->postJson(
            $this->route('approve', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-APPROVE-001')
            ->assertJsonPath('data.description', 'Submitted to approve')
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', (string) $this->userA->id);

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertNotNull($item['approved_at']);
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);

        $materialRequest->refresh();

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('MR-APPROVE-001', $materialRequest->request_number);
        $this->assertSame('Submitted to approve', $materialRequest->description);
        $this->assertSame('approved', $materialRequest->status);
        $this->assertSame('6789.10', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-05-06', optional($materialRequest->required_date)->toDateString());
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->approved_by);
        $this->assertNotNull($materialRequest->approved_at);
    }

    public function test_material_request_approve_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Approve Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-APPROVE-HIDDEN-001',
            'description' => 'Hidden submitted approve',
            'status' => 'submitted',
            'estimated_cost' => 7654.00,
            'required_date' => '2026-05-07',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('approve', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_approve_and_reject_require_canonical_approval_permission(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Approval Permission Project');

        $submittedForApprove = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-APP-PERM-001',
            'description' => 'Submitted approve gate',
            'status' => 'submitted',
            'estimated_cost' => 310.00,
            'required_date' => '2026-05-03',
            'requested_by' => $this->userA->id,
        ]);

        $submittedForReject = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REJ-PERM-001',
            'description' => 'Submitted reject gate',
            'status' => 'submitted',
            'estimated_cost' => 320.00,
            'required_date' => '2026-05-04',
            'requested_by' => $this->userA->id,
        ]);

        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material.read', 'material.request']);

        $this->postJson(
            $this->route('approve', ['id' => (string) $submittedForApprove->id]),
            [],
            $this->headersFor($restricted)
        )->assertStatus(403);

        $this->postJson(
            $this->route('reject', ['id' => (string) $submittedForReject->id]),
            [],
            $this->headersFor($restricted)
        )->assertStatus(403);
    }

    public function test_material_request_approve_returns_422_for_non_submitted_record(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Non Submitted Approve Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-APPROVE-NONSUB-001',
            'description' => 'Draft request',
            'status' => 'draft',
            'estimated_cost' => 888.00,
            'required_date' => '2026-05-08',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('approve', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        )->assertStatus(422)
            ->assertJsonPath('error.details.data.status.0', 'Only submitted material requests can be approved.');

        $materialRequest->refresh();

        $this->assertSame('draft', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
    }

    public function test_material_request_reject_transitions_submitted_to_rejected_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Reject Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REJECT-001',
            'description' => 'Submitted to reject',
            'status' => 'submitted',
            'estimated_cost' => 456.78,
            'required_date' => '2026-05-09',
            'requested_by' => $this->userA->id,
        ]);

        $response = $this->postJson(
            $this->route('reject', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-REJECT-001')
            ->assertJsonPath('data.description', 'Submitted to reject')
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', null)
            ->assertJsonPath('data.approved_at', null);

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);

        $materialRequest->refresh();

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('MR-REJECT-001', $materialRequest->request_number);
        $this->assertSame('Submitted to reject', $materialRequest->description);
        $this->assertSame('rejected', $materialRequest->status);
        $this->assertSame('456.78', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-05-09', optional($materialRequest->required_date)->toDateString());
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
    }

    public function test_material_request_reject_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Reject Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REJECT-HIDDEN-001',
            'description' => 'Hidden submitted reject',
            'status' => 'submitted',
            'estimated_cost' => 999.00,
            'required_date' => '2026-05-10',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('reject', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_reject_returns_422_for_non_submitted_record(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Non Submitted Reject Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-REJECT-NONSUB-001',
            'description' => 'Draft request reject attempt',
            'status' => 'draft',
            'estimated_cost' => 321.00,
            'required_date' => '2026-05-11',
            'requested_by' => $this->userA->id,
        ]);

        $this->postJson(
            $this->route('reject', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        )->assertStatus(422)
            ->assertJsonPath('error.details.data.status.0', 'Only submitted material requests can be rejected.');

        $materialRequest->refresh();

        $this->assertSame('draft', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
    }

    public function test_material_request_fulfill_transitions_approved_to_fulfilled_on_canonical_zena_path(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Fulfill Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-FULFILL-001',
            'description' => 'Approved to fulfill',
            'status' => 'approved',
            'estimated_cost' => 9876.54,
            'required_date' => '2026-05-12',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-13 09:00:00',
        ]);

        $response = $this->postJson(
            $this->route('fulfill', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        );

        $response->assertOk()
            ->assertJsonPath('data.id', (string) $materialRequest->id)
            ->assertJsonPath('data.project_id', (string) $project->id)
            ->assertJsonPath('data.request_number', 'MR-FULFILL-001')
            ->assertJsonPath('data.description', 'Approved to fulfill')
            ->assertJsonPath('data.status', 'fulfilled')
            ->assertJsonPath('data.requested_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_by', (string) $this->userA->id)
            ->assertJsonPath('data.approved_at', '2026-04-13T09:00:00.000000Z');

        $item = $response->json('data');

        $this->assertIsArray($item);
        $this->assertSame($this->expectedFields, array_keys($item));
        $this->assertArrayNotHasKey('title', $item);
        $this->assertArrayNotHasKey('material_type', $item);
        $this->assertArrayNotHasKey('quantity', $item);
        $this->assertArrayNotHasKey('unit', $item);
        $this->assertArrayNotHasKey('priority', $item);
        $this->assertArrayNotHasKey('requested_date', $item);

        $materialRequest->refresh();

        $this->assertSame((string) $project->id, (string) $materialRequest->project_id);
        $this->assertSame('MR-FULFILL-001', $materialRequest->request_number);
        $this->assertSame('Approved to fulfill', $materialRequest->description);
        $this->assertSame('fulfilled', $materialRequest->status);
        $this->assertSame('9876.54', (string) $materialRequest->estimated_cost);
        $this->assertSame('2026-05-12', optional($materialRequest->required_date)->toDateString());
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->approved_by);
        $this->assertSame('2026-04-13 09:00:00', optional($materialRequest->approved_at)->format('Y-m-d H:i:s'));
    }

    public function test_material_request_fulfill_returns_404_cross_tenant(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Hidden Fulfill Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-FULFILL-HIDDEN-001',
            'description' => 'Hidden approved fulfill',
            'status' => 'approved',
            'estimated_cost' => 1111.00,
            'required_date' => '2026-05-13',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-13 10:00:00',
        ]);

        $this->postJson(
            $this->route('fulfill', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userB)
        )->assertStatus(404);
    }

    public function test_material_request_fulfill_requires_canonical_receive_permission(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Receive Permission Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-FULFILL-PERM-001',
            'description' => 'Approved fulfill gate',
            'status' => 'approved',
            'estimated_cost' => 410.00,
            'required_date' => '2026-05-05',
            'requested_by' => $this->userA->id,
            'approved_by' => $this->userA->id,
            'approved_at' => '2026-04-13 12:00:00',
        ]);

        $restricted = $this->createTenantUser($this->tenantA, [], ['member'], ['material.read', 'material.request', 'material.approve']);

        $this->postJson(
            $this->route('fulfill', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($restricted)
        )->assertStatus(403);
    }

    public function test_material_request_fulfill_returns_422_for_non_approved_record(): void
    {
        $project = $this->createProjectPair($this->tenantA, 'Tenant A Non Approved Fulfill Project');

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => $project->id,
            'request_number' => 'MR-FULFILL-NONAPPROVED-001',
            'description' => 'Submitted request fulfill attempt',
            'status' => 'submitted',
            'estimated_cost' => 654.00,
            'required_date' => '2026-05-14',
            'requested_by' => $this->userA->id,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->postJson(
            $this->route('fulfill', ['id' => (string) $materialRequest->id]),
            [],
            $this->headersFor($this->userA)
        )->assertStatus(422)
            ->assertJsonPath('error.details.data.status.0', 'Only approved material requests can be fulfilled.');

        $materialRequest->refresh();

        $this->assertSame('submitted', $materialRequest->status);
        $this->assertSame((string) $this->userA->id, (string) $materialRequest->requested_by);
        $this->assertNull($materialRequest->approved_by);
        $this->assertNull($materialRequest->approved_at);
    }

    private function createProjectPair(Tenant $tenant, string $name): Project
    {
        return Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => $name,
        ]);
    }

    private function createCanonicalProjectViaApi(User $user, string $name): string
    {
        $response = $this->postJson(route('api.zena.projects.store', [], false), [
            'name' => $name,
            'description' => 'Canonical procurement pilot project anchor',
            'start_date' => '2026-04-18',
            'end_date' => '2026-05-18',
            'status' => 'planning',
        ], $this->headersFor($user));

        $response->assertStatus(201);

        return (string) $response->json('data.id');
    }

    private function route(string $name, array $parameters = [], array $query = []): string
    {
        $url = route('api.zena.material-requests.' . $name, $parameters, false);

        if ($query === []) {
            return $url;
        }

        return $url . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array<string, string>
     */
    private function headersFor(User $user): array
    {
        $token = $user->createToken('material-request-api-test')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
