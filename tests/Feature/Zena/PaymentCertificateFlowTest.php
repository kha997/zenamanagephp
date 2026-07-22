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

class PaymentCertificateFlowTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $user;
    private Project $project;
    private Contract $contract;
    private Boq $boq;
    private BoqLineItem $line1;
    private BoqLineItem $line2;

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
            'code' => 'CTR-IPC-01',
            'title' => 'HĐ thử IPC',
            'contract_type' => Contract::TYPE_CONSTRUCTION,
            'created_by' => (string) $this->user->id,
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'project_id' => (string) $this->project->id,
            'contract_id' => (string) $this->contract->id,
            'code' => 'BOQ-CTR-IPC-01',
            'name' => 'Bảng khối lượng HĐ CTR-IPC-01',
        ]);

        $this->line1 = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L001',
            'name' => 'Móng cọc',
            'quantity' => 100,
            'unit' => 'm',
            'unit_price' => 200000,
        ]);

        $this->line2 = BoqLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'boq_id' => (string) $this->boq->id,
            'code' => 'L002',
            'name' => 'Bê tông',
            'quantity' => 50,
            'unit' => 'm3',
            'unit_price' => 1000000,
        ]);

        $this->get('/login');
    }

    private function headers(): array
    {
        return ['X-Tenant-ID' => (string) $this->tenant->id];
    }

    public function test_happy_path_create_submit_approve(): void
    {
        $h = $this->headers();

        // 1. Create certificate kỳ 1
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ], $h)->assertRedirect();

        $cert = PaymentCertificate::query()->where('contract_id', $this->contract->id)->first();
        $this->assertNotNull($cert);
        $this->assertSame(1, $cert->period_no);
        $this->assertSame(PaymentCertificate::STATUS_DRAFT, $cert->status);

        // 2. Save lines: 30 on line1, 10 on line2
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]), [
            'lines' => [
                (string) $this->line1->id => 30,
                (string) $this->line2->id => 10,
            ],
        ], $h)->assertRedirect();

        $cert->refresh();
        $this->assertSame(16000000.0, $cert->total_this_period);

        // Verify snapshot price
        $line = PaymentCertificateLine::query()
            ->where('payment_certificate_id', $cert->id)
            ->where('boq_line_item_id', $this->line1->id)
            ->first();
        $this->assertSame(200000.0, $line->unit_price_snapshot);
        $this->assertSame(6000000.0, $line->amount_this_period);

        // 3. Submit
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.submit', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();
        $cert->refresh();
        $this->assertSame(PaymentCertificate::STATUS_SUBMITTED, $cert->status);

        // 4. Approve
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.approve', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();
        $cert->refresh();
        $this->assertSame(PaymentCertificate::STATUS_APPROVED, $cert->status);
        $this->assertNotNull($cert->approved_at);

        // 5. Verify ContractPayment created
        $this->assertDatabaseHas('contract_payments', [
            'contract_id' => (string) $this->contract->id,
            'name' => 'Nghiệm thu KL kỳ 1',
            'amount' => 16000000,
            'status' => ContractPayment::STATUS_PLANNED,
        ]);

        // 6. Verify EventRecord
        $this->assertDatabaseHas('event_records', [
            'aggregate_type' => 'payment_certificate',
            'event_key' => 'payment_certificate.approved',
        ]);
    }

    public function test_cumulative_succeeds_even_when_over_quantity(): void
    {
        $h = $this->headers();

        // Approve kỳ 1 with 30 on line1
        $cert1 = PaymentCertificate::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'period_no' => 1,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => PaymentCertificate::STATUS_DRAFT,
        ]);
        PaymentCertificateLine::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'payment_certificate_id' => $cert1->id,
            'boq_line_item_id' => $this->line1->id,
            'qty_this_period' => 30,
            'unit_price_snapshot' => 200000,
            'amount_this_period' => 6000000,
        ]);
        $cert1->update(['total_this_period' => 6000000, 'status' => PaymentCertificate::STATUS_APPROVED, 'approved_by' => $this->user->id, 'approved_at' => now()]);

        // Create kỳ 2
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $h)->assertRedirect();

        $cert2 = PaymentCertificate::query()->where('contract_id', $this->contract->id)->where('period_no', 2)->first();
        $this->assertNotNull($cert2);

        // Save 80 on line1 → cumulative 30+80=110 > 100 contract qty → warning but save succeeds
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert2->id]), [
            'lines' => [
                (string) $this->line1->id => 80,
            ],
        ], $h)->assertRedirect();

        $line = PaymentCertificateLine::query()
            ->where('payment_certificate_id', $cert2->id)
            ->where('boq_line_item_id', $this->line1->id)
            ->first();
        $this->assertNotNull($line);
        $this->assertSame(80.0, $line->qty_this_period);
    }

    public function test_snapshot_price_immutable_after_approve(): void
    {
        $h = $this->headers();

        // Approve kỳ 1
        $cert1 = PaymentCertificate::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'period_no' => 1,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => PaymentCertificate::STATUS_APPROVED,
            'approved_by' => $this->user->id,
            'approved_at' => now(),
        ]);
        PaymentCertificateLine::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'payment_certificate_id' => $cert1->id,
            'boq_line_item_id' => $this->line1->id,
            'qty_this_period' => 30,
            'unit_price_snapshot' => 200000,
            'amount_this_period' => 6000000,
        ]);
        $cert1->update(['total_this_period' => 6000000]);

        // Try to update BOQ line → should error (locked)
        $this->actingAs($this->user)->post(route('operator.contracts.boq-lines.update', [$this->contract->id, $this->line1->id]), [
            'code' => 'L001',
            'name' => 'Móng cọc',
            'unit' => 'm',
            'quantity' => 100,
            'unit_price' => 999999,
        ], $h)->assertSessionHasErrors();

        // Line 1 snapshot remains 200000
        $line = PaymentCertificateLine::query()
            ->where('payment_certificate_id', $cert1->id)
            ->where('boq_line_item_id', $this->line1->id)
            ->first();
        $this->assertSame(200000.0, $line->unit_price_snapshot);
    }

    public function test_period_no_auto_increments(): void
    {
        $h = $this->headers();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
        ], $h)->assertRedirect();

        $this->actingAs($this->user)->post(route('operator.contracts.certificates.store', $this->contract->id), [
            'period_from' => '2026-08-01',
            'period_to' => '2026-08-31',
        ], $h)->assertRedirect();

        $certs = PaymentCertificate::query()->where('contract_id', $this->contract->id)->orderBy('period_no')->get();
        $this->assertCount(2, $certs);
        $this->assertSame(1, $certs[0]->period_no);
        $this->assertSame(2, $certs[1]->period_no);
    }

    public function test_transition_rules_enforced(): void
    {
        $h = $this->headers();

        // Create and approve kỳ 1
        $cert = PaymentCertificate::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'period_no' => 1,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => PaymentCertificate::STATUS_DRAFT,
        ]);

        // Try to approve a draft directly → error
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.approve', [$this->contract->id, $cert->id]), [], $h)->assertSessionHasErrors();

        // Submit first
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.submit', [$this->contract->id, $cert->id]), [], $h)->assertRedirect();
        $cert->refresh();
        $this->assertSame(PaymentCertificate::STATUS_SUBMITTED, $cert->status);

        // Try to save lines on submitted cert → should error
        $this->actingAs($this->user)->post(route('operator.contracts.certificates.lines.save', [$this->contract->id, $cert->id]), [
            'lines' => [(string) $this->line1->id => 10],
        ], $h)->assertSessionHasErrors();
    }

    public function test_cross_tenant_and_permission_denial(): void
    {
        $h = $this->headers();

        // Create a certificate
        $cert = PaymentCertificate::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contract_id' => (string) $this->contract->id,
            'period_no' => 1,
            'period_from' => '2026-07-01',
            'period_to' => '2026-07-31',
            'status' => PaymentCertificate::STATUS_SUBMITTED,
        ]);

        // Cross-tenant user → 404
        $otherTenant = Tenant::factory()->create();
        $intruder = $this->createTenantUser($otherTenant, [], ['admin'], ['payment_certificate.view']);
        $this->actingAs($intruder)->get(route('operator.contracts.certificates.show', [$this->contract->id, $cert->id]), ['X-Tenant-ID' => (string) $otherTenant->id])->assertNotFound();

        // User without approve permission cannot approve
        $noApprove = $this->createTenantUser($this->tenant, [], ['team_member'], ['payment_certificate.view', 'payment_certificate.create']);
        $this->actingAs($noApprove)->post(route('operator.contracts.certificates.approve', [$this->contract->id, $cert->id]), [], $h)->assertStatus(302);
    }
}
