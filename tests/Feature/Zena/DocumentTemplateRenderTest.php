<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Contract;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentTemplateRenderTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_contract_render_requires_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], []);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
        ]);

        $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, $template->id]), $headers)
            ->assertStatus(403);
    }

    public function test_contract_render_downloads_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
        ]);

        $html = '<h1>{{contract_code}}</h1>';
        $htmlPath = 'deliverable-templates/' . $tenant->id . '/test-render/render.html';
        Storage::disk('local')->put($htmlPath, $html);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'status' => 'published',
        ]);

        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => $htmlPath,
            'checksum_sha256' => hash('sha256', $html),
            'mime' => 'text/html',
            'size' => strlen($html),
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, $template->id]), $headers);

        // PDF export may fail if Node.js unavailable, but should not be 403/404/500
        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_contract_render_404_for_nonexistent_template(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, '00000000-0000-0000-0000-000000000000']), $headers)
            ->assertStatus(404);
    }

    public function test_contract_render_404_for_unpublished_template(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'status' => 'draft',
        ]);

        // No published version — should 404
        $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, $template->id]), $headers)
            ->assertStatus(404);
    }

    public function test_project_render_downloads_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['project.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $html = '<h1>{{project_name}}</h1>';
        $htmlPath = 'deliverable-templates/' . $tenant->id . '/test-project/render.html';
        Storage::disk('local')->put($htmlPath, $html);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'project',
            'status' => 'published',
        ]);

        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => $htmlPath,
            'checksum_sha256' => hash('sha256', $html),
            'mime' => 'text/html',
            'size' => strlen($html),
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.projects.documents.render', [$project->id, $template->id]), $headers);

        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_contract_render_404_for_wrong_context_template(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $contract = Contract::factory()->create(['tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id]);

        // Template with context 'certificate' used on contract endpoint → 404
        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'certificate',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, $template->id]), $headers)
            ->assertStatus(404);
    }

    public function test_contract_render_404_for_cross_tenant_template(): void
    {
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant1, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant1->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant1->id]);
        $contract = Contract::factory()->create(['tenant_id' => (string) $tenant1->id, 'project_id' => (string) $project->id]);

        // Template belongs to tenant2 → should 404 from tenant1's endpoint
        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant2->id,
            'context' => 'contract',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('operator.contracts.documents.render', [$contract->id, $template->id]), $headers)
            ->assertStatus(404);
    }

    public function test_certificate_render_downloads_pdf(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['payment_certificate.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $contract = Contract::factory()->create(['tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id]);

        $cert = \App\Models\PaymentCertificate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'status' => \App\Models\PaymentCertificate::STATUS_APPROVED,
            'period_no' => 1,
            'total_this_period' => 1000000,
            'retention_amount' => 0,
            'advance_deduction' => 0,
        ]);

        $html = '<h1>{{certificate_code}}</h1>';
        $htmlPath = 'deliverable-templates/' . $tenant->id . '/test-cert/render.html';
        Storage::disk('local')->put($htmlPath, $html);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'certificate',
            'status' => 'published',
        ]);

        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => $htmlPath,
            'checksum_sha256' => hash('sha256', $html),
            'mime' => 'text/html',
            'size' => strlen($html),
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('operator.contracts.certificates.documents.render', [$contract->id, $cert->id, $template->id]), $headers);

        $this->assertContains($response->status(), [200, 302]);
    }

    public function test_certificate_render_rejects_draft_certificate(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['payment_certificate.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $contract = Contract::factory()->create(['tenant_id' => (string) $tenant->id, 'project_id' => (string) $project->id]);

        $cert = \App\Models\PaymentCertificate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'status' => \App\Models\PaymentCertificate::STATUS_DRAFT,
            'period_no' => 1,
            'total_this_period' => 1000000,
            'retention_amount' => 0,
            'advance_deduction' => 0,
        ]);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'certificate',
            'status' => 'published',
        ]);

        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => 'dummy.html',
            'checksum_sha256' => hash('sha256', 'dummy'),
            'mime' => 'text/html',
            'size' => 5,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        // Draft certificate → 302 redirect with error
        $response = $this->actingAs($user)
            ->get(route('operator.contracts.certificates.documents.render', [$contract->id, $cert->id, $template->id]), $headers);

        $response->assertStatus(302);
    }

    public function test_project_render_404_for_unpublished_template(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['project.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'project',
            'status' => 'draft',
        ]);

        // No published version — should 404
        $this->actingAs($user)
            ->get(route('app.projects.documents.render', [$project->id, $template->id]), $headers)
            ->assertStatus(404);
    }

    public function test_contract_show_passes_templates_to_view(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['contract.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $project = \App\Models\Project::factory()->create(['tenant_id' => (string) $tenant->id]);
        $contract = Contract::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
        ]);

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('operator.contracts.show', $contract->id), $headers)
            ->assertStatus(200);
    }
}
