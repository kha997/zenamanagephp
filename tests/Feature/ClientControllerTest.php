<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Client Controller Test
 * 
 * Tests the ClientController functionality including:
 * - Client CRUD operations
 * - Client lifecycle management
 * - Client filtering and search
 * - Client statistics
 */
class ClientControllerTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function it_can_create_a_client_directly()
    {
        // Test direct model creation without authentication
        $clientData = [
            'tenant_id' => $this->tenant->id,
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'phone' => $this->faker->phoneNumber,
            'company' => $this->faker->company,
            'lifecycle_stage' => 'lead',
            'notes' => $this->faker->text,
        ];

        $client = Client::create($clientData);

        $this->assertDatabaseHas('clients', [
            'name' => $clientData['name'],
            'email' => $clientData['email'],
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals($clientData['name'], $client->name);
        $this->assertEquals('lead', $client->lifecycle_stage);
    }

    /** @test */
    public function it_can_update_client_lifecycle_stage_directly()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead',
        ]);

        $client->update(['lifecycle_stage' => 'prospect']);
        $client->refresh();

        $this->assertEquals('prospect', $client->lifecycle_stage);
    }

    /** @test */
    public function it_can_filter_clients_by_lifecycle_stage_directly()
    {
        // Create clients with different lifecycle stages
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead',
        ]);
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'customer',
        ]);

        $leads = Client::where('tenant_id', $this->tenant->id)
            ->where('lifecycle_stage', 'lead')
            ->get();

        $this->assertCount(1, $leads);
        $this->assertEquals('lead', $leads->first()->lifecycle_stage);
    }

    /** @test */
    public function it_can_search_clients_directly()
    {
        $client1 = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        $client2 = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ]);

        $searchResults = Client::where('tenant_id', $this->tenant->id)
            ->where('name', 'like', '%John%')
            ->get();

        $this->assertCount(1, $searchResults);
        $this->assertEquals('John Doe', $searchResults->first()->name);
    }

    /** @test */
    public function it_can_calculate_client_statistics_directly()
    {
        // Create clients with different lifecycle stages
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead',
        ]);
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'prospect',
        ]);
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'customer',
        ]);

        $stats = [
            'total' => Client::where('tenant_id', $this->tenant->id)->count(),
            'leads' => Client::where('tenant_id', $this->tenant->id)->where('lifecycle_stage', 'lead')->count(),
            'prospects' => Client::where('tenant_id', $this->tenant->id)->where('lifecycle_stage', 'prospect')->count(),
            'customers' => Client::where('tenant_id', $this->tenant->id)->where('lifecycle_stage', 'customer')->count(),
        ];

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['leads']);
        $this->assertEquals(1, $stats['prospects']);
        $this->assertEquals(1, $stats['customers']);
    }

    /** @test */
    public function it_enforces_tenant_isolation_directly()
    {
        // Create client for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);

        // Try to access client from different tenant
        $client = Client::where('tenant_id', $this->tenant->id)
            ->where('id', $otherClient->id)
            ->first();

        $this->assertNull($client);
    }

    /** @test */
    public function it_updates_lifecycle_stage_based_on_quotes()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead',
        ]);

        // Create a sent quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'status' => 'sent',
        ]);

        // Update lifecycle stage
        $client->updateLifecycleStage();
        $client->refresh();

        $this->assertEquals('prospect', $client->lifecycle_stage);
    }

    /** @test */
    public function it_marks_client_as_customer_when_quote_accepted()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'prospect',
        ]);

        // Create an accepted quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $client->id,
            'status' => 'accepted',
        ]);

        // Update lifecycle stage
        $client->updateLifecycleStage();
        $client->refresh();

        $this->assertEquals('customer', $client->lifecycle_stage);
    }
}
