<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectMilestones;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UpcomingMilestoneQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-117 (Gate 2 L-1, E-1, A-1) — per-project "Mốc tiến độ" page: list with live status badges; forms only for the
 * project PM or project.update holders; writes through POST routes; other tenants get 404; milestones created here
 * show on OPPM and in the Today query.
 */
class ProjectMilestonePageTest extends TestCase
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
        Carbon::setTestNow('2026-11-10 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->viewer = $this->createTenantUser($this->tenant, [], ['msp_viewer'], ['project.view']);
        $this->editor = $this->createTenantUser($this->tenant, [], ['msp_editor'], ['project.view', 'project.update']);
        $this->pm = $this->createTenantUser($this->tenant, [], ['msp_pm'], ['project.view']);
        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'name' => 'Nhà phố Quận 7', 'status' => 'active',
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'pm_id' => (string) $this->pm->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_editor_manages_milestones_through_the_page(): void
    {
        $this->open($this->editor)->assertOk()->assertSee('Chưa có mốc')->assertSee('milestone-create-form', false);

        $this->send($this->editor, 'app.projects.milestones.store', ['name' => 'Xong móng', 'target_date' => '2026-11-01', 'order' => '1'])
            ->assertRedirect(route('app.projects.milestones.index', (string) $this->project->id));
        $this->send($this->pm, 'app.projects.milestones.store', ['name' => 'Bàn giao', 'target_date' => '2027-03-30', 'order' => '2'])
            ->assertRedirect();
        $foundation = ProjectMilestone::query()->where('name', 'Xong móng')->sole();
        $handover = ProjectMilestone::query()->where('name', 'Bàn giao')->sole();

        $this->open($this->viewer)->assertOk()
            ->assertSeeInOrder(['Xong móng', 'Quá hạn', 'Bàn giao', 'Đang chờ'])
            ->assertDontSee('milestone-create-form', false)
            ->assertDontSee('milestone-actions', false);

        $this->send($this->editor, 'app.projects.milestones.update', ['name' => 'Xong móng', 'target_date' => '2026-11-20', 'order' => '1'], ['milestone' => (string) $foundation->id])
            ->assertRedirect();
        $this->open($this->viewer)->assertDontSee('Quá hạn');

        $this->send($this->editor, 'app.projects.milestones.complete', ['completed_date' => '2026-11-09'], ['milestone' => (string) $foundation->id])->assertRedirect();
        $this->assertSame(ProjectMilestone::STATUS_COMPLETED, $foundation->refresh()->status);
        $this->send($this->editor, 'app.projects.milestones.cancel', ['reason' => 'Đổi phạm vi'], ['milestone' => (string) $handover->id])->assertRedirect();
        $this->assertSame(ProjectMilestone::STATUS_CANCELLED, $handover->refresh()->status);
        $this->open($this->viewer)->assertSee('Xong')->assertSee('Đã huỷ')->assertSee('Đổi phạm vi');
        $this->send($this->editor, 'app.projects.milestones.reopen', [], ['milestone' => (string) $handover->id])->assertRedirect();
        $this->assertSame(ProjectMilestone::STATUS_PENDING, $handover->refresh()->status);

        // Validation errors come back to the form.
        $this->send($this->editor, 'app.projects.milestones.store', ['name' => '', 'target_date' => '2026-12-01'])->assertSessionHasErrors('name');
        $this->send($this->editor, 'app.projects.milestones.cancel', ['reason' => ''], ['milestone' => (string) $handover->id])->assertSessionHasErrors('reason');
        $this->send($this->editor, 'app.projects.milestones.cancel', ['reason' => 'x'], ['milestone' => (string) $foundation->id])->assertSessionHasErrors('status');
    }

    public function test_viewer_cannot_write_and_other_tenants_get_404(): void
    {
        $m = ProjectMilestone::query()->create(['project_id' => (string) $this->project->id, 'name' => 'Xong móng', 'target_date' => '2026-12-01']);

        $this->send($this->viewer, 'app.projects.milestones.store', ['name' => 'X', 'target_date' => '2026-12-01'])->assertForbidden();
        $this->send($this->viewer, 'app.projects.milestones.complete', [], ['milestone' => (string) $m->id])->assertForbidden();
        $this->assertSame(1, ProjectMilestone::query()->count());

        $otherTenant = Tenant::factory()->create();
        $stranger = $this->createTenantUser($otherTenant, [], ['msp_stranger'], ['project.view', 'project.update']);
        $this->actingAs($stranger)->get(route('app.projects.milestones.index', (string) $this->project->id))->assertNotFound();
        $this->actingAs($stranger)->post(route('app.projects.milestones.store', (string) $this->project->id), ['name' => 'X', 'target_date' => '2026-12-01'])->assertNotFound();

        // A milestone id of another project cannot be used through this project.
        $other = Project::factory()->create(['tenant_id' => (string) $this->tenant->id]);
        $foreign = ProjectMilestone::query()->create(['project_id' => (string) $other->id, 'name' => 'Khác', 'target_date' => '2026-12-01']);
        $this->send($this->editor, 'app.projects.milestones.complete', [], ['milestone' => (string) $foreign->id])->assertNotFound();

        $noView = $this->createTenantUser($this->tenant, [], ['msp_none'], []);
        $this->actingAs($noView)->get(route('app.projects.milestones.index', (string) $this->project->id))->assertRedirect(); // rbac middleware sends web users without project.view away (as on Today).
    }

    public function test_links_and_milestones_show_on_oppm_and_today(): void
    {
        $this->send($this->editor, 'app.projects.milestones.store', ['name' => 'Xong móng', 'target_date' => '2026-11-01'])->assertRedirect();
        $this->send($this->editor, 'app.projects.milestones.store', ['name' => 'Xong thân', 'target_date' => '2026-11-25'])->assertRedirect();

        $milestonesUrl = route('app.projects.milestones.index', (string) $this->project->id);
        $this->actingAs($this->viewer)->get(route('app.projects.show', (string) $this->project->id))->assertOk()->assertSee($milestonesUrl, false);
        $this->actingAs($this->viewer)->get(route('app.projects.oppm', (string) $this->project->id))->assertOk()
            ->assertSee('0/2 hoàn thành · 1 quá hạn')
            ->assertSee($milestonesUrl, false);

        $today = app(UpcomingMilestoneQuery::class)->build((string) $this->tenant->id, (string) $this->pm->id, [(string) $this->project->id]);
        $this->assertSame(['Xong móng', 'Xong thân'], array_map(fn ($item) => $item->name, $today->items));
    }

    private function open(User $user): TestResponse
    {
        return $this->actingAs($user)->get(route('app.projects.milestones.index', (string) $this->project->id));
    }

    private function send(User $user, string $route, array $data = [], array $params = []): TestResponse
    {
        $this->open($user);

        return $this->actingAs($user)->post(route($route, array_merge(['project' => (string) $this->project->id], $params)), $data);
    }
}
