<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\EventRecord;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalQuoteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;
    private Opportunity $opportunity;
    private Quote $sentQuote;
    private Quote $draftQuote;
    private User $creator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->get('/login');

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-portal-quotes']);
        $this->creator = User::factory()->create(['tenant_id' => (string) $this->tenant->id]);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang quote portal',
            'email' => 'quote-portal@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->opportunity = Opportunity::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Co hoi quote portal',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'created_by' => (string) $this->creator->id,
        ]);

        $this->sentQuote = Quote::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'opportunity_id' => (string) $this->opportunity->id,
            'quote_number' => 'BQ-PORTAL-001',
            'revision_no' => 1,
            'status' => Quote::STATUS_SENT,
            'subtotal' => 27500000,
            'created_by' => (string) $this->creator->id,
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'quote_id' => (string) $this->sentQuote->id,
            'name' => 'Thiet ke mat tien',
            'unit' => 'm2',
            'quantity' => 100,
            'unit_price' => 200000,
            'amount' => 20000000,
            'sort_order' => 1,
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'quote_id' => (string) $this->sentQuote->id,
            'name' => 'Thiet ke noi that',
            'unit' => 'bo',
            'quantity' => 5,
            'unit_price' => 1500000,
            'amount' => 7500000,
            'sort_order' => 2,
        ]);

        $this->draftQuote = Quote::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'opportunity_id' => (string) $this->opportunity->id,
            'quote_number' => 'BQ-PORTAL-002',
            'revision_no' => 2,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 5000000,
            'created_by' => (string) $this->creator->id,
        ]);

        $this->actingAs($this->account, 'client');
    }

    public function test_show_displays_quote_detail(): void
    {
        $response = $this->get(route('portal.quotes.show', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]));

        $response->assertOk();
        $response->assertSee('BQ-PORTAL-001');
        $response->assertSee('27.500.000');
        $response->assertSee('Bằng chữ');
        $response->assertSee('Chấp nhận báo giá');
        $response->assertDontSee('price_note');
    }

    public function test_accept_happy_path(): void
    {
        $response = $this->post(route('portal.quotes.accept', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->sentQuote->refresh();
        $this->assertSame(Quote::STATUS_ACCEPTED, $this->sentQuote->status);
        $this->assertNotNull($this->sentQuote->decided_at);

        $this->draftQuote->refresh();
        $this->assertSame(Quote::STATUS_SUPERSEDED, $this->draftQuote->status);

        $event = EventRecord::query()
            ->where('aggregate_id', (string) $this->sentQuote->id)
            ->where('event_key', 'quote.accepted')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('portal', $event->payload['source']);
        $this->assertSame((string) $this->account->id, $event->payload['actor_account_id']);

        $notification = Notification::query()
            ->where('user_id', (string) $this->creator->id)
            ->where('type', 'portal_client_action')
            ->first();
        $this->assertNotNull($notification);
        $this->assertStringContainsString('BQ-PORTAL-001', $notification->title);
    }

    public function test_reject_with_note(): void
    {
        $response = $this->post(route('portal.quotes.reject', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]), [
            'note' => 'Gia cao qua',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->sentQuote->refresh();
        $this->assertSame(Quote::STATUS_REJECTED, $this->sentQuote->status);

        $event = EventRecord::query()
            ->where('aggregate_id', (string) $this->sentQuote->id)
            ->where('event_key', 'quote.rejected')
            ->first();
        $this->assertNotNull($event);
        $this->assertSame('Gia cao qua', $event->payload['note']);
    }

    public function test_accept_already_accepted_returns_error(): void
    {
        $this->sentQuote->update(['status' => Quote::STATUS_ACCEPTED]);

        $response = $this->post(route('portal.quotes.accept', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('action');

        $this->sentQuote->refresh();
        $this->assertSame(Quote::STATUS_ACCEPTED, $this->sentQuote->status);
    }

    public function test_draft_quote_returns_404(): void
    {
        $this->get(route('portal.quotes.show', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->draftQuote->id,
        ]))->assertNotFound();
    }

    public function test_other_account_same_tenant_returns_404(): void
    {
        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang khac',
            'email' => 'other-quote@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($otherAccount, 'client');

        $this->get(route('portal.quotes.show', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]))->assertNotFound();
    }

    public function test_other_tenant_returns_redirect(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'zena-other-quote']);

        $this->get(route('portal.quotes.show', [
            'tenantSlug' => 'zena-other-quote',
            'id' => $this->sentQuote->id,
        ]))->assertRedirect();
    }

    public function test_not_logged_in_redirects_to_login(): void
    {
        auth()->guard('client')->logout();

        $this->get(route('portal.quotes.show', [
            'tenantSlug' => 'zena-portal-quotes',
            'id' => $this->sentQuote->id,
        ]))->assertRedirect();
    }
}
