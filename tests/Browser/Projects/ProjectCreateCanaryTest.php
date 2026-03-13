<?php

declare(strict_types=1);

namespace Tests\Browser\Projects;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Hash;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;

class ProjectCreateCanaryTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Canary Tenant',
            'slug' => 'canary-tenant-' . uniqid(),
            'status' => 'active',
        ]);

        $this->user = User::create([
            'name' => 'Canary User',
            'email' => 'canary+' . uniqid() . '@example.com',
            'password' => Hash::make('password'),
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_project_create_surface_canary_is_route_anchored_and_has_required_fields(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                ->visitRoute('app.projects.create')
                ->waitFor('@project-name', 10)
                ->assertPathIs('/app/projects/create')
                ->assertPresent('@project-name')
                ->assertPresent('@project-description')
                ->assertPresent('@project-start-date')
                ->assertPresent('@project-end-date')
                ->assertPresent('@project-status')
                ->assertPresent('@project-submit')
                ->assertPresent('@project-cancel')
                ->click('@project-submit')
                ->pause(300)
                ->assertPathIs('/app/projects/create')
                ->assertScript("return document.querySelector('input[name=\"name\"]').matches(':invalid');", true)
                ->assertScript("return document.querySelector('input[name=\"start_date\"]').matches(':invalid');", true)
                ->assertScript("return document.querySelector('input[name=\"end_date\"]').matches(':invalid');", true);
        });
    }
}
