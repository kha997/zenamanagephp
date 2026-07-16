<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentTemplateLibraryTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        // Khởi tạo session để TestCase tự chèn được _token CSRF vào các POST form.
        $this->get('/login');
    }

    public function test_index_requires_document_template_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], []);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->actingAs($user)
            ->get(route('operator.document-templates.index'), $headers)
            ->assertStatus(403);
    }

    public function test_index_lists_templates_for_tenant(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.view']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Biểu mẫu hợp đồng',
            'context' => 'contract',
        ]);

        $this->actingAs($user)
            ->get(route('operator.document-templates.index'), $headers)
            ->assertOk()
            ->assertSee('Biểu mẫu hợp đồng');
    }

    public function test_create_form_shows_context_options(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->actingAs($user)
            ->get(route('operator.document-templates.create'), $headers)
            ->assertOk()
            ->assertSee('contract')
            ->assertSee('certificate')
            ->assertSee('project');
    }

    public function test_store_creates_template_with_draft_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $this->actingAs($user)
            ->post(route('operator.document-templates.store'), [
                'name' => 'Giấy chứng nhận',
                'context' => 'certificate',
                'html_body' => '<h1>{{contract_title}}</h1><p>{{period_no}}</p>',
            ], $headers)
            ->assertRedirect();

        $template = DeliverableTemplate::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('name', 'Giấy chứng nhận')
            ->first();

        $this->assertNotNull($template);
        $this->assertSame('certificate', $template->context);
        $this->assertSame('draft', $template->status);

        $draft = DeliverableTemplateVersion::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', 'draft')
            ->first();

        $this->assertNotNull($draft);
        $this->assertNotNull($draft->storage_path);
        $this->assertNotNull($draft->checksum_sha256);
    }

    public function test_update_creates_new_draft_version(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        // Create initial draft
        $v1Path = 'deliverable-templates/' . $tenant->id . '/' . $template->id . '/draft/initial.html';
        Storage::disk('local')->put($v1Path, '<h1>Original</h1>');
        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => $v1Path,
            'checksum_sha256' => hash('sha256', '<h1>Original</h1>'),
            'mime' => 'text/html',
            'size' => 18,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('operator.document-templates.update', $template->id), [
                'name' => 'Updated name',
                'html_body' => '<h1>{{contract_code}}</h1><p>Updated</p>',
            ], $headers)
            ->assertRedirect();

        $draft = DeliverableTemplateVersion::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', 'draft')
            ->first();

        $this->assertNotNull($draft);
        $this->assertNotSame($v1Path, $draft->storage_path);

        $html = Storage::disk('local')->get($draft->storage_path);
        $this->assertStringContainsString('Updated', $html);
    }

    public function test_preview_renders_with_sample_data(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.view', 'document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $htmlPath = 'deliverable-templates/' . $tenant->id . '/' . $template->id . '/draft/preview.html';
        Storage::disk('local')->put($htmlPath, '<h1>{{contract_title}}</h1>');
        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => $htmlPath,
            'checksum_sha256' => hash('sha256', '<h1>{{contract_title}}</h1>'),
            'mime' => 'text/html',
            'size' => 33,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $response = $this->actingAs($user)
            ->post(route('operator.document-templates.preview', $template->id), [], $headers);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');

        $content = $response->getContent();
        $this->assertNotEmpty($content);
        // Should contain sample contract title, not the raw placeholder
        $this->assertStringNotContainsString('{{contract_title}}', $content);
    }

    public function test_publish_creates_versioned_release(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'project',
            'status' => 'draft',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $htmlPath = 'deliverable-templates/' . $tenant->id . '/' . $template->id . '/draft/publish.html';
        Storage::disk('local')->put($htmlPath, '<h1>{{project_name}}</h1>');
        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => $template->id,
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => $htmlPath,
            'checksum_sha256' => hash('sha256', '<h1>{{project_name}}</h1>'),
            'mime' => 'text/html',
            'size' => 30,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('operator.document-templates.publish', $template->id), [], $headers)
            ->assertRedirect();

        $published = DeliverableTemplateVersion::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('deliverable_template_id', $template->id)
            ->where('semver', '1.0.0')
            ->whereNotNull('published_at')
            ->first();

        $this->assertNotNull($published);
        $this->assertSame($htmlPath, $published->storage_path);

        $template->refresh();
        $this->assertSame('published', $template->status);
    }

    public function test_publish_fails_without_draft(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['document_template.manage']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        $template = DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'context' => 'contract',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('operator.document-templates.publish', $template->id), [], $headers)
            ->assertSessionHas('error');
    }
}
