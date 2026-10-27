<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryPaymentRoute as Route;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GAP-095 S4b-2 — contract-payment card on the project page and tracing routes.
 */
class TreasuryContractPaymentRouteWebTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $accountant;

    private User $engineer;

    private ContractPayment $payment;

    private TreasuryWallet $siteCash;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        $this->seedTreasuryPermissions();
        $this->tenant = Tenant::factory()->create();
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $this->project = $this->projectFor($this->tenant);
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id, 'project_id' => $this->project->id, 'code' => 'HD-77']);
        $this->payment = ContractPayment::factory()->create([
            'tenant_id' => $this->tenant->id, 'contract_id' => $contract->id, 'name' => 'Đợt tạm ứng',
            'amount' => '1500000', 'status' => 'paid', 'paid_at' => '2026-10-05',
        ]);
        $this->siteCash = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Quỹ công trường']);
    }

    public function test_accountant_traces_a_paid_payment_from_the_project_page(): void
    {
        $projectPage = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('HD-77')
            ->assertSee('Đợt tạm ứng')
            ->assertSee('Chưa truy vết đủ')
            ->assertSee('treasury-contract-payment-route-form', false);

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.contract-payments.routes.store', ['contractPayment' => (string) $this->payment->id]), [
            'expected_destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1500000',
        ], $this->headers)->assertRedirect()->assertSessionHas('success');
        $route = Route::query()->firstOrFail();
        $routePage = $this->url('operator.treasury.projects.routes.show', ['treasuryRoute' => (string) $route->id]);

        $this->actingAs($this->accountant)->get($routePage, $this->headers)->assertOk()
            ->assertSee('Thanh toán HĐ: Đợt tạm ứng')
            ->assertSee('(đã thanh toán)')
            ->assertSee('Từ bên ngoài')
            ->assertSee('treasury-route-cancel-form', false);

        $this->actingAs($this->accountant)->post($this->url('operator.treasury.projects.routes.legs.store', ['treasuryRoute' => (string) $route->id]), [
            'from_wallet_id' => '', 'to_wallet_id' => (string) $this->siteCash->id, 'amount' => '1500000', 'occurred_at' => '2026-10-06',
        ], $this->headers)->assertRedirect($routePage)->assertSessionHas('success');
        $this->assertSame(Route::STATUS_COMPLETED, $route->fresh()?->status);

        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()
            ->assertSee('Tiền thanh toán hợp đồng đã về ví')
            ->assertDontSee('Chưa truy vết đủ')
            ->assertDontSee('treasury-contract-payment-route-form', false);
    }

    public function test_a_retry_after_a_duplicate_warning_keeps_the_entered_amount_and_wallet(): void
    {
        // Codex P2 / Owner amendment 1: confirming must not silently change the allocation.
        $second = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Ví thứ hai']);
        $investor = \App\Models\Treasury\TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        app(\App\Services\Treasury\TreasuryPostingService::class)->declareFunding($this->project, $this->accountant->fresh() ?? $this->accountant, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1500000', 'transaction_date' => '2026-10-05',
        ], confirmDuplicate: true);
        $projectPage = $this->url('operator.treasury.projects.show');
        $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk();

        $this->actingAs($this->accountant)->from($projectPage)->post($this->url('operator.treasury.projects.contract-payments.routes.store', ['contractPayment' => (string) $this->payment->id]), [
            'trace_payment_id' => (string) $this->payment->id,
            'expected_destination_wallet_id' => (string) $second->id, 'amount' => '400000',
        ], $this->headers)->assertRedirect($projectPage)->assertSessionHas('treasury_duplicate');
        $this->assertSame(0, Route::query()->count());

        $html = $this->actingAs($this->accountant)->get($projectPage, $this->headers)->assertOk()->getContent();
        $this->assertStringContainsString('value="400000"', (string) $html);
        $this->assertMatchesRegularExpression('/<option value="' . preg_quote((string) $second->id, '/') . '"\s+selected/', (string) $html);
        $this->assertStringContainsString('<details  open', (string) $html);
    }

    public function test_engineer_sees_the_list_but_cannot_trace(): void
    {
        $this->actingAs($this->engineer)->get($this->url('operator.treasury.projects.show'), $this->headers)->assertOk()
            ->assertSee('Đợt tạm ứng')
            ->assertDontSee('treasury-contract-payment-route-form', false);
        $this->actingAs($this->engineer)->post($this->url('operator.treasury.projects.contract-payments.routes.store', ['contractPayment' => (string) $this->payment->id]), [
            'expected_destination_wallet_id' => (string) $this->siteCash->id, 'amount' => '1',
        ], $this->headers)->assertRedirect()->assertSessionHas('error');
        $this->assertSame(0, Route::query()->count());
    }

    /**
     * @param array<string, string> $params
     */
    private function url(string $name, array $params = []): string
    {
        return route($name, ['project' => (string) $this->project->id] + $params, false);
    }
}
