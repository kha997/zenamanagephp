<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\AuditLog;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceFieldValue;
use App\Models\WorkInstanceStep;
use App\Models\WorkInstanceStepAttachment;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;
use ZipArchive;

class WorkInstanceExportBundleApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_export_bundle_returns_401_when_unauthenticated(): void
    {
        [, , $instance, $version] = $this->seedBundleScenario(['work.export'], ['template.view']);

        $this->postJson('/api/zena/work-instances/' . $instance->id . '/export-bundle', [
            'deliverable_template_version_id' => (string) $version->id,
        ], ['X-Tenant-ID' => (string) $instance->tenant_id])
            ->assertStatus(401);
    }

    public function test_export_bundle_returns_403_without_permission(): void
    {
        [, $user, $instance, $version] = $this->seedBundleScenario(['template.view'], ['template.view']);

        $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export-bundle', [
                'deliverable_template_version_id' => (string) $version->id,
            ])
            ->assertStatus(403);
    }

    public function test_export_bundle_returns_404_for_foreign_tenant_instance(): void
    {
        [, $userA] = $this->seedBundleScenario(['work.export'], ['template.view']);
        [, , $instanceB, $versionB] = $this->seedBundleScenario(['work.export'], ['template.view']);

        $this->withHeaders($this->authHeaders($userA))
            ->post('/api/zena/work-instances/' . $instanceB->id . '/export-bundle', [
                'deliverable_template_version_id' => (string) $versionB->id,
            ])
            ->assertStatus(404);
    }

    public function test_export_bundle_returns_zip_with_manifest_html_and_attachments(): void
    {
        [$tenant, $user, $instance, $version, $attachment] = $this->seedBundleScenario(['work.export'], ['template.view']);

        $this->app->instance(DeliverablePdfExportService::class, Mockery::mock(DeliverablePdfExportService::class, function ($mock): void {
            $mock->shouldReceive('normalizeOptions')
                ->once()
                ->with([])
                ->andReturn([
                    'preset' => 'a4_clean',
                    'orientation' => 'portrait',
                    'header_footer' => true,
                    'margin_mm' => [
                        'top' => 18,
                        'right' => 14,
                        'bottom' => 18,
                        'left' => 14,
                    ],
                ]);
            $mock->shouldReceive('render')
                ->once()
                ->andThrow(new DeliverablePdfExportUnavailableException());
        }));

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export-bundle', [
                'deliverable_template_version_id' => (string) $version->id,
            ]);

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
        $this->assertSame(
            'attachment; filename=work-instance-' . $instance->id . '.zip',
            $response->headers->get('content-disposition')
        );

        $baseResponse = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $zipPath = $baseResponse->getFile()->getPathname();
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('deliverable.html'));
        $this->assertNotFalse($zip->locateName('attachments/step-1/evidence.txt'));
        $this->assertFalse($zip->locateName('deliverable.pdf') !== false);

        $manifestJson = $zip->getFromName('manifest.json');
        $html = $zip->getFromName('deliverable.html');
        $attachmentBody = $zip->getFromName('attachments/step-1/evidence.txt');
        $zip->close();

        $this->assertIsString($manifestJson);
        $this->assertIsString($html);
        $this->assertSame('bundle attachment body', $attachmentBody);
        $this->assertStringContainsString('North Tower', $html);
        $this->assertStringContainsString('Checked &amp; signed', $html);

        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $instance->id, $manifest['wi_id'] ?? null);
        $this->assertSame((string) $instance->project_id, $manifest['project_id'] ?? null);
        $this->assertSame((string) $tenant->id, $manifest['tenant_id'] ?? null);
        $this->assertTrue($manifest['html']['included'] ?? false);
        $this->assertSame(strlen($html), $manifest['html']['bytes'] ?? null);
        $this->assertFalse($manifest['pdf']['included'] ?? true);
        $this->assertFalse($manifest['pdf']['available'] ?? true);
        $this->assertSame(DeliverablePdfExportUnavailableException::MESSAGE, $manifest['pdf']['reason'] ?? null);
        $this->assertCount(1, $manifest['attachments'] ?? []);
        $this->assertSame((string) $attachment->id, $manifest['attachments'][0]['id'] ?? null);
        $this->assertSame('attachments/step-1/evidence.txt', $manifest['attachments'][0]['stored_path'] ?? null);

        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $user->id)
            ->where('action', 'zena.work-instance.export.bundle')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('work_instance', $audit->entity_type);
        $this->assertSame((string) $instance->id, $audit->entity_id);
        $this->assertSame((string) $instance->project_id, $audit->project_id);
        $this->assertSame(200, $audit->status_code);
        $this->assertSame((string) $version->id, $audit->meta['template_version_id'] ?? null);
        $this->assertSame('zip', $audit->meta['format'] ?? null);
    }

    /**
     * @return array{Tenant, User, WorkInstance, DeliverableTemplateVersion, WorkInstanceStepAttachment}
     */
    private function seedBundleScenario(array $workPermissions, array $templatePermissions): array
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            [],
            ['member'],
            array_values(array_unique(array_merge($workPermissions, $templatePermissions)))
        );

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'North Tower',
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $workTemplate = WorkTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-' . substr((string) Str::ulid(), -8),
            'name' => 'Execution Template',
            'description' => 'Source work template',
            'status' => 'published',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $workTemplateVersion = WorkTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'work_template_id' => (string) $workTemplate->id,
            'semver' => '1.0.0',
            'content_json' => ['steps' => [], 'approvals' => [], 'rules' => []],
            'is_immutable' => true,
            'published_at' => now(),
            'published_by' => (string) $user->id,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $instance = WorkInstance::create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'work_template_version_id' => (string) $workTemplateVersion->id,
            'status' => 'in_progress',
            'created_by' => (string) $user->id,
        ]);

        $step = WorkInstanceStep::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'qa-check',
            'name' => 'QA Check',
            'type' => 'task',
            'step_order' => 1,
            'status' => 'completed',
        ]);

        WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'remark',
            'value_string' => 'Checked & signed',
        ]);

        $attachmentPath = 'work-instances/' . $instance->id . '/steps/' . $step->id . '/attachments/' . Str::lower((string) Str::ulid()) . '.txt';
        Storage::disk('local')->put($attachmentPath, 'bundle attachment body');

        $attachment = WorkInstanceStepAttachment::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'file_name' => 'evidence.txt',
            'file_path' => $attachmentPath,
            'mime_type' => 'text/plain',
            'file_size' => strlen('bundle attachment body'),
            'uploaded_by' => (string) $user->id,
        ]);

        $template = DeliverableTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'DT-' . substr((string) Str::ulid(), -8),
            'name' => 'Inspection Export',
            'description' => 'HTML export template',
            'status' => 'published',
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        $storagePath = 'deliverable-templates/' . $tenant->id . '/' . $template->id . '/published/export.html';
        Storage::disk('local')->put(
            $storagePath,
            <<<'HTML'
<html><body>
<h1>{{project.name}}</h1>
<p>{{wi.id}}</p>
<p>{{fields.remark}}</p>
</body></html>
HTML
        );

        $version = DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => (string) $template->id,
            'version' => '1.2.3',
            'semver' => '1.2.3',
            'storage_path' => $storagePath,
            'checksum_sha256' => hash('sha256', 'unused'),
            'mime' => 'text/html',
            'size' => 0,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'published_by' => (string) $user->id,
            'created_by' => (string) $user->id,
            'updated_by' => (string) $user->id,
        ]);

        return [$tenant, $user, $instance, $version, $attachment];
    }

    private function authHeaders(User $user): array
    {
        $token = $user->createToken('test-token')->plainTextToken;

        return [
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $user->tenant_id,
            'Authorization' => 'Bearer ' . $token,
        ];
    }
}
