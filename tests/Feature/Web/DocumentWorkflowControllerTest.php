<?php declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Enums\DocumentApprovalStatus;
use App\Enums\DocumentDecision;
use App\Enums\DocumentLifecycleStatus;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentWorkflowControllerTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->tenant = Tenant::factory()->create();
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        $uploader = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $document = Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $uploader->id,
            'created_by' => $uploader->id,
            'updated_by' => $uploader->id,
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'metadata' => ['status' => 'draft'],
            'current_version_id' => null,
        ], $overrides));

        $version = DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version_number' => 1,
            'file_path' => "documents/{$document->id}/v1.pdf",
            'storage_driver' => 'local',
            'comment' => 'Initial version',
            'metadata' => ['version' => 1],
            'created_by' => $uploader->id,
        ]);
        $document->forceFill(['current_version_id' => $version->id])->saveQuietly();

        return $document->fresh();
    }

    private function submitDocument(Document $document): Document
    {
        app(DocumentWorkflowService::class)->submit(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $document->uploaded_by
        );

        return $document->fresh();
    }

    private function decideDocument(Document $document, DocumentDecision $decision, ?string $note = null): Document
    {
        app(DocumentWorkflowService::class)->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $document->uploaded_by,
            $decision,
            $note
        );

        return $document->fresh();
    }

    private function publishDocument(Document $document): Document
    {
        app(DocumentLifecycleService::class)->publish(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $document->uploaded_by
        );

        return $document->fresh();
    }

    private function archiveDocument(Document $document): Document
    {
        app(DocumentLifecycleService::class)->archive(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $document->uploaded_by
        );

        return $document->fresh();
    }

    public function test_submit_by_actor_with_document_update_transitions_draft_to_submitted(): void
    {
        $document = $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_submit_on_non_draft_document_shows_error_and_does_not_mutate(): void
    {
        $document = $this->makeDocument();
        $document = $this->submitDocument($document);
        $document = $this->decideDocument($document, DocumentDecision::APPROVED);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_submit_without_document_update_permission_is_blocked(): void
    {
        $document = $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['viewer'], ['document.view']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $document->id]));

        $response->assertStatus(302);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_submit_on_cross_tenant_document_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUploader = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherUploader->id,
            'created_by' => $otherUploader->id,
            'updated_by' => $otherUploader->id,
            'status' => 'draft',
        ]);

        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.submit', ['document' => $foreignDocument->id]));

        $response->assertNotFound();
    }

    public function test_web_upload_creates_document_in_draft_status(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.create']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post('/app/documents', [
                'title' => 'Web Upload Draft Proof',
                'project_id' => $this->project->id,
                'document_type' => 'drawing',
                'file' => $this->createValidPdfUploadedFile('web-upload.pdf'),
            ]);

        $response->assertRedirect('/app/documents');
        $this->assertDatabaseHas('documents', [
            'title' => 'Web Upload Draft Proof',
            'status' => 'draft',
        ]);
    }

    public function test_approve_by_actor_with_document_approve_transitions_submitted_to_approved(): void
    {
        $document = $this->makeDocument();
        $document = $this->submitDocument($document);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_reject_without_decision_note_fails_validation(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reject', ['document' => $document->id]), []);

        $response->assertSessionHasErrors('decision_note');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_reject_with_decision_note_transitions_submitted_to_rejected(): void
    {
        $document = $this->makeDocument();
        $document = $this->submitDocument($document);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reject', ['document' => $document->id]), [
                'decision_note' => 'Thiếu chữ ký kỹ sư trưởng',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'rejected']);
    }

    public function test_approve_or_reject_without_document_approve_permission_is_blocked(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertStatus(302);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_approve_on_already_approved_document_shows_error_and_does_not_mutate(): void
    {
        $document = $this->makeDocument(['status' => 'approved', 'metadata' => ['status' => 'approved', 'decision_by' => 'someone-else']]);
        $actor = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.approve', ['document' => $document->id]));

        $response->assertSessionHasErrors('error');
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_publish_by_actor_with_document_update_transitions_draft_to_published(): void
    {
        $document = $this->makeDocument();
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.publish', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $document->fresh();
        self::assertSame(DocumentLifecycleStatus::PUBLISHED->value, $fresh->getRawOriginal('lifecycle_status'));
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $fresh->getRawOriginal('approval_status'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'published']);
    }

    public function test_archive_by_actor_with_document_update_transitions_published_to_archived(): void
    {
        $document = $this->makeDocument();
        $document = $this->publishDocument($document);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.archive', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $document->fresh();
        self::assertSame(DocumentLifecycleStatus::ARCHIVED->value, $fresh->getRawOriginal('lifecycle_status'));
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $fresh->getRawOriginal('approval_status'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'archived']);
    }

    public function test_reopen_by_actor_with_document_update_transitions_approved_to_draft(): void
    {
        $document = $this->makeDocument();
        $document = $this->submitDocument($document);
        $document = $this->decideDocument($document, DocumentDecision::APPROVED);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reopen', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $document->fresh();
        self::assertSame(DocumentLifecycleStatus::DRAFT->value, $fresh->getRawOriginal('lifecycle_status'));
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $fresh->getRawOriginal('approval_status'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_reactivate_by_actor_with_document_update_transitions_archived_to_draft(): void
    {
        $document = $this->makeDocument();
        $document = $this->publishDocument($document);
        $document = $this->archiveDocument($document);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $response = $this->actingAs($actor)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.workflow.reactivate', ['document' => $document->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $fresh = $document->fresh();
        self::assertSame(DocumentLifecycleStatus::DRAFT->value, $fresh->getRawOriginal('lifecycle_status'));
        self::assertSame(DocumentApprovalStatus::NOT_SUBMITTED->value, $fresh->getRawOriginal('approval_status'));
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'draft']);
    }

    public function test_lifecycle_actions_without_document_update_permission_are_blocked(): void
    {
        $publishable = $this->makeDocument();
        $archivable = $this->publishDocument($this->makeDocument());
        $reopenable = $this->decideDocument($this->submitDocument($this->makeDocument()), DocumentDecision::APPROVED);
        $reactivatable = $this->archiveDocument($this->publishDocument($this->makeDocument()));
        $actor = $this->createTenantUser($this->tenant, [], ['viewer'], ['document.view']);

        $requests = [
            'publish' => $publishable,
            'archive' => $archivable,
            'reopen' => $reopenable,
            'reactivate' => $reactivatable,
        ];

        foreach ($requests as $action => $document) {
            $before = $document->getRawOriginal('status');
            $response = $this->actingAs($actor)
                ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
                ->post(route("app.documents.workflow.{$action}", ['document' => $document->id]));

            $response->assertStatus(302);
            self::assertSame($before, $document->fresh()->getRawOriginal('status'));
        }
    }

    public function test_lifecycle_actions_on_cross_tenant_document_return_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherProject = Project::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUploader = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignDocument = Document::factory()->create([
            'tenant_id' => $otherTenant->id,
            'project_id' => $otherProject->id,
            'uploaded_by' => $otherUploader->id,
            'created_by' => $otherUploader->id,
            'updated_by' => $otherUploader->id,
            'status' => 'draft',
            'lifecycle_status' => DocumentLifecycleStatus::DRAFT->value,
            'approval_status' => DocumentApprovalStatus::NOT_SUBMITTED->value,
            'metadata' => ['status' => 'draft'],
        ]);
        $actor = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        foreach (['publish', 'archive', 'reopen', 'reactivate'] as $action) {
            $response = $this->actingAs($actor)
                ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
                ->post(route("app.documents.workflow.{$action}", ['document' => $foreignDocument->id]));

            $response->assertNotFound();
        }
    }

    public function test_projects_manager_can_assign_approver_via_web(): void
    {
        $pm = $this->createTenantUser($this->tenant, [], ['pm'], ['document.view', 'document.update', 'document.approve']);
        $this->project->update(['pm_id' => $pm->id]);
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $document = $this->makeDocument();

        $response = $this->actingAs($pm)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.approver.assign', ['document' => $document->id]), [
                'approver_id' => $eligible->id,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        self::assertSame($eligible->id, $document->fresh()->approver_id);
    }

    public function test_non_manager_cannot_assign_approver_via_web(): void
    {
        $document = $this->makeDocument();
        $eligible = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
        $designer = $this->createTenantUser($this->tenant, [], ['designer'], ['document.update']);

        $this->actingAs($designer)
            ->withHeaders(['X-Tenant-ID' => (string) $this->tenant->id])
            ->post(route('app.documents.approver.assign', ['document' => $document->id]), [
                'approver_id' => $eligible->id,
            ])->assertForbidden();
    }

    /**
     * `UploadedFile::fake()->create()` produces content with no real file signature,
     * which fails `FileStorageService`'s `EnhancedMimeValidationService` signature check
     * (see tests/Feature/Api/DocumentManagementTest.php::createValidPdfUploadedFile for
     * the established pattern this mirrors). A minimal but valid PDF payload is required
     * so the upload reaches the workflow-status assertion under test.
     */
    private function createValidPdfUploadedFile(string $name): \Illuminate\Http\UploadedFile
    {
        $content = "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n";

        return \Illuminate\Http\UploadedFile::fake()->createWithContent($name, $content, 'application/pdf');
    }
}
