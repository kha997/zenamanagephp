<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Models\Document;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    private DocumentWorkflowService $service;
    private Tenant $tenant;
    private Project $project;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(DocumentWorkflowService::class);
        $this->tenant = Tenant::factory()->create();
        $this->actor = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->actor->id,
        ]);
    }

    private function makeDocument(array $overrides = []): Document
    {
        return Document::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'uploaded_by' => $this->actor->id,
            'created_by' => $this->actor->id,
            'updated_by' => $this->actor->id,
            'status' => 'draft',
            'metadata' => ['status' => 'draft'],
        ], $overrides));
    }

    public function test_submit_transitions_draft_to_submitted_with_audit_metadata(): void
    {
        $document = $this->makeDocument();

        $result = $this->service->submit((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id);

        $this->assertSame('submitted', $result->status);
        $this->assertSame('submitted', $result->metadata['status']);
        $this->assertSame((string) $this->actor->id, $result->metadata['submitted_by']);
        $this->assertNotNull($result->metadata['submitted_at']);
        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'submitted']);
    }

    public function test_submit_from_non_draft_status_throws_invalid_submit_transition(): void
    {
        $document = $this->makeDocument(['status' => 'approved', 'metadata' => ['status' => 'approved']]);

        try {
            $this->service->submit((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id);
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_SUBMIT_TRANSITION', $e->reasonCode);
        }

        $this->assertDatabaseHas('documents', ['id' => $document->id, 'status' => 'approved']);
    }

    public function test_submit_on_missing_document_throws_document_not_found(): void
    {
        try {
            $this->service->submit((string) $this->tenant->id, '01HZNONEXISTENTDOC00000001', (string) $this->actor->id);
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
        }
    }

    public function test_decide_approved_with_null_note_records_null_decision_note(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::APPROVED,
            null
        );

        $this->assertSame('approved', $result->status);
        $this->assertSame('approved', $result->metadata['decision']);
        $this->assertNull($result->metadata['decision_note']);
        $this->assertSame((string) $this->actor->id, $result->metadata['decision_by']);
    }

    public function test_decide_approved_with_note_records_decision_note(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::APPROVED,
            'ghi chú duyệt'
        );

        $this->assertSame('ghi chú duyệt', $result->metadata['decision_note']);
    }

    public function test_decide_rejected_with_reason_records_rejected_status(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::REJECTED,
            'lý do từ chối'
        );

        $this->assertSame('rejected', $result->status);
        $this->assertSame('rejected', $result->metadata['decision']);
        $this->assertSame('lý do từ chối', $result->metadata['decision_note']);
    }

    public function test_decide_rejected_with_null_note_is_accepted_by_service(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $result = $this->service->decide(
            (string) $this->tenant->id,
            (string) $document->id,
            (string) $this->actor->id,
            DocumentDecision::REJECTED,
            null
        );

        $this->assertSame('rejected', $result->status);
        $this->assertNull($result->metadata['decision_note']);
    }

    public function test_decide_from_non_submitted_status_throws_invalid_decision_transition(): void
    {
        $document = $this->makeDocument(['status' => 'draft', 'metadata' => ['status' => 'draft']]);

        try {
            $this->service->decide(
                (string) $this->tenant->id,
                (string) $document->id,
                (string) $this->actor->id,
                DocumentDecision::APPROVED,
                null
            );
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        }
    }

    public function test_decide_on_missing_document_throws_document_not_found(): void
    {
        try {
            $this->service->decide(
                (string) $this->tenant->id,
                '01HZNONEXISTENTDOC00000002',
                (string) $this->actor->id,
                DocumentDecision::APPROVED,
                null
            );
            $this->fail('Expected DocumentWorkflowException.');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
        }
    }

    public function test_find_for_tenant_returns_null_for_cross_tenant_document(): void
    {
        $document = $this->makeDocument();
        $otherTenant = Tenant::factory()->create();

        $this->assertNull($this->service->findForTenant((string) $otherTenant->id, (string) $document->id));
    }

    /**
     * Sequential (sqlite) — chỉ chứng minh state machine đúng ở mức application,
     * KHÔNG chứng minh khoá hàng thật (xem RfiEscalationConcurrencyTest.php:16-26
     * cho cùng lưu ý). Bằng chứng khoá hàng thật nằm ở Task 8
     * (DocumentWorkflowConcurrencyTest, 2 process MySQL độc lập).
     */
    public function test_sequential_double_decide_second_call_rejected_first_decision_persists(): void
    {
        $document = $this->makeDocument(['status' => 'submitted', 'metadata' => ['status' => 'submitted']]);

        $this->service->decide((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id, DocumentDecision::APPROVED, null);

        try {
            $this->service->decide((string) $this->tenant->id, (string) $document->id, (string) $this->actor->id, DocumentDecision::REJECTED, null);
            $this->fail('Expected DocumentWorkflowException on second decide().');
        } catch (DocumentWorkflowException $e) {
            $this->assertSame('INVALID_DECISION_TRANSITION', $e->reasonCode);
        }

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'status' => 'approved',
            'updated_by' => $this->actor->id,
        ]);
    }
}
