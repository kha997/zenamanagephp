<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectOppm\OppmWorkstreamNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectOppm\OppmOverlayService;
use App\Services\ProjectOppm\OppmRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-110 (Gate 2 A-1, W-1, E-1, Q-1) — OPPM management overlay: per root component (outcome, weight, next action,
 * note, display order) and a project-level block; weight is display-only; edits by the project PM or project.update
 * holders, audited; tenant / project isolation; fixed query count.
 */
class ProjectOppmOverlayTest extends TestCase
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

    public function test_save_rules_permissions_and_audit(): void
    {
        $service = app(OppmOverlayService::class);
        $a = $this->makeComponent(['name' => 'Phần móng']);
        $child = $this->makeComponent(['name' => 'Cọc', 'parent_component_id' => (string) $a->id]);
        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $foreignRoot = $this->makeComponent(['name' => 'Khác'], $other);

        $service->saveWorkstream($this->project, $this->editor, (string) $a->id, [
            'outcome' => 'Móng nghiệm thu', 'weight' => '60', 'next_action' => 'Đổ bê tông đài', 'next_action_owner_id' => (string) $this->viewer->id,
            'next_action_due' => '2026-11-20', 'note' => 'Chờ thép', 'display_order' => '2',
        ]);
        $note = OppmWorkstreamNote::query()->sole();
        $this->assertSame('Móng nghiệm thu', $note->outcome);
        $this->assertSame('60.00', $note->weight);
        $this->assertSame('2026-11-20', $note->next_action_due?->toDateString());

        // PM of the project may edit; saving again updates the same row.
        $service->saveWorkstream($this->project, $this->pm, (string) $a->id, ['outcome' => 'Móng xong', 'weight' => '50']);
        $this->assertSame(1, OppmWorkstreamNote::query()->count());
        $this->assertSame('Móng xong', OppmWorkstreamNote::query()->sole()->outcome);
        $this->assertNull(OppmWorkstreamNote::query()->sole()->next_action);

        $this->assertRule(fn () => $service->saveWorkstream($this->project, $this->editor, (string) $child->id, ['outcome' => 'x']), 'nhóm công việc');
        $this->assertRule(fn () => $service->saveWorkstream($this->project, $this->editor, (string) $foreignRoot->id, ['outcome' => 'x']), 'nhóm công việc');
        $this->assertRule(fn () => $service->saveWorkstream($this->project, $this->editor, (string) $a->id, ['weight' => '120']), '0 đến 100');
        $stranger = $this->createTenantUser(Tenant::factory()->create(), [], ['oppm_stranger'], ['project.view']);
        $this->assertRule(fn () => $service->saveWorkstream($this->project, $this->editor, (string) $a->id, ['next_action_owner_id' => (string) $stranger->id]), 'Người làm');
        $this->assertRule(fn () => $service->saveWorkstream($this->project, $this->viewer, (string) $a->id, ['outcome' => 'x']), 'quyền');
        $this->assertRule(fn () => $service->saveProject($this->project, $this->viewer, ['summary' => 'x']), 'quyền');

        $service->saveProject($this->project, $this->editor, ['summary' => 'Dự án đúng tiến độ', 'next_action' => 'Họp chủ đầu tư', 'next_action_due' => '2026-11-10']);
        $this->assertSame('Dự án đúng tiến độ', $service->overlayFor($this->project)['project']['summary']);

        $this->assertSame(2, AuditLog::query()->where('action', 'oppm.workstream_note.saved')->count());
        $second = AuditLog::query()->where('action', 'oppm.workstream_note.saved')->orderByDesc('created_at')->orderByDesc('id')->get()->firstWhere(fn ($log) => ($log->old_data['outcome'] ?? null) === 'Móng nghiệm thu');
        $this->assertNotNull($second);
        $this->assertSame(1, AuditLog::query()->where('action', 'oppm.project_note.saved')->count());
    }

    public function test_page_shows_overlay_in_display_order_with_weight_total_and_unchanged_progress(): void
    {
        $a = $this->makeComponent(['name' => 'Phần móng', 'start_date' => '2026-02-01', 'progress_percent' => 100, 'planned_cost' => 100]);
        $b = $this->makeComponent(['name' => 'Phần thân', 'start_date' => '2026-05-01', 'progress_percent' => 20, 'planned_cost' => 300]);
        $before = $this->open($this->viewer)->assertOk()->assertSeeInOrder(['Phần móng', 'Phần thân'])->viewData('oppm')['health']['progress'];

        $service = app(OppmOverlayService::class);
        $service->saveWorkstream($this->project, $this->editor, (string) $a->id, ['outcome' => 'Móng nghiệm thu', 'weight' => '60', 'display_order' => '2', 'next_action' => 'Đổ bê tông đài']);
        $service->saveWorkstream($this->project, $this->editor, (string) $b->id, ['weight' => '30', 'display_order' => '1']);
        $service->saveProject($this->project, $this->editor, ['summary' => 'Cần tăng tốc phần thân']);

        $response = $this->open($this->viewer)->assertOk()
            ->assertSeeInOrder(['Phần thân', 'Phần móng'])
            ->assertSee('Móng nghiệm thu')->assertSee('Đổ bê tông đài')->assertSee('Cần tăng tốc phần thân')
            ->assertSee('Tổng trọng số: 90')->assertSee('khác 100')
            ->assertDontSee('oppm-edit-workstream', false)->assertDontSee('oppm-close-form', false);
        // W-1: weights never change the progress figure.
        $this->assertSame($before, $response->viewData('oppm')['health']['progress']);

        $this->open($this->editor)->assertOk()->assertSee('oppm-edit-workstream', false)->assertSee('oppm-edit-summary', false);
        $this->open($this->pm)->assertOk()->assertSee('oppm-edit-workstream', false);
    }

    public function test_web_save_permissions_validation_and_isolation(): void
    {
        $a = $this->makeComponent(['name' => 'Phần móng']);
        $this->send($this->editor, 'app.projects.oppm.workstreams.save', ['outcome' => 'Xong móng', 'weight' => '40'], ['component' => (string) $a->id])
            ->assertRedirect(route('app.projects.oppm', (string) $this->project->id));
        $this->assertSame('Xong móng', OppmWorkstreamNote::query()->sole()->outcome);
        $this->send($this->editor, 'app.projects.oppm.workstreams.save', ['weight' => '200'], ['component' => (string) $a->id])->assertSessionHasErrors('weight');
        $this->send($this->editor, 'app.projects.oppm.summary.save', ['summary' => 'Tóm tắt'])->assertRedirect(route('app.projects.oppm', (string) $this->project->id));
        $this->send($this->viewer, 'app.projects.oppm.workstreams.save', ['outcome' => 'Sửa trộm'], ['component' => (string) $a->id])->assertForbidden();
        $this->send($this->viewer, 'app.projects.oppm.summary.save', ['summary' => 'Sửa trộm'])->assertForbidden();
        $this->assertSame('Xong móng', OppmWorkstreamNote::query()->sole()->outcome);

        $otherTenant = Tenant::factory()->create();
        $outsider = $this->createTenantUser($otherTenant, [], ['oppm_outsider'], ['project.view', 'project.update']);
        $this->actingAs($outsider)->post(route('app.projects.oppm.summary.save', (string) $this->project->id), ['summary' => 'x'], ['X-Tenant-ID' => (string) $otherTenant->id])->assertNotFound();
    }

    public function test_query_count_does_not_grow_with_overlay_rows(): void
    {
        $service = app(OppmOverlayService::class);
        $first = $this->makeComponent(['name' => 'Gốc 0']);
        $service->saveWorkstream($this->project, $this->editor, (string) $first->id, ['outcome' => 'x', 'next_action_owner_id' => (string) $this->viewer->id]);
        $few = $this->countQueries();

        foreach (range(1, 10) as $i) {
            $component = $this->makeComponent(['name' => "Gốc {$i}"]);
            $service->saveWorkstream($this->project, $this->editor, (string) $component->id, ['outcome' => "Kết quả {$i}", 'weight' => '5', 'next_action_owner_id' => (string) $this->pm->id]);
        }
        $this->assertSame($few, $this->countQueries());
    }

    private function countQueries(): int
    {
        $this->open($this->viewer)->assertOk();
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->open($this->viewer)->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
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
