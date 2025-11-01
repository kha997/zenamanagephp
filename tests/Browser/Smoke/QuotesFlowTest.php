<?php

namespace Tests\Browser\Smoke;

use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Quote;
use App\Models\Client;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class QuotesFlowTest extends DuskTestCase
{
    use DatabaseMigrations;

    /** @test */
    public function user_can_view_quotes_list()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->browse(function (Browser $browser) use ($user, $quote) {
            $browser->loginAs($user)
                    ->visit('/app/quotes')
                    ->assertSee('Quotes')
                    ->assertSee($quote->title);
        });
    }

    /** @test */
    public function user_can_create_new_quote()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);

        $this->browse(function (Browser $browser) use ($user, $client) {
            $browser->loginAs($user)
                    ->visit('/app/quotes')
                    ->click('@create-quote-button')
                    ->assertPathIs('/app/quotes/create')
                    ->type('title', 'Test Quote')
                    ->type('description', 'Test Quote Description')
                    ->select('client_id', $client->id)
                    ->type('total_amount', '1000')
                    ->press('Create Quote')
                    ->assertPathIs('/app/quotes')
                    ->assertSee('Test Quote');
        });
    }

    /** @test */
    public function user_can_view_quote_details()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $client = Client::factory()->create(['tenant_id' => $tenant->id]);
        $quote = Quote::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

        $this->browse(function (Browser $browser) use ($user, $quote) {
            $browser->loginAs($user)
                    ->visit('/app/quotes')
                    ->clickLink($quote->title)
                    ->assertPathIs('/app/quotes/' . $quote->id)
                    ->assertSee($quote->title)
                    ->assertSee($quote->description);
        });
    }
}
