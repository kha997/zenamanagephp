<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Boq;
use App\Models\BoqLineItem;
use App\Models\Contract;
use App\Models\DesignItem;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentContext\ContractContextProvider;
use App\Services\DocumentContext\CertificateContextProvider;
use App\Services\DocumentContext\DocumentContextRegistry;
use App\Services\DocumentContext\ProjectContextProvider;
use App\Services\DocumentContext\QuoteContextProvider;
use App\Services\PaymentCertificateSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentContextProvidersTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private DocumentContextRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $contractProvider = new ContractContextProvider();
        $summaryService = new PaymentCertificateSummaryService();
        $certificateProvider = new CertificateContextProvider($contractProvider, $summaryService);
        $projectProvider = new ProjectContextProvider();
        $quoteProvider = new QuoteContextProvider();

        $this->registry = new DocumentContextRegistry([
            $contractProvider,
            $certificateProvider,
            $projectProvider,
            $quoteProvider,
        ]);
    }

    public function test_registry_returns_correct_providers(): void
    {
        $this->assertInstanceOf(ContractContextProvider::class, $this->registry->get('contract'));
        $this->assertInstanceOf(CertificateContextProvider::class, $this->registry->get('certificate'));
        $this->assertInstanceOf(ProjectContextProvider::class, $this->registry->get('project'));
        $this->assertInstanceOf(QuoteContextProvider::class, $this->registry->get('quote'));
        $this->assertCount(4, $this->registry->all());
    }

    public function test_registry_throws_for_unknown_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown document context: unknown');

        $this->registry->get('unknown');
    }

    public function test_contract_provider_keys_and_sample_coverage(): void
    {
        $provider = new ContractContextProvider();

        $this->assertSame('contract', $provider->slug());
        $this->assertSame('Hợp đồng', $provider->label());

        $keys = $provider->keys();
        $sample = $provider->sample();

        $this->assertNotEmpty($keys);
        $this->assertNotEmpty($sample);

        $sampleKeys = array_keys($sample);
        foreach ($keys as $keyDef) {
            $this->assertContains($keyDef['key'], $sampleKeys, "Sample missing key: {$keyDef['key']}");
        }

        // Verify specific keys
        $keyNames = array_column($keys, 'key');
        $this->assertContains('contract_code', $keyNames);
        $this->assertContains('total_value_words', $keyNames);
        $this->assertContains('boq_table_html', $keyNames);
    }

    public function test_contract_provider_build_from_real_seed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Golden Palace',
            'code' => 'GP-2024',
        ]);

        $boq = Boq::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => null, // Will set after contract creation
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'HD-2024-001',
            'title' => 'Hợp đồng thi công Golden Palace',
            'contract_type' => 'construction',
            'client_name' => 'Công ty Golden',
            'total_value' => 15000000000,
            'currency' => 'VND',
            'signed_at' => '2024-01-15',
            'start_date' => '2024-02-01',
            'end_date' => '2024-12-31',
        ]);

        // Update BOQ with contract_id
        $boq->update(['contract_id' => (string) $contract->id]);

        BoqLineItem::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'boq_id' => (string) $boq->id,
            'code' => 'A.01',
            'name' => 'Đào đất móng',
            'quantity' => 500,
            'unit' => 'm3',
            'unit_price' => 150000,
        ]);

        $provider = new ContractContextProvider();
        $context = $provider->build($contract);

        $this->assertSame('HD-2024-001', $context['contract_code']);
        $this->assertSame('Hợp đồng thi công Golden Palace', $context['contract_title']);
        $this->assertSame('Thi công', $context['contract_type_label']);
        $this->assertSame('Công ty Golden', $context['client_name']);
        $this->assertSame('15,000,000,000.00', $context['total_value']);
        $this->assertNotEmpty($context['total_value_words']);
        $this->assertSame('VND', $context['currency']);
        $this->assertSame('15/01/2024', $context['signed_at']);
        $this->assertSame('01/02/2024', $context['start_date']);
        $this->assertSame('31/12/2024', $context['end_date']);
        $this->assertSame('Golden Palace', $context['project_name']);
        $this->assertSame('GP-2024', $context['project_code']);
        $this->assertNotEmpty($context['today']);

        // Verify boq_table_html contains the line item
        $this->assertStringContainsString('Đào đất móng', $context['boq_table_html']);
        $this->assertStringContainsString('<table', $context['boq_table_html']);
    }

    public function test_certificate_provider_keys_and_sample_coverage(): void
    {
        $contractProvider = new ContractContextProvider();
        $summaryService = new PaymentCertificateSummaryService();
        $provider = new CertificateContextProvider($contractProvider, $summaryService);

        $this->assertSame('certificate', $provider->slug());
        $this->assertSame('Chứng chỉ nghiệm thu', $provider->label());

        $keys = $provider->keys();
        $sample = $provider->sample();

        $this->assertNotEmpty($keys);
        $this->assertNotEmpty($sample);

        $sampleKeys = array_keys($sample);
        foreach ($keys as $keyDef) {
            $this->assertContains($keyDef['key'], $sampleKeys, "Sample missing key: {$keyDef['key']}");
        }

        // Verify certificate-specific keys
        $keyNames = array_column($keys, 'key');
        $this->assertContains('period_no', $keyNames);
        $this->assertContains('net_payable_words', $keyNames);
        $this->assertContains('lines_table_html', $keyNames);

        // Verify contract keys are included
        $this->assertContains('contract_code', $keyNames);
        $this->assertContains('boq_table_html', $keyNames);
    }

    public function test_certificate_provider_composes_contract_provider(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member']);
        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Golden Palace',
            'code' => 'GP-2024',
        ]);

        $boq = Boq::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => null,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'HD-2024-001',
            'title' => 'Hợp đồng thi công',
            'client_name' => 'Công ty Golden',
            'total_value' => 15000000000,
            'currency' => 'VND',
        ]);

        $boq->update(['contract_id' => (string) $contract->id]);

        $boqLineItem = BoqLineItem::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'boq_id' => (string) $boq->id,
            'code' => 'A.01',
            'name' => 'Đào đất móng',
            'quantity' => 500,
            'unit' => 'm3',
            'unit_price' => 150000,
        ]);

        $certificate = PaymentCertificate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'period_no' => 3,
            'period_from' => '2024-06-01',
            'period_to' => '2024-06-30',
            'total_this_period' => 2500000000,
            'retention_amount' => 250000000,
            'advance_deduction' => 125000000,
            'net_payable' => 2125000000,
            'status' => 'approved',
            'approved_at' => '2024-07-05',
        ]);

        PaymentCertificateLine::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'payment_certificate_id' => (string) $certificate->id,
            'boq_line_item_id' => (string) $boqLineItem->id,
            'qty_this_period' => 200,
            'amount_this_period' => 30000000,
        ]);

        $contractProvider = new ContractContextProvider();
        $summaryService = new PaymentCertificateSummaryService();
        $provider = new CertificateContextProvider($contractProvider, $summaryService);

        $context = $provider->build($certificate);

        // Verify contract keys are included (composition)
        $this->assertSame('HD-2024-001', $context['contract_code']);
        $this->assertSame('Hợp đồng thi công', $context['contract_title']);

        // Verify certificate-specific keys
        $this->assertSame('3', $context['period_no']);
        $this->assertSame('01/06/2024', $context['period_from']);
        $this->assertSame('30/06/2024', $context['period_to']);
        $this->assertSame('2,500,000,000.00', $context['total_this_period']);
        $this->assertSame('2,125,000,000.00', $context['net_payable']);
        $this->assertNotEmpty($context['net_payable_words']);

        // Verify lines_table_html contains the line item
        $this->assertStringContainsString('Đào đất móng', $context['lines_table_html']);
        $this->assertStringContainsString('<table', $context['lines_table_html']);
    }

    public function test_project_provider_keys_and_sample_coverage(): void
    {
        $provider = new ProjectContextProvider();

        $this->assertSame('project', $provider->slug());
        $this->assertSame('Dự án', $provider->label());

        $keys = $provider->keys();
        $sample = $provider->sample();

        $this->assertNotEmpty($keys);
        $this->assertNotEmpty($sample);

        $sampleKeys = array_keys($sample);
        foreach ($keys as $keyDef) {
            $this->assertContains($keyDef['key'], $sampleKeys, "Sample missing key: {$keyDef['key']}");
        }

        // Verify specific keys
        $keyNames = array_column($keys, 'key');
        $this->assertContains('project_name', $keyNames);
        $this->assertContains('project_code', $keyNames);
        $this->assertContains('design_items_table_html', $keyNames);
    }

    public function test_project_provider_build_from_real_seed(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member']);

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Golden Palace',
            'code' => 'GP-2024',
            'status' => 'in_progress',
            'manager_id' => (string) $user->id,
        ]);

        DesignItem::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Mặt bằng tổng thể',
            'item_type' => 'schematic',
            'review_status' => 'approved',
            'revision_count' => 3,
        ]);

        DesignItem::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Mặt bằng tầng',
            'item_type' => 'technical',
            'review_status' => 'pending',
            'revision_count' => 2,
        ]);

        $provider = new ProjectContextProvider();
        $context = $provider->build($project);

        $this->assertSame('Golden Palace', $context['project_name']);
        $this->assertSame('GP-2024', $context['project_code']);
        $this->assertSame('Đang thi công', $context['project_status']);
        $this->assertNotEmpty($context['today']);

        // Verify design_items_table_html contains the design items
        $this->assertStringContainsString('Mặt bằng tổng thể', $context['design_items_table_html']);
        $this->assertStringContainsString('Mặt bằng tầng', $context['design_items_table_html']);
        $this->assertStringContainsString('<table', $context['design_items_table_html']);
    }

    public function test_sample_returns_literal_array_without_db(): void
    {
        $providers = [
            new ContractContextProvider(),
            new CertificateContextProvider(new ContractContextProvider(), new PaymentCertificateSummaryService()),
            new ProjectContextProvider(),
            new QuoteContextProvider(),
        ];

        foreach ($providers as $provider) {
            $sample = $provider->sample();
            $this->assertIsArray($sample);
            $this->assertNotEmpty($sample);

            // Verify all values are scalars or strings (no Eloquent models)
            foreach ($sample as $key => $value) {
                $this->assertTrue(
                    is_string($value) || is_int($value) || is_float($value) || is_bool($value),
                    "Sample value for {$key} should be scalar, got " . get_debug_type($value)
                );
            }
        }
    }
}
