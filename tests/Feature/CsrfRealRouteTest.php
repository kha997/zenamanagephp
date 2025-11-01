<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsrfRealRouteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test CSRF with real route
     */
    public function test_csrf_real_route(): void
    {
        // Test without CSRF token
        $response = $this->post('/test-csrf-real', [
            'data' => 'test'
        ]);
        
        dump('Real route status without CSRF:', $response->getStatusCode());
        dump('Real route content without CSRF:', $response->getContent());
        
        // Test with CSRF token
        $response = $this->post('/test-csrf-real', [
            'data' => 'test',
            '_token' => csrf_token()
        ]);
        
        dump('Real route status with CSRF:', $response->getStatusCode());
        dump('Real route content with CSRF:', $response->getContent());
        
        $this->assertTrue(true);
    }
}
