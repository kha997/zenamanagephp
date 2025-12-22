<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsrfDebugTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Debug CSRF middleware
     */
    public function test_csrf_debug(): void
    {
        // Create a simple route with web middleware
        \Illuminate\Support\Facades\Route::post('/test-csrf-debug', function () {
            return response()->json(['success' => true, 'csrf_checked' => true]);
        })->middleware('web');
        
        // Test without CSRF token
        $response = $this->post('/test-csrf-debug', [
            'data' => 'test'
        ]);
        
        dump('Response status without CSRF:', $response->getStatusCode());
        dump('Response content:', $response->getContent());
        
        // Test with CSRF token
        $response = $this->post('/test-csrf-debug', [
            'data' => 'test',
            '_token' => csrf_token()
        ]);
        
        dump('Response status with CSRF:', $response->getStatusCode());
        dump('Response content:', $response->getContent());
        
        $this->assertTrue(true); // Just for debugging
    }
}
