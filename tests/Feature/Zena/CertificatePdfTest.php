<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class CertificatePdfTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;
    private Boq $boq;
    private BoqLineItem $line1;

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
                'contract.view', 'contract.update',
                'payment_certificate.view', 'payment_certificate.create', 'payment_certificate.approve',
            ]
        );
        $this->project = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->contract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-PDF-01',
            'title' => 'HĐ test PDF chứng chỉ',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 1000000000,
            'currency' => 'VND',
            'client_name' => 'Khách hàng ABC',
            'retention_percent' => 5,
            'advance_amount' => 200000000,
            'advance_recovery_percent' => 20,
            'created_by' => (string) $this->user->id,
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-PDF-01',
            'name' => 'Bảng KL HĐ CTR-PDF-01',
        ]);

        $this->line1 = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc BT',
            'quantity' => 10000,
            'unit' => 'm',
            'unit_price' => 100000,
        ]);

        $this->get('/login');
    }

    private function headers(): array
    {
        return ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    private function createAndApproveCert(int $periodNo, float $qty, string $periodFrom, string $periodTo): PaymentCertificate
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => $periodFrom,
            'period_to' => $periodTo,
        ], $h)->assertRedirect();

        $cert = PaymentCertificate::query()
            ->where('contract_id', $this->contract->id)
            ->where('period_no', $periodNo)
            ->first();
        $this->assertNotNull($cert);

        $this->actingAs($this->user)->post(
            route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]),
            ['lines' => [(string) $this->line1->id => $qty]],
            $h
        )->assertRedirect();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.submit', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.approve', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();

        return $cert->fresh();
    }

    // ─── View render tests (no PDF engine needed) ─────────────────────

    public function test_certificate_pdf_view_renders_key_strings(): void
    {
        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $summaryService = new \App\Services\PaymentCertificateSummaryService();
        $boqLines = $this->boq->lineItems()->get()->keyBy('id');
        $lineSummaries = $summaryService->lineSummaries($cert);

        $html = view('contracts.certificate-pdf', [
            'contract' => $this->contract,
            'certificate' => $cert,
            'boqLinesById' => $boqLines,
            'lineSummaries' => $lineSummaries,
            'tenantName' => $this->tenant->name,
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords($cert->net_payable),
        ])->render();

        $this->assertStringContainsString('BIÊN BẢN NGHIỆM THU KHỐI LƯỢNG', $html);
        $this->assertStringContainsString('Kỳ 1', $html);
        $this->assertStringContainsString('225.000.000', $html);
        $this->assertStringContainsString('Hai trăm hai mươi lăm triệu đồng', $html);
        $this->assertStringContainsString('Móng cọc BT', $html);
        $this->assertStringContainsString('Giữ lại', $html);
        $this->assertStringContainsString('Đề nghị thanh toán', $html);
        // Blade {{ }} escapes HTML entities: faker names containing quotes/ampersands
        // (e.g. "O'Kon & Sons") appear as &#039;/&amp; in output — assert the escaped form.
        $this->assertStringContainsString(e($this->contract->client_name), $html);
        $this->assertStringContainsString(e($this->tenant->name), $html);
    }

    // ─── Endpoint tests ───────────────────────────────────────────────

    public function test_approved_certificate_returns_pdf(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    return '%PDF-1.4 fake-cert-pdf-bytes';
                }
            };
        });

        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.pdf', [$this->contract->id, $cert->id]),
            $this->headers()
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fake-cert-pdf-bytes', $response->getContent());
    }

    public function test_draft_certificate_redirects_with_error(): void
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ], $h)->assertRedirect();

        $cert = PaymentCertificate::query()
            ->where('contract_id', $this->contract->id)
            ->where('period_no', 1)
            ->first();

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.pdf', [$this->contract->id, $cert->id]),
            $h
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cross_contract_certificate_returns_404(): void
    {
        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $otherContract = Contract::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'code' => 'CTR-OTHER',
            'title' => 'HĐ khác',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 500000000,
            'currency' => 'VND',
            'created_by' => (string) $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.pdf', [(string) $otherContract->id, (string) $cert->id]),
            $this->headers()
        );

        $response->assertNotFound();
    }

    public function test_cross_tenant_returns_404(): void
    {
        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['payment_certificate.view']);

        $response = $this->actingAs($otherUser)->get(
            route('operator.contracts.certificates.pdf', [$this->contract->id, $cert->id]),
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

        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($member)->get(
            route('operator.contracts.certificates.pdf', [$this->contract->id, $cert->id]),
            $this->headers()
        );

        $response->assertForbidden();
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

        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.pdf', [$this->contract->id, $cert->id]),
            $this->headers()
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    // ─── Button render tests ──────────────────────────────────────────

    public function test_certificate_show_shows_pdf_button_when_approved(): void
    {
        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.show', [$this->contract->id, $cert->id]),
            $this->headers()
        );

        $response->assertOk();
        $response->assertSee('Xuất biên bản');
    }

    public function test_certificate_show_hides_pdf_button_when_draft(): void
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ], $h)->assertRedirect();

        $cert = PaymentCertificate::query()
            ->where('contract_id', $this->contract->id)
            ->where('period_no', 1)
            ->first();

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.show', [$this->contract->id, $cert->id]),
            $h
        );

        $response->assertOk();
        $response->assertDontSee('Xuất biên bản');
    }
}
