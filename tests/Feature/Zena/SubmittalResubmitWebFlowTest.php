<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalResubmitWebFlowTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    public function test_full_reject_reopen_edit_resubmit_approve_flow_via_web_routes(): void
    {
        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);

        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            [],
            ['admin'],
            ['submittal.view', 'submittal.create', 'submittal.edit', 'submittal.submit', 'submittal.approve', 'submittal.reject']
        );
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id, 'code' => 'PRJ-FLOW-001']);
        $headers = ['X-Tenant-ID' => (string) $tenant->id];

        // 1. Create as draft, submit (revision 1).
        // Establish session so csrf_token() is available for the subsequent mutating requests.
        $this->actingAs($user)->get(route('operator.submittals.index'), $headers);

        $create = $this->actingAs($user)->post(route('operator.submittals.store'), [
            'project_id' => (string) $project->id,
            'title' => 'Steel connection detail',
            'description' => 'Initial submission for review.',
            'submittal_type' => 'shop_drawing',
        ], $headers);
        $submittal = Submittal::query()->where('title', 'Steel connection detail')->firstOrFail();
        $create->assertRedirect(route('operator.submittals.show', $submittal->id));

        $this->actingAs($user)->post(route('operator.submittals.submit', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        // 2. Reject.
        $this->actingAs($user)->post(route('operator.submittals.reject', $submittal->id), [
            'rejection_reason' => 'Missing weld callouts',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('rejected', $submittal->status);
        $revisionOneSnapshotBefore = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();

        // 3. Reopen for revision — must NOT create a second revision row yet.
        $this->actingAs($user)->post(route('operator.submittals.start-revision', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('revising', $submittal->status);
        $this->assertSame(1, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());

        // 4. Edit content while revising — must not touch the rejected revision row.
        $this->actingAs($user)->put(route('operator.submittals.update', $submittal->id), [
            'title' => 'Steel connection detail (revised)',
            'description' => 'Added weld callouts per comments.',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('Steel connection detail (revised)', $submittal->title);
        $revisionOneSnapshotAfterEdit = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();
        unset($revisionOneSnapshotBefore['updated_at'], $revisionOneSnapshotAfterEdit['updated_at']);
        $this->assertSame($revisionOneSnapshotBefore, $revisionOneSnapshotAfterEdit, 'Editing while revising must not mutate the rejected revision row.');

        // 5. Rejection reason must still display after the edit (still revising).
        $show = $this->actingAs($user)->get(route('operator.submittals.show', $submittal->id), $headers);
        $show->assertSee('Missing weld callouts');

        // 6. Resubmit with revision_summary — creates revision 2, revision 1 stays untouched.
        $this->actingAs($user)->post(route('operator.submittals.submit', $submittal->id), [
            'revision_summary' => 'Added weld callouts to all connection points',
        ], $headers)->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('submitted', $submittal->status);
        $this->assertSame(2, $submittal->current_revision_no);
        $this->assertSame(2, SubmittalRevision::query()->where('submittal_id', $submittal->id)->count());

        $revisionTwo = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 2)->firstOrFail();
        $this->assertSame('Steel connection detail (revised)', $revisionTwo->title);
        $this->assertSame('Added weld callouts to all connection points', $revisionTwo->revision_summary);
        $this->assertNull($revisionTwo->decision);

        $revisionOneFinal = SubmittalRevision::query()->where('submittal_id', $submittal->id)->where('revision_no', 1)->firstOrFail()->toArray();
        unset($revisionOneFinal['updated_at']);
        $this->assertSame($revisionOneSnapshotBefore, $revisionOneFinal, 'Revision 1 must remain byte-identical through the entire resubmit flow.');

        // 7. Approve the resubmission.
        $this->actingAs($user)->post(route('operator.submittals.approve', $submittal->id), [], $headers)
            ->assertRedirect(route('operator.submittals.show', $submittal->id));

        $submittal->refresh();
        $this->assertSame('approved', $submittal->status);

        $eventKeys = DB::table('event_records')
            ->where('aggregate_type', 'submittal')
            ->where('aggregate_id', $submittal->id)
            ->orderBy('occurred_at')
            ->pluck('event_key')
            ->all();
        $this->assertSame(
            ['submittal.submitted', 'submittal.rejected', 'submittal.revision_started', 'submittal.content_updated', 'submittal.resubmitted', 'submittal.approved'],
            $eventKeys
        );
    }
}
