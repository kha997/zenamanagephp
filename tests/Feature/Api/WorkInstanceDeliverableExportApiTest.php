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
use App\Models\WorkTemplate;
use App\Models\WorkTemplateVersion;
use App\Services\DeliverablePdfExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class WorkInstanceDeliverableExportApiTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
    }

    public function test_rbac_denies_export_without_permission(): void
    {
        [$tenant, $user, $instance, $version] = $this->seedExportScenario(['template.view'], ['template.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
            ]);

        $response->assertStatus(403);
    }

    public function test_tenant_isolation_returns_404_for_foreign_template_version(): void
    {
        [$tenantA, $actorA, $instanceA] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);
        [, $actorB, , $foreignVersion] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

        $response = $this->withHeaders($this->authHeaders($actorA))
            ->post('/api/zena/work-instances/' . $instanceA->id . '/export', [
                'deliverable_template_version_id' => (string) $foreignVersion->id,
            ]);

        $response->assertStatus(404);
    }

    public function test_export_returns_html_download_with_substituted_values(): void
    {
        [, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
            ]);

        $response->assertOk();
        $this->assertSame('text/html; charset=utf-8', $response->headers->get('content-type'));
        $this->assertSame(
            'attachment; filename="deliverable-' . $instance->id . '-1.2.3.html"',
            $response->headers->get('content-disposition')
        );

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('North Tower', $content);
        $this->assertStringContainsString((string) $instance->id, $content);
        $this->assertStringContainsString('Checked &amp; signed', $content);
        $this->assertStringContainsString('12.5', $content);
        $this->assertStringContainsString('true', $content);
        $this->assertStringContainsString('{&quot;status&quot;:&quot;ready&quot;,&quot;count&quot;:2}', $content);
        $this->assertStringContainsString('<span class="missing"></span>', $content);
    }

    public function test_export_writes_audit_log_with_template_version_metadata(): void
    {
        [$tenant, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

        $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
            ])
            ->assertOk();

        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $user->id)
            ->where('action', 'work.export')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('work_instance', $audit->entity_type);
        $this->assertSame((string) $instance->id, $audit->entity_id);
        $this->assertSame((string) $version->id, $audit->meta['template_version_id'] ?? null);
        $this->assertSame('html', $audit->meta['format'] ?? null);
    }

    public function test_export_pdf_returns_pdf_download(): void
    {
        [, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

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
                ->with(
                    Mockery::on(static fn (string $html): bool => str_contains($html, 'North Tower')),
                    Mockery::on(static fn (array $options): bool => $options === [
                        'preset' => 'a4_clean',
                        'orientation' => 'portrait',
                        'header_footer' => true,
                        'margin_mm' => [
                            'top' => 18,
                            'right' => 14,
                            'bottom' => 18,
                            'left' => 14,
                        ],
                    ]),
                    Mockery::on(static fn (array $meta): bool => ($meta['project_name'] ?? null) === 'North Tower'
                        && ($meta['template_semver'] ?? null) === '1.2.3'
                        && is_string($meta['generated_at'] ?? null))
                )
                ->andReturn("%PDF-1.7\nfake deliverable\n");
        }));

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
            ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        $this->assertSame(
            'attachment; filename="deliverable-' . $instance->id . '-1.2.3.pdf"',
            $response->headers->get('content-disposition')
        );

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-1.7', $content);
    }

    public function test_export_pdf_writes_audit_log_with_format_metadata(): void
    {
        [$tenant, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

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
            $mock->shouldReceive('render')->once()->andReturn("%PDF-1.7\nfake deliverable\n");
        }));

        $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
            ])
            ->assertOk();

        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $user->id)
            ->where('action', 'work.export')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((string) $version->id, $audit->meta['template_version_id'] ?? null);
        $this->assertSame('pdf', $audit->meta['format'] ?? null);
        $this->assertSame('a4_clean', $audit->meta['pdf']['preset'] ?? null);
        $this->assertSame('portrait', $audit->meta['pdf']['orientation'] ?? null);
        $this->assertTrue($audit->meta['pdf']['header_footer'] ?? false);
    }

    public function test_export_pdf_with_options_returns_pdf_download(): void
    {
        [, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

        $this->app->instance(DeliverablePdfExportService::class, Mockery::mock(DeliverablePdfExportService::class, function ($mock): void {
            $mock->shouldReceive('normalizeOptions')
                ->once()
                ->with([
                    'orientation' => 'landscape',
                    'header_footer' => false,
                ])
                ->andReturn([
                    'preset' => 'a4_clean',
                    'orientation' => 'landscape',
                    'header_footer' => false,
                    'margin_mm' => [
                        'top' => 18,
                        'right' => 14,
                        'bottom' => 18,
                        'left' => 14,
                    ],
                ]);

            $mock->shouldReceive('render')
                ->once()
                ->with(
                    Mockery::type('string'),
                    [
                        'preset' => 'a4_clean',
                        'orientation' => 'landscape',
                        'header_footer' => false,
                        'margin_mm' => [
                            'top' => 18,
                            'right' => 14,
                            'bottom' => 18,
                            'left' => 14,
                        ],
                    ],
                    Mockery::type('array')
                )
                ->andReturn("%PDF-1.7\nfake deliverable\n");
        }));

        $response = $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
                'pdf' => [
                    'orientation' => 'landscape',
                    'header_footer' => false,
                ],
            ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_export_pdf_audit_log_includes_pdf_option_metadata(): void
    {
        [$tenant, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

        $this->app->instance(DeliverablePdfExportService::class, Mockery::mock(DeliverablePdfExportService::class, function ($mock): void {
            $mock->shouldReceive('normalizeOptions')->once()->andReturn([
                'preset' => 'a4_clean',
                'orientation' => 'landscape',
                'header_footer' => false,
                'margin_mm' => [
                    'top' => 18,
                    'right' => 14,
                    'bottom' => 18,
                    'left' => 14,
                ],
            ]);
            $mock->shouldReceive('render')->once()->andReturn("%PDF-1.7\nfake deliverable\n");
        }));

        $this->withHeaders($this->authHeaders($user))
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
                'pdf' => [
                    'orientation' => 'landscape',
                    'header_footer' => false,
                    'preset' => 'bad-value',
                ],
            ])
            ->assertOk();

        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('user_id', (string) $user->id)
            ->where('action', 'work.export')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame((string) $version->id, $audit->meta['template_version_id'] ?? null);
        $this->assertSame('pdf', $audit->meta['format'] ?? null);
        $this->assertSame('a4_clean', $audit->meta['pdf']['preset'] ?? null);
        $this->assertSame('landscape', $audit->meta['pdf']['orientation'] ?? null);
        $this->assertFalse($audit->meta['pdf']['header_footer'] ?? true);
    }

    public function test_export_pdf_returns_501_when_runtime_dependencies_are_missing(): void
    {
        [, $user, $instance, $version] = $this->seedExportScenario(['work.export', 'template.view'], ['work.export', 'template.view']);

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
            ->post('/api/zena/work-instances/' . $instance->id . '/export', [
                'deliverable_template_version_id' => (string) $version->id,
                'format' => 'pdf',
            ]);

        $response->assertStatus(501);
        $response->assertJson([
            'success' => false,
            'message' => DeliverablePdfExportUnavailableException::MESSAGE,
        ]);
    }

    /**
     * @return array{Tenant, User, WorkInstance, DeliverableTemplateVersion}
     */
    private function seedExportScenario(array $workPermissions, array $templatePermissions): array
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

        WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'quantity',
            'value_number' => 12.5,
        ]);

        WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'approved',
            'value_string' => 'true',
        ]);

        WorkInstanceFieldValue::create([
            'tenant_id' => (string) $tenant->id,
            'work_instance_step_id' => (string) $step->id,
            'field_key' => 'summary',
            'value_json' => ['status' => 'ready', 'count' => 2],
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
<p>{{fields.quantity}}</p>
<p>{{fields.approved}}</p>
<p>{{fields.summary}}</p>
<span class="missing">{{fields.unknown}}</span>
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

        return [$tenant, $user, $instance, $version];
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
