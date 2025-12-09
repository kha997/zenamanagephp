<?php declare(strict_types=1);

namespace Tests\Feature\Api\V1\App;

use App\Models\ChangeOrder;
use App\Models\ChangeOrderLine;
use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\ContractPaymentCertificate;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PdfExportApiTest
 * 
 * Round 228: PDF Export for Contracts, COs, and Payment Certificates
 * 
 * Tests PDF export endpoints for contracts, change orders, and payment certificates
 */
class PdfExportApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private string $tenantId;
    private Project $project;
    private Contract $contract;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant and user
        $this->tenantId = 'tenant-' . uniqid();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenantId,
        ]);

        // Create project
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenantId,
            'name' => 'Test Project',
        ]);

        // Create contract with lines
        $this->contract = Contract::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'code' => 'CT-001',
            'name' => 'Test Contract',
            'party_name' => 'Test Contractor',
            'base_amount' => 100000.00,
            'currency' => 'VND',
        ]);

        ContractLine::factory()->create([
            'tenant_id' => $this->tenantId,
            'contract_id' => $this->contract->id,
            'project_id' => $this->project->id,
            'item_code' => 'ITEM-001',
            'description' => 'Test Item',
            'quantity' => 10.00,
            'unit_price' => 10000.00,
            'amount' => 100000.00,
        ]);
    }

    /**
     * Test export contract PDF returns HTTP 200 with proper headers
     */
    public function test_export_contract_pdf_returns_200_with_proper_headers(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/export/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        
        // Check PDF content contains expected strings
        $content = $response->getContent();
        $this->assertStringContainsString('CONTRACT SUMMARY', $content);
        $this->assertStringContainsString($this->project->name, $content);
        $this->assertStringContainsString($this->contract->code, $content);
    }

    /**
     * Test export change order PDF returns HTTP 200 with PDF headers
     */
    public function test_export_change_order_pdf_returns_200_with_pdf_headers(): void
    {
        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-001',
            'title' => 'Test Change Order',
            'amount_delta' => 5000.00,
        ]);

        ChangeOrderLine::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'change_order_id' => $changeOrder->id,
            'description' => 'Test CO Line',
            'amount_delta' => 5000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/export/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        
        // Check PDF content contains expected strings
        $content = $response->getContent();
        $this->assertStringContainsString('CHANGE ORDER', $content);
        $this->assertStringContainsString($this->project->name, $content);
        $this->assertStringContainsString($changeOrder->code, $content);
    }

    /**
     * Test export payment certificate PDF returns HTTP 200
     */
    public function test_export_payment_certificate_pdf_returns_200(): void
    {
        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'PC-001',
            'title' => 'Test Payment Certificate',
            'amount_before_retention' => 50000.00,
            'retention_amount' => 5000.00,
            'amount_payable' => 45000.00,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$certificate->id}/export/pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        
        // Check PDF content contains expected strings
        $content = $response->getContent();
        $this->assertStringContainsString('PAYMENT CERTIFICATE', $content);
        $this->assertStringContainsString($this->project->name, $content);
        $this->assertStringContainsString($certificate->code, $content);
    }

    /**
     * Test tenant isolation - contract belonging to another tenant returns 404
     */
    public function test_tenant_isolation_contract_belongs_to_another_tenant_returns_404(): void
    {
        $otherTenantId = 'other-tenant-' . uniqid();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenantId,
            'project_id' => $otherProject->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$otherProject->id}/contracts/{$otherContract->id}/export/pdf");

        $response->assertStatus(404);
    }

    /**
     * Test tenant isolation - change order belonging to wrong contract returns 404
     */
    public function test_tenant_isolation_change_order_belongs_to_wrong_contract_returns_404(): void
    {
        $otherTenantId = 'other-tenant-' . uniqid();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenantId,
            'project_id' => $otherProject->id,
        ]);
        $otherChangeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $otherTenantId,
            'project_id' => $otherProject->id,
            'contract_id' => $otherContract->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$otherProject->id}/contracts/{$otherContract->id}/change-orders/{$otherChangeOrder->id}/export/pdf");

        $response->assertStatus(404);
    }

    /**
     * Test tenant isolation - certificate belonging to wrong contract returns 404
     */
    public function test_tenant_isolation_certificate_belongs_to_wrong_contract_returns_404(): void
    {
        $otherTenantId = 'other-tenant-' . uniqid();
        $otherProject = Project::factory()->create([
            'tenant_id' => $otherTenantId,
        ]);
        $otherContract = Contract::factory()->create([
            'tenant_id' => $otherTenantId,
            'project_id' => $otherProject->id,
        ]);
        $otherCertificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $otherTenantId,
            'project_id' => $otherProject->id,
            'contract_id' => $otherContract->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$otherProject->id}/contracts/{$otherContract->id}/payment-certificates/{$otherCertificate->id}/export/pdf");

        $response->assertStatus(404);
    }

    /**
     * Test PDF content contains project name
     */
    public function test_pdf_content_contains_project_name(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/export/pdf");

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringContainsString($this->project->name, $content);
    }

    /**
     * Test PDF content contains entity codes
     */
    public function test_pdf_content_contains_entity_codes(): void
    {
        // Test contract code
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/export/pdf");
        $response->assertStatus(200);
        $this->assertStringContainsString($this->contract->code, $response->getContent());

        // Test change order code
        $changeOrder = ChangeOrder::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'CO-002',
        ]);
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/change-orders/{$changeOrder->id}/export/pdf");
        $response->assertStatus(200);
        $this->assertStringContainsString($changeOrder->code, $response->getContent());

        // Test certificate code
        $certificate = ContractPaymentCertificate::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'contract_id' => $this->contract->id,
            'code' => 'PC-002',
        ]);
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$this->contract->id}/payment-certificates/{$certificate->id}/export/pdf");
        $response->assertStatus(200);
        $this->assertStringContainsString($certificate->code, $response->getContent());
    }

    /**
     * Test large contracts still generate within timeout
     */
    public function test_large_contracts_generate_within_timeout(): void
    {
        // Create contract with many lines
        $largeContract = Contract::factory()->create([
            'tenant_id' => $this->tenantId,
            'project_id' => $this->project->id,
            'code' => 'CT-LARGE',
            'name' => 'Large Contract',
        ]);

        // Create 50 lines
        for ($i = 1; $i <= 50; $i++) {
            ContractLine::factory()->create([
                'tenant_id' => $this->tenantId,
                'contract_id' => $largeContract->id,
                'project_id' => $this->project->id,
                'item_code' => "ITEM-{$i}",
                'description' => "Item {$i}",
                'quantity' => 1.00,
                'unit_price' => 1000.00,
                'amount' => 1000.00,
            ]);
        }

        $startTime = microtime(true);
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/app/projects/{$this->project->id}/contracts/{$largeContract->id}/export/pdf");
        $endTime = microtime(true);

        $response->assertStatus(200);
        $elapsed = $endTime - $startTime;
        
        // Should complete within 10 seconds
        $this->assertLessThan(10, $elapsed, "PDF generation took {$elapsed} seconds, expected less than 10 seconds");
    }
}
