<?php

namespace Tests\Unit\Services;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Services\PaymentCertificateSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentCertificateSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Project $project;
    private Contract $contract;
    private Boq $boq;
    private BoqLineItem $lineA;  // 100 units @ 200,000
    private BoqLineItem $lineB;  // 50 units @ 1,000,000
    private PaymentCertificateSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PaymentCertificateSummaryService();

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->contract = Contract::query()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'code' => 'HD-001',
            'contract_number' => 'C001',
            'title' => 'Test Contract',
            'total_value' => 70000000,
            'version' => 1,
            'status' => 'active',
        ]);

        $this->boq = Boq::query()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'BOQ-HD-001',
            'name' => 'Bảng khối lượng HD-001',
        ]);

        $this->lineA = BoqLineItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'boq_id' => $this->boq->id,
            'code' => 'A',
            'name' => 'Đào đất',
            'unit' => 'm3',
            'quantity' => 100,
            'unit_price' => 200000,
        ]);

        $this->lineB = BoqLineItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'boq_id' => $this->boq->id,
            'code' => 'B',
            'name' => 'Bê tông',
            'unit' => 'm3',
            'quantity' => 50,
            'unit_price' => 1000000,
        ]);
    }

    /** @test */
    public function draft_certificate_shows_zero_prev_and_this_from_own_lines(): void
    {
        $cert = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_DRAFT);

        // Manually add lines to the draft cert
        PaymentCertificateLine::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_certificate_id' => $cert->id,
            'boq_line_item_id' => $this->lineA->id,
            'qty_this_period' => 30,
            'unit_price_snapshot' => 200000,
            'amount_this_period' => 6000000,
        ]);

        $summaries = $this->service->lineSummaries($cert);

        // Line A
        $a = $summaries[$this->lineA->id];
        $this->assertEquals(100.0, $a['contract_qty']);
        $this->assertEquals(200000.0, $a['unit_price']);
        $this->assertEquals(0.0, $a['prev_qty']);  // no approved certs yet
        $this->assertEquals(30.0, $a['this_qty']);
        $this->assertEquals(70.0, $a['remaining_qty']);  // 100 - 0 - 30
        $this->assertEquals(30.0, $a['percent_done']);     // 30/100*100
        $this->assertFalse($a['over_quantity']);            // 30 < 100
        $this->assertEquals(6000000.0, $a['amount_this_period']);

        // Line B (no entry in draft cert → this_qty=0)
        $b = $summaries[$this->lineB->id];
        $this->assertEquals(50.0, $b['contract_qty']);
        $this->assertEquals(1000000.0, $b['unit_price']);
        $this->assertEquals(0.0, $b['prev_qty']);
        $this->assertEquals(0.0, $b['this_qty']);
        $this->assertEquals(50.0, $b['remaining_qty']);
        $this->assertEquals(0.0, $b['percent_done']);
        $this->assertFalse($b['over_quantity']);
        $this->assertEquals(0.0, $b['amount_this_period']);
    }

    /** @test */
    public function approved_previous_certificates_contribute_to_prev_qty(): void
    {
        // Period 1: approved — 30 of A, 10 of B
        $cert1 = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_APPROVED);
        $this->addLine($cert1, $this->lineA, 30, 200000);
        $this->addLine($cert1, $this->lineB, 10, 1000000);

        // Period 2: draft — 50 of A
        $cert2 = $this->createCertificate(2, '2026-02-01', '2026-02-28', PaymentCertificate::STATUS_DRAFT);
        $this->addLine($cert2, $this->lineA, 50, 200000);

        $summaries = $this->service->lineSummaries($cert2);

        $a = $summaries[$this->lineA->id];
        $this->assertEquals(30.0, $a['prev_qty']);  // from cert1
        $this->assertEquals(50.0, $a['this_qty']);  // from cert2
        $this->assertEquals(80.0, $a['percent_done']);  // 80/100*100
        $this->assertFalse($a['over_quantity']);  // 80 <= 100

        $b = $summaries[$this->lineB->id];
        $this->assertEquals(10.0, $b['prev_qty']);  // from cert1
        $this->assertEquals(0.0, $b['this_qty']);    // no entry in cert2
    }

    /** @test */
    public function over_quantity_flagged_when_cumulative_exceeds_contract_qty(): void
    {
        // Period 1: approved — 30 of A
        $cert1 = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_APPROVED);
        $this->addLine($cert1, $this->lineA, 30, 200000);

        // Period 2: draft — 80 of A (30+80=110 > 100)
        $cert2 = $this->createCertificate(2, '2026-02-01', '2026-02-28', PaymentCertificate::STATUS_DRAFT);
        $this->addLine($cert2, $this->lineA, 80, 200000);

        $summaries = $this->service->lineSummaries($cert2);

        $a = $summaries[$this->lineA->id];
        $this->assertTrue($a['over_quantity']);  // 110 > 100
        $this->assertEquals(110.0, $a['prev_qty'] + $a['this_qty']);
    }

    /** @test */
    public function zero_contract_qty_yields_zero_percent(): void
    {
        // Create a BOQ line with 0 quantity
        $zeroLine = BoqLineItem::query()->create([
            'tenant_id' => $this->tenant->id,
            'boq_id' => $this->boq->id,
            'code' => 'C',
            'name' => 'Empty',
            'unit' => 'pc',
            'quantity' => 0,
            'unit_price' => 50000,
        ]);

        $cert = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_DRAFT);
        $this->addLine($cert, $zeroLine, 5, 50000);

        $summaries = $this->service->lineSummaries($cert);

        $c = $summaries[$zeroLine->id];
        $this->assertEquals(0.0, $c['contract_qty']);
        $this->assertEquals(0.0, $c['percent_done']);
        $this->assertFalse($c['over_quantity']);  // 5 > 0 but contract_qty is 0 — edge: over should be false (no contract qty to exceed)
    }

    /** @test */
    public function only_approved_certificates_count_as_prev(): void
    {
        // Period 1: submitted (NOT approved) — should NOT count
        $cert1 = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_SUBMITTED);
        $this->addLine($cert1, $this->lineA, 40, 200000);

        // Period 2: draft
        $cert2 = $this->createCertificate(2, '2026-02-01', '2026-02-28', PaymentCertificate::STATUS_DRAFT);
        $this->addLine($cert2, $this->lineA, 10, 200000);

        $summaries = $this->service->lineSummaries($cert2);

        $a = $summaries[$this->lineA->id];
        $this->assertEquals(0.0, $a['prev_qty']);  // submitted not approved
        $this->assertEquals(10.0, $this->lineA->id ? $a['this_qty'] : 0);
    }

    /** @test */
    public function current_certificate_not_counted_in_prev_even_if_approved(): void
    {
        // Period 1: approved
        $cert1 = $this->createCertificate(1, '2026-01-01', '2026-01-31', PaymentCertificate::STATUS_APPROVED);
        $this->addLine($cert1, $this->lineA, 20, 200000);

        // Period 2: approved
        $cert2 = $this->createCertificate(2, '2026-02-01', '2026-02-28', PaymentCertificate::STATUS_APPROVED);
        $this->addLine($cert2, $this->lineA, 30, 200000);

        // Period 3: draft — prev should be 20+30=50 (not including itself)
        $cert3 = $this->createCertificate(3, '2026-03-01', '2026-03-31', PaymentCertificate::STATUS_DRAFT);
        $this->addLine($cert3, $this->lineA, 10, 200000);

        $summaries = $this->service->lineSummaries($cert3);

        $a = $summaries[$this->lineA->id];
        $this->assertEquals(50.0, $a['prev_qty']);  // cert1(20) + cert2(30)
        $this->assertEquals(10.0, $a['this_qty']);
        $this->assertEquals(60.0, $a['percent_done']);
    }

    private function createCertificate(int $periodNo, string $from, string $to, string $status): PaymentCertificate
    {
        return PaymentCertificate::query()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $this->contract->id,
            'period_no' => $periodNo,
            'period_from' => $from,
            'period_to' => $to,
            'status' => $status,
            'total_this_period' => 0,
        ]);
    }

    private function addLine(PaymentCertificate $cert, BoqLineItem $item, float $qty, float $price): PaymentCertificateLine
    {
        return PaymentCertificateLine::query()->create([
            'tenant_id' => $this->tenant->id,
            'payment_certificate_id' => $cert->id,
            'boq_line_item_id' => $item->id,
            'qty_this_period' => $qty,
            'unit_price_snapshot' => $price,
            'amount_this_period' => $qty * $price,
        ]);
    }
}
