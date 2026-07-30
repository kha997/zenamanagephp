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
}
