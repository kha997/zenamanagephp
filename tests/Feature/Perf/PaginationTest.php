<?php

namespace Tests\Feature\Perf;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PaginationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function clients_index_has_pagination(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create 25 clients to trigger pagination
        Client::factory()->count(25)->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user)->get('/app/clients');
        
        $response->assertStatus(200);
        $response->assertSee('pagination');
        $response->assertSee('Next');
        $response->assertSee('Previous');
    }

    /** @test */
    public function quotes_index_has_pagination(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create 25 quotes to trigger pagination
        Quote::factory()->count(25)->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user)->get('/app/quotes');
        
        $response->assertStatus(200);
        $response->assertSee('pagination');
        $response->assertSee('Next');
        $response->assertSee('Previous');
    }

    /** @test */
    public function pagination_shows_correct_per_page(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create 25 clients
        Client::factory()->count(25)->create(['tenant_id' => $tenant->id]);
        
        $response = $this->actingAs($user)->get('/app/clients');
        
        $response->assertStatus(200);
        // Should show 20 items per page (default pagination)
        $response->assertSee('Showing 1 to 20 of 25 results');
    }
}
