<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

class CsrfMiddlewareDebugTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Debug CSRF middleware execution
     */
    public function test_csrf_middleware_debug(): void
    {
        // Create a route that logs middleware execution
        Route::post('/test-middleware-debug', function () {
            return response()->json([
                'success' => true,
                'middleware_executed' => true,
                'csrf_token' => csrf_token(),
                'session_id' => session()->getId()
            ]);
        })->middleware('web');
        
        // Test without CSRF token
        $response = $this->post('/test-middleware-debug', [
            'data' => 'test'
        ]);
        
        dump('Status without CSRF:', $response->getStatusCode());
        dump('Content without CSRF:', $response->getContent());
        
        // Test with CSRF token
        $response = $this->post('/test-middleware-debug', [
            'data' => 'test',
            '_token' => csrf_token()
        ]);
        
        dump('Status with CSRF:', $response->getStatusCode());
        dump('Content with CSRF:', $response->getContent());
        
        $this->assertTrue(true);
    }
}
