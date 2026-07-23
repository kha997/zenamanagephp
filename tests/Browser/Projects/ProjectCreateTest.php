<?php

declare(strict_types=1);

namespace Tests\Browser\Projects;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectCreateTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;

    protected $user;

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

        // test_project_create_submit_flow does a real POST /app/projects, gated
        // by rbac:project.create — this user had zero roles/permissions before,
        // which only "passed" by accident pre-PR#220 (a raw JSON 403 left the
        // browser sitting at the POST-target URL /app/projects, coincidentally
        // matching waitForLocation). Grant the permission the flow actually needs.
        $role = Role::factory()->create(['name' => 'Dusk Project Creator ' . uniqid()]);
        $permission = Permission::firstOrCreate(
            ['code' => 'project.create'],
            ['name' => 'project.create', 'module' => 'project', 'action' => 'create']
        );
        $role->permissions()->sync([$permission->id]);
        UserRole::create(['user_id' => (string) $this->user->id, 'role_id' => (string) $role->id]);
    }

    public function test_project_create_page_renders(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/create')
                ->waitFor('@project-name', 10)
                ->assertPresent('@project-name')
                ->assertPresent('@project-description')
                ->assertPresent('@project-start-date')
                ->assertPresent('@project-end-date')
                ->assertPresent('@project-submit')
                ->assertPresent('@project-cancel');
        });
    }

    public function test_project_create_validation_surface(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/create')
                ->waitFor('@project-submit', 10)
                ->click('@project-submit')
                ->pause(400)
                ->assertPathIs('/app/projects/create')
                ->assertScript("return document.querySelector('input[name=\"name\"]').matches(':invalid');", true)
                ->assertScript("return document.querySelector('input[name=\"start_date\"]').matches(':invalid');", true)
                ->assertScript("return document.querySelector('input[name=\"end_date\"]').matches(':invalid');", true);
        });
    }

    public function test_project_create_cancel_flow(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visit('/app/projects/create')
                ->waitFor('@project-name', 10)
                ->type('@project-name', 'Cancel Flow Project')
                ->type('@project-description', 'Cancel flow smoke test')
                ->click('@project-cancel')
                ->waitForLocation('/app/projects', 10)
                ->assertPathIs('/app/projects');
        });
    }

    public function test_project_create_submit_flow(): void
    {
        $this->browse(function (Browser $browser) {
            $today = now()->toDateString();
            $nextWeek = now()->addDays(7)->toDateString();

            $browser->loginAs($this->user)
                ->visit('/app/projects/create')
                ->waitFor('@project-name', 10)
                ->type('@project-name', 'Browser Create Project')
                ->type('@project-description', 'Evidence-based submit flow test');

            // Native <input type="date"> must NOT be filled via type(): Dusk's
            // type() sends the ISO "Y-m-d" string as raw keystrokes, and the
            // widget's MM/DD/YYYY segment auto-advance consumes the leading
            // year digit as a (invalid, auto-completed) month digit, then
            // overflows the remaining digits into the year segment —
            // confirmed by reproduction: typing "2026-07-23" this way can
            // produce a garbled value like "60723-02-02" instead of
            // "2026-07-23", which fails the server's "end after start" date
            // validation and hangs waitForLocation() waiting for a redirect
            // that never happens. Setting .value directly (spec-guaranteed
            // ISO format, locale-independent) plus firing input/change so
            // the form treats the field as touched is the reliable way to
            // fill a date input in Dusk.
            $this->setNativeDateInput($browser, '@project-start-date', $today);
            $this->setNativeDateInput($browser, '@project-end-date', $nextWeek);

            $browser->select('@project-status', 'active')
                ->type('@project-budget-total', '50000')
                ->click('@project-submit')
                ->waitForLocation('/app/projects', 10)
                ->assertPathIs('/app/projects');
        });
    }

    /**
     * Reliably set a native <input type="date"> value in a Dusk test.
     *
     * Dusk's type() sends raw keystrokes, which native date-input widgets
     * split across MM/DD/YYYY segments in a way that garbles an ISO
     * "Y-m-d" string (see test_project_create_submit_flow for the full
     * explanation). The DOM .value property of a date input is always
     * "Y-m-d" regardless of display locale, so setting it directly and
     * firing input/change is reliable.
     */
    private function setNativeDateInput(Browser $browser, string $duskSelector, string $isoDate): void
    {
        $cssSelector = str_starts_with($duskSelector, '@')
            ? '[dusk="' . substr($duskSelector, 1) . '"]'
            : $duskSelector;

        $browser->script([
            "document.querySelector('{$cssSelector}').value = '{$isoDate}'",
            "document.querySelector('{$cssSelector}').dispatchEvent(new Event('input', {bubbles: true}))",
            "document.querySelector('{$cssSelector}').dispatchEvent(new Event('change', {bubbles: true}))",
        ]);
    }
}
