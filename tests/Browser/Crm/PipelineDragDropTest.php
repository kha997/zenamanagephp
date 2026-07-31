<?php declare(strict_types=1);

namespace Tests\Browser\Crm;

use App\Models\Account;
use App\Models\Opportunity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class PipelineDragDropTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;
    protected User $user;
    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Company',
            'slug' => 'test-company-' . uniqid(),
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Test User',
            'email' => 'test+' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
        ]);

        $role = Role::factory()->create(['name' => 'Dusk CRM Manager ' . uniqid()]);
        $managePermission = Permission::firstOrCreate(
            ['code' => 'crm.manage'],
            ['name' => 'crm.manage', 'module' => 'crm', 'action' => 'manage']
        );
        $viewPermission = Permission::firstOrCreate(
            ['code' => 'crm.view'],
            ['name' => 'crm.view', 'module' => 'crm', 'action' => 'view']
        );
        $role->permissions()->sync([$managePermission->id, $viewPermission->id]);
        UserRole::create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);

        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'display_name' => 'Khách hàng Dusk test',
        ]);
    }

    protected function makeOpportunity(array $overrides = []): Opportunity
    {
        return Opportunity::query()->create(array_merge([
            'tenant_id' => (string) $this->tenant->id,
            'account_id' => (string) $this->account->id,
            'opportunity_name' => 'Cơ hội Dusk ' . uniqid(),
            'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
            'estimated_fee' => 100000000,
            'sales_owner_id' => (string) $this->user->id,
            'created_by' => (string) $this->user->id,
        ], $overrides));
    }

    public function test_crm_index_page_loads_with_pipeline_drag_script_and_dom_contract(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-board-group="new"]', 10)
                ->assertSee('Pipeline kinh doanh')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"]')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->assertPresent('[data-crm-stage-dialog]');
        });
    }

    public function test_click_transition_opens_dialog_with_group_picker_excluding_current_group(): void
    {
        $opportunity = $this->makeOpportunity(); // stage mặc định new_lead → group 'new'

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="quote"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="negotiation_contract"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="won"]')
                ->assertVisible('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->assertScript(
                    "return document.querySelector('[data-crm-stage-dialog] .crm-dialog-group-option[data-group=\"new\"]').classList.contains('hidden');",
                    true
                );
        });
    }

    public function test_cancel_dialog_closes_without_changing_card(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-dialog-cancel]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_selecting_normal_group_closes_dialog_and_sets_pending(): void
    {
        // Group KHÔNG requires_choice (vd. consulting_survey): chọn xong là submit ngay
        // (1-bước) — không cần bước "Xác nhận" riêng như group requires_choice.
        //
        // Kể từ Task 9, submitStageTransition() gọi mạng thật (không còn là stub của
        // slice 2). Request tới backend thật (đã nối xong từ Task 4) hoàn tất nhanh
        // trong môi trường test nên card không còn pending vĩnh viễn — dialog đóng ngay
        // (submitStageTransition đóng dialog trước khi gọi mạng) và aria-busy trở về
        // 'false' sau khi request thành công. Bài test này giờ xác nhận dialog đóng +
        // request thật sự hoàn tất, không còn xác nhận "pending vĩnh viễn" (đó là đặc
        // điểm của stub cũ, không phải hành vi mục tiêu).
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitUntilMissing('[data-crm-stage-dialog][open]', 10)
                ->pause(500)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'false');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage);
    }

    public function test_selecting_lost_nurture_group_shows_three_choice_options(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="lost"]', 10)
                ->assertPresent('[data-dialog-choice-picker] input[value="no_bid"]')
                ->assertPresent('[data-dialog-choice-picker] input[value="nurture"]')
                // preselect: bước group-picker bị ẩn đi khi đã vào bước choice_options
                ->assertScript(
                    "return document.querySelector('[data-dialog-group-picker]').classList.contains('hidden');",
                    true
                )
                // dialog vẫn đang mở (không đóng lại khi chuyển bước)
                ->assertScript("return document.querySelector('[data-crm-stage-dialog]').open;", true);
        });
    }

    public function test_choosing_lost_requires_reason_before_confirm_enables(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="lost"]', 10)
                ->click('[data-dialog-choice-picker] input[value="lost"]')
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    true
                )
                ->type('[data-dialog-reason]', 'Khách chọn đối thủ khác')
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    false
                );
        });
    }

    public function test_choosing_no_bid_does_not_require_reason(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="no_bid"]', 10)
                ->click('[data-dialog-choice-picker] input[value="no_bid"]')
                ->assertScript(
                    "return document.querySelector('[data-dialog-confirm]').disabled;",
                    false
                );
        });
    }

    public function test_normal_group_click_sends_request_and_blocks_duplicate(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10);

            $browser->script([
                "window.__pendingCount = 0;"
                . "window.fetch = function() { window.__pendingCount++; return new Promise(function(){}); };",
            ]);

            $browser->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->pause(300)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'true');

            $pendingAfterClick = $browser->script('return window.__pendingCount;')[0];
            $this->assertSame(1, $pendingAfterClick);

            // Nút transition đã bị disable bởi setCardPending — không còn cách nào double-submit
            // qua UI. Đợi thêm rồi xác nhận __pendingCount không tự tăng thêm lần nào nữa.
            $browser->pause(500);
            $pendingAfterWait = $browser->script('return window.__pendingCount;')[0];
            $this->assertSame(1, $pendingAfterWait);

            $browser->assertScript(
                "return document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-stage-transition-btn').disabled;",
                true
            );
        });
    }

    public function test_error_403_response_shows_toast_clears_pending_and_keeps_card(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10);

            $browser->script([
                "window.fetch = function() { return Promise.resolve(new Response(JSON.stringify({message: 'Bạn không có quyền thực hiện thao tác này.'}), {status: 403})); };",
            ]);

            $browser->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitForText('Bạn không có quyền thực hiện thao tác này.', 10)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'false')
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_error_500_response_shows_generic_toast_and_clears_pending(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10);

            $browser->script([
                "window.fetch = function() { return Promise.resolve(new Response('Internal Server Error', {status: 500})); };",
            ]);

            $browser->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitForText('Có lỗi xảy ra, vui lòng thử lại.', 10)
                ->assertAttribute('[data-opportunity-id="' . $opportunity->id . '"]', 'aria-busy', 'false')
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_successful_submit_clears_pending_state(): void
    {
        // Backend thật (đã nối xong từ Task 4) — chỉ xác nhận pending được gỡ,
        // CHƯA xác nhận card di chuyển cột (đó là Task 10/slice 4, chưa tồn tại).
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->pause(500);

            $ariaBusy = $browser->script(
                "return document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"]').getAttribute('aria-busy');"
            )[0];
            $this->assertSame('false', $ariaBusy);
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage); // backend ĐÃ đổi thật
    }

    public function test_successful_submit_moves_card_and_updates_column_aggregates(): void
    {
        $opportunityToMove = $this->makeOpportunity(['estimated_fee' => 1000000000]);
        $otherInSourceColumn = $this->makeOpportunity(['estimated_fee' => 500000000]);

        $this->browse(function (Browser $browser) use ($opportunityToMove, $otherInSourceColumn) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunityToMove->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunityToMove->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="consulting_survey"]')
                ->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunityToMove->id . '"]', 10)
                ->assertMissing('[data-board-group="new"] [data-opportunity-id="' . $opportunityToMove->id . '"]')
                ->assertScript(
                    "return document.querySelector('[data-opportunity-id=\"{$opportunityToMove->id}\"]').dataset.currentStage;",
                    Opportunity::STAGE_BRIEF_DISCOVERY
                )
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"new\"] [data-column-count]').textContent.trim();",
                    '1' // chỉ còn $otherInSourceColumn
                )
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"consulting_survey\"] [data-column-count]').textContent.trim();",
                    '1'
                );
        });
    }

    public function test_terminal_response_removes_handle_and_transition_button(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="lost"]', 10)
                ->click('[data-dialog-choice-picker] input[value="lost"]')
                ->type('[data-dialog-reason]', 'Khách chọn đối thủ khác')
                ->click('[data-dialog-confirm]')
                ->waitFor('[data-board-group="lost_nurture"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertMissing('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_LOST, $opportunity->pipeline_stage);
        $this->assertSame('Khách chọn đối thủ khác', $opportunity->lost_reason);
    }

    public function test_nurture_response_keeps_handle_and_transition_button(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn', 10)
                ->click('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn')
                ->waitFor('[data-crm-stage-dialog][open]', 10)
                ->click('[data-crm-stage-dialog] .crm-dialog-group-option[data-group="lost_nurture"]')
                ->waitFor('[data-dialog-choice-picker] input[value="nurture"]', 10)
                ->click('[data-dialog-choice-picker] input[value="nurture"]')
                ->click('[data-dialog-confirm]')
                ->waitFor('[data-board-group="lost_nurture"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle')
                ->assertPresent('[data-opportunity-id="' . $opportunity->id . '"] .crm-stage-transition-btn');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NURTURE, $opportunity->pipeline_stage);
        $this->assertFalse($opportunity->isTerminal());
    }

    public function test_dragover_sets_and_clears_data_dragover_through_full_lifecycle(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10);

            // dragenter cột khác cột nguồn → data-dragover="1"
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                '1'
            );

            // dragleave thật sự rời cột → clear
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragleave', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                null
            );

            // dragenter lại rồi drop → clear
            $browser->script([
                "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('[data-board-group=\"consulting_survey\"]').dispatchEvent(new Event('drop', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"consulting_survey\"]').getAttribute('data-dragover');",
                null
            );

            // dragenter nhiều cột rồi dragend (thả ngoài board) → clear TOÀN BỘ cột, không riêng 1 cột
            $browser->script([
                "document.querySelector('[data-board-group=\"quote\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('[data-board-group=\"won\"]').dispatchEvent(new Event('dragenter', {bubbles: true, cancelable: true}));"
                . "document.querySelector('.crm-drag-handle').dispatchEvent(new Event('dragend', {bubbles: true, cancelable: true}));",
            ]);
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"quote\"]').getAttribute('data-dragover');",
                null
            );
            $browser->assertScript(
                "return document.querySelector('[data-board-group=\"won\"]').getAttribute('data-dragover');",
                null
            );
        });
    }

    public function test_drop_on_different_column_triggers_stage_transition(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10);

            $browser->script([
                "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                . "var dt = new DataTransfer();"
                . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                . "var target = document.querySelector('[data-board-group=\"consulting_survey\"]');"
                . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
            ]);

            $browser->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]', 10);
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_BRIEF_DISCOVERY, $opportunity->pipeline_stage);
    }

    public function test_drop_into_lost_nurture_column_opens_dialog_preselected(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10);

            $browser->script([
                "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                . "var dt = new DataTransfer();"
                . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                . "var target = document.querySelector('[data-board-group=\"lost_nurture\"]');"
                . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
            ]);

            $browser->waitFor('[data-crm-stage-dialog][open]', 10)
                ->assertVisible('[data-dialog-choice-picker] input[value="lost"]')
                ->assertScript(
                    "return document.querySelector('[data-dialog-group-picker]').classList.contains('hidden');",
                    true
                );
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage); // chưa submit, chỉ mở dialog
    }

    public function test_drop_on_same_column_is_noop(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10);

            $browser->script([
                "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                . "var dt = new DataTransfer();"
                . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                . "var target = document.querySelector('[data-board-group=\"new\"]');"
                . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
            ]);

            $browser->pause(300)
                ->assertPresent('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]')
                ->assertMissing('[data-crm-stage-dialog][open]');
        });

        $opportunity->refresh();
        $this->assertSame(Opportunity::STAGE_NEW_LEAD, $opportunity->pipeline_stage);
    }

    public function test_dragging_the_last_card_out_of_a_column_reveals_the_empty_state(): void
    {
        $opportunity = $this->makeOpportunity();

        $this->browse(function (Browser $browser) use ($opportunity) {
            $browser->loginAs($this->user)
                ->visit('/operator/crm')
                ->waitFor('[data-opportunity-id="' . $opportunity->id . '"] .crm-drag-handle', 10)
                ->assertMissing('[data-board-group="new"] [data-column-empty]:not(.hidden)');

            $browser->script([
                "var handle = document.querySelector('[data-opportunity-id=\"{$opportunity->id}\"] .crm-drag-handle');"
                . "var dt = new DataTransfer();"
                . "handle.dispatchEvent(new DragEvent('dragstart', {bubbles: true, cancelable: true, dataTransfer: dt}));"
                . "var target = document.querySelector('[data-board-group=\"consulting_survey\"]');"
                . "target.dispatchEvent(new DragEvent('drop', {bubbles: true, cancelable: true, dataTransfer: dt}));",
            ]);

            $browser->waitFor('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]', 10)
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"new\"] [data-column-count]').textContent.trim();",
                    '0'
                )
                ->assertScript(
                    "return document.querySelector('[data-board-group=\"new\"] [data-column-total]').textContent.trim()"
                    . " === new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(0);",
                    true
                )
                ->assertVisible('[data-board-group="new"] [data-column-empty]')
                ->assertSeeIn('[data-board-group="new"] [data-column-empty]', 'Trống')
                ->assertMissing('[data-board-group="new"] [data-opportunity-id="' . $opportunity->id . '"]')
                ->assertPresent('[data-board-group="consulting_survey"] [data-opportunity-id="' . $opportunity->id . '"]');
        });
    }
}
