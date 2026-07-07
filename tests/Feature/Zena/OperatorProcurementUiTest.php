<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\Material;
use App\Models\MaterialRequest;
use App\Models\MaterialReceipt;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorProcurementUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;
    private Vendor $vendor;
    private Material $material;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            [
                'material.read',
                'material.request',
                'material.approve',
                'material.view',
                'material-receipt.view',
                'material-receipt.create',
                'material-receipt-line.view',
                'material-receipt-line.create',
                'contract.view',
            ]
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Operator UI Project',
            'code' => 'PRJ-OP-001',
        ]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-OP-001',
            'title' => 'Operator UI Contract',
        ]);

        $this->vendor = Vendor::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'VENDOR-OP-001',
            'name' => 'Operator Vendor',
        ]);

        $this->material = Material::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'code' => 'MAT-OP-001',
            'name' => 'Operator Material',
            'unit' => 'kg',
        ]);
    }

    public function test_operator_procurement_ui_flow_loads_pages_and_executes_locked_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.dashboard'), $headers)
            ->assertOk()
            ->assertSee('Bảng điều hành');

        $this->actingAs($this->user)
            ->get(route('operator.material-requests.index'), $headers)
            ->assertOk()
            ->assertSee('Yêu cầu vật tư')
            ->assertSee('Tạo yêu cầu');

        $this->actingAs($this->user)
            ->get(route('operator.material-requests.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin yêu cầu');

        $createRequest = $this->actingAs($this->user)
            ->post(route('operator.material-requests.store'), [
                'project_id' => (string) $this->project->id,
                'description' => 'Operator request',
                'estimated_cost' => 1000,
                'required_date' => '2026-04-22',
            ], $headers);

        $createRequest->assertRedirect(route('operator.material-requests.index'));
        $createRequest->assertSessionHas('success', 'Tạo yêu cầu thành công');

        $materialRequest = MaterialRequest::query()->firstOrFail();
        $this->assertSame('draft', (string) $materialRequest->status);

        $submit = $this->actingAs($this->user)
            ->post(route('operator.material-requests.submit', $materialRequest->id), [], $headers);

        $submit->assertRedirect(route('operator.material-requests.index'));
        $submit->assertSessionHas('success', 'Đã gửi duyệt');

        $materialRequest->refresh();
        $this->assertSame('submitted', (string) $materialRequest->status);

        $approve = $this->actingAs($this->user)
            ->post(route('operator.material-requests.approve', $materialRequest->id), [], $headers);

        $approve->assertRedirect(route('operator.material-requests.index'));
        $approve->assertSessionHas('success', 'Đã phê duyệt');

        $materialRequest->refresh();
        $this->assertSame('approved', (string) $materialRequest->status);

        $this->actingAs($this->user)
            ->get(route('operator.receipts.index'), $headers)
            ->assertOk()
            ->assertSee('Phiếu nhập')
            ->assertSee('Tạo phiếu nhập');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.create'), $headers)
            ->assertOk()
            ->assertSee('Thông tin phiếu nhập');

        $createReceipt = $this->actingAs($this->user)
            ->post(route('operator.receipts.store'), [
                'project_id' => (string) $this->project->id,
                'vendor_id' => (string) $this->vendor->id,
                'contract_id' => (string) $this->contract->id,
                'material_request_id' => (string) $materialRequest->id,
                'receipt_number' => 'mr-op-001',
                'receipt_date' => '2026-04-20',
            ], $headers);

        $receipt = MaterialReceipt::query()->firstOrFail();

        $createReceipt->assertRedirect(route('operator.receipts.show', $receipt->id));
        $createReceipt->assertSessionHas('success', 'Tạo phiếu nhập thành công');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.show', $receipt->id), $headers)
            ->assertOk()
            ->assertSee('Thông tin phiếu nhập')
            ->assertSee('Tổng hợp chi phí hợp đồng');

        $addLine = $this->actingAs($this->user)
            ->post(route('operator.receipts.lines.store', $receipt->id), [
                'material_id' => (string) $this->material->id,
                'quantity_received' => 4,
                'unit_cost' => 25,
            ], $headers);

        $addLine->assertRedirect(route('operator.receipts.show', $receipt->id));
        $addLine->assertSessionHas('success', 'Đã thêm dòng vật tư');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.show', $receipt->id), $headers)
            ->assertOk()
            ->assertSee('Operator Material')
            ->assertSee('100.00')
            ->assertSee('Tổng chi phí')
            ->assertSee('Số phiếu nhập đã gắn');
    }

    public function test_operator_pages_render_empty_and_optional_states_safely(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.material-requests.index'), $headers)
            ->assertOk()
            ->assertSee('Chưa có yêu cầu vật tư')
            ->assertSee('Tạo yêu cầu');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.index'), $headers)
            ->assertOk()
            ->assertSee('Chưa có phiếu nhập')
            ->assertSee('Tạo phiếu nhập');

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'vendor_id' => null,
            'contract_id' => null,
            'material_request_id' => null,
            'receipt_number' => 'MR-EMPTY-001',
            'receipt_date' => '2026-04-20',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.receipts.show', $receipt->id), $headers)
            ->assertOk()
            ->assertSee('Thông tin phiếu nhập')
            ->assertSee('Chưa có dòng vật tư')
            ->assertSee('Chưa có dữ liệu tổng hợp chi phí')
            ->assertSee('Phiếu nhập này chưa gắn hợp đồng')
            ->assertSee('—');
    }

    public function test_operator_routes_require_authentication(): void
    {
        $this->get(route('operator.dashboard'))
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_operator_pages_work_without_explicit_tenant_header_in_browser_flow(): void
    {
        $this->actingAs($this->user)
            ->get(route('operator.dashboard'))
            ->assertOk()
            ->assertSee('Bảng điều hành');

        $createRequest = $this->actingAs($this->user)
            ->post(route('operator.material-requests.store'), [
                'project_id' => (string) $this->project->id,
                'description' => 'Browser session request',
                'estimated_cost' => 500,
                'required_date' => '2026-04-23',
            ]);

        $createRequest->assertRedirect(route('operator.material-requests.index'));
        $createRequest->assertSessionHas('success', 'Tạo yêu cầu thành công');
    }

    public function test_operator_actions_fail_safely_for_authenticated_users_without_required_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $restrictedUser = $this->createTenantUser(
            $this->tenant,
            [],
            ['viewer'],
            ['material.read']
        );

        $materialRequest = MaterialRequest::query()->create([
            'project_id' => (string) $this->project->id,
            'request_number' => 'MR-LOCK-001',
            'description' => 'Permission restricted request',
            'status' => 'draft',
            'estimated_cost' => 100,
            'required_date' => '2026-04-22',
            'requested_by' => (string) $this->user->id,
        ]);

        $this->actingAs($restrictedUser)
            ->get(route('operator.material-requests.index'), $headers)
            ->assertOk();

        $submit = $this->actingAs($restrictedUser)
            ->from(route('operator.material-requests.index'))
            ->post(route('operator.material-requests.submit', $materialRequest->id), [], $headers);

        $submit->assertRedirect(route('operator.material-requests.index'));
        $submit->assertSessionHas('error', 'Bạn không có quyền thực hiện thao tác này.');

        $materialRequest->refresh();
        $this->assertSame('draft', (string) $materialRequest->status);
    }

    public function test_operator_views_only_render_current_tenant_reference_data(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $foreignTenant = Tenant::factory()->create();

        Project::factory()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'name' => 'Foreign Project',
            'code' => 'PRJ-FOR-001',
        ]);

        Vendor::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'code' => 'VENDOR-FOR-001',
            'name' => 'Foreign Vendor',
        ]);

        Material::query()->create([
            'tenant_id' => (string) $foreignTenant->id,
            'code' => 'MAT-FOR-001',
            'name' => 'Foreign Material',
            'unit' => 'kg',
        ]);

        $receipt = MaterialReceipt::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'receipt_number' => 'MR-SCOPE-001',
            'receipt_date' => '2026-04-20',
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.material-requests.create'), $headers)
            ->assertOk()
            ->assertSee('Operator UI Project')
            ->assertDontSee('Foreign Project');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.create'), $headers)
            ->assertOk()
            ->assertSee('Operator UI Project')
            ->assertSee('Operator Vendor')
            ->assertDontSee('Foreign Project')
            ->assertDontSee('Foreign Vendor');

        $this->actingAs($this->user)
            ->get(route('operator.receipts.show', $receipt->id), $headers)
            ->assertOk()
            ->assertSee('Operator Material')
            ->assertDontSee('Foreign Material');
    }
}
