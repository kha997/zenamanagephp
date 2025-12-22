<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;

class ValidationDebugTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    /**
     * Debug validation response
     */
    public function test_validation_debug(): void
    {
        $this->actingAs($this->user);

        // Test invalid data types
        $invalidData = [
            'name' => 123, // Should be string
            'budget_total' => 'not_a_number', // Should be number
            'status' => 'invalid_status', // Should be valid enum
            'start_date' => 'not_a_date', // Should be date
            'tenant_id' => $this->tenant->id,
            'code' => 'INVALID-001'
        ];

        $response = $this->postJson('/api/projects', $invalidData);
        
        dump('Status:', $response->status());
        dump('Content:', $response->getContent());
        dump('Headers:', $response->headers->all());
        
        $this->assertTrue(true);
    }
}
