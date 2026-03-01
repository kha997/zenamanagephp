<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\DeliverablePdfExportUnavailableException;
use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\DeliverableTemplate;
use App\Models\DeliverableTemplateVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkInstanceStep;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;
use ZipArchive;

class WorkInstanceEndToEndSmokeFlowTest extends TestCase
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
        [, , $instance, , $version] = $this->seedSmokeScenario();

        $this->postJson('/api/zena/work-instances/' . $instance->id . '/export-bundle', [
            'deliverable_template_version_id' => (string) $version->id,
        ], ['X-Tenant-ID' => (string) $instance->tenant_id])
            ->assertStatus(401);
    }

    public function test_smoke_flow_uploads_attachment_and_exports_html_pdf_and_bundle(): void
    {
        [$tenant, $user, $instance, $step, $version] = $this->seedSmokeScenario();

        $uploadResponse = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/steps/' . $step->id . '/attachments', [
                'file' => UploadedFile::fake()->createWithContent('evidence.txt', 'smoke attachment body'),
            ]);

        $uploadResponse->assertCreated();
        $attachmentId = $uploadResponse->json('data.attachment.id');
        $this->assertNotNull($attachmentId);
        $this->assertDatabaseHas('work_instance_step_attachments', [
            'id' => $attachmentId,
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'file_name' => 'evidence.txt',
        ]);

        $htmlResponse = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
            ]);

        $htmlResponse->assertOk();
        $this->assertSame('text/html; charset=utf-8', $htmlResponse->headers->get('content-type'));
        $this->assertSame(
            'attachment; filename="deliverable-' . $instance->id . '-1.0.0.html"',
            $htmlResponse->headers->get('content-disposition')
        );
        $html = $htmlResponse->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('North Tower Smoke', $html);
        $this->assertStringContainsString('Checked &amp; signed', $html);
        $this->assertStringContainsString('All punch items closed', $html);

        $pdfResponse = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
            ]);

        $this->assertContains($pdfResponse->getStatusCode(), [200, 501]);
        if ($pdfResponse->getStatusCode() === 200) {
            $this->assertSame('application/pdf', $pdfResponse->headers->get('content-type'));
            $pdfBody = $pdfResponse->getContent();
            $this->assertIsString($pdfBody);
            $this->assertStringStartsWith('%PDF-', $pdfBody);
        } else {
            $pdfResponse->assertJson([
                'success' => false,
                'message' => DeliverablePdfExportUnavailableException::MESSAGE,
            ]);
        }

        $bundleResponse = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export-bundle', [
                'deliverable_template_version_id' => (string) $version->id,
            ]);

        $bundleResponse->assertOk();
        $this->assertSame('application/zip', $bundleResponse->headers->get('content-type'));
        $this->assertSame(
            'attachment; filename=work-instance-' . $instance->id . '.zip',
            $bundleResponse->headers->get('content-disposition')
        );

        $baseResponse = $bundleResponse->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $baseResponse);

        $sourceZipPath = $baseResponse->getFile()->getPathname();
        $zipPath = tempnam(sys_get_temp_dir(), 'work-instance-smoke-');
        $this->assertNotFalse($zipPath);
        file_put_contents($zipPath, (string) file_get_contents($sourceZipPath));

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('manifest.json'));
        $this->assertNotFalse($zip->locateName('deliverable.html'));

        $attachmentEntry = null;
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (is_string($name) && str_starts_with($name, 'attachments/')) {
                $attachmentEntry = $name;
                break;
            }
        }

        $this->assertIsString($attachmentEntry);
        $manifestJson = $zip->getFromName('manifest.json');
        $bundleHtml = $zip->getFromName('deliverable.html');
        $bundleAttachment = $zip->getFromName($attachmentEntry);
        $bundlePdf = $zip->getFromName('deliverable.pdf');
        $zip->close();
        @unlink($zipPath);

        $this->assertIsString($manifestJson);
        $this->assertIsString($bundleHtml);
        $this->assertSame('smoke attachment body', $bundleAttachment);
        $this->assertStringContainsString('North Tower Smoke', $bundleHtml);

        $manifest = json_decode($manifestJson, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame((string) $instance->id, $manifest['wi_id'] ?? null);
        $this->assertSame((string) $instance->project_id, $manifest['project_id'] ?? null);
        $this->assertSame((string) $tenant->id, $manifest['tenant_id'] ?? null);
        $this->assertTrue($manifest['html']['included'] ?? false);
        $this->assertSame(strlen($bundleHtml), $manifest['html']['bytes'] ?? null);
        $this->assertCount(1, $manifest['attachments'] ?? []);
        $this->assertSame((string) $attachmentId, $manifest['attachments'][0]['id'] ?? null);
        $this->assertSame('evidence.txt', $manifest['attachments'][0]['file_name'] ?? null);
        $this->assertSame($attachmentEntry, $manifest['attachments'][0]['stored_path'] ?? null);

        if ($bundlePdf !== false) {
            $this->assertTrue($manifest['pdf']['included'] ?? false);
            $this->assertTrue($manifest['pdf']['available'] ?? false);
            $this->assertSame(strlen($bundlePdf), $manifest['pdf']['bytes'] ?? null);
        } else {
            $this->assertFalse($manifest['pdf']['included'] ?? true);
            $this->assertFalse($manifest['pdf']['available'] ?? true);
            $this->assertSame(DeliverablePdfExportUnavailableException::MESSAGE, $manifest['pdf']['reason'] ?? null);
        }
    }

    /**
     * @return array{Tenant, User, WorkInstance, WorkInstanceStep, DeliverableTemplateVersion}
     */
    private function seedSmokeScenario(): array
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            [],
            ['member'],
            ['template.view', 'work.export', 'work.update']
        );

        $project = Project::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'North Tower Smoke',
            'created_by' => (string) $user->id,
            'pm_id' => (string) $user->id,
        ]);

        $workTemplate = WorkTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'WT-' . substr((string) Str::ulid(), -8),
            'name' => 'Execution Template Smoke',
            'description' => 'Smoke flow template',
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

        WorkInstanceStep::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_id' => (string) $instance->id,
            'step_key' => 'handover',
            'name' => 'Handover',
            'type' => 'task',
            'step_order' => 2,
            'status' => 'completed',
        ]);

        \App\Models\WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'remark',
            'value_string' => 'Checked & signed',
        ]);

        \App\Models\WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'handover_note',
            'value_string' => 'All punch items closed',
        ]);

        $template = DeliverableTemplate::create([
            'tenant_id' => (string) $tenant->id,
            'code' => 'DT-' . substr((string) Str::ulid(), -8),
            'name' => 'Inspection Export Smoke',
            'description' => 'Smoke HTML export template',
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
<p>{{fields.handover_note}}</p>
</body></html>
HTML
        );

        $version = DeliverableTemplateVersion::create([
            'tenant_id' => (string) $tenant->id,
            'deliverable_template_id' => (string) $template->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
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

        return [$tenant, $user, $instance, $step, $version];
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
