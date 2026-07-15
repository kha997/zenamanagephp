<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class QuotePdfTest extends TestCase
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

        return ['tenant' => $tenant, 'user' => $user, 'opportunity' => $opp, 'account' => $account];
    }

    private function makeQuoteWithLines(Tenant $tenant, Opportunity $opp, string $status = Quote::STATUS_SENT): Quote
    {
        $user = $this->createTenantUser($tenant, [], ['admin'], ['crm.view', 'crm.manage']);

        $quote = Quote::query()->create([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opp->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => Quote::nextRevision((string) $opp->id),
            'status' => $status,
            'subtotal' => 27500000,
            'valid_until' => now()->addDays(30),
            'created_by' => (string) $user->id,
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 1,
            'code' => 'L001',
            'name' => 'Son hoa van',
            'unit' => 'm2',
            'quantity' => 100,
            'unit_price' => 200000,
            'amount' => 20000000,
            'price_note' => 'Chau Au',
        ]);

        QuoteLineItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'sort_order' => 2,
            'code' => 'L002',
            'name' => 'Keo dan',
            'unit' => 'kg',
            'quantity' => 5,
            'unit_price' => 1500000,
            'amount' => 7500000,
            'price_note' => null,
        ]);

        return $quote;
    }

    // ─── View render tests ───────────────────────────────────────────

    public function test_quote_pdf_view_renders_key_strings(): void
    {
        $tenant = Tenant::factory()->create();
        ['account' => $account, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $quote = $this->makeQuoteWithLines($tenant, $opp);

        $html = view('crm.quote-pdf', [
            'quote' => $quote,
            'lines' => $quote->lines()->get(),
            'account' => $account,
            'opportunity' => $opp,
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords((float) $quote->subtotal),
        ])->render();

        $this->assertStringContainsString('BẢNG BÁO GIÁ', $html);
        $this->assertStringContainsString($quote->quote_number, $html);
        $this->assertStringContainsString('Test Account', $html);
        $this->assertStringContainsString('Test Opp', $html);
        $this->assertStringContainsString('Son hoa van', $html);
        $this->assertStringContainsString('Keo dan', $html);
        $this->assertStringContainsString('27.500.000', $html);
        $this->assertStringContainsString('Bằng chữ', $html);
        $this->assertStringContainsString('Chau Au', $html);
    }

    public function test_draft_quote_pdf_shows_watermark(): void
    {
        $tenant = Tenant::factory()->create();
        ['account' => $account, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $quote = $this->makeQuoteWithLines($tenant, $opp, Quote::STATUS_DRAFT);

        $html = view('crm.quote-pdf', [
            'quote' => $quote,
            'lines' => $quote->lines()->get(),
            'account' => $account,
            'opportunity' => $opp,
            'amountInWords' => \App\Support\VietnameseMoneyWords::toWords((float) $quote->subtotal),
        ])->render();

        $this->assertStringContainsString('BẢN NHÁP', $html);
    }

    // ─── Endpoint tests ───────────────────────────────────────────────

    public function test_pdf_endpoint_returns_pdf(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    return '%PDF-1.4 fake-quote-pdf-bytes';
                }
            };
        });

        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $quote = $this->makeQuoteWithLines($tenant, $opp);

        $response = $this->actingAs($user)->get(
            route('operator.crm.quotes.pdf', $quote->id)
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fake-quote-pdf-bytes', $response->getContent());
    }

    public function test_cross_tenant_pdf_returns_404(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        ['user' => $userA, 'opportunity' => $opp] = $this->makeOpportunity($tenantA);
        $userB = $this->createTenantUser($tenantB, [], ['admin'], ['crm.view']);

        $quote = $this->makeQuoteWithLines($tenantA, $opp);

        $response = $this->actingAs($userB)->get(
            route('operator.crm.quotes.pdf', $quote->id)
        );

        $response->assertNotFound();
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

        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opp] = $this->makeOpportunity($tenant);

        $quote = $this->makeQuoteWithLines($tenant, $opp);

        $response = $this->actingAs($user)->get(
            route('operator.crm.quotes.pdf', $quote->id)
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }
}
