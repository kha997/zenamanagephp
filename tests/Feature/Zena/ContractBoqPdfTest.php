<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractBoqPdfTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;
    private Boq $boq;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['contract.view', 'contract.update']
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-BOQ-01',
            'title' => 'HĐ test BOQ PDF',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 500000000,
            'currency' => 'VND',
            'client_name' => 'Khách hàng XYZ',
            'created_by' => (string) $this->user->id,
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-BOQ-01',
            'name' => 'Bảng KL HĐ CTR-BOQ-01',
        ]);

        BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc BT',
            'quantity' => 1000,
            'unit' => 'm3',
            'unit_price' => 150000,
        ]);

        BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L002',
            'name' => 'Thép gia cường',
            'quantity' => 200,
            'unit' => 'kg',
            'unit_price' => 25000,
        ]);

        $this->get('/login');
    }

    private function headers(): array
    {
        return ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    // ─── View render tests (no PDF engine needed) ─────────────────────

    public function test_boq_pdf_view_renders_key_strings(): void
    {
        $html = view('contracts.boq-pdf', [
            'contract' => $this->contract,
            'boq' => $this->boq,
            'lineItems' => $this->boq->lineItems()->get(),
            'total' => 150000000 + 5000000, // 1000*150000 + 200*25000
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords(155000000),
        ])->render();

        $this->assertStringContainsString('PHỤ LỤC HỢP ĐỒNG', $html);
        $this->assertStringContainsString('BẢNG KHỐI LƯỢNG', $html);
        $this->assertStringContainsString('CTR-BOQ-01', $html);
        $this->assertStringContainsString('Móng cọc BT', $html);
        $this->assertStringContainsString('Thép gia cường', $html);
        $this->assertStringContainsString('155.000.000', $html);
        $this->assertStringContainsString('Một trăm năm mươi lăm triệu đồng', $html);
        $this->assertStringContainsString('TỔNG GIÁ TRỊ', $html);
        $this->assertStringContainsString('Bằng chữ', $html);
    }

    // ─── Endpoint tests ───────────────────────────────────────────────

    public function test_boq_pdf_endpoint_returns_pdf(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    return '%PDF-1.4 fake-boq-pdf-bytes';
                }
            };
        });

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.boq.pdf', $this->contract->id),
            $this->headers()
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fake-boq-pdf-bytes', $response->getContent());
    }

    public function test_contract_without_boq_redirects_with_error(): void
    {
        $contractNoBoq = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-NO-BOQ',
            'title' => 'HĐ không có BOQ',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 100000000,
            'currency' => 'VND',
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.boq.pdf', $contractNoBoq->id),
            $this->headers()
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cross_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['contract.view']);

        $response = $this->actingAs($otherUser)->get(
            route('operator.contracts.boq.pdf', $this->contract->id),
            ['X-Tenant-ID' => (string) $otherTenant->id]
        );

        $response->assertNotFound();
    }

    public function test_team_member_without_permission_is_denied(): void
    {
        $member = $this->createTenantUser(
            $this->tenant,
            [],
            ['team_member'],
            []
        );

        $response = $this->actingAs($member)->get(
            route('operator.contracts.boq.pdf', $this->contract->id),
            $this->headers()
        );

        $response->assertStatus(302);
    }

    public function test_engine_unavailable_redirects_with_error(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    throw new DeliverablePdfExportUnavailableException();
                }
            };
        });

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.boq.pdf', $this->contract->id),
            $this->headers()
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── Button render tests ──────────────────────────────────────────

    public function test_contract_show_shows_boq_pdf_button_when_boq_exists(): void
    {
        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.show', $this->contract->id),
            $this->headers()
        );

        $response->assertOk();
        $response->assertSee('Xuất phụ lục');
    }

    public function test_contract_show_hides_boq_pdf_button_when_no_boq(): void
    {
        $contractNoBoq = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-NO-BOQ-2',
            'title' => 'HĐ không có BOQ',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 100000000,
            'currency' => 'VND',
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.show', $contractNoBoq->id),
            $this->headers()
        );

        $response->assertOk();
        $response->assertDontSee('Xuất phụ lục');
    }
}
