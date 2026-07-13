<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\AuthenticationTestTrait;
use Tests\Traits\RouteNameTrait;

/**
 * Assert that document_type validation is consistent across ALL upload paths.
 * Each path must accept only values from Document::VALID_DOCUMENT_TYPES.
 */
class DocumentUploadValidationTest extends TestCase
{
    use AuthenticationTestTrait;
    use RefreshDatabase;
    use RouteNameTrait;

    protected Tenant $tenant;
    protected User $user;
    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('filesystems.default', 'local');
        Config::set('filesystems.cloud', 'local');

        $this->app->forgetInstance('filesystem');
        $this->app->forgetInstance(FilesystemManager::class);

        foreach (['local', 'public'] as $disk) {
            Storage::fake($disk);
        }

        $this->forgetCachedStorageServices();

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser($this->tenant, [], ['designer'], [
            'document.view',
            'document.create',
            'document.update',
            'document.delete',
        ]);
        $this->apiAs($this->user, $this->tenant);

        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createValidPdfUploadedFile(string $name = 'test.pdf'): UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

        return UploadedFile::fake()->createWithContent($name, $content, 'application/pdf');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => $this->project->id,
            'title' => 'Validation Test Doc',
            'document_type' => 'drawing',
            'file' => $this->createValidPdfUploadedFile(),
        ], $overrides);
    }

    private function forgetCachedStorageServices(): void
    {
        $services = [
            \App\Services\FileStorageService::class,
            \App\Services\EnhancedMimeValidationService::class,
            \Src\Foundation\Services\FileStorageService::class,
            \Src\Foundation\Services\EnhancedMimeValidationService::class,
        ];

        foreach ($services as $service) {
            if ($this->app->bound($service)) {
                $this->app->forgetInstance($service);
            }
        }
    }

    // ---------------------------------------------------------------
    //  Path 1: Api\SimpleDocumentController (canonical — v1.documents)
    // ---------------------------------------------------------------

    public function test_simple_document_store_rejects_invalid_document_type(): void
    {
        $response = $this->apiPostMultipart(
            $this->namedRoute('v1.documents.store'),
            $this->validPayload(['document_type' => 'invoice'])
        );

        $response->assertStatus(422);
    }

    public function test_simple_document_store_accepts_valid_document_type(): void
    {
        $response = $this->apiPostMultipart(
            $this->namedRoute('v1.documents.store'),
            $this->validPayload(['document_type' => 'drawing'])
        );

        // 201 = created (JSend wrapped); 200 = success — both indicate validation passed
        $this->assertContains($response->status(), [200, 201], 'Expected 200/201 but got ' . $response->status());
    }

    // ---------------------------------------------------------------
    //  Path 2: Api\DocumentController (legacy — api/documents)
    // ---------------------------------------------------------------

    public function test_legacy_document_store_rejects_invalid_document_type(): void
    {
        $response = $this->apiPostMultipart(
            '/api/documents',
            $this->validPayload(['document_type' => 'invoice'])
        );

        $response->assertStatus(422);
    }

    public function test_legacy_document_store_accepts_valid_document_type(): void
    {
        $response = $this->apiPostMultipart(
            '/api/documents',
            $this->validPayload(['document_type' => 'contract'])
        );

        $this->assertContains($response->status(), [200, 201], 'Expected 200/201 but got ' . $response->status());
    }

    // ---------------------------------------------------------------
    //  Sanity: every VALID_DOCUMENT_TYPES value is accepted on canonical path
    // ---------------------------------------------------------------

    public function test_all_canonical_document_types_are_accepted(): void
    {
        foreach (Document::VALID_DOCUMENT_TYPES as $type) {
            $response = $this->apiPostMultipart(
                $this->namedRoute('v1.documents.store'),
                $this->validPayload(['document_type' => $type])
            );

            $this->assertContains(
                $response->status(),
                [200, 201],
                "document_type '{$type}' should be accepted but got HTTP {$response->status()}"
            );
        }
    }
}
