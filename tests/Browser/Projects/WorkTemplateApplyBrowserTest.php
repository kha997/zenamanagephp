<?php

declare(strict_types=1);

namespace Tests\Browser\Projects;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use App\Models\WorkTemplate;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Feature\Api\Concerns\InteractsWithWorkTemplateV2;

class WorkTemplateApplyBrowserTest extends DuskTestCase
{
    use DatabaseMigrations;
    use InteractsWithWorkTemplateV2;

    protected Tenant $tenant;
    protected User $user;
    protected Project $project;
    protected WorkTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'is_active' => true,
        ]);
        $role = Role::factory()->create(['name' => 'Browser PM ' . uniqid()]);
        $permissionIds = Permission::whereIn('code', ['project.view', 'template.view', 'template.apply'])->pluck('id');
        $role->permissions()->sync($permissionIds);
        UserRole::query()->create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);

        $this->project = Project::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'created_by' => (string) $this->user->id,
            'pm_id' => (string) $this->user->id,
        ]);

        // seedV2Template($tenant, $user, $code) tạo WorkTemplate với
        // name cố định 'Seeded V2 Template' (xem InteractsWithWorkTemplateV2:118).
        [$this->template, $version] = $this->seedV2Template($this->tenant, $this->user, 'WT-BROWSER-1');
        $version->update([
            'published_at' => now(),
            'is_immutable' => true,
            'published_by' => (string) $this->user->id,
        ]);

        // Seed một DeliverableTemplate published để dropdown "Biểu mẫu ▾"
        // THẬT SỰ xuất hiện trên trang — nếu thiếu, test closed-by-default
        // bên dưới pass rỗng vì không có dropdown nào để kiểm tra.
        $docTemplate = \App\Models\DeliverableTemplate::factory()->create([
            'tenant_id' => (string) $this->tenant->id,
            'context' => 'project',
            'name' => 'Browser Dropdown Template',
            'status' => 'published',
        ]);
        \App\Models\DeliverableTemplateVersion::create([
            'tenant_id' => (string) $this->tenant->id,
            'deliverable_template_id' => $docTemplate->id,
            'version' => '1.0.0',
            'semver' => '1.0.0',
            'storage_path' => 'deliverable-templates/' . $this->tenant->id . '/browser-test/render.html',
            'checksum_sha256' => hash('sha256', '<h1>x</h1>'),
            'mime' => 'text/html',
            'size' => 10,
            'placeholders_spec_json' => ['schema_version' => '1.0.0', 'placeholders' => []],
            'published_at' => now(),
            'created_by' => (string) $this->user->id,
            'updated_by' => (string) $this->user->id,
        ]);
    }

    public function test_apply_work_template_card_loads_templates_and_previews(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/' . $this->project->id)
                // Card đã render và JS đã fetch xong danh sách (loading ẩn, body hiện).
                ->waitFor('[data-wta-body]:not(.hidden)', 15)
                ->assertPresent('select[data-wta-select]')
                // Select có đúng template đã publish (option text = name của template).
                ->assertSeeIn('select[data-wta-select]', 'Seeded V2 Template')
                // Chọn template → nút Xem trước bật.
                ->select('select[data-wta-select]', (string) $this->template->id)
                ->pause(200)
                ->click('[data-wta-preview-btn]')
                // Preview trả về summary (dry-run) → khối kết quả hiện.
                ->waitFor('[data-wta-result]:not(.hidden)', 15)
                ->assertSeeIn('[data-wta-result]', 'Giai đoạn')
                // Không phải duplicate → nút Áp dụng hiện.
                ->assertVisible('[data-wta-apply-btn]');
        });
    }

    public function test_template_dropdown_menu_is_closed_by_default(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/' . $this->project->id)
                // Dropdown Biểu mẫu PHẢI tồn tại (setUp đã seed DeliverableTemplate
                // published) — nếu selector này absent, fixture hỏng chứ không phải pass.
                ->waitFor('details[data-template-dropdown]', 15);

            // Menu phải ĐÓNG mặc định (regression: Alpine chết từng làm
            // menu hiện thường trực đè lên header).
            $openMenus = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(0, $openMenus);

            // Click mở, click ra ngoài đóng lại.
            $browser->click('details[data-template-dropdown] summary')
                ->pause(150);
            $openAfterClick = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(1, $openAfterClick);

            $browser->click('header.operator-topbar')->pause(150);
            $openAfterOutsideClick = $browser->script(
                "return document.querySelectorAll('details[data-template-dropdown][open]').length;"
            )[0];
            $this->assertSame(0, $openAfterOutsideClick);
        });
    }
}
