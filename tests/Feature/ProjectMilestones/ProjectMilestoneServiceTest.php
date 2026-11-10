<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectMilestones;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectMilestoneService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-117 (Gate 2 A-1, E-1, T-1, O-1) — milestone authoring service: create / update / complete / reopen / cancel,
 * edits by the project PM or project.update holders, audited before / after; tenant_id filled from the project;
 * "overdue" is computed live, never stored; the migration backfills tenant_id and normalizes stored overdue rows.
 */
class ProjectMilestoneServiceTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private Project $project;

    private User $viewer;

    private User $editor;

    private User $pm;

    private ProjectMilestoneService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['ms_viewer'], ['project.view']);
        $this->editor = $this->createTenantUser($this->tenant, [], ['ms_editor'], ['project.view', 'project.update']);
        $this->pm = $this->createTenantUser($this->tenant, [], ['ms_pm'], ['project.view']);
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7', 'status' => 'active',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'pm_id' => (string) $this->pm->id,
        ]);
        $this->service = app(ProjectMilestoneService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_create_update_and_list_in_order_with_tenant_filled_and_audit(): void
    {
        $b = $this->service->create($this->project, $this->editor, ['name' => 'Xong thân', 'target_date' => '2027-02-01', 'order' => '2']);
        $a = $this->service->create($this->project, $this->pm, ['name' => ' Xong móng ', 'description' => 'Nghiệm thu móng', 'target_date' => '2026-12-15', 'order' => '1']);

        $this->assertSame('Xong móng', $a->name);
        $this->assertSame((string) $this->tenant->id, (string) $a->tenant_id);
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $a->status);
        $this->assertSame((string) $this->pm->id, (string) $a->created_by);
        $this->assertSame([(string) $a->id, (string) $b->id], $this->service->listForProject($this->project)->pluck('id')->map(fn ($id) => (string) $id)->all());

        $this->service->update($this->project, $this->editor, (string) $a->id, ['name' => 'Xong móng và đà kiềng', 'target_date' => '2026-12-20', 'order' => '1']);
        $a->refresh();
        $this->assertSame('Xong móng và đà kiềng', $a->name);
        $this->assertSame('2026-12-20', $a->target_date?->toDateString());
        $this->assertNull($a->description);

        $this->assertSame(2, AuditLog::query()->where('action', 'milestone.created')->count());
        $log = AuditLog::query()->where('action', 'milestone.updated')->sole();
        $this->assertSame('Xong móng', $log->old_data['name']);
        $this->assertSame('Xong móng và đà kiềng', $log->new_data['name']);
        $this->assertSame((string) $this->project->id, (string) $log->project_id);
        $this->assertSame((string) $a->id, (string) $log->entity_id);
    }

    public function test_validation_rules(): void
    {
        $this->assertInvalid(fn () => $this->service->create($this->project, $this->editor, ['name' => '  ', 'target_date' => '2026-12-01']), 'name');
        $this->assertInvalid(fn () => $this->service->create($this->project, $this->editor, ['name' => 'X']), 'target_date');
        $this->assertInvalid(fn () => $this->service->create($this->project, $this->editor, ['name' => 'X', 'target_date' => '01/12/2026']), 'target_date');
        $this->assertInvalid(fn () => $this->service->create($this->project, $this->editor, ['name' => str_repeat('a', 256), 'target_date' => '2026-12-01']), 'name');
        $this->assertInvalid(fn () => $this->service->create($this->project, $this->editor, ['name' => 'X', 'target_date' => '2026-12-01', 'order' => '1000']), 'order');

        $m = $this->service->create($this->project, $this->editor, ['name' => 'Bàn giao', 'target_date' => '2026-12-01']);
        $this->assertInvalid(fn () => $this->service->complete($this->project, $this->editor, (string) $m->id, '2026-11-11'), 'completed_date');
        $this->assertInvalid(fn () => $this->service->cancel($this->project, $this->editor, (string) $m->id, ' '), 'reason');
        $this->assertInvalid(fn () => $this->service->reopen($this->project, $this->editor, (string) $m->id), 'status');

        $this->service->cancel($this->project, $this->editor, (string) $m->id, 'Chủ đầu tư đổi phạm vi');
        $this->assertInvalid(fn () => $this->service->update($this->project, $this->editor, (string) $m->id, ['name' => 'Y', 'target_date' => '2026-12-01']), 'status');
        $this->assertInvalid(fn () => $this->service->complete($this->project, $this->editor, (string) $m->id, null), 'status');
        $this->assertInvalid(fn () => $this->service->cancel($this->project, $this->editor, (string) $m->id, 'lần hai'), 'status');
    }

    public function test_complete_reopen_cancel_lifecycle_and_audit(): void
    {
        $m = $this->service->create($this->project, $this->editor, ['name' => 'Xong móng', 'target_date' => '2026-11-01']);

        $this->service->complete($this->project, $this->pm, (string) $m->id, null);
        $m->refresh();
        $this->assertSame(ProjectMilestone::STATUS_COMPLETED, $m->status);
        $this->assertSame('2026-11-10', $m->completed_date?->toDateString());
        $this->assertFalse($m->isOverdue());

        $this->service->reopen($this->project, $this->editor, (string) $m->id);
        $m->refresh();
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $m->status);
        $this->assertNull($m->completed_date);

        $this->service->complete($this->project, $this->editor, (string) $m->id, '2026-11-05');
        $this->assertSame('2026-11-05', $m->refresh()->completed_date?->toDateString());
        $this->service->reopen($this->project, $this->editor, (string) $m->id);

        $this->service->cancel($this->project, $this->editor, (string) $m->id, 'Gộp vào mốc khác');
        $m->refresh();
        $this->assertSame(ProjectMilestone::STATUS_CANCELLED, $m->status);
        $this->assertSame('Gộp vào mốc khác', $m->metadata['cancelled_reason'] ?? null);
        $this->service->reopen($this->project, $this->editor, (string) $m->id);
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $m->refresh()->status);

        $this->assertSame(2, AuditLog::query()->where('action', 'milestone.completed')->count());
        $this->assertSame(3, AuditLog::query()->where('action', 'milestone.reopened')->count());
        $cancel = AuditLog::query()->where('action', 'milestone.cancelled')->sole();
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $cancel->old_data['status']);
        $this->assertSame('Gộp vào mốc khác', $cancel->new_data['cancelled_reason']);
    }

    public function test_permissions_and_isolation(): void
    {
        $m = $this->service->create($this->project, $this->editor, ['name' => 'Xong móng', 'target_date' => '2026-12-01']);
        $this->assertTrue($this->service->canEdit($this->editor, $this->project));
        $this->assertTrue($this->service->canEdit($this->pm, $this->project));
        $this->assertFalse($this->service->canEdit($this->viewer, $this->project));

        $this->assertDenied(fn () => $this->service->create($this->project, $this->viewer, ['name' => 'X', 'target_date' => '2026-12-01']));
        $this->assertDenied(fn () => $this->service->update($this->project, $this->viewer, (string) $m->id, ['name' => 'X', 'target_date' => '2026-12-01']));
        $this->assertDenied(fn () => $this->service->complete($this->project, $this->viewer, (string) $m->id, null));
        $this->assertDenied(fn () => $this->service->cancel($this->project, $this->viewer, (string) $m->id, 'x'));
        $this->assertDenied(fn () => $this->service->reopen($this->project, $this->viewer, (string) $m->id));

        $otherTenant = Tenant::factory()->create();
        $stranger = $this->createTenantUser($otherTenant, [], ['ms_stranger'], ['project.view', 'project.update']);
        $this->assertFalse($this->service->canEdit($stranger, $this->project));
        $this->assertDenied(fn () => $this->service->create($this->project, $stranger, ['name' => 'X', 'target_date' => '2026-12-01']));

        // A milestone of another project (same tenant) cannot be reached through this project.
        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $foreign = $this->service->create($other, $this->editor, ['name' => 'Khác', 'target_date' => '2026-12-01']);
        $this->expectException(ModelNotFoundException::class);
        $this->service->complete($this->project, $this->editor, (string) $foreign->id, null);
    }

    public function test_overdue_is_live_and_never_stored(): void
    {
        $m = $this->service->create($this->project, $this->editor, ['name' => 'Xong móng', 'target_date' => '2026-11-01']);
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $m->refresh()->status);
        $this->assertTrue($m->isOverdue());

        $this->service->update($this->project, $this->editor, (string) $m->id, ['name' => 'Xong móng', 'target_date' => '2026-11-30']);
        $m->refresh();
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $m->status);
        $this->assertFalse($m->isOverdue());

        // Due today is not overdue yet.
        $this->service->update($this->project, $this->editor, (string) $m->id, ['name' => 'Xong móng', 'target_date' => '2026-11-10']);
        $this->assertFalse($m->refresh()->isOverdue());
    }

    public function test_statistics_and_analytics_count_overdue_live(): void
    {
        // GAP-117 O-1 (Owner amendment 1): readers that used the stored "overdue" status now compute it live.
        $late = $this->service->create($this->project, $this->editor, ['name' => 'Trễ', 'target_date' => '2026-11-01']);
        $this->service->create($this->project, $this->editor, ['name' => 'Hôm nay', 'target_date' => '2026-11-10']);
        $this->service->create($this->project, $this->editor, ['name' => 'Sau', 'target_date' => '2026-12-01']);
        $done = $this->service->create($this->project, $this->editor, ['name' => 'Xong', 'target_date' => '2026-10-01']);
        $this->service->complete($this->project, $this->editor, (string) $done->id, '2026-10-02');

        $stats = ProjectMilestone::getProjectStatistics((string) $this->project->id);
        $this->assertSame(4, $stats['total']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(2, $stats['pending']);
        $this->assertSame(1, $stats['overdue']);
        $this->assertSame([(string) $late->id], ProjectMilestone::query()->overdue()->pluck('id')->map(fn ($id) => (string) $id)->all());

        // (ProjectAnalyticsService, which never ran end to end, was removed by GAP-119.)
    }

    public function test_migration_backfills_tenant_and_normalizes_stored_overdue(): void
    {
        $id = (string) Str::ulid();
        DB::table('project_milestones')->insert([
            'id' => $id, 'project_id' => (string) $this->project->id, 'name' => 'Mốc cũ', 'target_date' => '2026-10-01',
            'status' => 'overdue', 'order' => 0, 'tenant_id' => null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_10_100000_add_tenant_id_to_project_milestones.php');
        $migration->up();

        $row = DB::table('project_milestones')->where('id', $id)->first();
        $this->assertSame((string) $this->tenant->id, (string) $row->tenant_id);
        $this->assertSame('pending', $row->status);
        $this->assertTrue(ProjectMilestone::query()->findOrFail($id)->isOverdue());
    }

    private function assertInvalid(callable $action, string $field): void
    {
        try {
            $action();
            $this->fail("Expected a validation error on {$field}.");
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    private function assertDenied(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected an AuthorizationException.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
