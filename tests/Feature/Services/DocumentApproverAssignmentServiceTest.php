<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\DocumentApproverAssignmentException;
use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentApproverAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class DocumentApproverAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private DocumentApproverAssignmentService $service;
    private Tenant $tenant;
    private Project $project;
    private Document $document;
    private User $actor;
    private User $eligibleApprover;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DocumentApproverAssignmentService::class);

        $this->tenant = Tenant::factory()->create();
        $this->actor = $this->createTenantUser($this->tenant, [], ['pm'], []);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id, 'pm_id' => $this->actor->id]);
        $this->document = Document::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => 'draft',
        ]);
        $this->eligibleApprover = $this->createTenantUser($this->tenant, [], ['pm'], ['document.approve']);
    }

    public function test_assign_sets_approver_id_and_writes_audit_row(): void
    {
        $document = $this->service->assign(
            $this->tenant->id,
            $this->document->id,
            $this->actor->id,
            $this->eligibleApprover->id,
        );

        self::assertSame($this->eligibleApprover->id, $document->approver_id);
        self::assertDatabaseHas('document_approver_assignments', [
            'document_id' => $this->document->id,
            'actor_id' => $this->actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $this->eligibleApprover->id,
        ]);
    }

    public function test_assign_rejects_target_from_a_different_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $outsider = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $outsider->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_TENANT_MISMATCH', $e->reasonCode);
            self::assertNull($this->document->fresh()->approver_id);
            self::assertDatabaseCount('document_approver_assignments', 0);
            throw $e;
        }
    }

    public function test_assign_rejects_target_without_document_approve_permission(): void
    {
        $ineligible = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $ineligible->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('APPROVER_LACKS_PERMISSION', $e->reasonCode);
            throw $e;
        }
    }

    public function test_assign_with_null_clears_explicit_override_and_records_it(): void
    {
        $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);

        $document = $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, null);

        self::assertNull($document->approver_id);
        self::assertDatabaseHas('document_approver_assignments', [
            'document_id' => $this->document->id,
            'previous_approver_id' => $this->eligibleApprover->id,
            'new_approver_id' => null,
        ]);
    }

    public function test_assign_throws_document_not_found_for_wrong_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->expectException(DocumentApproverAssignmentException::class);
        try {
            $this->service->assign($otherTenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);
        } catch (DocumentApproverAssignmentException $e) {
            self::assertSame('DOCUMENT_NOT_FOUND', $e->reasonCode);
            throw $e;
        }
    }

    public function test_reassignment_persists_across_a_reopen_cycle(): void
    {
        // GAP-033 §6.5: reopening (Approval reset to not-submitted) must NOT
        // clear a prior assignment. The service itself never touches Approval,
        // so this proves it has no side effect on that dimension at all.
        $this->service->assign($this->tenant->id, $this->document->id, $this->actor->id, $this->eligibleApprover->id);

        $fresh = $this->document->fresh();
        self::assertSame('draft', $fresh->getRawOriginal('lifecycle_status') ?? $fresh->status);
        self::assertSame($this->eligibleApprover->id, $fresh->approver_id);
    }
}
