<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DeliverableTemplateMvpApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_tenant_isolation_anti_enumeration_for_deliverable_template_ids(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $actorA = $this->createTenantUser($tenantA, [], ['member'], ['template.view', 'template.publish']);
        $actorB = $this->createTenantUser($tenantB, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $templateB = $this->createTemplate($tenantB, $actorB);

        DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenantB->id,
            'deliverable_template_id' => (string) $templateB->id,
            'version' => 'draft',
            'semver' => 'draft',
            'storage_path' => 'deliverable-templates/' . $tenantB->id . '/' . $templateB->id . '/draft/source.html',
            'checksum_sha256' => hash('sha256', '<h1>B</h1>'),
            'mime' => 'text/html',
            'size' => 9,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'created_by' => (string) $actorB->id,
            'updated_by' => (string) $actorB->id,
        ]);

        $this->getJson('/api/zena/deliverable-templates/' . $templateB->id, $this->authHeaders($actorA))
            ->assertStatus(404);

        $this->getJson('/api/zena/deliverable-templates/' . $templateB->id . '/versions', $this->authHeaders($actorA))
            ->assertStatus(404);

        $this->postJson('/api/zena/deliverable-templates/' . $templateB->id . '/publish-version', [], $this->authHeaders($actorA))
            ->assertStatus(404);
    }

    public function test_rbac_denies_upload_and_publish_without_permissions(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);
        $viewer = $this->createTenantUser($tenant, [], ['viewer_no_template_edit'], ['template.view']);

        $template = $this->createTemplate($tenant, $owner);

        $this->withHeaders($this->authHeaders($viewer, false))
            ->post('/api/zena/deliverable-templates/' . $template->id . '/upload-version', [
                'file' => $this->htmlUpload('<html><body><h1>Draft</h1></body></html>', 'draft.html'),
            ])->assertStatus(403);

        $this->postJson('/api/zena/deliverable-templates/' . $template->id . '/publish-version', [], $this->authHeaders($viewer))
            ->assertStatus(403);
    }

    public function test_publish_immutability_keeps_existing_published_version_unchanged_after_new_upload(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $template = $this->createTemplate($tenant, $user);

        $htmlV1 = '<html><body><h1>v1</h1>{{project.name}}</body></html>';
        $uploadV1 = $this->withHeaders($this->authHeaders($user, false))
            ->post('/api/zena/deliverable-templates/' . $template->id . '/upload-version', [
                'file' => $this->htmlUpload($htmlV1, 'v1.html'),
            ]);
        $uploadV1->assertStatus(201);

        $publishV1 = $this->postJson('/api/zena/deliverable-templates/' . $template->id . '/publish-version', [], $this->authHeaders($user));
        $publishV1->assertStatus(201)
            ->assertJsonPath('data.semver', '1.0.0');

        $publishedV1Id = (string) $publishV1->json('data.id');
        $publishedV1Checksum = (string) $publishV1->json('data.checksum_sha256');

        $htmlV2 = '<html><body><h1>v2</h1>{{project.code}}</body></html>';
        $this->withHeaders($this->authHeaders($user, false))
            ->post('/api/zena/deliverable-templates/' . $template->id . '/upload-version', [
                'file' => $this->htmlUpload($htmlV2, 'v2.html'),
            ])->assertStatus(201);

        $publishV2 = $this->postJson('/api/zena/deliverable-templates/' . $template->id . '/publish-version', [], $this->authHeaders($user));
        $publishV2->assertStatus(201)
            ->assertJsonPath('data.semver', '1.0.1');

        $publishedV1 = DeliverableTemplateVersion::query()->findOrFail($publishedV1Id);
        $this->assertSame('1.0.0', $publishedV1->semver);
        $this->assertSame($publishedV1Checksum, $publishedV1->checksum_sha256);
        $this->assertNotSame((string) $publishV2->json('data.checksum_sha256'), $publishedV1Checksum);

        $this->assertDatabaseHas('deliverable_template_versions', [
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => (string) $template->id,
            'semver' => '1.0.0',
        ]);

        $this->assertDatabaseHas('deliverable_template_versions', [
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => (string) $template->id,
            'semver' => '1.0.1',
        ]);
    }

    public function test_create_upload_publish_write_audit_logs(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser($tenant, [], ['member'], ['template.view', 'template.edit_draft', 'template.publish']);

        $create = $this->postJson('/api/zena/deliverable-templates', [
            'code' => 'DT-' . substr((string) Str::ulid(), -8),
            'name' => 'Concrete Pour Checklist',
            'description' => 'MVP deliverable template',
        ], $this->authHeaders($user));

        $create->assertStatus(201);
        $templateId = (string) $create->json('data.id');

        $this->withHeaders($this->authHeaders($user, false))
            ->post('/api/zena/deliverable-templates/' . $templateId . '/upload-version', [
                'file' => $this->htmlUpload('<html><body>{{project.name}}</body></html>', 'audit.html'),
            ])->assertStatus(201);

        $this->postJson('/api/zena/deliverable-templates/' . $templateId . '/publish-version', [], $this->authHeaders($user))
            ->assertStatus(201);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.deliverable-template.create',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.deliverable-template.upload-version',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => (string) $tenant->id,
            'user_id' => (string) $user->id,
            'action' => 'zena.deliverable-template.publish-version',
        ]);
    }

    private function createTemplate(Tenant $tenant, User $user): DeliverableTemplate
    {
        return DeliverableTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'DT-' . substr((string) Str::ulid(), -8),
            'name' => 'Deliverable ' . substr((string) Str::ulid(), -6),
            'description' => 'Draft template',
            'status' => 'draft',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);
    }

    private function authHeaders(User $user, bool $asJson = true): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        $headers = [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];

        if ($asJson) {
            $headers['Content-Type'] = 'application/json';
        }

        return $headers;
    }

    private function htmlUpload(string $content, string $name = 'template.html'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }
}
