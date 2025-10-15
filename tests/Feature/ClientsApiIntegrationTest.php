<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Clients API Integration Test
 *
 * Tests the Clients API functionality including:
 * - Client creation, reading, updating, deletion
 * - Tenant isolation
 * - Permission checks
 * - Response structure validation
 * - Error handling
 */
class ClientsApiIntegrationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $otherUser;
    protected Tenant $tenant;
    protected Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenants
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();

        // Create users
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin'
        ]);

        $this->otherUser = User::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'role' => 'admin'
        ]);

        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function can_create_client_with_valid_data()
    {
        $clientData = [
            'name' => 'Test Client',
            'email' => 'test@client.com',
            'phone' => '+1234567890',
            'company' => 'Test Company',
            'address' => '123 Test Street, Test City, TC 12345',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country',
            'postal_code' => '12345',
            'lifecycle_stage' => 'lead',
            'notes' => 'A test client for integration testing',
            'tags' => ['test', 'integration'],
            'is_vip' => false,
            'credit_limit' => 10000,
            'payment_terms' => 'Net 30'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/clients', $clientData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'company',
                    'address',
                    'city',
                    'state',
                    'country',
                    'postal_code',
                    'lifecycle_stage',
                    'notes',
                    'tags',
                    'is_vip',
                    'credit_limit',
                    'payment_terms',
                    'created_at',
                    'updated_at'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Test Client',
                    'email' => 'test@client.com',
                    'phone' => '+1234567890',
                    'company' => 'Test Company',
                    'address' => '123 Test Street, Test City, TC 12345',
                    'city' => 'Test City',
                    'state' => 'Test State',
                    'country' => 'Test Country',
                    'postal_code' => '12345',
                    'lifecycle_stage' => 'lead',
                    'notes' => 'A test client for integration testing',
                    'tags' => ['test', 'integration'],
                    'is_vip' => false,
                    'credit_limit' => 10000,
                    'payment_terms' => 'Net 30'
                ],
                'message' => 'Client created successfully'
            ]);

        // Verify client was created in database
        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'email' => 'test@client.com',
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead'
        ]);
    }

    /** @test */
    public function cannot_create_client_with_invalid_data()
    {
        $invalidData = [
            'name' => '', // Required field empty
            'email' => 'invalid-email', // Invalid email format
            'phone' => str_repeat('1', 100), // Phone too long
            'company' => str_repeat('A', 300), // Company name too long
            'lifecycle_stage' => 'invalid-stage', // Invalid lifecycle stage
            'credit_limit' => -1000, // Negative credit limit
            'payment_terms' => str_repeat('A', 200) // Payment terms too long
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/clients', $invalidData);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'error' => [
                    'message',
                    'errors'
                ]
            ])
            ->assertJson([
                'success' => false
            ]);

        // Verify no client was created
        $this->assertDatabaseMissing('clients', [
            'tenant_id' => $this->tenant->id
        ]);
    }

    /** @test */
    public function cannot_create_client_with_duplicate_email()
    {
        // Create first client
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'duplicate@test.com'
        ]);

        // Try to create second client with same email
        $response = $this->actingAs($this->user)
            ->postJson('/api/clients', [
                'name' => 'Second Client',
                'email' => 'duplicate@test.com'
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Validation failed',
                    'errors' => [
                        'email' => ['This email address is already registered.']
                    ]
                ]
            ]);
    }

    /** @test */
    public function can_retrieve_client_list()
    {
        // Create test clients
        Client::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/clients');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'phone',
                        'company',
                        'lifecycle_stage',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'meta' => [
                    'total',
                    'per_page',
                    'current_page',
                    'last_page'
                ]
            ])
            ->assertJson([
                'success' => true
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);
    }

    /** @test */
    public function can_retrieve_specific_client()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Specific Test Client'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'phone',
                    'company',
                    'address',
                    'city',
                    'state',
                    'country',
                    'postal_code',
                    'lifecycle_stage',
                    'notes',
                    'tags',
                    'is_vip',
                    'credit_limit',
                    'payment_terms',
                    'created_at',
                    'updated_at'
                ]
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $client->id,
                    'name' => 'Specific Test Client'
                ]
            ]);
    }

    /** @test */
    public function can_update_client()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Original Client Name',
            'lifecycle_stage' => 'lead'
        ]);

        $updateData = [
            'name' => 'Updated Client Name',
            'email' => 'updated@client.com',
            'lifecycle_stage' => 'customer',
            'is_vip' => true,
            'credit_limit' => 25000,
            'notes' => 'Updated client notes'
        ];

        $response = $this->actingAs($this->user)
            ->putJson("/api/clients/{$client->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                    'lifecycle_stage',
                    'is_vip',
                    'credit_limit',
                    'notes'
                ],
                'message'
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $client->id,
                    'name' => 'Updated Client Name',
                    'email' => 'updated@client.com',
                    'lifecycle_stage' => 'customer',
                    'is_vip' => true,
                    'credit_limit' => 25000,
                    'notes' => 'Updated client notes'
                ],
                'message' => 'Client updated successfully'
            ]);

        // Verify client was updated in database
        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'name' => 'Updated Client Name',
            'email' => 'updated@client.com',
            'lifecycle_stage' => 'customer'
        ]);
    }

    /** @test */
    public function can_delete_client()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/clients/{$client->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message'
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Client deleted successfully'
            ]);

        // Verify client was soft deleted
        $this->assertSoftDeleted('clients', [
            'id' => $client->id
        ]);
    }

    /** @test */
    public function enforces_tenant_isolation()
    {
        // Create client in other tenant
        $otherClient = Client::factory()->create([
            'tenant_id' => $this->otherTenant->id
        ]);

        // Try to access client from different tenant
        $response = $this->actingAs($this->user)
            ->getJson("/api/clients/{$otherClient->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Client not found'
                ]
            ]);

        // Try to update client from different tenant
        $response = $this->actingAs($this->user)
            ->putJson("/api/clients/{$otherClient->id}", [
                'name' => 'Hacked Client'
            ]);

        $response->assertStatus(404);

        // Try to delete client from different tenant
        $response = $this->actingAs($this->user)
            ->deleteJson("/api/clients/{$otherClient->id}");

        $response->assertStatus(404);

        // Verify other tenant's client is unchanged
        $this->assertDatabaseHas('clients', [
            'id' => $otherClient->id,
            'name' => $otherClient->name,
            'tenant_id' => $this->otherTenant->id
        ]);
    }

    /** @test */
    public function requires_authentication()
    {
        $response = $this->getJson('/api/clients');
        $response->assertStatus(401);

        $response = $this->postJson('/api/clients', [
            'name' => 'Test Client'
        ]);
        $response->assertStatus(401);
    }

    /** @test */
    public function requires_proper_permissions()
    {
        // Create user with insufficient permissions
        $member = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'member'
        ]);

        $response = $this->actingAs($member)
            ->postJson('/api/clients', [
                'name' => 'Test Client',
                'email' => 'test@client.com'
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function handles_nonexistent_client()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/clients/non-existent-id');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'message' => 'Client not found'
                ]
            ]);
    }

    /** @test */
    public function validates_field_names_in_response()
    {
        $client = Client::factory()->create([
            'tenant_id' => $this->tenant->id
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/clients/{$client->id}");

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Verify standardized field names
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('phone', $data);
        $this->assertArrayHasKey('company', $data);
        $this->assertArrayHasKey('lifecycle_stage', $data);
        $this->assertArrayHasKey('notes', $data);
        $this->assertArrayHasKey('is_vip', $data);
        $this->assertArrayHasKey('credit_limit', $data);
        $this->assertArrayHasKey('payment_terms', $data);
    }

    /** @test */
    public function can_filter_clients_by_lifecycle_stage()
    {
        // Create clients with different lifecycle stages
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'lead'
        ]);

        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'lifecycle_stage' => 'customer'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/clients?lifecycle_stage=lead');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('lead', $data[0]['lifecycle_stage']);
    }

    /** @test */
    public function can_search_clients_by_name()
    {
        // Create clients with different names
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe'
        ]);

        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Jane Smith'
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/clients?search=John');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('John Doe', $data[0]['name']);
    }

    /** @test */
    public function can_filter_clients_by_vip_status()
    {
        // Create VIP and non-VIP clients
        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_vip' => true
        ]);

        Client::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_vip' => false
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/clients?vip=true');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertTrue($data[0]['is_vip']);
    }
}
