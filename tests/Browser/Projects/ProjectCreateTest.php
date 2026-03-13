<?php

declare(strict_types=1);

namespace Tests\Browser\Projects;

use App\Models\Tenant;
use App\Models\User;
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
                ->type('@project-description', 'Evidence-based submit flow test')
                ->type('@project-start-date', $today)
                ->type('@project-end-date', $nextWeek)
                ->select('@project-status', 'active')
                ->type('@project-budget-total', '50000')
                ->click('@project-submit')
                ->waitForLocation('/app/projects', 10)
                ->assertPathIs('/app/projects');
        });
    }
}
