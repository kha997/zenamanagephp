<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class CertificateDeductionsTest extends TestCase
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
            'code' => 'CTR-DED-01',
            'title' => 'HĐ thử deductions',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'total_value' => 1000000000,
            'retention_percent' => 5,
            'advance_amount' => 200000000,
            'advance_recovery_percent' => 20,
            'created_by' => (string) $this->user->id,
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-DED-01',
            'name' => 'Bảng khối lượng HĐ CTR-DED-01',
        ]);

        $this->line1 = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc',
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

    /**
     * Helper: create cert, save lines, approve. Returns the approved cert.
     */
    private function createAndApproveCert(int $periodNo, float $qty, string $periodFrom, string $periodTo, ?float $advanceOverride = null): PaymentCertificate
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

        $postBody = [
            'lines' => [
                (string) $this->line1->id => $qty,
            ],
        ];
        if ($advanceOverride !== null) {
            $postBody['advance_deduction'] = $advanceOverride;
        }

        $this->actingAs($this->user)->post(
            route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]),
            $postBody,
            $h
        )->assertRedirect();

        // Submit + approve
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.submit', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.approve', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();

        return $cert->fresh();
    }

    public function test_ky1_deductions_and_net_payable(): void
    {
        // Kỳ 1: KL 3000 × 100000 = 300tr
        // retention = 5% × 300tr = 15tr, advance recovery = min(20% × 300tr=60tr, remaining=200tr) = 60tr
        // net = 300 − 15 − 60 = 225tr
        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $this->assertSame(300000000.0, $cert->total_this_period);
        $this->assertSame(15000000.0, $cert->retention_amount);
        $this->assertSame(60000000.0, $cert->advance_deduction);
        $this->assertSame(225000000.0, $cert->net_payable);

        // Payment amount should be net_payable
        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Nghiệm thu KL kỳ 1',
            'amount' => 225000000,
            'status' => ContractPayment::STATUS_PLANNED,
        ]);
    }

    public function test_ky2_suggested_deduction_respects_remaining(): void
    {
        // First approve kỳ 1 (same as above)
        $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        // Kỳ 2: KL 8000 × 100000 = 800tr
        // remaining advance = 200tr − 60tr (ky1) = 140tr
        // suggested = min(20% × 800tr = 160tr, 140tr) = 140tr
        // retention = 5% × 800tr = 40tr, net = 800 − 40 − 140 = 620tr
        $cert = $this->createAndApproveCert(2, 8000, '2026-08-01', '2026-08-31');

        $this->assertSame(800000000.0, $cert->total_this_period);
        $this->assertSame(40000000.0, $cert->retention_amount);
        $this->assertSame(140000000.0, $cert->advance_deduction);
        $this->assertSame(620000000.0, $cert->net_payable);

        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Nghiệm thu KL kỳ 2',
            'amount' => 620000000,
        ]);
    }

    public function test_override_exceeding_remaining_fails_validation(): void
    {
        $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $h = $this->headers();

        // Create kỳ 2 cert
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $h)->assertRedirect();

        $cert2 = PaymentCertificate::query()->where('contract_id', $this->contract->id)->where('period_no', 2)->first();

        // Override = 150tr > remaining 140tr → should fail
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert2->id]), [
            'lines' => [(string) $this->line1->id => 8000],
            'advance_deduction' => 150000000,
        ], $h)->assertSessionHasErrors('advance_deduction');
    }

    public function test_override_within_remaining_succeeds(): void
    {
        $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $h)->assertRedirect();

        $cert2 = PaymentCertificate::query()->where('contract_id', $this->contract->id)->where('period_no', 2)->first();

        // Override = 100tr ≤ remaining 140tr → should succeed
        // net = 800 − 40 − 100 = 660tr
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert2->id]), [
            'lines' => [(string) $this->line1->id => 8000],
            'advance_deduction' => 100000000,
        ], $h)->assertRedirect();

        $cert2->refresh();
        $this->assertSame(100000000.0, $cert2->advance_deduction);
        $this->assertSame(660000000.0, $cert2->net_payable);
    }

    public function test_snapshot_immutable_after_config_change(): void
    {
        // Approve kỳ 1 with 5% retention → 15tr
        $cert1 = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');
        $this->assertSame(15000000.0, $cert1->retention_amount);

        // Change retention to 10% on the contract
        $this->contract->update(['retention_percent' => 10]);

        // Refresh cert1 — should still be 15tr (snapshot)
        $cert1->refresh();
        $this->assertSame(15000000.0, $cert1->retention_amount);
    }

    public function test_edit_deduction_when_submitted_fails(): void
    {
        $h = $this->headers();

        // Create cert and save lines (draft)
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ], $h)->assertRedirect();

        $cert = PaymentCertificate::query()->where('contract_id', $this->contract->id)->first();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]), [
            'lines' => [(string) $this->line1->id => 3000],
        ], $h)->assertRedirect();

        // Submit
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.submit', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();

        // Try to save lines on submitted cert → should error
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]), [
            'lines' => [(string) $this->line1->id => 3000],
            'advance_deduction' => 50000000,
        ], $h)->assertSessionHasErrors();
    }

    public function test_certificate_show_renders_net_payable(): void
    {
        $h = $this->headers();

        $cert = $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.certificates.show', [$this->contract->id, $cert->id]),
            $h
        );

        $response->assertOk();
        $response->assertSee('Đề nghị thanh toán');
        $response->assertSee('225.000.000');
    }

    public function test_contract_show_renders_cumulative_retention(): void
    {
        $h = $this->headers();

        // Approve kỳ 1 → retention 15tr
        $this->createAndApproveCert(1, 3000, '2026-07-01', '2026-07-31');

        $response = $this->actingAs($this->user)->get(
            route('operator.contracts.show', $this->contract->id),
            $h
        );

        $response->assertOk();
        $response->assertSee('Đang giữ lại');
        $response->assertSee('15.000.000');
    }
}
