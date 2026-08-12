<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentCreationTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private User $creator;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('app.key', 'base64:' . base64_encode(str_repeat('a', 32)));
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        Storage::fake('local');
        $this->tenant = Tenant::factory()->create();
        $this->creator = $this->createTenantUser($this->tenant, [], ['designer'], ['document.create']);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->creator->id]);
    }

    public function test_web_create_persists_draft_not_submitted_matching_projections_and_version_snapshot(): void
    {
        $this->actingAs($this->creator)->get('/app/documents/create')->assertOk();
        $this->actingAs($this->creator)->post('/app/documents', [
            'title' => 'Web canonical document',
            'project_id' => $this->project->id,
            'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('web-canonical.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", 'application/pdf'),
        ])->assertRedirect('/app/documents');

        $document = Document::query()->where('title', 'Web canonical document')->firstOrFail();
        $this->assertSame('draft', $document->status);
        $this->assertSame('draft', $document->lifecycle_status);
        $this->assertSame('not-submitted', $document->approval_status);
        $this->assertSame('draft', $document->metadata['status']);
        $this->assertSame('draft', $document->currentVersion?->metadata['status']);
    }

    public function test_every_web_document_create_route_requires_document_create_permission(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('POST', $route->methods(), true) && $route->uri() === 'app/documents');

        $this->assertCount(1, $routes);
        $this->assertContains('rbac:document.create', $routes->first()->gatherMiddleware());
    }

    public function test_web_create_without_document_create_permission_is_forbidden_without_persistence(): void
    {
        $withoutPermission = $this->createTenantUser($this->tenant, [], ['viewer'], []);

        $this->actingAs($withoutPermission)->get('/app/documents/create')->assertOk();
        $this->actingAs($withoutPermission)->withHeaders(['Accept' => 'application/json'])->post('/app/documents', [
            'title' => 'Blocked web document',
            'project_id' => $this->project->id,
            'document_type' => 'drawing',
            'file' => UploadedFile::fake()->createWithContent('blocked-web.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n", 'application/pdf'),
        ])->assertForbidden();

        $this->assertDatabaseMissing('documents', ['title' => 'Blocked web document']);
    }
}
