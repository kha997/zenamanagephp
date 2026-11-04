<?php

declare(strict_types=1);

namespace Tests\Feature\Treasury;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\Treasury\TreasuryAdvance;
use App\Models\Treasury\TreasuryFinancialDocument as Document;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\Treasury\TreasuryWallet;
use App\Models\User;
use App\Services\Treasury\TreasuryAdvanceService;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDashboardService as Dashboard;
use App\Services\Treasury\TreasuryPeriodLockService;
use App\Services\Treasury\TreasuryPostingService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * GAP-108 (Gate 2 H-1, Q-1) — optional due date on an advance request, copied at posting, changeable afterwards by
 * approvers with a reason; overdue once the due date has passed with money still owed; shown on the advance table,
 * the advance page, the ageing report and the overdue alert.
 */
class TreasuryAdvanceDueDateTest extends TestCase
{
    use RefreshDatabase;
    use TreasuryAccessTestHelpers;

    private Tenant $tenant;

    private Project $project;

    private User $owner;

    private User $accountant;

    private User $engineer;

    private TreasuryWallet $bank;

    private TreasuryFinancialParty $holder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTreasuryPermissions();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        Carbon::setTestNow('2026-11-04 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->project = $this->projectFor($this->tenant);
        $this->owner = $this->userWithRole($this->tenant, 'Admin');
        $this->accountant = $this->userWithRole($this->tenant, 'Finance');
        $this->engineer = $this->userWithRole($this->tenant, 'site_engineer');
        $this->addMember($this->project, $this->engineer);
        $investor = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'investor']);
        $this->holder = TreasuryFinancialParty::factory()->create(['tenant_id' => $this->tenant->id, 'party_type' => 'employee', 'name' => 'Kỹ sư Z']);
        $this->bank = TreasuryWallet::factory()->create(['project_id' => $this->project->id, 'name' => 'Bank']);
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

    public function test_due_date_on_the_request_is_validated_and_copied_at_posting(): void
    {
        $advances = $this->advances();
        $this->assertRule(fn () => $advances->createDraft($this->project, $this->owner, $this->draftData('400', '2026-11-03')), 'Hạn hoàn ứng');

        $draft = $advances->createDraft($this->project, $this->owner, $this->draftData('400', '2026-11-20'));
        $this->assertSame('2026-11-20', $draft->fresh()->due_date?->toDateString());
        $draft = $advances->updateDraft($this->project, $this->owner, $draft, $this->draftData('400', '2026-11-30'));
        $this->assertSame('2026-11-30', $advances->detail($draft->fresh())['due_date']);
        $advances->submit($this->project, $this->owner, $draft);
        $advances->approve($this->project, $this->accountant, $draft->fresh());
        $this->assertSame('2026-11-30', TreasuryAdvance::query()->sole()->due_date?->toDateString());

        // No due date stays optional; a rejected request copies its due date into the new draft.
        $plain = $advances->createDraft($this->project, $this->owner, $this->draftData('100'));
        $this->assertNull($plain->fresh()->due_date);
        $rejected = $advances->createDraft($this->project, $this->owner, $this->draftData('200', '2026-12-15'));
        $advances->submit($this->project, $this->owner, $rejected);
        $advances->reject($this->project, $this->accountant, $rejected->fresh(), 'Thiếu dự toán');
        $copy = $advances->copyToDraft($this->project, $this->owner, $rejected->fresh());
        $this->assertSame('2026-12-15', $copy->fresh()->due_date?->toDateString());
    }

    public function test_overdue_rule_with_a_due_date(): void
    {
        $withDue = $this->postedAdvance('400', '2026-11-30');
        $this->postedAdvance('300');

        $row = $this->rowFor($withDue, '2026-11-30');
        $this->assertSame('2026-11-30', $row['due_date']);
        $this->assertFalse($row['overdue']);
        $this->assertSame(0, $row['overdue_days']);
        $this->assertFalse($this->hasAlert('2026-11-30'));

        $row = $this->rowFor($withDue, '2026-12-05');
        $this->assertTrue($row['overdue']);
        $this->assertSame(5, $row['overdue_days']);
        // The one without a due date is only 31 days old: not overdue (default term 90).
        $alert = collect(app(Dashboard::class)->dashboard($this->project, Carbon::parse('2026-12-05'))['alerts'])->firstWhere('code', Dashboard::ALERT_OVERDUE_ADVANCES);
        $this->assertSame(1, $alert['count']);
        $this->assertSame('400.00', $alert['amount']);

        // Fully returned: no longer owed, so no longer overdue.
        $this->advances()->recordReturn($this->project, $this->accountant, $withDue, [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '400', 'transaction_date' => '2026-11-04',
        ]);
        $this->assertFalse($this->hasAlert('2026-12-05'));
        $this->assertNull($this->rowFor($withDue, '2026-12-05'));
    }

    public function test_change_due_date_needs_approver_and_reason_is_audited_and_works_in_a_locked_period(): void
    {
        $advance = $this->postedAdvance('400');
        $advances = $this->advances();
        $held = app(TreasuryBalanceService::class)->projectSummary($this->project)['held_total'];

        $this->assertRule(fn () => $advances->changeDueDate($this->project, $this->engineer, $advance, '2026-11-10', 'Gia hạn'), 'quyền');
        $this->assertRule(fn () => $advances->changeDueDate($this->project, $this->accountant, $advance, '2026-11-10', ' '), 'lý do');
        $this->assertRule(fn () => $advances->changeDueDate($this->project, $this->accountant, $advance, '2026-11-03', 'Sai'), 'Hạn hoàn ứng');

        app(TreasuryPeriodLockService::class)->lock($this->project, $this->owner, '2026-11-04');
        $advances->changeDueDate($this->project, $this->accountant, $advance, '2026-11-10', 'Đã thống nhất với kỹ sư');
        $this->assertSame('2026-11-10', TreasuryAdvance::query()->sole()->due_date?->toDateString());
        $advances->changeDueDate($this->project, $this->accountant, $advance, null, 'Bỏ hạn');
        $this->assertNull(TreasuryAdvance::query()->sole()->due_date);

        $audits = AuditLog::query()->where('action', 'treasury.advance.due_date_changed')->orderBy('created_at')->get();
        $this->assertCount(2, $audits);
        $this->assertSame(['due_date' => null], $audits[0]->old_data);
        $this->assertSame('2026-11-10', $audits[0]->new_data['due_date']);
        $this->assertSame('Đã thống nhất với kỹ sư', $audits[0]->new_data['reason']);
        $this->assertSame($held, app(TreasuryBalanceService::class)->projectSummary($this->project)['held_total']);
        $this->assertSame('400.00', number_format((float) TreasuryAdvance::query()->sole()->amount, 2, '.', ''));

        // Not owed any more: nothing to chase.
        $advances->recordReturn($this->project, $this->accountant, $advance, [
            'destination_wallet_id' => (string) $this->bank->id, 'amount' => '400', 'transaction_date' => '2026-11-05',
        ]);
        $this->assertRule(fn () => $advances->changeDueDate($this->project, $this->accountant, $advance->fresh(), '2026-11-20', 'Gia hạn'), 'còn nợ');
    }

    public function test_web_pages_report_and_api(): void
    {
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id));
        $this->actingAs($this->owner)->post(route('operator.treasury.projects.advances.store', $this->project->id), $this->draftData('400', '2026-11-20') + ['action' => 'submit'])
            ->assertRedirect();
        $draft = Document::query()->where('document_type', Document::TYPE_ADVANCE)->sole();
        $this->assertSame('2026-11-20', $draft->due_date?->toDateString());
        $advance = $this->advances()->approve($this->project, $this->accountant, $draft);

        Carbon::setTestNow('2026-11-25 09:00:00');
        $this->actingAs($this->owner)->get(route('operator.treasury.projects.show', $this->project->id))
            ->assertOk()->assertSee('20/11/2026')->assertSee('Quá hạn 5 ngày')->assertSee('Tạm ứng quá hạn');
        $page = route('operator.treasury.projects.advances.show', [$this->project->id, $advance->id]);
        $this->actingAs($this->accountant)->get($page)->assertOk()
            ->assertSee('Hạn hoàn ứng')->assertSee('Quá hạn 5 ngày')->assertSee('treasury-advance-due-date-form', false);
        $this->actingAs($this->engineer)->get($page)->assertOk()->assertDontSee('treasury-advance-due-date-form', false);

        $change = route('operator.treasury.projects.advances.due-date', [$this->project->id, $advance->id]);
        $this->actingAs($this->accountant)->post($change, ['due_date' => '2026-11-30', 'reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($this->accountant)->post($change, ['due_date' => '2026-11-30', 'reason' => 'Gia hạn'])->assertRedirect($page);
        $this->assertSame('2026-11-30', TreasuryAdvance::query()->sole()->due_date?->toDateString());
        $this->actingAs($this->engineer)->post($change, ['due_date' => '', 'reason' => 'Bỏ hạn'])->assertRedirect();
        $this->assertSame('2026-11-30', TreasuryAdvance::query()->sole()->due_date?->toDateString());

        $report = app(TreasuryReportService::class)->report($this->project, 'advance_ageing', Carbon::parse('2026-12-02'));
        $this->assertSame(['Hạn', 'Quá hạn (ngày)'], array_slice($report['headers'], -2));
        $this->assertSame(['2026-11-30', 2], array_slice($report['rows'][0], -2));

        // API: draft with a due date, change it, read it back.
        $base = ['project' => (string) $this->project->id];
        $id = (string) $this->api($this->owner)->postJson(route('api.zena.treasury.advances.store', $base, false), $this->draftData('100', '2026-12-31'))
            ->assertStatus(201)->assertJsonPath('data.due_date', '2026-12-31')->json('data.id');
        $this->api($this->owner)->postJson(route('api.zena.treasury.advances.store', $base, false), $this->draftData('100', 'not-a-date'))
            ->assertStatus(422)->assertJsonValidationErrors(['due_date'], 'error.details.data');
        $dueDate = route('api.zena.treasury.advances.due-date', $base + ['treasuryAdvance' => (string) $advance->id], false);
        $this->api($this->engineer)->putJson($dueDate, ['due_date' => '2026-12-10', 'reason' => 'Gia hạn'])->assertForbidden();
        $this->api($this->accountant)->putJson($dueDate, ['due_date' => '2026-12-10'])
            ->assertStatus(422)->assertJsonValidationErrors(['reason'], 'error.details.data');
        $this->api($this->accountant)->putJson($dueDate, ['due_date' => '2026-12-10', 'reason' => 'Gia hạn'])->assertOk()
            ->assertJsonPath('data.advance.due_date', '2026-12-10')->assertJsonPath('data.advance.overdue', false);
        $this->assertNotSame('', $id);

        // Another tenant cannot reach this advance.
        $otherTenant = Tenant::factory()->create();
        $stranger = $this->userWithRole($otherTenant, 'Admin');
        $this->api($stranger)->putJson($dueDate, ['due_date' => '2026-12-20', 'reason' => 'x'])->assertStatus(404);
        $this->assertSame('2026-12-10', TreasuryAdvance::query()->withoutGlobalScopes()->sole()->due_date?->toDateString());
    }

    /**
     * @return array<string, string>
     */
    private function draftData(string $amount, ?string $dueDate = null): array
    {
        return array_filter([
            'source_wallet_id' => (string) $this->bank->id, 'destination_party_id' => (string) $this->holder->id,
            'amount' => $amount, 'transaction_date' => '2026-11-04', 'due_date' => $dueDate,
        ], static fn (?string $v): bool => $v !== null);
    }

    private function postedAdvance(string $amount, ?string $dueDate = null): Document
    {
        $advances = $this->advances();
        $draft = $advances->createDraft($this->project, $this->owner, $this->draftData($amount, $dueDate));
        $advances->submit($this->project, $this->owner, $draft);

        return $advances->approve($this->project, $this->accountant, $draft->fresh());
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rowFor(Document $advance, string $today): ?array
    {
        return collect($this->advances()->ageing($this->project, Carbon::parse($today))['rows'])->firstWhere('id', (string) $advance->id);
    }

    private function hasAlert(string $today): bool
    {
        return collect(app(Dashboard::class)->dashboard($this->project, Carbon::parse($today))['alerts'])->contains('code', Dashboard::ALERT_OVERDUE_ADVANCES);
    }

    private function advances(): TreasuryAdvanceService
    {
        return app(TreasuryAdvanceService::class);
    }

    private function api(User $user): static
    {
        return $this->actingAsSanctumBearerToken($user)->withHeaders(['Accept' => 'application/json', 'X-Tenant-ID' => (string) $user->tenant_id]);
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
