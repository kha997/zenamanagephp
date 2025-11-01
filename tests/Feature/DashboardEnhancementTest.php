<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

class DashboardEnhancementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test ETag caching behavior for dashboard summary endpoint
     */
    public function test_dashboard_summary_etag_caching()
    {
        // First request should return 200 with ETag header
        $response1 = $this->getJson('http://localhost/admin/dashboard/summary');
        
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertTrue($response1->hasHeader('ETag'));
        $this->assertTrue($response1->hasHeader('Cache-Control'));
        
        $etag = $response1->header('ETag');
        $cacheControl = $response1->header('Cache-Control');
        
        $this->assertStringContains('private, max-age=30', $cacheControl);
        
        // Second request with same ETag should return 304
        $response2 = $this->getJson('http://localhost/admin/dashboard/summary', [
            'headers' => ['If-None-Match' => $etag]
        ]);
        
        $this->assertEquals(304, $response2->getStatusCode());
        $this->assertEmpty($response2->getContent());
    }

    /**
     * Test dashboard charts endpoint with ETag support
     */
    public function test_dashboard_charts_etag_caching()
    {
        $response1 = $this->getJson('http://localhost/admin/dashboard/charts');
        
        $this->assertEquals(200, $response1->getStatusCode());
        $this->assertTrue($response1->hasHeader('ETag'));
        
        $etag = $response1->header('ETag');
        
        // Test 304 response
        $response2 = $this->getJson('http://localhost/admin/dashboard/charts', [
            'headers' => ['If-None-Match' => $etag]
        ]);
        
        $this->assertEquals(304, $response2->getStatusCode());
    }

    /**
     * Test activity endpoint with cursor-based pagination
     */
    public function test_dashboard_activity_cursor_pagination()
    {
        $response = $this->getJson('http://localhost/admin/dashboard/activity');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $response->json();
        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('cursor', $data);
        $this->assertArrayHasKey('has_more', $data);
        
        // Test cursor-based pagination
        if (!empty($data['cursor'])) {
            $nextPageResponse = $this->getJson('http://localhost/admin/dashboard/activity?cursor=' . $data['cursor']);
            
            $this->assertEquals(200, $nextPageResponse->getStatusCode());
            $nextPageData = $nextPageResponse->json();
            
            $this->assertArrayHasKey('items', $nextPageResponse);
            $this->assertNotEquals($data['items'], $nextPageData['items']);
        }
    }

    /**
     * Test export rate limiting for CSV downloads
     */
    public function test_csv_export_rate_limiting()
    {
        // Make multiple export requests
        $requests = [];
        for ($i = 0; $i < 12; $i++) { // Exceed 10 requests/minute limit
            $requests[] = $this->get('http://localhost/admin/dashboard/signups/export.csv');
        }
        
        // The 11th and 12th requests should be rate limited
        $rateLimitedResponse = $requests[10];
        
        $this->assertEquals(429, $rateLimitedResponse->getStatusCode());
        $this->assertTrue($rateLimitedResponse->hasHeader('Retry-After'));
        $this->assertTrue($rateLimitedResponse->hasHeader('X-RateLimit-Limit'));
        $this->assertTrue($rateLimitedResponse->hasHeader('X-RateLimit-Remaining'));
        
        $this->assertEquals('10', $rateLimitedResponse->header('X-RateLimit-Limit'));
        $this->assertEquals('0', $rateLimitedResponse->header('X-RateLimit-Remaining'));
    }

    /**
     * Test CSV export headers and content
     */
    public function test_csv_export_headers()
    {
        $response = $this->get('http://localhost/admin/dashboard/signups/export.csv?range=30d');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Check headers
        $this->assertEquals('text/csv', $response->header('Content-Type'));
        $this->assertStringContains('attachment', $response->header('Content-Disposition'));
        
        // Check CSV content
        $content = $response->getContent();
        $lines = explode("\n", trim($content));
        
        $this->assertGreaterThan(1, count($lines)); // At least header + one data row
        $this->assertEquals('Date,Value', $lines[0]); // Check header
        
        // Check data rows have comma-separated format
        $this->assertStringContains(',', $lines[1]);
    }

    /**
     * Test dashboard response structure
     */
    public function test_dashboard_data_structure()
    {
        $response = $this->getJson('http://localhost/admin/dashboard/summary');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $response->json();
        
        // Check KPI structure
        $this->assertArrayHasKey('tenants', $data);
        $this->assertArrayHasKey('users', $data);
        $this->assertArrayHasKey('errors', $data);
        $this->assertArrayHasKey('queue', $data);
        $this->assertArrayHasKey('storage', $data);
        
        // Check tenants KPI
        $this->assertArrayHasKey('total', $data['tenants']);
        $this->assertArrayHasKey('growth_rate', $data['tenants']);
        $this->assertArrayHasKey('sparkline', $data['tenants']);
        
        // Check sparkline data
        $this->assertIsArray($data['tenants']['sparkline']);
        $this->assertGreaterThan(0, count($data['tenants']['sparkline']));
    }

    /**
     * Test charts data structure
     */
    public function test_charts_data_structure()
    {
        $response = $this->getJson('http://localhost/admin/dashboard/charts');
        
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = $response->json();
        
        // Check chart types
        $this->assertArrayHasKey('signups', $data);
        $this->assertArrayHasKey('error_rate', $data);
        
        // Check Chart.js structure
        $this->assertArrayHasKey('labels', $data['signups']);
        $this->assertArrayHasKey('datasets', $data['signups']);
        
        $this->assertIsArray($data['signups']['datasets']);
        $this->assertArrayHasKey('label', $data['signups']['datasets'][0]);
        $this->assertArrayHasKey('data', $data['signups']['datasets'][0]);
    }

    /**
     * Test dashboard performance timing
     */
    public function test_dashboard_performance()
    {
        $start = microtime(true);
        
        $response = $this->getJson('http://localhost/admin/dashboard/summary');
        
        $end = microtime(true);
        $duration = ($end - $start) * 1000; // Convert to milliseconds
        
        $this->assertEquals(200, $response->getStatusCode());
        
        // Dashboard should respond within 300ms (p95 requirement)
        $this->assertLessThan(300, $duration, 
            "Dashboard response time {$duration}ms exceeds 300ms threshold"
        );
    }

    /**
     * Test cache efficiency with 304 responses
     */
    public function test_cache_efficiency()
    {
        // First request - cold cache
        $start1 = microtime(true);
        $response1 = $this->getJson('http://localhost/admin/dashboard/summary');
        $end1 = microtime(true);
        $coldDuration = ($end1 - $start1) * 1000;
        
        $etag = $response1->header('ETag');
        
        // Second request with ETag - should be faster
        $start2 = microtime(true);
        $response2 = $this->getJson('http://localhost/admin/dashboard/summary', [
            'headers' => ['If-None-Match' => $etag]
        ]);
        $end2 = microtime(true);
        $cachedDuration = ($end2 - $start2) * 1000;
        
        $this->assertEquals(304, $response2->getStatusCode());
        
        // 304 response should be significantly faster
        $this->assertLessThan($coldDuration * 0.5, $cachedDuration, 
            "304 response not significantly faster than initial request"
        );
    }
}
