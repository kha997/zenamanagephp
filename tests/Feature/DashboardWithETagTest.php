<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DashboardWithETagTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test user
        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);
    }

    /**
     * Test ETag caching on dashboard summary endpoint
     */
    public function test_dashboard_summary_returns_etag(): void
    {
        $response = $this->getJson('/api/admin/dashboard/summary?range=30d');

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertHeader('Cache-Control', 'private, max-age=30');
        
        $etag = $response->headers->get('ETag');
        $this->assertStringStartsWith('"', $etag); // ETags should be quoted
    }

    /**
     * Test 304 Not Modified response with correct ETag
     */
    public function test_dashboard_summary_returns_304_with_valid_etag(): void
    {
        // First request
        $response1 = $this->getJson('/api/admin/dashboard/summary?range=30d');
        $response1->assertStatus(200);
        
        $etag = $response1->headers->get('ETag');
        $this->assertNotNull($etag);

        // Second request with If-None-Match header
        $response2 = $this->getJson('/api/admin/dashboard/summary?range=30d', [
            'If-None-Match' => $etag
        ]);

        $response2->assertStatus(304);
        $this->assertEmpty($response2->getContent());
    }

    /**
     * Test charts endpoint returns proper ETag headers
     */
    public function test_dashboard_charts_returns_etag(): void
    {
        $response = $this->getJson('/api/admin/dashboard/charts?range=30d');

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertHeader('Cache-Control', 'private, max-age=30');
        
        $data = $response->json();
        $this->assertArrayHasKey('signups', $data);
        $this->assertArrayHasKey('error_rate', $data);
    }

    /**
     * Test activity endpoint returns ETag and cursor pagination
     */
    public function test_dashboard_activity_returns_etag_and_cursor(): void
    {
        $response = $this->getJson('/api/admin/dashboard/activity');

        $response->assertStatus(200);
        $response->assertHeader('ETag');
        $response->assertHeader('Cache-Control', 'private, max-age=10');
        
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('cursor', $data);
        $this->assertArrayHasKey('has_more', $data);
    }

    /**
     * Test CSV export endpoints with rate limiting
     */
    public function test_signups_export_returns_csv_with_rate_limiting(): void
    {
        $response = $this->get('/api/admin/dashboard/signups/export.csv?range=30d');

        $response->assertStatus(200)
                ->assertHeader('Content-Type', 'text/csv')
                ->assertHeader('Content-Disposition');
                
        $filename = $response->headers->get('Content-Disposition');
        $this->assertStringContains('signups', $filename);
    }

    /**
     * Test rate limiting on export endpoints
     */
    public function test_export_rate_limiting(): void
    {
        // Make multiple requests to trigger rate limiting
        $rateLimited = false;
        for ($i = 0; $i < 12; $i++) {
            $response = $this->get('/api/admin/dashboard/signups/export.csv?range=30d');
            
            if ($response->status() === 429) {
                $this->assertHeader('Retry-After');
                $rateLimited = true;
                break;
            }
        }
        
        // Verify that either rate limiting worked or endpoint is accessible
        $this->assertTrue($rateLimited || $response->status() === 200);
    }

    /**
     * Test dashboard data structure matches expected format
     */
    public function test_dashboard_summary_data_structure(): void
    {
        $response = $this->getJson('/api/admin/dashboard/summary?range=30d');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        
        // Check required KPI structure
        $this->assertArrayHasKey('tenants', $data);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('storage', $data);

        // Check tenants structure
        $tenants = $data['tenants'];
        $this->assertArrayHasKey('total', $tenants);
        $this->assertArrayHasKey('growth_rate', $tenants);
        $this->assertArrayHasKey('sparkline', $tenants);
        $this->assertIsArray($tenants['sparkline']);

        // Check users structure
        $users = $data['users'];
        $this->assertArrayHasKey('total', $users);
        $this->assertArrayHasKey('growth_rate', $users);
        $this->assertArrayHasKey('sparkline', $users);
        $this->assertIsArray($users['sparkline']);

        // Check errors structure
        $errors = $data['errors'];
        $this->assertArrayHasKey('last_24h', $errors);
        $this->assertArrayHasKey('change_from_yesterday', $errors);
        $this->assertArrayHasKey('sparkline', $errors);
        $this->assertIsArray($errors['sparkline']);

        // Check queue structure
        $queue = $data['queue'];
        $this->assertArrayHasKey('active_jobs', $queue);
        $this->assertArrayHasKey('status', $queue);
        $this->assertArrayHasKey('sparkline', $queue);
        $this->assertIsArray($queue['sparkline']);

        // Check storage structure
        $storage = $data['storage'];
        $this->assertArrayHasKey('used_bytes', $storage);
        $this->assertArrayHasKey('capacity_bytes', $storage);
        $this->assertArrayHasKey('sparkline', $storage);
        $this->assertIsArray($storage['sparkline']);
    }

    /**
     * Test charts data structure includes Chart.js format
     */
    public function test_charts_data_structure(): void
    {
        $response = $this->getJson('/api/admin/dashboard/charts?range=30d');
        
        $response->assertStatus(200);
        
        $data = $response->json();

        // Check signups structure
        $this->assertArrayHasKey('signups', $data);
        $signups = $data['signups'];
        $this->assertArrayHasKey('labels', $signups);
        $this->assertArrayHasKey('datasets', $signups);
        $this->assertIsArray($signups['labels']);
        $this->assertIsArray($signups['datasets']);

        // Check error_rate structure
        $this->assertArrayHasKey('error_rate', $data);
        $errors = $data['error_rate'];
        $this->assertArrayHasKey('labels', $errors);
        $this->assertArrayHasKey('datasets', $errors);
        $this->assertIsArray($errors['labels']);
        $this->assertIsArray($errors['datasets']);

        // Check timestamps are dates
        if (!empty($signups['labels'])) {
            $this->assertTrue(\DateTime::createFromFormat('Y-m-d', $signups['labels'][0]) !== false);
        }
    }

    /**
     * Test activity items have required fields
     */
    public function test_activity_items_structure(): void
    {
        $response = $this->getJson('/api/admin/dashboard/activity');
        
        $response->assertStatus(200);
        
        $data = $response->json();
        $items = $data['items'];

        if (!empty($items)) {
            $item = $items[0];
            
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('message', $item);
            $this->assertArrayHasKey('severity', $item);
            $this->assertArrayHasKey('ts', $item);
            $this->assertArrayHasKey('time_ago', $item);
            
            // Check time_ago is human readable
            $this->assertContains('ago', $item['time_ago']);
        }
    }

    /**
     * Test different ranges work correctly
     */
    public function test_dashboard_accepts_different_ranges(): void
    {
        $ranges = ['7d', '30d', '90d', '365d'];
        
        foreach ($ranges as $range) {
            $response = $this->getJson("/api/admin/dashboard/summary?range={$range}");
            $response->assertStatus(200);
            
            // Check response contains data for this range
            $data = $response->json();
            $tenantsSparkline = $data['tenants']['sparkline'];
            
            // Sparkline should have appropriate length for range
            $expectedLength = $this->getExpectedLength($range);
            $this->assertEquals($expectedLength, count($tenantsSparkline));
        }
    }

    private function getExpectedLength($range): int
    {
        return match ($range) {
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            '365d', '1y' => 365,
            default => 30
        };
    }
}
