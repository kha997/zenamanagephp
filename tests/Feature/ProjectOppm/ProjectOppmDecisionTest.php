<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOppm;

use App\Models\AuditLog;
use App\Models\Component;
use App\Models\Project;
use App\Models\ProjectOppm\OppmDecision;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProjectOppm\OppmDecisionService;
use App\Services\ProjectOppm\OppmRuleViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-110 (Gate 2 D-1, E-1) — per-project management decisions: open → decided (outcome required) or cancelled (reason
 * required); editable while open; the OPPM block shows at most five open ones, overdue first; audit; isolation.
 */
class ProjectOppmDecisionTest extends TestCase
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

    public function test_lifecycle_rules_and_audit(): void
    {
        $service = app(OppmDecisionService::class);
        $this->assertRule(fn () => $service->create($this->project, $this->viewer, ['title' => 'Chọn nhà thầu']), 'quyền');
        $this->assertRule(fn () => $service->create($this->project, $this->editor, ['title' => ' ']), 'Nội dung');
        $stranger = $this->createTenantUser(Tenant::factory()->create(), [], ['oppm_stranger'], ['project.view']);
        $this->assertRule(fn () => $service->create($this->project, $this->editor, ['title' => 'Chọn nhà thầu', 'decision_maker_id' => (string) $stranger->id]), 'Người quyết');

        $decision = $service->create($this->project, $this->pm, [
            'title' => 'Chọn nhà thầu phần thân', 'context' => 'Ba báo giá', 'decision_maker_id' => (string) $this->editor->id, 'due_date' => '2026-11-12',
        ]);
        $this->assertSame(OppmDecision::STATUS_OPEN, $decision->status);
        $service->update($this->project, $this->editor, $decision, ['title' => 'Chọn nhà thầu phần thân (gói 2)', 'due_date' => '2026-11-15']);
        $this->assertSame('2026-11-15', $decision->fresh()->due_date?->toDateString());

        $this->assertRule(fn () => $service->decide($this->project, $this->editor, $decision->fresh(), ' '), 'kết quả');
        $service->decide($this->project, $this->editor, $decision->fresh(), 'Chọn nhà thầu B');
        $fresh = $decision->fresh();
        $this->assertSame(OppmDecision::STATUS_DECIDED, $fresh->status);
        $this->assertSame('Chọn nhà thầu B', $fresh->outcome);
        $this->assertSame((string) $this->editor->id, $fresh->decided_by);
        $this->assertNotNull($fresh->decided_at);
        $this->assertRule(fn () => $service->update($this->project, $this->editor, $fresh, ['title' => 'Sửa']), 'đang chờ');
        $this->assertRule(fn () => $service->cancel($this->project, $this->editor, $fresh, 'Bỏ'), 'đang chờ');

        $second = $service->create($this->project, $this->editor, ['title' => 'Duyệt phương án mặt tiền']);
        $this->assertRule(fn () => $service->cancel($this->project, $this->editor, $second, ''), 'lý do');
        $service->cancel($this->project, $this->editor, $second, 'Chủ đầu tư rút yêu cầu');
        $this->assertSame(OppmDecision::STATUS_CANCELLED, $second->fresh()->status);

        foreach (['created' => 2, 'updated' => 1, 'decided' => 1, 'cancelled' => 1] as $event => $count) {
            $this->assertSame($count, AuditLog::query()->where('action', 'oppm.decision.' . $event)->count(), $event);
        }
    }

    public function test_oppm_block_shows_five_open_overdue_first(): void
    {
        $service = app(OppmDecisionService::class);
        foreach (range(1, 5) as $i) {
            $service->create($this->project, $this->editor, ['title' => "Quyết định tương lai {$i}", 'due_date' => '2026-12-0' . $i]);
        }
        $service->create($this->project, $this->editor, ['title' => 'Quyết định quá hạn A', 'due_date' => '2026-11-01']);
        $service->create($this->project, $this->editor, ['title' => 'Quyết định quá hạn B', 'due_date' => '2026-11-03']);
        $done = $service->create($this->project, $this->editor, ['title' => 'Quyết định đã xong']);
        $service->decide($this->project, $this->editor, $done, 'Xong');

        $this->open($this->viewer)->assertOk()
            ->assertSeeInOrder(['Quyết định cần xử lý', 'Quyết định quá hạn A', 'Quá hạn', 'Quyết định quá hạn B', 'Quyết định tương lai 1'])
            ->assertSee('Quyết định tương lai 3')->assertDontSee('Quyết định tương lai 4')->assertDontSee('Quyết định đã xong')
            ->assertSee('Xem tất cả (7 đang chờ)');
    }

    public function test_pages_permissions_and_isolation(): void
    {
        $service = app(OppmDecisionService::class);
        $this->send($this->editor, 'app.projects.oppm.decisions.store', ['title' => 'Chọn vật liệu lát sàn', 'decision_maker_id' => (string) $this->pm->id, 'due_date' => '2026-11-30'])
            ->assertRedirect(route('app.projects.oppm.decisions', (string) $this->project->id));
        $decision = OppmDecision::query()->sole();
        $this->send($this->editor, 'app.projects.oppm.decisions.store', ['title' => ''])->assertSessionHasErrors('title');
        $this->send($this->pm, 'app.projects.oppm.decisions.decide', ['outcome' => 'Gỗ công nghiệp'], ['decision' => (string) $decision->id])
            ->assertRedirect(route('app.projects.oppm.decisions', (string) $this->project->id));
        $this->assertSame(OppmDecision::STATUS_DECIDED, $decision->fresh()->status);

        $open = $service->create($this->project, $this->editor, ['title' => 'Chọn màu sơn']);
        $this->send($this->viewer, 'app.projects.oppm.decisions.store', ['title' => 'Trộm'])->assertForbidden();
        $this->send($this->viewer, 'app.projects.oppm.decisions.cancel', ['reason' => 'x'], ['decision' => (string) $open->id])->assertForbidden();
        $this->send($this->editor, 'app.projects.oppm.decisions.update', ['title' => 'Chọn màu sơn ngoài trời'], ['decision' => (string) $open->id])
            ->assertRedirect(route('app.projects.oppm.decisions', (string) $this->project->id));
        $this->send($this->editor, 'app.projects.oppm.decisions.cancel', ['reason' => 'Không cần nữa'], ['decision' => (string) $open->id])->assertRedirect();

        $this->open($this->viewer, 'app.projects.oppm.decisions')->assertOk()
            ->assertSee('Chọn vật liệu lát sàn')->assertSee('Gỗ công nghiệp')->assertSee('Chọn màu sơn ngoài trời')->assertSee('Không cần nữa')
            ->assertDontSee('oppm-decision-form', false);
        $this->open($this->editor, 'app.projects.oppm.decisions')->assertOk()->assertSee('oppm-decision-form', false);

        // A decision of another project cannot be reached through this project.
        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $foreign = $service->create($other, $this->editor, ['title' => 'Dự án khác']);
        $this->send($this->editor, 'app.projects.oppm.decisions.decide', ['outcome' => 'x'], ['decision' => (string) $foreign->id])->assertNotFound();
        $this->open($this->viewer, 'app.projects.oppm.decisions')->assertDontSee('Dự án khác');

        $otherTenant = Tenant::factory()->create();
        $outsider = $this->createTenantUser($otherTenant, [], ['oppm_outsider'], ['project.view', 'project.update']);
        $this->actingAs($outsider)->get(route('app.projects.oppm.decisions', (string) $this->project->id), ['X-Tenant-ID' => (string) $otherTenant->id])->assertNotFound();
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
