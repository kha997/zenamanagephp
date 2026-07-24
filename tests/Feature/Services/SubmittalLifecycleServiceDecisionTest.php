<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\SubmittalTransitionConflictException;
use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubmittalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmittalLifecycleServiceDecisionTest extends TestCase
{
    use RefreshDatabase;

    private SubmittalLifecycleService $service;
    private Tenant $tenant;
    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubmittalLifecycleService::class);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->project = Project::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function submittedSubmittal(): Submittal
    {
        $submittal = Submittal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => Submittal::STATUS_DRAFT,
            'submittal_number' => 'SUB-0002',
        ]);

        return $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_reject_then_start_revision_then_resubmit(): void
    {
        $submittal = $this->submittedSubmittal();

        $rejected = $this->service->reject($submittal, [
            'actor_user_id' => $this->user->id,
            'rejection_reason' => 'Missing calcs',
            'rejection_comments' => 'Please redo section 3',
        ]);
        $this->assertSame(Submittal::STATUS_REJECTED, $rejected->status);

        $revising = $this->service->startRevision($rejected, ['actor_user_id' => $this->user->id]);
        $this->assertSame(Submittal::STATUS_REVISING, $revising->status);

        $resubmitted = $this->service->submit($revising, [
            'actor_user_id' => $this->user->id,
            'revision_summary' => 'Fixed section 3',
        ]);
        $this->assertSame(Submittal::STATUS_SUBMITTED, $resubmitted->status);
        $this->assertSame(2, $resubmitted->current_revision_no);

        $this->assertDatabaseHas('submittal_revisions', ['submittal_id' => $submittal->id, 'revision_no' => 1, 'decision' => 'rejected']);
        $this->assertDatabaseHas('submittal_revisions', ['submittal_id' => $submittal->id, 'revision_no' => 2, 'decision' => null]);
    }

    public function test_approve_conflicts_when_revision_already_decided(): void
    {
        $submittal = $this->submittedSubmittal();

        DB::table('submittal_revisions')
            ->where('submittal_id', $submittal->id)
            ->update([
                'decision' => 'rejected',
                'decided_by' => $this->user->id,
                'decided_at' => now(),
                'decision_comments' => 'raced by another request',
            ]);

        $this->expectException(SubmittalTransitionConflictException::class);

        $this->service->approve($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_start_revision_from_approved_is_rejected(): void
    {
        $submittal = $this->submittedSubmittal();
        $approved = $this->service->approve($submittal, ['actor_user_id' => $this->user->id]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->startRevision($approved, ['actor_user_id' => $this->user->id]);
    }
}
