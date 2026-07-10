<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Contract;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class ContractPdfExportTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_contract_pdf_endpoint_streams_pdf_bytes(): void
    {
        $this->app->bind(DeliverablePdfExportService::class, function () {
            return new class extends DeliverablePdfExportService {
                public function render(string $html, array $options = [], array $documentMeta = []): string
                {
                    return '%PDF-1.4 fake-contract-pdf-bytes';
                }
            };
        });

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['admin'], ['contract.view']);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an pdf',
            'code' => 'PRJ-PDFTEST1',
            'status' => 'planning',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-PDFTEST1',
            'title' => 'Hop dong pdf test',
            'client_name' => 'Khach hang pdf',
            'total_value' => 123000000,
            'currency' => 'VND',
        ]);

        $response = $this->actingAs($user)->get(
            "/api/zena/projects/{$project->id}/contracts/{$contract->id}/pdf",
            ['X-Tenant-ID' => (string) $tenant->id]
        );

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('fake-contract-pdf-bytes', $response->getContent());
    }
}
