<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectOppm\OppmSnapshot;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectOppm\OppmDecisionService;
use App\Services\ProjectOppm\OppmOverlayService;
use App\Services\ProjectOppm\OppmRuleViolation;
use App\Services\ProjectOppm\OppmSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-110 (Gate 2 K-1, P-1) — period snapshots: the full OPPM data (header, KPIs, work groups, overlay, open decisions,
 * attention, finance) stored append-only with a sha256; later data changes never alter it; history, view (print
 * layout), comparison with the previous snapshot from stored numbers only; permissions and isolation.
 */
class ProjectOppmSnapshotTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private Project $project;

    private User $viewer;

    private User $editor;

    private User $pm;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['oppm_viewer'], ['project.view']);
        $this->editor = $this->createTenantUser($this->tenant, [], ['oppm_editor'], ['project.view', 'project.update']);
        $this->pm = $this->createTenantUser($this->tenant, [], ['oppm_pm'], ['project.view']);
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7', 'code' => 'P-OPPM', 'status' => 'active',
            'budget_total' => 0, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'pm_id' => (string) $this->pm->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_close_stores_full_payload_with_hash_and_never_changes(): void
    {
        $component = $this->makeComponent(['name' => 'Phần móng', 'progress_percent' => 20, 'planned_cost' => 100, 'start_date' => '2026-02-01', 'end_date' => '2026-04-30']);
        app(OppmOverlayService::class)->saveWorkstream($this->project, $this->editor, (string) $component->id, ['outcome' => 'Móng nghiệm thu', 'weight' => '100']);
        app(OppmOverlayService::class)->saveProject($this->project, $this->editor, ['summary' => 'Ổn định']);
        $decision = app(OppmDecisionService::class)->create($this->project, $this->editor, ['title' => 'Chọn nhà thầu', 'due_date' => '2026-11-20']);

        $service = app(OppmSnapshotService::class);
        $this->assertRule(fn () => $service->close($this->project, $this->viewer, 'Tuần 45/2026'), 'quyền');
        $this->assertRule(fn () => $service->close($this->project, $this->editor, ' '), 'Tên kỳ');
        $snapshot = $service->close($this->project, $this->editor, 'Tuần 45/2026');

        $payload = $snapshot->payload;
        $this->assertSame('Nhà phố Quận 7', $payload['header']['name']);
        $this->assertSame('Phần móng', $payload['oppm']['workstreams'][0]['name']);
        $this->assertSame('Móng nghiệm thu', $payload['oppm']['workstreams'][0]['overlay']['outcome']);
        $this->assertSame('Ổn định', $payload['oppm']['overlay']['project']['summary']);
        $this->assertSame('Chọn nhà thầu', $payload['oppm']['decisions']['open'][0]['title']);
        $this->assertEquals(20, $payload['oppm']['health']['progress']['value']);
        $this->assertSame(hash('sha256', (string) json_encode($payload)), $snapshot->payload_hash);
        $this->assertSame(1, AuditLog::query()->where('action', 'oppm.snapshot.closed')->count());

        // Live data changes afterwards.
        $component->update(['name' => 'Móng (đổi tên)', 'progress_percent' => 80]);
        $this->project->update(['name' => 'Tên dự án mới']);
        app(OppmOverlayService::class)->saveWorkstream($this->project, $this->editor, (string) $component->id, ['outcome' => 'Kết quả mới']);
        app(OppmDecisionService::class)->decide($this->project, $this->editor, $decision, 'Nhà thầu B');

        $stored = OppmSnapshot::query()->sole();
        $this->assertSame($payload, $stored->payload);
        $this->assertSame($snapshot->payload_hash, hash('sha256', (string) json_encode($stored->payload)));
        $this->open($this->viewer, 'app.projects.oppm.snapshots.show', ['snapshot' => (string) $stored->id])->assertOk()
            ->assertSee('Nhà phố Quận 7')->assertSee('Phần móng')->assertSee('Móng nghiệm thu')->assertSee('Chọn nhà thầu')
            ->assertSee('Bản chốt')->assertSee('Tuần 45/2026')->assertDontSee('Tên dự án mới')->assertDontSee('Kết quả mới')
            ->assertSee('@media print', false)->assertDontSee('oppm-edit-workstream', false);

        $this->expectException(\LogicException::class);
        $stored->update(['period_label' => 'Sửa']);
    }

    public function test_snapshot_cannot_be_deleted(): void
    {
        $snapshot = app(OppmSnapshotService::class)->close($this->project, $this->editor, 'Tháng 11/2026');

        try {
            $snapshot->delete();
            $this->fail('Expected a LogicException.');
        } catch (\LogicException) {
            $this->assertSame(1, OppmSnapshot::query()->count());
        }
    }

    public function test_compare_with_previous_uses_stored_numbers(): void
    {
        $component = $this->makeComponent(['name' => 'Phần thân', 'progress_percent' => 20, 'planned_cost' => 100]);
        $task = Task::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'project_id' => (string) $this->project->id, 'status' => Task::STATUS_IN_PROGRESS,
            'blocked_at' => now(), 'component_id' => (string) $component->id, 'end_date' => null,
        ]);
        app(OppmDecisionService::class)->create($this->project, $this->editor, ['title' => 'Quyết định 1']);
        $service = app(OppmSnapshotService::class);
        $first = $service->close($this->project, $this->editor, 'Tuần 45/2026');
        $this->assertNull($service->compare($first)['previous']);

        $component->update(['progress_percent' => 50]);
        $task->update(['blocked_at' => null]);
        app(OppmDecisionService::class)->create($this->project, $this->editor, ['title' => 'Quyết định 2']);
        Carbon::setTestNow('2026-11-12 09:00:00');
        $second = $service->close($this->project, $this->editor, 'Tuần 46/2026');

        $diff = $service->compare($second);
        $this->assertSame((string) $first->id, $diff['previous']['id']);
        $this->assertEquals(20, $diff['progress']['before']);
        $this->assertEquals(50, $diff['progress']['after']);
        $this->assertEquals(30, $diff['progress']['delta']);
        $this->assertSame(['before' => 1, 'after' => 0, 'delta' => -1], $diff['attention']['blocked_tasks']);
        $this->assertSame(['before' => 1, 'after' => 2, 'delta' => 1], $diff['decisions_open']);
        $this->assertEquals(20, $diff['workstreams'][0]['before']);
        $this->assertEquals(50, $diff['workstreams'][0]['after']);

        $this->open($this->viewer, 'app.projects.oppm.snapshots.compare', ['snapshot' => (string) $second->id])->assertOk()
            ->assertSee('Tuần 45/2026')->assertSee('Tuần 46/2026')->assertSee('+30');
    }

    public function test_pages_permissions_and_isolation(): void
    {
        $this->send($this->editor, 'app.projects.oppm.snapshots.store', ['period_label' => 'Tuần 45/2026'])->assertRedirect();
        $snapshot = OppmSnapshot::query()->sole();
        $this->send($this->editor, 'app.projects.oppm.snapshots.store', ['period_label' => ''])->assertSessionHasErrors('period_label');
        $this->send($this->viewer, 'app.projects.oppm.snapshots.store', ['period_label' => 'Trộm'])->assertForbidden();
        $this->assertSame(1, OppmSnapshot::query()->count());

        $this->open($this->viewer, 'app.projects.oppm.snapshots')->assertOk()->assertSee('Tuần 45/2026');
        $this->open($this->editor)->assertOk()->assertSee('oppm-close-form', false);

        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $foreign = app(OppmSnapshotService::class)->close($other, $this->editor, 'Dự án khác');
        $this->open($this->viewer, 'app.projects.oppm.snapshots.show', ['snapshot' => (string) $foreign->id])->assertNotFound();

        $otherTenant = Tenant::factory()->create();
        $outsider = $this->createTenantUser($otherTenant, [], ['oppm_outsider'], ['project.view', 'project.update']);
        $this->actingAs($outsider)->get(route('app.projects.oppm.snapshots.show', ['project' => (string) $this->project->id, 'snapshot' => (string) $snapshot->id]), ['X-Tenant-ID' => (string) $otherTenant->id])->assertNotFound();
    }

    private function open(User $user, ?string $route = null, array $params = []): TestResponse
    {
        return $this->actingAs($user)->get(route($route ?? 'app.projects.oppm', array_merge(['project' => (string) $this->project->id], $params)), ['X-Tenant-ID' => (string) $this->tenant->id]);
    }

    private function send(User $user, string $route, array $data = [], array $params = []): TestResponse
    {
        $this->actingAs($user)->get(route('app.projects.oppm', (string) $this->project->id), ['X-Tenant-ID' => (string) $this->tenant->id]);

        return $this->actingAs($user)->post(route($route, array_merge(['project' => (string) $this->project->id], $params)), $data, ['X-Tenant-ID' => (string) $this->tenant->id]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeComponent(array $attributes, ?Project $project = null): Component
    {
        $project ??= $this->project;

        return Component::factory()->create(array_merge([
            'tenant_id' => (string) $project->tenant_id,
            'project_id' => (string) $project->id,
            'parent_component_id' => null,
            'progress_percent' => 0,
            'planned_cost' => 0,
        ], $attributes));
    }

    private function assertRule(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected an OppmRuleViolation.');
        } catch (OppmRuleViolation $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
