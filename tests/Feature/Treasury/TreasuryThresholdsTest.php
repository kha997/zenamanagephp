<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryDashboardService as Dashboard;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryRuleViolation;
use App\Services\Treasury\TreasurySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-108 (Gate 2 N-1, S-1, Q-1) — two tenant-wide Treasury thresholds: days before unreconciled funding alerts
 * (default 30) and the term after which an advance without a due date is overdue (default 90). Owner-level only,
 * audited, 1–365 days; defaults keep the S6 behaviour.
 */
class TreasuryThresholdsTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private TreasuryWallet $bank;

    private TreasuryFinancialParty $holder;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->headers = ['X-Tenant-ID' => (string) $this->tenant->id];
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'name' => 'Kỹ sư Z']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
        // Funding dated 2026-10-01: 34 days old on 2026-11-04, unreconciled.
        app(TreasuryPostingService::class)->declareFunding($this->project, $this->owner, [
            'document_type' => 'funding', 'source_party_id' => (string) $investor->id,
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '100000', 'transaction_date' => '2026-10-01', 'reference' => 'F-1',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_defaults_bounds_owner_only_audit_and_tenant_scope(): void
    {
        $settings = app(TreasurySettingsService::class);
        $this->assertSame(['unreconciled_funding_days' => 30, 'advance_default_term_days' => 90], $settings->thresholds((string) $this->tenant->id));

        $this->assertRule(fn () => $settings->setThresholds($this->accountant, 10, 45), 'chủ doanh nghiệp');
        $this->assertRule(fn () => $settings->setThresholds($this->owner, 0, 45), '1 đến 365');
        $this->assertRule(fn () => $settings->setThresholds($this->owner, 10, 366), '1 đến 365');
        $this->assertSame(0, AuditLog::query()->where('action', 'treasury.thresholds.changed')->count());

        $settings->setThresholds($this->owner, 10, 45);
        $this->assertSame(['unreconciled_funding_days' => 10, 'advance_default_term_days' => 45], $settings->thresholds((string) $this->tenant->id));
        $audit = AuditLog::query()->where('action', 'treasury.thresholds.changed')->sole();
        $this->assertSame(['unreconciled_funding_days' => 30, 'advance_default_term_days' => 90], $audit->old_data);
        $this->assertSame(['unreconciled_funding_days' => 10, 'advance_default_term_days' => 45], $audit->new_data);

        // Other tenant keeps the defaults; the evidence switch beside it is untouched.
        $other = Tenant::factory()->create();
        $this->assertSame(['unreconciled_funding_days' => 30, 'advance_default_term_days' => 90], $settings->thresholds((string) $other->id));
        $this->assertFalse(app(\App\Services\Treasury\TreasuryEvidenceService::class)->evidenceRequired((string) $this->tenant->id));
    }

    public function test_funding_threshold_drives_the_unreconciled_funding_alert(): void
    {
        $this->assertTrue($this->hasAlert(Dashboard::ALERT_UNRECONCILED_FUNDING, '2026-11-04'));
        app(TreasurySettingsService::class)->setThresholds($this->owner, 40, 90);
        $this->assertFalse($this->hasAlert(Dashboard::ALERT_UNRECONCILED_FUNDING, '2026-11-04'));
        $this->assertFalse($this->hasAlert(Dashboard::ALERT_UNRECONCILED_FUNDING, '2026-11-10'));
        $this->assertTrue($this->hasAlert(Dashboard::ALERT_UNRECONCILED_FUNDING, '2026-11-11'));
        $this->assertSame(40, app(Dashboard::class)->dashboard($this->project)['thresholds']['unreconciled_funding_days']);
    }

    public function test_advance_term_drives_overdue_for_advances_without_due_date(): void
    {
        $advances = app(TreasuryAdvanceService::class);
        $draft = $advances->createDraft($this->project, $this->owner, [
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id, 'amount' => '400', 'transaction_date' => '2026-11-04',
        ]);
        $advances->submit($this->project, $this->owner, $draft);
        $advances->approve($this->project, $this->accountant, $draft->fresh());

        // Default 90: overdue from day 90 after payment, as in S6.
        $this->assertFalse($this->row('2027-02-01')['overdue']);
        $this->assertTrue($this->row('2027-02-02')['overdue']);
        $this->assertSame(1, $this->row('2027-02-02')['overdue_days']);
        $this->assertSame(TreasuryAdvanceService::BUCKET_OVER_90, $this->row('2027-02-02')['bucket']);

        app(TreasurySettingsService::class)->setThresholds($this->owner, 30, 30);
        $row = $this->row('2026-12-03');
        $this->assertSame(29, $row['days']);
        $this->assertFalse($row['overdue']);
        $this->assertNull($row['due_date']);
        $this->assertSame('2026-12-03', $row['effective_due_date']);
        $this->assertTrue($this->row('2026-12-04')['overdue']);
        $this->assertSame(1, $this->row('2026-12-04')['overdue_days']);
        // Buckets stay fixed (N-1): 30 days is still the 30–60 bucket.
        $this->assertSame(TreasuryAdvanceService::BUCKET_30_60, $this->row('2026-12-04')['bucket']);
        $this->assertFalse($this->hasAlert(Dashboard::ALERT_OVERDUE_ADVANCES, '2026-12-03'));
        $this->assertTrue($this->hasAlert(Dashboard::ALERT_OVERDUE_ADVANCES, '2026-12-04'));
    }

    public function test_settings_form_is_owner_only_and_labels_follow_the_threshold(): void
    {
        $this->actingAs($this->owner)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertSee('treasury-thresholds-form', false);
        $this->actingAs($this->owner)->post(route('operator.treasury.settings.thresholds'), [
            'unreconciled_funding_days' => '20', 'advance_default_term_days' => '60',
        ], $this->headers)->assertRedirect(route('operator.treasury.index'));
        $this->assertSame(['unreconciled_funding_days' => 20, 'advance_default_term_days' => 60], app(TreasurySettingsService::class)->thresholds((string) $this->tenant->id));
        $this->actingAs($this->owner)->post(route('operator.treasury.settings.thresholds'), [
            'unreconciled_funding_days' => '0', 'advance_default_term_days' => '60',
        ], $this->headers)->assertSessionHasErrors('unreconciled_funding_days');

        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id), $this->headers)
            ->assertOk()->assertSee('Tiền nhận chưa đối soát quá 20 ngày');

        $this->actingAs($this->accountant)->get(route('operator.treasury.index'), $this->headers)
            ->assertOk()->assertDontSee('treasury-thresholds-form', false);
        $this->actingAs($this->accountant)->post(route('operator.treasury.settings.thresholds'), [
            'unreconciled_funding_days' => '5', 'advance_default_term_days' => '5',
        ], $this->headers);
        $this->assertSame(20, app(TreasurySettingsService::class)->thresholds((string) $this->tenant->id)['unreconciled_funding_days']);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $today): array
    {
        return app(TreasuryAdvanceService::class)->ageing($this->project, Carbon::parse($today))['rows'][0];
    }

    private function hasAlert(string $code, string $today): bool
    {
        return collect(app(Dashboard::class)->dashboard($this->project, Carbon::parse($today))['alerts'])->contains('code', $code);
    }

    private function assertRule(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a TreasuryRuleViolation.');
        } catch (TreasuryRuleViolation $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
