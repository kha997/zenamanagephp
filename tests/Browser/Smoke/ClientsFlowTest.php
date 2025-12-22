<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class ClientsFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_view_clients_list()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $client) {
            $browser->loginAs($user)
                    ->visit('/app/clients')
                    ->assertSee('Clients')
                    ->assertSee($client->name);
        });
    }

    /** @test */
    public function user_can_create_new_client()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                    ->visit('/app/clients')
                    ->click('@create-client-button')
                    ->assertPathIs('/app/clients/create')
                    ->type('name', 'Test Client')
                    ->type('email', 'test@client.com')
                    ->type('company', 'Test Company')
                    ->press('Create Client')
                    ->assertPathIs('/app/clients')
                    ->assertSee('Test Client');
        });
    }

    /** @test */
    public function user_can_view_client_details()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $client) {
            $browser->loginAs($user)
                    ->visit('/app/clients')
                    ->clickLink($client->name)
                    ->assertPathIs('/app/clients/' . $client->id)
                    ->assertSee($client->name)
                    ->assertSee($client->email);
        });
    }
}
