<?php declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Exceptions\SubmittalTransitionNotAllowedException;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubmittalLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmittalLifecycleServiceSubmitTest extends TestCase
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

    private function makeDraft(): Submittal
    {
        return Submittal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'status' => Submittal::STATUS_DRAFT,
            'title' => 'Original title',
            'submittal_number' => 'SUB-0001',
        ]);
    }

    public function test_first_submit_creates_revision_one(): void
    {
        $submittal = $this->makeDraft();

        $result = $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);

        $this->assertSame(Submittal::STATUS_SUBMITTED, $result->status);
        $this->assertSame(1, $result->current_revision_no);
        $this->assertDatabaseHas('submittal_revisions', [
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Original title',
        ]);
    }

    public function test_submit_from_approved_is_rejected(): void
    {
        $submittal = $this->makeDraft();
        $submittal->update(['status' => Submittal::STATUS_APPROVED]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->submit($submittal, ['actor_user_id' => $this->user->id]);
    }

    public function test_update_content_blocked_when_submitted(): void
    {
        $submittal = $this->makeDraft();
        $submittal->update(['status' => Submittal::STATUS_SUBMITTED]);

        $this->expectException(SubmittalTransitionNotAllowedException::class);

        $this->service->updateContent($submittal, ['title' => 'New title'], ['actor_user_id' => $this->user->id]);
    }

    public function test_update_content_allowed_when_draft(): void
    {
        $submittal = $this->makeDraft();

        $result = $this->service->updateContent($submittal, ['title' => 'Edited title'], ['actor_user_id' => $this->user->id]);

        $this->assertSame('Edited title', $result->title);
    }
}
