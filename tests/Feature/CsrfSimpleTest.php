<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsrfSimpleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test CSRF with minimal setup
     */
    public function test_csrf_simple(): void
    {
        // Test with register route
        $response = $this->post('/register', [
            'name' => 'Test',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123'
        ]);
        
        dump('Register route status:', $response->getStatusCode());
        dump('Register route content:', $response->getContent());
        
        // Test with login route
        $response = $this->post('/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);
        
        dump('Login route status:', $response->getStatusCode());
        dump('Login route content:', $response->getContent());
        
        $this->assertTrue(true);
    }
}
