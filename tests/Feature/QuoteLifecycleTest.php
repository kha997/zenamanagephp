<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuoteLifecycleTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->get('/login');
    }

    private function makeOpportunity(Tenant $tenant, ?\App\Models\User $user = null): array
    {
        $user ??= $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => 'Test Account',
        ]);

        $opp = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Test Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        return ['tenant' => $tenant, 'user' => $user, 'opportunity' => $opp];
    }

    private function makeQuote(Tenant $tenant, Opportunity $opp, string $status = Quote::STATUS_DRAFT, array $lines = []): Quote
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $quote = Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opp->id),
            'status' => $status,
            'created_by' => (string) $user->id,
        ]);

        foreach ($lines as $i => $line) {
            QuoteLineItem::query()->create([
                'tenant_id' => (string) $tenant->id,
                'quote_id' => (string) $quote->id,
                'sort_order' => $i + 1,
                'name' => $line['name'] ?? "Item {$i}",
                'unit' => $line['unit'] ?? 'm2',
                'quantity' => $line['quantity'] ?? 10,
                'unit_price' => $line['unit_price'] ?? 100000,
                'amount' => ($line['quantity'] ?? 10) * ($line['unit_price'] ?? 100000),
                'price_note' => $line['price_note'] ?? null,
            ]);
        }

        return $quote;
    }

    public function test_store_creates_draft_quote(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $this->actingAs($user);

        $response = $this->post(route('operator.crm.opportunities.quotes.store', $opp->id));
        $response->assertRedirect();

        $this->assertDatabaseHas('quotes', [
            'opportunity_id' => (string) $opp->id,
            'status' => Quote::STATUS_DRAFT,
            'revision_no' => 1,
        ]);
    }

    public function test_save_lines_replaces_all_lines_and_recomputes_subtotal(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp);

        $lines = [
            ['name' => 'Son', 'unit' => 'm2', 'quantity' => 100, 'unit_price' => 200000, 'price_note' => 'Chau Au'],
            ['name' => 'Keo', 'unit' => 'kg', 'quantity' => 5, 'unit_price' => 1500000, 'price_note' => null],
        ];

        $this->post(route('operator.crm.quotes.lines.save', $quote->id), ['lines' => $lines]);

        $this->assertDatabaseCount('quote_line_items', 2);

        $dbQuote = Quote::find($quote->id);
        $this->assertEqualsWithDelta(27500000, $dbQuote->subtotal, 0.01);
    }

    public function test_send_transitions_to_sent_with_sent_at(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'A', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 50000],
        ]);

        $response = $this->post(route('operator.crm.quotes.send', $quote->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $dbQuote = Quote::find($quote->id);
        $this->assertSame(Quote::STATUS_SENT, $dbQuote->status);
        $this->assertNotNull($dbQuote->sent_at);

        $this->assertSame(1, EventRecord::query()
            ->where('aggregate_id', (string) $quote->id)
            ->where('event_key', 'quote.sent')
            ->count());
    }

    public function test_send_with_zero_lines_fails(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp);

        $this->post(route('operator.crm.quotes.send', $quote->id))->assertSessionHas('error');

        $dbQuote = Quote::find($quote->id);
        $this->assertSame(Quote::STATUS_DRAFT, $dbQuote->status);
    }

    public function test_accept_transitions_and_supersedes_others(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote1 = $this->makeQuote($tenant, $opp, Quote::STATUS_SENT, [
            ['name' => 'A', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 100000],
        ]);
        $quote2 = $this->makeQuote($tenant, $opp, Quote::STATUS_SENT, [
            ['name' => 'B', 'unit' => 'pcs', 'quantity' => 2, 'unit_price' => 50000],
        ]);

        // Update revision_no manually for quote2 to be different
        Quote::query()->where('id', $quote2->id)->update(['revision_no' => 2]);

        $response = $this->post(route('operator.crm.quotes.accept', $quote1->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame(Quote::STATUS_ACCEPTED, Quote::find($quote1->id)->status);
        $this->assertSame(Quote::STATUS_SUPERSEDED, Quote::find($quote2->id)->status);
        $this->assertNotNull(Quote::find($quote1->id)->decided_at);

        $this->assertSame(1, EventRecord::query()
            ->where('aggregate_id', (string) $quote1->id)
            ->where('event_key', 'quote.accepted')
            ->count());

        $event = EventRecord::query()
            ->where('aggregate_id', (string) $quote1->id)
            ->where('event_key', 'quote.accepted')
            ->first();
        $this->assertSame('operator', $event->payload['source']);
    }

    public function test_reject_transitions(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, Quote::STATUS_SENT, [
            ['name' => 'X', 'unit' => 'm', 'quantity' => 10, 'unit_price' => 10000],
        ]);

        $response = $this->post(route('operator.crm.quotes.reject', $quote->id));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $dbQuote = Quote::find($quote->id);
        $this->assertSame(Quote::STATUS_REJECTED, $dbQuote->status);
        $this->assertNotNull($dbQuote->decided_at);

        $this->assertSame(1, EventRecord::query()
            ->where('aggregate_id', (string) $quote->id)
            ->where('event_key', 'quote.rejected')
            ->count());
    }

    public function test_revise_creates_new_draft_with_copied_lines(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $original = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Alpha', 'unit' => 'm2', 'quantity' => 20, 'unit_price' => 300000, 'price_note' => 'Chau A'],
        ]);

        $this->post(route('operator.crm.quotes.revise', $original->id));

        $newQuote = Quote::query()
            ->where('opportunity_id', $opp->id)
            ->where('revision_no', 2)
            ->first();

        $this->assertNotNull($newQuote);
        $this->assertSame(Quote::STATUS_DRAFT, $newQuote->status);
        $this->assertSame(2, $newQuote->revision_no);
        $this->assertSame($original->notes, $newQuote->notes);

        $newLines = QuoteLineItem::query()->where('quote_id', $newQuote->id)->get();
        $this->assertCount(1, $newLines);
        $this->assertSame('Alpha', $newLines[0]->name);
        $this->assertSame('Chau A', $newLines[0]->price_note);
        $this->assertEqualsWithDelta(20 * 300000, $newLines[0]->amount, 0.01);
    }

    public function test_cannot_edit_lines_when_sent(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, Quote::STATUS_SENT, [
            ['name' => 'A', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 100],
        ]);

        $this->post(route('operator.crm.quotes.lines.save', $quote->id), [
            'lines' => [['name' => 'B', 'unit' => 'pcs', 'quantity' => 1, 'unit_price' => 200]],
        ])->assertSessionHas('error');
    }

    public function test_cannot_send_when_no_lines(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp);

        $this->post(route('operator.crm.quotes.send', $quote->id))->assertSessionHas('error');
    }

    public function test_cross_tenant_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        ['opportunity' => $opp] = $this->makeOpportunity($tenantA);
        $quote = $this->makeQuote($tenantA, $opp);

        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view', 'crm.manage']);
        $this->actingAs($userB);

        $this->get(route('operator.crm.quotes.show', $quote->id))->assertNotFound();
    }

    public function test_team_member_without_manage_cannot_mutate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['team_member'], ['crm.view']);
        $this->actingAs($user);

        $opp = Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (function () use ($tenant) {
                return (string) Account::query()->create(['tenant_id' => (string) $tenant->id, 'display_name' => 'X'])->id;
            })(),
            'opportunity_name' => 'Opp',
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);

        $this->post(route('operator.crm.opportunities.quotes.store', $opp->id))->assertForbidden();
    }

    public function test_quote_show_view_renders(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Viet Hoa', 'unit' => 'm2', 'quantity' => 100, 'unit_price' => 250000],
        ]);

        $response = $this->get(route('operator.crm.quotes.show', $quote->id));
        $response->assertStatus(200);
        $response->assertSee($quote->quote_number);
        $response->assertSee('Viet Hoa');
    }

    public function test_opportunity_show_view_has_native_quotes_card(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Keo', 'unit' => 'kg', 'quantity' => 5, 'unit_price' => 1500000],
        ]);

        $response = $this->get(route('operator.crm.opportunities.show', $opp->id));
        $response->assertStatus(200);
        $response->assertSee('Báo giá (native)');
        $response->assertSee($quote->quote_number);
    }

    // ─── Commercial breakdown view tests ─────────────────────────────

    public function test_quote_show_draft_renders_commercial_form(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Item A', 'unit' => 'm2', 'quantity' => 10, 'unit_price' => 100000],
        ]);

        $response = $this->get(route('operator.crm.quotes.show', $quote->id));
        $response->assertOk();
        $response->assertSee('Thông tin thương mại');
        $response->assertSee('Chiết khấu');
        $response->assertSee('VAT (%)');
        $response->assertSee('Điều khoản thanh toán');
    }

    public function test_quote_show_with_discount_and_vat_shows_breakdown(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Item A', 'unit' => 'm2', 'quantity' => 100, 'unit_price' => 250000],
        ]);

        // subtotal=25000000, discount 10%, vat 8%
        $totals = Quote::computeTotals(25000000, 10, 8);
        $quote->update(array_merge([
            'discount_percent' => 10,
            'vat_percent' => 8,
        ], $totals));

        $response = $this->get(route('operator.crm.quotes.show', $quote->id));
        $response->assertOk();
        $response->assertSee('Tạm tính');
        $response->assertSee('25.000.000');
        $response->assertSee('Chiết khấu');
        $response->assertSee('2.500.000');
        $response->assertSee('VAT');
        $response->assertSee('1.800.000');
        $response->assertSee('24.300.000');
    }

    public function test_quote_show_zero_discount_and_vat_hides_breakdown(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Item A', 'unit' => 'm2', 'quantity' => 10, 'unit_price' => 100000],
        ]);

        $response = $this->get(route('operator.crm.quotes.show', $quote->id));
        $response->assertOk();
        // No discount/VAT rows when both are 0
        $this->assertStringNotContainsString('Chiết khấu (0', $response->getContent());
        $this->assertStringNotContainsString('VAT (0', $response->getContent());
    }

    public function test_quote_show_with_payment_terms_displays_terms(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);
        $this->actingAs($user);

        $quote = $this->makeQuote($tenant, $opp, lines: [
            ['name' => 'Item A', 'unit' => 'm2', 'quantity' => 10, 'unit_price' => 100000],
        ]);
        $quote->update(['payment_terms' => 'Net 30']);

        $response = $this->get(route('operator.crm.quotes.show', $quote->id));
        $response->assertOk();
        $response->assertSee('Điều khoản thanh toán');
        $response->assertSee('Net 30');
    }
}
