<?php

declare(strict_types=1);

namespace Tests\Feature\WorkTemplates;

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkInstance;
use App\Models\WorkTemplate;
use App\Models\WorkTemplateField;
use App\Models\WorkTemplateStep;
use App\Models\WorkTemplateVersion;
use Database\Seeders\WorkTemplateBaselineSeeder;
use Database\Seeders\WorkTemplateVietnameseBaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

/**
 * GAP-123 (Gate 2 E-1, P-1, S-1) — authoring work templates in the web app.
 *
 * - E-1: the editor writes the step subset that drives "apply" (name, type, after which steps, days, description,
 *   priority, phase, checklist, required documents) through WorkTemplateCrudService; custom fields of existing steps are
 *   preserved.
 * - P-1: editing / publishing needs template.edit_draft / template.publish (Owner); PMs only see the list.
 * - S-1: blank template, Duplicate, and three published Vietnamese baseline templates.
 */
class WorkTemplateAuthoringPageTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private Tenant $tenant;

    private User $owner;

    private User $pm;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Carbon::setTestNow('2026-11-11 09:00:00');
        $this->tenant = Tenant::factory()->create();
        $this->owner = $this->createTenantUser($this->tenant, ['name' => 'Chủ'], ['wt_owner'], [
            'template.view', 'template.edit_draft', 'template.publish', 'template.apply', 'project.view',
        ]);
        $this->pm = $this->createTenantUser($this->tenant, ['name' => 'PM'], ['wt_pm'], ['template.view', 'template.apply', 'project.view']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_owner_creates_edits_publishes_and_the_template_applies_to_a_project(): void
    {
        $template = $this->createTemplate('WT-NHA-PHO', 'Nhà phố 3 tầng');

        $this->actingAs($this->owner)->get(route('app.work-templates.edit', $template->id))->assertOk()
            ->assertSee('Nhà phố 3 tầng')->assertSee('name="steps[0][name]"', false);

        $this->actingAs($this->owner)->post(route('app.work-templates.update', $template->id), [
            'name' => 'Nhà phố 3 tầng',
            'description' => 'Trình tự chuẩn',
            'steps' => [
                ['order' => 1, 'name' => 'Khảo sát hiện trạng', 'type' => 'task', 'days' => 2, 'description' => 'Đo đạc, chụp ảnh',
                    'priority' => 'high', 'phase' => 'Chuẩn bị', 'checklist' => "* Đo kích thước lô đất\nChụp ảnh hiện trạng", 'docs' => '* Biên bản khảo sát'],
                ['order' => 2, 'name' => 'Thiết kế sơ bộ', 'type' => 'deliverable', 'days' => 5, 'after' => ['khao-sat-hien-trang'],
                    'priority' => 'medium', 'phase' => 'Thiết kế', 'checklist' => '', 'docs' => "* Bản vẽ mặt bằng\nPhối cảnh"],
                ['order' => 3, 'name' => 'Chủ nhà duyệt phương án', 'type' => 'approval', 'days' => 1, 'after' => ['thiet-ke-so-bo'],
                    'priority' => 'high', 'phase' => 'Thiết kế', 'checklist' => '', 'docs' => ''],
                ['order' => 4, 'name' => '', 'type' => 'task', 'days' => '', 'phase' => '', 'checklist' => '', 'docs' => ''],
            ],
        ])->assertRedirect(route('app.work-templates.edit', $template->id))->assertSessionHasNoErrors();

        $draft = $this->latestVersion($template);
        $this->assertNull($draft->published_at);
        $steps = WorkTemplateStep::query()->where('work_template_version_id', $draft->id)->orderBy('step_order')->get();
        $this->assertSame(['Khảo sát hiện trạng', 'Thiết kế sơ bộ', 'Chủ nhà duyệt phương án'], $steps->pluck('name')->all());
        $this->assertSame(['khao-sat-hien-trang'], $steps[1]->depends_on);
        $this->assertSame(48, $steps[0]->sla_hours);
        $this->assertSame(['role' => 'project_manager'], $steps[0]->assignee_rule_json);
        $this->assertSame('Chuẩn bị', $steps[0]->config_json['phase_name']);
        $this->assertSame([
            ['key' => 'do-kich-thuoc-lo-dat', 'label' => 'Đo kích thước lô đất', 'required' => true],
            ['key' => 'chup-anh-hien-trang', 'label' => 'Chụp ảnh hiện trạng', 'required' => false],
        ], $steps[0]->config_json['checklist_items']);
        $this->assertSame('Phối cảnh', $steps[1]->config_json['required_docs'][1]['label']);

        // Preview on the edit page lists the tasks with their day offsets.
        $this->actingAs($this->owner)->get(route('app.work-templates.edit', $template->id))->assertOk()
            ->assertSee('data-testid="work-template-preview"', false)->assertSee('Ngày 3 – 7');

        // Not offered for apply before publishing.
        $project = $this->project();
        $this->assertSame([], $this->applicable($project));

        $this->actingAs($this->owner)->post(route('app.work-templates.publish', $template->id))
            ->assertRedirect(route('app.work-templates.edit', $template->id));
        $this->assertSame(['WT-NHA-PHO'], $this->applicable($project));

        $this->actingAs($this->owner)->postJson("/app/projects/{$project->id}/work-templates/apply", ['work_template_id' => (string) $template->id])
            ->assertStatus(201);
        $tasks = Task::query()->where('project_id', (string) $project->id)->orderBy('order')->get();
        $this->assertSame(['Khảo sát hiện trạng', 'Thiết kế sơ bộ', 'Chủ nhà duyệt phương án'], $tasks->pluck('name')->all());
        $this->assertSame('2026-11-11', $tasks[0]->start_date?->toDateString());
        $this->assertSame('2026-11-13', $tasks[0]->end_date?->toDateString());
        $this->assertSame('2026-11-18', $tasks[1]->end_date?->toDateString());
        $this->assertSame([(string) $tasks[0]->id], json_decode((string) $tasks[1]->getRawOriginal('dependencies_json'), true));
        $this->assertSame((string) $this->owner->id, (string) $tasks[0]->assigned_to);

        $this->assertSame(1, AuditLog::query()->where('action', 'zena.work-template.publish')->where('entity_id', (string) $template->id)->count());
        $this->assertSame(1, AuditLog::query()->where('action', 'zena.work-template.create')->where('entity_id', (string) $template->id)->count());
    }

    public function test_editing_a_published_template_opens_a_new_draft_and_applied_projects_keep_the_old_version(): void
    {
        $template = $this->createTemplate('WT-SUA', 'Sửa mẫu');
        $this->saveSteps($template, [['order' => 1, 'name' => 'Bước A', 'type' => 'task', 'days' => 1]]);
        $this->actingAs($this->owner)->post(route('app.work-templates.publish', $template->id));
        $project = $this->project();
        $this->actingAs($this->owner)->postJson("/app/projects/{$project->id}/work-templates/apply", ['work_template_id' => (string) $template->id])->assertStatus(201);
        $appliedVersion = (string) WorkInstance::query()->where('project_id', (string) $project->id)->value('work_template_version_id');

        $this->saveSteps($template, [
            ['key' => 'buoc-a', 'order' => 1, 'name' => 'Bước A', 'type' => 'task', 'days' => 1],
            ['order' => 2, 'name' => 'Bước B', 'type' => 'task', 'days' => 2, 'after' => ['buoc-a']],
        ]);

        $versions = WorkTemplateVersion::query()->where('work_template_id', $template->id)->get();
        $this->assertSame(1, $versions->whereNull('published_at')->count());
        $this->assertSame(1, WorkTemplateStep::query()->where('work_template_version_id', $appliedVersion)->count());
        $this->assertSame(2, WorkTemplateStep::query()->where('work_template_version_id', (string) $versions->whereNull('published_at')->first()?->id)->count());
    }

    public function test_custom_fields_of_existing_steps_are_kept_and_duplicate_copies_everything(): void
    {
        $this->seed(WorkTemplateBaselineSeeder::class);
        /** @var WorkTemplate $design */
        $design = WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->where('code', 'WT-BL-DESIGN')->firstOrFail();
        $edit = $this->actingAs($this->owner)->get(route('app.work-templates.edit', $design->id))->assertOk();
        $edit->assertSee('Capture Design Inputs')->assertSee('3 trường nhập liệu riêng');

        // Rename the first step only; keep the others as they are on the form.
        $rows = $this->rowsFromVersion($this->latestVersion($design));
        $rows[0]['name'] = 'Thu thập đầu vào thiết kế';
        $this->saveSteps($design, $rows);
        $draft = $this->latestVersion($design);
        $first = WorkTemplateStep::query()->where('work_template_version_id', $draft->id)->where('step_key', 'design-intake')->firstOrFail();
        $this->assertSame('Thu thập đầu vào thiết kế', $first->name);
        $this->assertSame(3, WorkTemplateField::query()->where('work_template_step_id', $first->id)->count());
        $this->assertSame(['reviewers' => [], 'watchers' => []], $first->config_json['assignment_rules']);

        $this->actingAs($this->owner)->post(route('app.work-templates.duplicate', $design->id))->assertRedirect();
        /** @var WorkTemplate $copy */
        $copy = WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->where('code', 'WT-BL-DESIGN-COPY')->firstOrFail();
        $this->assertStringContainsString('Bản sao', $copy->name);
        $copyVersion = $this->latestVersion($copy);
        $this->assertNull($copyVersion->published_at);
        $copySteps = WorkTemplateStep::query()->where('work_template_version_id', $copyVersion->id)->get();
        $this->assertSame(4, $copySteps->count());
        $this->assertSame('Thu thập đầu vào thiết kế', $copySteps->firstWhere('step_key', 'design-intake')?->name);
        $this->assertSame(3, WorkTemplateField::query()->where('work_template_step_id', $copySteps->firstWhere('step_key', 'design-intake')?->id)->count());
    }

    public function test_validation_rejects_missing_names_duplicates_cycles_and_bad_days(): void
    {
        $template = $this->createTemplate('WT-LOI', 'Lỗi');
        $cases = [
            'name' => [['order' => 1, 'name' => '', 'type' => 'task', 'days' => 1, 'description' => 'Có mô tả nhưng không tên']],
            'duplicate' => [['order' => 1, 'name' => 'Đổ móng', 'type' => 'task', 'days' => 1], ['order' => 2, 'name' => 'Đổ móng', 'type' => 'task', 'days' => 1]],
            'days' => [['order' => 1, 'name' => 'Đổ móng', 'type' => 'task', 'days' => 400]],
            'after-below' => [['key' => 'a', 'order' => 1, 'name' => 'A', 'type' => 'task', 'days' => 1, 'after' => ['b']], ['key' => 'b', 'order' => 2, 'name' => 'B', 'type' => 'task', 'days' => 1]],
            'type' => [['order' => 1, 'name' => 'Đổ móng', 'type' => 'hack', 'days' => 1]],
        ];
        foreach ($cases as $label => $rows) {
            $this->actingAs($this->owner)->from(route('app.work-templates.edit', $template->id))
                ->post(route('app.work-templates.update', $template->id), ['name' => 'Lỗi', 'steps' => $rows])
                ->assertSessionHasErrors();
            $this->assertSame(0, WorkTemplateStep::query()->where('work_template_version_id', $this->latestVersion($template)->id)->count(), $label);
        }
    }

    public function test_pm_sees_the_list_but_cannot_create_edit_publish_or_duplicate(): void
    {
        $template = $this->createTemplate('WT-PM', 'Mẫu PM');

        $this->actingAs($this->pm)->get(route('app.work-templates.index'))->assertOk()->assertSee('Mẫu PM')
            ->assertDontSee(route('app.work-templates.create'), false);
        // RBAC on web routes: a browser is sent back with an error flash, JSON gets 403 (existing middleware behaviour).
        $this->actingAs($this->pm)->get(route('app.work-templates.create'))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->pm)->get(route('app.work-templates.edit', $template->id))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->pm)->getJson(route('app.work-templates.edit', $template->id))->assertForbidden();
        $this->actingAs($this->pm)->post(route('app.work-templates.update', $template->id), ['name' => 'x', 'steps' => [['order' => 1, 'name' => 'Lén', 'type' => 'task', 'days' => 1]]])
            ->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->pm)->post(route('app.work-templates.publish', $template->id))->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->pm)->post(route('app.work-templates.store'), ['code' => 'WT-PM-2', 'name' => 'Lén'])->assertRedirect()->assertSessionHas('error');
        $this->actingAs($this->pm)->post(route('app.work-templates.duplicate', $template->id))->assertRedirect()->assertSessionHas('error');

        $this->assertSame('Mẫu PM', $template->fresh()?->name);
        $this->assertSame(0, WorkTemplateStep::query()->where('work_template_version_id', $this->latestVersion($template)->id)->count());
        $this->assertSame(0, WorkTemplateVersion::query()->where('work_template_id', $template->id)->whereNotNull('published_at')->count());
        $this->assertSame(1, WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->count());
    }

    public function test_templates_of_other_companies_are_invisible(): void
    {
        $other = Tenant::factory()->create();
        $otherOwner = $this->createTenantUser($other, [], ['wt_owner'], ['template.view', 'template.edit_draft', 'template.publish']);
        $this->actingAs($otherOwner)->post(route('app.work-templates.store'), ['code' => 'WT-KHAC', 'name' => 'Mẫu công ty khác']);
        $foreign = WorkTemplate::query()->withoutGlobalScopes()->where('code', 'WT-KHAC')->firstOrFail();

        $this->actingAs($this->owner)->get(route('app.work-templates.index'))->assertOk()->assertDontSee('Mẫu công ty khác');
        $this->actingAs($this->owner)->get(route('app.work-templates.edit', $foreign->id))->assertNotFound();
        $this->actingAs($this->owner)->post(route('app.work-templates.publish', $foreign->id))->assertNotFound();
    }

    public function test_menu_lists_work_templates_for_template_viewers(): void
    {
        $this->actingAs($this->pm)->get(route('app.work-templates.index'))->assertOk()
            ->assertSee('href="' . route('app.work-templates.index') . '"', false)->assertSee('Mẫu công việc');
    }

    public function test_vietnamese_baselines_are_seeded_published_complete_and_idempotent(): void
    {
        $this->seed(WorkTemplateBaselineSeeder::class);
        $this->seed(WorkTemplateVietnameseBaselineSeeder::class);
        $this->seed(WorkTemplateVietnameseBaselineSeeder::class);

        $map = ['WT-VN-THIETKE' => 'WT-BL-DESIGN', 'WT-VN-THICONG' => 'WT-BL-CONSTRUCTION', 'WT-VN-NGHIEMTHU' => 'WT-BL-INSPECTION'];
        foreach ($map as $vn => $en) {
            $templates = WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->where('code', $vn)->get();
            $this->assertCount(1, $templates, $vn);
            $version = $this->latestVersion($templates->first());
            $this->assertNotNull($version->published_at, $vn);
            $vnSteps = WorkTemplateStep::query()->where('work_template_version_id', $version->id)->orderBy('step_order')->get();
            $enTemplate = WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->where('code', $en)->firstOrFail();
            $enSteps = WorkTemplateStep::query()->where('work_template_version_id', $this->latestVersion($enTemplate)->id)->orderBy('step_order')->get();
            $this->assertSame($enSteps->pluck('step_key')->all(), $vnSteps->pluck('step_key')->all(), $vn);
            $this->assertSame($enSteps->pluck('depends_on')->all(), $vnSteps->pluck('depends_on')->all(), $vn);
            $this->assertSame($enSteps->pluck('sla_hours')->all(), $vnSteps->pluck('sla_hours')->all(), $vn);
            $this->assertSame($enSteps->pluck('type')->all(), $vnSteps->pluck('type')->all(), $vn);
            foreach ($vnSteps as $i => $step) {
                $this->assertNotSame($enSteps[$i]->name, $step->name, $vn);
                $this->assertCount(count($enSteps[$i]->config_json['checklist_items']), $step->config_json['checklist_items'], $vn);
                $this->assertCount(count($enSteps[$i]->config_json['required_docs']), $step->config_json['required_docs'], $vn);
            }
        }
    }

    private function createTemplate(string $code, string $name): WorkTemplate
    {
        $this->actingAs($this->owner)->get(route('app.work-templates.create'))->assertOk();
        $this->actingAs($this->owner)->post(route('app.work-templates.store'), ['code' => $code, 'name' => $name, 'description' => ''])
            ->assertSessionHasNoErrors()->assertRedirect();

        return WorkTemplate::query()->where('tenant_id', (string) $this->tenant->id)->where('code', $code)->firstOrFail();
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function saveSteps(WorkTemplate $template, array $rows): void
    {
        $this->actingAs($this->owner)->post(route('app.work-templates.update', $template->id), ['name' => $template->name, 'steps' => $rows])
            ->assertSessionHasNoErrors();
    }

    /**
     * The form rows the edit page would post back for a version (what the user sees, unchanged).
     *
     * @return list<array<string, mixed>>
     */
    private function rowsFromVersion(WorkTemplateVersion $version): array
    {
        return WorkTemplateStep::query()->where('work_template_version_id', $version->id)->orderBy('step_order')->get()
            ->map(fn (WorkTemplateStep $step): array => [
                'key' => $step->step_key,
                'order' => $step->step_order,
                'name' => $step->name,
                'type' => $step->type,
                'days' => (int) round(((int) $step->sla_hours) / 24, 0) ?: 1,
                'after' => $step->depends_on ?? [],
                'description' => $step->config_json['description'] ?? '',
                'priority' => $step->config_json['priority'] ?? 'medium',
                'phase' => $step->config_json['phase_name'] ?? '',
                'checklist' => implode("\n", array_map(fn (array $i): string => (($i['required'] ?? false) ? '* ' : '') . $i['label'], $step->config_json['checklist_items'] ?? [])),
                'docs' => implode("\n", array_map(fn (array $i): string => (($i['required'] ?? false) ? '* ' : '') . $i['label'], $step->config_json['required_docs'] ?? [])),
            ])->all();
    }

    private function latestVersion(WorkTemplate $template): WorkTemplateVersion
    {
        /** @var WorkTemplateVersion $version */
        $version = WorkTemplateVersion::query()->where('work_template_id', $template->id)->orderByDesc('created_at')->orderByDesc('id')->firstOrFail();

        return $version;
    }

    private function project(): Project
    {
        return Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id, 'created_by' => (string) $this->owner->id, 'pm_id' => (string) $this->owner->id,
            'start_date' => '2026-11-11',
        ]);
    }

    /**
     * @return list<string>
     */
    private function applicable(Project $project): array
    {
        return array_column($this->actingAs($this->owner)->getJson("/app/projects/{$project->id}/work-templates")->assertOk()->json('data'), 'code');
    }
}
