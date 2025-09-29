<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class SecurityApiValidationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create super admin user
        $this->user = User::factory()->create([
            'role' => 'super_admin',
            'email' => 'admin@example.com'
        ]);
        
        // Create token with admin ability
        $this->token = $this->user->createToken('admin', ['admin'])->plainTextToken;
    }

    /** @test */
    public function it_validates_period_parameter()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/kpis?period=invalid');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid period parameter. Must be one of: 7d, 30d, 90d',
                    'details' => [
                        'field' => 'period',
                        'value' => 'invalid',
                        'allowed' => ['7d', '30d', '90d']
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_date_range_parameters()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/audit?date_from=2025-09-30T00:00:00Z&date_to=2025-09-01T00:00:00Z');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Date range invalid. date_from must be before date_to',
                    'details' => [
                        'field' => 'date_range',
                        'date_from' => '2025-09-30T00:00:00Z',
                        'date_to' => '2025-09-01T00:00:00Z'
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_max_range_days()
    {
        $dateFrom = now()->subDays(100)->toISOString();
        $dateTo = now()->toISOString();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get("/api/admin/security/audit?date_from={$dateFrom}&date_to={$dateTo}");

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Date range exceeds maximum allowed days (90)',
                    'details' => [
                        'field' => 'date_range',
                        'days' => 100,
                        'max_days' => 90
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_per_page_limits()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/audit?per_page=200');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'per_page must be between 1 and 100',
                    'details' => [
                        'field' => 'per_page',
                        'value' => 200,
                        'min' => 1,
                        'max' => 100
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_sort_fields()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/audit?sort_by=invalid_field');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid sort field',
                    'details' => [
                        'field' => 'sort_by',
                        'value' => 'invalid_field',
                        'allowed' => ['created_at', 'action', 'user_email']
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_validates_severity_values()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/audit?severity=invalid');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid severity value',
                    'details' => [
                        'field' => 'severity',
                        'value' => 'invalid',
                        'allowed' => ['high', 'medium', 'low', 'info']
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_returns_304_for_etag_match()
    {
        // First request to get ETag
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/audit?per_page=5');

        $etag = $response1->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with If-None-Match
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'If-None-Match' => $etag
        ])->get('/api/admin/security/audit?per_page=5');

        $response2->assertStatus(304);
    }

    /** @test */
    public function it_escapes_csv_injection_attempts()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'text/csv'
        ])->get('/api/admin/security/audit/export?action==1+2');

        $response->assertStatus(200);
        
        $csvContent = $response->getContent();
        
        // Check that CSV injection is escaped
        $this->assertStringNotContains('=1+2', $csvContent);
        $this->assertStringContains('"=1+2"', $csvContent);
    }

    /** @test */
    public function it_handles_malicious_csv_payloads()
    {
        $maliciousPayloads = [
            '@cmd',
            '=cmd',
            '+cmd',
            '-cmd',
            '\tcmd',
            '\rcmd',
            '\ncmd'
        ];

        foreach ($maliciousPayloads as $payload) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'text/csv'
            ])->get("/api/admin/security/audit/export?action=" . urlencode($payload));

            $response->assertStatus(200);
            
            $csvContent = $response->getContent();
            
            // Ensure payload is properly escaped
            $this->assertStringNotContains($payload, $csvContent);
            $this->assertStringContains('"' . $payload . '"', $csvContent);
        }
    }

    /** @test */
    public function it_requires_authentication()
    {
        $response = $this->get('/api/admin/security/kpis');

        $response->assertStatus(401)
            ->assertJson([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required'
                ]
            ]);
    }

    /** @test */
    public function it_requires_admin_privileges()
    {
        // Create regular user
        $regularUser = User::factory()->create(['role' => 'user']);
        $token = $regularUser->createToken('user')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json'
        ])->get('/api/admin/security/kpis');

        $response->assertStatus(403)
            ->assertJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Admin privileges required'
                ]
            ]);
    }

    /** @test */
    public function it_handles_invalid_json_in_request_body()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', 'invalid json');

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid JSON in request body'
                ]
            ]);
    }

    /** @test */
    public function it_validates_test_event_parameters()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ])->post('/api/admin/security/test-event', [
            'event' => 'invalid_event'
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid event type',
                    'details' => [
                        'field' => 'event',
                        'value' => 'invalid_event',
                        'allowed' => ['login_failed', 'key_revoked', 'session_ended']
                    ]
                ]
            ]);
    }
}
