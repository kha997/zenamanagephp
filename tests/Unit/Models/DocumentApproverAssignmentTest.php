<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Document;
use App\Models\DocumentApproverAssignment;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class DocumentApproverAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_row_is_append_only(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $assignment = DocumentApproverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $actor->id,
        ]);

        $assignment->new_approver_id = null;

        $this->expectException(LogicException::class);
        $assignment->save();
    }

    public function test_assignment_row_cannot_be_deleted(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        $document = Document::factory()->create(['tenant_id' => $tenant->id, 'project_id' => $project->id]);
        $actor = User::factory()->create(['tenant_id' => $tenant->id]);

        $assignment = DocumentApproverAssignment::query()->create([
            'tenant_id' => $tenant->id,
            'document_id' => $document->id,
            'actor_id' => $actor->id,
            'previous_approver_id' => null,
            'new_approver_id' => $actor->id,
        ]);

        $this->expectException(LogicException::class);
        $assignment->delete();
    }

    public function test_effective_approver_prefers_explicit_assignment_over_project_manager(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $explicit = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => $explicit->id,
        ]);

        self::assertSame($explicit->id, $document->fresh()->effectiveApprover()?->id);
    }

    public function test_effective_approver_falls_back_to_project_manager_when_unset(): void
    {
        $tenant = Tenant::factory()->create();
        $pm = User::factory()->create(['tenant_id' => $tenant->id]);
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => $pm->id]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => null,
        ]);

        self::assertSame($pm->id, $document->fresh()->effectiveApprover()?->id);
    }

    public function test_effective_approver_is_null_when_neither_is_set(): void
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id, 'pm_id' => null]);
        $document = Document::factory()->create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'approver_id' => null,
        ]);

        self::assertNull($document->fresh()->effectiveApprover());
    }
}
