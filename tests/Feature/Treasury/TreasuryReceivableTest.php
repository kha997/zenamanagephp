<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryDashboardService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReceivableService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRouteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-105 R-1 — investor receivable per contract from the contract lifecycle (ContractPayment paid). Treasury arrivals
 * are informational and unlinked Treasury funding is shown apart, never subtracted, so nothing is counted twice.
 */
class TreasuryReceivableTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private TreasuryWallet $bank;

    private TreasuryFinancialParty $investor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-03 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor', 'name' => 'Nhà đầu tư A']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_contracts_is_no_data(): void
    {
        $receivable = $this->service()->forProject($this->project);

        $this->assertSame('no_data', $receivable['availability']);
        $this->assertSame([], $receivable['contracts']);
        $this->assertNull($receivable['totals']['outstanding']);
    }

    public function test_receivable_per_contract_uses_paid_contract_payments_only(): void
    {
        $design = $this->contract('HĐ-TK', 1000);
        $build = $this->contract('HĐ-TC', 5000);
        $this->contract('HĐ-HUY', 9000, Contract::STATUS_CANCELLED);
        $this->payment($design, 400, ContractPayment::STATUS_PAID);
        $this->payment($design, 300, ContractPayment::STATUS_PLANNED);
        $this->payment($build, 6000, ContractPayment::STATUS_PAID);

        $receivable = $this->service()->forProject($this->project);

        $this->assertSame('available', $receivable['availability']);
        $rows = collect($receivable['contracts'])->keyBy('code');
        $this->assertSame(['HĐ-TC', 'HĐ-TK'], $rows->keys()->sort()->values()->all());
        $this->assertSame(['1000.00', '400.00', '600.00'], [$rows['HĐ-TK']['value'], $rows['HĐ-TK']['paid'], $rows['HĐ-TK']['outstanding']]);
        $this->assertSame('-1000.00', $rows['HĐ-TC']['outstanding']);
        $this->assertTrue($rows['HĐ-TC']['overpaid']);
        $this->assertSame(['6000.00', '6400.00', '-400.00'], [$receivable['totals']['value'], $receivable['totals']['paid'], $receivable['totals']['outstanding']]);
    }

    public function test_treasury_funding_is_shown_apart_and_never_reduces_receivable(): void
    {
        $contract = $this->contract('HĐ-1', 1000);
        $posting = app(TreasuryPostingService::class);
        $posting->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $this->investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '700', 'transaction_date' => '2026-10-01', 'reference' => 'F-700',
        ]);

        $receivable = $this->service()->forProject($this->project);

        $this->assertSame('1000.00', $receivable['totals']['outstanding']);
        $this->assertSame('700.00', $receivable['unlinked_funding']);
        $this->assertSame('0.00', collect($receivable['contracts'])->firstWhere('id', (string) $contract->id)['arrived_in_treasury']);
    }

    public function test_contract_payment_arrived_through_treasury_is_informational(): void
    {
        $contract = $this->contract('HĐ-1', 1000);
        $payment = $this->payment($contract, 400, ContractPayment::STATUS_PAID);
        $routes = app(TreasuryRouteService::class);
        $route = $routes->createContractPaymentRoute($this->project, $this->owner, $payment, [
            'expected_destination_wallet_id' => (string) $this->bank->id, 'amount' => '400',
        ]);
        $routes->recordLeg($this->project, $this->owner, $route, [
            'from_wallet_id' => null, 'to_wallet_id' => (string) $this->bank->id, 'amount' => '400', 'occurred_at' => '2026-10-06',
        ]);

        $row = collect($this->service()->forProject($this->project)['contracts'])->firstWhere('id', (string) $contract->id);

        $this->assertSame('400.00', $row['paid']);
        $this->assertSame('400.00', $row['arrived_in_treasury']);
        $this->assertSame('600.00', $row['outstanding']);
    }

    public function test_other_tenant_and_project_contracts_are_ignored(): void
    {
        $other = $this->projectFor($this->tenant);
        Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $other->id, 'status' => Contract::STATUS_ACTIVE, 'total_value' => 500, 'created_by' => $this->owner->id]);

        $this->assertSame('no_data', $this->service()->forProject($this->project)['availability']);
    }

    public function test_dashboard_cards_and_report_show_the_receivable(): void
    {
        $contract = $this->contract('HĐ-1', 1000);
        $this->payment($contract, 250, ContractPayment::STATUS_PAID);

        $cards = app(TreasuryDashboardService::class)->dashboard($this->project)['cards'];
        $this->assertSame('1000.00', $cards['contract_value']);
        $this->assertSame('750.00', $cards['receivable_outstanding']);

        $report = app(TreasuryReportService::class)->report($this->project, 'investor_receivable');
        $this->assertSame('Phải thu nhà đầu tư', $report['title']);
        $this->assertContains(['HĐ-1', (string) $contract->title, '1000.00', '250.00', '0.00', '750.00'], $report['rows']);

        $this->actingAs($this->owner)
            ->get(route('operator.treasury.projects.show', $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id])
            ->assertOk()
            ->assertSee('Còn phải thu')
            ->assertSee('Giá trị hợp đồng');
    }

    public function test_dashboard_cards_are_no_data_without_contracts(): void
    {
        $cards = app(TreasuryDashboardService::class)->dashboard($this->project)['cards'];

        $this->assertNull($cards['contract_value']);
        $this->assertNull($cards['receivable_outstanding']);
    }

    private function service(): TreasuryReceivableService
    {
        return app(TreasuryReceivableService::class);
    }

    private function contract(string $code, int $value, string $status = Contract::STATUS_ACTIVE): Contract
    {
        return Contract::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'code' => $code,
            'status' => $status,
            'total_value' => $value,
            'created_by' => $this->owner->id,
        ]);
    }

    private function payment(Contract $contract, int $amount, string $status): ContractPayment
    {
        return ContractPayment::query()->create([
            'tenant_id' => $this->tenant->id,
            'contract_id' => $contract->id,
            'name' => 'Đợt',
            'amount' => $amount,
            'due_date' => '2026-10-20',
            'status' => $status,
            'paid_at' => $status === ContractPayment::STATUS_PAID ? '2026-10-21' : null,
        ]);
    }
}
