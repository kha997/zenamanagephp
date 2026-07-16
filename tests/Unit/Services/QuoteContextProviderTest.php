<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Quote;
use App\Models\QuoteLineItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentContext\ContractContextProvider;
use App\Services\DocumentContext\CertificateContextProvider;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\DocumentContext\ProjectContextProvider;
use App\Services\DocumentContext\QuoteContextProvider;
use App\Services\PaymentCertificateSummaryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteContextProviderTest extends TestCase
{
    use RefreshDatabase;

    private QuoteContextProvider $provider;
    private DocumentContextRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new QuoteContextProvider();

        $contractProvider = new ContractContextProvider();
        $summaryService = new PaymentCertificateSummaryService();
        $certificateProvider = new CertificateContextProvider($contractProvider, $summaryService);
        $projectProvider = new ProjectContextProvider();

        $this->registry = new DocumentContextRegistry([
            $contractProvider,
            $certificateProvider,
            $projectProvider,
            $this->provider,
        ]);
    }

    /**
     * Build a Quote with full relationship chain: Tenant → User → Account → Opportunity → Quote.
     * Account and Opportunity have no factories, so created via Model::create().
     *
     * @param array<string, mixed> $quoteOverrides
     * @return Quote
     */
    private function createQuoteWithRelations(array $quoteOverrides = []): Quote
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->for($tenant)->create();

        $account = Account::create([
            'tenant_id' => (string) $tenant->id,
            'display_name' => $quoteOverrides['_account_name'] ?? 'Công ty TNHH ABC',
            'account_type' => Account::TYPE_COMPANY,
            'status' => Account::STATUS_ACTIVE,
        ]);
        unset($quoteOverrides['_account_name']);

        $opportunity = Opportunity::create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => $quoteOverrides['_opportunity_name'] ?? 'Dự án cải tạo',
            'pipeline_stage' => Opportunity::STAGE_PROPOSAL_SENT,
            'sales_owner_id' => (string) $user->id,
            'created_by' => (string) $user->id,
        ]);
        unset($quoteOverrides['_opportunity_name']);

        $quote = Quote::create(array_merge([
            'tenant_id' => (string) $tenant->id,
            'opportunity_id' => (string) $opportunity->id,
            'created_by' => (string) $user->id,
            'quote_number' => Quote::nextNumber((string) $tenant->id),
            'revision_no' => 1,
            'status' => Quote::STATUS_DRAFT,
            'subtotal' => 0,
        ], $quoteOverrides));

        return $quote;
    }

    public function test_is_registered_with_slug(): void
    {
        $this->assertSame('quote', $this->provider->slug());
        $this->assertSame($this->provider, $this->registry->get('quote'));
    }

    public function test_build_from_quote_model(): void
    {
        $quote = $this->createQuoteWithRelations([
            'quote_number' => 'BG-2026-0001',
            'revision_no' => 2,
            'status' => 'sent',
            'valid_until' => '2026-12-31',
            'subtotal' => 27500000,
            'discount_percent' => 10,
            'discount_amount' => 2750000,
            'vat_percent' => 8,
            'vat_amount' => 1980000,
            'total' => 26730000,
            'payment_terms' => '50% tạm ứng',
        ]);

        $tenant = Tenant::first();
        QuoteLineItem::create([
            'tenant_id' => (string) $tenant->id,
            'quote_id' => (string) $quote->id,
            'code' => 'L001',
            'name' => 'Sơn',
            'unit' => 'm2',
            'quantity' => 100,
            'unit_price' => 200000,
            'amount' => 20000000,
            'sort_order' => 1,
        ]);

        $context = $this->provider->build($quote);

        $this->assertSame('BG-2026-0001', $context['quote_number']);
        $this->assertSame('2', $context['revision_no']);
        $this->assertSame('Đã gửi', $context['status_label']);
        $this->assertSame('Công ty TNHH ABC', $context['account_name']);
        $this->assertSame('Dự án cải tạo', $context['opportunity_name']);
        $this->assertSame('31/12/2026', $context['valid_until']);
        $this->assertSame('27,500,000.00', $context['subtotal']);
        $this->assertSame('10.00', $context['discount_percent']);
        $this->assertSame('2,750,000.00', $context['discount_amount']);
        $this->assertSame('8.00', $context['vat_percent']);
        $this->assertSame('1,980,000.00', $context['vat_amount']);
        $this->assertSame('26,730,000.00', $context['total']);
        $this->assertNotEmpty($context['total_words']);
        $this->assertSame('50% tạm ứng', $context['payment_terms']);
        $this->assertNotEmpty($context['today']);
        $this->assertNotEmpty($context['lines_table_html']);
        $this->assertStringContainsString('Sơn', $context['lines_table_html']);
        $this->assertStringContainsString('L001', $context['lines_table_html']);
    }

    public function test_sample_returns_all_keys_without_database(): void
    {
        $sample = $this->provider->sample();

        $this->assertArrayHasKey('quote_number', $sample);
        $this->assertArrayHasKey('revision_no', $sample);
        $this->assertArrayHasKey('status_label', $sample);
        $this->assertArrayHasKey('account_name', $sample);
        $this->assertArrayHasKey('opportunity_name', $sample);
        $this->assertArrayHasKey('valid_until', $sample);
        $this->assertArrayHasKey('subtotal', $sample);
        $this->assertArrayHasKey('discount_percent', $sample);
        $this->assertArrayHasKey('discount_amount', $sample);
        $this->assertArrayHasKey('vat_percent', $sample);
        $this->assertArrayHasKey('vat_amount', $sample);
        $this->assertArrayHasKey('total', $sample);
        $this->assertArrayHasKey('total_words', $sample);
        $this->assertArrayHasKey('payment_terms', $sample);
        $this->assertArrayHasKey('today', $sample);
        $this->assertArrayHasKey('lines_table_html', $sample);
    }

    public function test_keys_match_sample_keys(): void
    {
        $keys = array_column($this->provider->keys(), 'key');
        $sampleKeys = array_keys($this->provider->sample());

        $this->assertSame($keys, $sampleKeys);
    }

    public function test_empty_lines_renders_placeholder(): void
    {
        $quote = $this->createQuoteWithRelations();

        $context = $this->provider->build($quote);

        $this->assertStringContainsString('Chưa có dòng báo giá', $context['lines_table_html']);
    }

    public function test_draft_status_label(): void
    {
        $sample = $this->provider->sample();

        $this->assertSame('Đã gửi', $sample['status_label']);
    }

    public function test_provider_returns_html_for_lines_table(): void
    {
        $sample = $this->provider->sample();

        $this->assertStringContainsString('<table', $sample['lines_table_html']);
        $this->assertStringContainsString('STT', $sample['lines_table_html']);
        $this->assertStringContainsString('Mã', $sample['lines_table_html']);
        $this->assertStringContainsString('Hạng mục', $sample['lines_table_html']);
        $this->assertStringContainsString('Đơn vị', $sample['lines_table_html']);
        $this->assertStringContainsString('Khối lượng', $sample['lines_table_html']);
        $this->assertStringContainsString('Đơn giá', $sample['lines_table_html']);
        $this->assertStringContainsString('Thành tiền', $sample['lines_table_html']);
    }

    public function test_all_status_labels(): void
    {
        $cases = [
            'draft' => 'Nháp',
            'sent' => 'Đã gửi',
            'accepted' => 'Đã chấp nhận',
            'rejected' => 'Đã từ chối',
            'superseded' => 'Đã thay thế',
        ];

        foreach ($cases as $status => $expected) {
            $quote = $this->createQuoteWithRelations(['status' => $status]);

            $context = $this->provider->build($quote);

            $this->assertSame($expected, $context['status_label'], "Status '$status' should map to '$expected'");
        }
    }
}
