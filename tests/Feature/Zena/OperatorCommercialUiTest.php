<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Boq;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class OperatorCommercialUiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['boq.view', 'boq.create', 'boq.update', 'vendor.view', 'vendor.create', 'contract.view', 'contract.create']
        );

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'name' => 'Commercial UI Project',
            'code' => 'PRJ-COM-001',
        ]);
    }

    public function test_vendor_create_and_index_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.vendors.index'), $headers)
            ->assertOk()
            ->assertSee('Nhà cung cấp');

        $create = $this->actingAs($this->user)
            ->post(route('operator.vendors.store'), [
                'code' => 'VND-UI-001',
                'name' => 'Song Da Cement JSC',
                'email' => 'sales@songda.example',
            ], $headers);

        $create->assertRedirect(route('operator.vendors.index'));
        $create->assertSessionHas('success', 'Tạo nhà cung cấp thành công');

        $vendor = Vendor::query()->firstOrFail();
        $this->assertSame('VND-UI-001', (string) $vendor->code);

        $this->actingAs($this->user)
            ->get(route('operator.vendors.show', $vendor->id), $headers)
            ->assertOk()
            ->assertSee('Song Da Cement JSC');
    }

    public function test_boq_create_with_line_items_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.boqs.index'), $headers)
            ->assertOk()
            ->assertSee('BOQ');

        $create = $this->actingAs($this->user)
            ->post(route('operator.boqs.store'), [
                'project_id' => (string) $this->project->id,
                'code' => 'BOQ-UI-001',
                'name' => 'Structural works BOQ',
            ], $headers);

        $boq = Boq::query()->firstOrFail();
        $create->assertRedirect(route('operator.boqs.show', $boq->id));
        $create->assertSessionHas('success', 'Tạo BOQ thành công');

        $addLine = $this->actingAs($this->user)
            ->post(route('operator.boqs.lines.store', $boq->id), [
                'name' => 'Concrete grade 40',
                'quantity' => 120.5,
                'unit' => 'm3',
            ], $headers);

        $addLine->assertRedirect(route('operator.boqs.show', $boq->id));
        $addLine->assertSessionHas('success', 'Đã thêm hạng mục');

        $this->assertSame(1, $boq->lineItems()->count());

        $this->actingAs($this->user)
            ->get(route('operator.boqs.show', $boq->id), $headers)
            ->assertOk()
            ->assertSee('Concrete grade 40');
    }

    public function test_contract_create_and_show_flow(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $this->actingAs($this->user)
            ->get(route('operator.contracts.index'), $headers)
            ->assertOk()
            ->assertSee('Hợp đồng');

        $create = $this->actingAs($this->user)
            ->post(route('operator.contracts.store'), [
                'project_id' => (string) $this->project->id,
                'code' => 'CTR-UI-001',
                'title' => 'Main structural contract',
                'total_value' => 5000000,
                'currency' => 'VND',
            ], $headers);

        $contract = Contract::query()->firstOrFail();
        $create->assertRedirect(route('operator.contracts.show', $contract->id));
        $create->assertSessionHas('success', 'Tạo hợp đồng thành công');

        $this->actingAs($this->user)
            ->get(route('operator.contracts.show', $contract->id), $headers)
            ->assertOk()
            ->assertSee('CTR-UI-001')
            ->assertSee('Main structural contract');
    }

    public function test_commercial_pages_require_authentication(): void
    {
        $this->get(route('operator.vendors.index'))->assertRedirect();
        $this->get(route('operator.boqs.index'))->assertRedirect();
        $this->get(route('operator.contracts.index'))->assertRedirect();
    }

    public function test_commercial_creation_denied_without_permission(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $viewer = $this->createTenantUser($this->tenant, [], ['commercial_viewer'], ['boq.view', 'vendor.view', 'contract.view']);

        $this->actingAs($viewer)
            ->get(route('operator.vendors.index'), $headers)
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('operator.vendors.store'), ['code' => 'X', 'name' => 'X'], $headers)
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('operator.boqs.store'), ['project_id' => (string) $this->project->id, 'code' => 'X', 'name' => 'X'], $headers)
            ->assertForbidden();

        $this->assertSame(0, Vendor::query()->count());
        $this->assertSame(0, Boq::query()->count());
    }
}
