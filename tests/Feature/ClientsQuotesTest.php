<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

class ClientsQuotesTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_create_client(): void
    {
        $clientData = [
            'name' => $this->faker->company,
            'email' => $this->faker->email,
            'phone' => $this->faker->phoneNumber,
            'type' => 'potential',
            'address' => $this->faker->address,
            'notes' => $this->faker->text,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/clients', $clientData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'type',
                    'address',
                    'notes',
                    'tenant_id',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('clients', [
            'name' => $clientData['name'],
            'email' => $clientData['email'],
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_list_clients(): void
    {
        Client::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/clients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'phone',
                            'type',
                            'created_at',
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page',
                    ]
                ]
            ]);

        $this->assertEquals(5, $response->json('data.data.total'));
    }

    /** @test */
    public function it_can_update_client(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $updateData = [
            'name' => 'Updated Company Name',
            'type' => 'signed',
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/app/clients/{$client->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Updated Company Name',
            'type' => 'signed',
        ]);
    }

    /** @test */
    public function it_can_delete_client(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/app/clients/{$client->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('clients', [
            'id' => $client->id,
        ]);
    }

    /** @test */
    public function it_can_create_quote(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quoteData = [
            'client_id' => $client->id,
            'quote_number' => 'Q-' . $this->faker->unique()->numberBetween(1000, 9999),
            'project_type' => 'design',
            'description' => $this->faker->text,
            'total_amount' => $this->faker->numberBetween(1000, 50000),
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/quotes', $quoteData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'client_id',
                    'quote_number',
                    'project_type',
                    'description',
                    'total_amount',
                    'valid_until',
                    'tenant_id',
                    'created_at',
                    'updated_at',
                ]
            ]);

        $this->assertDatabaseHas('quotes', [
            'quote_number' => $quoteData['quote_number'],
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_list_quotes(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Quote::factory()->count(3)->create([
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/app/quotes');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'quote_number',
                            'project_type',
                            'total_amount',
                            'valid_until',
                            'client' => [
                                'id',
                                'name',
                            ]
                        ]
                    ],
                    'meta' => [
                        'current_page',
                        'total',
                        'per_page',
                    ]
                ]
            ]);

        $this->assertEquals(3, $response->json('data.data.total'));
    }

    /** @test */
    public function it_can_update_quote(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $updateData = [
            'status' => 'accepted',
            'total_amount' => 25000,
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/app/quotes/{$quote->id}", $updateData);

        $response->assertStatus(200);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => 'accepted',
            'total_amount' => 25000,
        ]);
    }

    /** @test */
    public function it_can_delete_quote(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/app/quotes/{$quote->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('quotes', [
            'id' => $quote->id,
        ]);
    }

    /** @test */
    public function it_enforces_tenant_isolation_for_clients(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/app/clients/{$otherClient->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_enforces_tenant_isolation_for_quotes(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $otherQuote = Quote::factory()->create([
            'client_id' => $otherClient->id,
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/app/quotes/{$otherQuote->id}");

        $response->assertStatus(404);
    }

    /** @test */
    public function it_validates_client_creation_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/clients', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email']);
    }

    /** @test */
    public function it_validates_quote_creation_data(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/app/quotes', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['client_id', 'quote_number', 'total_amount']);
    }

    /** @test */
    public function it_can_export_quote_as_pdf(): void
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $quote = Quote::factory()->create([
            'client_id' => $client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/app/quotes/{$quote->id}/export");

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
