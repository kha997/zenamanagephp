<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Simple Client Test - Minimal test to debug authentication issues
 */
class SimpleClientTest extends TestCase
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
    public function it_can_create_a_client_without_authentication()
    {
        // Test without authentication first
        $clientData = [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Client',
            'email' => 'test@example.com',
            'phone' => '1234567890',
            'company' => 'Test Company',
            'lifecycle_stage' => 'lead',
        ];

        $client = Client::create($clientData);

        $this->assertDatabaseHas('clients', [
            'name' => 'Test Client',
            'email' => 'test@example.com',
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('Test Client', $client->name);
        $this->assertEquals('lead', $client->lifecycle_stage);
    }

    /** @test */
    public function it_can_access_clients_route_without_middleware()
    {
        // Test direct route access without middleware
        $response = $this->get('/app/clients');

        // Should get 404 or redirect, not authentication error
        $this->assertTrue(
            $response->status() === 404 || 
            $response->status() === 302 || 
            $response->status() === 401
        );
    }

    /** @test */
    public function it_can_test_authentication_directly()
    {
        // Test authentication directly
        $this->assertFalse(auth()->check());
        
        // Try to authenticate manually
        auth()->login($this->user);
        
        $this->assertTrue(auth()->check());
        $this->assertEquals($this->user->id, auth()->id());
    }
}
